<?php

namespace App\Services;

use Illuminate\Database\Connection;

final class SpjV2CanonicalSourceResolver
{
    /**
     * Resolve the canonical ARKAS source for one active fiscal-year/fund-source
     * context without guessing a source id.
     *
     * @return array{status:string,source_id:?int,source_ids:array<int,int>,reason:string}
     */
    public function resolve(Connection $db, int $fiscalYearId, int $fundSourceId): array
    {
        $schema = $db->getSchemaBuilder();
        if (! $schema->hasTable('spj_transactions')
            || ! $schema->hasColumn('spj_transactions', 'canonical_context_status')) {
            return [
                'status' => 'UNAVAILABLE',
                'source_id' => null,
                'source_ids' => [],
                'reason' => 'canonical V2 transaction schema is unavailable',
            ];
        }

        $sourceIds = $db->table('spj_transactions')
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('fund_source_id', $fundSourceId)
            ->where('canonical_context_status', 'ACTIVE_CANONICAL')
            ->whereIn('source_status', ['ACTIVE', 'SOURCE_MISSING'])
            ->distinct()
            ->orderBy('source_id')
            ->pluck('source_id')
            ->map(fn ($sourceId): int => (int) $sourceId)
            ->values()
            ->all();

        if ($sourceIds === []) {
            return [
                'status' => 'UNAVAILABLE',
                'source_id' => null,
                'source_ids' => [],
                'reason' => 'no active canonical V2 source exists for the requested context',
            ];
        }

        if (count($sourceIds) !== 1) {
            return [
                'status' => 'AMBIGUOUS',
                'source_id' => null,
                'source_ids' => $sourceIds,
                'reason' => 'multiple canonical V2 sources exist for the requested context',
            ];
        }

        return [
            'status' => 'RESOLVED',
            'source_id' => $sourceIds[0],
            'source_ids' => $sourceIds,
            'reason' => 'exactly one canonical V2 source exists for the requested context',
        ];
    }
}
