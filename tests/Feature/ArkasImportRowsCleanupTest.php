<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ArkasImportRowsCleanupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');
        DB::reconnect('school');
    }

    public function test_cleanup_drops_the_unused_column_and_preserves_existing_rows(): void
    {
        Schema::connection('school')->create('arkas_import_rows', function (Blueprint $table): void {
            $table->id();
            $table->string('source_key');
            $table->timestamp('source_updated_at')->nullable();
        });
        DB::connection('school')->table('arkas_import_rows')->insert([
            'source_key' => 'legacy-row',
            'source_updated_at' => null,
        ]);

        $this->runCleanupMigration();

        $this->assertFalse(Schema::connection('school')->hasColumn('arkas_import_rows', 'source_updated_at'));
        $this->assertSame('legacy-row', DB::connection('school')->table('arkas_import_rows')->value('source_key'));
    }

    public function test_cleanup_is_safe_when_legacy_database_has_no_importer_table(): void
    {
        $this->runCleanupMigration();

        $this->assertFalse(Schema::connection('school')->hasTable('arkas_import_rows'));
    }

    public function test_cleanup_is_safe_when_legacy_table_already_lacks_the_column(): void
    {
        Schema::connection('school')->create('arkas_import_rows', function (Blueprint $table): void {
            $table->id();
            $table->string('source_key');
        });

        $this->runCleanupMigration();

        $this->assertTrue(Schema::connection('school')->hasTable('arkas_import_rows'));
        $this->assertFalse(Schema::connection('school')->hasColumn('arkas_import_rows', 'source_updated_at'));
    }

    private function runCleanupMigration(): void
    {
        $migration = require database_path('migrations/school/2026_09_17_020000_drop_source_updated_at_from_arkas_import_rows.php');
        $migration->up();
    }
}
