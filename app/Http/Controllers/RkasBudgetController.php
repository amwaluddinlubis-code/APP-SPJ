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
        $planningQuery = $db->table('arkas_rkas_items as r')
            ->where('r.fiscal_year_id', $yearId)
            ->where('r.fund_source_id', $fundSourceId);
        if ($programFilter !== '') {
            $planningQuery->where(function ($filter) use ($programFilter): void {
                $filter->where('r.activity_code', $programFilter)->orWhere('r.activity_code', 'like', $programFilter.'.%');
            });
        }
        if ($subprogramFilter !== '') {
            $planningQuery->where(function ($filter) use ($subprogramFilter): void {
                $filter->where('r.activity_code', $subprogramFilter)->orWhere('r.activity_code', 'like', $subprogramFilter.'.%');
            });
        }
        if ($activityFilter !== '') {
            $planningQuery->whereIn('r.activity_code', [$activityFilter, $activityFilter.'.']);
        }
        if ($search !== '') {
            $planningQuery->where(function ($filter) use ($search): void {
                $term = '%'.$search.'%';
                $filter->where('r.account_code', 'like', $term)
                    ->orWhere('r.activity_code', 'like', $term)
                    ->orWhere('r.description', 'like', $term)
                    ->orWhere('r.activity_name', 'like', $term);
            });
        }
        $periodPlanningRows = $planningQuery->orderBy('r.activity_code')->orderBy('r.account_code')->orderBy('r.description')->get()->map(function ($item): array {
            $payload = is_array($item->payload) ? $item->payload : (json_decode((string) $item->payload, true) ?: []);
            $value = static fn (string $key): float => (float) ($payload[$key] ?? 0);

            return [
                'activity_code' => trim((string) $item->activity_code, '.'),
                'activity_name' => $item->activity_name ?: 'Kegiatan belum diisi',
                'account_code' => $item->account_code ?: 'Tanpa kode rekening',
                'description' => $item->description ?: 'Tanpa uraian',
                'unit' => $payload['SATUAN'] ?? '—',
                'volume' => (float) ($payload['VOLUME_TOTAL'] ?? 0),
                'unit_price' => $value('HARGA_SATUAN'),
                'annual' => (float) $item->amount,
                'quarters' => array_combine(range(1, 4), array_map(fn (int $quarter): float => $value('TW_'.$quarter), range(1, 4))),
            ];
        })->values();
        $periodPlanningRows = $periodPlanningRows->map(function (array $row) use ($scope, $scopeValue): array {
            $row['semesters'] = [1 => $row['quarters'][1] + $row['quarters'][2], 2 => $row['quarters'][3] + $row['quarters'][4]];
            $row['months'] = $scope === 'quarter' ? $row['quarters'] : $row['semesters'];
            $row['total'] = $scope === 'semester'
                ? $row['semesters'][$scopeValue]
                : $row['annual'];

            return $row;
        });
        $periodDetailRows = collect();
        if ($scope === 'quarter' && $scopeValue > 0) {
            $detailQuery = $db->table('arkas_rkas_periods as p')
                ->join('arkas_rkas_items as r', function ($join): void {
                    $join->on('r.fiscal_year_id', '=', 'p.fiscal_year_id')
                        ->on('r.fund_source_id', '=', 'p.fund_source_id')
                        ->on('r.source_rapbs_id', '=', 'p.source_rapbs_id');
                })
                ->where('p.fiscal_year_id', $yearId)
                ->where('p.fund_source_id', $fundSourceId)
                ->whereIn('p.month_number', $periodMonths)
                ->select(['p.month_number', 'p.volume', 'p.amount', 'r.source_rapbs_id', 'r.activity_code', 'r.activity_name', 'r.account_code', 'r.description', 'r.payload']);
            if ($programFilter !== '') {
                $detailQuery->where(function ($filter) use ($programFilter): void {
                    $filter->where('r.activity_code', $programFilter)->orWhere('r.activity_code', 'like', $programFilter.'.%');
                });
            }
            if ($subprogramFilter !== '') {
                $detailQuery->where(function ($filter) use ($subprogramFilter): void {
                    $filter->where('r.activity_code', $subprogramFilter)->orWhere('r.activity_code', 'like', $subprogramFilter.'.%');
                });
            }
            if ($activityFilter !== '') {
                $detailQuery->whereIn('r.activity_code', [$activityFilter, $activityFilter.'.']);
            }
            if ($search !== '') {
                $detailQuery->where(function ($filter) use ($search): void {
                    $term = '%'.$search.'%';
                    $filter->where('r.account_code', 'like', $term)
                        ->orWhere('r.activity_code', 'like', $term)
                        ->orWhere('r.description', 'like', $term)
                        ->orWhere('r.activity_name', 'like', $term);
                });
            }
            $periodDetailRows = $detailQuery->orderBy('r.activity_code')->orderBy('r.account_code')->orderBy('r.description')->get()
                ->groupBy('source_rapbs_id')
                ->map(function ($rows) use ($periodMonths): array {
                    $first = $rows->first();
                    $payload = is_array($first->payload) ? $first->payload : (json_decode((string) $first->payload, true) ?: []);
                    $byMonth = $rows->groupBy('month_number');

                    return [
                        'activity_code' => trim((string) $first->activity_code, '.'),
                        'activity_name' => $first->activity_name ?: 'Kegiatan belum diisi',
                        'account_code' => $first->account_code ?: 'Tanpa kode rekening',
                        'description' => $first->description ?: 'Tanpa uraian',
                        'unit' => $payload['SATUAN'] ?? '—',
                        'unit_price' => (float) ($payload['HARGA_SATUAN'] ?? 0),
                        'volume' => $rows->sum('volume'),
                        'months' => collect($periodMonths)->mapWithKeys(fn (int $month): array => [$month => (float) $byMonth->get($month, collect())->sum('amount')])->all(),
                        'total' => (float) $rows->sum('amount'),
                    ];
                })->values();
        }
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
        $items = $query->orderBy('r.activity_code')->orderBy('r.account_code')->paginate($perPage)->withQueryString();
        $items->getCollection()->transform(function ($item) {
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
                'remaining' => $activityItems->sum('variance'),
                'accounts' => $activityItems->groupBy(fn ($item) => $item->account_code ?: 'tanpa-rekening')->map(function ($accountItems, $accountKey): array {
                    return [
                        'key' => $accountKey,
                        'code' => $accountItems->first()->account_code ?: 'Tanpa kode rekening',
                        'amount' => $accountItems->sum('display_amount'),
                        'realization' => $accountItems->sum('realization'),
                        'remaining' => $accountItems->sum('variance'),
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

        return view('rkas-budget.index', compact('items', 'rkasGroups', 'search', 'perPage', 'budget', 'spent', 'remaining', 'overBudget', 'underBudget', 'activityCount', 'scope', 'scopeValue', 'periodLabel', 'periodMonths', 'periodPlanningRows', 'periodDetailRows', 'programOptions', 'subprogramOptions', 'activityOptions', 'programFilter', 'subprogramFilter', 'activityFilter', 'contextLabel'));
    }
}
