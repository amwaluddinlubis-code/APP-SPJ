<?php

namespace App\Http\Controllers;

use App\Models\FiscalYear;
use App\Services\ArkasReferenceReadBoundary;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Read-only RKAS budget monitor based on synchronized ARKAS data. */
class RkasBudgetController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('rkas-budget.index', $this->renderData($request));
    }

    /**
     * Build the read-only data set consumed by the Livewire RKAS workspace.
     *
     * @return array<string, mixed>
     */
    public function renderData(Request $request): array
    {
        $yearId = (int) session('active_fiscal_year_id');
        $db = DB::connection('school');
        $search = trim((string) $request->query('q'));
        $fundSourceId = (int) session('active_fund_source_id');
        if ($rawData = $this->rawBudgetData($db, $yearId, $fundSourceId, $request)) {
            return $rawData;
        }
        $activityNames = $db->table('activity_references')->where('fiscal_year_id', $yearId)->get(['activity_code', 'activity_name'])->mapWithKeys(fn ($row): array => [trim((string) $row->activity_code, '.') => $row->activity_name])->all();
        $stagedActivityNames = $db->table('arkas_import_rows as rows')
            ->join('arkas_import_profiles as profiles', 'profiles.id', '=', 'rows.profile_id')
            ->whereRaw("lower(profiles.source_table) = 'ref_kode'")
            ->where(function ($query) use ($yearId): void {
                $query->where('rows.fiscal_year_id', $yearId)->orWhereNull('rows.fiscal_year_id');
            })
            ->pluck('rows.payload');
        foreach ($stagedActivityNames as $payload) {
            $reference = is_array($payload) ? $payload : json_decode((string) $payload, true);
            if (! is_array($reference)) {
                continue;
            }
            $reference = array_change_key_case($reference, CASE_UPPER);
            $code = trim((string) ($reference['ID_KODE'] ?? $reference['KODE_KEGIATAN'] ?? $reference['KODE'] ?? ''), '.');
            $name = trim((string) ($reference['URAIAN_KODE'] ?? $reference['NAMA_KEGIATAN'] ?? $reference['NAMA_KODE'] ?? $reference['NAMA'] ?? $reference['URAIAN'] ?? $reference['DESCRIPTION'] ?? ''));
            if ($code !== '' && $name !== '') {
                $activityNames[$code] = $name;
            }
        }
        $activityNames += [
            '03' => 'Standar Proses',
            '03.03' => 'Pelaksanaan Kegiatan Pembelajaran dan Ekstrakurikuler',
            '03.03.07' => 'Pelaksanaan Kegiatan Ekstrakurikuler (diluar Kepramukaan)',
        ];
        $hierarchyRows = $db->table('arkas_rkas_items')->where('fiscal_year_id', $yearId)->where('fund_source_id', $fundSourceId)->select(['activity_code', 'activity_name'])->distinct()->orderBy('activity_code')->get();
        $hierarchyOptions = $hierarchyRows->map(function ($row) use ($activityNames): array {
            $code = trim((string) ($row->activity_code ?? ''), '.');
            $parts = $code !== '' ? explode('.', $code) : [];
            $programCode = $parts[0] ?? '';
            $subprogramCode = count($parts) >= 2 ? implode('.', array_slice($parts, 0, 2)) : null;
            $subprogramName = $subprogramCode !== null ? ($activityNames[$subprogramCode] ?? 'Subprogram') : null;

            return ['program' => $programCode, 'program_name' => $activityNames[$programCode] ?? 'Program', 'subprogram' => $subprogramCode, 'subprogram_name' => $subprogramName, 'activity' => $code, 'activity_name' => $row->activity_name ?: 'Kegiatan belum diisi'];
        })->filter(fn (array $option): bool => $option['activity'] !== '')->values();
        $programOptions = $hierarchyOptions->filter(fn (array $option): bool => $option['program'] !== '')->unique('program')->values();
        $subprogramOptions = $hierarchyOptions->filter(fn (array $option): bool => $option['subprogram'] !== null)->unique('subprogram')->values();
        $activityOptions = $hierarchyOptions->unique('activity')->values();
        $programFilter = trim((string) $request->query('program'));
        $subprogramFilter = trim((string) $request->query('sub', $request->query('subprogram')));
        $activityFilter = trim((string) $request->query('kegiatan', $request->query('activity')));
        $isWithin = static fn (string $code, string $parent): bool => $code === $parent || str_starts_with($code, $parent.'.');
        $programCodes = $programOptions->pluck('program')->all();
        $subprogramCodes = $subprogramOptions->pluck('subprogram')->all();
        $activityCodes = $activityOptions->pluck('activity')->all();
        if (! in_array($programFilter, $programCodes, true)) {
            $programFilter = '';
        }
        if (! in_array($subprogramFilter, $subprogramCodes, true)
            || ($programFilter !== '' && ! $isWithin($subprogramFilter, $programFilter))) {
            $subprogramFilter = '';
        }
        if (! in_array($activityFilter, $activityCodes, true)
            || ($subprogramFilter !== '' && ! $isWithin($activityFilter, $subprogramFilter))
            || ($subprogramFilter === '' && $programFilter !== '' && ! $isWithin($activityFilter, $programFilter))) {
            $activityFilter = '';
        }
        if ($activityFilter !== '') {
            $activityParts = explode('.', $activityFilter);
            $programFilter = $activityParts[0];
            $subprogramFilter = count($activityParts) >= 2 ? implode('.', array_slice($activityParts, 0, 2)) : '';
        }
        $subprogramOptions = $subprogramOptions
            ->filter(fn (array $option): bool => $programFilter === '' || $isWithin((string) $option['subprogram'], $programFilter))
            ->values();
        $activityOptions = $activityOptions
            ->filter(fn (array $option): bool => ($subprogramFilter !== '' && $isWithin($option['activity'], $subprogramFilter))
                || ($subprogramFilter === '' && ($programFilter === '' || $isWithin($option['activity'], $programFilter))))
            ->values();
        $modeAliases = ['semua' => 'year', 'bulan' => 'month', 'triwulan' => 'quarter', 'semester' => 'semester'];
        $requestedMode = (string) $request->query('mode', '');
        $scope = isset($modeAliases[$requestedMode]) ? $modeAliases[$requestedMode] : (string) $request->query('scope', 'year');
        $scopeValue = (int) $request->query('periode', $request->query('scope_value', 0));
        if (! in_array($scope, ['month', 'quarter', 'semester', 'year'], true)) {
            $scope = 'year';
        }
        if (($scope === 'month' && ($scopeValue < 1 || $scopeValue > 12))
            || ($scope === 'quarter' && ($scopeValue < 1 || $scopeValue > 4))
            || ($scope === 'semester' && ($scopeValue < 1 || $scopeValue > 2))) {
            $scope = 'year';
            $scopeValue = 0;
        }
        $fiscalYearNumber = (int) (FiscalYear::query()->whereKey($yearId)->value('year') ?: now()->year);
        $periodMonths = match ($scope) {
            'month' => [$scopeValue],
            'quarter' => range((($scopeValue - 1) * 3) + 1, $scopeValue * 3),
            'semester' => range((($scopeValue - 1) * 6) + 1, $scopeValue * 6),
            default => range(1, 12),
        };

        $realization = $db->table('arkas_bku_rows as bku')
            ->selectRaw("json_extract(bku.payload, '$.ID_RAPBS') as source_rapbs_id, SUM(bku.amount) as realization, COUNT(*) as bku_count")
            ->where('bku.fiscal_year_id', $yearId)
            ->where('bku.fund_source_id', $fundSourceId)
            ->where('bku.category', 'BELANJA')
            ->whereNotNull('bku.no_bukti')
            ->where(function ($query): void {
                $query->where('bku.no_bukti', 'like', 'BPU%')
                    ->orWhere('bku.no_bukti', 'like', 'BNU%');
            });

        if ($scope !== 'year') {
            $periodIds = $db->table('arkas_rkas_periods')
                ->select('source_rapbs_period_id')
                ->where('fiscal_year_id', $yearId)
                ->where('fund_source_id', $fundSourceId)
                ->whereIn('month_number', $periodMonths)
                ->whereNotNull('source_rapbs_period_id');

            $realization->where(function ($scoped) use ($periodIds, $periodMonths, $fiscalYearNumber): void {
                $scoped->whereIn('bku.source_rapbs_period_id', $periodIds)
                    ->orWhere(function ($legacy) use ($periodMonths, $fiscalYearNumber): void {
                        $legacy->whereNull('bku.source_rapbs_period_id')
                            ->where(function ($dates) use ($periodMonths, $fiscalYearNumber): void {
                                foreach ($periodMonths as $month) {
                                    $start = Carbon::create($fiscalYearNumber, $month, 1)->startOfMonth()->toDateString();
                                    $end = Carbon::create($fiscalYearNumber, $month, 1)->endOfMonth()->toDateString();
                                    $dates->orWhereBetween('bku.transaction_date', [$start, $end]);
                                }
                            });
                    });
            });
        }

        $realization->groupByRaw("json_extract(bku.payload, '$.ID_RAPBS')");
        $applyBkuHierarchy = function ($builder) use ($db, $yearId, $fundSourceId, $programFilter, $subprogramFilter, $activityFilter, $search): void {
            if ($programFilter === '' && $subprogramFilter === '' && $activityFilter === '' && $search === '') {
                return;
            }
            $filter = $db->table('arkas_rkas_items as filter_rkas')
                ->select('filter_rkas.source_rapbs_id')
                ->where('filter_rkas.fiscal_year_id', $yearId)
                ->where('filter_rkas.fund_source_id', $fundSourceId);
            if ($programFilter !== '') {
                $filter->where(function ($nested) use ($programFilter): void {
                    $nested->where('filter_rkas.activity_code', $programFilter)->orWhere('filter_rkas.activity_code', 'like', $programFilter.'.%');
                });
            }
            if ($subprogramFilter !== '') {
                $filter->where(function ($nested) use ($subprogramFilter): void {
                    $nested->where('filter_rkas.activity_code', $subprogramFilter)->orWhere('filter_rkas.activity_code', 'like', $subprogramFilter.'.%');
                });
            }
            if ($activityFilter !== '') {
                $filter->whereIn('filter_rkas.activity_code', [$activityFilter, $activityFilter.'.']);
            }
            if ($search !== '') {
                $term = '%'.$search.'%';
                $filter->where(function ($nested) use ($term): void {
                    $nested->where('filter_rkas.account_code', 'like', $term)
                        ->orWhere('filter_rkas.activity_code', 'like', $term)
                        ->orWhere('filter_rkas.description', 'like', $term)
                        ->orWhere('filter_rkas.activity_name', 'like', $term);
                });
            }
            $builder->whereIn(DB::raw("json_extract(bku.payload, '$.ID_RAPBS')"), $filter);
        };
        $applyBkuHierarchy($realization);
        $periods = $db->table('arkas_rkas_periods')
            ->selectRaw('source_rapbs_id, SUM(amount) as scoped_amount, SUM(volume) as scoped_volume')
            ->where('fiscal_year_id', $yearId)
            ->where('fund_source_id', $fundSourceId);
        if ($scope === 'month') {
            $periods->where('month_number', $scopeValue);
        } elseif ($scope === 'quarter') {
            $periods->where('quarter_number', $scopeValue);
        } elseif ($scope === 'semester') {
            $periods->where('semester_number', $scopeValue);
        }
        $periods->groupBy('source_rapbs_id');
        if (in_array($scope, ['quarter', 'semester'], true)) {
            $twNumbers = $scope === 'quarter'
                ? [$scopeValue]
                : range((($scopeValue - 1) * 2) + 1, $scopeValue * 2);
            $twAmount = implode(' + ', array_map(fn (int $quarter): string => "COALESCE(CAST(json_extract(payload, '$.TW_{$quarter}') AS REAL), 0)", $twNumbers));
            $twVolume = implode(' + ', array_map(fn (int $quarter): string => "COALESCE(CAST(json_extract(payload, '$.VOL_TW{$quarter}') AS REAL), 0)", $twNumbers));
            $periods = $db->table('arkas_rkas_items')
                ->where('fiscal_year_id', $yearId)
                ->where('fund_source_id', $fundSourceId)
                ->selectRaw("source_rapbs_id, ({$twAmount}) as scoped_amount, ({$twVolume}) as scoped_volume");
        }
        [$programNames, $subprogramNames] = $this->hierarchyLevelNames($db, $yearId);
        $query = $db->table('arkas_rkas_items as r')->leftJoinSub($realization, 'b', fn ($join) => $join->on('b.source_rapbs_id', '=', 'r.source_rapbs_id'))->where('r.fiscal_year_id', $yearId)->where('r.fund_source_id', $fundSourceId)->selectRaw('r.*, COALESCE(b.realization, 0) as realization, COALESCE(b.bku_count, 0) as bku_count');
        if ($scope !== 'year') {
            $query->joinSub($periods, 'p', fn ($join) => $join->on('p.source_rapbs_id', '=', 'r.source_rapbs_id'))
                ->addSelect('p.scoped_amount', 'p.scoped_volume');
        }
        if ($programFilter !== '') {
            $query->where(function ($filter) use ($programFilter): void {
                $filter->where('r.activity_code', $programFilter)->orWhere('r.activity_code', 'like', $programFilter.'.%');
            });
        }
        if ($subprogramFilter !== '') {
            $query->where(function ($filter) use ($subprogramFilter): void {
                $filter->where('r.activity_code', $subprogramFilter)->orWhere('r.activity_code', 'like', $subprogramFilter.'.%');
            });
        }
        if ($activityFilter !== '') {
            $query->whereIn('r.activity_code', [$activityFilter, $activityFilter.'.']);
        }
        if ($search !== '') {
            $query->where(function ($filter) use ($search) {
                $term = '%'.$search.'%';
                $filter->where('r.account_code', 'like', $term)->orWhere('r.activity_code', 'like', $term)->orWhere('r.description', 'like', $term)->orWhere('r.activity_name', 'like', $term);
            });
        }
        $rows = $query->orderBy('r.activity_code')->orderBy('r.account_code')->get();
        $rows->transform(function ($item) {
            $payload = json_decode($item->payload, true) ?: [];
            $item->volume = (float) ($item->scoped_volume ?? $payload['VOLUME_TOTAL'] ?? 0);
            $item->unit = $payload['SATUAN'] ?? '—';
            $item->unit_price = (float) ($payload['HARGA_SATUAN'] ?? 0);
            $item->display_amount = (float) ($item->scoped_amount ?? $item->amount);
            $item->source_realization = (float) $item->realization;
            $item->realization = (float) $item->realization;
            $item->variance = $item->display_amount - $item->realization;

            return $item;
        });
        $hierarchyTree = [];
        foreach ($rows as $item) {
            $activityCode = trim((string) ($item->activity_code ?? ''), '.');
            $codeParts = $activityCode !== '' ? explode('.', $activityCode) : [];
            $programCode = $codeParts[0] ?? 'tanpa-program';
            $subprogramCode = count($codeParts) >= 2 ? implode('.', array_slice($codeParts, 0, 2)) : $programCode;
            $activityKey = $activityCode !== '' ? $activityCode : 'tanpa-kegiatan';
            if (! isset($hierarchyTree[$programCode])) {
                $hierarchyTree[$programCode] = [
                    'code' => $programCode,
                    'name' => $programNames[$programCode] ?? $activityNames[$programCode] ?? 'Program',
                    'amount' => 0.0,
                    'realization' => 0.0,
                    'remaining' => 0.0,
                    'subs' => [],
                ];
            }
            if (! isset($hierarchyTree[$programCode]['subs'][$subprogramCode])) {
                $hierarchyTree[$programCode]['subs'][$subprogramCode] = [
                    'code' => $subprogramCode,
                    'name' => $subprogramNames[$subprogramCode] ?? $activityNames[$subprogramCode] ?? 'Subprogram',
                    'amount' => 0.0,
                    'realization' => 0.0,
                    'remaining' => 0.0,
                    'activities' => [],
                ];
            }
            if (! isset($hierarchyTree[$programCode]['subs'][$subprogramCode]['activities'][$activityKey])) {
                $hierarchyTree[$programCode]['subs'][$subprogramCode]['activities'][$activityKey] = [
                    'code' => $activityCode !== '' ? $activityCode : 'Tanpa kode kegiatan',
                    'name' => $item->activity_name ?: 'Kegiatan belum diisi',
                    'amount' => 0.0,
                    'realization' => 0.0,
                    'remaining' => 0.0,
                    'items' => [],
                ];
            }
            $hierarchyTree[$programCode]['amount'] += $item->display_amount;
            $hierarchyTree[$programCode]['realization'] += $item->realization;
            $hierarchyTree[$programCode]['remaining'] += $item->variance;
            $hierarchyTree[$programCode]['subs'][$subprogramCode]['amount'] += $item->display_amount;
            $hierarchyTree[$programCode]['subs'][$subprogramCode]['realization'] += $item->realization;
            $hierarchyTree[$programCode]['subs'][$subprogramCode]['remaining'] += $item->variance;
            $hierarchyTree[$programCode]['subs'][$subprogramCode]['activities'][$activityKey]['amount'] += $item->display_amount;
            $hierarchyTree[$programCode]['subs'][$subprogramCode]['activities'][$activityKey]['realization'] += $item->realization;
            $hierarchyTree[$programCode]['subs'][$subprogramCode]['activities'][$activityKey]['remaining'] += $item->variance;
            $hierarchyTree[$programCode]['subs'][$subprogramCode]['activities'][$activityKey]['items'][] = $item;
        }
        $hierarchyTree = array_values(array_map(function (array $program): array {
            $program['subs'] = array_values(array_map(function (array $sub): array {
                $sub['activities'] = array_values($sub['activities']);

                return $sub;
            }, $program['subs']));

            return $program;
        }, $hierarchyTree));
        $treeTotals = [
            'amount' => array_sum(array_column($hierarchyTree, 'amount')),
            'realization' => array_sum(array_column($hierarchyTree, 'realization')),
            'remaining' => array_sum(array_column($hierarchyTree, 'remaining')),
            'items' => $rows->count(),
        ];
        $budgetQuery = $db->table('arkas_rkas_items as r')
            ->where('r.fiscal_year_id', $yearId)
            ->where('r.fund_source_id', $fundSourceId);
        if ($programFilter !== '') {
            $budgetQuery->where(function ($filter) use ($programFilter): void {
                $filter->where('r.activity_code', $programFilter)->orWhere('r.activity_code', 'like', $programFilter.'.%');
            });
        }
        if ($subprogramFilter !== '') {
            $budgetQuery->where(function ($filter) use ($subprogramFilter): void {
                $filter->where('r.activity_code', $subprogramFilter)->orWhere('r.activity_code', 'like', $subprogramFilter.'.%');
            });
        }
        if ($activityFilter !== '') {
            $budgetQuery->whereIn('r.activity_code', [$activityFilter, $activityFilter.'.']);
        }
        if ($search !== '') {
            $budgetQuery->where(function ($filter) use ($search): void {
                $term = '%'.$search.'%';
                $filter->where('r.account_code', 'like', $term)
                    ->orWhere('r.activity_code', 'like', $term)
                    ->orWhere('r.description', 'like', $term)
                    ->orWhere('r.activity_name', 'like', $term);
            });
        }
        if ($scope === 'year') {
            $budget = (float) $budgetQuery->sum('r.amount');
            $remainingQuery = (clone $budgetQuery)
                ->leftJoinSub($realization, 'booked_rkas', fn ($join) => $join->on('booked_rkas.source_rapbs_id', '=', 'r.source_rapbs_id'));
            $remaining = (float) $remainingQuery->sum(DB::raw('r.amount - COALESCE(booked_rkas.realization, 0)'));
        } else {
            $periodBudgetQuery = (clone $budgetQuery)
                ->joinSub(clone $periods, 'budget_periods', fn ($join) => $join->on('budget_periods.source_rapbs_id', '=', 'r.source_rapbs_id'));
            $budget = (float) $periodBudgetQuery->sum('budget_periods.scoped_amount');
            $remainingQuery = (clone $budgetQuery)
                ->joinSub(clone $periods, 'budget_periods', fn ($join) => $join->on('budget_periods.source_rapbs_id', '=', 'r.source_rapbs_id'))
                ->leftJoinSub($realization, 'booked_rkas', fn ($join) => $join->on('booked_rkas.source_rapbs_id', '=', 'r.source_rapbs_id'));
            $remaining = (float) $remainingQuery->sum(DB::raw('budget_periods.scoped_amount - COALESCE(booked_rkas.realization, 0)'));
        }
        $spent = $budget - $remaining;
        $overBudget = max(0, -$remaining);
        $underBudget = max(0, $remaining);
        $activityCount = $db->table('arkas_rkas_items')->where('fiscal_year_id', $yearId)->where('fund_source_id', $fundSourceId)->distinct('activity_code')->count('activity_code');

        $periodLabel = match ($scope) {
            'month' => Carbon::create($fiscalYearNumber, $scopeValue, 1)->translatedFormat('F Y'),
            'quarter' => 'Triwulan '.$scopeValue.' · '.$fiscalYearNumber,
            'semester' => 'Semester '.$scopeValue.' · '.$fiscalYearNumber,
            default => 'Tahun anggaran '.$fiscalYearNumber,
        };

        $contextFundName = (string) ($db->table('fund_sources')->where('id', $fundSourceId)->value('name') ?: '');
        $contextLabel = trim($fiscalYearNumber.' · '.$contextFundName, ' ·');

        $filterContext = 'pada '.$periodLabel;
        $contextTrail = array_filter([$programFilter, $subprogramFilter, $activityFilter]);
        if ($contextTrail !== []) {
            $filterContext .= ' · '.implode(' › ', $contextTrail);
        }
        if ($search !== '') {
            $filterContext .= ' · pencarian "'.$search.'"';
        }

        return compact('hierarchyTree', 'treeTotals', 'filterContext', 'search', 'budget', 'spent', 'remaining', 'overBudget', 'underBudget', 'activityCount', 'scope', 'scopeValue', 'periodLabel', 'programFilter', 'subprogramFilter', 'activityFilter', 'contextLabel');
    }

    /**
     * Read RKAS directly from the generic raw mirror when the legacy projection
     * is absent. This keeps ARKAS as the source of truth and never writes to
     * arkas_rkas_items or other legacy tables.
     *
     * @return array<string, mixed>|null
     */
    private function rawBudgetData(object $db, int $yearId, int $fundSourceId, Request $request): ?array
    {
        if (! $db->getSchemaBuilder()->hasTable('arkas_raw_mirror_tables')) {
            return null;
        }

        $year = FiscalYear::query()->find($yearId);
        if (! $year) {
            return null;
        }

        $rawTable = $db->table('arkas_raw_mirror_tables')
            ->where('source_table', 'rapbs')
            ->where('status', 'ACTIVE')
            ->where('row_count', '>', 0)
            ->first();
        $budgetTable = $db->table('arkas_raw_mirror_tables')
            ->where('source_table', 'anggaran')
            ->where('status', 'ACTIVE')
            ->first();
        if ($rawTable === null || $budgetTable === null) {
            return null;
        }

        $budgetRows = [];
        foreach ($db->table('arkas_raw_mirror_rows')->where('mirror_table_id', $budgetTable->id)->get() as $row) {
            $payload = json_decode((string) $row->payload, true);
            if (! is_array($payload)) {
                continue;
            }
            $payload = array_change_key_case($payload, CASE_UPPER);
            if ((string) ($payload['TAHUN_ANGGARAN'] ?? '') === (string) $year->year
                && (int) ($payload['ID_REF_SUMBER_DANA'] ?? 0) === $fundSourceId
                && (string) ($payload['IS_APPROVE'] ?? '0') === '1'
                && (string) ($payload['IS_AKTIF'] ?? '0') === '1'
                && (string) ($payload['SOFT_DELETE'] ?? '0') !== '1') {
                $budgetRows[] = $payload;
            }
        }
        if ($budgetRows === []) {
            return null;
        }
        $modeAliases = ['semua' => 'year', 'bulan' => 'month', 'triwulan' => 'quarter', 'semester' => 'semester'];
        $requestedMode = (string) $request->query('mode', '');
        $scope = isset($modeAliases[$requestedMode]) ? $modeAliases[$requestedMode] : (string) $request->query('scope', 'year');
        $scopeValue = (int) $request->query('periode', $request->query('scope_value', 0));
        if (! in_array($scope, ['month', 'quarter', 'semester', 'year'], true)) {
            $scope = 'year';
        }
        if (($scope === 'month' && ($scopeValue < 1 || $scopeValue > 12))
            || ($scope === 'quarter' && ($scopeValue < 1 || $scopeValue > 4))
            || ($scope === 'semester' && ($scopeValue < 1 || $scopeValue > 2))) {
            $scope = 'year';
            $scopeValue = 0;
        }
        $latestRevision = max(array_map(fn (array $row): int => (int) ($row['IS_REVISI'] ?? 0), $budgetRows));
        $latestBudgets = array_values(array_filter($budgetRows, fn (array $row): bool => (int) ($row['IS_REVISI'] ?? 0) === $latestRevision));
        $latestUpdate = max(array_map(fn (array $row): string => (string) ($row['LAST_UPDATE'] ?? ''), $latestBudgets));
        $latestBudgets = array_values(array_filter($latestBudgets, fn (array $row): bool => (string) ($row['LAST_UPDATE'] ?? '') === $latestUpdate));
        $allowedBudgets = [];
        foreach ($latestBudgets as $budget) {
            $allowedBudgets[(string) ($budget['ID_ANGGARAN'] ?? '')] = true;
        }

        $referenceTable = $db->table('arkas_raw_mirror_tables')->where('source_table', 'ref_kode')->where('status', 'ACTIVE')->first();
        $references = [];
        $referenceNames = [];
        $legacyReferences = collect();
        if ($referenceTable !== null) {
            foreach ($db->table('arkas_raw_mirror_rows')->where('mirror_table_id', $referenceTable->id)->get() as $row) {
                $payload = json_decode((string) $row->payload, true);
                if (is_array($payload)) {
                    $legacyReferences->push(array_change_key_case($payload, CASE_UPPER));
                }
            }
        }
        $referenceRows = app(ArkasReferenceReadBoundary::class)->resolve('ref_kode', $legacyReferences, (string) session('active_school_id')) ?? collect();
        foreach ($referenceRows as $payload) {
            $payload = array_change_key_case($payload, CASE_UPPER);
            $references[(string) ($payload['ID_REF_KODE'] ?? '')] = $payload;
            $referenceNames[trim((string) ($payload['ID_KODE'] ?? ''), '.')] = (string) ($payload['URAIAN_KODE'] ?? '');
        }

        $periodTable = $db->table('arkas_raw_mirror_tables')->where('source_table', 'rapbs_periode')->where('status', 'ACTIVE')->first();
        $periodToRapbs = [];
        $periodCoordinates = [];
        $periodAmountsByRapbs = [];
        if ($periodTable !== null) {
            foreach ($db->table('arkas_raw_mirror_rows')->where('mirror_table_id', $periodTable->id)->get() as $row) {
                $payload = json_decode((string) $row->payload, true);
                if (is_array($payload)) {
                    $payload = array_change_key_case($payload, CASE_UPPER);
                    $periodId = (string) ($payload['ID_PERIODE'] ?? '');
                    $periodRapbsId = (string) ($payload['ID_RAPBS'] ?? '');
                    $periodRapbsKey = (string) ($payload['ID_RAPBS_PERIODE'] ?? '');
                    $month = (int) ($payload['BULAN'] ?? 0);
                    if ($month < 1 || $month > 12) {
                        $numericPeriodId = (int) $periodId;
                        $month = $numericPeriodId >= 81 && $numericPeriodId <= 92 ? $numericPeriodId - 80 : 0;
                    }
                    $quarter = $month > 0 ? (int) ceil($month / 3) : ((int) $periodId >= 1 && (int) $periodId <= 4 ? (int) $periodId : 0);
                    $semester = $month > 0 ? (int) ceil($month / 6) : ($quarter > 0 ? (int) ceil($quarter / 2) : 0);
                    if ($periodRapbsKey !== '') {
                        $periodToRapbs[$periodRapbsKey] = $periodRapbsId;
                        $periodCoordinates[$periodRapbsKey] = [$month, $quarter, $semester];
                    }
                    if ($periodRapbsId !== '' && $periodRapbsKey !== '') {
                        $periodAmountsByRapbs[$periodRapbsId][] = [
                            'amount' => (float) ($payload['JUMLAH'] ?? 0),
                            'month' => $month,
                            'quarter' => $quarter,
                            'semester' => $semester,
                        ];
                    }
                }
            }
        }

        $realizationByRapbs = [];
        $bkuTable = $db->table('arkas_raw_mirror_tables')->where('source_table', 'kas_umum')->where('status', 'ACTIVE')->first();
        if ($bkuTable !== null) {
            foreach ($db->table('arkas_raw_mirror_rows')->where('mirror_table_id', $bkuTable->id)->get() as $row) {
                $payload = json_decode((string) $row->payload, true);
                if (is_array($payload)) {
                    $payload = array_change_key_case($payload, CASE_UPPER);
                    $budgetKey = (string) ($payload['ID_ANGGARAN'] ?? '');
                    $periodKey = (string) ($payload['ID_RAPBS_PERIODE'] ?? '');
                    $rapbsKey = $periodToRapbs[$periodKey] ?? '';
                    $coordinates = $periodCoordinates[$periodKey] ?? [0, 0, 0];
                    $matchesScope = $scope === 'year'
                        || ($scope === 'month' && $coordinates[0] === $scopeValue)
                        || ($scope === 'quarter' && $coordinates[1] === $scopeValue)
                        || ($scope === 'semester' && $coordinates[2] === $scopeValue);
                    if (isset($allowedBudgets[$budgetKey]) && $rapbsKey !== '' && $matchesScope && str_starts_with((string) ($payload['TANGGAL_TRANSAKSI'] ?? ''), (string) $year->year) && in_array((string) ($payload['ID_REF_BKU'] ?? ''), ['4', '15'], true) && (string) ($payload['SOFT_DELETE'] ?? '0') !== '1') {
                        $amount = $payload['SALDO'] ?? $payload['JUMLAH'] ?? $payload['NILAI'] ?? 0;
                        $realizationByRapbs[$rapbsKey] = ($realizationByRapbs[$rapbsKey] ?? 0) + abs((float) $amount);
                    }
                }
            }
        }

        $items = collect();
        foreach ($db->table('arkas_raw_mirror_rows')->where('mirror_table_id', $rawTable->id)->get() as $row) {
            $payload = json_decode((string) $row->payload, true);
            if (! is_array($payload)) {
                continue;
            }
            $payload = array_change_key_case($payload, CASE_UPPER);
            $payload = array_change_key_case($payload, CASE_UPPER);
            if (! isset($allowedBudgets[(string) ($payload['ID_ANGGARAN'] ?? '')]) || (string) ($payload['SOFT_DELETE'] ?? '0') === '1') {
                continue;
            }
            $reference = $references[(string) ($payload['ID_REF_KODE'] ?? '')] ?? [];
            $activityCode = trim((string) ($reference['ID_KODE'] ?? $payload['KODE_KEGIATAN'] ?? ''), '.');
            $activityName = (string) ($reference['URAIAN_KODE'] ?? '');
            $sourceId = (string) ($payload['ID_RAPBS'] ?? $row->source_key);
            $item = (object) [
                'source_rapbs_id' => $sourceId,
                'activity_code' => $activityCode,
                'activity_name' => $activityName,
                'account_code' => (string) ($payload['KODE_REKENING'] ?? ''),
                'description' => (string) ($payload['URAIAN_TEXT'] ?? $payload['URAIAN'] ?? ''),
                'volume' => (float) ($payload['VOLUME'] ?? 0),
                'unit' => (string) ($payload['SATUAN'] ?? '—'),
                'unit_price' => (float) ($payload['HARGA_SATUAN'] ?? 0),
                'display_amount' => $this->rawScopedAmount($payload, $periodAmountsByRapbs, $scope, $scopeValue),
                'realization' => (float) ($realizationByRapbs[$sourceId] ?? 0),
                'bku_count' => 0,
            ];
            $items->push($item);
        }
        if ($items->isEmpty()) {
            return null;
        }

        $search = trim((string) $request->query('q'));
        if ($search !== '') {
            $items = $items->filter(function (object $item) use ($search): bool {
                return str_contains(strtolower(implode(' ', [(string) $item->activity_code, (string) $item->activity_name, (string) $item->account_code, (string) $item->description])), strtolower($search));
            })->values();
        }

        $programFilter = trim((string) $request->query('program'));
        $subprogramFilter = trim((string) $request->query('sub', $request->query('subprogram')));
        $activityFilter = trim((string) $request->query('kegiatan', $request->query('activity')));
        $within = static fn (string $code, string $parent): bool => $code === $parent || str_starts_with($code, $parent.'.');
        $items = $items->filter(fn (object $item): bool => ($programFilter === '' || $within($item->activity_code, $programFilter)) && ($subprogramFilter === '' || $within($item->activity_code, $subprogramFilter)) && ($activityFilter === '' || $item->activity_code === $activityFilter))->values();
        $items = $items->sort(function (object $left, object $right): int {
            $activityOrder = strnatcasecmp((string) $left->activity_code, (string) $right->activity_code);
            if ($activityOrder !== 0) {
                return $activityOrder;
            }

            $accountOrder = strnatcasecmp((string) $left->account_code, (string) $right->account_code);
            if ($accountOrder !== 0) {
                return $accountOrder;
            }

            return strnatcasecmp((string) $left->description, (string) $right->description);
        })->values();

        $hierarchyTree = [];
        foreach ($items as $item) {
            $parts = $item->activity_code !== '' ? explode('.', $item->activity_code) : [];
            $program = $parts[0] ?? 'tanpa-program';
            $subprogram = count($parts) >= 2 ? implode('.', array_slice($parts, 0, 2)) : $program;
            $activity = $item->activity_code !== '' ? $item->activity_code : 'tanpa-kegiatan';
            $hierarchyTree[$program] ??= ['code' => $program, 'name' => $referenceNames[$program] ?? 'Program '.$program, 'amount' => 0.0, 'realization' => 0.0, 'remaining' => 0.0, 'subs' => []];
            $hierarchyTree[$program]['subs'][$subprogram] ??= ['code' => $subprogram, 'name' => $referenceNames[$subprogram] ?? 'Subprogram '.$subprogram, 'amount' => 0.0, 'realization' => 0.0, 'remaining' => 0.0, 'activities' => []];
            $hierarchyTree[$program]['subs'][$subprogram]['activities'][$activity] ??= ['code' => $activity, 'name' => $item->activity_name ?: 'Kegiatan belum diisi', 'amount' => 0.0, 'realization' => 0.0, 'remaining' => 0.0, 'items' => []];
            $item->variance = $item->display_amount - $item->realization;
            foreach ([&$hierarchyTree[$program], &$hierarchyTree[$program]['subs'][$subprogram], &$hierarchyTree[$program]['subs'][$subprogram]['activities'][$activity]] as &$node) {
                $node['amount'] += $item->display_amount;
                $node['realization'] += $item->realization;
                $node['remaining'] += $item->variance;
            }
            $hierarchyTree[$program]['subs'][$subprogram]['activities'][$activity]['items'][] = $item;
        }
        $hierarchyTree = array_values(array_map(function (array $program): array {
            $program['subs'] = array_values(array_map(function (array $sub): array {
                $sub['activities'] = array_values($sub['activities']);

                return $sub;
            }, $program['subs']));

            return $program;
        }, $hierarchyTree));
        $budget = (float) $items->sum('display_amount');
        $realization = (float) $items->sum('realization');
        $treeTotals = ['amount' => $budget, 'realization' => $realization, 'remaining' => $budget - $realization, 'items' => $items->count()];
        $fiscalYearNumber = (int) $year->year;
        $fundName = (string) ($db->table('fund_sources')->where('id', $fundSourceId)->value('name') ?: $year->fund_source ?: 'Sumber Dana');

        $periodLabel = match ($scope) {
            'month' => Carbon::create($fiscalYearNumber, $scopeValue, 1)->translatedFormat('F Y'),
            'quarter' => 'Triwulan '.$scopeValue.' · '.$fiscalYearNumber,
            'semester' => 'Semester '.$scopeValue.' · '.$fiscalYearNumber,
            default => 'Tahun anggaran '.$fiscalYearNumber,
        };

        return ['hierarchyTree' => $hierarchyTree, 'treeTotals' => $treeTotals, 'filterContext' => 'pada '.$periodLabel, 'search' => $search, 'budget' => $budget, 'spent' => $realization, 'remaining' => $budget - $realization, 'overBudget' => max(0, $realization - $budget), 'underBudget' => max(0, $budget - $realization), 'activityCount' => $items->pluck('activity_code')->filter()->unique()->count(), 'scope' => $scope, 'scopeValue' => $scopeValue, 'periodLabel' => $periodLabel, 'programFilter' => $programFilter, 'subprogramFilter' => $subprogramFilter, 'activityFilter' => $activityFilter, 'contextLabel' => $fundName.' - '.$fiscalYearNumber];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, array<int, array{amount:float,month:int,quarter:int,semester:int}>>  $periodAmountsByRapbs
     */
    private function rawScopedAmount(array $payload, array $periodAmountsByRapbs, string $scope, int $scopeValue): float
    {
        if ($scope === 'year') {
            return (float) ($payload['JUMLAH'] ?? 0);
        }

        $amount = 0.0;
        foreach ($periodAmountsByRapbs[(string) ($payload['ID_RAPBS'] ?? '')] ?? [] as $period) {
            $matches = match ($scope) {
                'month' => $period['month'] === $scopeValue,
                'quarter' => $period['quarter'] === $scopeValue,
                'semester' => $period['semester'] === $scopeValue,
                default => true,
            };
            if ($matches) {
                $amount += $period['amount'];
            }
        }

        return $amount;
    }

    /** @param array<string, mixed> $payload */
    private function rawBelongsToContext(array $payload, int $year, int $fundSourceId): bool
    {
        $date = (string) ($payload['TANGGAL'] ?? $payload['TAHUN'] ?? $payload['YEAR'] ?? '');
        $fund = (string) ($payload['ID_REF_SUMBER_DANA'] ?? $payload['FUND_SOURCE_ID'] ?? $payload['ID_SUMBER_DANA'] ?? '');

        return ($date === '' || str_contains($date, (string) $year)) && ($fund === '' || (int) $fund === $fundSourceId);
    }

    /**
     * @return array{0:array<string,string>,1:array<string,string>}
     */
    protected function hierarchyLevelNames(object $db, int $yearId): array
    {
        $programs = [];
        $subs = [];

        try {
            $rows = $db->table('activity_hierarchy_references')
                ->where('fiscal_year_id', $yearId)
                ->select(['program_code', 'program_name', 'sub_program_code', 'sub_program_name'])
                ->distinct()
                ->get();
        } catch (\Throwable) {
            return [$programs, $subs];
        }

        foreach ($rows as $row) {
            $programCode = trim((string) ($row->program_code ?? ''), '.');
            $subprogramCode = trim((string) ($row->sub_program_code ?? ''), '.');
            if ($programCode !== '' && ! isset($programs[$programCode])) {
                $programs[$programCode] = (string) ($row->program_name ?: 'Program');
            }
            if ($subprogramCode !== '' && ! isset($subs[$subprogramCode])) {
                $subs[$subprogramCode] = (string) ($row->sub_program_name ?: 'Subprogram');
            }
        }

        return [$programs, $subs];
    }
}
