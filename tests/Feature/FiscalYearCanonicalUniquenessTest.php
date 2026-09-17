<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FiscalYearCanonicalUniquenessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');
        Artisan::call('migrate', ['--database' => 'school', '--path' => 'database/migrations/school', '--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    public function test_same_year_can_use_different_canonical_fund_sources(): void
    {
        $this->seedFundSources();

        DB::connection('school')->table('fiscal_years')->insert([
            ['year' => 2026, 'fund_source' => 'Legacy BOSP', 'fund_source_id' => 1, 'is_active' => true],
            ['year' => 2026, 'fund_source' => 'Legacy BOSP', 'fund_source_id' => 2, 'is_active' => true],
        ]);

        $this->assertSame(2, DB::connection('school')->table('fiscal_years')->count());
    }

    public function test_same_year_and_canonical_fund_source_id_is_rejected(): void
    {
        $this->seedFundSources();

        DB::connection('school')->table('fiscal_years')->insert([
            'year' => 2026,
            'fund_source' => 'BOSP Reguler',
            'fund_source_id' => 1,
            'is_active' => true,
        ]);

        $this->expectException(QueryException::class);

        DB::connection('school')->table('fiscal_years')->insert([
            'year' => 2026,
            'fund_source' => 'Renamed label',
            'fund_source_id' => 1,
            'is_active' => true,
        ]);
    }

    public function test_legacy_fund_source_label_is_not_an_identity_key(): void
    {
        $this->seedFundSources();

        DB::connection('school')->table('fiscal_years')->insert([
            'year' => 2026,
            'fund_source' => 'BOSP',
            'fund_source_id' => 1,
            'is_active' => true,
        ]);

        DB::connection('school')->table('fiscal_years')->insert([
            'year' => 2026,
            'fund_source' => 'BOSP',
            'fund_source_id' => 2,
            'is_active' => true,
        ]);

        $this->assertSame(2, DB::connection('school')->table('fiscal_years')->where('fund_source', 'BOSP')->count());
    }

    public function test_migration_refuses_existing_canonical_duplicates(): void
    {
        $this->seedFundSources();
        $connection = DB::connection('school');

        Schema::connection('school')->table('fiscal_years', function (Blueprint $table): void {
            $table->dropUnique('fiscal_years_year_fund_source_id_unique');
        });
        $connection->table('fiscal_years')->insert([
            ['year' => 2026, 'fund_source' => 'BOSP A', 'fund_source_id' => 1, 'is_active' => true],
            ['year' => 2026, 'fund_source' => 'BOSP B', 'fund_source_id' => 1, 'is_active' => true],
        ]);

        $migration = require database_path('migrations/school/2026_09_17_000000_scope_fiscal_year_uniqueness_by_fund_source_id.php');

        $this->expectException(\RuntimeException::class);
        $migration->up();
    }

    private function seedFundSources(): void
    {
        DB::connection('school')->table('fund_sources')->insert([
            ['id' => 1, 'code' => 'BOSP-1', 'name' => 'BOSP 1', 'is_hidden' => false],
            ['id' => 2, 'code' => 'BOSP-2', 'name' => 'BOSP 2', 'is_hidden' => false],
        ]);
    }
}
