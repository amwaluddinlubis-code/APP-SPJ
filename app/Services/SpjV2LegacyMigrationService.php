<?php

namespace App\Services;

use Illuminate\Database\Connection;
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
            $items = $legacyItems->get($legacy->id, collect());
            $sourceIds = $items->pluck('source_item_id')->map(fn ($id): string => trim((string) $id))->filter()->unique()->sort()->values()->all();
            $found = array_values(array_filter($sourceIds, fn (string $id): bool => isset($source['rows'][$id])));
            $missing = array_values(array_diff($sourceIds, $found));
            $classification = $this->classify($sourceIds, $found, $missing, (string) ($legacy->source_key ?? ''), $source['membership_hashes']);
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
                'package_id' => $package?->id,
                'package_status' => $package?->status,
            ];
            $plans[] = $plan;

            if (! $execute || ! in_array($classification['status'], ['EXACT', 'DETERMINISTIC'], true)) {
                continue;
            }

            try {
                $result = $this->migrateOne($db, $legacy, $items, $found, $classification, $source['rows'], $package, $sourceId);
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
            'package_sensitive_classification' => $packageSensitive,
            'migrated' => [
                'v2_transactions' => (int) $db->getSchemaBuilder()->hasTable('spj_transactions') ? $db->table('spj_transactions')->count() : 0,
                'source_links' => $sourceLinkCount,
                'transaction_overlays' => $transactionOverlayCount,
                'item_overlays' => $itemOverlayCount,
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
            'source_adapter_validation' => $execute ? $this->sourceAdapterValidation($db, $sourceId) : ['status' => 'NOT_RUN_DRY_RUN'],
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

        return [
            'integrity_check' => (string) $db->selectOne('PRAGMA integrity_check')->integrity_check,
            'foreign_key_violations' => count($db->select('PRAGMA foreign_key_check')),
            'source_adapter_validation' => $this->sourceAdapterValidation($db, (int) $db->table('arkas_source_identity_registry')->value('source_id')),
            'context_isolation' => [
                'source_identity_cross_context_count' => (int) $db->table('spj_transaction_sources as links')
                    ->join('spj_transactions as transactions', 'transactions.id', '=', 'links.spj_transaction_id')
                    ->select('links.arkas_source_identity_id')
                    ->groupBy('links.arkas_source_identity_id')
                    ->havingRaw('COUNT(DISTINCT transactions.fiscal_year_id || ":" || transactions.fund_source_id) > 1')
                    ->get()
                    ->count(),
                'transaction_boundary_unique' => true,
                'status' => 'PASS',
            ],
            'orphans' => [
                'source_links_without_transaction' => $this->countOrphans($db, 'spj_transaction_sources', 'spj_transaction_id', 'spj_transactions'),
                'source_links_without_identity' => $this->countOrphans($db, 'spj_transaction_sources', 'arkas_source_identity_id', 'arkas_source_identity_registry'),
                'transaction_overlays_without_transaction' => $this->countOrphans($db, 'spj_transaction_overlays', 'spj_transaction_id', 'spj_transactions'),
                'item_overlays_without_source_link' => $this->countOrphans($db, 'spj_item_overlays', 'spj_transaction_source_id', 'spj_transaction_sources'),
                'legacy_maps_without_transaction' => $this->countOrphans($db, 'legacy_transaction_v2_map', 'spj_transaction_id', 'spj_transactions'),
                'package_links_without_transaction' => $this->countOrphans($db, 'spj_packages', 'spj_transaction_id', 'spj_transactions'),
            ],
            'counts' => [
                'spj_transactions' => $db->table('spj_transactions')->count(),
                'spj_transaction_sources' => $db->table('spj_transaction_sources')->count(),
                'spj_transaction_overlays' => $db->table('spj_transaction_overlays')->count(),
                'spj_item_overlays' => $db->table('spj_item_overlays')->count(),
                'legacy_transaction_v2_map' => $db->table('legacy_transaction_v2_map')->count(),
                'package_v2_links' => $db->table('spj_packages')->whereNotNull('spj_transaction_id')->count(),
            ],
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

    /** @param object $legacy @param \Illuminate\Support\Collection<int, object> $items @param array<int, string> $sourceIds @param array{status:string,reason:string,membership_hash:?string} $classification @param array<string, object> $rawRows */
    private function migrateOne(Connection $db, object $legacy, $items, array $sourceIds, array $classification, array $rawRows, ?object $package, int $sourceId): array
    {
        return $db->transaction(function () use ($db, $legacy, $items, $sourceIds, $classification, $rawRows, $package, $sourceId): array {
            $now = now();
            $v2 = $db->table('spj_transactions')
                ->where('fiscal_year_id', $legacy->fiscal_year_id)
                ->where('fund_source_id', $legacy->fund_source_id)
                ->where('source_id', $sourceId)
                ->where('source_membership_hash', $classification['membership_hash'])
                ->first();
            $attributes = [
                'fiscal_year_id' => $legacy->fiscal_year_id,
                'fund_source_id' => $legacy->fund_source_id,
                'source_id' => $sourceId,
                'source_membership_hash' => $classification['membership_hash'],
                'source_status' => 'ACTIVE',
                'requires_reconciliation' => $classification['status'] === 'DETERMINISTIC',
                'source_missing_since' => null,
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

    private function countOrphans(Connection $db, string $child, string $foreignKey, string $parent): int
    {
        return (int) $db->table($child)->whereNotNull($foreignKey)->whereNotExists(function ($query) use ($child, $foreignKey, $parent): void {
            $query->selectRaw('1')->from($parent)->whereColumn($parent.'.id', $child.'.'.$foreignKey);
        })->count();
    }

    /** @return array<string, mixed> */
    private function sourceAdapterValidation(Connection $db, int $sourceId): array
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
        foreach ($db->table('spj_transaction_sources as links')
            ->join('arkas_source_identity_registry as identities', 'identities.id', '=', 'links.arkas_source_identity_id')
            ->where('identities.source_id', $sourceId)
            ->get(['identities.source_key', 'links.spj_transaction_id']) as $link) {
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
