<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\School;
use App\Models\Transaction;
use App\Services\SpjDocumentRequirementService;
use App\Services\SpjPackageValidationService;
use App\Services\SpjProcurementPolicyService;
use App\Services\SpjTemplateService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SiplahPurchaseMvpTest extends TestCase
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

    public function test_siplah_purchase_metadata_is_persisted_without_changing_spj_category(): void
    {
        $transaction = $this->transaction();
        $response = $this->withoutMiddleware()
            ->withSession(['active_fiscal_year_id' => 1, 'active_fund_source_id' => 1])
            ->put(route('transactions.manual-description.update', $transaction->id), [
                'spj_category' => 'BARANG', 'payment_method' => 'siplah',
                'vendor_name' => 'Toko SiPLah Nusantara', 'vendor_owner' => 'Budi Santoso',
                'vendor_npwp' => 'NPWP-UJI', 'siplah_order_number' => 'SIPL-2026-12345',
                'invoice_number' => 'INV-88231', 'invoice_date' => '2026-01-14',
                'invoice_status' => 'LUNAS', 'payment_reference' => 'PAY-7788',
            ]);

        $response->assertSessionHasNoErrors();
        $transaction->refresh();
        $this->assertSame('siplah', $transaction->payment_method);
        $this->assertSame('BARANG', $transaction->spj_category);
        $this->assertNotSame('SIPLAH', $transaction->spj_category);
        $this->assertSame('Toko SiPLah Nusantara', $transaction->vendor_name);
        $this->assertSame('Budi Santoso', $transaction->vendor_owner);
        $this->assertSame('NPWP-UJI', $transaction->vendor_npwp);
        $this->assertSame('SIPL-2026-12345', $transaction->siplah_order_number);
        $this->assertSame('INV-88231', $transaction->invoice_number);
        $this->assertSame('2026-01-14', $transaction->invoice_date?->format('Y-m-d'));
        $this->assertSame('LUNAS', $transaction->invoice_status);
        $this->assertSame('PAY-7788', $transaction->payment_reference);
    }

    public function test_siplah_is_identified_by_payment_method_or_source_flag(): void
    {
        $policy = app(SpjProcurementPolicyService::class);

        $this->assertTrue($policy->isSiplah(new Transaction(['payment_method' => 'SiPLah'])));
        $this->assertTrue($policy->isSiplah(new Transaction(['payment_method' => 'tunai', 'is_siplah' => true])));
        $this->assertFalse($policy->isSiplah(new Transaction(['payment_method' => 'tunai', 'is_siplah' => false])));
    }

    public function test_preparing_siplah_purchase_creates_normal_spj_package_and_persists_marketplace_order(): void
    {
        $transaction = $this->transaction();
        $transaction->items()->create([
            'description' => 'Kertas A4', 'item_description' => 'Kertas A4', 'quantity' => 1,
            'unit' => 'rim', 'unit_price' => 1000, 'amount' => 1000,
        ]);
        $response = $this->withoutMiddleware()
            ->withSession(['active_fiscal_year_id' => 1, 'active_fund_source_id' => 1])
            ->post(route('spj.prepare', $transaction->id), [
                'spj_category' => 'BARANG', 'payment_description' => 'Pembelian kertas melalui SiPLah',
                'payment_method' => 'siplah', 'receipt_recipient_name' => 'Toko SiPLah Nusantara',
                'vendor_name' => 'Toko SiPLah Nusantara', 'siplah_order_number' => 'SIPL-2026-12345',
                'payment_reference' => 'PAY-7788', 'invoice_number' => 'INV-88231', 'invoice_date' => '2026-01-14',
            ]);

        $response->assertSessionHasNoErrors();
        $transaction->refresh()->load('spjPackage');
        $this->assertSame('BARANG', $transaction->spj_category);
        $this->assertSame('SIPL-2026-12345', $transaction->siplah_order_number);
        $this->assertNotNull($transaction->spjPackage);
        $this->assertSame('DRAFT', $transaction->spjPackage->status);

        $updateResponse = $this->withoutMiddleware()
            ->withSession(['active_fiscal_year_id' => 1, 'active_fund_source_id' => 1])
            ->put(route('spj.update', $transaction->spjPackage->id), [
                'payment_method' => 'siplah',
                'vendor_name' => 'Penyedia SiPLah Diperbarui',
                'siplah_order_number' => 'SIPL-2026-54321',
                'payment_reference' => 'PAY-UPDATED',
                'invoice_number' => 'INV-UPDATED',
                'invoice_date' => '2026-01-14',
            ]);

        $updateResponse->assertSessionHasNoErrors();
        $transaction->refresh();
        $this->assertSame('SIPL-2026-54321', $transaction->siplah_order_number);
        $this->assertSame('Penyedia SiPLah Diperbarui', $transaction->vendor_name);
        $this->assertSame('INV-UPDATED', $transaction->invoice_number);
    }

    public function test_siplah_invoice_date_only_needs_to_be_on_or_before_the_transaction_date(): void
    {
        $transaction = $this->transaction(['is_siplah' => true]);
        $transaction->items()->create([
            'description' => 'Kertas A4', 'item_description' => 'Kertas A4', 'quantity' => 1,
            'unit' => 'rim', 'unit_price' => 1000, 'amount' => 1000,
        ]);

        $response = $this->withoutMiddleware()
            ->withSession(['active_fiscal_year_id' => 1, 'active_fund_source_id' => 1])
            ->post(route('spj.prepare', $transaction->id), [
                'spj_category' => 'BARANG', 'payment_description' => 'Pembelian melalui SiPLah',
                'payment_method' => 'siplah', 'receipt_recipient_name' => 'Toko SiPLah Nusantara',
                'siplah_order_number' => 'SIPL-2026-12345', 'payment_reference' => 'PAY-7788',
                'invoice_number' => 'INV-88231', 'invoice_date' => '2026-01-06',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('2026-01-06', $transaction->fresh()->invoice_date?->format('Y-m-d'));
    }

    public function test_siplah_template_placeholders_keep_marketplace_and_spj_order_numbers_separate(): void
    {
        $transaction = $this->transaction([
            'payment_method' => 'siplah', 'vendor_name' => 'Toko SiPLah Nusantara',
            'siplah_order_number' => 'SIPL-2026-12345', 'invoice_number' => 'INV-88231',
            'invoice_date' => '2026-01-14', 'payment_reference' => 'PAY-7788',
        ]);
        $item = $transaction->items()->create([
            'description' => 'Kertas A4', 'item_description' => 'Kertas A4', 'quantity' => 1,
            'unit' => 'rim', 'unit_price' => 1000, 'amount' => 1000,
        ]);
        $item->goods()->create(['order_number' => '017/SP/BOS/IX/2026']);
        $package = $transaction->spjPackage()->create(['quarter_code' => 'TW1', 'semester_code' => 'S1', 'status' => 'DRAFT']);
        DB::connection('school')->table('school_profiles')->insert(['fiscal_year_id' => 1, 'created_at' => now(), 'updated_at' => now()]);

        $values = app(SpjTemplateService::class)->placeholders($package->load('transaction'), new School([
            'name' => 'SD Negeri Uji', 'npsn' => '12345678',
        ]));

        $this->assertSame('SIPL-2026-12345', $values['SIPLAH_NOMOR_PESANAN']);
        $this->assertSame('Toko SiPLah Nusantara', $values['SIPLAH_PENYEDIA']);
        $this->assertSame('INV-88231', $values['SIPLAH_NOMOR_INVOICE']);
        $this->assertSame('14 Januari 2026', $values['SIPLAH_TANGGAL_INVOICE']);
        $this->assertSame('PAY-7788', $values['SIPLAH_REFERENSI_BAYAR']);
        $this->assertSame('017/SP/BOS/IX/2026', $values['NOMOR_PESANAN']);
        $this->assertNotSame($values['SIPLAH_NOMOR_PESANAN'], $values['NOMOR_PESANAN']);
    }

    public function test_incomplete_siplah_metadata_does_not_add_ready_blockers(): void
    {
        $transaction = $this->transaction([
            'payment_method' => 'siplah', 'spj_category' => 'JASA_LAINNYA',
            'recipient_name' => 'Penyedia', 'receipt_recipient_name' => 'Penyedia',
            'payment_description' => 'Pembayaran jasa', 'activity_code' => '01',
            'activity_name' => 'Kegiatan', 'account_code' => '5.1', 'account_name' => 'Jasa',
        ]);
        $transaction->items()->create([
            'description' => 'Jasa', 'item_description' => 'Jasa', 'quantity' => 1,
            'unit' => 'paket', 'unit_price' => 1000, 'amount' => 1000,
        ]);
        $package = $transaction->spjPackage()->create(['quarter_code' => 'TW1', 'semester_code' => 'S1', 'status' => 'READY']);
        $labels = collect(app(SpjPackageValidationService::class)->validate(
            $package->load(['transaction.items', 'transaction.goods'])
        ))->pluck('label');

        $this->assertNotContains('Referensi pesanan/invoice SIPLah', $labels);
        $this->assertNotContains('Identitas penyedia SIPLah', $labels);
    }

    public function test_siplah_goods_purchase_does_not_require_internal_purchase_order(): void
    {
        $transaction = $this->goodsTransaction(['payment_method' => 'siplah']);
        $package = $transaction->spjPackage()->create(['quarter_code' => 'TW1', 'semester_code' => 'S1', 'status' => 'DRAFT']);
        $requirements = app(SpjDocumentRequirementService::class);
        $rows = collect($requirements->forTransaction($transaction->load(['goods', 'payments', 'goodsReceipts'])));
        $internalOrderContent = $rows->firstWhere('key', 'internal_order_content');
        $internalOrderNumber = $rows->firstWhere('key', 'internal_order_number');
        $blockingLabels = collect($requirements->blockingRequirements($transaction))->pluck('label');
        $validationIssues = collect(app(SpjPackageValidationService::class)->validate(
            $package->load(['transaction.items', 'transaction.goods'])
        ));

        $this->assertFalse($internalOrderContent['applicable']);
        $this->assertFalse($internalOrderContent['required']);
        $this->assertSame('TIDAK_BERLAKU', $internalOrderContent['status']);
        $this->assertFalse($internalOrderNumber['applicable']);
        $this->assertFalse($internalOrderNumber['required']);
        $this->assertNotContains('Kelengkapan isi Surat Pesanan', $blockingLabels);
        $this->assertNotContains('Nomor Surat Pesanan', $blockingLabels);
        $this->assertFalse($validationIssues->contains('label', 'Kelengkapan isi Surat Pesanan'));
        $this->assertFalse($validationIssues->contains('label', 'Nomor Surat Pesanan'));
        $this->assertFalse($validationIssues->contains('label', 'Bukti penerimaan barang'));
    }

    public function test_non_siplah_goods_purchase_uses_content_then_numbering_stages(): void
    {
        $transaction = $this->goodsTransaction(['payment_method' => 'tunai', 'is_siplah' => false]);
        $package = $transaction->spjPackage()->create(['quarter_code' => 'TW1', 'semester_code' => 'S1', 'status' => 'DRAFT']);
        $requirements = app(SpjDocumentRequirementService::class);
        $rows = collect($requirements->forTransaction($transaction->load(['goods', 'payments', 'goodsReceipts'])));
        $internalOrderContent = $rows->firstWhere('key', 'internal_order_content');
        $internalOrderNumber = $rows->firstWhere('key', 'internal_order_number');
        $validationIssues = collect(app(SpjPackageValidationService::class)->validate(
            $package->load(['transaction.items', 'transaction.goods'])
        ));

        $this->assertTrue($internalOrderContent['applicable']);
        $this->assertTrue($internalOrderContent['required']);
        $this->assertTrue($internalOrderContent['available']);
        $this->assertSame('TERSEDIA', $internalOrderContent['status']);
        $this->assertTrue($internalOrderNumber['applicable']);
        $this->assertFalse($internalOrderNumber['required']);
        $this->assertFalse($internalOrderNumber['available']);
        $this->assertSame('OPSIONAL_BELUM_LENGKAP', $internalOrderNumber['status']);
        $this->assertFalse($validationIssues->contains('label', 'Nomor Surat Pesanan'));
    }

    /** @param array<string, mixed> $overrides */
    private function goodsTransaction(array $overrides = []): Transaction
    {
        $transaction = $this->transaction(array_merge([
            'spj_category' => 'BARANG', 'recipient_name' => 'Penyedia',
            'receipt_recipient_name' => 'Penyedia', 'payment_description' => 'Pembelian barang',
            'payment_reference' => 'PAY-001', 'vendor_name' => 'Penyedia',
            'invoice_number' => 'INV-001', 'activity_code' => '01',
            'activity_name' => 'Kegiatan', 'account_code' => '5.1', 'account_name' => 'Barang',
        ], $overrides));
        $transaction->items()->create([
            'description' => 'Barang', 'item_description' => 'Barang', 'quantity' => 1,
            'unit' => 'paket', 'unit_price' => 1000, 'amount' => 1000,
        ]);

        return $transaction;
    }

    /** @param array<string, mixed> $overrides */
    private function transaction(array $overrides = []): Transaction
    {
        return Transaction::query()->create(array_merge([
            'fiscal_year_id' => 1, 'fund_source_id' => 1, 'no_bukti' => 'BKU-001',
            'transaction_date' => '2026-01-15', 'gross_amount' => 1000,
            'tax_total' => 0, 'net_amount' => 1000,
        ], $overrides));
    }
}
