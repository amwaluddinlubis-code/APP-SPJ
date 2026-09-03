<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\School;
use App\Models\User;
use App\Services\SchoolDatabaseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class SchoolYearSelectionFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        DB::purge('school');

        parent::tearDown();
    }

    public function test_selecting_school_always_continues_to_year_selection(): void
    {
        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');

        Schema::connection('school')->create('arkas_rkas_items', function ($table): void {
            $table->id();
            $table->foreignId('fund_source_id')->nullable();
        });

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

        $school = School::create(['npsn' => '12345678', 'name' => 'SD Uji']);
        $user = User::factory()->create(['school_id' => $school->id, 'role' => 'ADMIN']);

        app()->instance(SchoolDatabaseManager::class, Mockery::mock(SchoolDatabaseManager::class, function ($mock) use ($school): void {
            $mock->shouldReceive('activate')->once()->with(Mockery::on(fn ($argument) => $argument->is($school)));
            $mock->shouldReceive('migrate')->never();
        }));

        FundSource::on('school')->create(['code' => 'BOS', 'name' => 'BOS Reguler']);
        FiscalYear::on('school')->create(['year' => 2026, 'fund_source' => 'BOS Reguler', 'fund_source_id' => 1, 'is_active' => true]);

        $this->actingAs($user)
            ->post(route('schools.activate'), ['school_id' => $school->id])
            ->assertRedirect(route('years.select'))
            ->assertSessionHas('active_school_id', $school->id)
            ->assertSessionMissing('active_fiscal_year_id')
            ->assertSessionMissing('active_fund_source_id');
    }
}
