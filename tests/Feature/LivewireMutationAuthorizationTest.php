<?php

namespace Tests\Feature;

use App\Livewire\DatabaseMaintenance;
use App\Livewire\DatabaseResetForm;
use App\Livewire\DatabaseSchoolList;
use App\Livewire\DocumentStorageSettings;
use App\Livewire\SchoolMaster;
use App\Livewire\UserManagement;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

class LivewireMutationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_and_viewer_cannot_invoke_administrator_livewire_mutations(): void
    {
        $schoolCountBeforeMutationAttempts = School::query()->count();
        $school = School::query()->create([
            'school_code' => 'AUTH-001',
            'npsn' => '40000001',
            'name' => 'SD Authorization',
        ]);
        $targetAdmin = User::factory()->create([
            'name' => 'Target Administrator',
            'email' => 'target-admin@example.test',
            'role' => User::ROLE_ADMIN,
        ]);
        $victim = User::factory()->create([
            'name' => 'Protected Operator',
            'email' => 'protected-operator@example.test',
            'role' => User::ROLE_OPERATOR,
            'school_id' => $school->id,
        ]);

        foreach ([User::ROLE_OPERATOR, User::ROLE_VIEWER] as $role) {
            $actor = User::factory()->create([
                'role' => $role,
                'school_id' => $school->id,
            ]);

            Livewire::actingAs($actor)
                ->test(UserManagement::class)
                ->call('createUser')
                ->assertStatus(403);

            Livewire::actingAs($actor)
                ->test(UserManagement::class)
                ->call(
                    'updateUser',
                    $targetAdmin->id,
                    'Tampered Administrator',
                    'tampered-admin@example.test',
                    User::ROLE_ADMIN,
                )
                ->assertStatus(403);

            Livewire::actingAs($actor)
                ->test(UserManagement::class)
                ->call('deleteUser', $victim->id)
                ->assertStatus(403);

            Livewire::actingAs($actor)
                ->test(SchoolMaster::class)
                ->call('createSchool')
                ->assertStatus(403);

            Livewire::actingAs($actor)
                ->test(DatabaseMaintenance::class)
                ->call('run', 'checkpoint')
                ->assertStatus(403);

            Livewire::actingAs($actor)
                ->test(DatabaseResetForm::class, [
                    'active' => ['school' => $school, 'database' => '—'],
                ])
                ->call('resetDatabase')
                ->assertStatus(403);

            Livewire::actingAs($actor)
                ->test(DatabaseSchoolList::class)
                ->call('activate', $school->id)
                ->assertStatus(403);

            Livewire::actingAs($actor)
                ->test(DatabaseSchoolList::class)
                ->call('migrate', $school->id)
                ->assertStatus(403);
        }

        $this->assertDatabaseHas('users', [
            'id' => $targetAdmin->id,
            'name' => 'Target Administrator',
            'email' => 'target-admin@example.test',
            'role' => User::ROLE_ADMIN,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $victim->id,
            'email' => 'protected-operator@example.test',
        ]);
        $this->assertDatabaseCount('schools', $schoolCountBeforeMutationAttempts + 1);
    }

    public function test_document_storage_livewire_mutation_allows_operator_and_rejects_viewer(): void
    {
        $directory = storage_path('framework/testing/livewire-storage-'.uniqid());
        File::ensureDirectoryExists($directory);

        try {
            $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);

            Livewire::actingAs($operator)
                ->test(DocumentStorageSettings::class)
                ->set('path', $directory)
                ->call('save')
                ->assertHasNoErrors();

            $this->assertDatabaseHas('app_settings', [
                'key' => 'document_storage_path',
                'value' => $directory,
            ]);

            $viewer = User::factory()->create(['role' => User::ROLE_VIEWER]);

            Livewire::actingAs($viewer)
                ->test(DocumentStorageSettings::class)
                ->set('path', $directory)
                ->call('save')
                ->assertStatus(403);
        } finally {
            File::deleteDirectory($directory);
        }
    }
}
