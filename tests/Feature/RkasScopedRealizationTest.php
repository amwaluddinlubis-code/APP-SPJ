<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RkasScopedRealizationTest extends TestCase
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

        $this->seedRkasItem();
        $this->seedPeriod('PERIOD-JAN', 'RAPBS-PERIOD-JAN', 1, 1, 1, 8_000_000);
        $this->seedPeriod('PERIOD-APR', 'RAPBS-PERIOD-APR', 4, 2, 1, 12_000_000);
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    public function test_quarter_realization_uses_only_bku_rows_linked_to_selected_period(): void
    {
        $this->seedBku('KAS-Q1', 'BPU-001', '2026-01-15', 10_000_000, 'RAPBS-PERIOD-JAN');
        $this->seedBku('KAS-Q2', 'BPU-002', '2026-04-15', 30_000_000, 'RAPBS-PERIOD-APR');

        $quarter = $this->get(route('rkas-budget.index', [
            'scope' => 'quarter',
            'scope_value' => 1,
        ]))->assertOk();

        $quarter->assertViewHas('budget', fn ($value): bool => abs((float) $value - 25_000_000) < 0.01);
        $quarter->assertViewHas('spent', fn ($value): bool => abs((float) $value - 10_000_000) < 0.01);
        $quarter->assertViewHas('remaining', fn ($value): bool => abs((float) $value - 15_000_000) < 0.01);

        $year = $this->get(route('rkas-budget.index'))->assertOk();
        $year->assertViewHas('budget', fn ($value): bool => abs((float) $value - 100_000_000) < 0.01);
        $year->assertViewHas('spent', fn ($value): bool => abs((float) $value - 40_000_000) < 0.01);
        $year->assertViewHas('remaining', fn ($value): bool => abs((float) $value - 60_000_000) < 0.01);
    }

    public function test_scoped_realization_falls_back_to_transaction_date_for_legacy_bku_rows(): void
    {
        $this->seedBku('KAS-LEGACY-Q1', 'BPU-003', '2026-02-10', 6_000_000, null);
        $this->seedBku('KAS-LEGACY-Q2', 'BPU-004', '2026-05-10', 14_000_000, null);

        $quarter = $this->get(route('rkas-budget.index', [
            'scope' => 'quarter',
            'scope_value' => 1,
        ]))->assertOk();

        $quarter->assertViewHas('spent', fn ($value): bool => abs((float) $value - 6_000_000) < 0.01);
        $quarter->assertViewHas('remaining', fn ($value): bool => abs((float) $value - 19_000_000) < 0.01);
    }

    private function seedRkasItem(): void
    {
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
                'VOL_TW1' => 1,
                'VOL_TW2' => 1,
                'VOL_TW3' => 1,
                'VOL_TW4' => 1,
                'VOLUME_TOTAL' => 4,
                'SATUAN' => 'paket',
                'HARGA_SATUAN' => 25_000_000,
            ]),
        ]);
    }

    private function seedPeriod(
        string $sourcePeriodId,
        string $sourceRapbsPeriodId,
        int $month,
        int $quarter,
        int $semester,
        int $amount,
    ): void {
        DB::connection('school')->table('arkas_rkas_periods')->insert([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'source_rapbs_id' => 'RAPBS-1',
            'source_period_id' => $sourcePeriodId,
            'source_rapbs_period_id' => $sourceRapbsPeriodId,
            'period_name' => 'Bulan '.$month,
            'month_number' => $month,
            'quarter_number' => $quarter,
            'semester_number' => $semester,
            'volume' => 1,
            'amount' => $amount,
            'payload' => json_encode([]),
        ]);
    }

    private function seedBku(
        string $sourceKasId,
        string $proofNumber,
        string $date,
        int $amount,
        ?string $sourceRapbsPeriodId,
    ): void {
        DB::connection('school')->table('arkas_bku_rows')->insert([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'source_kas_id' => $sourceKasId,
            'source_rapbs_period_id' => $sourceRapbsPeriodId,
            'category' => 'BELANJA',
            'no_bukti' => $proofNumber,
            'transaction_date' => $date,
            'amount' => $amount,
            'payload' => json_encode(['ID_RAPBS' => 'RAPBS-1']),
        ]);
    }
}
