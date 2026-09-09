<?php

namespace Tests\Feature;

use App\Models\ArkasImportProfile;
use App\Models\ArkasSource;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Services\ArkasDomainAdapter;
use App\Services\ArkasGenericImportService;
use App\Services\ArkasSourceKeyResolver;
use App\Services\ArkasStagingService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class ArkasGenericImportSourceKeyTest extends TestCase
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

    public function test_non_empty_generic_import_persists_rows_through_shared_source_key_resolver(): void
    {
        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        $year = FiscalYear::query()->create([
            'year' => 2026,
            'fund_source' => 'BOSP',
            'fund_source_id' => 1,
            'is_active' => true,
        ]);
        $profile = ArkasImportProfile::query()->create([
            'source_table' => 'custom_arkas_table',
            'target_domain' => 'raw',
            'label' => 'Custom ARKAS Table',
            'source_key_column' => 'EXTERNAL_ID',
            'sync_mode' => 'upsert',
            'mapping' => ['EXTERNAL_ID' => 'source_key'],
        ]);
        $records = [
            ['external_id' => 'CUSTOM-001', 'uraian' => 'Baris dengan key konfigurasi'],
            ['ID_RAPBS' => 'RAPBS-002', 'uraian' => 'Baris fallback canonical'],
        ];

        $staging = Mockery::mock(ArkasStagingService::class);
        $staging->shouldReceive('fetch')->once()->andReturn($records);
        $adapter = Mockery::mock(ArkasDomainAdapter::class);
        $adapter->shouldReceive('synchronize')->once()->with($profile, $year, $records)->andReturn(0);

        $service = new ArkasGenericImportService($staging, $adapter, new ArkasSourceKeyResolver);
        $run = $service->synchronize($profile, $year, new ArkasSource);

        $this->assertSame('SUCCESS', $run->status);
        $this->assertSame(2, $run->records_read);
        $this->assertSame(2, $run->records_written);
        $this->assertSame(
            ['CUSTOM-001', 'RAPBS-002'],
            DB::connection('school')->table('arkas_import_rows')->orderBy('id')->pluck('source_key')->all(),
        );
    }
}
