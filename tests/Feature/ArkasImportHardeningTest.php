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
use App\Services\ArkasTenantLockKey;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class ArkasImportHardeningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');
        Artisan::call('migrate', [
            '--database' => 'school',
            '--path' => 'database/migrations/school',
            '--force' => true,
        ]);
        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Cache::flush();
        DB::purge('school');
        parent::tearDown();
    }

    public function test_upsert_preserves_first_created_timestamp_and_records_semantic_metrics(): void
    {
        $year = $this->createYear();
        $profile = $this->createRawProfile('hardening_upsert', 'upsert');
        $source = new ArkasSource(['school_id' => 101]);
        $service = $this->serviceWithSnapshots([
            [
                ['EXTERNAL_ID' => 'A', 'VALUE' => 'old'],
                ['EXTERNAL_ID' => 'B', 'VALUE' => 'stable'],
            ],
            [
                ['EXTERNAL_ID' => 'A', 'VALUE' => 'changed'],
                ['EXTERNAL_ID' => 'B', 'VALUE' => 'stable'],
                ['EXTERNAL_ID' => 'C', 'VALUE' => 'new'],
            ],
        ]);

        Carbon::setTestNow('2026-09-10 09:00:00');
        $firstRun = $service->synchronize($profile, $year, $source);
        $firstA = $this->row($profile, $year, 'A');
        $firstB = $this->row($profile, $year, 'B');

        $this->assertSame(2, $firstRun->records_read);
        $this->assertSame(2, $firstRun->records_written);
        $this->assertSame(2, $firstRun->records_new);
        $this->assertSame(0, $firstRun->records_changed);
        $this->assertSame(0, $firstRun->records_unchanged);
        $this->assertSame(0, $firstRun->records_removed);

        Carbon::setTestNow('2026-09-10 10:00:00');
        $secondRun = $service->synchronize($profile->refresh(), $year, $source);
        $secondA = $this->row($profile, $year, 'A');
        $secondB = $this->row($profile, $year, 'B');
        $secondC = $this->row($profile, $year, 'C');

        $this->assertSame(3, $secondRun->records_read);
        $this->assertSame(2, $secondRun->records_written);
        $this->assertSame(1, $secondRun->records_new);
        $this->assertSame(1, $secondRun->records_changed);
        $this->assertSame(1, $secondRun->records_unchanged);
        $this->assertSame(0, $secondRun->records_removed);

        $this->assertSame($firstA->created_at, $secondA->created_at);
        $this->assertSame($firstB->created_at, $secondB->created_at);
        $this->assertSame($firstB->updated_at, $secondB->updated_at);
        $this->assertSame('2026-09-10 10:00:00', $secondA->updated_at);
        $this->assertSame('2026-09-10 10:00:00', $secondC->created_at);
    }

    public function test_full_refresh_reports_removed_rows_without_recreating_unchanged_rows(): void
    {
        $year = $this->createYear();
        $profile = $this->createRawProfile('hardening_refresh', 'full_refresh');
        $source = new ArkasSource(['school_id' => 102]);
        $service = $this->serviceWithSnapshots([
            [
                ['EXTERNAL_ID' => 'A', 'VALUE' => 'remove-me'],
                ['EXTERNAL_ID' => 'B', 'VALUE' => 'stable'],
            ],
            [
                ['EXTERNAL_ID' => 'B', 'VALUE' => 'stable'],
                ['EXTERNAL_ID' => 'C', 'VALUE' => 'new'],
            ],
        ]);

        Carbon::setTestNow('2026-09-10 09:00:00');
        $service->synchronize($profile, $year, $source);
        $firstB = $this->row($profile, $year, 'B');

        Carbon::setTestNow('2026-09-10 10:00:00');
        $run = $service->synchronize($profile->refresh(), $year, $source);
        $secondB = $this->row($profile, $year, 'B');

        $this->assertSame(2, $run->records_read);
        $this->assertSame(1, $run->records_written);
        $this->assertSame(1, $run->records_new);
        $this->assertSame(0, $run->records_changed);
        $this->assertSame(1, $run->records_unchanged);
        $this->assertSame(1, $run->records_removed);
        $this->assertFalse($this->rowExists($profile, $year, 'A'));
        $this->assertTrue($this->rowExists($profile, $year, 'C'));
        $this->assertSame($firstB->created_at, $secondB->created_at);
        $this->assertSame($firstB->updated_at, $secondB->updated_at);
    }

    public function test_import_and_staging_share_a_tenant_scoped_resource_lock(): void
    {
        $year = $this->createYear();
        $profile = $this->createRawProfile('hardening_lock', 'upsert');
        $sourceA = new ArkasSource(['school_id' => 201]);
        $sourceB = new ArkasSource(['school_id' => 202]);

        $importA = ArkasTenantLockKey::import($sourceA, $profile->source_table, $year->id);
        $stagingA = ArkasTenantLockKey::staging($sourceA, $profile->source_table, $year->id);
        $importB = ArkasTenantLockKey::import($sourceB, $profile->source_table, $year->id);
        $stagingB = ArkasTenantLockKey::staging($sourceB, $profile->source_table, $year->id);

        $this->assertSame($importA, $stagingA);
        $this->assertSame($importB, $stagingB);
        $this->assertNotSame($importA, $importB);

        $held = Cache::lock($stagingA, 900);
        $this->assertTrue($held->get());

        try {
            $otherTenantService = $this->serviceWithSnapshots([[['EXTERNAL_ID' => 'B', 'VALUE' => 'other tenant']]]);
            $this->assertSame('SUCCESS', $otherTenantService->synchronize($profile, $year, $sourceB)->status);

            $blockedStaging = Mockery::mock(ArkasStagingService::class);
            $blockedStaging->shouldNotReceive('fetch');
            $blockedService = new ArkasGenericImportService(
                $blockedStaging,
                new ArkasDomainAdapter,
                new ArkasSourceKeyResolver,
            );

            try {
                $blockedService->synchronize($profile->refresh(), $year, $sourceA);
                $this->fail('Importer harus ditolak ketika resource lock staging untuk tenant/tabel/tahun yang sama sedang dipegang.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('sedang berjalan', $exception->getMessage());
            }
        } finally {
            $held->release();
        }
    }

    private function createYear(): FiscalYear
    {
        return FiscalYear::query()->create([
            'year' => 2026,
            'fund_source' => 'BOSP',
            'fund_source_id' => 1,
            'is_active' => true,
        ]);
    }

    private function createRawProfile(string $sourceTable, string $syncMode): ArkasImportProfile
    {
        return ArkasImportProfile::query()->create([
            'source_table' => $sourceTable,
            'target_domain' => 'raw',
            'label' => $sourceTable,
            'source_key_column' => 'EXTERNAL_ID',
            'sync_mode' => $syncMode,
            'mapping' => ['EXTERNAL_ID' => 'source_key'],
        ]);
    }

    /** @param array<int, array<int, array<string, mixed>>> $snapshots */
    private function serviceWithSnapshots(array $snapshots): ArkasGenericImportService
    {
        $staging = Mockery::mock(ArkasStagingService::class);
        $staging->shouldReceive('fetch')->times(count($snapshots))->andReturn(...$snapshots);

        return new ArkasGenericImportService(
            $staging,
            new ArkasDomainAdapter,
            new ArkasSourceKeyResolver,
        );
    }

    private function row(ArkasImportProfile $profile, FiscalYear $year, string $sourceKey): object
    {
        return DB::connection('school')->table('arkas_import_rows')
            ->where('profile_id', $profile->id)
            ->where('fiscal_year_id', $year->id)
            ->where('source_key', $sourceKey)
            ->firstOrFail();
    }

    private function rowExists(ArkasImportProfile $profile, FiscalYear $year, string $sourceKey): bool
    {
        return DB::connection('school')->table('arkas_import_rows')
            ->where('profile_id', $profile->id)
            ->where('fiscal_year_id', $year->id)
            ->where('source_key', $sourceKey)
            ->exists();
    }
}
