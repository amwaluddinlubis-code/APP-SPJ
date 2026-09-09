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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class ArkasGenericImportSyncModeTest extends TestCase
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

    public function test_upsert_updates_existing_keys_and_keeps_missing_snapshot_rows(): void
    {
        $year = $this->createYear(2026);
        $profile = $this->createRawProfile('upsert_table', 'upsert');
        $initial = [
            ['EXTERNAL_ID' => 'A', 'VALUE' => 'A-old'],
            ['EXTERNAL_ID' => 'B', 'VALUE' => 'B-stable'],
        ];
        $next = [
            ['EXTERNAL_ID' => 'A', 'VALUE' => 'A-new'],
            ['EXTERNAL_ID' => 'B', 'VALUE' => 'B-stable'],
            ['EXTERNAL_ID' => 'C', 'VALUE' => 'C-new'],
        ];

        $service = $this->serviceWithSnapshots($initial, $next);
        $service->synchronize($profile, $year, new ArkasSource);
        $run = $service->synchronize($profile->refresh(), $year, new ArkasSource);

        $rows = DB::connection('school')->table('arkas_import_rows')
            ->where('profile_id', $profile->id)
            ->where('fiscal_year_id', $year->id)
            ->orderBy('source_key')
            ->get();

        $this->assertSame('SUCCESS', $run->status);
        $this->assertSame(3, $run->records_read);
        $this->assertSame(3, $rows->count());
        $this->assertSame(['A', 'B', 'C'], $rows->pluck('source_key')->all());
        $this->assertSame('A-new', $this->payloadFor($profile, $year, 'A')['VALUE']);
        $this->assertSame('B-stable', $this->payloadFor($profile, $year, 'B')['VALUE']);
        $this->assertSame('C-new', $this->payloadFor($profile, $year, 'C')['VALUE']);
    }

    public function test_incremental_only_applies_records_newer_than_previous_sync_checkpoint(): void
    {
        $year = $this->createYear(2026);
        $profile = $this->createRawProfile('incremental_table', 'incremental', 'UPDATED_AT');
        $initial = [
            ['EXTERNAL_ID' => 'A', 'VALUE' => 'A-old', 'UPDATED_AT' => '2026-09-10 09:00:00'],
            ['EXTERNAL_ID' => 'B', 'VALUE' => 'B-old', 'UPDATED_AT' => '2026-09-10 09:30:00'],
        ];
        $next = [
            ['EXTERNAL_ID' => 'A', 'VALUE' => 'A-stale-change', 'UPDATED_AT' => '2026-09-10 09:55:00'],
            ['EXTERNAL_ID' => 'B', 'VALUE' => 'B-new', 'UPDATED_AT' => '2026-09-10 10:05:00'],
            ['EXTERNAL_ID' => 'C', 'VALUE' => 'C-new', 'UPDATED_AT' => '2026-09-10 10:06:00'],
        ];

        $service = $this->serviceWithSnapshots($initial, $next);

        Carbon::setTestNow('2026-09-10 10:00:00');
        $firstRun = $service->synchronize($profile, $year, new ArkasSource);
        $this->assertSame(2, $firstRun->records_read);
        $this->assertSame('2026-09-10 10:00:00', $profile->refresh()->last_synced_at?->format('Y-m-d H:i:s'));

        Carbon::setTestNow('2026-09-10 10:10:00');
        $secondRun = $service->synchronize($profile, $year, new ArkasSource);

        $this->assertSame('SUCCESS', $secondRun->status);
        $this->assertSame(2, $secondRun->records_read);
        $this->assertSame(2, $secondRun->records_written);
        $this->assertSame('A-old', $this->payloadFor($profile, $year, 'A')['VALUE']);
        $this->assertSame('B-new', $this->payloadFor($profile, $year, 'B')['VALUE']);
        $this->assertSame('C-new', $this->payloadFor($profile, $year, 'C')['VALUE']);
        $this->assertSame('2026-09-10 10:10:00', $profile->refresh()->last_synced_at?->format('Y-m-d H:i:s'));
    }

    public function test_full_refresh_replaces_only_active_profile_year_and_domain_scope(): void
    {
        $activeYear = $this->createYear(2026);
        $otherYear = $this->createYear(2025, false);
        $profile = ArkasImportProfile::query()->create([
            'source_table' => 'rapbs',
            'target_domain' => 'rkas',
            'label' => 'RKAS Full Refresh',
            'source_key_column' => 'ID_RAPBS',
            'sync_mode' => 'full_refresh',
            'mapping' => [
                'ID_RAPBS' => 'source_key',
                'KODE_REKENING' => 'account_code',
                'URAIAN' => 'description',
                'JUMLAH' => 'amount',
            ],
        ]);
        $otherProfile = $this->createRawProfile('other_profile_table', 'upsert');
        $initial = [
            ['ID_RAPBS' => 'R1', 'KODE_REKENING' => '5.1.01', 'URAIAN' => 'Lama 1', 'JUMLAH' => 1000],
            ['ID_RAPBS' => 'R2', 'KODE_REKENING' => '5.1.02', 'URAIAN' => 'Lama 2', 'JUMLAH' => 2000],
        ];
        $replacement = [
            ['ID_RAPBS' => 'R2', 'KODE_REKENING' => '5.1.02', 'URAIAN' => 'Baru 2', 'JUMLAH' => 2500],
            ['ID_RAPBS' => 'R3', 'KODE_REKENING' => '5.1.03', 'URAIAN' => 'Baru 3', 'JUMLAH' => 3000],
        ];

        $service = $this->serviceWithSnapshots($initial, $replacement, new ArkasDomainAdapter);
        $service->synchronize($profile, $activeYear, new ArkasSource);

        $this->seedStagingRow($profile, $otherYear, 'YEAR2');
        $this->seedStagingRow($otherProfile, $activeYear, 'OTHER-PROFILE');
        DB::connection('school')->table('arkas_rkas_items')->insert([
            'fiscal_year_id' => $otherYear->id,
            'fund_source_id' => 1,
            'source_rapbs_id' => 'YEAR2',
            'description' => 'Tahun lain harus tetap ada',
            'amount' => 4000,
            'payload' => json_encode(['ID_RAPBS' => 'YEAR2'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $run = $service->synchronize($profile->refresh(), $activeYear, new ArkasSource);

        $activeKeys = DB::connection('school')->table('arkas_import_rows')
            ->where('profile_id', $profile->id)
            ->where('fiscal_year_id', $activeYear->id)
            ->orderBy('source_key')
            ->pluck('source_key')
            ->all();

        $this->assertSame('SUCCESS', $run->status);
        $this->assertSame(['R2', 'R3'], $activeKeys);
        $this->assertFalse($this->stagingRowExists($profile, $activeYear, 'R1'));
        $this->assertTrue($this->stagingRowExists($profile, $otherYear, 'YEAR2'));
        $this->assertTrue($this->stagingRowExists($otherProfile, $activeYear, 'OTHER-PROFILE'));

        $domain = DB::connection('school')->table('arkas_rkas_items');
        $this->assertFalse((clone $domain)->where('fiscal_year_id', $activeYear->id)->where('source_rapbs_id', 'R1')->exists());
        $this->assertTrue((clone $domain)->where('fiscal_year_id', $activeYear->id)->where('source_rapbs_id', 'R2')->exists());
        $this->assertTrue((clone $domain)->where('fiscal_year_id', $activeYear->id)->where('source_rapbs_id', 'R3')->exists());
        $this->assertTrue((clone $domain)->where('fiscal_year_id', $otherYear->id)->where('source_rapbs_id', 'YEAR2')->exists());
        $this->assertSame('Baru 2', (clone $domain)->where('fiscal_year_id', $activeYear->id)->where('source_rapbs_id', 'R2')->value('description'));
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

    /** @param array<int, array<string, mixed>> ...$snapshots */
    private function serviceWithSnapshots(array ...$snapshots): ArkasGenericImportService
    {
        $adapter = new ArkasDomainAdapter;
        if ($snapshots !== [] && end($snapshots) instanceof ArkasDomainAdapter) {
            /** @var ArkasDomainAdapter $adapter */
            $adapter = array_pop($snapshots);
        }

        $staging = Mockery::mock(ArkasStagingService::class);
        $expectation = $staging->shouldReceive('fetch')->times(count($snapshots));
        foreach ($snapshots as $snapshot) {
            $expectation->andReturn($snapshot);
        }

        return new ArkasGenericImportService($staging, $adapter, new ArkasSourceKeyResolver);
    }

    /** @return array<string, mixed> */
    private function payloadFor(ArkasImportProfile $profile, FiscalYear $year, string $sourceKey): array
    {
        $payload = DB::connection('school')->table('arkas_import_rows')
            ->where('profile_id', $profile->id)
            ->where('fiscal_year_id', $year->id)
            ->where('source_key', $sourceKey)
            ->value('payload');

        return json_decode((string) $payload, true, 512, JSON_THROW_ON_ERROR);
    }

    private function seedStagingRow(ArkasImportProfile $profile, FiscalYear $year, string $sourceKey): void
    {
        $payload = json_encode(['source_key' => $sourceKey], JSON_THROW_ON_ERROR);
        DB::connection('school')->table('arkas_import_rows')->insert([
            'profile_id' => $profile->id,
            'fiscal_year_id' => $year->id,
            'source_key' => $sourceKey,
            'payload' => $payload,
            'payload_hash' => hash('sha256', $payload),
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
