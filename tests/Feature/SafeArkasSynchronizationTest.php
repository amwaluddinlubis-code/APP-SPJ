<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\Transaction;
use App\Services\ArkasBridgeClient;
use App\Services\ArkasPipePayload;
use App\Services\ArkasSynchronizationServiceV2;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class SafeArkasSynchronizationTest extends TestCase
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

    public function test_source_changes_and_temporary_disappearance_preserve_manual_spj_data(): void
    {
        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        $year = FiscalYear::query()->create(['year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
        $service = new ArkasSynchronizationServiceV2(Mockery::mock(ArkasBridgeClient::class));
        $method = new ReflectionMethod($service, 'saveBkuAndTransactions');

        $method->invoke($service, $year, [$this->sourceRecord(100000)], $this->createSyncRun($year));
        $transaction = Transaction::query()->with('items')->firstOrFail();
        $this->assertTrue((bool) $transaction->is_siplah);
        $this->assertSame('siplah', $transaction->payment_method);
        $this->assertSame('Penyedia ARKAS', $transaction->vendor_name);
        $this->assertSame('12.345.678.9-012.000', $transaction->vendor_npwp);

        $transaction->update([
            'payment_description' => 'Uraian pembayaran manual',
            'receipt_recipient_name' => 'Penerima kuitansi manual',
            'payment_method' => 'siplah',
            'siplah_order_number' => 'SIPL-2026-12345',
            'payment_reference' => 'PAY-7788',
            'invoice_number' => 'INV-88231',
            'invoice_date' => '2026-08-30',
            'invoice_status' => 'PAID',
            'vendor_name' => 'Penyedia SiPLah Manual',
            'vendor_owner' => 'Pemilik penyedia manual',
            'vendor_npwp' => '98.765.432.1-210.000',
        ]);
        $transaction->items->first()->update(['item_description' => 'Uraian item manual']);
        $transaction->spjPackage()->create(['status' => 'DRAFT']);

        $method->invoke($service, $year, [$this->sourceRecord(125000)], $this->createSyncRun($year));
        $transaction->refresh()->load(['items', 'spjPackage']);

        $this->assertSame('Uraian pembayaran manual', $transaction->payment_description);
        $this->assertSame('Penerima kuitansi manual', $transaction->receipt_recipient_name);
        $this->assertSame('siplah', $transaction->payment_method);
        $this->assertSame('SIPL-2026-12345', $transaction->siplah_order_number);
        $this->assertSame('PAY-7788', $transaction->payment_reference);
        $this->assertSame('INV-88231', $transaction->invoice_number);
        $this->assertSame('2026-08-30', $transaction->invoice_date?->toDateString());
        $this->assertSame('PAID', $transaction->invoice_status);
        $this->assertSame('Penyedia SiPLah Manual', $transaction->vendor_name);
        $this->assertSame('Pemilik penyedia manual', $transaction->vendor_owner);
        $this->assertSame('98.765.432.1-210.000', $transaction->vendor_npwp);
        $this->assertSame('Uraian item manual', $transaction->items->first()->item_description);
        $this->assertNotNull($transaction->spjPackage);
        $this->assertTrue($transaction->requires_reconciliation);
        $this->assertSame('ACTIVE', $transaction->source_status);
        $sourceHash = $transaction->source_hash;

        $method->invoke($service, $year, [], $this->createSyncRun($year));
        $transaction->refresh()->load(['items', 'spjPackage']);

        $this->assertSame('SOURCE_MISSING', $transaction->source_status);
        $this->assertNotNull($transaction->source_missing_since);
        $this->assertSame('SOURCE_MISSING', $transaction->items->first()->source_status);
        $this->assertSame('Uraian pembayaran manual', $transaction->payment_description);
        $this->assertSame('Uraian item manual', $transaction->items->first()->item_description);
        $this->assertNotNull($transaction->spjPackage);

        $method->invoke($service, $year, [$this->sourceRecord(125000)], $this->createSyncRun($year));
        $transaction->refresh();

        $this->assertSame('ACTIVE', $transaction->source_status);
        $this->assertNull($transaction->source_missing_since);
        $this->assertSame($sourceHash, $transaction->source_hash);
    }

    public function test_legacy_encoded_rkas_and_bku_payloads_are_normalized_before_json_storage_and_hashing(): void
    {
        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        $year = FiscalYear::query()->create(['year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
        $service = new ArkasSynchronizationServiceV2(Mockery::mock(ArkasBridgeClient::class));

        $rkasOutput = "FIELDS|ID_RAPBS|ID_REF_SUMBER_DANA|URAIAN|NAMA_KEGIATAN|JUMLAH\n"
            .'DATA|RKAS-001|1|Belanja '.chr(0x96).' alat|Kegiatan '.chr(0x80)." sekolah|100000\n";
        $bkuOutput = "FIELDS|ID_KAS_UMUM|ID_REF_SUMBER_DANA|KATEGORI_BKU|NO_BUKTI|TANGGAL_TRANSAKSI|JUMLAH|VOLUME|URAIAN|KODE_REKENING|NAMA_TOKO|IS_SIPLAH|KODE_BKU\n"
            .'DATA|KAS-001|1|BELANJA|BKU-001|2026-08-31|100000|1|Belanja '.chr(0x96).' alat|5.1.02.01|Toko O'.chr(0x92)."Brien|1|BNU\n";

        $saveRkas = new ReflectionMethod($service, 'saveRkas');
        $saveRkas->invoke($service, $year, ArkasPipePayload::decode($rkasOutput, 'rkas'));
        $saveTransactions = new ReflectionMethod($service, 'saveBkuAndTransactions');
        $saveTransactions->invoke($service, $year, ArkasPipePayload::decode($bkuOutput, 'bku'), $this->createSyncRun($year));

        $rkasPayload = DB::connection('school')->table('arkas_rkas_items')->value('payload');
        $bkuPayload = DB::connection('school')->table('arkas_bku_rows')->value('payload');
        $transaction = Transaction::query()->firstOrFail();

        $this->assertIsArray(json_decode($rkasPayload, true, flags: JSON_THROW_ON_ERROR));
        $this->assertIsArray(json_decode($bkuPayload, true, flags: JSON_THROW_ON_ERROR));
        $this->assertSame('Belanja – alat', $transaction->description);
        $this->assertSame('Toko O’Brien', $transaction->vendor_name);
        $this->assertNotEmpty($transaction->source_hash);
    }

    public function test_tax_deposit_row_is_not_counted_twice_as_transaction_tax(): void
    {
        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        $year = FiscalYear::query()->create(['year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
        $service = new ArkasSynchronizationServiceV2(Mockery::mock(ArkasBridgeClient::class));
        $method = new ReflectionMethod($service, 'saveBkuAndTransactions');

        $spending = $this->sourceRecord(625000);
        $receipt = $this->taxRecord('TAX-RECEIVE', 'PBT', 'Terima SSPD', 62500);
        $deposit = $this->taxRecord('TAX-DEPOSIT', 'PBS', 'Setor SSPD', 62500);
        $method->invoke($service, $year, [$spending, $receipt, $deposit], $this->createSyncRun($year));

        $transaction = Transaction::query()->firstOrFail();
        $this->assertSame('62500.00', $transaction->sspd);
        $this->assertSame('62500.00', $transaction->tax_total);
        $this->assertSame('562500.00', $transaction->net_amount);
    }

    private function createSyncRun(FiscalYear $year): int
    {
        return DB::connection('school')->table('sync_runs')->insertGetId([
            'fiscal_year_id' => $year->id, 'source' => 'TEST', 'status' => 'RUNNING',
            'started_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function sourceRecord(int $amount): array
    {
        return [
            'ID_KAS_UMUM' => 'KAS-001', 'ID_REF_SUMBER_DANA' => 1,
            'KATEGORI_BKU' => 'BELANJA', 'NO_BUKTI' => 'BKU-001',
            'TANGGAL_TRANSAKSI' => '2026-08-31', 'JUMLAH' => $amount,
            'VOLUME' => 1, 'URAIAN' => 'Belanja dari ARKAS',
            'KODE_REKENING' => '5.1.02.01', 'NAMA_TOKO' => 'Penyedia ARKAS',
            'NPWP_REKANAN' => '12.345.678.9-012.000', 'IS_SIPLAH' => 1,
            'KODE_BKU' => 'BNU',
        ];
    }

    /** @return array<string, mixed> */
    private function taxRecord(string $id, string $code, string $description, int $amount): array
    {
        return [
            'ID_KAS_UMUM' => $id, 'ID_REF_SUMBER_DANA' => 1,
            'PARENT_ID_KAS_UMUM' => 'KAS-001', 'KATEGORI_BKU' => 'PAJAK',
            'TANGGAL_TRANSAKSI' => '2026-08-31', 'JUMLAH' => $amount,
            'URAIAN' => $description, 'REK_BKU' => 'Pajak Belanja '.($code === 'PBS' ? 'Setor' : 'Terima'),
            'KODE_BKU' => $code, 'IS_SSPD' => 1,
        ];
    }
}
