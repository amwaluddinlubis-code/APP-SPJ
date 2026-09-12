<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RkasHierarchyTest extends TestCase
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

        $this->seedItem('RAPBS-A', '05.08.01', 'Kegiatan A', '5.1.01', 'Belanja A', 30_000_000, [10_000_000, 10_000_000, 5_000_000, 5_000_000]);
        $this->seedItem('RAPBS-B', '05.08.02', 'Kegiatan B', '5.1.02', 'Belanja B', 20_000_000, [5_000_000, 5_000_000, 5_000_000, 5_000_000]);
        $this->seedItem('RAPBS-C', '03.03.07', 'Kegiatan C', '5.1.03', 'Belanja C', 50_000_000, [25_000_000, 10_000_000, 10_000_000, 5_000_000]);

        DB::connection('school')->table('arkas_bku_rows')->insert([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'source_kas_id' => 'KAS-1',
            'category' => 'BELANJA',
            'no_bukti' => 'BPU-001',
            'transaction_date' => '2026-01-15',
            'amount' => 12_000_000,
            'payload' => json_encode(['ID_RAPBS' => 'RAPBS-A']),
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    public function test_tree_nests_programs_subs_activities_with_bottom_up_subtotals(): void
    {
        $response = $this->get(route('rkas-budget.index'))->assertOk();

        $tree = $response->viewData('hierarchyTree');
        $this->assertSame(['03', '05'], array_column($tree, 'code'));

        $program = $tree[1];
        $this->assertSame('05', $program['code']);
        $this->assertSame('Program', $program['name']);
        $this->assertEqualsWithDelta(50_000_000, $program['amount'], 0.01);
        $this->assertEqualsWithDelta(12_000_000, $program['realization'], 0.01);
        $this->assertEqualsWithDelta(38_000_000, $program['remaining'], 0.01);

        $this->assertSame(['05.08'], array_column($program['subs'], 'code'));
        $sub = $program['subs'][0];
        $this->assertSame('Subprogram', $sub['name']);
        $this->assertEqualsWithDelta(50_000_000, $sub['amount'], 0.01);

        $this->assertSame(['05.08.01', '05.08.02'], array_column($sub['activities'], 'code'));
        $this->assertSame('Kegiatan A', $sub['activities'][0]['name']);
        $this->assertEqualsWithDelta(30_000_000, $sub['activities'][0]['amount'], 0.01);
        $this->assertCount(1, $sub['activities'][0]['items']);
    }

    public function test_tree_totals_match_summary_cards(): void
    {
        $response = $this->get(route('rkas-budget.index'))->assertOk();

        $totals = $response->viewData('treeTotals');
        $this->assertEqualsWithDelta((float) $response->viewData('budget'), (float) $totals['amount'], 0.01);
        $this->assertEqualsWithDelta((float) $response->viewData('spent'), (float) $totals['realization'], 0.01);
        $this->assertEqualsWithDelta((float) $response->viewData('remaining'), (float) $totals['remaining'], 0.01);
        $this->assertSame(3, $totals['items']);
    }

    public function test_program_filter_narrows_tree(): void
    {
        $response = $this->get(route('rkas-budget.index', ['program' => '05']))->assertOk();

        $tree = $response->viewData('hierarchyTree');
        $this->assertSame(['05'], array_column($tree, 'code'));
        $response->assertSee('05.08.01');
        $response->assertDontSee('03.03.07');
    }

    public function test_quarter_scope_uses_tw_amounts_in_tree(): void
    {
        $response = $this->get(route('rkas-budget.index', ['mode' => 'triwulan', 'periode' => 1]))->assertOk();

        $totals = $response->viewData('treeTotals');
        $this->assertEqualsWithDelta(40_000_000, (float) $totals['amount'], 0.01);
        $this->assertEqualsWithDelta((float) $response->viewData('budget'), (float) $totals['amount'], 0.01);
    }

    /**
     * @param  array{0:int,1:int,2:int,3:int}  $quarters
     */
    private function seedItem(
        string $rapbsId,
        string $activityCode,
        string $activityName,
        string $accountCode,
        string $description,
        int $amount,
        array $quarters,
    ): void {
        DB::connection('school')->table('arkas_rkas_items')->insert([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'source_rapbs_id' => $rapbsId,
            'activity_code' => $activityCode,
            'activity_name' => $activityName,
            'account_code' => $accountCode,
            'description' => $description,
            'amount' => $amount,
            'payload' => json_encode([
                'TW_1' => $quarters[0],
                'TW_2' => $quarters[1],
                'TW_3' => $quarters[2],
                'TW_4' => $quarters[3],
                'VOLUME_TOTAL' => 1,
                'SATUAN' => 'paket',
                'HARGA_SATUAN' => $amount,
            ]),
        ]);
    }
}
