<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Collection;

final class SpjV2EffectiveContextCompatibilityService
{
    /**
     * Audit every migrated legacy/V2 context and Paket bridge without mutating
     * legacy transactions, Paket, documents, or canonical V2 rows.
     *
     * @return array<string, mixed>
     */
    public function audit(Connection $db): array
    {
        $schemaIssue = $this->schemaIssue($db);
        if ($schemaIssue !== null) {
            return [
                'status' => 'UNAVAILABLE',
                'reason' => $schemaIssue,
                'counts' => [],
                'packages' => [],
                'provenance' => [],
                'contexts' => [],
            ];
        }

        $mappingRows = $this->mappingRows($db);
        $packageRows = $this->packageRows($db);
        $packageClassifications = $packageRows->map(fn (object $row): array => $this->classifyPackage($row));

        $contexts = $db->table('spj_transactions')
            ->where('canonical_context_status', 'ACTIVE_CANONICAL')
            ->select('fiscal_year_id', 'fund_source_id', 'source_id')
            ->distinct()
            ->orderBy('fiscal_year_id')
            ->orderBy('fund_source_id')
            ->orderBy('source_id')
            ->get()
            ->map(fn (object $context): array => $this->resolve(
                $db,
                (int) $context->fiscal_year_id,
                (int) $context->fund_source_id,
                (int) $context->source_id,
            ))
            ->values();

        $unsafePackages = $packageClassifications
            ->where('compatibility_status', 'UNSAFE')
            ->values();
        $blockedContexts = $contexts
            ->where('status', 'BLOCKED')
            ->values();
        $stalePackages = $packageClassifications
            ->where('compatibility_status', 'STALE_LEGACY_FISCAL_YEAR')
            ->values();

        $status = $unsafePackages->isNotEmpty() || $blockedContexts->isNotEmpty()
            ? 'FAIL_UNSAFE'
            : ($stalePackages->isNotEmpty() ? 'COMPATIBLE_STALE_CONTEXT' : 'PASS_ALIGNED');

        return [
            'status' => $status,
            'reason' => match ($status) {
                'FAIL_UNSAFE' => 'one or more provenance/context/package relations are unsafe for compatibility reads',
                'COMPATIBLE_STALE_CONTEXT' => 'all audited relations are deterministic, but legacy fiscal_year_id is stale for part of the effective canonical context',
                default => 'legacy and canonical effective contexts are aligned',
            },
            'counts' => [
                'legacy_mappings' => $mappingRows->count(),
                'active_canonical_transactions' => (int) $db->table('spj_transactions')
                    ->where('canonical_context_status', 'ACTIVE_CANONICAL')
                    ->count(),
                'contexts' => $contexts->count(),
                'blocked_contexts' => $blockedContexts->count(),
            ],
            'packages' => [
                'total' => $packageRows->count(),
                'numbered' => $packageRows->where('package_status', 'NUMBERED')->count(),
                'final' => $packageRows->where('package_status', 'FINAL')->count(),
                'aligned' => $packageClassifications->where('compatibility_status', 'ALIGNED')->count(),
                'stale_legacy_fiscal_year' => $stalePackages->count(),
                'unsafe' => $unsafePackages->count(),
                'duplicate_provenance' => $packageClassifications
                    ->where('provenance_status', 'LEGACY_DUPLICATE')
                    ->count(),
                'classification' => $packageClassifications
                    ->groupBy('compatibility_status')
                    ->map(fn (Collection $rows): int => $rows->count())
                    ->sortKeys()
                    ->all(),
                'rows' => $packageClassifications->all(),
            ],
            'provenance' => [
                'aligned_legacy_context' => $mappingRows
                    ->filter(fn (object $row): bool => (int) $row->legacy_fiscal_year_id === (int) $row->effective_fiscal_year_id
                        && (int) $row->legacy_fund_source_id === (int) $row->effective_fund_source_id)
                    ->count(),
                'stale_legacy_fiscal_year' => $mappingRows
                    ->filter(fn (object $row): bool => (int) $row->legacy_fiscal_year_id !== (int) $row->effective_fiscal_year_id
                        && (int) $row->legacy_fund_source_id === (int) $row->effective_fund_source_id)
                    ->count(),
                'fund_source_mismatch' => $mappingRows
                    ->filter(fn (object $row): bool => (int) $row->legacy_fund_source_id !== (int) $row->effective_fund_source_id)
                    ->count(),
                'legacy_duplicate' => $mappingRows
                    ->where('provenance_status', 'LEGACY_DUPLICATE')
                    ->count(),
            ],
            'contexts' => $contexts->all(),
        ];
    }

    /**
     * Resolve one effective canonical context to its deterministic legacy
     * provenance and Paket set. LEGACY_DUPLICATE provenance is allowed when it
     * still targets the same ACTIVE_CANONICAL transaction.
     *
     * @return array<string, mixed>
     */
    public function resolve(
        Connection $db,
        int $fiscalYearId,
        int $fundSourceId,
        int $sourceId,
    ): array {
        $schemaIssue = $this->schemaIssue($db);
        if ($schemaIssue !== null) {
            return $this->unavailable($fiscalYearId, $fundSourceId, $sourceId, $schemaIssue);
        }

        $canonicalIds = $db->table('spj_transactions')
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('fund_source_id', $fundSourceId)
            ->where('source_id', $sourceId)
            ->where('canonical_context_status', 'ACTIVE_CANONICAL')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values();

        if ($canonicalIds->isEmpty()) {
            return $this->unavailable(
                $fiscalYearId,
                $fundSourceId,
                $sourceId,
                'no ACTIVE_CANONICAL V2 transactions exist for the requested context',
            );
        }

        $mappings = $this->mappingRows($db)
            ->whereIn('effective_transaction_id', $canonicalIds->all())
            ->values();

        $mappedCanonicalIds = $mappings
            ->pluck('effective_transaction_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->sort()
            ->values();
        $missingCanonicalIds = $canonicalIds->diff($mappedCanonicalIds)->values();

        $unsafe = [];
        foreach ($mappings as $mapping) {
            if ((int) $mapping->effective_fiscal_year_id !== $fiscalYearId
                || (int) $mapping->effective_fund_source_id !== $fundSourceId
                || (int) $mapping->source_id !== $sourceId) {
                $unsafe[] = [
                    'type' => 'CROSS_CONTEXT_PROVENANCE',
                    'legacy_transaction_id' => (int) $mapping->legacy_transaction_id,
                    'spj_transaction_id' => (int) $mapping->effective_transaction_id,
                ];
            }
            if ((int) $mapping->legacy_fund_source_id !== $fundSourceId) {
                $unsafe[] = [
                    'type' => 'FUND_SOURCE_MISMATCH',
                    'legacy_transaction_id' => (int) $mapping->legacy_transaction_id,
                    'legacy_fund_source_id' => (int) $mapping->legacy_fund_source_id,
                    'effective_fund_source_id' => $fundSourceId,
                ];
            }
            if (! in_array((string) $mapping->provenance_status, ['ACTIVE_CANONICAL', 'LEGACY_DUPLICATE'], true)) {
                $unsafe[] = [
                    'type' => 'UNSAFE_PROVENANCE_STATUS',
                    'legacy_transaction_id' => (int) $mapping->legacy_transaction_id,
                    'provenance_status' => (string) $mapping->provenance_status,
                ];
            }
        }

        foreach ($missingCanonicalIds as $missingId) {
            $unsafe[] = [
                'type' => 'MISSING_LEGACY_PROVENANCE',
                'spj_transaction_id' => (int) $missingId,
            ];
        }

        $packages = $db->table('spj_packages')
            ->whereIn('spj_transaction_id', $canonicalIds->all())
            ->orderBy('id')
            ->get();

        $mapsByLegacyId = $mappings->keyBy('legacy_transaction_id');
        foreach ($packages as $package) {
            $map = $mapsByLegacyId->get($package->transaction_id);
            if ($map === null) {
                $unsafe[] = [
                    'type' => 'PACKAGE_WITHOUT_CONTEXT_PROVENANCE',
                    'package_id' => (int) $package->id,
                    'legacy_transaction_id' => (int) $package->transaction_id,
                    'spj_transaction_id' => (int) $package->spj_transaction_id,
                ];

                continue;
            }

            if ((int) $map->effective_transaction_id !== (int) $package->spj_transaction_id) {
                $unsafe[] = [
                    'type' => 'PACKAGE_PROVENANCE_LINK_MISMATCH',
                    'package_id' => (int) $package->id,
                    'legacy_transaction_id' => (int) $package->transaction_id,
                    'expected_spj_transaction_id' => (int) $map->effective_transaction_id,
                    'actual_spj_transaction_id' => (int) $package->spj_transaction_id,
                ];
            }
        }

        $multiplePackageTargets = $packages
            ->groupBy('spj_transaction_id')
            ->filter(fn (Collection $rows): bool => $rows->count() > 1);

        foreach ($multiplePackageTargets as $transactionId => $rows) {
            $unsafe[] = [
                'type' => 'MULTIPLE_PACKAGES_PER_CANONICAL_TRANSACTION',
                'spj_transaction_id' => (int) $transactionId,
                'package_ids' => $rows->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
            ];
        }

        $alignedLegacyIds = $mappings
            ->filter(fn (object $row): bool => (int) $row->legacy_fiscal_year_id === $fiscalYearId)
            ->pluck('legacy_transaction_id')
            ->map(fn ($id): int => (int) $id)
            ->sort()
            ->values();
        $staleLegacyIds = $mappings
            ->filter(fn (object $row): bool => (int) $row->legacy_fiscal_year_id !== $fiscalYearId)
            ->pluck('legacy_transaction_id')
            ->map(fn ($id): int => (int) $id)
            ->sort()
            ->values();
        $duplicateLegacyIds = $mappings
            ->where('provenance_status', 'LEGACY_DUPLICATE')
            ->pluck('legacy_transaction_id')
            ->map(fn ($id): int => (int) $id)
            ->sort()
            ->values();

        return [
            'status' => $unsafe === [] ? 'RESOLVED' : 'BLOCKED',
            'mode' => $unsafe !== []
                ? 'UNSAFE'
                : ($staleLegacyIds->isNotEmpty() ? 'STALE_COMPATIBLE' : 'ALIGNED'),
            'reason' => $unsafe === []
                ? ($staleLegacyIds->isNotEmpty()
                    ? 'canonical context is deterministic through provenance although one or more legacy fiscal_year_id values are stale'
                    : 'legacy provenance and canonical effective context are aligned')
                : 'effective-context compatibility contains unsafe or ambiguous relations',
            'context' => [
                'fiscal_year_id' => $fiscalYearId,
                'fund_source_id' => $fundSourceId,
                'source_id' => $sourceId,
            ],
            'canonical_transaction_ids' => $canonicalIds->all(),
            'legacy_transaction_ids' => $mappings
                ->pluck('legacy_transaction_id')
                ->map(fn ($id): int => (int) $id)
                ->sort()
                ->values()
                ->all(),
            'aligned_legacy_transaction_ids' => $alignedLegacyIds->all(),
            'stale_legacy_transaction_ids' => $staleLegacyIds->all(),
            'duplicate_provenance_legacy_transaction_ids' => $duplicateLegacyIds->all(),
            'package_ids' => $packages->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
            'numbered_package_ids' => $packages
                ->where('status', 'NUMBERED')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all(),
            'unsafe' => array_values($unsafe),
        ];
    }

    /** @return Collection<int, object> */
    private function mappingRows(Connection $db): Collection
    {
        return $db->table('legacy_transaction_v2_map as provenance')
            ->join('transactions as legacy', 'legacy.id', '=', 'provenance.legacy_transaction_id')
            ->join('spj_transactions as v2', 'v2.id', '=', 'provenance.spj_transaction_id')
            ->select([
                'provenance.legacy_transaction_id',
                'provenance.spj_transaction_id as effective_transaction_id',
                'provenance.canonical_context_status as provenance_status',
                'provenance.legacy_fiscal_year_id as recorded_legacy_fiscal_year_id',
                'provenance.effective_context_key',
                'legacy.fiscal_year_id as legacy_fiscal_year_id',
                'legacy.fund_source_id as legacy_fund_source_id',
                'legacy.transaction_date',
                'v2.fiscal_year_id as effective_fiscal_year_id',
                'v2.fund_source_id as effective_fund_source_id',
                'v2.source_id',
                'v2.canonical_context_status as canonical_status',
            ])
            ->orderBy('provenance.legacy_transaction_id')
            ->get();
    }

    /** @return Collection<int, object> */
    private function packageRows(Connection $db): Collection
    {
        return $db->table('spj_packages as package')
            ->leftJoin('transactions as legacy', 'legacy.id', '=', 'package.transaction_id')
            ->leftJoin('legacy_transaction_v2_map as provenance', 'provenance.legacy_transaction_id', '=', 'package.transaction_id')
            ->leftJoin('spj_transactions as v2', 'v2.id', '=', 'package.spj_transaction_id')
            ->select([
                'package.id as package_id',
                'package.transaction_id as legacy_transaction_id',
                'package.spj_transaction_id',
                'package.status as package_status',
                'package.document_number',
                'legacy.fiscal_year_id as legacy_fiscal_year_id',
                'legacy.fund_source_id as legacy_fund_source_id',
                'legacy.transaction_date',
                'provenance.spj_transaction_id as provenance_spj_transaction_id',
                'provenance.canonical_context_status as provenance_status',
                'v2.fiscal_year_id as effective_fiscal_year_id',
                'v2.fund_source_id as effective_fund_source_id',
                'v2.source_id',
                'v2.canonical_context_status as canonical_status',
            ])
            ->orderBy('package.id')
            ->get();
    }

    /** @return array<string, mixed> */
    private function classifyPackage(object $row): array
    {
        $reasons = [];

        if ($row->spj_transaction_id === null) {
            $reasons[] = 'missing V2 package link';
        }
        if ($row->provenance_spj_transaction_id === null) {
            $reasons[] = 'missing legacy provenance';
        }
        if ($row->spj_transaction_id !== null
            && $row->provenance_spj_transaction_id !== null
            && (int) $row->spj_transaction_id !== (int) $row->provenance_spj_transaction_id) {
            $reasons[] = 'package V2 link differs from provenance target';
        }
        if ((string) ($row->canonical_status ?? '') !== 'ACTIVE_CANONICAL') {
            $reasons[] = 'package target is not ACTIVE_CANONICAL';
        }
        if ($row->legacy_fund_source_id === null
            || $row->effective_fund_source_id === null
            || (int) $row->legacy_fund_source_id !== (int) $row->effective_fund_source_id) {
            $reasons[] = 'legacy and effective fund source differ';
        }
        if (! in_array((string) ($row->provenance_status ?? ''), ['ACTIVE_CANONICAL', 'LEGACY_DUPLICATE'], true)) {
            $reasons[] = 'provenance status is not eligible for compatibility reads';
        }

        $compatibilityStatus = $reasons !== []
            ? 'UNSAFE'
            : ((int) $row->legacy_fiscal_year_id === (int) $row->effective_fiscal_year_id
                ? 'ALIGNED'
                : 'STALE_LEGACY_FISCAL_YEAR');

        return [
            'package_id' => (int) $row->package_id,
            'package_status' => (string) $row->package_status,
            'document_number' => $row->document_number,
            'legacy_transaction_id' => (int) $row->legacy_transaction_id,
            'spj_transaction_id' => $row->spj_transaction_id === null ? null : (int) $row->spj_transaction_id,
            'legacy_fiscal_year_id' => $row->legacy_fiscal_year_id === null ? null : (int) $row->legacy_fiscal_year_id,
            'effective_fiscal_year_id' => $row->effective_fiscal_year_id === null ? null : (int) $row->effective_fiscal_year_id,
            'legacy_fund_source_id' => $row->legacy_fund_source_id === null ? null : (int) $row->legacy_fund_source_id,
            'effective_fund_source_id' => $row->effective_fund_source_id === null ? null : (int) $row->effective_fund_source_id,
            'source_id' => $row->source_id === null ? null : (int) $row->source_id,
            'provenance_status' => (string) ($row->provenance_status ?? ''),
            'canonical_status' => (string) ($row->canonical_status ?? ''),
            'compatibility_status' => $compatibilityStatus,
            'reasons' => $reasons,
        ];
    }

    private function schemaIssue(Connection $db): ?string
    {
        $schema = $db->getSchemaBuilder();
        foreach (['transactions', 'spj_transactions', 'legacy_transaction_v2_map', 'spj_packages'] as $table) {
            if (! $schema->hasTable($table)) {
                return 'effective-context compatibility requires table: '.$table;
            }
        }

        foreach ([
            ['legacy_transaction_v2_map', 'canonical_context_status'],
            ['legacy_transaction_v2_map', 'legacy_fiscal_year_id'],
            ['legacy_transaction_v2_map', 'effective_context_key'],
            ['spj_transactions', 'canonical_context_status'],
            ['spj_packages', 'spj_transaction_id'],
        ] as [$table, $column]) {
            if (! $schema->hasColumn($table, $column)) {
                return 'effective-context compatibility requires column: '.$table.'.'.$column;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function unavailable(
        int $fiscalYearId,
        int $fundSourceId,
        int $sourceId,
        string $reason,
    ): array {
        return [
            'status' => 'UNAVAILABLE',
            'mode' => 'UNAVAILABLE',
            'reason' => $reason,
            'context' => [
                'fiscal_year_id' => $fiscalYearId,
                'fund_source_id' => $fundSourceId,
                'source_id' => $sourceId,
            ],
            'canonical_transaction_ids' => [],
            'legacy_transaction_ids' => [],
            'aligned_legacy_transaction_ids' => [],
            'stale_legacy_transaction_ids' => [],
            'duplicate_provenance_legacy_transaction_ids' => [],
            'package_ids' => [],
            'numbered_package_ids' => [],
            'unsafe' => [],
        ];
    }
}
