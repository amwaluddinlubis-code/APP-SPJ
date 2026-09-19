<?php

namespace App\Services;

use Illuminate\Database\Connection;

final class SpjV2PackageReadMembershipService
{
    public function __construct(
        private readonly SpjReadPathSelector $readPaths,
        private readonly SpjV2EffectiveContextCompatibilityService $effectiveContexts,
    ) {}

    /**
     * Resolve Paket membership for one active fiscal-year/fund-source context
     * without trusting legacy transactions.fiscal_year_id.
     *
     * Null means the caller must stay on the legacy read path.
     *
     * @return array{package_ids:list<int>,legacy_transaction_ids:list<int>,canonical_transaction_ids:list<int>,source_id:int,compatibility_mode:string}|null
     */
    public function forContext(
        Connection $db,
        int $fiscalYearId,
        int $fundSourceId,
    ): ?array {
        $selection = $this->readPaths->select($db, $fiscalYearId, $fundSourceId);
        if ($selection['path'] !== SpjReadPathSelector::V2 || $selection['source_id'] === null) {
            return null;
        }

        $resolved = $this->effectiveContexts->resolve(
            $db,
            $fiscalYearId,
            $fundSourceId,
            $selection['source_id'],
        );

        if ($resolved['status'] !== 'RESOLVED') {
            return null;
        }

        return [
            'package_ids' => array_values(array_map('intval', $resolved['package_ids'])),
            'legacy_transaction_ids' => array_values(array_map('intval', $resolved['legacy_transaction_ids'])),
            'canonical_transaction_ids' => array_values(array_map('intval', $resolved['canonical_transaction_ids'])),
            'source_id' => $selection['source_id'],
            'compatibility_mode' => (string) $resolved['mode'],
        ];
    }
}
