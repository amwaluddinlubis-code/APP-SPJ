<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\Transaction;
use App\UseCases\Spj\SpjPackageCategoryUseCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SpjCategoryLifecycleRegressionTest extends TestCase
{
    use RefreshDatabase;

    private FiscalYear $year;

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

        $fund = FundSource::query()->create([
            'id' => 1,
            'code' => 'BOSP',
            'name' => 'BOSP',
        ]);
        $this->year = FiscalYear::query()->create([
            'year' => 2026,
            'fund_source' => 'BOSP',
            'fund_source_id' => $fund->id,
            'is_active' => true,
        ]);

        session()->put([
            'active_fiscal_year_id' => $this->year->id,
            'active_fund_source_id' => $fund->id,
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    public function test_category_change_demotes_ready_package_for_revalidation(): void
    {
        $transaction = $this->transaction('BPU-READY-CHANGE', 'BARANG');
        $package = $transaction->spjPackage()->create(['status' => 'READY']);

        $response = app(SpjPackageCategoryUseCase::class)->switchCategory(
            (string) $package->id,
            $this->jsonRequest('JASA_LAINNYA'),
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('JASA_LAINNYA', $transaction->fresh()->spj_category);
        $this->assertSame('DRAFT', $package->fresh()->status);
        $this->assertSame('DRAFT', $response->getData(true)['package_status']);

        $audit = DB::connection('school')->table('operational_audit_logs')
            ->where('entity_type', 'SPJ_PACKAGE')
            ->where('entity_id', $package->id)
            ->where('action', 'UBAH_KATEGORI')
            ->first();

        $this->assertNotNull($audit);
        $this->assertStringContainsString('DRAFT', (string) $audit->description);
    }

    public function test_unchanged_category_keeps_ready_package_ready(): void
    {
        $transaction = $this->transaction('BPU-READY-SAME', 'BARANG');
        $package = $transaction->spjPackage()->create(['status' => 'READY']);

        $response = app(SpjPackageCategoryUseCase::class)->switchCategory(
            (string) $package->id,
            $this->jsonRequest('BARANG'),
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('BARANG', $transaction->fresh()->spj_category);
        $this->assertSame('READY', $package->fresh()->status);
        $this->assertSame('READY', $response->getData(true)['package_status']);
        $this->assertSame(0, DB::connection('school')->table('operational_audit_logs')->count());
    }

    private function transaction(string $noBukti, string $category): Transaction
    {
        return Transaction::query()->create([
            'fiscal_year_id' => $this->year->id,
            'fund_source_id' => $this->year->fund_source_id,
            'no_bukti' => $noBukti,
            'transaction_date' => '2026-04-01',
            'spj_category' => $category,
        ]);
    }

    private function jsonRequest(string $category): Request
    {
        $request = Request::create('/spj/category', 'PUT', [
            'spj_category' => $category,
        ]);
        $request->headers->set('Accept', 'application/json');

        return $request;
    }
}
