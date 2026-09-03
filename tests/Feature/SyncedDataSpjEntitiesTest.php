<?php

namespace Tests\Feature;

use App\Http\Controllers\SyncedDataController;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SyncedDataSpjEntitiesTest extends TestCase
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

    public function test_all_spj_entities_are_counted_and_scoped_through_the_active_year(): void
    {
        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        $year = FiscalYear::query()->create(['year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
        $transaction = Transaction::query()->create([
            'fiscal_year_id' => $year->id,
            'fund_source_id' => 1,
            'no_bukti' => 'BPU-001',
            'transaction_date' => '2026-03-10',
        ]);
        $transaction->spjPackage()->create(['document_number' => '0001/SPJ/2026', 'status' => 'NUMBERED']);
        session(['active_fiscal_year_id' => $year->id]);

        $view = app(SyncedDataController::class)->index(Request::create('/data-sinkron'), 'overview');
        $counts = $view->getData()['counts'];

        $expectedEntities = [
            'spj-paket',
            'spj-dokumen',
            'spj-barang',
            'spj-peserta',
            'spj-perjalanan',
            'spj-pemeliharaan',
            'spj-spk',
            'spj-pekerja',
            'spj-honor',
        ];

        foreach ($expectedEntities as $entity) {
            $this->assertArrayHasKey($entity, $counts);
        }
        $this->assertSame(1, $counts['spj-paket']);
    }

    public function test_number_domain_columns_are_available_on_spj_package_browser(): void
    {
        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        $year = FiscalYear::query()->create(['year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
        session(['active_fiscal_year_id' => $year->id]);

        $view = app(SyncedDataController::class)->index(Request::create('/data-sinkron/spj-paket'), 'spj-paket');
        $columns = $view->getData()['table']['select'];

        $this->assertContains('t.no_bukti as NO_BUKTI', $columns);
        $this->assertContains('sp.document_number as NO_SPJ', $columns);
    }
}
