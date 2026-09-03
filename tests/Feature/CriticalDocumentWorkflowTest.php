<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\Transaction;
use App\Services\FiscalPeriodWorkflowService;
use App\Services\SpjPackageValidationService;
use App\Services\TransactionSettlementService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CriticalDocumentWorkflowTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    public function test_partial_payments_cannot_exceed_transaction_value(): void
    {
        $service = app(TransactionSettlementService::class);
        $first = $service->addPayment($this->transaction(), ['payment_date' => '2026-02-01', 'gross_amount' => 600, 'tax_amount' => 60]);
        $this->assertSame('540.00', $first->net_amount);
        $this->assertSame('PAYMENT:1', $first->scope_key);

        $this->expectException(\RuntimeException::class);
        $service->addPayment($first->transaction, ['payment_date' => '2026-02-02', 'gross_amount' => 500, 'tax_amount' => 0]);
    }

    public function test_partial_receipts_cannot_exceed_ordered_quantity(): void
    {
        $transaction = $this->transaction();
        $item = $transaction->items()->create(['description' => 'Kertas', 'item_description' => 'Kertas', 'quantity' => 10, 'unit' => 'rim', 'unit_price' => 100, 'amount' => 1000]);
        $service = app(TransactionSettlementService::class);
        $receipt = $service->addGoodsReceipt($transaction, ['receipt_date' => '2026-01-20'], [['transaction_item_id' => $item->id, 'quantity_received' => 6, 'amount_received' => 600]]);
        $this->assertSame('RECEIPT:1', $receipt->scope_key);
        $this->assertCount(1, $receipt->items);

        $this->expectException(\RuntimeException::class);
        $service->addGoodsReceipt($transaction, ['receipt_date' => '2026-01-21'], [['transaction_item_id' => $item->id, 'quantity_received' => 5, 'amount_received' => 500]]);
    }

    public function test_entry_after_quarter_numbering_is_marked_late(): void
    {
        $periods = app(FiscalPeriodWorkflowService::class);
        $period = $periods->period(1, 1);
        $periods->markNumbered($period, 1);
        $payment = app(TransactionSettlementService::class)->addPayment($this->transaction(), ['payment_date' => '2026-02-01', 'gross_amount' => 1000, 'tax_amount' => 0]);
        $this->assertTrue($payment->is_late_entry);
        $this->assertSame('NUMBERED', $period->fresh()->status);
    }

    public function test_inconsistent_honor_total_blocks_package_numbering_validation(): void
    {
        $transaction = $this->transaction();
        $transaction->forceFill([
            'spj_category' => 'HONOR_PEGAWAI',
            'recipient_name' => 'Bendahara',
            'activity_code' => '01', 'activity_name' => 'Kegiatan',
            'account_code' => '5.1', 'account_name' => 'Honor',
            'payment_method' => 'tunai',
        ])->save();
        $item = $transaction->items()->create([
            'description' => 'Honor', 'item_description' => 'Honor',
            'quantity' => 1, 'unit' => 'paket', 'unit_price' => 1000, 'amount' => 1000,
        ]);
        $item->honors()->create([
            'name' => 'Pegawai A', 'honor_months' => 1, 'rate_per_unit' => 750,
            'gross_amount' => 750, 'tax_rate' => 0, 'tax_amount' => 0, 'net_amount' => 750,
        ]);
        $package = $transaction->spjPackage()->create(['quarter_code' => 'TW1', 'semester_code' => 'S1', 'status' => 'READY']);

        $issues = app(SpjPackageValidationService::class)->validate($package->load(['transaction.items', 'transaction.goods', 'transaction.honors']));

        $this->assertContains('Total honor', collect($issues)->pluck('label')->all());
    }

    public function test_document_dates_after_transaction_date_are_rejected(): void
    {
        $transaction = $this->transaction();
        $transaction->items()->create([
            'description' => 'Kertas',
            'item_description' => 'Kertas',
            'quantity' => 1,
            'unit' => 'rim',
            'unit_price' => 1000,
            'amount' => 1000,
        ]);

        $response = $this->withoutMiddleware()
            ->withSession(['active_fiscal_year_id' => 1, 'active_fund_source_id' => 1])
            ->from('/transaksi/'.$transaction->id.'#modul-buat-spj')
            ->post(route('spj.prepare', $transaction->id), [
                'spj_category' => 'BARANG',
                'payment_description' => 'Pembelian kertas',
                'payment_method' => 'tunai',
                'receipt_recipient_name' => 'Toko Kertas',
                'order_date' => '2026-01-16',
                'workers' => [[
                    'name' => '',
                    'job_description' => '',
                    'work_days' => 1,
                    'daily_rate' => 0,
                ]],
            ]);

        $response->assertRedirect('/transaksi/'.$transaction->id.'#modul-buat-spj');
        $response->assertSessionHasErrors('order_date');
        $response->assertSessionDoesntHaveErrors('workers.0.daily_rate');
        $this->assertDatabaseMissing('spj_goods', ['order_date' => '2026-01-16'], 'school');
    }

    public function test_goods_document_dates_must_follow_business_sequence(): void
    {
        $transaction = $this->transaction();
        $transaction->items()->create([
            'description' => 'Kertas',
            'item_description' => 'Kertas',
            'quantity' => 1,
            'unit' => 'rim',
            'unit_price' => 1000,
            'amount' => 1000,
        ]);

        $response = $this->withoutMiddleware()
            ->withSession(['active_fiscal_year_id' => 1, 'active_fund_source_id' => 1])
            ->post(route('spj.prepare', $transaction->id), [
                'spj_category' => 'BARANG',
                'payment_description' => 'Pembelian kertas',
                'payment_method' => 'tunai',
                'receipt_recipient_name' => 'Toko Kertas',
                'order_date' => '2026-01-10',
                'bap_date' => '2026-01-09',
                'bast_date' => '2026-01-08',
                'invoice_date' => '2026-01-07',
            ]);

        $response->assertSessionHasErrors(['bap_date', 'bast_date', 'invoice_date']);
    }

    public function test_inconsistent_goods_item_value_blocks_package_numbering(): void
    {
        $transaction = $this->readyGoodsTransaction();
        $item = $transaction->items()->create([
            'description' => 'Kertas', 'item_description' => 'Kertas',
            'quantity' => 2, 'unit' => 'rim', 'unit_price' => 300, 'amount' => 1000,
        ]);
        $item->goods()->create([
            'order_date' => '2026-01-10', 'bap_date' => '2026-01-11', 'bast_date' => '2026-01-12',
        ]);
        $package = $transaction->spjPackage()->create(['quarter_code' => 'TW1', 'semester_code' => 'S1', 'status' => 'READY']);

        $issues = app(SpjPackageValidationService::class)->validate($package->load(['transaction.items', 'transaction.goods']));
        $labels = collect($issues)->pluck('label')->all();

        $this->assertContains('Nilai item barang', $labels);
        $this->assertContains('Kelengkapan BAP/BAST', $labels);
    }

    public function test_duplicate_vendor_invoice_in_same_fiscal_year_blocks_numbering(): void
    {
        Transaction::query()->create([
            'fiscal_year_id' => 1, 'fund_source_id' => 1, 'no_bukti' => 'BKU-000',
            'transaction_date' => '2026-01-10', 'gross_amount' => 500, 'net_amount' => 500,
            'vendor_name' => 'Toko Maju', 'invoice_number' => 'INV-001',
        ]);
        $transaction = $this->readyGoodsTransaction();
        $transaction->forceFill(['vendor_name' => 'toko maju', 'invoice_number' => 'inv-001'])->save();
        $item = $transaction->items()->create([
            'description' => 'Kertas', 'item_description' => 'Kertas',
            'quantity' => 1, 'unit' => 'rim', 'unit_price' => 1000, 'amount' => 1000,
        ]);
        $item->goods()->create(['order_date' => '2026-01-10']);
        $package = $transaction->spjPackage()->create(['quarter_code' => 'TW1', 'semester_code' => 'S1', 'status' => 'READY']);

        $issues = app(SpjPackageValidationService::class)->validate($package->load(['transaction.items', 'transaction.goods']));

        $this->assertContains('Duplikasi invoice', collect($issues)->pluck('label')->all());
    }

    private function readyGoodsTransaction(): Transaction
    {
        $transaction = $this->transaction();
        $transaction->forceFill([
            'spj_category' => 'BARANG', 'recipient_name' => 'Toko',
            'activity_code' => '01', 'activity_name' => 'Kegiatan',
            'account_code' => '5.1', 'account_name' => 'Barang', 'payment_method' => 'tunai',
        ])->save();

        return $transaction;
    }

    private function transaction(): Transaction
    {
        return Transaction::query()->create([
            'fiscal_year_id' => 1, 'fund_source_id' => 1, 'no_bukti' => 'BKU-001',
            'transaction_date' => '2026-01-15', 'gross_amount' => 1000, 'net_amount' => 1000,
        ]);
    }
}
