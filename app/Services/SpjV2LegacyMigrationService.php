<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

final class SpjV2LegacyMigrationService
{
    public function __construct(
        private readonly V2BSourceIdentityRegistryService $identities,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function migrate(Connection $db, int $sourceId, bool $execute, string $reportPath, ?int $npsn = null): array
    {
        $startedAt = now()->toIso8601String();
        $legacyTransactions = $db->table('transactions')->orderBy('id')->get();
        $legacyItems = $db->table('transaction_items')->orderBy('id')->get()->groupBy('transaction_id');
        $packages = $db->table('spj_packages')->orderBy('id')->get();
        $documents = $db->table('spj_documents')->orderBy('id')->get();
        $source = $this->loadKasUmumSource($db, $sourceId);
        $fiscalYears = $db->getSchemaBuilder()->hasTable('fiscal_years')
            ? $db->table('fiscal_years')->get()->keyBy('id')
            : collect();
        $sourcePlans = [];
        $validMembershipContexts = [];
        foreach ($legacyTransactions as $legacy) {
            $items = $legacyItems->get($legacy->id, collect());
            $sourceIds = $items->pluck('source_item_id')->map(fn ($id): string => trim((string) $id))->filter()->unique()->sort()->values()->all();
            $found = array_values(array_filter($sourceIds, fn (string $id): bool => isset($source['rows'][$id])));
            $missing = array_values(array_diff($sourceIds, $found));
            $mapping = $this->classify($sourceIds, $found, $missing, (string) ($legacy->source_key ?? ''), $source['membership_hashes']);
            $transactionYear = (int) date('Y', strtotime((string) $legacy->transaction_date));
            $effectiveFiscalYear = $fiscalYears->first(fn (object $year): bool => (int) $year->year === $transactionYear
                && (string) ($year->fund_source_id ?? '') === (string) ($legacy->fund_source_id ?? ''));
            $legacyFiscalYear = $fiscalYears->get($legacy->fiscal_year_id);
            $legacyContextMatches = $legacyFiscalYear !== null
                && (int) $legacyFiscalYear->year === $transactionYear
                && (string) ($legacyFiscalYear->fund_source_id ?? '') === (string) ($legacy->fund_source_id ?? '');
            $contextValid = $effectiveFiscalYear !== null;
            $contextKey = (string) ($effectiveFiscalYear?->id ?? $legacy->fiscal_year_id).':'.(string) $legacy->fund_source_id.':'.$sourceId;
            if ($contextValid && $mapping['membership_hash'] !== null) {
                $validMembershipContexts[$mapping['membership_hash']][$contextKey][] = [
                    'legacy_id' => (int) $legacy->id,
                    'legacy_context_matches' => $legacyContextMatches,
                ];
            }
            $sourcePlans[$legacy->id] = [
                'items' => $items,
                'source_ids' => $sourceIds,
                'found' => $found,
                'missing' => $missing,
                'mapping' => $mapping,
                'context_valid' => $contextValid,
                'effective_fiscal_year_id' => $effectiveFiscalYear?->id,
                'effective_fund_source_id' => $legacy->fund_source_id,
                'effective_context_key' => $contextKey,
                'legacy_fiscal_year_id' => $legacy->fiscal_year_id,
                'legacy_context_matches' => $legacyContextMatches,
            ];
        }
        $beforeProtected = $this->protectedManifest($packages, $documents);
        $counts = array_fill_keys(['EXACT', 'DETERMINISTIC', 'PARTIAL', 'SOURCE_MISSING', 'AMBIGUOUS', 'LEGACY_ONLY'], 0);
        $packageSensitive = array_fill_keys(['DRAFT', 'NUMBERED', 'FINAL', 'OTHER'], 0);
        $itemOverlayCount = 0;
        $transactionOverlayCount = 0;
        $sourceLinkCount = 0;
        $legacyMapCount = 0;
        $errors = [];
        $plans = [];

        foreach ($legacyTransactions as $legacy) {
            $sourcePlan = $sourcePlans[$legacy->id];
            $items = $sourcePlan['items'];
            $sourceIds = $sourcePlan['source_ids'];
            $found = $sourcePlan['found'];
            $missing = $sourcePlan['missing'];
            $classification = $sourcePlan['mapping'];
            $canonical = $this->classifyCanonicalContext(
                $legacy,
                $classification,
                $sourcePlan['context_valid'],
                $sourcePlan['effective_context_key'],
                $sourcePlan['legacy_context_matches'],
                $validMembershipContexts,
            );
            $counts[$classification['status']]++;
            $package = $packages->firstWhere('transaction_id', $legacy->id);
            if ($package !== null) {
                $status = strtoupper((string) ($package->status ?? 'OTHER'));
                $packageSensitive[array_key_exists($status, $packageSensitive) ? $status : 'OTHER']++;
            }

            $plan = [
                'legacy_transaction_id' => (int) $legacy->id,
                'legacy_source_key' => (string) ($legacy->source_key ?? ''),
                'status' => $classification['status'],
                'reason' => $classification['reason'],
                'current_membership_hash' => $classification['membership_hash'],
                'source_keys' => $found,
                'missing_source_keys' => $missing,
                'canonical_context_status' => $canonical['status'],
                'canonical_context_reason' => $canonical['reason'],
                'effective_fiscal_year_id' => $sourcePlan['effective_fiscal_year_id'],
                'effective_fund_source_id' => $sourcePlan['effective_fund_source_id'],
                'effective_context_key' => $sourcePlan['effective_context_key'],
                'legacy_fiscal_year_id' => $sourcePlan['legacy_fiscal_year_id'],
                'package_id' => $package?->id,
                'package_status' => $package?->status,
            ];
            $plans[] = $plan;

            if (! $execute || ! in_array($classification['status'], ['EXACT', 'DETERMINISTIC'], true)) {
                continue;
            }

            try {
                $result = $this->migrateOne($db, $legacy, $items, $found, $classification, $canonical, $sourcePlan, $source['rows'], $package, $sourceId);
                $sourceLinkCount += $result['source_links'];
                $itemOverlayCount += $result['item_overlays'];
                $transactionOverlayCount += $result['transaction_overlay'];
                $legacyMapCount += $result['legacy_map'];
            } catch (\Throwable $exception) {
                $errors[] = ['legacy_transaction_id' => (int) $legacy->id, 'message' => $exception->getMessage()];
            }
        }

        $afterProtected = $this->protectedManifest(
            $db->table('spj_packages')->orderBy('id')->get(),
            $db->table('spj_documents')->orderBy('id')->get(),
        );
        $result = [
            'schema' => 'V2-C',
            'mode' => $execute ? 'execute' : 'dry-run',
            'npsn' => $npsn,
            'started_at' => $startedAt,
            'finished_at' => now()->toIso8601String(),
            'legacy' => [
                'transactions' => $legacyTransactions->count(),
                'items' => $legacyItems->flatten()->count(),
                'packages' => $packages->count(),
                'documents' => $documents->count(),
            ],
            'classification' => $counts,
            'canonical_context_classification' => $this->canonicalCounts($plans),
            'package_sensitive_classification' => $packageSensitive,
            'migrated' => [
                'v2_transactions' => (int) $db->getSchemaBuilder()->hasTable('spj_transactions') ? $db->table('spj_transactions')->count() : 0,
                'source_links' => $sourceLinkCount,
                'transaction_overlays' => $transactionOverlayCount,
                'item_overlays' => $db->getSchemaBuilder()->hasTable('spj_item_overlays') ? $db->table('spj_item_overlays')->count() : 0,
                'created_item_overlays' => $itemOverlayCount,
                'legacy_maps' => $legacyMapCount,
                'package_v2_links' => $db->getSchemaBuilder()->hasColumn('spj_packages', 'spj_transaction_id') ? $db->table('spj_packages')->whereNotNull('spj_transaction_id')->count() : 0,
            ],
            'source' => [
                'source_id' => $sourceId,
                'source_table' => 'kas_umum',
                'available' => $source['available'],
                'raw_rows' => count($source['rows']),
                'referenced_registry_rows' => $execute ? (int) $db->table('arkas_source_identity_registry')->where('source_id', $sourceId)->where('source_table', 'kas_umum')->count() : 0,
            ],
            'package_document_continuity' => [
                'unchanged' => $beforeProtected === $afterProtected,
                'before_hash' => $this->hashArray($beforeProtected),
                'after_hash' => $this->hashArray($afterProtected),
            ],
            'package_document_matrix' => $this->packageDocumentMatrix($db),
            'source_adapter_validation' => $execute ? $this->sourceAdapterValidation($db, $sourceId) : ['status' => 'NOT_RUN_DRY_RUN'],
            'item_overlay_reconciliation' => $this->itemOverlayReconciliation($db, $legacyItems),
            'errors' => $errors,
            'plan' => $plans,
        ];

        File::ensureDirectoryExists(dirname($reportPath));
        File::put($reportPath, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return $result + ['report_path' => $reportPath];
    }

    /** @return array<string, mixed> */
    public function verify(Connection $db): array
    {
        $tables = ['spj_transactions', 'spj_transaction_sources', 'spj_transaction_overlays', 'spj_item_overlays', 'legacy_transaction_v2_map'];
        foreach ($tables as $table) {
            if (! $db->getSchemaBuilder()->hasTable($table)) {
                throw new RuntimeException('V2-C verify requires table: '.$table);
            }
        }
        $legacyItems = $db->table('transaction_items')->get()->groupBy('transaction_id');

        $orphans = [
            'source_links_without_transaction' => $this->countOrphans($db, 'spj_transaction_sources', 'spj_transaction_id', 'spj_transactions'),
            'source_links_without_identity' => $this->countOrphans($db, 'spj_transaction_sources', 'arkas_source_identity_id', 'arkas_source_identity_registry'),
            'transaction_overlays_without_transaction' => $this->countOrphans($db, 'spj_transaction_overlays', 'spj_transaction_id', 'spj_transactions'),
            'item_overlays_without_source_link' => $this->countOrphans($db, 'spj_item_overlays', 'spj_transaction_source_id', 'spj_transaction_sources'),
            'legacy_maps_without_transaction' => $this->countOrphans($db, 'legacy_transaction_v2_map', 'spj_transaction_id', 'spj_transactions'),
            'package_links_without_transaction' => $this->countOrphans($db, 'spj_packages', 'spj_transaction_id', 'spj_transactions'),
        ];
        $contextIsolation = $this->contextIsolation($db);
        $canonical = $this->canonicalVerification($db);
        $overlay = $this->itemOverlayReconciliation($db, $legacyItems);
        $sourceId = (int) $db->table('arkas_source_identity_registry')->value('source_id');
        $sourceAdapter = $this->sourceAdapterValidation($db, $sourceId);
        $activeSourceAdapter = $this->sourceAdapterValidation($db, $sourceId, 'ACTIVE_CANONICAL');
        $gates = [
            'integrity' => (string) $db->selectOne('PRAGMA integrity_check')->integrity_check === 'ok',
            'foreign_keys' => count($db->select('PRAGMA foreign_key_check')) === 0,
            'orphans' => max($orphans) === 0,
            'source_adapter' => $sourceAdapter['status'] === 'PASS',
            'context_isolation' => $contextIsolation['status'] === 'PASS',
            'canonical_context' => $canonical['status'] === 'PASS',
            'overlay_reconciliation' => $overlay['status'] === 'PASS',
        ];

        return [
            'integrity_check' => (string) $db->selectOne('PRAGMA integrity_check')->integrity_check,
            'foreign_key_violations' => count($db->select('PRAGMA foreign_key_check')),
            'source_adapter_validation' => $sourceAdapter,
            'financial_reconciliation' => [
                'ALL_LEGACY_MAPPED' => $sourceAdapter,
                'ACTIVE_CANONICAL' => $activeSourceAdapter,
            ],
            'context_isolation' => $contextIsolation,
            'orphans' => $orphans,
            'counts' => [
                'spj_transactions' => $db->table('spj_transactions')->count(),
                'spj_transaction_sources' => $db->table('spj_transaction_sources')->count(),
                'spj_transaction_overlays' => $db->table('spj_transaction_overlays')->count(),
                'spj_item_overlays' => $db->table('spj_item_overlays')->count(),
                'legacy_transaction_v2_map' => $db->table('legacy_transaction_v2_map')->count(),
                'package_v2_links' => $db->table('spj_packages')->whereNotNull('spj_transaction_id')->count(),
            ],
            'canonical_context_classification' => $db->table('spj_transactions')
                ->select('canonical_context_status', DB::raw('COUNT(*) as count'))
                ->groupBy('canonical_context_status')
                ->pluck('count', 'canonical_context_status')
                ->all(),
            'canonical_reconciliation' => $canonical,
            'package_document_matrix' => $this->packageDocumentMatrix($db),
            'item_overlay_reconciliation' => $overlay,
            'gates' => $gates,
            'status' => in_array(false, $gates, true) ? 'FAIL' : 'PASS',
        ];
    }

    /** @return array{rows:array<string, object>,membership_hashes:array<string, bool>,available:bool} */
    private function loadKasUmumSource(Connection $db, int $sourceId): array
    {
        if (! $db->getSchemaBuilder()->hasTable('arkas_raw_mirror_tables')
            || ! $db->getSchemaBuilder()->hasTable('arkas_raw_mirror_rows')) {
            return ['rows' => [], 'membership_hashes' => [], 'available' => false];
        }

        $table = $db->table('arkas_raw_mirror_tables')->where('source_id', $sourceId)->where('source_table', 'kas_umum')->where('status', 'ACTIVE')->first();
        if ($table === null) {
            return ['rows' => [], 'membership_hashes' => [], 'available' => false];
        }

        $rows = [];
        foreach ($db->table('arkas_raw_mirror_rows')->where('mirror_table_id', $table->id)->get() as $row) {
            $rows[(string) $row->source_key] = $row;
        }

        return ['rows' => $rows, 'membership_hashes' => [], 'available' => true];
    }

    /** @param array<int, string> $sourceIds @param array<int, string> $found @param array<int, string> $missing @param array<string, bool> $membershipHashes @return array{status:string,reason:string,membership_hash:?string} */
    private function classify(array $sourceIds, array $found, array $missing, string $legacySourceKey, array $membershipHashes): array
    {
        if ($sourceIds === []) {
            return ['status' => 'LEGACY_ONLY', 'reason' => 'transaction has no source_item_id values', 'membership_hash' => null];
        }
        if ($found === []) {
            return ['status' => 'SOURCE_MISSING', 'reason' => 'all source_item_id values are absent from active kas_umum mirror', 'membership_hash' => null];
        }
        if ($missing !== []) {
            return ['status' => 'PARTIAL', 'reason' => 'some source_item_id values are absent from active kas_umum mirror', 'membership_hash' => null];
        }

        $currentHash = hash('sha256', implode('|', $found));

        return [
            'status' => hash_equals($legacySourceKey, $currentHash) ? 'EXACT' : 'DETERMINISTIC',
            'reason' => hash_equals($legacySourceKey, $currentHash) ? 'legacy source_key equals current membership hash' : 'all source items resolved; legacy source_key retained unchanged',
            'membership_hash' => $currentHash,
        ];
    }

    /** @param object $legacy @param \Illuminate\Support\Collection<int, object> $items @param array<int, string> $sourceIds @param array{status:string,reason:string,membership_hash:?string} $classification @param array{status:string,reason:string} $canonical @param array<string,mixed> $sourcePlan @param array<string, object> $rawRows */
    private function migrateOne(Connection $db, object $legacy, $items, array $sourceIds, array $classification, array $canonical, array $sourcePlan, array $rawRows, ?object $package, int $sourceId): array
    {
        return $db->transaction(function () use ($db, $legacy, $items, $sourceIds, $classification, $canonical, $sourcePlan, $rawRows, $package, $sourceId): array {
            $now = now();
            $v2 = $db->table('spj_transactions')
                ->where('fiscal_year_id', $sourcePlan['effective_fiscal_year_id'])
                ->where('fund_source_id', $sourcePlan['effective_fund_source_id'])
                ->where('source_id', $sourceId)
                ->where('source_membership_hash', $classification['membership_hash'])
                ->first();
            $attributes = [
                'fiscal_year_id' => $sourcePlan['effective_fiscal_year_id'],
                'fund_source_id' => $sourcePlan['effective_fund_source_id'],
                'source_id' => $sourceId,
                'source_membership_hash' => $classification['membership_hash'],
                'source_status' => 'ACTIVE',
                'requires_reconciliation' => $classification['status'] === 'DETERMINISTIC',
                'source_missing_since' => null,
                'canonical_context_status' => $canonical['status'],
                'canonical_context_reason' => $canonical['reason'],
                'updated_at' => $now,
            ];
            $v2Id = $v2?->id;
            if ($v2Id === null) {
                $v2Id = $db->table('spj_transactions')->insertGetId($attributes + ['created_at' => $now]);
            } else {
                $db->table('spj_transactions')->where('id', $v2Id)->update($attributes);
            }

            $sourceLinks = 0;
            $itemOverlays = 0;
            foreach ($sourceIds as $sortOrder => $sourceKey) {
                $identityId = $this->identities->registerOrRefresh($db, $sourceId, 'kas_umum', $sourceKey, ['id_kas_umum' => $sourceKey], 'PRIMARY_KEY', (int) $rawRows[$sourceKey]->id, $rawRows[$sourceKey]->payload_hash);
                $link = $db->table('spj_transaction_sources')->where('spj_transaction_id', $v2Id)->where('arkas_source_identity_id', $identityId)->first();
                if ($link === null) {
                    $linkId = $db->table('spj_transaction_sources')->insertGetId(['spj_transaction_id' => $v2Id, 'arkas_source_identity_id' => $identityId, 'sort_order' => $sortOrder, 'created_at' => $now, 'updated_at' => $now]);
                    $sourceLinks++;
                } else {
                    $linkId = $link->id;
                    $db->table('spj_transaction_sources')->where('id', $linkId)->update(['sort_order' => $sortOrder, 'updated_at' => $now]);
                }
                $item = $items->firstWhere('source_item_id', $sourceKey);
                if ($item !== null && filled($item->item_description)) {
                    $existingOverlay = $db->table('spj_item_overlays')->where('spj_transaction_source_id', $linkId)->first();
                    $itemValues = ['item_description' => $item->item_description, 'updated_at' => $now];
                    if ($existingOverlay === null) {
                        $db->table('spj_item_overlays')->insert($itemValues + ['spj_transaction_source_id' => $linkId, 'created_at' => $now]);
                        $itemOverlays++;
                    } else {
                        $db->table('spj_item_overlays')->where('id', $existingOverlay->id)->update($itemValues);
                    }
                }
            }

            $overlayValues = [
                'spj_category' => $legacy->spj_category,
                'payment_description' => $legacy->payment_description,
                'payment_method' => $legacy->payment_method,
                'payment_reference' => $legacy->payment_reference,
                'receipt_recipient_name' => $legacy->receipt_recipient_name,
                'updated_at' => $now,
            ];
            $overlay = $db->table('spj_transaction_overlays')->where('spj_transaction_id', $v2Id)->first();
            if ($overlay === null) {
                $db->table('spj_transaction_overlays')->insert($overlayValues + ['spj_transaction_id' => $v2Id, 'created_at' => $now]);
                $transactionOverlay = 1;
            } else {
                $db->table('spj_transaction_overlays')->where('id', $overlay->id)->update($overlayValues);
                $transactionOverlay = 0;
            }

            $mapping = $db->table('legacy_transaction_v2_map')->where('legacy_transaction_id', $legacy->id)->first();
            $mapValues = [
                'legacy_source_key' => $legacy->source_key,
                'mapping_status' => $classification['status'],
                'mapping_reason' => $classification['reason'],
                'current_membership_hash' => $classification['membership_hash'],
                'canonical_context_status' => $canonical['status'],
                'canonical_context_reason' => $canonical['reason'],
                'legacy_fiscal_year_id' => $sourcePlan['legacy_fiscal_year_id'],
                'effective_context_key' => $sourcePlan['effective_context_key'],
                'updated_at' => $now,
            ];
            if ($mapping === null) {
                $db->table('legacy_transaction_v2_map')->insert($mapValues + ['legacy_transaction_id' => $legacy->id, 'spj_transaction_id' => $v2Id, 'created_at' => $now]);
                $legacyMap = 1;
            } elseif ((int) $mapping->spj_transaction_id !== (int) $v2Id) {
                throw new RuntimeException('Legacy mapping points to a different V2 transaction.');
            } else {
                $db->table('legacy_transaction_v2_map')->where('id', $mapping->id)->update($mapValues);
                $legacyMap = 0;
            }

            if ($package !== null) {
                $existingPackage = $db->table('spj_packages')->where('id', $package->id)->first();
                if ($existingPackage->spj_transaction_id !== null && (int) $existingPackage->spj_transaction_id !== (int) $v2Id) {
                    throw new RuntimeException('Package already points to a different V2 transaction.');
                }
                if ($existingPackage->spj_transaction_id === null) {
                    $db->table('spj_packages')->where('id', $package->id)->update(['spj_transaction_id' => $v2Id]);
                }
            }

            return ['source_links' => $sourceLinks, 'item_overlays' => $itemOverlays, 'transaction_overlay' => $transactionOverlay, 'legacy_map' => $legacyMap];
        });
    }

    /** @param array{status:string,membership_hash:?string} $mapping @param array<string, array<string, array<int, array{legacy_id:int,legacy_context_matches:bool}>>> $validMembershipContexts @return array{status:string,reason:string} */
    private function classifyCanonicalContext(object $legacy, array $mapping, bool $contextValid, string $effectiveContextKey, bool $legacyContextMatches, array $validMembershipContexts): array
    {
        if ($mapping['status'] === 'SOURCE_MISSING') {
            return ['status' => 'SOURCE_MISSING', 'reason' => 'source mapping is unresolved because the active raw mirror does not contain the source items'];
        }
        if ($mapping['status'] === 'PARTIAL') {
            return ['status' => 'REQUIRES_REVIEW', 'reason' => 'source mapping is partial and cannot enter the canonical read path'];
        }
        if ($mapping['status'] === 'AMBIGUOUS') {
            return ['status' => 'REQUIRES_REVIEW', 'reason' => 'source mapping is ambiguous and cannot enter the canonical read path'];
        }
        if ($mapping['status'] === 'LEGACY_ONLY') {
            return ['status' => 'REQUIRES_REVIEW', 'reason' => 'legacy transaction has no source identity'];
        }
        if (! $contextValid) {
            $reason = 'fiscal year and fund source do not agree in the legacy context';
            if ($mapping['membership_hash'] !== null && count($validMembershipContexts[$mapping['membership_hash']][$effectiveContextKey] ?? []) > 0) {
                $reason .= '; source membership duplicates a valid context';
            }

            return ['status' => 'INVALID_CONTEXT', 'reason' => $reason];
        }
        $candidates = $mapping['membership_hash'] !== null
            ? ($validMembershipContexts[$mapping['membership_hash']][$effectiveContextKey] ?? [])
            : [];
        if (count($candidates) > 1) {
            usort($candidates, function (array $left, array $right): int {
                return [(int) $right['legacy_context_matches'], $left['legacy_id']] <=> [(int) $left['legacy_context_matches'], $right['legacy_id']];
            });
            if ((int) $legacy->id !== $candidates[0]['legacy_id']) {
                $reason = 'source membership duplicates another legacy row in the same effective fiscal/fund context';
                if (! $legacyContextMatches) {
                    $reason .= '; legacy fiscal_year_id does not match the effective transaction-date context';
                }

                return ['status' => 'LEGACY_DUPLICATE', 'reason' => $reason];
            }
        }

        return [
            'status' => 'ACTIVE_CANONICAL',
            'reason' => $legacyContextMatches
                ? 'fiscal year, fund source, and source identity are valid for the canonical context'
                : 'transaction date and fund source resolve to the canonical context; legacy fiscal_year_id is stale',
        ];
    }

    /** @param array<int, array<string, mixed>> $plans @return array<string, int> */
    private function canonicalCounts(array $plans): array
    {
        $counts = array_fill_keys(['ACTIVE_CANONICAL', 'HISTORICAL', 'LEGACY_DUPLICATE', 'INVALID_CONTEXT', 'SOURCE_MISSING', 'REQUIRES_REVIEW'], 0);
        foreach ($plans as $plan) {
            $status = $plan['canonical_context_status'];
            $counts[array_key_exists($status, $counts) ? $status : 'REQUIRES_REVIEW']++;
        }

        return $counts;
    }

    /** @param Collection<int, Collection<int, object>> $legacyItems @return array<string, int|string> */
    private function itemOverlayReconciliation(Connection $db, $legacyItems): array
    {
        $expected = [];
        $legacySourceKeys = [];
        foreach ($legacyItems as $legacyId => $items) {
            foreach ($items as $item) {
                $key = (int) $legacyId.'|'.(string) $item->source_item_id;
                $legacySourceKeys[$key] = true;
                if (filled($item->item_description ?? null)) {
                    $expected[$key] = (string) $item->item_description;
                }
            }
        }
        $actual = [];
        $wrongSourceLink = 0;
        if ($db->getSchemaBuilder()->hasTable('spj_item_overlays')) {
            foreach ($db->table('legacy_transaction_v2_map as maps')
                ->join('spj_transaction_sources as links', 'links.spj_transaction_id', '=', 'maps.spj_transaction_id')
                ->join('arkas_source_identity_registry as identities', 'identities.id', '=', 'links.arkas_source_identity_id')
                ->join('spj_item_overlays as overlays', 'overlays.spj_transaction_source_id', '=', 'links.id')
                ->get(['maps.legacy_transaction_id', 'identities.source_key', 'overlays.item_description']) as $row) {
                $key = (int) $row->legacy_transaction_id.'|'.(string) $row->source_key;
                if (! isset($legacySourceKeys[$key])) {
                    $wrongSourceLink++;
                }
                $actual[$key] = (string) $row->item_description;
            }
        }
        $matched = array_intersect_key($expected, $actual);
        $descriptionMismatch = 0;
        foreach ($matched as $key => $description) {
            if ($description !== $actual[$key]) {
                $descriptionMismatch++;
            }
        }
        $missing = array_diff_key($expected, $actual);
        $unexpected = array_diff_key($actual, $expected);

        return [
            'legacy_operator_owned_candidates' => count($expected),
            'v2_item_overlays' => count($actual),
            'expected_overlay' => count($expected),
            'matched_overlay' => count($matched),
            'missing_overlay' => count($missing),
            'unexpected_overlay' => count($unexpected),
            'lost_overlay' => count($missing),
            'description_mismatch' => $descriptionMismatch,
            'wrong_source_link' => $wrongSourceLink,
            'status' => $missing === [] && $unexpected === [] && $descriptionMismatch === 0 && $wrongSourceLink === 0 ? 'PASS' : 'FAIL',
        ];
    }

    private function countOrphans(Connection $db, string $child, string $foreignKey, string $parent): int
    {
        return (int) $db->table($child)->whereNotNull($foreignKey)->whereNotExists(function ($query) use ($child, $foreignKey, $parent): void {
            $query->selectRaw('1')->from($parent)->whereColumn($parent.'.id', $child.'.'.$foreignKey);
        })->count();
    }

    /** @return array<string, int|string> */
    private function contextIsolation(Connection $db): array
    {
        $duplicateBoundaries = $db->table('spj_transactions')
            ->select('fiscal_year_id', 'fund_source_id', 'source_id', 'source_membership_hash')
            ->groupBy('fiscal_year_id', 'fund_source_id', 'source_id', 'source_membership_hash')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();
        $identityContexts = [];
        foreach ($db->table('spj_transaction_sources as links')
            ->join('spj_transactions as transactions', 'transactions.id', '=', 'links.spj_transaction_id')
            ->get(['links.arkas_source_identity_id', 'transactions.fiscal_year_id', 'transactions.fund_source_id']) as $row) {
            $identityContexts[(int) $row->arkas_source_identity_id][(string) $row->fiscal_year_id.'|'.(string) $row->fund_source_id] = true;
        }
        $crossContext = count(array_filter($identityContexts, fn (array $contexts): bool => count($contexts) > 1));

        return [
            'duplicate_transaction_boundaries' => $duplicateBoundaries,
            'transaction_boundary_unique' => $duplicateBoundaries === 0,
            'source_identity_cross_context_count' => $crossContext,
            'status' => $duplicateBoundaries === 0 && $crossContext === 0 ? 'PASS' : 'FAIL',
        ];
    }

    /** @return array<string, int|string> */
    private function canonicalVerification(Connection $db): array
    {
        $invalid = $db->table('spj_transactions')
            ->whereNotIn('canonical_context_status', ['ACTIVE_CANONICAL', 'LEGACY_DUPLICATE'])
            ->count();
        $maps = $db->table('legacy_transaction_v2_map')->count();
        $transactions = $db->table('transactions')->count();

        return [
            'status' => $invalid === 0 && $maps === $transactions ? 'PASS' : 'FAIL',
            'invalid_context_rows' => $invalid,
            'legacy_map_count' => $maps,
            'legacy_transaction_count' => $transactions,
        ];
    }

    /** @return array<string, int> */
    private function packageDocumentMatrix(Connection $db): array
    {
        if (! $db->getSchemaBuilder()->hasTable('legacy_transaction_v2_map')
            || ! $db->getSchemaBuilder()->hasColumn('legacy_transaction_v2_map', 'canonical_context_status')) {
            return [];
        }
        $matrix = [];
        foreach ($db->table('spj_packages as packages')
            ->join('legacy_transaction_v2_map as maps', 'maps.legacy_transaction_id', '=', 'packages.transaction_id')
            ->select('maps.canonical_context_status', 'packages.status')
            ->get() as $row) {
            $key = $row->canonical_context_status.'_'.strtoupper((string) $row->status);
            $matrix[$key] = ($matrix[$key] ?? 0) + 1;
        }

        return $matrix;
    }

    /** @return array<string, mixed> */
    private function sourceAdapterValidation(Connection $db, int $sourceId, ?string $canonicalStatus = null): array
    {
        $rawTable = $db->table('arkas_raw_mirror_tables')
            ->where('source_id', $sourceId)
            ->where('source_table', 'kas_umum')
            ->where('status', 'ACTIVE')
            ->first();
        if ($rawTable === null) {
            return ['status' => 'BLOCKED_SOURCE_UNAVAILABLE', 'source_links' => 0, 'unresolved_links' => 0];
        }

        $raw = [];
        foreach ($db->table('arkas_raw_mirror_rows')->where('mirror_table_id', $rawTable->id)->get(['id', 'source_key', 'payload']) as $row) {
            $payload = json_decode((string) $row->payload, true);
            if (is_array($payload)) {
                $raw[(string) $row->source_key] = $payload;
            }
        }
        $taxesByParent = [];
        foreach ($raw as $payload) {
            $parent = trim((string) ($payload['parent_id_kas_umum'] ?? ''));
            $ref = (int) ($payload['id_ref_bku'] ?? 0);
            if ($parent !== '' && in_array($ref, [10, 30], true) && (int) ($payload['soft_delete'] ?? 0) !== 1) {
                $taxesByParent[$parent][] = $payload;
            }
        }

        $sourceLinks = 0;
        $unresolved = 0;
        $gross = 0.0;
        $tax = 0.0;
        $net = 0.0;
        $linksQuery = $db->table('spj_transaction_sources as links')
            ->join('arkas_source_identity_registry as identities', 'identities.id', '=', 'links.arkas_source_identity_id')
            ->join('spj_transactions as transactions', 'transactions.id', '=', 'links.spj_transaction_id')
            ->where('identities.source_id', $sourceId);
        if ($canonicalStatus !== null) {
            $linksQuery->where('transactions.canonical_context_status', $canonicalStatus);
        }
        foreach ($linksQuery->get(['identities.source_key', 'links.spj_transaction_id']) as $link) {
            $sourceLinks++;
            $payload = $raw[(string) $link->source_key] ?? null;
            if (! is_array($payload)) {
                $unresolved++;

                continue;
            }

            $amount = (float) ($payload['saldo'] ?? $payload['jumlah'] ?? $payload['nilai'] ?? $payload['nominal'] ?? 0);
            if (in_array((int) ($payload['id_ref_bku'] ?? 0), [4, 15, 24, 35], true) && (int) ($payload['soft_delete'] ?? 0) !== 1) {
                $gross += $amount;
            }
            $parent = (string) ($payload['id_kas_umum'] ?? $link->source_key);
            foreach ($taxesByParent[$parent] ?? [] as $taxPayload) {
                $tax += (float) ($taxPayload['saldo'] ?? $taxPayload['jumlah'] ?? $taxPayload['nilai'] ?? $taxPayload['nominal'] ?? 0);
            }
        }
        $net = $gross - $tax;

        return [
            'status' => $unresolved === 0 ? 'PASS' : 'BLOCKED_UNRESOLVED_SOURCE',
            'source_links' => $sourceLinks,
            'unresolved_links' => $unresolved,
            'gross_from_raw' => $gross,
            'tax_from_raw' => $tax,
            'net_from_raw' => $net,
            'net_formula_valid' => abs($net - ($gross - $tax)) < 0.01,
        ];
    }

    /** @return array<string, array<int, mixed>> */
    private function protectedManifest($packages, $documents): array
    {
        $packageFields = ['id', 'transaction_id', 'document_number', 'quarter_code', 'semester_code', 'phase_code', 'status', 'numbered_at', 'generated_at', 'snapshot', 'finalized_at', 'finalized_by', 'cancelled_at', 'cancelled_by', 'cancellation_reason'];
        $documentFields = ['id', 'spj_package_id', 'document_number', 'status', 'snapshot', 'template_snapshot', 'template_hash', 'rendered_hash', 'numbered_at', 'finalized_at', 'finalized_by', 'cancelled_at', 'cancelled_by', 'cancellation_reason'];

        return [
            'packages' => $packages->map(fn ($row): array => array_intersect_key((array) $row, array_flip($packageFields)))->values()->all(),
            'documents' => $documents->map(fn ($row): array => array_intersect_key((array) $row, array_flip($documentFields)))->values()->all(),
        ];
    }

    private function hashArray(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
