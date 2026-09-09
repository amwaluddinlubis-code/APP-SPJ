<?php

namespace Tests\Feature;

use App\Models\ArkasImportProfile;
use App\Models\ArkasImportRun;
use App\Models\ArkasSource;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Services\ArkasDomainAdapter;
use App\Services\ArkasGenericImportService;
use App\Services\ArkasImportGuard;
use App\Services\ArkasReconciliationService;
use App\Services\ArkasSourceKeyResolver;
use App\Services\ArkasStagingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class ArkasGenericImportReleaseSafetyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');
        Artisan::call('migrate', ['--database' => 'school', '--path' => 'database/migrations/school', '--force' => true]);

        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::purge('school');
        parent::tearDown();
    }

    public function test_full_reconciliation_preview_is_read_only_for_staging_domain_and_run_history(): void
    {
        $year = $this->createYear(2026);
        $profile = $this->createRkasProfile('upsert');
        $this->seedStagingRow($profile, $year, 'R1', ['ID_RAPBS' => 'R1', 'URAIAN' => 'Lama']);
        DB::connection('school')->table('arkas_rkas_items')->insert([
            'fiscal_year_id' => $year->id,
            'fund_source_id' => 1,
            'source_rapbs_id' => 'R1',
            'description' => 'Domain lama',
            'amount' => 1000,
            'payload' => json_encode(['ID_RAPBS' => 'R1', 'URAIAN' => 'Domain lama'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $staging = Mockery::mock(ArkasStagingService::class);
        $staging->shouldReceive('fetch')->once()->andReturn([
            ['ID_RAPBS' => 'R1', 'URAIAN' => 'Berubah', 'JUMLAH' => 1500],
            ['ID_RAPBS' => 'R2', 'URAIAN' => 'Baru', 'JUMLAH' => 2000],
        ]);
        $service = new ArkasReconciliationService($staging, new ArkasSourceKeyResolver);

        $beforeStaging = DB::connection('school')->table('arkas_import_rows')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        $beforeDomain = DB::connection('school')->table('arkas_rkas_items')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        $beforeRuns = ArkasImportRun::query()->count();

        $preview = $service->preview($profile, $year, new ArkasSource);

        $this->assertSame(1, $preview['new']);
        $this->assertSame(1, $preview['changed']);
        $this->assertSame(0, $preview['removed']);
        $this->assertSame($beforeStaging, DB::connection('school')->table('arkas_import_rows')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all());
        $this->assertSame($beforeDomain, DB::connection('school')->table('arkas_rkas_items')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all());
        $this->assertSame($beforeRuns, ArkasImportRun::query()->count());
    }

    public function test_raw_upsert_without_stable_source_key_is_rejected_before_fetch(): void
    {
        $year = $this->createYear(2026);
        $profile = ArkasImportProfile::query()->create([
            'source_table' => 'custom_raw_table',
            'target_domain' => 'raw',
            'label' => 'Unsafe raw upsert',
            'source_key_column' => null,
            'sync_mode' => 'upsert',
            'mapping' => [],
        ]);
        $staging = Mockery::mock(ArkasStagingService::class);
        $staging->shouldReceive('fetch')->never();
        $service = new ArkasGenericImportService($staging, new ArkasDomainAdapter, new ArkasSourceKeyResolver);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('source key stabil');

        $service->synchronize($profile, $year, new ArkasSource);
    }

    public function test_raw_full_refresh_without_stable_source_key_remains_allowed(): void
    {
        $year = $this->createYear(2026);
        $profile = ArkasImportProfile::query()->create([
            'source_table' => 'custom_raw_table',
            'target_domain' => 'raw',
            'label' => 'Raw full refresh',
            'source_key_column' => null,
            'sync_mode' => 'full_refresh',
            'mapping' => [],
        ]);
        $staging = Mockery::mock(ArkasStagingService::class);
        $staging->shouldReceive('fetch')->once()->andReturn([
            ['NAME' => 'Alpha', 'VALUE' => '1'],
            ['NAME' => 'Beta', 'VALUE' => '2'],
        ]);
        $service = new ArkasGenericImportService($staging, new ArkasDomainAdapter, new ArkasSourceKeyResolver);

        $run = $service->synchronize($profile, $year, new ArkasSource);

        $this->assertSame('SUCCESS', $run->status);
        $this->assertSame(2, $run->records_written);
        $this->assertSame(2, DB::connection('school')->table('arkas_import_rows')->where('profile_id', $profile->id)->where('fiscal_year_id', $year->id)->count());
    }

    public function test_empty_source_preserves_upsert_and_incremental_but_full_refresh_clears_only_active_scope(): void
    {
        $year = $this->createYear(2026);
        $otherYear = $this->createYear(2025, false);

        $upsert = $this->createRawProfile('empty_upsert', 'upsert');
        $upsertService = $this->serviceWithSnapshots([
            [['EXTERNAL_ID' => 'U1', 'VALUE' => 'keep']],
            [],
        ]);
        $upsertService->synchronize($upsert, $year, new ArkasSource);
        $upsertRun = $upsertService->synchronize($upsert->refresh(), $year, new ArkasSource);
        $this->assertSame(0, $upsertRun->records_read);
        $this->assertTrue($this->stagingRowExists($upsert, $year, 'U1'));

        $incremental = $this->createRawProfile('empty_incremental', 'incremental', 'UPDATED_AT');
        $incrementalService = $this->serviceWithSnapshots([
            [['EXTERNAL_ID' => 'I1', 'VALUE' => 'keep', 'UPDATED_AT' => '2026-09-10 08:00:00']],
            [],
        ]);
        Carbon::setTestNow('2026-09-10 09:00:00');
        $incrementalService->synchronize($incremental, $year, new ArkasSource);
        Carbon::setTestNow('2026-09-10 10:00:00');
        $incrementalRun = $incrementalService->synchronize($incremental->refresh(), $year, new ArkasSource);
        $this->assertSame(0, $incrementalRun->records_read);
        $this->assertTrue($this->stagingRowExists($incremental, $year, 'I1'));

        $fullRefresh = $this->createRkasProfile('full_refresh');
        $refreshService = $this->serviceWithSnapshots([
            [['ID_RAPBS' => 'R1', 'KODE_REKENING' => '5.1.01', 'URAIAN' => 'Aktif', 'JUMLAH' => 1000]],
            [],
        ], new ArkasDomainAdapter);
        $refreshService->synchronize($fullRefresh, $year, new ArkasSource);
        $this->seedStagingRow($fullRefresh, $otherYear, 'OTHER-YEAR', ['ID_RAPBS' => 'OTHER-YEAR']);
        DB::connection('school')->table('arkas_rkas_items')->insert([
            'fiscal_year_id' => $otherYear->id,
            'fund_source_id' => 1,
            'source_rapbs_id' => 'OTHER-YEAR',
            'description' => 'Tahun lain',
            'amount' => 500,
            'payload' => json_encode(['ID_RAPBS' => 'OTHER-YEAR'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $refreshRun = $refreshService->synchronize($fullRefresh->refresh(), $year, new ArkasSource);

        $this->assertSame(0, $refreshRun->records_read);
        $this->assertFalse($this->stagingRowExists($fullRefresh, $year, 'R1'));
        $this->assertTrue($this->stagingRowExists($fullRefresh, $otherYear, 'OTHER-YEAR'));
        $this->assertFalse(DB::connection('school')->table('arkas_rkas_items')->where('fiscal_year_id', $year->id)->exists());
        $this->assertTrue(DB::connection('school')->table('arkas_rkas_items')->where('fiscal_year_id', $otherYear->id)->where('source_rapbs_id', 'OTHER-YEAR')->exists());
    }

    public function test_schema_guard_blocks_missing_mapped_key_and_incremental_timestamp_columns(): void
    {
        $profile = ArkasImportProfile::query()->create([
            'source_table' => 'custom_table',
            'target_domain' => 'raw',
            'label' => 'Schema guarded',
            'source_key_column' => 'EXTERNAL_ID',
            'source_updated_column' => 'UPDATED_AT',
            'sync_mode' => 'incremental',
            'mapping' => [
                'EXTERNAL_ID' => 'source_key',
                'VALUE' => 'description',
            ],
        ]);

        $errors = (new ArkasImportGuard)->schemaErrors($profile, ['VALUE']);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('EXTERNAL_ID', $errors[0]);
        $this->assertStringContainsString('UPDATED_AT', $errors[0]);
    }

    private function createYear(int $year, bool $active = true): FiscalYear
    {
        return FiscalYear::query()->create([
            'year' => $year,
            'fund_source' => 'BOSP',
            'fund_source_id' => 1,
            'is_active' => $active,
        ]);
    }

    private function createRawProfile(string $sourceTable, string $syncMode, ?string $updatedColumn = null): ArkasImportProfile
    {
        return ArkasImportProfile::query()->create([
            'source_table' => $sourceTable,
            'target_domain' => 'raw',
            'label' => ucfirst(str_replace('_', ' ', $sourceTable)),
            'source_key_column' => 'EXTERNAL_ID',
            'source_updated_column' => $updatedColumn,
            'sync_mode' => $syncMode,
            'mapping' => ['EXTERNAL_ID' => 'source_key'],
        ]);
    }

    private function createRkasProfile(string $syncMode): ArkasImportProfile
    {
        return ArkasImportProfile::query()->create([
            'source_table' => 'rapbs',
            'target_domain' => 'rkas',
            'label' => 'RKAS '.$syncMode,
            'source_key_column' => 'ID_RAPBS',
            'sync_mode' => $syncMode,
            'mapping' => [
                'ID_RAPBS' => 'source_key',
                'KODE_REKENING' => 'account_code',
                'URAIAN' => 'description',
                'JUMLAH' => 'amount',
            ],
        ]);
    }

    /** @param array<int, array<int, array<string, mixed>>> $snapshots */
    private function serviceWithSnapshots(array $snapshots, ?ArkasDomainAdapter $adapter = null): ArkasGenericImportService
    {
        $staging = Mockery::mock(ArkasStagingService::class);
        $staging->shouldReceive('fetch')->times(count($snapshots))->andReturn(...$snapshots);

        return new ArkasGenericImportService($staging, $adapter ?? new ArkasDomainAdapter, new ArkasSourceKeyResolver);
    }

    /** @param array<string, mixed> $payload */
    private function seedStagingRow(ArkasImportProfile $profile, FiscalYear $year, string $sourceKey, array $payload): void
    {
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        DB::connection('school')->table('arkas_import_rows')->insert([
            'profile_id' => $profile->id,
            'fiscal_year_id' => $year->id,
            'source_key' => $sourceKey,
            'payload' => $encoded,
            'payload_hash' => hash('sha256', $encoded),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function stagingRowExists(ArkasImportProfile $profile, FiscalYear $year, string $sourceKey): bool
    {
        return DB::connection('school')->table('arkas_import_rows')
            ->where('profile_id', $profile->id)
            ->where('fiscal_year_id', $year->id)
            ->where('source_key', $sourceKey)
            ->exists();
    }
}
