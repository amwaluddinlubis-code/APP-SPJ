<?php

namespace Tests\Feature;

use App\Livewire\DatabaseTableExplorer;
use App\Models\School;
use App\Models\SchoolDatabase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class DatabaseTableSummaryTest extends TestCase
{
    use RefreshDatabase;

    private string $dbPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dbPath = tempnam(sys_get_temp_dir(), 'dbguide-test-').'.sqlite';
        touch($this->dbPath);
        config()->set('database.connections.school.database', $this->dbPath);
        DB::purge('school');

        Schema::connection('school')->create('demo_pegawai', function ($table): void {
            $table->id();
            $table->string('nama');
            $table->string('nip')->nullable();
        });
        DB::connection('school')->table('demo_pegawai')->insert([
            ['nama' => 'Uji Coba', 'nip' => '123'],
            ['nama' => 'Data Jahat <script>alert(1)</script>', 'nip' => null],
        ]);

        $school = School::query()->create(['npsn' => '99900001', 'name' => 'Sekolah Uji']);
        SchoolDatabase::query()->create(['school_id' => $school->id, 'database_path' => $this->dbPath, 'status' => 'READY']);
        $this->withSession(['active_school_id' => $school->id]);
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        if (isset($this->dbPath) && file_exists($this->dbPath)) {
            unlink($this->dbPath);
        }
        parent::tearDown();
    }

    public function test_admin_gets_table_summary_json(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $response = $this->getJson('/pengaturan/database-aktif/tabel/demo_pegawai')->assertOk();
        $response->assertJsonPath('name', 'demo_pegawai');
        $response->assertJsonPath('total', 2);
        $response->assertJsonPath('meta.group', 'Sistem');
        $response->assertJsonCount(3, 'columns');

        $names = collect($response->json('columns'))->pluck('name')->all();
        $this->assertContains('nama', $names);
    }

    public function test_unknown_table_returns_404(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->getJson('/pengaturan/database-aktif/tabel/tidak_ada')->assertNotFound();
        $this->getJson('/pengaturan/database-aktif/tabel/migrations;DROP')->assertNotFound();
    }

    public function test_viewer_is_forbidden_and_guest_redirected(): void
    {
        $this->get('/pengaturan/database-aktif/tabel/demo_pegawai')->assertRedirect('/masuk');

        $this->actingAs(User::factory()->create(['role' => User::ROLE_VIEWER]));
        $this->getJson('/pengaturan/database-aktif/tabel/demo_pegawai')->assertForbidden();
    }

    public function test_table_explorer_keeps_rows_visible_on_second_page(): void
    {
        $tables = collect(range(1, 30))->map(fn (int $number): array => [
            'name' => 'demo_table_'.$number,
            'label' => 'Demo '.$number,
            'group' => 'Sistem',
            'blurb' => 'Tabel uji',
            'count' => $number,
            'columns' => 3,
        ])->all();

        Livewire::test(DatabaseTableExplorer::class, ['tables' => $tables])
            ->assertSee('demo_table_1')
            ->call('setPage', 2)
            ->assertSee('demo_table_23')
            ->assertDontSee('demo_table_1</p>');
    }

    public function test_central_table_explorer_reads_the_application_database(): void
    {
        Schema::create('central_demo', function ($table): void {
            $table->id();
            $table->string('name');
        });
        DB::table('central_demo')->insert(['name' => 'Pusat']);

        Livewire::test(DatabaseTableExplorer::class, [
            'database' => 'central',
            'tables' => [[
                'name' => 'central_demo',
                'label' => 'Central Demo',
                'group' => 'Sistem',
                'blurb' => 'Tabel pusat',
                'count' => 1,
                'columns' => 2,
            ]],
        ])
            ->assertSee('central_demo')
            ->call('openTable', 'central_demo')
            ->assertSee('Pusat');
    }

    public function test_open_table_action_is_not_shadowed_by_livewire_state(): void
    {
        $reflection = new \ReflectionClass(DatabaseTableExplorer::class);

        $this->assertFalse($reflection->hasProperty('openTable'));
        $this->assertTrue($reflection->hasMethod('openTable'));
    }
}
