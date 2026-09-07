<?php

namespace Tests\Feature;

use App\UseCases\Spj\CreateSpjDraftUseCase;
use App\UseCases\Spj\UpdateSpjPackageDetailsUseCase;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TransactionPurchaseDateValidationTest extends TestCase
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

    public function test_bap_and_bast_may_follow_transaction_date_when_chronology_is_valid(): void
    {
        $transaction = Transaction::query()->create([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'no_bukti' => 'BPU-001',
            'transaction_date' => '2026-03-10',
            'gross_amount' => 100000,
            'net_amount' => 100000,
            'source_status' => 'ACTIVE',
            'requires_reconciliation' => false,
        ]);
        $transaction->items()->create([
            'description' => 'Barang uji',
            'item_description' => 'Barang uji',
            'quantity' => 1,
            'unit' => 'buah',
            'unit_price' => 100000,
            'amount' => 100000,
        ]);

        $request = Request::create('/transaksi/'.$transaction->id, 'PUT', [
            'spj_category' => 'BARANG',
            'payment_description' => 'Pembelian barang uji',
            'payment_method' => 'tunai',
            'receipt_recipient_name' => 'Toko Uji',
            'order_date' => '2026-03-09',
            'bap_date' => '2026-03-11',
            'bast_date' => '2026-03-12',
        ]);

        app(CreateSpjDraftUseCase::class)->handle((string) $transaction->id);
        app(UpdateSpjPackageDetailsUseCase::class)->handle((string) $transaction->fresh()->spjPackage->id, $request);

        $goods = $transaction->fresh()->goods()->firstOrFail();
        $this->assertSame('2026-03-09', $goods->order_date?->format('Y-m-d'));
        $this->assertSame('2026-03-11', $goods->bap_date?->format('Y-m-d'));
        $this->assertSame('2026-03-12', $goods->bast_date?->format('Y-m-d'));
    }
}
