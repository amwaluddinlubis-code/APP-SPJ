<?php

namespace Tests\Feature;

use App\Models\ArkasImportProfile;
use App\Models\ArkasSource;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\School;
use App\Models\User;
use App\Services\ArkasDatabaseExplorer;
use App\Services\SchoolDatabaseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Mockery\MockInterface;
use Tests\TestCase;

class ArkasImporterTenantBoundaryTest extends TestCase
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

    public function test_importer_routes_require_administrator_active_school_and_active_year(): void
    {
        foreach ([
            'arkas.importer',
            'arkas.importer.mapping.store',
            'arkas.importer.preview',
            'arkas.importer.sync',
        ] as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull($route, 'Route importer hilang: '.$routeName);
            $middleware = $route->gatherMiddleware();
            $this->assertContains('administrator', $middleware, 'Importer bukan administrator-only: '.$routeName);
            $this->assertContains('active-school', $middleware, 'Importer tidak mengaktifkan sekolah: '.$routeName);
            $this->assertContains('active-year', $middleware, 'Importer tidak mengaktifkan tahun: '.$routeName);
            $this->assertLessThan(
                array_search('active-year', $middleware, true),
                array_search('active-school', $middleware, true),
                'active-school harus berjalan sebelum active-year: '.$routeName,
            );
        }
    }

    public function test_importer_isolated_across_school_and_fiscal_year_contexts(): void
    {
        $schoolA = School::query()->create(['npsn' => '91000001', 'name' => 'Sekolah A']);
        $schoolB = School::query()->create(['npsn' => '91000002', 'name' => 'Sekolah B']);
        $administrator = User::factory()->create(['school_id' => null, 'role' => User::ROLE_ADMIN]);

        $pathA = $this->createTenantDatabase('school-a');
        $pathB = $this->createTenantDatabase('school-b');
        $paths = [$schoolA->id => $pathA, $schoolB->id => $pathB];

        $this->activateTenant($pathA);
        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        $yearA = FiscalYear::query()->create([
            'year' => 2026,
            'fund_source' => 'BOSP',
            'fund_source_id' => 1,
            'is_active' => true,
        ]);
        ArkasImportProfile::query()->create([
            'source_table' => 'school_a_table',
            'target_domain' => 'raw',
            'label' => 'PROFILE SEKOLAH A',
            'source_key_column' => 'ID',
            'sync_mode' => 'upsert',
            'mapping' => ['ID' => 'source_key'],
        ]);

        $this->activateTenant($pathB);
        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        FiscalYear::query()->create([
            'year' => 2025,
            'fund_source' => 'BOSP',
            'fund_source_id' => 1,
            'is_active' => false,
        ]);
        $foreignYear = FiscalYear::query()->create([
            'year' => 2026,
            'fund_source' => 'BOSP',
            'fund_source_id' => 1,
            'is_active' => true,
        ]);
        ArkasImportProfile::query()->create([
            'source_table' => 'school_b_dummy',
            'target_domain' => 'raw',
            'label' => 'PROFILE SEKOLAH B DUMMY',
            'source_key_column' => 'ID',
            'sync_mode' => 'upsert',
            'mapping' => ['ID' => 'source_key'],
        ]);
        $foreignProfile = ArkasImportProfile::query()->create([
            'source_table' => 'school_b_table',
            'target_domain' => 'raw',
            'label' => 'PROFILE SEKOLAH B',
            'source_key_column' => 'ID',
            'sync_mode' => 'upsert',
            'mapping' => ['ID' => 'source_key'],
        ]);

        $this->mock(SchoolDatabaseManager::class, function (MockInterface $mock) use ($paths): void {
            $activate = function (School $school) use ($paths): void {
                $this->activateTenant($paths[$school->id]);
            };

            $mock->shouldReceive('ensureMigrated')->andReturnUsing($activate);
            $mock->shouldReceive('activate')->andReturnUsing($activate);
        });
        $this->mock(ArkasDatabaseExplorer::class);

        $sessionA = [
            'active_school_id' => $schoolA->id,
            'active_fiscal_year_id' => $yearA->id,
            'active_fund_source_id' => 1,
        ];
        $sessionB = [
            'active_school_id' => $schoolB->id,
            'active_fiscal_year_id' => $foreignYear->id,
            'active_fund_source_id' => 1,
        ];

        $this->actingAs($administrator)
            ->withSession($sessionA)
            ->get(route('arkas.importer'))
            ->assertOk();
        $this->assertSame($pathA, config('database.connections.school.database'));
        $this->assertTrue(ArkasImportProfile::query()->where('source_table', 'school_a_table')->exists());
        $this->assertFalse(ArkasImportProfile::query()->where('source_table', 'school_b_table')->exists());

        $this->actingAs($administrator)
            ->withSession($sessionB)
            ->get(route('arkas.importer'))
            ->assertOk();
        $this->assertSame($pathB, config('database.connections.school.database'));
        $this->assertTrue(ArkasImportProfile::query()->where('source_table', 'school_b_table')->exists());
        $this->assertFalse(ArkasImportProfile::query()->where('source_table', 'school_a_table')->exists());

        $this->actingAs($administrator)
            ->withSession($sessionA)
            ->post(route('arkas.importer.preview', $foreignProfile->id))
            ->assertNotFound();

        $this->actingAs($administrator)
            ->withSession($sessionA)
            ->post(route('arkas.importer.sync', $foreignProfile->id))
            ->assertNotFound();

        ArkasSource::query()->create([
            'school_id' => $schoolA->id,
            'database_path' => '/tmp/fake-arkas.sqlite',
            'bridge_path' => '/tmp/fake-bridge',
            'database_password' => 'test-password',
        ]);
        $this->mock(ArkasDatabaseExplorer::class, function (MockInterface $mock): void {
            $mock->shouldReceive('tables')->once()->andReturn(['mapping_only']);
            $mock->shouldReceive('inspect')->once()->andReturn([
                'columns' => [['name' => 'EXTERNAL_ID']],
                'rows' => [],
            ]);
        });

        $this->actingAs($administrator)
            ->withSession($sessionA)
            ->post(route('arkas.importer.mapping.store'), [
                'source_table' => 'mapping_only',
                'target_domain' => 'raw',
                'label' => 'MAPPING SEKOLAH A',
                'source_key_column' => 'EXTERNAL_ID',
                'sync_mode' => 'upsert',
                'mapping' => ['EXTERNAL_ID' => 'source_key'],
            ])
            ->assertRedirect(route('arkas.importer', ['table' => 'mapping_only']));

        $this->activateTenant($pathA);
        $this->assertTrue(ArkasImportProfile::query()->where('source_table', 'mapping_only')->exists());
        $this->activateTenant($pathB);
        $this->assertFalse(ArkasImportProfile::query()->where('source_table', 'mapping_only')->exists());

        foreach ([
            ['GET', route('arkas.importer')],
            ['POST', route('arkas.importer.mapping.store')],
            ['POST', route('arkas.importer.preview', 1)],
            ['POST', route('arkas.importer.sync', 1)],
        ] as [$method, $uri]) {
            // Start on tenant B intentionally. Correct middleware order must switch to A
            // before validating a year id that only exists in B.
            $this->activateTenant($pathB);

            $this->actingAs($administrator)
                ->withSession([
                    'active_school_id' => $schoolA->id,
                    'active_fiscal_year_id' => $foreignYear->id,
                    'active_fund_source_id' => 1,
                ])
                ->call($method, $uri)
                ->assertRedirect(route('years.select'));
        }
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
