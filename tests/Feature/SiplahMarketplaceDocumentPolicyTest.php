<?php

namespace Tests\Feature;

use App\Models\DocumentTemplate;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Services\SpjDocumentRequirementService;
use App\Services\SpjPackageTemplateSelector;
use App\Services\SpjProcurementPolicyService;
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
            'siplah_order_number' => 'SIPL-2026-001',
            'invoice_number' => 'INV-001',
            'payment_reference' => 'PAY-001',
        ]);

        $requirements = collect(app(SpjDocumentRequirementService::class)->forTransaction($transaction));

        $this->assertTrue((bool) $requirements->firstWhere('key', 'siplah_order')['available']);
        $this->assertFalse((bool) $requirements->firstWhere('key', 'internal_order_content')['applicable']);
        $this->assertFalse((bool) $requirements->firstWhere('key', 'internal_order_number')['applicable']);
        $this->assertFalse((bool) $requirements->firstWhere('key', 'goods_receipt')['applicable']);
        $this->assertFalse((bool) $requirements->firstWhere('key', 'bap')['applicable']);
        $this->assertFalse((bool) $requirements->firstWhere('key', 'bast')['applicable']);
    }

    public function test_non_siplah_goods_keep_existing_internal_purchase_order_requirement(): void
    {
        $transaction = $this->transaction([
            'payment_method' => 'tunai',
            'is_siplah' => false,
            'spj_category' => 'BARANG',
        ]);

        $requirements = collect(app(SpjDocumentRequirementService::class)->forTransaction($transaction));
        $internalOrderContent = $requirements->firstWhere('key', 'internal_order_content');
        $internalOrderNumber = $requirements->firstWhere('key', 'internal_order_number');

        $this->assertTrue((bool) $internalOrderContent['applicable']);
        $this->assertTrue((bool) $internalOrderContent['required']);
        $this->assertFalse((bool) $internalOrderContent['available']);
        $this->assertTrue((bool) $internalOrderNumber['applicable']);
        $this->assertFalse((bool) $internalOrderNumber['required']);
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

    public function test_siplah_package_excludes_internal_procurement_templates_but_keeps_a2_and_shared_documents(): void
    {
        $this->seedPackageTemplates();

        $transaction = $this->transaction([
            'payment_method' => 'siplah',
            'is_siplah' => true,
            'spj_category' => 'BARANG',
        ]);
        $package = SpjPackage::query()->create(['transaction_id' => $transaction->id]);

        $selected = app(SpjPackageTemplateSelector::class)
            ->forPackage($package)
            ->pluck('document_type');

        $this->assertCount(2, $selected);
        $this->assertContains('SPJ_COVER', $selected);
        $this->assertContains('SPJ_KUITANSI_A2', $selected);
        $this->assertNotContains('SPJ_SURAT_PESANAN', $selected);
        $this->assertNotContains('SPJ_BA_PEMERIKSAAN', $selected);
        $this->assertNotContains('SPJ_BAST_PEMBELIAN', $selected);
    }

    public function test_non_siplah_package_keeps_internal_procurement_templates(): void
    {
        $this->seedPackageTemplates();

        $transaction = $this->transaction([
            'payment_method' => 'tunai',
            'is_siplah' => false,
            'spj_category' => 'BARANG',
        ]);
        $package = SpjPackage::query()->create(['transaction_id' => $transaction->id]);

        $selected = app(SpjPackageTemplateSelector::class)
            ->forPackage($package)
            ->pluck('document_type');

        $this->assertCount(5, $selected);
        foreach ([
            'SPJ_COVER',
            'SPJ_KUITANSI_A2',
            'SPJ_SURAT_PESANAN',
            'SPJ_BA_PEMERIKSAAN',
            'SPJ_BAST_PEMBELIAN',
        ] as $documentType) {
            $this->assertContains($documentType, $selected);
        }
    }

    public function test_canonical_payment_method_takes_precedence_over_stale_legacy_siplah_flag(): void
    {
        $this->seedPackageTemplates();

        $transaction = $this->transaction([
            'payment_method' => 'tunai',
            'is_siplah' => true,
            'spj_category' => 'BARANG',
        ]);
        $package = SpjPackage::query()->create(['transaction_id' => $transaction->id]);

        $this->assertFalse(app(SpjProcurementPolicyService::class)->isSiplah($transaction));
        $this->assertContains(
            'SPJ_SURAT_PESANAN',
            app(SpjPackageTemplateSelector::class)->forPackage($package)->pluck('document_type'),
        );
    }

    public function test_legacy_siplah_flag_is_used_when_payment_method_is_not_canonical(): void
    {
        $this->seedPackageTemplates();

        $transaction = $this->transaction([
            'payment_method' => null,
            'is_siplah' => true,
            'spj_category' => 'BARANG',
        ]);
        $package = SpjPackage::query()->create(['transaction_id' => $transaction->id]);

        $this->assertTrue(app(SpjProcurementPolicyService::class)->isSiplah($transaction));
        $this->assertNotContains(
            'SPJ_SURAT_PESANAN',
            app(SpjPackageTemplateSelector::class)->forPackage($package)->pluck('document_type'),
        );
    }

    private function seedPackageTemplates(): void
    {
        foreach ([
            'SPJ_COVER' => ['SEMUA'],
            'SPJ_KUITANSI_A2' => ['BARANG'],
            'SPJ_SURAT_PESANAN' => ['BARANG'],
            'SPJ_BA_PEMERIKSAAN' => ['BARANG'],
            'SPJ_BAST_PEMBELIAN' => ['BARANG'],
        ] as $documentType => $categories) {
            DocumentTemplate::query()->create([
                'fiscal_year_id' => 1,
                'document_type' => $documentType,
                'name' => $documentType,
                'format' => 'xlsx',
                'file_path' => strtolower($documentType).'.xlsx',
                'applicable_categories' => $categories,
                'is_active' => true,
            ]);
        }
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
