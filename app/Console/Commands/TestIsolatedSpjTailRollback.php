<?php

namespace App\Console\Commands;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\School;
use App\Models\SpjDocument;
use App\Models\SpjPackage;
use App\Services\OperationalAuditService;
use App\Services\SpjDocumentNumberService;
use App\Services\SpjNumberingOrderService;
use App\Services\SpjPackageValidationService;
use App\Support\ActiveSpjContext;
use App\UseCases\Spj\SpjNumberingRollbackUseCase;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class TestIsolatedSpjTailRollback extends Command
{
    protected $signature = 'spj:test-tail-rollback-copy
        {npsn : NPSN sekolah pemilik baseline}
        {--database= : Path absolut SQLite copy baru dari baseline}
        {--year= : Tahun anggaran}
        {--quarter=1 : Triwulan 1 sampai 4}
        {--fund-source= : ID, kode, atau nama sumber dana}
        {--json : Tampilkan report JSON}';

    protected $description = 'Smoke test tail rollback pada copy SQLite: 1,2,3 -> rollback dari 2 -> sequence kembali 1 -> nomor 2 dapat dipakai kembali.';

    public function handle(
        SpjDocumentNumberService $numbers,
        SpjNumberingOrderService $order,
        SpjPackageValidationService $validator,
        OperationalAuditService $audit,
    ): int {
        $npsn = trim((string) $this->argument('npsn'));
        $copyPath = $this->absolutePath(trim((string) $this->option('database')));
        $year = (int) $this->option('year');
        $quarter = (int) $this->option('quarter');
        $fundSourceOption = trim((string) ($this->option('fund-source') ?? ''));

        if ($copyPath === '' || ! is_file($copyPath)) {
            $this->error('Path --database wajib menunjuk file SQLite copy yang sudah ada.');

            return self::FAILURE;
        }
        if ($year < 2000 || $year > 2100) {
            $this->error('Tahun anggaran tidak valid.');

            return self::FAILURE;
        }
        if ($quarter < 1 || $quarter > 4) {
            $this->error('Triwulan harus bernilai 1 sampai 4.');

            return self::FAILURE;
        }

        $school = School::query()->with('databaseRecord')->where('npsn', $npsn)->first();
        if (! $school) {
            $this->error('Sekolah dengan NPSN '.$npsn.' tidak ditemukan.');

            return self::FAILURE;
        }

        $baselinePaths = $this->resolveBaselinePaths($school);
        if ($baselinePaths === []) {
            $this->error('Baseline database sekolah tidak ditemukan.');

            return self::FAILURE;
        }
        foreach ($baselinePaths as $baselinePath) {
            if ($this->samePath($copyPath, $baselinePath)) {
                $this->error('DITOLAK: --database menunjuk salah satu baseline asli. Gunakan file copy terisolasi.');

                return self::FAILURE;
            }
        }

        $baselineHashesBefore = $this->hashPaths($baselinePaths);
        $copyHashBefore = hash_file('sha256', $copyPath);
        if ($baselineHashesBefore === null || $copyHashBefore === false) {
            $this->error('Hash database tidak dapat dibaca.');

            return self::FAILURE;
        }

        $originalSchoolConfig = config('database.connections.school');
        $report = null;
        $failureMessage = null;

        try {
            config()->set('database.connections.school.database', $copyPath);
            config()->set('database.connections.school.journal_mode', null);
            config()->set('database.connections.school.synchronous', null);
            DB::purge('school');
            DB::connection('school')->statement('PRAGMA foreign_keys=ON');

            $fundSourceId = $this->resolveFundSourceId($year, $fundSourceOption);
            $fiscalYear = FiscalYear::query()
                ->where('year', $year)
                ->where('fund_source_id', $fundSourceId)
                ->first();
            if (! $fiscalYear) {
                throw new RuntimeException("Fiscal year {$year} untuk fund source {$fundSourceId} tidak ditemukan pada copy.");
            }

            [$dateFrom, $dateTo] = $this->quarterRange($year, $quarter);
            $scope = fn ($query) => $query
                ->where('fiscal_year_id', $fiscalYear->id)
                ->where('fund_source_id', $fundSourceId)
                ->whereBetween('transaction_date', [$dateFrom, $dateTo]);

            $existingNumbered = SpjDocument::query()
                ->where('document_type', 'SPJ')
                ->where('scope_key', 'MAIN')
                ->whereHas('package.transaction', $scope)
                ->whereIn('status', ['NUMBERED', 'FINAL', 'CANCELLED'])
                ->count();
            if ($existingNumbered !== 0) {
                throw new RuntimeException('Copy harus baru dari baseline dan belum memiliki histori penomoran SPJ pada scope ini. Ditemukan '.$existingNumbered.' dokumen.');
            }

            $packages = SpjPackage::query()
                ->with([
                    'documents',
                    'transaction.items',
                    'transaction.goods',
                    'transaction.goodsReceipts',
                    'transaction.workOrder',
                    'transaction.honors',
                    'transaction.travels',
                    'transaction.payments',
                    'transaction.workers',
                    'transaction.participants',
                    'transaction.serviceRecipients',
                    'transaction.spjPackage',
                ])
                ->where('status', 'READY')
                ->whereHas('transaction', $scope)
                ->get();
            $ordered = $order->orderedPackagesForDocumentType($packages, 'SPJ')->take(3)->values();
            if ($ordered->count() !== 3) {
                throw new RuntimeException('Tail rollback smoke test membutuhkan minimal 3 Paket READY pada scope copy; ditemukan '.$ordered->count().'.');
            }

            foreach ($ordered as $package) {
                $issues = $validator->validateForNumbering($package);
                if ($issues !== []) {
                    throw new RuntimeException('Paket '.$package->id.' gagal preflight: '.collect($issues)->pluck('message')->implode(' '));
                }
                $numbers->assignAutomaticNumbers(
                    $package,
                    $school->school_code ?: $school->npsn,
                    $school->npsn,
                    ['SPJ'],
                );
            }

            $issued = SpjDocument::query()
                ->with('package.transaction')
                ->where('document_type', 'SPJ')
                ->where('scope_key', 'MAIN')
                ->where('status', 'NUMBERED')
                ->whereHas('package.transaction', $scope)
                ->orderBy('sequence_number')
                ->get();
            if ($issued->count() !== 3 || $issued->pluck('sequence_number')->map(fn ($value): int => (int) $value)->all() !== [1, 2, 3]) {
                throw new RuntimeException('Penerbitan awal harus menghasilkan tepat sequence 1,2,3.');
            }

            $sequenceBeforeRollback = $this->sequenceValue($fiscalYear->id, $fundSourceId);
            if ($sequenceBeforeRollback !== 3) {
                throw new RuntimeException('Sequence sebelum rollback harus 3; ditemukan '.$sequenceBeforeRollback.'.');
            }

            $context = new ActiveSpjContext($school->id, $fiscalYear->id, $fundSourceId, 0, true);
            $rollback = new SpjNumberingRollbackUseCase($audit, $context);
            $request = Request::create('/isolated-tail-rollback', 'POST', [
                'sequence_number' => 2,
                'reason' => 'Isolated runtime QA: tail rollback',
            ]);
            $request->setLaravelSession(app('session')->driver());
            app()->instance('request', $request);
            $response = $rollback->rollbackFromSequence($request);
            $error = (string) ($response->getSession()->get('error') ?? '');
            if ($error !== '') {
                throw new RuntimeException('Tail rollback ditolak: '.$error);
            }

            $activeAfterRollback = SpjDocument::query()
                ->where('document_type', 'SPJ')
                ->where('scope_key', 'MAIN')
                ->where('status', 'NUMBERED')
                ->whereHas('package.transaction', $scope)
                ->orderBy('sequence_number')
                ->get();
            if ($activeAfterRollback->count() !== 1 || (int) $activeAfterRollback->first()->sequence_number !== 1) {
                throw new RuntimeException('Setelah rollback dari 2, hanya sequence 1 yang boleh tetap aktif.');
            }

            $second = $ordered[1]->fresh();
            $third = $ordered[2]->fresh();
            if ($second->status !== 'DRAFT' || $third->status !== 'DRAFT') {
                throw new RuntimeException('Paket sequence 2 dan 3 harus kembali ke DRAFT setelah tail rollback.');
            }

            $sequenceAfterRollback = $this->sequenceValue($fiscalYear->id, $fundSourceId);
            if ($sequenceAfterRollback !== 1) {
                throw new RuntimeException('Sequence setelah rollback dari 2 harus kembali ke 1; ditemukan '.$sequenceAfterRollback.'.');
            }

            $second->forceFill(['status' => 'READY'])->save();
            $second = $second->fresh([
                'documents',
                'transaction.items',
                'transaction.goods',
                'transaction.goodsReceipts',
                'transaction.workOrder',
                'transaction.honors',
                'transaction.travels',
                'transaction.payments',
                'transaction.workers',
                'transaction.participants',
                'transaction.serviceRecipients',
                'transaction.spjPackage',
            ]);
            $issues = $validator->validateForNumbering($second);
            if ($issues !== []) {
                throw new RuntimeException('Paket sequence 2 gagal preflight saat dinomori ulang: '.collect($issues)->pluck('message')->implode(' '));
            }
            $numbers->assignAutomaticNumbers(
                $second,
                $school->school_code ?: $school->npsn,
                $school->npsn,
                ['SPJ'],
            );
            $renumbered = $second->documents()
                ->where('document_type', 'SPJ')
                ->where('scope_key', 'MAIN')
                ->where('status', 'NUMBERED')
                ->latest('id')
                ->first();
            if (! $renumbered || (int) $renumbered->sequence_number !== 2) {
                throw new RuntimeException('Nomor tail yang dilepas harus dapat diterbitkan kembali sebagai sequence 2.');
            }

            $sequenceAfterRenumber = $this->sequenceValue($fiscalYear->id, $fundSourceId);
            if ($sequenceAfterRenumber !== 2) {
                throw new RuntimeException('Sequence setelah renumber harus 2; ditemukan '.$sequenceAfterRenumber.'.');
            }

            $report = [
                'baseline_path' => $baselinePaths[0],
                'baseline_paths' => $baselinePaths,
                'copy_path' => $copyPath,
                'context' => [
                    'year' => $year,
                    'quarter' => $quarter,
                    'fund_source_id' => $fundSourceId,
                ],
                'issued' => $issued->map(fn (SpjDocument $document): array => [
                    'package_id' => $document->spj_package_id,
                    'no_bukti' => $document->package->transaction->no_bukti,
                    'document_number' => $document->document_number,
                    'sequence_number' => (int) $document->sequence_number,
                ])->all(),
                'rollback' => [
                    'from_sequence' => 2,
                    'sequence_before' => $sequenceBeforeRollback,
                    'sequence_after' => $sequenceAfterRollback,
                    'active_sequences_after' => $activeAfterRollback->pluck('sequence_number')->map(fn ($value): int => (int) $value)->all(),
                ],
                'renumber' => [
                    'package_id' => $second->id,
                    'no_bukti' => $second->transaction->no_bukti,
                    'document_number' => $renumbered->document_number,
                    'sequence_number' => (int) $renumbered->sequence_number,
                    'sequence_after' => $sequenceAfterRenumber,
                ],
            ];
        } catch (Throwable $exception) {
            $failureMessage = $exception->getMessage();
        } finally {
            DB::purge('school');
            config()->set('database.connections.school', $originalSchoolConfig);
        }

        foreach ($baselinePaths as $baselinePath) {
            clearstatcache(true, $baselinePath);
        }
        clearstatcache(true, $copyPath);
        $baselineHashesAfter = $this->hashPaths($baselinePaths);
        $copyHashAfter = hash_file('sha256', $copyPath);
        $baselineUnchanged = $baselineHashesAfter !== null && $this->sameHashes($baselineHashesBefore, $baselineHashesAfter);
        $copyChanged = $copyHashAfter !== false && ! hash_equals($copyHashBefore, $copyHashAfter);

        if ($failureMessage !== null) {
            $this->error('Isolated tail rollback test gagal: '.$failureMessage);
            $this->line('BASELINE HASH UNCHANGED: '.($baselineUnchanged ? 'YES' : 'NO'));

            return self::FAILURE;
        }
        if ($report === null || ! $baselineUnchanged || ! $copyChanged) {
            $this->error('Isolated tail rollback guarantee gagal: seluruh baseline harus tetap sama dan copy harus berubah.');

            return self::FAILURE;
        }

        $report['baseline_hash_unchanged'] = true;
        $report['copy_hash_changed'] = true;

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('SPJ ISOLATED TAIL ROLLBACK TEST');
        $this->line('Baseline hash       : UNCHANGED');
        $this->line('Copy hash           : CHANGED');
        $this->line('Scope               : '.$year.' / TW'.$quarter.' / Fund Source '.$report['context']['fund_source_id']);
        $this->line('Initial sequences   : 1,2,3');
        $this->line('Rollback from       : 2');
        $this->line('Sequence after      : '.$report['rollback']['sequence_after']);
        $this->line('Renumbered sequence : '.$report['renumber']['sequence_number']);
        $this->line('Sequence final      : '.$report['renumber']['sequence_after']);
        $this->info('ISOLATED TAIL ROLLBACK RESULT: PASS — tail 2-3 dilepas, checkpoint kembali 1, lalu sequence 2 dapat diterbitkan kembali; baseline tidak berubah.');

        return self::SUCCESS;
    }

    private function sequenceValue(int $fiscalYearId, int $fundSourceId): int
    {
        return (int) (DB::connection('school')->table('document_number_sequences')
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('fund_source_id', $fundSourceId)
            ->where('format_name', 'SPJ')
            ->value('last_number') ?? 0);
    }

    private function resolveFundSourceId(int $year, string $fundSource): int
    {
        if ($fundSource !== '') {
            if (ctype_digit($fundSource)) {
                $id = (int) $fundSource;
                if (! FundSource::query()->whereKey($id)->exists()) {
                    throw new RuntimeException('Fund source ID '.$id.' tidak ditemukan pada copy.');
                }

                return $id;
            }

            $resolved = FundSource::query()
                ->whereRaw('LOWER(code) = ?', [mb_strtolower($fundSource)])
                ->orWhereRaw('LOWER(name) = ?', [mb_strtolower($fundSource)])
                ->first();
            if (! $resolved) {
                throw new RuntimeException('Fund source '.$fundSource.' tidak ditemukan pada copy.');
            }

            return (int) $resolved->id;
        }

        $ids = FiscalYear::query()
            ->where('year', $year)
            ->whereNotNull('fund_source_id')
            ->pluck('fund_source_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
        if ($ids->count() !== 1) {
            throw new RuntimeException('Gunakan --fund-source karena tahun '.$year.' tidak memiliki tepat satu sumber dana.');
        }

        return (int) $ids->first();
    }

    /** @return array{0:string,1:string} */
    private function quarterRange(int $year, int $quarter): array
    {
        $startMonth = (($quarter - 1) * 3) + 1;
        $from = Carbon::create($year, $startMonth, 1)->startOfMonth();
        $to = $from->copy()->addMonths(2)->endOfMonth();

        return [$from->toDateString(), $to->toDateString()];
    }

    /** @return list<string> */
    private function resolveBaselinePaths(School $school): array
    {
        $managedPath = rtrim((string) config('spj.data_path'), '/\\')
            .DIRECTORY_SEPARATOR.'school-databases'
            .DIRECTORY_SEPARATOR.preg_replace('/[^A-Za-z0-9_-]/', '_', $school->npsn)
            .DIRECTORY_SEPARATOR.'spj.sqlite';
        $paths = [$managedPath, (string) ($school->databaseRecord?->database_path ?? '')];
        $resolved = [];

        foreach (array_filter($paths) as $path) {
            $candidate = $this->absolutePath($path);
            if (! is_file($candidate)) {
                continue;
            }
            if (collect($resolved)->contains(fn (string $existing): bool => $this->samePath($candidate, $existing))) {
                continue;
            }
            $resolved[] = $candidate;
        }

        return $resolved;
    }

    /** @param list<string> $paths
     *  @return array<string,string>|null
     */
    private function hashPaths(array $paths): ?array
    {
        $hashes = [];
        foreach ($paths as $path) {
            $hash = hash_file('sha256', $path);
            if ($hash === false) {
                return null;
            }
            $hashes[$this->normalizedPath($path)] = $hash;
        }

        return $hashes;
    }

    /** @param array<string,string> $before
     *  @param array<string,string> $after
     */
    private function sameHashes(array $before, array $after): bool
    {
        if (array_keys($before) !== array_keys($after)) {
            return false;
        }
        foreach ($before as $path => $hash) {
            if (! isset($after[$path]) || ! hash_equals($hash, $after[$path])) {
                return false;
            }
        }

        return true;
    }

    private function samePath(string $a, string $b): bool
    {
        return $this->normalizedPath($a) === $this->normalizedPath($b);
    }

    private function normalizedPath(string $path): string
    {
        $real = realpath($path);
        $path = $real !== false ? $real : $this->absolutePath($path);
        $path = rtrim(str_replace('\\', '/', $path), '/');

        return PHP_OS_FAMILY === 'Windows' ? mb_strtolower($path) : $path;
    }

    private function absolutePath(string $path): string
    {
        if ($path === '') {
            return '';
        }
        if (str_starts_with($path, '/') || preg_match('~^[A-Za-z]:[\\\\/]~', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }
}
