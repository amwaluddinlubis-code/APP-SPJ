<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\SpjPackage;
use App\Models\Transaction;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SpjNumberedDescriptionCorrectionTest extends TestCase
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
        $this->withoutMiddleware()->withSession(['active_fiscal_year_id' => 1, 'active_fund_source_id' => 1]);
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    public function test_payment_description_can_be_corrected_on_numbered_package(): void
    {
        $package = $this->package('NUMBERED', '0001/SPJ/2026');

        $this->put(route('spj.update', $package->id), [
            'payment_description' => 'Uraian koreksi',
        ])->assertSessionHas('success');

        $package->refresh();
        $this->assertSame('NUMBERED', $package->status);
        $this->assertSame('0001/SPJ/2026', $package->document_number);
        $this->assertSame('Uraian koreksi', $package->transaction->payment_description);
    }

    public function test_locked_fields_are_ignored_on_numbered_package(): void
    {
        $package = $this->package('NUMBERED', '0001/SPJ/2026');

        $this->put(route('spj.update', $package->id), [
            'payment_description' => 'Uraian koreksi',
            'vendor_name' => 'Vendor Baru',
            'payment_method' => 'transfer',
        ])->assertSessionHas('success');

        $package->refresh();
        $this->assertSame('NUMBERED', $package->status);
        $this->assertSame('Uraian koreksi', $package->transaction->payment_description);
        $this->assertSame('Vendor Lama', $package->transaction->vendor_name);
        $this->assertNull($package->transaction->payment_method);
    }

    public function test_final_package_rejects_description_correction(): void
    {
        $package = $this->package('FINAL', '0001/SPJ/2026');

        $this->put(route('spj.update', $package->id), [
            'payment_description' => 'Uraian koreksi',
        ])->assertSessionHas('error');

        $this->assertNull($package->transaction->fresh()->payment_description);
        $this->assertSame('FINAL', $package->fresh()->status);
    }

    public function test_transaction_route_corrects_descriptions_on_numbered_package(): void
    {
        $package = $this->package('NUMBERED', '0001/SPJ/2026');
        $itemId = $package->transaction->items()->first()->id;

        $this->put(route('transactions.spj-descriptions.update', $package->transaction->id), [
            'payment_description' => 'Uraian transaksi',
            'items' => [['id' => $itemId, 'item_description' => 'Barang koreksi']],
        ])->assertSessionHas('success');

        $this->assertSame('Uraian transaksi', $package->transaction->fresh()->payment_description);
        $this->assertSame('Barang koreksi', $package->transaction->items()->first()->item_description);
        $this->assertSame('NUMBERED', $package->fresh()->status);
        $this->assertSame('0001/SPJ/2026', $package->fresh()->document_number);
    }

    public function test_transaction_route_rejects_correction_on_final_package(): void
    {
        $package = $this->package('FINAL', '0001/SPJ/2026');
        $itemId = $package->transaction->items()->first()->id;

        $this->put(route('transactions.spj-descriptions.update', $package->transaction->id), [
            'payment_description' => 'Uraian transaksi',
            'items' => [['id' => $itemId, 'item_description' => 'Barang koreksi']],
        ])->assertSessionHas('error');

        $this->assertNull($package->transaction->fresh()->payment_description);
    }

    public function test_document_routes_reject_cross_fund_source_package(): void
    {
        $package = $this->package('READY', null);
        $this->withSession(['active_fiscal_year_id' => 1, 'active_fund_source_id' => 2]);

        $this->get(route('spj.preview-package', $package->id))
            ->assertRedirect(route('spj.index', ['tab' => 'paket', 'package_id' => $package->id]))
            ->assertSessionHas('error');

        $this->post(route('spj.download', $package->id))
            ->assertRedirect(route('spj.index', ['tab' => 'paket', 'package_id' => $package->id]))
            ->assertSessionHas('error');
    }

    private function package(string $status, ?string $documentNumber): SpjPackage
    {
        $transaction = Transaction::query()->create([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'no_bukti' => 'BKU-001',
            'transaction_date' => '2026-01-10',
            'vendor_name' => 'Vendor Lama',
        ]);
        $transaction->items()->create([
            'description' => 'Barang uji',
            'item_description' => 'Barang uji',
            'amount' => 1000,
        ]);

        return $transaction->spjPackage()->create([
            'status' => $status,
            'document_number' => $documentNumber,
        ]);
    }
}
