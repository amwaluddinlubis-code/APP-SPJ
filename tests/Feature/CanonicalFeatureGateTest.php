<?php

namespace Tests\Feature;

use App\Services\ArkasReferenceResolver;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class CanonicalFeatureGateTest extends TestCase
{
    protected function tearDown(): void
    {
        DB::purge('school');

        parent::tearDown();
    }

    public function test_production_reference_reads_default_to_central_compat(): void
    {
        $this->assertSame(ArkasReferenceResolver::CENTRAL_COMPAT, config('arkas.reference_read_mode'));
    }

    public function test_canonical_school_schema_contains_spj_fresh_and_raw_mirror_dependencies(): void
    {
        $databasePath = storage_path('framework/testing/canonical-gate-'.bin2hex(random_bytes(8)).'.sqlite');
        File::ensureDirectoryExists(dirname($databasePath));
        config()->set('database.connections.school.database', $databasePath);
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');

        try {
            Artisan::call('migrate', [
                '--database' => 'school',
                '--path' => 'database/migrations/school',
                '--force' => true,
                '--no-interaction' => true,
            ]);

            $tables = [
                'transactions',
                'transaction_items',
                'spj_packages',
                'spj_documents',
                'arkas_raw_mirror_tables',
                'arkas_raw_mirror_rows',
                'spj_fresh_transactions',
                'spj_fresh_transaction_items',
                'spj_fresh_packages',
                'spj_fresh_documents',
            ];

            foreach ($tables as $table) {
                $this->assertTrue(Schema::connection('school')->hasTable($table), $table);
            }
        } finally {
            DB::purge('school');
            File::delete($databasePath);
        }
    }
}
