<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\Transaction;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SpjPackageManualCategoryTest extends TestCase
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

    public function test_package_category_switch_persists_before_category_specific_form_reload(): void
    {
        $transaction = Transaction::query()->create([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'no_bukti' => 'BPU03',
            'transaction_date' => '2026-01-15',
            'gross_amount' => 220000,
            'net_amount' => 220000,
            'spj_category' => 'BARANG',
        ]);
        $transaction->items()->create([
            'description' => 'Paket Data T-Sel 30 Hari',
            'item_description' => 'Paket Data T-Sel 30 Hari',
            'quantity' => 2,
            'unit' => 'paket',
            'unit_price' => 110000,
            'amount' => 220000,
        ]);
        $package = $transaction->spjPackage()->create([
            'quarter_code' => 'TW-1',
            'semester_code' => 'SEM-I',
            'status' => 'DRAFT',
        ]);

        $response = $this->withoutMiddleware()
            ->withSession(['active_fiscal_year_id' => 1, 'active_fund_source_id' => 1])
            ->put(route('spj.update', $package->id), [
                'spj_category' => 'KONSUMSI',
                'category_switch' => 1,
            ]);

        $response->assertRedirect(route('spj.index', ['tab' => 'paket', 'package_id' => $package->id]));
        $this->assertSame('KONSUMSI', $transaction->fresh()->spj_category);
    }

    public function test_incompatible_goods_details_are_removed_only_when_manual_details_are_saved(): void
    {
        $transaction = Transaction::query()->create([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'no_bukti' => 'BPU04',
            'transaction_date' => '2026-01-15',
            'gross_amount' => 220000,
            'net_amount' => 220000,
            'spj_category' => 'BARANG',
        ]);
        $item = $transaction->items()->create([
            'description' => 'Paket Data',
            'item_description' => 'Paket Data',
            'quantity' => 2,
            'unit' => 'paket',
            'unit_price' => 110000,
            'amount' => 220000,
        ]);
        $item->goods()->create(['order_date' => '2026-01-10']);
        $package = $transaction->spjPackage()->create(['status' => 'DRAFT']);

        $this->withoutMiddleware()
            ->withSession(['active_fiscal_year_id' => 1, 'active_fund_source_id' => 1])
            ->put(route('spj.update', $package->id), [
                'spj_category' => 'JASA_LAINNYA',
                'payment_description' => 'Pembayaran jasa',
                'payment_method' => 'tunai',
                'receipt_recipient_name' => 'Penyedia Jasa',
                'service_recipients' => [[
                    'name' => 'Penyedia Jasa',
                    'quantity' => 1,
                    'rental_days' => 1,
                    'daily_rate' => 220000,
                ]],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $transaction->fresh()->goods()->count());
        $this->assertSame(1, $transaction->fresh()->serviceRecipients()->count());
    }
}
