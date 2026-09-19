<?php

namespace App\Services;

use App\Models\FiscalPeriodClosure;
use App\Models\FiscalYear;
use App\Models\SpjPackage;
use App\Support\ActiveSpjContext;
use Illuminate\Support\Carbon;

/**
 * Resolve the one effective numbering year/quarter without legacy-year writes.
 *
 * The canonical V2 transaction context supplies the effective fiscal-year id;
 * its canonical source transaction date supplies the quarter. The active
 * tenant context is a required consistency check, not a fallback authority.
 */
final class SpjV2EffectiveNumberingPeriodResolver
{
    public function __construct(private readonly ActiveSpjContext $context) {}

    /**
     * @param  array<string,mixed>  $canonical
     * @return array{status:string,authorized:bool,error_code:?string,reason:string,effective_fiscal_year_id:?int,effective_fiscal_year:?int,effective_quarter:?int,effective_fund_source_id:?int,source_id:?int,context_key:?string,date_basis:?string,legacy_fiscal_year_id:?int}
     */
    public function resolve(SpjPackage $package, array $canonical): array
    {
        $transaction = $package->relationLoaded('transaction') ? $package->transaction : $package->transaction()->first();
        $effectiveYearId = isset($canonical['fiscal_year_id']) ? (int) $canonical['fiscal_year_id'] : 0;
        $effectiveFundSourceId = isset($canonical['fund_source_id']) ? (int) $canonical['fund_source_id'] : 0;
        $sourceId = isset($canonical['source_id']) ? (int) $canonical['source_id'] : 0;
        $date = $this->parseDate($canonical['transaction_date'] ?? null);

        $blocked = function (string $code, string $reason) use ($effectiveYearId, $effectiveFundSourceId, $sourceId, $transaction): array {
            return [
                'status' => 'BLOCKED',
                'authorized' => false,
                'error_code' => $code,
                'reason' => $reason,
                'effective_fiscal_year_id' => $effectiveYearId > 0 ? $effectiveYearId : null,
                'effective_fiscal_year' => null,
                'effective_quarter' => null,
                'effective_fund_source_id' => $effectiveFundSourceId > 0 ? $effectiveFundSourceId : null,
                'source_id' => $sourceId > 0 ? $sourceId : null,
                'context_key' => null,
                'date_basis' => null,
                'legacy_fiscal_year_id' => $transaction?->fiscal_year_id === null ? null : (int) $transaction->fiscal_year_id,
            ];
        };

        if ($transaction === null) {
            return $blocked('PACKAGE_TRANSACTION_MISSING', 'effective period requires one package transaction');
        }
        if ($effectiveYearId < 1 || $effectiveFundSourceId < 1 || $sourceId < 1) {
            return $blocked('CANONICAL_CONTEXT_MISSING', 'canonical fiscal year, fund source, and source identity are required');
        }
        if ($effectiveYearId !== $this->context->fiscalYearId() || $effectiveFundSourceId !== $this->context->fundSourceId()) {
            return $blocked('EFFECTIVE_CONTEXT_MISMATCH', 'canonical effective year/fund source does not match the active context');
        }
        if (strtoupper((string) ($canonical['canonical_context_status'] ?? '')) !== 'ACTIVE_CANONICAL') {
            return $blocked('CANONICAL_CONTEXT_NOT_ACTIVE', 'canonical transaction is not ACTIVE_CANONICAL');
        }
        if (strtoupper((string) ($canonical['source_status'] ?? '')) !== 'ACTIVE') {
            return $blocked('CANONICAL_SOURCE_NOT_ACTIVE', 'canonical source identity is not active');
        }
        if ($date === null) {
            return $blocked('CANONICAL_DATE_UNPROVEN', 'canonical transaction date is missing or invalid');
        }
        if ($this->parseDate($transaction->transaction_date) === null
            || $this->parseDate($transaction->transaction_date)?->toDateString() !== $date->toDateString()) {
            return $blocked('DATE_FACTS_DRIFT', 'canonical and legacy transaction dates are not in parity');
        }

        $fiscalYear = FiscalYear::query()->find($effectiveYearId);
        if ($fiscalYear === null || ! is_numeric($fiscalYear->year) || (int) $fiscalYear->year !== (int) $date->year) {
            return $blocked('EFFECTIVE_YEAR_UNPROVEN', 'canonical date cannot be proven inside the effective fiscal year');
        }

        $quarter = intdiv($date->month - 1, 3) + 1;
        $period = FiscalPeriodClosure::query()
            ->where('fiscal_year_id', $effectiveYearId)
            ->where('quarter', $quarter)
            ->first();
        if ($period === null) {
            return $blocked('EFFECTIVE_PERIOD_UNPROVEN', 'effective quarter period state is unavailable');
        }
        if ($period->status === 'CLOSED') {
            return $blocked('EFFECTIVE_PERIOD_CLOSED', 'effective quarter period is closed');
        }

        return [
            'status' => 'AUTHORIZED',
            'authorized' => true,
            'error_code' => null,
            'reason' => 'canonical effective fiscal year and transaction date deterministically resolve an open quarter',
            'effective_fiscal_year_id' => $effectiveYearId,
            'effective_fiscal_year' => (int) $fiscalYear->year,
            'effective_quarter' => $quarter,
            'effective_fund_source_id' => $effectiveFundSourceId,
            'source_id' => $sourceId,
            'context_key' => $effectiveYearId.'|'.$effectiveFundSourceId.'|'.$sourceId.'|Q'.$quarter,
            'date_basis' => $date->toDateString(),
            'legacy_fiscal_year_id' => $transaction->fiscal_year_id === null ? null : (int) $transaction->fiscal_year_id,
        ];
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $text = trim((string) $value);
        try {
            $date = Carbon::parse($text);
        } catch (\Throwable) {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) === 1) {
            try {
                $strict = Carbon::createFromFormat('!Y-m-d', $text);
            } catch (\Throwable) {
                return null;
            }
            if ($strict === false || $strict->format('Y-m-d') !== $text) {
                return null;
            }
        }

        return $date;
    }
}
