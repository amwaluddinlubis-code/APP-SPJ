<?php

namespace App\Http\Controllers;

use App\Models\FiscalYear;
use App\Models\Transaction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class TaxController extends Controller
{
    public function index(Request $request): View
    {
        $baseQuery = Transaction::query()
            ->activeContext()
            ->where('tax_total', '>', 0);
        $year = FiscalYear::query()->findOrFail(session('active_fiscal_year_id'));

        $summary = (clone $baseQuery)->selectRaw(
            'COUNT(*) as count, COALESCE(SUM(ppn), 0) as ppn, COALESCE(SUM(pph21), 0) as pph21,
            COALESCE(SUM(pph22), 0) as pph22, COALESCE(SUM(pph23), 0) as pph23,
            COALESCE(SUM(pph4), 0) as pph4, COALESCE(SUM(sspd), 0) as sspd,
            COALESCE(SUM(tax_total), 0) as total'
        )->first();

        $search = trim((string) $request->string('q'));
        $month = $request->integer('month') ?: null;
        $quarter = $request->integer('quarter') ?: null;
        $semester = $request->integer('semester') ?: null;
        $query = (clone $baseQuery)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('no_bukti', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('recipient_name', 'like', "%{$search}%");
                });
            })
            ->when($month, fn ($query) => $query->whereMonth('transaction_date', $month))
            ->when(! $month && $quarter, fn ($query) => $query->whereBetween('transaction_date', [now()->setYear($year->year)->setMonth(($quarter - 1) * 3 + 1)->startOfMonth(), now()->setYear($year->year)->setMonth($quarter * 3)->endOfMonth()]))
            ->when(! $month && ! $quarter && $semester, fn ($query) => $query->whereBetween('transaction_date', [now()->setYear($year->year)->setMonth($semester === 1 ? 1 : 7)->startOfMonth(), now()->setYear($year->year)->setMonth($semester === 1 ? 6 : 12)->endOfMonth()]));
        $filteredSummary = (clone $query)->selectRaw('COUNT(*) as count, COALESCE(SUM(ppn), 0) as ppn, COALESCE(SUM(pph21), 0) as pph21, COALESCE(SUM(pph22), 0) as pph22, COALESCE(SUM(pph23), 0) as pph23, COALESCE(SUM(pph4), 0) as pph4, COALESCE(SUM(sspd), 0) as sspd, COALESCE(SUM(tax_total), 0) as total')->first();

        $perPageRaw = $request->input('perPage', 15);
        $perPage = $perPageRaw === 'all' ? 10000 : (int) $perPageRaw;
        $perPage = in_array($perPage, [15, 25, 50, 100, 10000]) ? $perPage : 15;
        $transactions = $query
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('taxes.index', compact('summary', 'filteredSummary', 'transactions', 'search', 'month', 'quarter', 'semester', 'year'));
    }
}
