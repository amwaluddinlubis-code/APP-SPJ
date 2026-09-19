<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use RuntimeException;

final class SpjV2PackageDocumentParityService
{
    /** @return array<string, mixed> */
    public function compare(Connection $db): array
    {
        $this->assertSchema($db);

        $packages = $db->table('spj_packages')->orderBy('id')->get();
        $documents = $db->table('spj_documents')->orderBy('id')->get();
        $maps = $db->table('legacy_transaction_v2_map')->get()->keyBy('legacy_transaction_id');
        $v2Transactions = $db->table('spj_transactions')->get()->keyBy('id');

        $missingProvenance = [];
        $missingV2Links = [];
        $mismatchedV2Links = [];
        $missingV2Transactions = [];
        $nonCanonicalPackages = [];
        $impactedDocumentIds = [];
        $documentsByPackage = $documents->groupBy('spj_package_id');

        foreach ($packages as $package) {
            $packageId = (int) $package->id;
            $map = $maps->get($package->transaction_id);
            $actualV2Id = $package->spj_transaction_id === null ? null : (int) $package->spj_transaction_id;
            $expectedV2Id = $map?->spj_transaction_id === null ? null : (int) $map->spj_transaction_id;

            $broken = false;
            if ($map === null) {
                $missingProvenance[] = $packageId;
                $broken = true;
            }
            if ($actualV2Id === null) {
                $missingV2Links[] = $packageId;
                $broken = true;
            }
            if ($expectedV2Id !== null && $actualV2Id !== $expectedV2Id) {
                $mismatchedV2Links[] = [
                    'package_id' => $packageId,
                    'legacy_transaction_id' => (int) $package->transaction_id,
                    'expected_spj_transaction_id' => $expectedV2Id,
                    'actual_spj_transaction_id' => $actualV2Id,
                ];
                $broken = true;
            }
            $targetV2 = $actualV2Id === null ? null : $v2Transactions->get($actualV2Id);
            if ($actualV2Id !== null && $targetV2 === null) {
                $missingV2Transactions[] = $packageId;
                $broken = true;
            }
            if ($targetV2 !== null && (string) ($targetV2->canonical_context_status ?? '') !== 'ACTIVE_CANONICAL') {
                $nonCanonicalPackages[] = [
                    'package_id' => $packageId,
                    'legacy_transaction_id' => (int) $package->transaction_id,
                    'spj_transaction_id' => $actualV2Id,
                    'canonical_context_status' => (string) ($targetV2->canonical_context_status ?? ''),
                    'legacy_provenance_status' => (string) ($map?->canonical_context_status ?? ''),
                ];
                $broken = true;
            }

            if ($broken) {
                foreach ($documentsByPackage->get($packageId, collect()) as $document) {
                    $impactedDocumentIds[] = (int) $document->id;
                }
            }
        }

        $packageIds = $packages->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $orphanDocuments = $documents
            ->reject(fn (object $document): bool => in_array((int) $document->spj_package_id, $packageIds, true))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $legacyManifest = $this->manifest($packages, $documents);
        $v2EligiblePackageIds = $packages
            ->filter(function (object $package) use ($maps, $v2Transactions): bool {
                $map = $maps->get($package->transaction_id);
                if ($map === null || $package->spj_transaction_id === null) {
                    return false;
                }

                $actual = (int) $package->spj_transaction_id;
                $expected = (int) $map->spj_transaction_id;
                $targetV2 = $v2Transactions->get($actual);

                return $actual === $expected
                    && $targetV2 !== null
                    && (string) ($targetV2->canonical_context_status ?? '') === 'ACTIVE_CANONICAL';
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $v2Packages = $packages->filter(fn (object $package): bool => in_array((int) $package->id, $v2EligiblePackageIds, true))->values();
        $v2Documents = $documents->filter(fn (object $document): bool => in_array((int) $document->spj_package_id, $v2EligiblePackageIds, true))->values();
        $v2Manifest = $this->manifest($v2Packages, $v2Documents);
        $legacyHash = $this->hash($legacyManifest);
        $v2Hash = $this->hash($v2Manifest);

        $relationMismatchCount = count($missingProvenance)
            + count($missingV2Links)
            + count($mismatchedV2Links)
            + count($missingV2Transactions)
            + count($nonCanonicalPackages);

        $status = $relationMismatchCount === 0
            && $orphanDocuments === []
            && hash_equals($legacyHash, $v2Hash)
            ? 'PASS'
            : 'FAIL';

        return [
            'status' => $status,
            'counts' => [
                'packages' => $packages->count(),
                'v2_linked_packages' => $packages->whereNotNull('spj_transaction_id')->count(),
                'v2_parity_packages' => count($v2EligiblePackageIds),
                'documents' => $documents->count(),
                'v2_parity_documents' => $v2Documents->count(),
            ],
            'package_statuses' => $this->statusCounts($packages),
            'document_statuses' => $this->statusCounts($documents),
            'protected_lifecycle' => [
                'numbered_packages' => $packages->where('status', 'NUMBERED')->count(),
                'final_packages' => $packages->where('status', 'FINAL')->count(),
                'numbered_documents' => $documents->where('status', 'NUMBERED')->count(),
                'final_documents' => $documents->where('status', 'FINAL')->count(),
            ],
            'relations' => [
                'mismatch_count' => $relationMismatchCount,
                'missing_provenance_package_ids' => $missingProvenance,
                'missing_v2_link_package_ids' => $missingV2Links,
                'mismatched_v2_links' => $mismatchedV2Links,
                'missing_v2_transaction_package_ids' => $missingV2Transactions,
                'non_canonical_packages' => $nonCanonicalPackages,
                'impacted_document_ids' => array_values(array_unique($impactedDocumentIds)),
            ],
            'documents' => [
                'orphan_count' => count($orphanDocuments),
                'orphan_document_ids' => $orphanDocuments,
            ],
            'protected_manifest' => [
                'legacy_hash' => $legacyHash,
                'v2_hash' => $v2Hash,
                'match' => hash_equals($legacyHash, $v2Hash),
            ],
        ];
    }

    /** @return array<string, int> */
    private function statusCounts(Collection $rows): array
    {
        return $rows
            ->groupBy(fn (object $row): string => strtoupper((string) $row->status))
            ->map(fn ($rows): int => $rows->count())
            ->sortKeys()
            ->all();
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function manifest(Collection $packages, Collection $documents): array
    {
        $packageFields = [
            'id', 'transaction_id', 'document_number', 'quarter_code', 'semester_code', 'phase_code',
            'status', 'numbered_at', 'generated_at', 'snapshot', 'finalized_at', 'finalized_by',
            'cancelled_at', 'cancelled_by', 'cancellation_reason',
        ];
        $documentFields = [
            'id', 'spj_package_id', 'document_template_id', 'replaces_document_id', 'document_type', 'scope_key',
            'document_number', 'sequence_number', 'document_date', 'event_date', 'status', 'snapshot',
            'template_snapshot', 'template_hash', 'rendered_hash', 'numbered_at', 'finalized_at', 'finalized_by',
            'cancelled_at', 'cancelled_by', 'cancellation_reason',
        ];

        return [
            'packages' => $packages
                ->sortBy('id')
                ->map(fn (object $row): array => array_intersect_key((array) $row, array_flip($packageFields)))
                ->values()
                ->all(),
            'documents' => $documents
                ->sortBy('id')
                ->map(fn (object $row): array => array_intersect_key((array) $row, array_flip($documentFields)))
                ->values()
                ->all(),
        ];
    }

    private function hash(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function assertSchema(Connection $db): void
    {
        foreach (['spj_packages', 'spj_documents', 'legacy_transaction_v2_map', 'spj_transactions'] as $table) {
            if (! $db->getSchemaBuilder()->hasTable($table)) {
                throw new RuntimeException('V2-D package/document parity requires table: '.$table);
            }
        }

        if (! $db->getSchemaBuilder()->hasColumn('spj_packages', 'spj_transaction_id')) {
            throw new RuntimeException('V2-D package/document parity requires spj_packages.spj_transaction_id.');
        }
    }
}
