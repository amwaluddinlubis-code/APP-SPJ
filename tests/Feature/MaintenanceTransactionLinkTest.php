<?php

namespace Tests\Feature;

use App\Http\Controllers\MaintenanceTransactionLinkController;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MaintenanceTransactionLinkTest extends TestCase
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

    public function test_candidates_use_proof_number_and_payment_description_and_respect_date_boundary(): void
    {
        $maintenance = $this->transaction('BPU-010', '2026-03-10', 'Pemeliharaan ruang kelas');
        $eligible = $this->transaction('BPU-014', '2026-03-12', 'Pembelian cat dan kuas');
        $this->transaction('BPU-009', '2026-03-09', 'Pembelian semen lebih awal');
        $this->transaction('BPU-015', '2026-03-13', null);

        $response = app(MaintenanceTransactionLinkController::class)->show((string) $maintenance->id);
        $payload = $response->getData(true);

        $this->assertSame('unknown', $payload['current_role']);
        $this->assertSame([
            ['id' => $eligible->id, 'label' => 'BPU-014 - Pembelian cat dan kuas'],
        ], $payload['candidates']);
    }

    public function test_labor_transaction_only_links_to_material_transaction(): void
    {
        $labor = $this->transaction('BPU-010', '2026-03-10', 'Upah tukang pemeliharaan ruang kelas');
        $material = $this->transaction('BPU-014', '2026-03-12', 'Pembelian cat dan kuas');

        $payload = app(MaintenanceTransactionLinkController::class)
            ->show((string) $labor->id)
            ->getData(true);

        $this->assertSame('labor', $payload['current_role']);

        $request = Request::create('/transaksi/'.$labor->id.'/pemeliharaan/transaksi-terkait', 'PUT', [
            'material_transaction_id' => $material->id,
            'labor_transaction_id' => null,
        ]);

        app(MaintenanceTransactionLinkController::class)->update($request, (string) $labor->id);

        $labor->refresh();
        $this->assertSame($material->id, $labor->maintenance_material_transaction_id);
        $this->assertNull($labor->maintenance_labor_transaction_id);
    }

    public function test_material_transaction_only_links_to_labor_transaction(): void
    {
        $material = $this->transaction('BPU-010', '2026-03-10', 'Pembelian bahan pemeliharaan ruang kelas');
        $labor = $this->transaction('BPU-018', '2026-03-15', 'Upah tukang 3 hari');

        $payload = app(MaintenanceTransactionLinkController::class)
            ->show((string) $material->id)
            ->getData(true);

        $this->assertSame('material', $payload['current_role']);

        $request = Request::create('/transaksi/'.$material->id.'/pemeliharaan/transaksi-terkait', 'PUT', [
            'material_transaction_id' => null,
            'labor_transaction_id' => $labor->id,
        ]);

        app(MaintenanceTransactionLinkController::class)->update($request, (string) $material->id);

        $material->refresh();
        $this->assertNull($material->maintenance_material_transaction_id);
        $this->assertSame($labor->id, $material->maintenance_labor_transaction_id);
    }

    private function transaction(string $proofNumber, string $date, ?string $paymentDescription): Transaction
    {
        return Transaction::query()->create([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'no_bukti' => $proofNumber,
            'transaction_date' => $date,
            'payment_description' => $paymentDescription,
            'gross_amount' => 100000,
            'net_amount' => 100000,
            'source_status' => 'ACTIVE',
            'requires_reconciliation' => false,
        ]);
    }
}
