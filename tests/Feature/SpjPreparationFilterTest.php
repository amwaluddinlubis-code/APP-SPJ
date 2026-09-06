<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\Transaction;
use App\UseCases\Spj\SpjWorkspaceUseCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SpjPreparationFilterTest extends TestCase
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

    public function test_preparation_state_filters_follow_shared_operator_workflow(): void
    {
        $unprepared = $this->transaction('BPU-001', '2026-01-11');
        $draft = $this->transaction('BPU-002', '2026-01-12', 'DRAFT');
        $ready = $this->transaction('BPU-003', '2026-01-13', 'READY');
        $numbered = $this->transaction('BPU-004', '2026-01-14', 'NUMBERED', '001/SPJ/2026');
        $final = $this->transaction('BPU-005', '2026-01-15', 'FINAL', '002/SPJ/2026');
        $sourceMissing = $this->transaction('BPU-006', '2026-01-16', sourceStatus: 'SOURCE_MISSING');
        $reconciliation = $this->transaction('BPU-007', '2026-01-17', requiresReconciliation: true);

        $this->assertSame([$unprepared->id], $this->filteredIds('unprepared'));
        $this->assertSame([$draft->id], $this->filteredIds('draft'));
        $this->assertSame([$ready->id], $this->filteredIds('ready'));
        $this->assertSame([$numbered->id, $final->id], $this->filteredIds('numbered'));
        $this->assertSame([$sourceMissing->id, $reconciliation->id], $this->filteredIds('attention'));
    }

    public function test_legacy_needs_details_state_is_a_temporary_alias_for_source_attention(): void
    {
        $sourceMissing = $this->transaction('BPU-MISSING', '2026-01-10', sourceStatus: 'SOURCE_MISSING');
        $this->transaction('BPU-NORMAL', '2026-01-11');

        $this->assertSame([$sourceMissing->id], $this->filteredIds('needs_details'));
    }

    public function test_month_filter_takes_precedence_when_month_and_quarter_are_both_present(): void
    {
        $april = $this->transaction('BPU-APR', '2026-04-10');
        $this->transaction('BPU-FEB', '2026-02-10');

        $view = app(SpjWorkspaceUseCase::class)->handle(Request::create('/spj', 'GET', [
            'tab' => 'persiapan',
            'month' => 4,
            'quarter' => 1,
        ]));

        $ids = $view->getData()['transactions']->getCollection()->pluck('id')->all();

        $this->assertSame([$april->id], $ids);
    }

    private function filteredIds(string $state): array
    {
        $view = app(SpjWorkspaceUseCase::class)->handle(Request::create('/spj', 'GET', [
            'tab' => 'persiapan',
            'state' => $state,
        ]));

        return $view->getData()['transactions']->getCollection()->pluck('id')->all();
    }

    private function transaction(
        string $noBukti,
        string $date,
        ?string $packageStatus = null,
        ?string $documentNumber = null,
        string $sourceStatus = 'ACTIVE',
        bool $requiresReconciliation = false,
    ): Transaction {
        $transaction = Transaction::query()->create([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'no_bukti' => $noBukti,
            'transaction_date' => $date,
            'gross_amount' => 100000,
            'net_amount' => 100000,
            'spj_category' => 'BARANG',
            'source_status' => $sourceStatus,
            'requires_reconciliation' => $requiresReconciliation,
        ]);

        $transaction->items()->create([
            'description' => 'Barang uji',
            'item_description' => 'Barang uji',
            'quantity' => 1,
            'unit' => 'buah',
            'unit_price' => 100000,
            'amount' => 100000,
        ]);

        if ($packageStatus) {
            $transaction->spjPackage()->create([
                'quarter_code' => 'TW-1',
                'semester_code' => 'SEM-I',
                'status' => $packageStatus,
                'document_number' => $documentNumber,
            ]);
        }

        return $transaction;
    }
}
