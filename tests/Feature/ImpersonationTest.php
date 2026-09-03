<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_impersonate_operator_and_return(): void
    {
        $school = School::query()->create([
            'npsn' => '10000001',
            'name' => 'SD Uji Impersonate',
        ]);
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $operator = User::factory()->create(['role' => 'OPERATOR', 'school_id' => $school->id]);

        $this->actingAs($admin)
            ->post(route('impersonation.start', $operator->id))
            ->assertRedirect(route('years.select'));

        $this->assertAuthenticatedAs($operator);
        $this->assertSame($admin->id, session('impersonator_user_id'));
        $this->assertSame($operator->id, session('impersonated_user_id'));
        $this->assertSame($school->id, session('active_school_id'));

        $this->post(route('impersonation.stop'))
            ->assertRedirect(route('impersonation.index'));

        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session('impersonator_user_id'));
        $this->assertNull(session('impersonated_user_id'));
    }

    public function test_operator_cannot_open_impersonation_page(): void
    {
        $operator = User::factory()->create(['role' => 'OPERATOR']);

        $this->actingAs($operator)
            ->get(route('impersonation.index'))
            ->assertForbidden();
    }

    public function test_administrator_cannot_impersonate_another_administrator(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $otherAdmin = User::factory()->create(['role' => 'ADMIN']);

        $this->actingAs($admin)
            ->post(route('impersonation.start', $otherAdmin->id))
            ->assertRedirect(route('impersonation.index'))
            ->assertSessionHas('error');

        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session('impersonator_user_id'));
    }
}
