<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\SpjMaintenance;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Services\SpjDocumentNumberService;
use App\Services\SpjPackageValidationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SpjPreNumberingRegressionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');
        Artisan::call('migrate', ['--database' => 'school', '--path' => 'database/migrations/school', '--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    public function test_order_month_before_rkas_month_blocks_pre_numbering_but_same_month_passes(): void
    {
        $package = $this->package([
            'spj_category' => 'BARANG',
            'rkas_date' => '2026-02-15',
            'payment_description' => 'Pembelian ATK sekolah',
            'payment_method' => 'tunai',
            'receipt_recipient_name' => 'Toko Sumber Ilmu',
        ]);
        $goods = $package->transaction->items->first()->goods()->create([
            'order_date' => '2026-01-31',
            'bap_date' => '2026-02-20',
            'bast_date' => '2026-02-20',
        ]);

        $checks = $this->checks($package);
        $this->assertFalse($checks['order_month_after_rkas']['passed']);

        $goods->forceFill(['order_date' => '2026-02-01'])->save();

        $checks = $this->checks($package->fresh());
        $this->assertTrue($checks['order_month_after_rkas']['passed']);
    }

    public function test_order_month_uses_rkas_period_instead_of_rkas_source_creation_date(): void
    {
        $package = $this->package([
            'rkas_date' => '2026-06-09',
        ]);
        $goods = $package->transaction->items->first()->goods()->create([
            'order_date' => '2026-07-12',
        ]);

        DB::connection('school')->table('arkas_bku_rows')->insert([
            'fiscal_year_id' => $package->transaction->fiscal_year_id,
            'fund_source_id' => $package->transaction->fund_source_id,
            'source_kas_id' => $package->transaction->id_kas_umum,
            'category' => 'BELANJA',
            'no_bukti' => $package->transaction->no_bukti,
            'amount' => 1000,
            'payload' => json_encode(['ID_RAPBS' => 'rapbs-100'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('school')->table('arkas_rkas_periods')->insert([
            'fiscal_year_id' => $package->transaction->fiscal_year_id,
            'fund_source_id' => $package->transaction->fund_source_id,
            'source_rapbs_id' => 'rapbs-100',
            'source_period_id' => 'period-8',
            'period_name' => 'Agustus',
            'month_number' => 8,
            'quarter_number' => 3,
            'semester_number' => 2,
            'volume' => 1,
            'amount' => 1000,
            'payload' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $checks = $this->checks($package->fresh());
        $this->assertFalse($checks['order_month_after_rkas']['passed']);

        $goods->forceFill(['order_date' => '2026-08-01'])->save();

        $checks = $this->checks($package->fresh());
        $this->assertTrue($checks['order_month_after_rkas']['passed']);
    }

    public function test_core_common_document_fields_are_numbering_blockers_until_completed(): void
    {
        $package = $this->package([
            'spj_category' => null,
            'rkas_date' => '2026-01-05',
            'payment_description' => null,
            'payment_method' => null,
            'receipt_recipient_name' => null,
        ]);

        $checks = $this->checks($package);
        foreach (['spj_category', 'payment_description', 'recipient', 'payment_method'] as $key) {
            $this->assertFalse($checks[$key]['passed'], $key.' harus memblokir penomoran ketika kosong.');
        }

        $package->transaction->forceFill([
            'spj_category' => 'BARANG',
            'payment_description' => 'Pembelian kebutuhan pembelajaran',
            'payment_method' => 'tunai',
            'receipt_recipient_name' => 'Penerima Utama',
        ])->save();

        $checks = $this->checks($package->fresh());
        foreach (['spj_category', 'payment_description', 'recipient', 'payment_method'] as $key) {
            $this->assertTrue($checks[$key]['passed'], $key.' harus lolos setelah Data Umum dilengkapi.');
        }
    }

    public function test_spk_and_rab_numbers_use_quarter_placeholder_and_stay_synced_with_work_order(): void
    {
        $package = $this->package([
            'spj_category' => 'PEMELIHARAAN',
            'rkas_date' => '2026-01-05',
            'payment_description' => 'Pemeliharaan ruang kelas',
            'payment_method' => 'tunai',
            'receipt_recipient_name' => 'Pelaksana Pemeliharaan',
        ]);
        $maintenance = SpjMaintenance::query()->create([
            'fiscal_year_id' => $package->transaction->fiscal_year_id,
            'name' => 'Pemeliharaan ruang kelas',
            'status' => 'ACTIVE',
        ]);
        $workOrder = $package->transaction->workOrder()->create([
            'maintenance_id' => $maintenance->id,
            'expense_type' => 'MATERIAL',
            'work_description' => 'Pemeliharaan ruang kelas',
            'spk_date' => '2026-02-10',
            'rab_date' => '2026-03-01',
        ]);

        $result = app(SpjDocumentNumberService::class)->assignAutomaticNumbers(
            $package->fresh(['transaction.workOrder']),
            'SDN.318',
            '10208183',
            ['SPK', 'RAB'],
        );

        $this->assertSame(2, $result['created']);

        $documents = $package->documents()->whereIn('document_type', ['SPK', 'RAB'])->get()->keyBy('document_type');
        $this->assertStringContainsString('/SPK/SDN.318/I/2026', $documents['SPK']->document_number);
        $this->assertStringContainsString('/RAB/SDN.318/I/2026', $documents['RAB']->document_number);

        $workOrder->refresh();
        $this->assertSame($documents['SPK']->document_number, $workOrder->spk_number);
        $this->assertSame($documents['RAB']->document_number, $workOrder->rab_number);
    }

    public function test_pre_numbering_modal_exposes_the_required_review_fields(): void
    {
        $blade = file_get_contents(resource_path('views/spj/partials/package/numbering-preflight-modal.blade.php'));

        foreach (['TGL_RKAS', 'TGL_TRANSAKSI', 'NO_BUKTI', 'PAYMENT_DESCRIPTION', 'TGL_PESANAN', 'TGL_BAP', 'TGL_BAST', 'BRUTO'] as $label) {
            $this->assertStringContainsString($label, $blade);
        }
    }

    /** @param array<string, mixed> $overrides */
    private function package(array $overrides = []): SpjPackage
    {
        FundSource::query()->firstOrCreate(['id' => 1], ['code' => 'BOSP', 'name' => 'BOSP']);
        $year = FiscalYear::query()->create([
            'year' => 2026,
            'fund_source' => 'BOSP',
            'fund_source_id' => 1,
        ]);
        $transaction = Transaction::query()->create(array_merge([
            'fiscal_year_id' => $year->id,
            'fund_source_id' => 1,
            'id_kas_umum' => '100',
            'no_bukti' => 'BPU01',
            'transaction_date' => '2026-03-06',
            'rkas_date' => '2026-01-05',
            'activity_code' => '05.02.01',
            'activity_name' => 'Kegiatan sekolah',
            'account_code' => '5.1.02.01',
            'account_name' => 'Belanja barang',
            'gross_amount' => 1000,
            'tax_total' => 0,
            'net_amount' => 1000,
            'spj_category' => 'BARANG',
            'payment_description' => 'Pembelian barang',
            'payment_method' => 'tunai',
            'receipt_recipient_name' => 'Penerima Utama',
        ], $overrides));
        $transaction->items()->create([
            'source_item_id' => '100',
            'description' => 'Barang sumber',
            'item_description' => 'Barang dokumen',
            'quantity' => 1,
            'unit' => 'buah',
            'unit_price' => 1000,
            'amount' => 1000,
        ]);

        return $transaction->spjPackage()->create(['status' => 'READY']);
    }

    /** @return array<string, array<string, mixed>> */
    private function checks(SpjPackage $package): array
    {
        $package->load([
            'transaction.items',
            'transaction.goods',
            'transaction.honors',
            'transaction.serviceRecipients',
        ]);

        return collect(app(SpjPackageValidationService::class)->checklist($package))
            ->keyBy('key')
            ->all();
    }
}
