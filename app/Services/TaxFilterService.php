<?php

namespace App\Services;

use App\Models\FiscalYear;
use App\Models\Transaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TaxFilterService
{
    /**
     * Query rekap pajak dari parameter eksplisit memakai implementasi yang
     * sama dengan jalur HTTP, untuk dipakai controller dan komponen Livewire.
     *
     * @return array{summary: object, filteredSummary: object, transactions: LengthAwarePaginator, year: FiscalYear}
     */
    public function taxData(string $search, ?int $month, ?int $quarter, ?int $semester, int $perPage): array
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

        $transactions = $query
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return compact('summary', 'filteredSummary', 'transactions', 'year');
    }
}
