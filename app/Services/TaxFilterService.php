<?php

namespace App\Services;

use App\Models\FiscalYear;
use App\Models\SpjFreshTransaction;
use App\Models\Transaction;
use App\Support\ActiveSpjContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
    public function taxData(string $search, ?int $month, ?int $quarter, ?int $semester, int $perPage, ?string $taxType = null, ?string $siplah = null): array
    {
        $year = FiscalYear::query()->findOrFail($this->context->fiscalYearId());
        $taxType = $this->normalizeTaxType($taxType);
        $siplah = $this->normalizeSiplah($siplah);
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

        if ($baseQuery->count() === 0) {
            $freshData = $this->freshTaxData($year, $search, $month, $quarter, $semester, $perPage, $taxType, $siplah);
            if ($freshData !== null) {
                return $freshData;
            }
        }

        $summary = (clone $baseQuery)->selectRaw(
            'COUNT(*) as count, COALESCE(SUM(ppn), 0) as ppn, COALESCE(SUM(pph21), 0) as pph21,
            COALESCE(SUM(pph22), 0) as pph22, COALESCE(SUM(pph23), 0) as pph23,
            COALESCE(SUM(pph4), 0) as pph4, COALESCE(SUM(sspd), 0) as sspd,
            COALESCE(SUM(tax_total), 0) as total'
        )->first();

        $query = (clone $baseQuery)
            ->when($taxType !== null, fn ($query) => $query->where($taxType, '>', 0))
            ->when($siplah !== null, fn ($query) => $query->where('is_siplah', $siplah === 'siplah'))
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

    /**
     * Read tax data from the fresh transaction projection when the legacy
     * transaction projection has not been built for the active context.
     *
     * @return array{summary: object, filteredSummary: object, transactions: LengthAwarePaginator, year: FiscalYear, read_path: string}|null
     */
    private function freshTaxData(FiscalYear $year, string $search, ?int $month, ?int $quarter, ?int $semester, int $perPage, ?string $taxType, ?string $siplah): ?array
    {
        if (! Schema::connection('school')->hasTable('spj_fresh_transactions')) {
            return null;
        }

        $transactions = SpjFreshTransaction::query()
            ->with(['rawMirrorRow', 'items.rawMirrorRow'])
            ->forSpjContext($this->context)
            ->whereIn('source_status', ['ACTIVE', 'SOURCE_MISSING'])
            ->get()
            ->map(function (SpjFreshTransaction $transaction): SpjFreshTransaction {
                $breakdown = $transaction->tax_breakdown;
                foreach ($breakdown as $field => $amount) {
                    $transaction->setAttribute($field, $amount);
                }
                $transaction->setAttribute('tax_total', array_sum($breakdown));
                $transaction->setAttribute('is_siplah', $transaction->is_siplah);
                $transaction->setAttribute('read_context_path', 'fresh');

                return $transaction;
            })
            ->filter(fn (SpjFreshTransaction $transaction): bool => (float) $transaction->tax_total > 0)
            ->values();

        if ($transactions->isEmpty()) {
            return null;
        }

        $filtered = $transactions
            ->filter(fn (SpjFreshTransaction $transaction): bool => $this->matchesFreshFilters($transaction, $search, $month, $quarter, $semester, $year, $taxType, $siplah))
            ->sort(function (SpjFreshTransaction $left, SpjFreshTransaction $right): int {
                $leftDate = $left->transaction_date?->timestamp ?? PHP_INT_MIN;
                $rightDate = $right->transaction_date?->timestamp ?? PHP_INT_MIN;

                return $rightDate <=> $leftDate ?: ((int) $right->id <=> (int) $left->id);
            })
            ->values();

        return [
            'summary' => $this->summaryFor($transactions),
            'filteredSummary' => $this->summaryFor($filtered),
            'transactions' => $this->freshPaginator($filtered, $perPage),
            'year' => $year,
            'read_path' => 'fresh',
        ];
    }

    private function matchesFreshFilters(SpjFreshTransaction $transaction, string $search, ?int $month, ?int $quarter, ?int $semester, FiscalYear $year, ?string $taxType, ?string $siplah): bool
    {
        if ($taxType !== null && (float) $transaction->{$taxType} <= 0) {
            return false;
        }
        if ($siplah !== null && (bool) $transaction->is_siplah !== ($siplah === 'siplah')) {
            return false;
        }
        if ($search !== '') {
            $haystack = mb_strtolower(implode(' ', [
                $transaction->no_bukti,
                $transaction->description,
                $transaction->effective_receipt_recipient_name,
            ]));
            if (! str_contains($haystack, mb_strtolower($search))) {
                return false;
            }
        }

        $date = $transaction->transaction_date;
        if ($date === null) {
            return ! ($month || $quarter || $semester);
        }

        if ($month !== null) {
            return $date->year === $year->year && $date->month === $month;
        }
        if ($quarter !== null) {
            return $date->year === $year->year && $date->quarter === $quarter;
        }
        if ($semester !== null) {
            return $date->year === $year->year && (($semester === 1 && $date->month <= 6) || ($semester === 2 && $date->month >= 7));
        }

        return true;
    }

    private function normalizeTaxType(?string $taxType): ?string
    {
        $taxType = strtolower(trim((string) $taxType));

        return in_array($taxType, ['ppn', 'pph21', 'pph22', 'pph23', 'pph4', 'sspd'], true) ? $taxType : null;
    }

    private function normalizeSiplah(?string $siplah): ?string
    {
        $siplah = strtolower(trim((string) $siplah));

        return in_array($siplah, ['siplah', 'non_siplah'], true) ? $siplah : null;
    }

    /** @param Collection<int, SpjFreshTransaction> $transactions */
    private function summaryFor(Collection $transactions): object
    {
        return (object) [
            'count' => $transactions->count(),
            'ppn' => $transactions->sum(fn (SpjFreshTransaction $transaction): float => (float) $transaction->ppn),
            'pph21' => $transactions->sum(fn (SpjFreshTransaction $transaction): float => (float) $transaction->pph21),
            'pph22' => $transactions->sum(fn (SpjFreshTransaction $transaction): float => (float) $transaction->pph22),
            'pph23' => $transactions->sum(fn (SpjFreshTransaction $transaction): float => (float) $transaction->pph23),
            'pph4' => $transactions->sum(fn (SpjFreshTransaction $transaction): float => (float) $transaction->pph4),
            'sspd' => $transactions->sum(fn (SpjFreshTransaction $transaction): float => (float) $transaction->sspd),
            'total' => $transactions->sum(fn (SpjFreshTransaction $transaction): float => (float) $transaction->tax_total),
        ];
    }

    /** @param Collection<int, SpjFreshTransaction> $transactions */
    private function freshPaginator(Collection $transactions, int $perPage): Paginator
    {
        $page = Paginator::resolveCurrentPage('page');
        $items = $transactions->forPage($page, $perPage)->values();

        return new Paginator($items, $transactions->count(), $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]);
    }
}
