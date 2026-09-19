<?php

namespace Tests\Feature;

use App\Services\SpjV2LegacyMigrationService;
use App\Services\SpjV2ReadParityService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class V2DReadParityTest extends TestCase
{
    public function test_fresh_rehearsal_matches_v2_canonical_read_contract(): void
    {
        $sourceClone = storage_path('app/school-databases/10260786/spj.sqlite');
        $source = getenv('SPJ_V2_C_SOURCE_PATH') ?: '';
        $this->assertFileExists($sourceClone);
        $this->assertNotSame('', $source);
        $target = storage_path('app/v2-c-rehearsal/test-v2d-read-parity.sqlite');
        File::copy($sourceClone, $target);

        try {
            $this->connect($target, $source);
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
                storage_path('app/v2-c-rehearsal/reports/test-v2d-read-parity.json'),
                10260756,
            );
            $this->assertSame([], $migration['errors']);
            $this->assertSame('PASS', app(SpjV2LegacyMigrationService::class)->verify($db)['status']);

            $parity = app(SpjV2ReadParityService::class)->compare($db);
            $this->assertSame('PASS', $parity['status']);
            $this->assertSame(187, $parity['counts']['fresh_transactions']);
            $this->assertSame(187, $parity['counts']['v2_transactions']);
            $this->assertSame(187, $parity['counts']['shared_transactions']);
            $this->assertSame(0, $parity['identity']['missing_in_v2_count']);
            $this->assertSame(0, $parity['identity']['missing_in_fresh_count']);
            $this->assertSame(0, $parity['source_membership']['mismatch_count']);
            $this->assertSame(0, $parity['transaction_overlay']['mismatch_count']);
            $this->assertSame(0, $parity['transaction_overlay']['conflict_count']);
            $this->assertSame(0, $parity['item_overlay']['mismatch_count']);
            $this->assertSame(0, $parity['item_overlay']['conflict_count']);
        } finally {
            File::delete($target);
        }
    }

    public function test_shadow_read_parity_fails_closed_when_v2_overlay_drifts(): void
    {
        $sourceClone = storage_path('app/school-databases/10260786/spj.sqlite');
        $source = getenv('SPJ_V2_C_SOURCE_PATH') ?: '';
        $this->assertFileExists($sourceClone);
        $this->assertNotSame('', $source);
        $target = storage_path('app/v2-c-rehearsal/test-v2d-read-parity-drift.sqlite');
        File::copy($sourceClone, $target);

        try {
            $this->connect($target, $source);
            Artisan::call('migrate', [
                '--database' => 'school',
                '--path' => 'database/migrations/v2-rehearsal',
                '--force' => true,
                '--no-interaction' => true,
            ]);

            $db = DB::connection('school');
            app(SpjV2LegacyMigrationService::class)->migrate(
                $db,
                1,
                true,
                storage_path('app/v2-c-rehearsal/reports/test-v2d-read-parity-drift.json'),
                10260756,
            );
            $overlay = $db->table('spj_transaction_overlays')->orderBy('id')->first();
            $this->assertNotNull($overlay);
            $db->table('spj_transaction_overlays')->where('id', $overlay->id)->update([
                'payment_description' => '__V2_D_DRIFT__',
            ]);

            $parity = app(SpjV2ReadParityService::class)->compare($db);
            $this->assertSame('FAIL', $parity['status']);
            $this->assertSame(1, $parity['transaction_overlay']['mismatch_count']);
        } finally {
            File::delete($target);
        }
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
}
