<?php

namespace Tests\Feature;

use App\Livewire\SchoolMaster;
use App\Livewire\UserManagement;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TallProfileMasterTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_profile_component_creates_a_user_reactively(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $school = School::query()->create(['npsn' => '30000001', 'name' => 'SD TALL']);

        Livewire::actingAs($admin)
            ->test(UserManagement::class)
            ->set('name', 'Operator TALL')
            ->set('email', 'operator-tall@example.test')
            ->set('role', User::ROLE_OPERATOR)
            ->set('schoolId', $school->id)
            ->set('password', 'password123')
            ->set('passwordConfirmation', 'password123')
            ->call('createUser')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'operator-tall@example.test',
            'school_id' => $school->id,
            'role' => User::ROLE_OPERATOR,
        ]);
    }

    public function test_profile_and_master_pages_mount_the_livewire_layouts(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        School::query()->create(['npsn' => '30000002', 'name' => 'SD Layout']);

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSeeLivewire('user-management');

        $this->actingAs($admin)
            ->get(route('schools.settings'))
            ->assertOk()
            ->assertSeeLivewire('school-master');

        Livewire::actingAs($admin)
            ->test(SchoolMaster::class)
            ->set('search', 'Layout')
            ->assertSee('SD Layout');
    }

    public function test_settings_views_mount_the_four_tall_components(): void
    {
        $views = [
            resource_path('views/schools/select.blade.php') => '<livewire:school-selector',
            resource_path('views/years/select.blade.php') => '<livewire:year-selector',
            resource_path('views/schools/settings.blade.php') => 'name="document_storage_path"',
            resource_path('views/employees/index.blade.php') => '<livewire:employee-directory',
        ];

        foreach ($views as $view => $component) {
            $this->assertStringContainsString($component, file_get_contents($view));
        }
    }

    public function test_school_master_uses_one_page_header_wrapper(): void
    {
        $view = file_get_contents(resource_path('views/schools/settings.blade.php'));

        $this->assertSame(1, substr_count($view, 'title="Master Sekolah"'));
        $this->assertSame(1, substr_count($view, '<livewire:school-master />'));
    }

    public function test_dapodik_layout_uses_shell_width_and_compact_status_panel(): void
    {
        $view = file_get_contents(resource_path('views/dapodik/index.blade.php'));

        $this->assertStringContainsString('class="w-full space-y-6"', $view);
        $this->assertStringContainsString('xl:grid-cols-2', $view);
        $this->assertStringContainsString('max-w-md break-words', $view);
        $this->assertStringContainsString('title="Integrasi Dapodik"', $view);
    }
}
