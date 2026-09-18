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
        $target = getenv('SPJ_V2_C_TENANT_A_PATH') ?: '';
        $source = getenv('SPJ_V2_C_SOURCE_PATH') ?: '';
        $this->assertNotSame('', $target);
        $this->assertNotSame('', $source);
        $this->connect($target, $source, 10260756);
        $this->migrateRehearsalSchema();

        $result = app(SpjV2LegacyMigrationService::class)->verify(DB::connection('school'));
        $this->assertSame('ok', $result['integrity_check']);
        $this->assertSame(0, $result['foreign_key_violations']);
        $this->assertSame(0, max($result['orphans']));
        $this->assertSame(291, $result['counts']['spj_transactions']);
        $this->assertSame(699, $result['counts']['spj_transaction_sources']);
        $this->assertSame(291, $result['counts']['legacy_transaction_v2_map']);
        $this->assertSame(67, $result['counts']['package_v2_links']);
        $this->assertSame('PASS', $result['source_adapter_validation']['status']);
        $this->assertSame(0, $result['source_adapter_validation']['unresolved_links']);
        $this->assertTrue($result['context_isolation']['transaction_boundary_unique']);
        $this->assertSame(268, $result['context_isolation']['source_identity_cross_context_count']);
        $this->assertSame('FAIL', $result['context_isolation']['status']);
        $this->assertSame(187, (int) ($result['canonical_context_classification']['ACTIVE_CANONICAL'] ?? 0));
        $this->assertSame(104, (int) ($result['canonical_context_classification']['LEGACY_DUPLICATE'] ?? 0));
        $this->assertSame(245, $result['item_overlay_reconciliation']['legacy_operator_owned_candidates']);
        $this->assertSame(245, $result['item_overlay_reconciliation']['v2_item_overlays']);
        $this->assertSame(0, $result['item_overlay_reconciliation']['lost_overlay']);
        $this->assertSame(0, $result['item_overlay_reconciliation']['unexpected_overlay']);
        $this->assertEquals(429605000, $result['financial_reconciliation']['ACTIVE_CANONICAL']['gross_from_raw']);
        $this->assertEquals(20497310, $result['financial_reconciliation']['ACTIVE_CANONICAL']['tax_from_raw']);
        $this->assertEquals(409107690, $result['financial_reconciliation']['ACTIVE_CANONICAL']['net_from_raw']);
    }

    public function test_tenant_b_dry_run_is_source_safe_and_does_not_guess(): void
    {
        $target = getenv('SPJ_V2_C_TENANT_B_PATH') ?: '';
        $this->assertNotSame('', $target);
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
        $target = getenv('SPJ_V2_C_TENANT_A_PATH') ?: '';
        $source = getenv('SPJ_V2_C_SOURCE_PATH') ?: '';
        $this->assertNotSame('', $target);
        $synthetic = storage_path('app/v2-c-rehearsal/final-synthetic.sqlite');
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
