<?php

namespace Tests\Feature;

use App\Jobs\SynchronizeArkasImport;
use App\Models\ArkasImportProfile;
use App\Models\ArkasImportRun;
use App\Models\ArkasSource;
use App\Models\BackgroundOperation;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\School;
use App\Services\ArkasDatabaseExplorer;
use App\Services\ArkasGenericImportService;
use App\Services\ArkasImportGuard;
use App\Services\SchoolDatabaseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class ArkasImportQueueTenantTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private array $tenantPaths = [];

    protected function tearDown(): void
    {
        DB::purge('school');
        foreach ($this->tenantPaths as $path) {
            foreach ([$path, $path.'-wal', $path.'-shm'] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
        parent::tearDown();
    }

    public function test_background_import_activates_school_before_reading_tenant_profile_and_year(): void
    {
        $school = School::query()->create(['npsn' => '92000001', 'name' => 'Queue School']);
        $source = ArkasSource::query()->create([
            'school_id' => $school->id,
            'database_path' => '/tmp/queue-arkas.sqlite',
            'bridge_path' => '/tmp/queue-bridge',
            'database_password' => 'secret',
        ]);
        $operation = BackgroundOperation::query()->create([
            'school_id' => $school->id,
            'fiscal_year_id' => 1,
            'type' => 'ARKAS_IMPORT',
            'status' => 'QUEUED',
            'progress' => 0,
            'message' => 'Queued',
        ]);

        $pathA = $this->createTenantDatabase('queue-a');
        $pathB = $this->createTenantDatabase('queue-b');

        $this->activateTenant($pathA);
        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        $year = FiscalYear::query()->create([
            'year' => 2026,
            'fund_source' => 'BOSP',
            'fund_source_id' => 1,
            'is_active' => true,
        ]);
        $profile = ArkasImportProfile::query()->create([
            'source_table' => 'queue_table',
            'target_domain' => 'raw',
            'label' => 'Queue profile',
            'source_key_column' => 'EXTERNAL_ID',
            'sync_mode' => 'upsert',
            'mapping' => ['EXTERNAL_ID' => 'source_key'],
            'source_columns' => ['EXTERNAL_ID', 'VALUE'],
        ]);
        $run = ArkasImportRun::query()->create([
            'profile_id' => $profile->id,
            'fiscal_year_id' => $year->id,
            'status' => 'SUCCESS',
            'records_read' => 1,
            'records_written' => 1,
            'message' => 'Queue import selesai.',
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $this->activateTenant($pathB);
        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        FiscalYear::query()->create([
            'year' => 2025,
            'fund_source' => 'BOSP',
            'fund_source_id' => 1,
            'is_active' => true,
        ]);
        $this->assertFalse(ArkasImportProfile::query()->where('source_table', 'queue_table')->exists());

        $databases = Mockery::mock(SchoolDatabaseManager::class);
        $databases->shouldReceive('activate')->once()->withArgs(function (School $activated) use ($school, $pathA): bool {
            $this->assertSame($school->id, $activated->id);
            $this->activateTenant($pathA);

            return true;
        });

        $explorer = Mockery::mock(ArkasDatabaseExplorer::class);
        $explorer->shouldReceive('inspect')->once()->withArgs(function (ArkasSource $actualSource, string $table, int $limit) use ($source, $pathA): bool {
            $this->assertSame($pathA, config('database.connections.school.database'));
            $this->assertSame($source->id, $actualSource->id);
            $this->assertSame('queue_table', $table);
            $this->assertSame(1, $limit);

            return true;
        })->andReturn([
            'columns' => [['name' => 'EXTERNAL_ID'], ['name' => 'VALUE']],
            'rows' => [],
        ]);

        $importer = Mockery::mock(ArkasGenericImportService::class);
        $importer->shouldReceive('synchronize')->once()->withArgs(function (ArkasImportProfile $actualProfile, FiscalYear $actualYear, ArkasSource $actualSource) use ($profile, $year, $source, $pathA): bool {
            $this->assertSame($pathA, config('database.connections.school.database'));
            $this->assertSame($profile->id, $actualProfile->id);
            $this->assertSame($year->id, $actualYear->id);
            $this->assertSame($source->id, $actualSource->id);

            return true;
        })->andReturn($run);

        $job = new SynchronizeArkasImport($operation->id, $school->id, $profile->id, $year->id, $source->id);
        $job->handle($importer, $databases, $explorer, new ArkasImportGuard);

        $operation->refresh();
        $this->assertSame('COMPLETED', $operation->status);
        $this->assertSame(100, $operation->progress);
        $this->assertSame($run->id, $operation->result['run_id']);
        $this->assertSame(1, $operation->result['records_written']);
        $this->assertSame($pathA, config('database.connections.school.database'));
    }

    private function createTenantDatabase(string $suffix): string
    {
        $path = tempnam(sys_get_temp_dir(), 'spj-arkas-'.$suffix.'-');
        if ($path === false) {
            $this->fail('Gagal membuat temporary tenant database.');
        }

        $this->tenantPaths[] = $path;
        $this->activateTenant($path);
        Artisan::call('migrate', [
            '--database' => 'school',
            '--path' => 'database/migrations/school',
            '--force' => true,
            '--no-interaction' => true,
        ]);

        return $path;
    }

    private function activateTenant(string $path): void
    {
        config()->set('database.connections.school.database', $path);
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');
        DB::reconnect('school');
        DB::connection('school')->statement('PRAGMA foreign_keys=ON');
    }
}
