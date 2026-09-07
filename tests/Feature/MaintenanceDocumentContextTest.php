<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Services\SpjMaintenanceDocumentContextService;
use App\Services\SpjTransactionDetailsService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MaintenanceDocumentContextTest extends TestCase
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

        session([
            'active_fiscal_year_id' => 1,
            'active_fund_source_id' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    public function test_labor_package_uses_linked_material_items_and_current_labor_workers_for_documents(): void
    {
        $material = $this->transaction('BPU-014', '2026-03-10', 'Pembelian cat dan kuas', 'BARANG', 250000);
        $materialItem = $material->items()->create([
            'description' => 'Cat tembok',
            'item_description' => 'Cat tembok',
            'quantity' => 2,
            'unit' => 'kaleng',
            'unit_price' => 125000,
            'amount' => 250000,
        ]);

        $labor = $this->transaction('BPU-018', '2026-03-12', 'Upah tukang pengecatan', 'PEMELIHARAAN', 300000);
        $labor->items()->create([
            'description' => 'Upah tukang',
            'item_description' => 'Upah tukang',
            'quantity' => 1,
            'unit' => 'paket',
            'unit_price' => 300000,
            'amount' => 300000,
        ]);
        $labor->forceFill(['maintenance_material_transaction_id' => $material->id])->save();
        $labor->load('items');

        app(SpjTransactionDetailsService::class)->synchronize($labor, [
            'work_description' => 'Pengecatan ruang kelas',
            'work_location' => 'Ruang kelas IV',
            'workers' => [[
                'name' => 'Tukang A',
                'job_description' => 'Pengecatan',
                'work_days' => 2,
                'daily_rate' => 150000,
                'is_receipt_recipient' => true,
            ]],
        ]);

        $package = $labor->spjPackage()->create([
            'quarter_code' => 'TW-1',
            'semester_code' => 'SEM-I',
            'status' => 'DRAFT',
        ]);
        $package = SpjPackage::query()->with('transaction')->findOrFail($package->id);

        app(SpjMaintenanceDocumentContextService::class)->apply($package);

        $this->assertSame([$materialItem->id], $package->transaction->items->pluck('id')->all());
        $this->assertSame(['Tukang A'], $package->transaction->workers->pluck('name')->all());
        $this->assertSame($material->id, $package->transaction->maintenance_material_source_id);
        $this->assertSame($labor->id, $package->transaction->maintenance_labor_source_id);
        $this->assertSame(550000.0, $package->transaction->maintenance_combined_amount);
    }

    private function transaction(string $proofNumber, string $date, string $description, string $category, float $gross): Transaction
    {
        return Transaction::query()->create([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'no_bukti' => $proofNumber,
            'transaction_date' => $date,
            'payment_description' => $description,
            'spj_category' => $category,
            'gross_amount' => $gross,
            'net_amount' => $gross,
            'source_status' => 'ACTIVE',
            'requires_reconciliation' => false,
        ]);
    }
}
