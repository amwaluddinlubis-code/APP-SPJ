<?php

namespace Tests\Feature;

use App\Models\ArkasImportProfile;
use App\Models\ArkasSource;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\School;
use App\Models\User;
use App\Services\ArkasDatabaseExplorer;
use App\Services\ArkasGenericImportService;
use App\Services\SchoolDatabaseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use Tests\TestCase;

class ArkasImporterRuntimeGuardTest extends TestCase
{
    use RefreshDatabase;

    private ?string $tenantPath = null;

    protected function tearDown(): void
    {
        DB::purge('school');
        if ($this->tenantPath) {
            foreach ([$this->tenantPath, $this->tenantPath.'-wal', $this->tenantPath.'-shm'] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
        parent::tearDown();
    }

    public function test_store_rejects_raw_upsert_without_stable_key_and_sync_blocks_missing_mapped_schema(): void
    {
        $school = School::query()->create(['npsn' => '93000001', 'name' => 'Guard School']);
        $administrator = User::factory()->create(['school_id' => null, 'role' => User::ROLE_ADMIN]);
        ArkasSource::query()->create([
            'school_id' => $school->id,
            'database_path' => '/tmp/guard-arkas.sqlite',
            'bridge_path' => '/tmp/guard-bridge',
            'database_password' => 'secret',
        ]);

        $this->tenantPath = tempnam(sys_get_temp_dir(), 'spj-arkas-guard-');
        if ($this->tenantPath === false) {
            $this->fail('Gagal membuat temporary tenant database.');
        }
        $this->activateTenant($this->tenantPath);
        Artisan::call('migrate', [
            '--database' => 'school',
            '--path' => 'database/migrations/school',
            '--force' => true,
            '--no-interaction' => true,
        ]);
        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        $year = FiscalYear::query()->create([
            'year' => 2026,
            'fund_source' => 'BOSP',
            'fund_source_id' => 1,
            'is_active' => true,
        ]);
        $profile = ArkasImportProfile::query()->create([
            'source_table' => 'drift_table',
            'target_domain' => 'raw',
            'label' => 'Drift profile',
            'source_key_column' => 'EXTERNAL_ID',
            'sync_mode' => 'upsert',
            'mapping' => ['EXTERNAL_ID' => 'source_key', 'VALUE' => 'description'],
            'source_columns' => ['EXTERNAL_ID', 'VALUE'],
        ]);

        $path = $this->tenantPath;
        $this->mock(SchoolDatabaseManager::class, function (MockInterface $mock) use ($path): void {
            $activate = function () use ($path): void {
                $this->activateTenant($path);
            };
            $mock->shouldReceive('ensureMigrated')->andReturnUsing($activate);
            $mock->shouldReceive('activate')->andReturnUsing($activate);
        });
        $this->mock(ArkasDatabaseExplorer::class, function (MockInterface $mock): void {
            $mock->shouldReceive('tables')->once()->andReturn(['unsafe_raw']);
            $mock->shouldReceive('inspect')->withArgs(fn (ArkasSource $source, string $table, int $limit): bool => $table === 'unsafe_raw' && $limit === 1)
                ->once()
                ->andReturn(['columns' => [['name' => 'VALUE']], 'rows' => []]);
            $mock->shouldReceive('inspect')->withArgs(fn (ArkasSource $source, string $table, int $limit): bool => $table === 'drift_table' && $limit === 1)
                ->once()
                ->andReturn(['columns' => [['name' => 'VALUE']], 'rows' => []]);
        });
        $this->mock(ArkasGenericImportService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('synchronize')->never();
        });

        $session = [
            'active_school_id' => $school->id,
            'active_fiscal_year_id' => $year->id,
            'active_fund_source_id' => 1,
        ];

        $this->actingAs($administrator)
            ->withSession($session)
            ->post(route('arkas.importer.mapping.store'), [
                'source_table' => 'unsafe_raw',
                'target_domain' => 'raw',
                'label' => 'Unsafe raw',
                'source_key_column' => null,
                'sync_mode' => 'upsert',
                'mapping' => [],
            ])
            ->assertSessionHasErrors('mapping');

        $this->assertFalse(ArkasImportProfile::query()->where('source_table', 'unsafe_raw')->exists());

        $response = $this->actingAs($administrator)
            ->withSession($session)
            ->post(route('arkas.importer.sync', $profile->id));

        $response->assertRedirect(route('arkas.importer', ['table' => 'drift_table']));
        $response->assertSessionHasErrors('mapping');
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
