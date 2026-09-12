<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\Transaction;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SpjSettlementAuditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');
        Artisan::call('migrate', ['--database' => 'school', '--path' => 'database/migrations/school', '--force' => true]);
        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        FiscalYear::query()->create(['id' => 1, 'year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
        $this->withoutMiddleware()->withSession(['active_fiscal_year_id' => 1, 'active_fund_source_id' => 1]);
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    public function test_store_payment_records_operational_audit(): void
    {
        $transaction = $this->transaction();

        $this->post(route('spj.payments.store', $transaction->id), [
            'payment_date' => '2026-02-01',
            'gross_amount' => 600,
            'tax_amount' => 60,
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('operational_audit_logs', [
            'fiscal_year_id' => 1,
            'entity_type' => 'TRANSACTION',
            'entity_id' => $transaction->id,
            'action' => 'TAMBAH_PEMBAYARAN',
        ], 'school');
    }

    public function test_store_goods_receipt_records_operational_audit(): void
    {
        $transaction = $this->transaction();
        $itemId = $transaction->items()->first()->id;

        $this->post(route('spj.receipts.store', $transaction->id), [
            'receipt_date' => '2026-01-20',
            'items' => [['transaction_item_id' => $itemId, 'quantity_received' => 6, 'amount_received' => 600]],
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('operational_audit_logs', [
            'fiscal_year_id' => 1,
            'entity_type' => 'TRANSACTION',
            'entity_id' => $transaction->id,
            'action' => 'TAMBAH_PENERIMAAN',
        ], 'school');
    }

    private function transaction(): Transaction
    {
        $transaction = Transaction::query()->create([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'no_bukti' => 'BKU-001',
            'transaction_date' => '2026-01-10',
            'gross_amount' => 1000,
        ]);
        $transaction->items()->create([
            'description' => 'Kertas',
            'item_description' => 'Kertas',
            'quantity' => 10,
            'unit' => 'rim',
            'unit_price' => 100,
            'amount' => 1000,
        ]);

        return $transaction;
    }
}
