<?php

namespace Tests\Feature;

use App\Services\SpjV2LegacyMigrationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class V2CLegacyMigrationTest extends TestCase
{
    public function test_full_tenant_a_rehearsal_and_verify_contract(): void
    {
        $sourceClone = storage_path('app/school-databases/10260786/spj.sqlite');
        $source = $this->sourcePath();
        $this->assertFileExists($sourceClone);
        $this->assertNotSame('', $source, 'No readable ARKAS evidence source was found for the V2-C rehearsal.');
        $target = storage_path('app/v2-c-rehearsal/test-fresh-c2.sqlite');
        File::copy($sourceClone, $target);

        try {
            $this->connect($target, $source, 10260756);
            $this->migrateRehearsalSchema();
            $service = app(SpjV2LegacyMigrationService::class);
            $firstMigration = $service->migrate(DB::connection('school'), 1, true, storage_path('app/v2-c-rehearsal/reports/test-fresh-c2-1.json'), 10260756);
            $this->assertSame([], $firstMigration['errors']);
            $this->assertTrue($firstMigration['package_document_continuity']['unchanged']);
            $result = $service->verify(DB::connection('school'));
            $this->assertSame('ok', $result['integrity_check']);
            $this->assertSame(0, $result['foreign_key_violations']);
            $this->assertSame(0, max($result['orphans']));
            $this->assertSame(187, $result['counts']['spj_transactions']);
            $this->assertSame(431, $result['counts']['spj_transaction_sources']);
            $this->assertSame(291, $result['counts']['legacy_transaction_v2_map']);
            $this->assertSame(67, $result['counts']['package_v2_links']);
            $this->assertSame('PASS', $result['source_adapter_validation']['status']);
            $this->assertSame(0, $result['source_adapter_validation']['unresolved_links']);
            $this->assertTrue($result['context_isolation']['transaction_boundary_unique']);
            $this->assertSame(0, $result['context_isolation']['source_identity_cross_context_count']);
            $this->assertSame('PASS', $result['context_isolation']['status']);
            $this->assertSame(187, $result['canonical_reconciliation']['active_canonical_count']);
            $this->assertSame(104, $result['legacy_provenance']['many_to_one_v2_count']);
            $this->assertSame(['ACTIVE_CANONICAL' => 187, 'LEGACY_DUPLICATE' => 104], $result['legacy_provenance']['classification']);
            $this->assertSame(0, $result['transaction_overlay_reconciliation']['transaction_overlay_conflict_count']);
            $this->assertSame(0, $result['item_overlay_reconciliation']['item_overlay_conflict_count']);
            $this->assertSame(245, $result['item_overlay_reconciliation']['legacy_operator_owned_candidates']);
            $this->assertSame(245, $result['item_overlay_reconciliation']['v2_item_overlays']);
            $this->assertSame(0, $result['item_overlay_reconciliation']['lost_overlay']);
            $this->assertSame(0, $result['item_overlay_reconciliation']['unexpected_overlay']);
            $this->assertEquals(429605000, $result['financial_reconciliation']['ACTIVE_CANONICAL']['gross_from_raw']);
            $this->assertEquals(20497310, $result['financial_reconciliation']['ACTIVE_CANONICAL']['tax_from_raw']);
            $this->assertEquals(409107690, $result['financial_reconciliation']['ACTIVE_CANONICAL']['net_from_raw']);

            $secondMigration = $service->migrate(DB::connection('school'), 1, true, storage_path('app/v2-c-rehearsal/reports/test-fresh-c2-2.json'), 10260756);
            $this->assertSame([], $secondMigration['errors']);
            $this->assertTrue($secondMigration['package_document_continuity']['unchanged']);
            $this->assertSame(
                $firstMigration['package_document_continuity']['after_hash'],
                $secondMigration['package_document_continuity']['before_hash'],
            );
            $this->assertSame(187, $secondMigration['migrated']['v2_transactions']);
            $this->assertSame(245, $secondMigration['migrated']['item_overlays']);
            $this->assertSame(67, $secondMigration['migrated']['package_v2_links']);
            $this->assertSame(0, $secondMigration['migrated']['source_links']);
            $this->assertSame(0, $secondMigration['migrated']['transaction_overlays']);
            $this->assertSame(0, $secondMigration['migrated']['legacy_maps']);
            $this->assertSame($firstMigration['canonical_context_classification'], $secondMigration['canonical_context_classification']);
            $this->assertSame($firstMigration['transaction_overlay_reconciliation'], $secondMigration['transaction_overlay_reconciliation']);
            $second = $service->verify(DB::connection('school'));
            $this->assertSame($result['counts'], $second['counts']);
            $this->assertSame('PASS', $second['status']);
        } finally {
            File::delete($target);
        }
    }

    public function test_tenant_b_dry_run_is_source_safe_and_does_not_guess(): void
    {
        $target = $this->tenantBPath();
        if ($target === '') {
            $this->markTestSkipped('Optional external/orphan Tenant B fixture is not available on this workstation.');
        }
        $before = hash_file('sha256', $target);
        $this->connect($target, null, 10208183, true);

        $report = storage_path('app/v2-c-rehearsal/reports/test-10208183-dry-run.json');
        $result = app(SpjV2LegacyMigrationService::class)->migrate(DB::connection('school'), 1, false, $report, 10208183);
        $this->assertSame(46, $result['classification']['SOURCE_MISSING']);
        $this->assertSame(0, $result['migrated']['legacy_maps']);
        $this->assertSame($before, hash_file('sha256', $target));
    }

    public function test_final_package_and_document_are_immutable_in_synthetic_rehearsal(): void
    {
        $target = storage_path('app/school-databases/10260786/spj.sqlite');
        $source = $this->sourcePath();
        $this->assertFileExists($target);
        $this->assertNotSame('', $source, 'No readable ARKAS evidence source was found for the V2-C rehearsal.');
        $synthetic = storage_path('app/v2-c-rehearsal/final-synthetic-c2.sqlite');
        File::copy($target, $synthetic);

        try {
            $this->connect($synthetic, $source, 10260756);
            $this->migrateRehearsalSchema();
            $db = DB::connection('school');
            $package = $db->table('spj_packages')->where('status', 'NUMBERED')->first();
            $this->assertNotNull($package);
            $document = $db->table('spj_documents')->where('spj_package_id', $package->id)->first();
            $this->assertNotNull($document);
            $packageBefore = $this->protectedPackage($package);
            $documentBefore = $this->protectedDocument($document);
            $db->table('spj_packages')->where('id', $package->id)->update(['status' => 'FINAL']);
            $db->table('spj_documents')->where('id', $document->id)->update(['status' => 'FINAL']);
            $packageBefore = $this->protectedPackage($db->table('spj_packages')->find($package->id));
            $documentBefore = $this->protectedDocument($db->table('spj_documents')->find($document->id));

            app(SpjV2LegacyMigrationService::class)->migrate(
                $db,
                1,
                true,
                storage_path('app/v2-c-rehearsal/reports/test-final-synthetic.json'),
                10260756,
            );

            $this->assertSame($packageBefore, $this->protectedPackage($db->table('spj_packages')->find($package->id)));
            $this->assertSame($documentBefore, $this->protectedDocument($db->table('spj_documents')->find($document->id)));
        } finally {
            File::delete($synthetic);
        }
    }

    public function test_many_to_one_overlay_merge_is_non_destructive_and_conflicts_are_explicit(): void
    {
        $service = app(SpjV2LegacyMigrationService::class);
        $method = new \ReflectionMethod($service, 'mergeOverlayValues');
        $method->setAccessible(true);

        $blank = (object) ['payment_description' => null, 'operator_metadata' => null];
        $kept = $method->invoke($service, $blank, ['payment_description' => 'operator value'], now());
        $this->assertSame('operator value', $kept['payment_description']);

        $existing = (object) ['payment_description' => 'first value', 'operator_metadata' => null];
        $conflict = $method->invoke($service, $existing, ['payment_description' => 'second value'], now());
        $this->assertArrayNotHasKey('payment_description', $conflict);
        $metadata = json_decode($conflict['operator_metadata'], true);
        $this->assertSame(['first value', 'second value'], $metadata['_v2_reconciliation_conflicts']['payment_description']);
    }

    public function test_transaction_overlay_conflict_fails_semantic_verify(): void
    {
        $source = storage_path('app/school-databases/10260786/spj.sqlite');
        $target = storage_path('app/v2-c-rehearsal/overlay-conflict-c2.sqlite');
        $this->assertFileExists($source);
        File::copy($source, $target);

        try {
            $sourcePath = $this->sourcePath();
            $this->assertNotSame('', $sourcePath, 'No readable ARKAS evidence source was found for the V2-C rehearsal.');
            $this->connect($target, $sourcePath, 10260756);
            $this->migrateRehearsalSchema();
            $db = DB::connection('school');
            app(SpjV2LegacyMigrationService::class)->migrate(
                $db,
                1,
                true,
                storage_path('app/v2-c-rehearsal/reports/test-overlay-conflict-c2.json'),
                10260756,
            );
            $overlay = $db->table('spj_transaction_overlays')->first();
            $metadata = ['_v2_reconciliation_conflicts' => ['payment_description' => ['one', 'two']]];
            $db->table('spj_transaction_overlays')->where('id', $overlay->id)->update(['operator_metadata' => json_encode($metadata)]);
            $itemOverlay = $db->table('spj_item_overlays')->first();
            $itemMetadata = ['_v2_reconciliation_conflicts' => ['item_description' => ['one', 'two']]];
            $db->table('spj_item_overlays')->where('id', $itemOverlay->id)->update(['operator_metadata' => json_encode($itemMetadata)]);
            $result = app(SpjV2LegacyMigrationService::class)->verify($db);
            $this->assertSame('FAIL', $result['status']);
            $this->assertSame(1, $result['transaction_overlay_reconciliation']['transaction_overlay_conflict_count']);
            $this->assertFalse($result['gates']['transaction_overlay_reconciliation']);
            $this->assertSame(1, $result['item_overlay_reconciliation']['item_overlay_conflict_count']);
            $this->assertFalse($result['gates']['overlay_reconciliation']);
        } finally {
            File::delete($target);
        }
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

    private function tenantBPath(): string
    {
        $configured = getenv('SPJ_V2_C_TENANT_B_PATH') ?: config('spj.v2_c_tenant_b_path');
        $candidates = array_filter([
            is_string($configured) ? $configured : null,
            storage_path('app/school-databases/10208183/spj.sqlite'),
            base_path('../spj-bosp-data/storage/app/school-databases/10208183/spj.sqlite'),
            base_path('../../spj-bosp-data/storage/app/school-databases/10208183/spj.sqlite'),
        ]);

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    private function connect(string $target, ?string $source, int $npsn, bool $sourceUnavailable = false): void
    {
        config()->set('database.connections.school.database', $target);
        config()->set('spj.v2_b_isolated_manifest', [
            'target_path' => $target,
            'npsn' => $npsn,
            'source_id' => 1,
            'source_identity_npsn' => $sourceUnavailable ? null : $npsn,
            'source_path' => $source,
            'source_read_only' => ! $sourceUnavailable,
            'query_only' => ! $sourceUnavailable,
            'source_unavailable' => $sourceUnavailable,
            'mode' => $sourceUnavailable ? 'SOURCE_UNAVAILABLE_DRY_RUN' : null,
        ]);
        DB::purge('school');
    }

    private function migrateRehearsalSchema(): void
    {
        Artisan::call('migrate', [
            '--database' => 'school',
            '--path' => 'database/migrations/v2-rehearsal',
            '--force' => true,
            '--no-interaction' => true,
        ]);
    }

    private function protectedPackage(object $package): array
    {
        return array_intersect_key((array) $package, array_flip(['id', 'transaction_id', 'document_number', 'quarter_code', 'semester_code', 'phase_code', 'status', 'numbered_at', 'generated_at', 'snapshot', 'finalized_at', 'finalized_by', 'cancelled_at', 'cancelled_by', 'cancellation_reason']));
    }

    private function protectedDocument(object $document): array
    {
        return array_intersect_key((array) $document, array_flip(['id', 'spj_package_id', 'document_number', 'status', 'snapshot', 'template_snapshot', 'template_hash', 'rendered_hash', 'numbered_at', 'finalized_at', 'finalized_by', 'cancelled_at', 'cancelled_by', 'cancellation_reason']));
    }
}
