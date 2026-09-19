<?php

namespace App\Services;

use App\Models\FiscalYear;
use App\Models\Transaction;
use App\Support\ActiveSpjContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TaxFilterService
{
    public function __construct(
        private readonly ActiveSpjContext $context,
        private readonly SpjV2TaxReadContextService $v2ReadContext,
    ) {}

    /**
     * Query rekap pajak dari parameter eksplisit memakai implementasi yang
     * sama dengan jalur HTTP, untuk dipakai controller dan komponen Livewire.
     *
     * @return array{summary: object, filteredSummary: object, transactions: LengthAwarePaginator, year: FiscalYear, read_path: string}
     */
    public function taxData(string $search, ?int $month, ?int $quarter, ?int $semester, int $perPage): array
    {
        $year = FiscalYear::query()->findOrFail($this->context->fiscalYearId());
        $membership = null;
        $fundSourceId = $this->context->fundSourceId();
        if ($fundSourceId !== null) {
            $membership = $this->v2ReadContext->forContext(
                DB::connection('school'),
                $this->context->fiscalYearId(),
                $fundSourceId,
            );
        }

        $baseQuery = Transaction::query()
            ->when(
                $membership !== null,
                fn ($query) => $query->whereIn('id', $membership['legacy_transaction_ids']),
                fn ($query) => $query->forSpjContext($this->context),
            )
            ->where('tax_total', '>', 0);
        $readPath = $membership !== null ? 'v2' : 'legacy';

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

        if ($membership !== null) {
            $transactions->getCollection()->each(
                fn (Transaction $transaction): Transaction => $transaction->setAttribute('read_context_path', 'v2_compat'),
            );
        }

        return [
            'summary' => $summary,
            'filteredSummary' => $filteredSummary,
            'transactions' => $transactions,
            'year' => $year,
            'read_path' => $readPath,
        ];
    }
}
