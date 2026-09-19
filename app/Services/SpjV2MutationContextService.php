<?php

namespace App\Services;

use App\Models\SpjPackage;
use App\Support\ActiveSpjContext;
use Illuminate\Support\Facades\DB;

final class SpjV2MutationContextService
{
    public function __construct(
        private readonly ActiveSpjContext $context,
        private readonly SpjV2PackageReadMembershipService $packageReadMembership,
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
            $package->setAttribute('mutation_context_path', 'legacy');
            $transaction->setAttribute('mutation_context_path', 'legacy');

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

        $transaction->setAttribute('fiscal_year_id', $this->context->fiscalYearId());
        $transaction->unsetRelation('fiscalYear');
        $transaction->setAttribute('mutation_context_path', 'v2_compat');
        $package->setAttribute('mutation_context_path', 'v2_compat');
        $package->setAttribute('mutation_context_source_id', $membership['source_id']);
        $package->setAttribute('mutation_context_mode', $membership['compatibility_mode']);

        return true;
    }
}
