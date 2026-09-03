<?php

namespace App\Http\Controllers;

use App\Models\BackgroundOperation;
use App\Models\FiscalYear;
use App\Models\School;
use App\Models\SpjPackage;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OperationalDashboardController extends Controller
{
    public function __invoke(): View
    {
        $year = FiscalYear::query()->findOrFail(session('active_fiscal_year_id'));
        $school = School::query()->find(session('active_school_id'));

        $transactions = Transaction::query()->activeContext();
        $packages = SpjPackage::query()->whereHas('transaction', fn ($query) => $query->activeContext());

        $summary = [
            'transactions' => (clone $transactions)->count(),
            'without_package' => (clone $transactions)->has('items')->doesntHave('spjPackage')->count(),
            'draft' => (clone $packages)->where('status', 'DRAFT')->count(),
            'ready' => (clone $packages)->where('status', 'READY')->count(),
            'numbered' => (clone $packages)->where('status', 'NUMBERED')->count(),
            'final' => (clone $packages)->where('status', 'FINAL')->count(),
            'reconciliation' => (clone $transactions)->where('requires_reconciliation', true)->count(),
            'source_missing' => (clone $transactions)->where('source_status', 'SOURCE_MISSING')->count(),
        ];

        $attentionCount = $summary['without_package'] + $summary['draft'] + $summary['reconciliation'] + $summary['source_missing'];

        $quarterSummary = collect(range(1, 4))->map(function (int $quarter) use ($transactions): array {
            $startMonth = (($quarter - 1) * 3) + 1;
            $endMonth = $quarter * 3;
            $quarterTransactions = (clone $transactions)
                ->whereMonth('transaction_date', '>=', $startMonth)
                ->whereMonth('transaction_date', '<=', $endMonth);

            $total = (clone $quarterTransactions)->count();
            $withItems = (clone $quarterTransactions)->has('items')->count();
            $ready = (clone $quarterTransactions)->whereHas('spjPackage', fn ($query) => $query->where('status', 'READY'))->count();
            $numbered = (clone $quarterTransactions)->whereHas('spjPackage', fn ($query) => $query->whereIn('status', ['NUMBERED', 'FINAL']))->count();
            $blocked = (clone $quarterTransactions)->has('items')->where(function ($query): void {
                $query->doesntHave('spjPackage')
                    ->orWhereHas('spjPackage', fn ($package) => $package->where('status', 'DRAFT'));
            })->count();

            return compact('quarter', 'total', 'withItems', 'ready', 'numbered', 'blocked');
        });

        $workQueue = Transaction::query()
            ->activeContext()
            ->with(['spjPackage:id,transaction_id,status,document_number'])
            ->withCount('items')
            ->where(function ($query): void {
                $query->where('requires_reconciliation', true)
                    ->orWhere('source_status', 'SOURCE_MISSING')
                    ->orWhereDoesntHave('spjPackage')
                    ->orWhereHas('spjPackage', fn ($package) => $package->whereIn('status', ['DRAFT', 'READY']));
            })
            ->orderByRaw("CASE WHEN source_status = 'SOURCE_MISSING' THEN 0 WHEN requires_reconciliation = 1 THEN 1 ELSE 2 END")
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->limit(8)
            ->get();

        $latestSync = DB::connection('school')->table('sync_runs')
            ->where('fiscal_year_id', $year->id)
            ->latest('started_at')
            ->first();

        $latestOperation = BackgroundOperation::query()
            ->where('school_id', $school?->id)
            ->where('fiscal_year_id', $year->id)
            ->latest('id')
            ->first();

        return view('dashboard-operational', compact(
            'school',
            'year',
            'summary',
            'attentionCount',
            'quarterSummary',
            'workQueue',
            'latestSync',
            'latestOperation',
        ));
    }
}
