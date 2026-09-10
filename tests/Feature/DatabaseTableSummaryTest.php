<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\SchoolDatabase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
}
