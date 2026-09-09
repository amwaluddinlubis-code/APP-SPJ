<?php

namespace App\Http\Controllers;

use App\Jobs\SynchronizeArkasImport;
use App\Models\ArkasImportProfile;
use App\Models\ArkasSource;
use App\Models\BackgroundOperation;
use App\Models\FiscalYear;
use App\Models\School;
use App\Services\ArkasDatabaseExplorer;
use App\Services\ArkasDomainAdapter;
use App\Services\ArkasGenericImportService;
use App\Services\ArkasReconciliationService;
use App\Services\ArkasSourceKeyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ArkasImporterController implements HasMiddleware
{
    /** @return array<int, string> */
    public static function middleware(): array
    {
        return ['active-school', 'active-year'];
    }

    public function __invoke(Request $request, ArkasDatabaseExplorer $explorer, ArkasSourceKeyResolver $sourceKeys): View
    {
        $source = ArkasSource::query()->where('school_id', session('active_school_id'))->first();
        $profiles = ArkasImportProfile::query()->where('source_table', 'not like', '__%')->latest('source_table')->get();
        $tables = [];
        $columns = [];
        $rows = [];
        $selectedTable = trim((string) $request->query('table'));
        $profile = $selectedTable !== '' ? $profiles->firstWhere('source_table', $selectedTable) : null;
        $targetDomains = ArkasDomainAdapter::targetDomains();
        $preset = ArkasDomainAdapter::presetFor($selectedTable);
        $effectiveTargetDomain = $profile?->target_domain ?: $preset['target_domain'];
        $effectiveMapping = filled($profile?->mapping) ? $profile->mapping : $preset['mapping'];
        $effectiveSourceKeyColumn = $profile?->source_key_column ?: $preset['source_key_column'];
        $currentStatus = null;
        if ($selectedTable !== '' && session('active_fiscal_year_id')) {
            $targetTable = ArkasDomainAdapter::targetTable($effectiveTargetDomain);
            if ($targetTable) {
                $query = DB::connection('school')->table($targetTable);
                if ($targetTable !== 'arkas_periods') {
                    $query->where('fiscal_year_id', session('active_fiscal_year_id'));
                }
                $currentStatus = ['target_table' => $targetTable, 'rows' => $query->count(), 'profile' => $profile?->label];
            }
        }
        $limit = min(max($request->integer('limit', 25), 1), 100);
        $error = null;
        $recentRun = $profile?->runs()->latest()->first();
        $runHistory = $profile?->runs()->latest()->limit(10)->get() ?? collect();
        $reconciliation = session('arkas_reconciliation_preview');
        $schemaDrift = ['new' => [], 'missing' => []];
        $previewDiff = null;

        if ($source) {
            try {
                $tables = $explorer->tables($source);
                if ($selectedTable !== '' && in_array($selectedTable, $tables, true)) {
                    ['columns' => $columns, 'rows' => $rows] = $explorer->inspect($source, $selectedTable, $limit);
                    if ($profile) {
                        $currentColumns = array_map(static fn (array $column): string => $column['name'], $columns);
                        $knownColumns = $profile->source_columns ?? [];
                        $schemaDrift['missing'] = array_values(array_diff($knownColumns, $currentColumns));
                        $schemaDrift['new'] = $knownColumns === [] ? [] : array_values(array_diff($currentColumns, $knownColumns));
                    }
                    if (! $profile) {
                        $effectiveMapping = ArkasDomainAdapter::initialMapping($columns, $preset);
                    }
                    if ($profile) {
                        $staged = DB::connection('school')->table('arkas_import_rows')
                            ->where('profile_id', $profile->id)
                            ->where('fiscal_year_id', session('active_fiscal_year_id'))
                            ->pluck('payload_hash', 'source_key');
                        $previewDiff = ['new' => 0, 'changed' => 0, 'unchanged' => 0, 'sample' => count($rows)];
                        foreach ($rows as $row) {
                            $key = $sourceKeys->resolve($row, $effectiveSourceKeyColumn);
                            $payload = json_encode($row, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
                            $hash = hash('sha256', $payload);
                            if (! $staged->has($key)) {
                                $previewDiff['new']++;
                            } elseif ($staged->get($key) !== $hash) {
                                $previewDiff['changed']++;
                            } else {
                                $previewDiff['unchanged']++;
                            }
                        }
                    }
                }
            } catch (\Throwable $exception) {
                $error = $exception->getMessage();
            }
        } else {
            $error = 'Sumber database ARKAS untuk sekolah aktif belum dikonfigurasi.';
        }

        return view('arkas.importer', compact('tables', 'columns', 'rows', 'selectedTable', 'limit', 'error', 'source', 'profiles', 'profile', 'targetDomains', 'preset', 'effectiveTargetDomain', 'effectiveMapping', 'effectiveSourceKeyColumn', 'currentStatus', 'recentRun', 'runHistory', 'previewDiff', 'reconciliation', 'schemaDrift'));
    }

    public function store(Request $request, ArkasDatabaseExplorer $explorer): RedirectResponse
    {
        $data = $request->validate([
            'source_table' => ['required', 'string', 'max:120'],
            'target_domain' => ['required', 'in:'.implode(',', array_keys(ArkasDomainAdapter::targetDomains()))],
            'label' => ['required', 'string', 'max:160'],
            'source_key_column' => ['nullable', 'string', 'max:120'],
            'year_column' => ['nullable', 'string', 'max:120'],
            'fund_source_column' => ['nullable', 'string', 'max:120'],
            'source_updated_column' => ['nullable', 'string', 'max:120'],
            'sync_mode' => ['required', 'in:incremental,upsert,full_refresh'],
            'mapping' => ['nullable', 'array'],
        ]);
        $source = ArkasSource::query()->where('school_id', session('active_school_id'))->firstOrFail();
        abort_unless(in_array($data['source_table'], $explorer->tables($source), true), 422, 'Tabel ARKAS tidak ditemukan.');
        $sourceColumns = array_map(static fn (array $column): string => $column['name'], $explorer->inspect($source, $data['source_table'], 1)['columns']);

        $mapping = array_filter($data['mapping'] ?? [], static fn (mixed $role): bool => $role !== 'ignore' && filled($role));
        $mappingErrors = app(ArkasReconciliationService::class)->validateMapping($mapping, $data['source_key_column'] ?? null, $data['target_domain']);
        if ($data['sync_mode'] === 'incremental' && blank($data['source_updated_column'] ?? null)) {
            $mappingErrors[] = 'Mode Incremental memerlukan kolom terakhir berubah.';
        }
        if ($mappingErrors !== []) {
            return back()->withInput()->withErrors(['mapping' => $mappingErrors]);
        }
        ArkasImportProfile::query()->updateOrCreate(
            ['source_table' => $data['source_table']],
            [...$data, 'is_enabled' => true, 'mapping' => $mapping, 'source_columns' => $sourceColumns],
        );

        return redirect()->route('arkas.importer', ['table' => $data['source_table']])->with('success', 'Konfigurasi mapping tersimpan.');
    }

    public function preview(int $profileId, ArkasReconciliationService $reconciliation): RedirectResponse
    {
        $profile = ArkasImportProfile::query()->findOrFail($profileId);
        $year = FiscalYear::query()->findOrFail(session('active_fiscal_year_id'));
        $source = ArkasSource::query()->where('school_id', session('active_school_id'))->firstOrFail();
        try {
            return redirect()->route('arkas.importer', ['table' => $profile->source_table])->with('arkas_reconciliation_preview', $reconciliation->preview($profile, $year, $source));
        } catch (\Throwable $exception) {
            return redirect()->route('arkas.importer', ['table' => $profile->source_table])->with('error', 'Preview rekonsiliasi gagal: '.$exception->getMessage());
        }
    }

    public function sync(int $profileId, ArkasGenericImportService $importer, ArkasReconciliationService $reconciliation): RedirectResponse
    {
        $profile = ArkasImportProfile::query()->findOrFail($profileId);
        $year = FiscalYear::query()->findOrFail(session('active_fiscal_year_id'));
        $source = ArkasSource::query()->where('school_id', session('active_school_id'))->firstOrFail();
        $mappingErrors = $reconciliation->validateMapping($profile->mapping ?? [], $profile->source_key_column, $profile->target_domain);
        if ($profile->sync_mode === 'incremental' && blank($profile->source_updated_column)) {
            $mappingErrors[] = 'Mode Incremental memerlukan kolom terakhir berubah.';
        }
        if ($mappingErrors !== []) {
            return redirect()->route('arkas.importer', ['table' => $profile->source_table])->withErrors(['mapping' => $mappingErrors]);
        }
        if (config('queue.arkas_sync_async')) {
            $school = School::query()->findOrFail(session('active_school_id'));
            $operation = BackgroundOperation::query()->create([
                'school_id' => $school->id, 'fiscal_year_id' => $year->id, 'requested_by' => request()->user()?->id,
                'type' => 'ARKAS_IMPORT', 'status' => 'QUEUED', 'progress' => 0,
                'message' => 'Import tabel '.$profile->source_table.' masuk antrean.',
            ]);
            SynchronizeArkasImport::dispatch($operation->id, $school->id, $profile->id, $year->id, $source->id)->onQueue('operations');

            return redirect()->route('arkas.importer', ['table' => $profile->source_table])->with('success', 'Import '.$profile->source_table.' masuk antrean. Histori akan diperbarui setelah worker menyelesaikan proses.');
        }
        try {
            $run = $importer->synchronize($profile, $year, $source);
        } catch (\Throwable $exception) {
            return redirect()->route('arkas.importer', ['table' => $profile->source_table])
                ->with('error', 'Sinkronisasi gagal: '.$exception->getMessage());
        }

        return redirect()->route('arkas.importer', ['table' => $profile->source_table])->with('success', "Sinkronisasi {$profile->source_table} selesai: {$run->records_written} baris tersimpan.");
    }
}
