<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\Transaction;
use App\Services\SpjDocumentRequirementService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SiplahMarketplaceDocumentPolicyTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    public function test_siplah_goods_use_marketplace_documents_and_do_not_require_internal_order_bap_or_bast(): void
    {
        $transaction = $this->transaction([
            'payment_method' => 'siplah',
            'is_siplah' => true,
            'spj_category' => 'BARANG',
            'vendor_name' => 'Toko SiPLah',
            'vendor_npwp' => '12.345.678.9-012.000',
            'siplah_order_number' => 'SIPL-2026-001',
            'invoice_number' => 'INV-001',
            'payment_reference' => 'PAY-001',
        ]);

        $requirements = collect(app(SpjDocumentRequirementService::class)->forTransaction($transaction));

        $this->assertTrue((bool) $requirements->firstWhere('key', 'siplah_order')['available']);
        $this->assertFalse((bool) $requirements->firstWhere('key', 'internal_order')['applicable']);
        $this->assertFalse((bool) $requirements->firstWhere('key', 'bap')['applicable']);
        $this->assertFalse((bool) $requirements->firstWhere('key', 'bast')['applicable']);

        $goodsReceipt = $requirements->firstWhere('key', 'goods_receipt');
        $this->assertTrue((bool) $goodsReceipt['applicable']);
        $this->assertTrue((bool) $goodsReceipt['required']);
        $this->assertFalse((bool) $goodsReceipt['available']);
    }

    public function test_non_siplah_goods_keep_existing_internal_purchase_order_requirement(): void
    {
        $transaction = $this->transaction([
            'payment_method' => 'tunai',
            'is_siplah' => false,
            'spj_category' => 'BARANG',
        ]);

        $requirements = collect(app(SpjDocumentRequirementService::class)->forTransaction($transaction));
        $internalOrder = $requirements->firstWhere('key', 'internal_order');

        $this->assertTrue((bool) $internalOrder['applicable']);
        $this->assertTrue((bool) $internalOrder['required']);
        $this->assertFalse((bool) $internalOrder['available']);
    }

    public function test_payment_evidence_and_invoice_are_visible_but_do_not_block_print_when_missing(): void
    {
        $transaction = $this->transaction([
            'payment_method' => 'tunai',
            'is_siplah' => false,
            'spj_category' => 'BARANG',
            'vendor_name' => 'Toko Contoh',
            'payment_reference' => null,
            'invoice_number' => null,
        ]);

        $service = app(SpjDocumentRequirementService::class);
        $requirements = collect($service->forTransaction($transaction));

        foreach (['payment_evidence', 'invoice'] as $key) {
            $requirement = $requirements->firstWhere('key', $key);

            $this->assertTrue((bool) $requirement['applicable']);
            $this->assertFalse((bool) $requirement['required']);
            $this->assertFalse((bool) $requirement['available']);
            $this->assertSame('OPSIONAL_BELUM_LENGKAP', $requirement['status']);
        }

        $blockingKeys = collect($service->blockingRequirements($transaction))->pluck('key')->all();
        $this->assertNotContains('payment_evidence', $blockingKeys);
        $this->assertNotContains('invoice', $blockingKeys);
    }

    /** @param array<string, mixed> $overrides */
    private function transaction(array $overrides = []): Transaction
    {
        return Transaction::query()->create(array_merge([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'no_bukti' => 'BKU-001',
            'transaction_date' => '2026-09-05',
            'gross_amount' => 1000,
            'tax_total' => 0,
            'net_amount' => 1000,
        ], $overrides));
    }
}
