<?php

namespace Tests\Feature;

use App\Livewire\EmployeeDirectory;
use App\Models\Employee;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class EmployeeDirectoryLivewireTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        DB::purge('school');

        parent::tearDown();
    }

    public function test_directory_search_and_filters_update_without_reload(): void
    {
        $this->seedEmployees();

        Livewire::test(EmployeeDirectory::class)
            ->assertSee('Budi Santoso')
            ->assertSee('Siti Aminah')
            ->set('search', 'Siti')
            ->assertSee('Siti Aminah')
            ->assertDontSee('Budi Santoso')
            ->set('search', '')
            ->set('source', 'DAPODIK')
            ->assertSee('Siti Aminah')
            ->assertDontSee('Budi Santoso');
    }

    public function test_directory_reset_and_pagination(): void
    {
        $this->seedEmployees(extra: 20);

        Livewire::test(EmployeeDirectory::class)
            ->set('status', 'inactive')
            ->assertDontSee('Budi Santoso')
            ->call('resetFilters')
            ->assertSee('Budi Santoso')
            ->assertSee('Sebelumnya', false)
            ->assertSee('Berikutnya', false);
    }

    public function test_directory_uses_student_page_pattern(): void
    {
        $blade = file_get_contents(resource_path('views/livewire/employee-directory.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringContainsString('<x-ui.form-section', $blade);
        $this->assertStringContainsString('<x-ui.table', $blade);
        $this->assertStringContainsString('md:hidden', $blade);
        $this->assertStringContainsString('Daftar pegawai', $blade);
        $this->assertStringContainsString("route('employees.edit'", $blade);
        $this->assertStringContainsString('$employees->links()', $blade);
    }

    public function test_employees_page_renders_livewire_directory(): void
    {
        $this->seedEmployees();

        $this->withoutMiddleware()->get(route('employees.index'))
            ->assertOk()
            ->assertSee('Master Pegawai Terpadu', false)
            ->assertSee('Budi Santoso', false);
    }

    private function seedEmployees(int $extra = 0): void
    {
        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');

        Schema::connection('school')->create('fund_sources', function ($table): void {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection('school')->create('fiscal_years', function ($table): void {
            $table->id();
            $table->integer('year');
            $table->string('fund_source')->nullable();
            $table->foreignId('fund_source_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection('school')->create('employees', function ($table): void {
            $table->id();
            $table->string('source_type')->nullable();
            $table->string('name');
            $table->string('nip')->nullable();
            $table->string('nik')->nullable();
            $table->string('nuptk')->nullable();
            $table->string('position')->nullable();
            $table->string('staff_type')->nullable();
            $table->string('employment_status')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_arkas_at')->nullable();
            $table->timestamp('last_seen_dapodik_at')->nullable();
            $table->timestamps();
        });

        $fundSource = FundSource::on('school')->create(['code' => 'BOS', 'name' => 'BOS Reguler']);
        $year = FiscalYear::on('school')->create([
            'year' => 2026,
            'fund_source' => 'BOS Reguler',
            'fund_source_id' => $fundSource->id,
            'is_active' => true,
        ]);

        Employee::query()->create([
            'name' => 'Budi Santoso', 'nip' => '198001012010', 'position' => 'Guru',
            'staff_type' => 'Guru', 'is_active' => true, 'last_seen_arkas_at' => now(),
        ]);
        Employee::query()->create([
            'name' => 'Siti Aminah', 'nuptk' => '123456', 'position' => 'Tendik',
            'staff_type' => 'Tendik', 'is_active' => true, 'last_seen_dapodik_at' => now(),
        ]);
        foreach (range(1, $extra) as $number) {
            Employee::query()->create([
                'name' => sprintf('Pegawai Tambahan %03d', $number), 'is_active' => false,
            ]);
        }

        $this->actingAs(User::factory()->create(['role' => 'ADMIN']))
            ->withSession([
                'active_school_id' => 1,
                'active_fiscal_year_id' => $year->id,
                'active_fund_source_id' => $fundSource->id,
            ]);
    }
}
