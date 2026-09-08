<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\UseCases\Spj\SpjWorkspaceUseCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Tests\TestCase;

class SpjPackageNavigationContextTest extends TestCase
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

    public function test_previous_and_next_package_ignore_other_fund_source(): void
    {
        $activeFund = FundSource::query()->create(['id' => 1, 'code' => 'REGULER', 'name' => 'BOSP Reguler']);
        $otherFund = FundSource::query()->create(['id' => 2, 'code' => 'KINERJA', 'name' => 'BOSP Kinerja']);
        $year = FiscalYear::query()->create([
            'id' => 1,
            'year' => 2026,
            'fund_source' => 'REGULER',
            'fund_source_id' => $activeFund->id,
            'is_active' => true,
        ]);
        session()->put([
            'active_fiscal_year_id' => $year->id,
            'active_fund_source_id' => $activeFund->id,
        ]);

        $expectedPrevious = $this->package($year, $activeFund, '2026-01-01', 'ACTIVE-PREV');
        $this->package($year, $otherFund, '2026-01-02', 'OTHER-PREV');
        $current = $this->package($year, $activeFund, '2026-01-03', 'CURRENT');
        $this->package($year, $otherFund, '2026-01-04', 'OTHER-NEXT');
        $expectedNext = $this->package($year, $activeFund, '2026-01-05', 'ACTIVE-NEXT');

        $request = Request::create('/spj', 'GET', [
            'tab' => 'paket',
            'package_id' => $current->id,
        ]);
        $response = app(SpjWorkspaceUseCase::class)->handle($request);

        $this->assertInstanceOf(View::class, $response);
        $this->assertSame($expectedPrevious->id, $response->getData()['previousPackageId']);
        $this->assertSame($expectedNext->id, $response->getData()['nextPackageId']);
    }

    private function package(FiscalYear $year, FundSource $fund, string $date, string $proof): SpjPackage
    {
        $transaction = Transaction::query()->create([
            'fiscal_year_id' => $year->id,
            'fund_source_id' => $fund->id,
            'no_bukti' => $proof,
            'transaction_date' => $date,
            'description' => 'Pengujian navigasi paket',
            'gross_amount' => 1000,
            'tax_total' => 0,
            'net_amount' => 1000,
            'source_key' => 'NAV-'.$proof,
            'source_status' => 'ACTIVE',
            'spj_category' => 'BARANG',
        ]);
        $transaction->items()->create([
            'source_item_id' => 'ITEM-'.$proof,
            'description' => 'Item '.$proof,
            'item_description' => 'Item '.$proof,
            'quantity' => 1,
            'unit' => 'buah',
            'unit_price' => 1000,
            'amount' => 1000,
        ]);

        return $transaction->spjPackage()->create([
            'status' => 'DRAFT',
            'quarter_code' => 'TW-1',
            'semester_code' => 'SEM-I',
        ]);
    }
}
