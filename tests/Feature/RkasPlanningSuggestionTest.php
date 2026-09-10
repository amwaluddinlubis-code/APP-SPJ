<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RkasPlanningSuggestionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');
        Artisan::call('migrate', ['--database' => 'school', '--path' => 'database/migrations/school', '--force' => true]);
        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        FiscalYear::query()->create(['id' => 1, 'year' => 2025, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
        FiscalYear::query()->create(['id' => 2, 'year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
        $this->actingAs(User::factory()->create());
        $this->withoutMiddleware()->withSession(['active_fiscal_year_id' => 2, 'active_fund_source_id' => 1]);
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    public function test_initial_module_baselines_on_previous_year(): void
    {
        $this->seedItems(1, [
            ['KGT-1', 'Kegiatan Satu', 'RAPBS-1', 10000000],
            ['KGT-2', 'Kegiatan Dua', 'RAPBS-2', 5000000],
        ]);
        $this->seedRealization(1, ['RAPBS-1' => 9000000, 'RAPBS-2' => 0]);

        $response = $this->get(route('rkas-planning.index'))->assertOk();
        $response->assertSee('Modul 1', false);
        $response->assertSee('KGT-1', false);
        // Baseline saran = pagu tahun lalu.
        $response->assertSee('Rp 10.000.000', false);
        $response->assertSee('Serapan baik', false);
        $response->assertSee('Tak terserap', false);
    }

    public function test_remaining_module_flags_statuses(): void
    {
        $this->seedItems(2, [
            ['KGT-A', 'Jalan', 'RAPBS-A', 10000000],
            ['KGT-B', 'Macet', 'RAPBS-B', 10000000],
            ['KGT-C', 'Diam', 'RAPBS-C', 10000000],
        ]);
        $this->seedRealization(2, ['RAPBS-A' => 9000000, 'RAPBS-B' => 2000000]);

        $response = $this->get(route('rkas-planning.index'))->assertOk();
        $response->assertSee('Terserap baik', false);
        $response->assertSee('Tersendat', false);
        $response->assertSee('Belum tersentuh', false);
    }

    public function test_exports_return_spreadsheets(): void
    {
        $this->seedItems(1, [['KGT-1', 'Kegiatan Satu', 'RAPBS-1', 10000000]]);
        $this->seedItems(2, [['KGT-1', 'Kegiatan Satu', 'RAPBS-1', 8000000]]);

        foreach (['pagu-awal', 'sisa-pagu'] as $modul) {
            $response = $this->get(route('rkas-planning.export', $modul))->assertOk();
            $this->assertStringContainsString(
                'spreadsheetml',
                (string) $response->headers->get('Content-Type'),
                "Export {$modul} bukan xlsx."
            );
        }

        $this->get(route('rkas-planning.export', 'sembarang'))->assertNotFound();
    }

    /** @param array<int, array{0:string, 1:string, 2:string, 3:int}> $items */
    private function seedItems(int $yearId, array $items): void
    {
        foreach ($items as [$activityCode, $activityName, $rapbs, $amount]) {
            DB::connection('school')->table('arkas_rkas_items')->insert([
                'fiscal_year_id' => $yearId, 'fund_source_id' => 1,
                'activity_code' => $activityCode, 'activity_name' => $activityName,
                'account_code' => '5.1.02.01', 'description' => $activityName,
                'source_rapbs_id' => $rapbs, 'amount' => $amount, 'payload' => json_encode([]),
            ]);
        }
    }

    /** @param array<string, int> $byRapbs */
    private function seedRealization(int $yearId, array $byRapbs): void
    {
        foreach ($byRapbs as $rapbs => $amount) {
            DB::connection('school')->table('arkas_bku_rows')->insert([
                'fiscal_year_id' => $yearId, 'fund_source_id' => 1, 'category' => 'BELANJA',
                'source_kas_id' => 'KAS-'.$rapbs,
                'amount' => $amount, 'payload' => json_encode(['ID_RAPBS' => $rapbs]),
            ]);
        }
    }
}
