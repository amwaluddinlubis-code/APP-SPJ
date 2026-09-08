<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use App\Services\SchoolDatabaseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Mockery\MockInterface;
use Tests\TestCase;

class SpjSchoolIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'active-school'])
            ->get('/_test/spj-school-isolation', fn () => response('OK', 200))
            ->name('test.spj-school-isolation');
    }

    public function test_operator_cannot_use_another_school_from_forged_active_school_session(): void
    {
        $ownSchool = School::query()->create(['npsn' => '11111111', 'name' => 'Sekolah Operator']);
        $otherSchool = School::query()->create(['npsn' => '22222222', 'name' => 'Sekolah Lain']);
        $operator = User::factory()->create([
            'school_id' => $ownSchool->id,
            'role' => User::ROLE_OPERATOR,
        ]);

        $this->mock(SchoolDatabaseManager::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('ensureMigrated');
        });

        $response = $this->actingAs($operator)
            ->withSession([
                'active_school_id' => $otherSchool->id,
                'active_fiscal_year_id' => 99,
                'active_fund_source_id' => 88,
            ])
            ->get('/_test/spj-school-isolation');

        $response->assertRedirect(route('schools.select'));
        $response->assertSessionMissing('active_school_id');
        $response->assertSessionMissing('active_fiscal_year_id');
        $response->assertSessionMissing('active_fund_source_id');
    }

    public function test_operator_can_only_continue_with_own_school(): void
    {
        $school = School::query()->create(['npsn' => '33333333', 'name' => 'Sekolah Operator']);
        $operator = User::factory()->create([
            'school_id' => $school->id,
            'role' => User::ROLE_OPERATOR,
        ]);

        $this->mock(SchoolDatabaseManager::class, function (MockInterface $mock) use ($school): void {
            $mock->shouldReceive('ensureMigrated')->once()->withArgs(fn (School $resolved): bool => $resolved->is($school));
        });

        $this->actingAs($operator)
            ->withSession(['active_school_id' => $school->id])
            ->get('/_test/spj-school-isolation')
            ->assertOk()
            ->assertSee('OK');
    }

    public function test_administrator_can_manage_a_selected_school_without_cross_school_restriction(): void
    {
        $school = School::query()->create(['npsn' => '44444444', 'name' => 'Sekolah Dikelola']);
        $administrator = User::factory()->create([
            'school_id' => null,
            'role' => User::ROLE_ADMIN,
        ]);

        $this->mock(SchoolDatabaseManager::class, function (MockInterface $mock) use ($school): void {
            $mock->shouldReceive('ensureMigrated')->once()->withArgs(fn (School $resolved): bool => $resolved->is($school));
        });

        $this->actingAs($administrator)
            ->withSession(['active_school_id' => $school->id])
            ->get('/_test/spj-school-isolation')
            ->assertOk()
            ->assertSee('OK');
    }
}
