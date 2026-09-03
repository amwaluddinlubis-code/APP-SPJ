<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\Transaction;
use App\Services\ArkasBridgeClient;
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
        $transaction->update(['payment_description' => 'Uraian pembayaran manual']);
        $transaction->items->first()->update(['item_description' => 'Uraian item manual']);
        $transaction->spjPackage()->create(['status' => 'DRAFT']);

        $method->invoke($service, $year, [$this->sourceRecord(125000)], $this->createSyncRun($year));
        $transaction->refresh()->load(['items', 'spjPackage']);

        $this->assertSame('Uraian pembayaran manual', $transaction->payment_description);
        $this->assertSame('Uraian item manual', $transaction->items->first()->item_description);
        $this->assertNotNull($transaction->spjPackage);
        $this->assertTrue($transaction->requires_reconciliation);
        $this->assertSame('ACTIVE', $transaction->source_status);

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
