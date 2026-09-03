<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_create_user_with_role_and_school(): void
    {
        $school = School::query()->create([
            'npsn' => '20000001',
            'name' => 'SD User',
        ]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'Operator Satu',
                'email' => 'operator@example.test',
                'school_id' => $school->id,
                'role' => User::ROLE_OPERATOR,
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'operator@example.test',
            'school_id' => $school->id,
            'role' => User::ROLE_OPERATOR,
        ]);
    }

    public function test_administrator_can_update_user_and_change_password_when_filled(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->create(['role' => User::ROLE_OPERATOR]);

        $this->actingAs($admin)
            ->put(route('users.update', $user->id), [
                'name' => 'Viewer Sekolah',
                'email' => 'viewer@example.test',
                'school_id' => null,
                'role' => User::ROLE_VIEWER,
                'password' => 'password456',
                'password_confirmation' => 'password456',
            ])
            ->assertRedirect();

        $user->refresh();

        $this->assertSame('Viewer Sekolah', $user->name);
        $this->assertSame('viewer@example.test', $user->email);
        $this->assertSame(User::ROLE_VIEWER, $user->role);
        $this->assertTrue(Hash::check('password456', $user->password));
    }

    public function test_operator_cannot_manage_users(): void
    {
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);

        $this->actingAs($operator)
            ->get(route('users.index'))
            ->assertForbidden();
    }

    public function test_administrator_cannot_delete_self(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->delete(route('users.destroy', $admin->id))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_last_administrator_cannot_be_demoted(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->put(route('users.update', $admin->id), [
                'name' => $admin->name,
                'email' => $admin->email,
                'school_id' => null,
                'role' => User::ROLE_OPERATOR,
                'password' => null,
                'password_confirmation' => null,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertTrue($admin->fresh()->isAdministrator());
    }
}
