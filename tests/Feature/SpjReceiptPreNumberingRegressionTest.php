<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Services\SpjDocumentRequirementService;
use App\Services\SpjPackageValidationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SpjReceiptPreNumberingRegressionTest extends TestCase
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

    public function test_bap_bast_dates_are_enough_for_pre_numbering_before_numbers_exist(): void
    {
        $package = $this->package();
        $package->transaction->items->first()->goods()->create([
            'order_date' => '2026-04-02',
            'bap_date' => '2026-04-08',
            'bast_date' => '2026-04-08',
            'bap_number' => null,
            'bast_number' => null,
        ]);

        $transaction = $package->transaction->fresh();
        $receiptRequirement = collect(app(SpjDocumentRequirementService::class)->forTransaction($transaction))
            ->firstWhere('key', 'goods_receipt');

        $this->assertTrue($receiptRequirement['available']);

        $issues = app(SpjPackageValidationService::class)->validateForNumbering($package->fresh(['transaction']));
        $this->assertFalse(
            collect($issues)->contains(fn (array $issue): bool => $issue['label'] === 'Bukti penerimaan barang'),
            'Nomor BAP/BAST yang belum diterbitkan tidak boleh menjadi circular blocker sebelum penomoran.'
        );
    }

    public function test_missing_receipt_event_still_blocks_pre_numbering(): void
    {
        $package = $this->package();
        $package->transaction->items->first()->goods()->create([
            'order_date' => '2026-04-02',
            'bap_date' => null,
            'bast_date' => null,
        ]);

        $issues = app(SpjPackageValidationService::class)->validateForNumbering($package->fresh(['transaction']));

        $this->assertTrue(
            collect($issues)->contains(fn (array $issue): bool => $issue['label'] === 'Bukti penerimaan barang'),
            'Transaksi barang tanpa data/tanggal penerimaan tetap harus diblokir sebelum penomoran.'
        );
    }

    private function package(): SpjPackage
    {
        FundSource::query()->firstOrCreate(['id' => 1], ['code' => 'BOSP', 'name' => 'BOSP']);
        $year = FiscalYear::query()->create([
            'year' => 2026,
            'fund_source' => 'BOSP',
            'fund_source_id' => 1,
        ]);
        $transaction = Transaction::query()->create([
            'fiscal_year_id' => $year->id,
            'fund_source_id' => 1,
            'id_kas_umum' => '200',
            'no_bukti' => 'BPU-RECEIPT-01',
            'transaction_date' => '2026-04-10',
            'rkas_date' => '2026-04-01',
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
            'receipt_recipient_name' => 'Toko Uji',
            'recipient_name' => 'Toko Uji',
            'vendor_name' => 'Toko Uji',
            'is_siplah' => false,
        ]);
        $transaction->items()->create([
            'source_item_id' => '200',
            'description' => 'Barang sumber',
            'item_description' => 'Barang dokumen',
            'quantity' => 1,
            'unit' => 'buah',
            'unit_price' => 1000,
            'amount' => 1000,
        ]);

        return $transaction->spjPackage()->create(['status' => 'READY']);
    }
}
