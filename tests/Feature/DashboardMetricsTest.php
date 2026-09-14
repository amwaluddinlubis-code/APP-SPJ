<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ProductivityDashboardDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardMetricsTest extends TestCase
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
        FiscalYear::query()->create(['id' => 1, 'year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
        $this->actingAs(User::factory()->create(['role' => 'ADMIN']));
        $this->withoutMiddleware()->withSession(['active_fiscal_year_id' => 1, 'active_fund_source_id' => 1]);
    }

    protected function tearDown(): void
    {
        DB::purge('school');

        parent::tearDown();
    }

    public function test_progress_uses_single_workable_base_without_double_counting(): void
    {
        $withoutPackage = $this->transaction(['no_bukti' => 'BKU-A']);
        $withoutPackage->items()->create($this->item());
        $draft = $this->transaction(['no_bukti' => 'BKU-B']);
        $draft->items()->create($this->item());
        SpjPackage::query()->create(['transaction_id' => $draft->id, 'status' => 'DRAFT']);
        $numbered = $this->transaction(['no_bukti' => 'BKU-C']);
        $numbered->items()->create($this->item());
        SpjPackage::query()->create(['transaction_id' => $numbered->id, 'status' => 'NUMBERED']);
        // Tanpa rincian: bukan unit kerja, tidak masuk basis progres.
        $this->transaction(['no_bukti' => 'BKU-D']);

        $data = app(ProductivityDashboardDataService::class)->getData();

        $this->assertSame(4, $data['summary']['transactions']);
        $this->assertSame(3, $data['summary']['workable']);
        $this->assertSame(1, $data['summary']['without_package']);
        $this->assertSame(1, $data['summary']['draft']);
        $this->assertSame(1, $data['summary']['numbered']);
        // 1 dari 3 transaksi kerja selesai. Rumus lama (transaksi+paket
        // sebagai penyebut) akan menghasilkan angka berbeda.
        $this->assertSame(33, $data['progressPercent']);
        $this->assertSame('1 paket selesai dari 3 transaksi kerja', $data['progressLabel']);
    }

    public function test_attention_count_deduplicates_overlapping_conditions(): void
    {
        $transaction = $this->transaction(['no_bukti' => 'BKU-E']);
        $transaction->items()->create($this->item());
        SpjPackage::query()->create(['transaction_id' => $transaction->id, 'status' => 'DRAFT']);

        $data = app(ProductivityDashboardDataService::class)->getData();

        // Satu transaksi yang sama (berpaket DRAFT) terhitung tepat sekali.
        $this->assertSame(1, $data['attentionCount']);
    }

    public function test_quarter_summary_covers_all_quarters_in_one_shape(): void
    {
        $transaction = $this->transaction(['no_bukti' => 'BKU-F', 'transaction_date' => '2026-02-10']);
        $transaction->items()->create($this->item());
        SpjPackage::query()->create(['transaction_id' => $transaction->id, 'status' => 'READY']);

        $data = app(ProductivityDashboardDataService::class)->getData();

        $this->assertCount(4, $data['quarterSummary']);
        $first = $data['quarterSummary']->firstWhere('quarter', 1);
        $this->assertSame(1, $first['total']);
        $this->assertSame(1, $first['withItems']);
        $this->assertSame(1, $first['ready']);
        $this->assertSame(0, $first['blocked']);
        $this->assertSame(0, $data['quarterSummary']->firstWhere('quarter', 3)['total']);
    }

    public function test_dashboard_page_renders_progress_and_queue_badges(): void
    {
        $transaction = $this->transaction(['no_bukti' => 'BKU-G']);
        $transaction->items()->create($this->item());

        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('Pusat Kerja Operator', $html);
        $this->assertStringContainsString('0%', $html);
        $this->assertStringContainsString('Belum Dikerjakan', $html);
        $this->assertStringContainsString('role="progressbar"', $html);
    }

    public function test_dashboard_data_stays_within_query_budget(): void
    {
        $transaction = $this->transaction(['no_bukti' => 'BKU-H']);
        $transaction->items()->create($this->item());
        SpjPackage::query()->create(['transaction_id' => $transaction->id, 'status' => 'DRAFT']);

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });
        // RefreshDatabase + session setup terjadi di luar listener.
        DB::flushQueryLog();
        $queries = 0;

        app(ProductivityDashboardDataService::class)->getData();

        // Rebuild menargetkan segelintir agregat, bukan puluhan query
        // (ringkasan + triwulan + antrean + validasi per baris).
        $this->assertLessThanOrEqual(12, $queries);
    }

    private function transaction(array $overrides = []): Transaction
    {
        return Transaction::query()->create(array_merge([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'transaction_date' => '2026-02-10',
            'gross_amount' => 1000,
            'net_amount' => 1000,
        ], $overrides));
    }

    /**
     * @return array<string, mixed>
     */
    private function item(): array
    {
        return [
            'description' => 'Kertas',
            'item_description' => 'Kertas HVS',
            'quantity' => 10,
            'unit' => 'rim',
            'unit_price' => 100,
            'amount' => 1000,
        ];
    }
}
