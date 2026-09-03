<?php

namespace App\Http\Controllers;

use App\Models\BackgroundOperation;
use App\Models\FiscalYear;
use App\Models\School;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $year = FiscalYear::query()->findOrFail(session('active_fiscal_year_id'));
        $school = School::query()->find(session('active_school_id'));
        $db = DB::connection('school');
        $fundSourceId = (int) session('active_fund_source_id');
        $cacheKey = "dashboard:v2:school:{$school?->id}:year:{$year->id}:fund:{$fundSourceId}";

        $summary = Cache::store('file')->remember($cacheKey, now()->addSeconds(60), fn (): array => $this->financialSummary($year->id));
        extract($summary);

        // Status operasional harus tetap aktual dan tidak ikut cache ringkasan keuangan.
        $latestSync = $db->table('sync_runs')->where('fiscal_year_id', $year->id)->latest('started_at')->first();
        $latestOperation = BackgroundOperation::query()->where('school_id', $school?->id)->where('fiscal_year_id', $year->id)->latest('id')->first();

        return view('dashboard', compact(
            'school', 'year', 'budget', 'realization', 'taxes', 'remaining', 'absorption', 'transactionCount',
            'latestSync', 'latestOperation', 'activities', 'categories', 'recentTransactions',
            'hierarchyComparison', 'hierarchyChart', 'hierarchyTotalCount', 'hierarchyTotals'
        ));
    }

    private function financialSummary(int $yearId): array
    {
        $db = DB::connection('school');
        $budget = (float) $db->table('arkas_rkas_items')->where('fiscal_year_id', $yearId)->sum('amount');
        $realization = (float) $db->table('arkas_bku_rows')->where('fiscal_year_id', $yearId)->where('category', 'BELANJA')->sum('amount');
        $taxes = (float) $db->table('transactions')->where('fiscal_year_id', $yearId)->sum('tax_total');
        $remaining = $budget - $realization;
        $absorption = $budget > 0 ? min(100, max(0, ($realization / $budget) * 100)) : 0;
        $transactionCount = (int) $db->table('transactions')->where('fiscal_year_id', $yearId)->count();

        $activityRows = $db->table('arkas_rkas_items')->selectRaw('activity_code, activity_name, SUM(amount) as budget')
            ->where('fiscal_year_id', $yearId)->groupBy('activity_code', 'activity_name')->orderByDesc('budget')->limit(6)->get();
        $realizationByActivity = $db->table('transactions')->selectRaw('activity_code, SUM(gross_amount) as realization')
            ->where('fiscal_year_id', $yearId)->groupBy('activity_code')->pluck('realization', 'activity_code');
        $activities = $activityRows->map(function (object $row) use ($realizationByActivity): object {
            $row->realization = (float) ($realizationByActivity[$row->activity_code] ?? 0);
            $row->percentage = (float) $row->budget > 0 ? min(100, ($row->realization / (float) $row->budget) * 100) : 0;

            return $row;
        });

        $categories = $db->table('transactions')
            ->selectRaw("COALESCE(NULLIF(spj_category, ''), 'Belum diklasifikasi') as label, SUM(gross_amount) as amount")
            ->where('fiscal_year_id', $yearId)->groupByRaw("COALESCE(NULLIF(spj_category, ''), 'Belum diklasifikasi')")
            ->orderByDesc('amount')->limit(6)->get()->map(function (object $row) use ($realization): object {
                $row->percentage = $realization > 0 ? min(100, ((float) $row->amount / $realization) * 100) : 0;

                return $row;
            });
        $recentTransactions = $db->table('transactions')->select('no_bukti', 'transaction_date', 'description', 'recipient_name', 'gross_amount', 'status', 'payment_method')
            ->where('fiscal_year_id', $yearId)->orderByDesc('transaction_date')->orderByDesc('id')->limit(6)->get();

        $hierarchies = $db->table('account_hierarchies')->whereIn('level', [1, 2, 3, 4])->orderBy('level')->orderBy('account_code')->get();
        if ($hierarchies->isEmpty()) {
            $hierarchies = collect();
            foreach ([1 => 1, 2 => 3, 3 => 7, 4 => 10] as $level => $length) {
                $hierarchies = $hierarchies->concat($db->table('account_references')->where('fiscal_year_id', $yearId)
                    ->selectRaw("SUBSTR(account_code,1,{$length}) as account_code, MIN(account_name) as account_name, {$level} as level")
                    ->groupByRaw("SUBSTR(account_code,1,{$length})")->orderBy('account_code')->get());
            }
        }

        $lengths = $hierarchies->pluck('account_code')->filter()->map(fn ($code) => strlen((string) $code))->unique()->values();
        $budgetPrefixes = $this->prefixTotals($db->table('arkas_rkas_items')->where('fiscal_year_id', $yearId)->selectRaw('account_code, SUM(amount) as total')->groupBy('account_code')->pluck('total', 'account_code'), $lengths);
        $realizationPrefixes = $this->prefixTotals($db->table('transactions')->where('fiscal_year_id', $yearId)->selectRaw('account_code, SUM(gross_amount) as total')->groupBy('account_code')->pluck('total', 'account_code'), $lengths);

        $allHierarchyRows = $hierarchies->map(function (object $row) use ($budgetPrefixes, $realizationPrefixes, $budget, $realization): object {
            $row->budget = (float) ($budgetPrefixes[$row->account_code] ?? 0);
            $row->realization = (float) ($realizationPrefixes[$row->account_code] ?? 0);
            $row->remaining = $row->budget - $row->realization;
            $row->absorption = $row->budget > 0 ? min(100, ($row->realization / $row->budget) * 100) : 0;
            $row->shareBudget = $budget > 0 ? ($row->budget / $budget) * 100 : 0;
            $row->shareRealization = $realization > 0 ? ($row->realization / $realization) * 100 : 0;

            return $row;
        })->filter(fn ($row) => $row->budget > 0 || $row->realization > 0)->sortBy('account_code', SORT_NATURAL)->values();

        $hierarchyTotalCount = $allHierarchyRows->count();
        $hierarchyTotals = (object) ['budget' => $allHierarchyRows->sum('budget'), 'realization' => $allHierarchyRows->sum('realization')];
        $hierarchyChart = $allHierarchyRows->sortByDesc('budget')->take(6)->values();
        $hierarchyComparison = $allHierarchyRows->take(15)->values();

        return compact('budget', 'realization', 'taxes', 'remaining', 'absorption', 'transactionCount', 'activities', 'categories', 'recentTransactions', 'hierarchyComparison', 'hierarchyChart', 'hierarchyTotalCount', 'hierarchyTotals');
    }

    private function prefixTotals($accountTotals, $lengths): array
    {
        $totals = [];
        foreach ($accountTotals as $accountCode => $amount) {
            foreach ($lengths as $length) {
                $prefix = Str::substr((string) $accountCode, 0, (int) $length);
                $totals[$prefix] = ($totals[$prefix] ?? 0) + (float) $amount;
            }
        }

        return $totals;
    }
}
