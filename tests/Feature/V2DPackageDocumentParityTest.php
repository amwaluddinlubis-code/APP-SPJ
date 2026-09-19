<?php

namespace Tests\Feature;

use App\Services\SpjV2LegacyMigrationService;
use App\Services\SpjV2PackageDocumentParityService;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class V2DPackageDocumentParityTest extends TestCase
{
    public function test_package_and_document_bridge_matches_legacy_relations_and_protected_lifecycle(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-package-document-parity.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $before = $this->protectedHash($db);
            $result = app(SpjV2PackageDocumentParityService::class)->compare($db);
            $after = $this->protectedHash($db);

            $this->assertSame('PASS', $result['status']);
            $this->assertSame(67, $result['counts']['packages']);
            $this->assertSame(67, $result['counts']['v2_linked_packages']);
            $this->assertSame(67, $result['counts']['v2_parity_packages']);
            $this->assertSame(115, $result['counts']['documents']);
            $this->assertSame(115, $result['counts']['v2_parity_documents']);
            $this->assertSame(66, $result['protected_lifecycle']['numbered_packages']);
            $this->assertSame(0, $result['protected_lifecycle']['final_packages']);
            $this->assertSame(115, $result['protected_lifecycle']['numbered_documents']);
            $this->assertSame(0, $result['relations']['mismatch_count']);
            $this->assertSame(0, $result['documents']['orphan_count']);
            $this->assertTrue($result['protected_manifest']['match']);

            $duplicateProvenancePackage = $db->table('spj_packages as package')
                ->join('legacy_transaction_v2_map as map', 'map.legacy_transaction_id', '=', 'package.transaction_id')
                ->join('spj_transactions as v2', 'v2.id', '=', 'package.spj_transaction_id')
                ->where('map.canonical_context_status', 'LEGACY_DUPLICATE')
                ->where('v2.canonical_context_status', 'ACTIVE_CANONICAL')
                ->select(['package.id', 'package.spj_transaction_id'])
                ->first();
            $this->assertNotNull(
                $duplicateProvenancePackage,
                'A legacy duplicate provenance may validly bridge to the active canonical V2 transaction.',
            );
            $this->assertSame($before, $after, 'Parity comparison must not mutate Paket/document lifecycle rows.');
        } finally {
            File::delete($target);
        }
    }

    public function test_wrong_but_valid_v2_package_link_fails_closed(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-package-document-drift.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $package = $db->table('spj_packages')
                ->where('status', 'NUMBERED')
                ->whereNotNull('spj_transaction_id')
                ->orderBy('id')
                ->first();
            $this->assertNotNull($package);

            $replacementId = $db->table('spj_transactions')
                ->where('id', '!=', $package->spj_transaction_id)
                ->where('canonical_context_status', 'ACTIVE_CANONICAL')
                ->orderBy('id')
                ->value('id');
            $this->assertNotNull($replacementId);

            $db->table('spj_packages')->where('id', $package->id)->update([
                'spj_transaction_id' => $replacementId,
            ]);

            $result = app(SpjV2PackageDocumentParityService::class)->compare($db);

            $this->assertSame('FAIL', $result['status']);
            $this->assertSame(1, $result['relations']['mismatch_count']);
            $this->assertCount(1, $result['relations']['mismatched_v2_links']);
            $this->assertSame((int) $package->id, $result['relations']['mismatched_v2_links'][0]['package_id']);
            $this->assertNotEmpty($result['relations']['impacted_document_ids']);
            $this->assertFalse($result['protected_manifest']['match']);
        } finally {
            File::delete($target);
        }
    }

    public function test_synthetic_final_package_and_documents_remain_read_only_and_v2_linked(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-package-document-final.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $package = $db->table('spj_packages')
                ->where('status', 'NUMBERED')
                ->whereNotNull('spj_transaction_id')
                ->orderBy('id')
                ->first();
            $this->assertNotNull($package);

            $finalizedAt = '2026-09-19 08:00:00';
            $db->table('spj_packages')->where('id', $package->id)->update([
                'status' => 'FINAL',
                'finalized_at' => $finalizedAt,
                'finalized_by' => 1,
            ]);
            $db->table('spj_documents')->where('spj_package_id', $package->id)->update([
                'status' => 'FINAL',
                'finalized_at' => $finalizedAt,
                'finalized_by' => 1,
            ]);

            $finalDocumentCount = (int) $db->table('spj_documents')->where('spj_package_id', $package->id)->count();
            $this->assertGreaterThan(0, $finalDocumentCount);

            $before = $this->protectedHash($db);
            $result = app(SpjV2PackageDocumentParityService::class)->compare($db);
            $after = $this->protectedHash($db);

            $this->assertSame('PASS', $result['status']);
            $this->assertSame(1, $result['protected_lifecycle']['final_packages']);
            $this->assertSame($finalDocumentCount, $result['protected_lifecycle']['final_documents']);
            $this->assertSame(0, $result['relations']['mismatch_count']);
            $this->assertTrue($result['protected_manifest']['match']);
            $this->assertSame($before, $after, 'Parity comparison must not mutate synthetic FINAL lifecycle state.');
        } finally {
            File::delete($target);
        }
    }

    private function prepareClone(string $target): string
    {
        $sourceClone = storage_path('app/school-databases/10260786/spj.sqlite');
        $source = $this->sourcePath();
        $this->assertFileExists($sourceClone);
        $this->assertNotSame('', $source, 'No readable ARKAS evidence source was found for the V2-D rehearsal.');
        File::copy($sourceClone, $target);

        return $source;
    }

    private function migrateAndProject(): void
    {
        Artisan::call('migrate', [
            '--database' => 'school',
            '--path' => 'database/migrations/v2-rehearsal',
            '--force' => true,
            '--no-interaction' => true,
        ]);

        $db = DB::connection('school');
        $migration = app(SpjV2LegacyMigrationService::class)->migrate(
            $db,
            1,
            true,
            storage_path('app/v2-c-rehearsal/reports/test-v2d-package-document-parity.json'),
            10260756,
        );

        $this->assertSame([], $migration['errors']);
        $this->assertSame('PASS', app(SpjV2LegacyMigrationService::class)->verify($db)['status']);
    }

    private function connect(string $target, string $source): void
    {
        config()->set('database.connections.school.database', $target);
        config()->set('spj.v2_b_isolated_manifest', [
            'target_path' => $target,
            'npsn' => 10260756,
            'source_id' => 1,
            'source_identity_npsn' => 10260756,
            'source_path' => $source,
            'source_read_only' => true,
            'query_only' => true,
            'source_unavailable' => false,
            'mode' => null,
        ]);
        DB::purge('school');
    }

    private function sourcePath(): string
    {
        $configured = getenv('SPJ_V2_C_SOURCE_PATH') ?: config('spj.v2_c_source_path');
        $candidates = array_filter([
            is_string($configured) ? $configured : null,
            base_path('../../backupdata/datasmp.db'),
            storage_path('app/datasmp.db'),
        ]);

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    private function protectedHash(Connection $db): string
    {
        return hash('sha256', json_encode([
            'packages' => $db->table('spj_packages')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
            'documents' => $db->table('spj_documents')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
