<?php

namespace Tests\Feature;

use App\Livewire\RkasBudgetFilter;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class RkasBudgetFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');
        Artisan::call('migrate', [
            '--database' => 'school',
            '--path' => 'database/migrations/school',
            '--force' => true,
        ]);

        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        FiscalYear::query()->create([
            'id' => 1,
            'year' => 2026,
            'fund_source' => 'BOSP',
            'fund_source_id' => 1,
        ]);

        $this->actingAs(User::factory()->create());
        $this->withoutMiddleware()->withSession([
            'active_fiscal_year_id' => 1,
            'active_fund_source_id' => 1,
        ]);

        DB::connection('school')->table('arkas_rkas_items')->insert([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'source_rapbs_id' => 'RAPBS-1',
            'activity_code' => '03.03.07',
            'activity_name' => 'Kegiatan Uji',
            'account_code' => '5.1.02.01',
            'description' => 'Belanja uji',
            'amount' => 100_000_000,
            'payload' => json_encode([
                'TW_1' => 25_000_000,
                'TW_2' => 25_000_000,
                'TW_3' => 25_000_000,
                'TW_4' => 25_000_000,
                'VOLUME_TOTAL' => 4,
                'SATUAN' => 'paket',
                'HARGA_SATUAN' => 25_000_000,
            ]),
        ]);
        DB::connection('school')->table('arkas_rkas_items')->insert([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'source_rapbs_id' => 'RAPBS-2',
            'activity_code' => '05.02.03',
            'activity_name' => 'Kegiatan Buku',
            'account_code' => '5.1.02.02',
            'description' => 'Belanja buku',
            'amount' => 0,
            'payload' => json_encode([]),
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    public function test_mode_alias_maps_to_legacy_quarter_scope(): void
    {
        $this->get(route('rkas-budget.index', [
            'mode' => 'triwulan',
            'periode' => 1,
        ]))->assertOk()
            ->assertViewHas('budget', fn ($value): bool => abs((float) $value - 25_000_000) < 0.01);
    }

    public function test_short_hierarchy_aliases_filter_like_legacy_names(): void
    {
        $this->get(route('rkas-budget.index', [
            'program' => '03',
            'sub' => '03.03',
            'kegiatan' => '03.03.07',
        ]))->assertOk()
            ->assertViewHas('budget', fn ($value): bool => abs((float) $value - 100_000_000) < 0.01);

        $this->get(route('rkas-budget.index', [
            'program' => '99',
        ]))->assertOk()
            ->assertViewHas('budget', fn ($value): bool => abs((float) $value - 100_000_000) < 0.01);
    }

    public function test_filter_component_renders_options_and_navigates_on_change(): void
    {
        $this->withSession([
            'active_fiscal_year_id' => 1,
            'active_fund_source_id' => 1,
        ]);

        Livewire::test(RkasBudgetFilter::class)
            ->assertSee('Filter Penganggaran')
            ->assertSee('Semua Program')
            ->assertSee('03 - Program')
            ->set('mode', 'bulan')
            ->assertRedirect(route('rkas-budget.index', [
                'mode' => 'bulan',
            ]));
    }

    public function test_filter_component_resets_child_state_on_parent_change(): void
    {
        $this->withSession([
            'active_fiscal_year_id' => 1,
            'active_fund_source_id' => 1,
        ]);

        Livewire::test(RkasBudgetFilter::class)
            ->assertSee('03 - Program')
            ->assertSee('05 - Program')
            ->set('kegiatan', '03.03.07')
            ->assertSet('program', '03')
            ->assertSet('sub', '03.03')
            ->set('program', '')
            ->assertSet('sub', '')
            ->assertSet('kegiatan', '');
    }

    public function test_filter_page_cascades_options_within_selected_parent(): void
    {
        $this->get(route('rkas-budget.index', ['program' => '05']))
            ->assertOk()
            ->assertSee('05.02')
            ->assertDontSee('03.03.07');

        $this->get(route('rkas-budget.index', ['program' => '05', 'sub' => '05.02']))
            ->assertOk()
            ->assertSee('05.02.03')
            ->assertDontSee('03.03.07');
    }
}
