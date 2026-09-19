<?php

namespace App\Services;

use App\Models\SpjPackage;
use App\Support\ActiveSpjContext;
use Illuminate\Support\Facades\DB;

final class SpjV2PackageReadContextService
{
    public function __construct(
        private readonly ActiveSpjContext $context,
        private readonly SpjV2PackageReadMembershipService $membership,
    ) {}

    /**
     * Prepare a Paket for a read-only consumer.
     *
     * Legacy-aligned Paket pass unchanged. A stale legacy fiscal-year Paket is
     * eligible only when the V2 membership resolver proves that the Paket,
     * legacy transaction, and canonical transaction all belong to the active
     * fiscal-year/fund-source context.
     *
     * For compatible V2 reads the fiscal_year_id is normalized only on the
     * in-memory legacy Transaction model so template/profile lookup uses the
     * effective active year. No database row is updated.
     */
    public function prepare(SpjPackage $package): bool
    {
        $transaction = $package->transaction;

        if ($this->context->matchesTransaction($transaction)) {
            $package->setAttribute('read_context_path', 'legacy');

            return true;
        }

        $fundSourceId = $this->context->fundSourceId();
        if ($fundSourceId === null) {
            return false;
        }

        $membership = $this->membership->forContext(
            DB::connection('school'),
            $this->context->fiscalYearId(),
            $fundSourceId,
        );

        if ($membership === null
            || ! in_array((int) $package->getKey(), $membership['package_ids'], true)
            || ! in_array((int) $transaction->getKey(), $membership['legacy_transaction_ids'], true)
            || $package->spj_transaction_id === null
            || ! in_array((int) $package->spj_transaction_id, $membership['canonical_transaction_ids'], true)
            || (int) $transaction->fund_source_id !== $fundSourceId) {
            return false;
        }

        $transaction->setAttribute('fiscal_year_id', $this->context->fiscalYearId());
        $transaction->unsetRelation('fiscalYear');
        $package->setAttribute('read_context_path', 'v2_compat');
        $package->setAttribute('read_context_source_id', $membership['source_id']);
        $package->setAttribute('read_context_mode', $membership['compatibility_mode']);

        return true;
    }
}
