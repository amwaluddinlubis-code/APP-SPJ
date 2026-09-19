<?php

namespace App\Services;

use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Support\ActiveSpjContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class SpjV2MutationContextService
{
    private const CONTEXT_RELATION = 'v2MutationContext';
    public function __construct(
        private readonly ActiveSpjContext $context,
        private readonly SpjV2PackageReadMembershipService $packageReadMembership,
        private readonly SpjV2CanonicalReadService $canonicalReads,
    ) {}

    /**
     * Authorize one Paket mutation against the active effective context.
     *
     * Legacy-aligned Paket remain authorized exactly as before. A stale legacy
     * fiscal year is accepted only when the V2 selector is enabled and the
     * package/provenance bridge resolves deterministically to the same active
     * fiscal-year + fund-source context.
     *
     * The transaction fiscal year is normalized in memory only for downstream
     * validation. This method never persists a Transaction/Paket/document row.
     */
    public function preparePackage(SpjPackage $package): bool
    {
        $transaction = $package->transaction;
        if ($transaction === null) {
            return false;
        }

        if ($this->context->matchesTransaction($transaction)) {
            $this->attachContext($package, $transaction, 'legacy', null, 'ALIGNED');

            return true;
        }

        $fundSourceId = $this->context->fundSourceId();
        if ($fundSourceId === null || (int) $transaction->fund_source_id !== $fundSourceId) {
            return false;
        }

        $membership = $this->packageReadMembership->forContext(
            DB::connection('school'),
            $this->context->fiscalYearId(),
            $fundSourceId,
        );
        if ($membership === null) {
            return false;
        }

        $packageId = (int) $package->id;
        $legacyTransactionId = (int) $package->transaction_id;
        $canonicalTransactionId = $package->spj_transaction_id === null
            ? null
            : (int) $package->spj_transaction_id;

        if (! in_array($packageId, $membership['package_ids'], true)
            || ! in_array($legacyTransactionId, $membership['legacy_transaction_ids'], true)
            || $canonicalTransactionId === null
            || ! in_array($canonicalTransactionId, $membership['canonical_transaction_ids'], true)) {
            return false;
        }

        $exactBridgeExists = DB::connection('school')
            ->table('legacy_transaction_v2_map')
            ->where('legacy_transaction_id', $legacyTransactionId)
            ->where('spj_transaction_id', $canonicalTransactionId)
            ->whereIn('canonical_context_status', ['ACTIVE_CANONICAL', 'LEGACY_DUPLICATE'])
            ->exists();
        if (! $exactBridgeExists) {
            return false;
        }

        if ((bool) $transaction->requires_reconciliation
            || strtoupper((string) ($transaction->source_status ?: 'ACTIVE')) === 'SOURCE_MISSING') {
            return false;
        }

        $canonical = $this->canonicalReads
            ->forContext(
                DB::connection('school'),
                $this->context->fiscalYearId(),
                $fundSourceId,
                $membership['source_id'],
            )
            ->first(fn (array $row): bool => (int) $row['id'] === $canonicalTransactionId);
        if (! is_array($canonical) || ! $this->sourceFactsMatch($canonical, $transaction)) {
            return false;
        }

        $transaction->setAttribute('fiscal_year_id', $this->context->fiscalYearId());
        $transaction->unsetRelation('fiscalYear');
        $this->attachContext(
            $package,
            $transaction,
            'v2_compat',
            $membership['source_id'],
            $membership['compatibility_mode'],
        );

        return true;
    }

    /** @return array{path:string,source_id:int|null,mode:string}|null */
    public function packageContext(SpjPackage $package): ?array
    {
        return $this->relationContext($package->relationLoaded(self::CONTEXT_RELATION)
            ? $package->getRelation(self::CONTEXT_RELATION)
            : null);
    }

    /** @return array{path:string,source_id:int|null,mode:string}|null */
    public function transactionContext(Transaction $transaction): ?array
    {
        return $this->relationContext($transaction->relationLoaded(self::CONTEXT_RELATION)
            ? $transaction->getRelation(self::CONTEXT_RELATION)
            : null);
    }

    private function attachContext(
        SpjPackage $package,
        Transaction $transaction,
        string $path,
        ?int $sourceId,
        string $mode,
    ): void {
        $context = [
            'path' => $path,
            'source_id' => $sourceId,
            'mode' => $mode,
        ];

        $package->setRelation(self::CONTEXT_RELATION, $context);
        $transaction->setRelation(self::CONTEXT_RELATION, $context);
    }

    /** @return array{path:string,source_id:int|null,mode:string}|null */
    private function relationContext(mixed $context): ?array
    {
        if (! is_array($context) || ! isset($context['path'], $context['mode'])) {
            return null;
        }

        return [
            'path' => (string) $context['path'],
            'source_id' => isset($context['source_id']) ? (int) $context['source_id'] : null,
            'mode' => (string) $context['mode'],
        ];
    }

    /** @param array<string, mixed> $canonical */
    private function sourceFactsMatch(array $canonical, Transaction $legacy): bool
    {
        foreach (['no_bukti', 'description', 'activity_code', 'account_code', 'recipient_name'] as $field) {
            if ($this->normalize($canonical[$field] ?? null) !== $this->normalize($legacy->{$field} ?? null)) {
                return false;
            }
        }

        if ($this->date($canonical['transaction_date'] ?? null) !== $this->date($legacy->transaction_date ?? null)) {
            return false;
        }

        foreach (['gross_amount', 'tax_total', 'net_amount', 'ppn', 'pph21', 'pph22', 'pph23', 'pph4', 'sspd'] as $field) {
            if (abs(round((float) ($canonical[$field] ?? 0), 2) - round((float) ($legacy->{$field} ?? 0), 2)) > 0.01) {
                return false;
            }
        }

        return true;
    }

    private function date(mixed $value): ?string
    {
        $value = $this->normalize($value);
        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
