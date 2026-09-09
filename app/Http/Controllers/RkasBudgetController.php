<?php

namespace App\Http\Controllers;

use App\Models\FiscalYear;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Read-only RKAS budget monitor based on synchronized ARKAS data. */
class RkasBudgetController extends Controller
{
    public function __invoke(Request $request): View
    {
        $yearId = (int) session('active_fiscal_year_id');
        $db = DB::connection('school');
        $search = trim((string) $request->query('q'));
        $requestedPerPage = (int) $request->query('per_page', 15);
        $perPage = in_array($requestedPerPage, [15, 30, 50, 100], true) ? $requestedPerPage : 15;
        $fundSourceId = (int) session('active_fund_source_id');
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
            $subprogramName = $subprogramCode !== null ? ($activityNames[$subprogramCode] ?? null) : null;

            return ['program' => $programCode, 'program_name' => $activityNames[$programCode] ?? null, 'subprogram' => $subprogramCode, 'subprogram_name' => $subprogramName, 'activity' => $code, 'activity_name' => $row->activity_name ?: 'Kegiatan belum diisi'];
        })->filter(fn (array $option): bool => $option['activity'] !== '')->values();
        $programOptions = $hierarchyOptions->filter(fn (array $option): bool => $option['program'] !== '')->unique('program')->values();
        $subprogramOptions = $hierarchyOptions->filter(fn (array $option): bool => $option['subprogram'] !== null)->unique('subprogram')->values();
        $activityOptions = $hierarchyOptions->unique('activity')->values();
        $programFilter = trim((string) $request->query('program'));
        $subprogramFilter = trim((string) $request->query('subprogram'));
        $activityFilter = trim((string) $request->query('activity'));
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
        $scope = (string) $request->query('scope', 'year');
        $scopeValue = (int) $request->query('scope_value', 0);
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
        $dateRange = null;
        if ($scope === 'month') {
            $dateRange = [Carbon::create($fiscalYearNumber, $scopeValue, 1)->startOfMonth()->toDateString(), Carbon::create($fiscalYearNumber, $scopeValue, 1)->endOfMonth()->toDateString()];
        } elseif ($scope === 'quarter') {
            $startMonth = (($scopeValue - 1) * 3) + 1;
            $dateRange = [Carbon::create($fiscalYearNumber, $startMonth, 1)->startOfMonth()->toDateString(), Carbon::create($fiscalYearNumber, $startMonth + 2, 1)->endOfMonth()->toDateString()];
        } elseif ($scope === 'semester') {
            $startMonth = (($scopeValue - 1) * 6) + 1;
            $dateRange = [Carbon::create($fiscalYearNumber, $startMonth, 1)->startOfMonth()->toDateString(), Carbon::create($fiscalYearNumber, $startMonth + 5, 1)->endOfMonth()->toDateString()];
        }
        $realization = $db->table('arkas_bku_rows as bku')->selectRaw("json_extract(bku.payload, '$.ID_RAPBS') as source_rapbs_id, SUM(bku.amount) as realization")->where('bku.fiscal_year_id', $yearId)->where('bku.fund_source_id', $fundSourceId)->where('bku.category', 'BELANJA')->groupByRaw("json_extract(bku.payload, '$.ID_RAPBS')");
        if ($dateRange !== null) {
            $realization->whereBetween('transaction_date', $dateRange);
        }
        $applyBkuHierarchy = function ($builder) use ($db, $yearId, $fundSourceId, $programFilter, $subprogramFilter, $activityFilter): void {
            if ($programFilter === '' && $subprogramFilter === '' && $activityFilter === '') {
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
        $query = $db->table('arkas_rkas_items as r')->leftJoinSub($realization, 'b', fn ($join) => $join->on('b.source_rapbs_id', '=', 'r.source_rapbs_id'))->where('r.fiscal_year_id', $yearId)->where('r.fund_source_id', $fundSourceId)->selectRaw('r.*, COALESCE(b.realization, 0) as realization');
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
        $items = $query->orderBy('r.activity_code')->orderBy('r.account_code')->paginate($perPage)->withQueryString();
        $items->getCollection()->transform(function ($item) {
            $payload = json_decode($item->payload, true) ?: [];
            $item->volume = (float) ($item->scoped_volume ?? $payload['VOLUME_TOTAL'] ?? 0);
            $item->unit = $payload['SATUAN'] ?? '—';
            $item->unit_price = (float) ($payload['HARGA_SATUAN'] ?? 0);
            $item->display_amount = (float) ($item->scoped_amount ?? $item->amount);
            $item->variance = $item->display_amount - (float) $item->realization;

            return $item;
        });
        $activityGroups = $items->getCollection()->groupBy(fn ($item) => $item->activity_code ?: 'tanpa-kegiatan')->map(function ($activityItems, $activityKey) use ($activityNames): array {
            $activityCode = trim((string) ($activityItems->first()->activity_code ?? ''), '.');
            $codeParts = $activityCode !== '' ? explode('.', $activityCode) : [];
            $programCode = $codeParts[0] ?? 'tanpa-program';
            $subprogramCode = count($codeParts) >= 2 ? implode('.', array_slice($codeParts, 0, 2)) : $programCode;

            return [
                'key' => $activityKey,
                'program_code' => $programCode,
                'program_name' => $activityNames[$programCode] ?? null,
                'subprogram_code' => $subprogramCode,
                'subprogram_name' => $activityNames[$subprogramCode] ?? null,
                'code' => $activityCode !== '' ? $activityCode : 'Tanpa kode kegiatan',
                'name' => $activityItems->first()->activity_name ?: 'Kegiatan belum diisi',
                'amount' => $activityItems->sum('display_amount'),
                'realization' => $activityItems->sum('realization'),
                'accounts' => $activityItems->groupBy(fn ($item) => $item->account_code ?: 'tanpa-rekening')->map(function ($accountItems, $accountKey): array {
                    return [
                        'key' => $accountKey,
                        'code' => $accountItems->first()->account_code ?: 'Tanpa kode rekening',
                        'amount' => $accountItems->sum('display_amount'),
                        'realization' => $accountItems->sum('realization'),
                        'items' => $accountItems,
                    ];
                })->values(),
            ];
        })->values();
        $rkasGroups = $activityGroups;
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
        if ($scope === 'year') {
            $budget = (float) $budgetQuery->sum('r.amount');
        } else {
            $budget = (float) $budgetQuery->joinSub(clone $periods, 'budget_periods', fn ($join) => $join->on('budget_periods.source_rapbs_id', '=', 'r.source_rapbs_id'))->sum('budget_periods.scoped_amount');
        }
        $spentQuery = $db->table('arkas_bku_rows as bku')->where('bku.fiscal_year_id', $yearId)->where('bku.fund_source_id', $fundSourceId)->where('bku.category', 'BELANJA');
        if ($dateRange !== null) {
            $spentQuery->whereBetween('transaction_date', $dateRange);
        }
        $applyBkuHierarchy($spentQuery);
        $spent = (float) $spentQuery->sum('amount');
        $remaining = $budget - $spent;
        $overBudget = max(0, -$remaining);
        $underBudget = max(0, $remaining);
        $activityCount = $db->table('arkas_rkas_items')->where('fiscal_year_id', $yearId)->where('fund_source_id', $fundSourceId)->distinct('activity_code')->count('activity_code');

        return view('rkas-budget.index', compact('items', 'rkasGroups', 'search', 'perPage', 'budget', 'spent', 'remaining', 'overBudget', 'underBudget', 'activityCount', 'scope', 'scopeValue', 'programOptions', 'subprogramOptions', 'activityOptions', 'programFilter', 'subprogramFilter', 'activityFilter'));
    }
}
