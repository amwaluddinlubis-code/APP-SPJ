<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\SpjPackage;
use App\Models\Transaction;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SpjMaintenanceLinkWorkspaceTest extends TestCase
{
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
        FiscalYear::query()->create(['id' => 1, 'year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);

        $this->withoutMiddleware()->withSession([
            'active_fiscal_year_id' => 1,
            'active_fund_source_id' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    public function test_maintenance_partial_exposes_only_link_endpoints_for_the_compact_category_selector(): void
    {
        $transaction = $this->transaction('Pemeliharaan ruang kelas');
        $package = $transaction->spjPackage()->create([
            'status' => 'DRAFT',
            'quarter_code' => 'TW-1',
            'semester_code' => 'SEM-I',
        ]);
        $transaction->load(['workers', 'workOrder']);

        $html = view('spj.partials.package.categories.pemeliharaan', [
            'transaction' => $transaction,
            'package' => $package,
            'selectedSpjType' => 'PEMELIHARAAN',
            'workerRows' => [],
            'workDetails' => null,
            'transactionDateLimit' => '2026-01-15',
        ])->render();

        $this->assertStringContainsString('data-spj-maintenance-links', $html);
        $this->assertStringContainsString(route('transactions.maintenance-links.show', $transaction->id), $html);
        $this->assertStringContainsString(route('transactions.maintenance-links.update', $transaction->id), $html);
        $this->assertStringNotContainsString('data-maintenance-link-select', $html);
        $this->assertStringNotContainsString('Transaksi bahan / barang terkait', $html);
        $this->assertStringNotContainsString('Transaksi upah terkait', $html);
        $this->assertStringNotContainsString('name="maintenance_material_transaction_id"', $html);
        $this->assertStringNotContainsString('name="maintenance_labor_transaction_id"', $html);
    }

    public function test_maintenance_link_endpoints_persist_and_return_material_and_labor_transactions(): void
    {
        $transaction = $this->transaction('Pemeliharaan ruang kelas');
        $material = $this->transaction('Belanja bahan cat', 'BPU-MATERIAL', '2026-01-16');
        $labor = $this->transaction('Upah tukang pengecatan', 'BPU-LABOR', '2026-01-16');

        $this->putJson(route('transactions.maintenance-links.update', $transaction->id), [
            'material_transaction_id' => $material->id,
            'labor_transaction_id' => $labor->id,
        ])->assertOk()
            ->assertJsonPath('message', 'Transaksi terkait pemeliharaan berhasil disimpan.');

        $transaction->refresh();
        $this->assertSame($material->id, $transaction->maintenance_material_transaction_id);
        $this->assertSame($labor->id, $transaction->maintenance_labor_transaction_id);

        $this->getJson(route('transactions.maintenance-links.show', $transaction->id))
            ->assertOk()
            ->assertJsonPath('selected.material_transaction_id', $material->id)
            ->assertJsonPath('selected.labor_transaction_id', $labor->id);
    }

    private function transaction(
        string $paymentDescription,
        ?string $noBukti = null,
        string $transactionDate = '2026-01-15',
    ): Transaction {
        return Transaction::query()->create([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'no_bukti' => $noBukti ?: 'BPU-'.(Transaction::query()->count() + 1),
            'transaction_date' => $transactionDate,
            'description' => $paymentDescription,
            'payment_description' => $paymentDescription,
            'gross_amount' => 1000,
            'tax_total' => 0,
            'net_amount' => 1000,
            'spj_category' => 'PEMELIHARAAN',
            'payment_method' => 'tunai',
            'source_status' => 'ACTIVE',
            'requires_reconciliation' => false,
        ]);
    }
}
