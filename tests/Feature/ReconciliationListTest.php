<?php

namespace Tests\Feature;

use App\Livewire\ReconciliationList;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\Transaction;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ReconciliationListTest extends TestCase
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

    public function test_queue_lists_only_attention_transactions_with_summary(): void
    {
        $this->transaction('BPU-NORMAL', '2026-01-10');
        $changed = $this->transaction('BPU-CHANGED', '2026-01-11', requiresReconciliation: true);
        $missing = $this->transaction('BPU-MISSING', '2026-01-12', sourceStatus: 'SOURCE_MISSING');

        Livewire::test(ReconciliationList::class)
            ->assertViewHas('transactions', function ($transactions) use ($changed, $missing): bool {
                return $transactions->getCollection()->pluck('id')->sort()->values()->all() === collect([$changed->id, $missing->id])->sort()->values()->all();
            })
            ->assertViewHas('summary', function ($summary): bool {
                return $summary['total'] === 2 && $summary['changed'] === 1 && $summary['missing'] === 1 && $summary['with_package'] === 0;
            });
    }

    public function test_attention_filter_narrows_the_queue(): void
    {
        $changed = $this->transaction('BPU-CHANGED', '2026-01-11', requiresReconciliation: true);
        $missing = $this->transaction('BPU-MISSING', '2026-01-12', sourceStatus: 'SOURCE_MISSING');
        $packaged = $this->transaction('BPU-PACKAGED', '2026-01-13', requiresReconciliation: true, packageStatus: 'DRAFT');

        $this->assertFilteredIds(['filter' => 'changed'], [$changed->id, $packaged->id]);
        $this->assertFilteredIds(['filter' => 'missing'], [$missing->id]);
        $this->assertFilteredIds(['filter' => 'with_package'], [$packaged->id]);
    }

    public function test_search_narrows_by_proof_number_and_clear_resets(): void
    {
        $changed = $this->transaction('BPU-CHANGED', '2026-01-11', requiresReconciliation: true);
        $this->transaction('BPU-MISSING', '2026-01-12', sourceStatus: 'SOURCE_MISSING');

        $component = Livewire::test(ReconciliationList::class)
            ->set('q', 'CHANGED')
            ->assertViewHas('transactions', function ($transactions) use ($changed): bool {
                return $transactions->getCollection()->pluck('id')->all() === [$changed->id];
            });

        $component->call('clearFilters')
            ->assertSet('q', '')
            ->assertSet('filter', '')
            ->assertViewHas('transactions', function ($transactions): bool {
                return $transactions->total() === 2;
            });
    }

    private function assertFilteredIds(array $set, array $expectedIds): void
    {
        Livewire::test(ReconciliationList::class)
            ->set($set)
            ->assertViewHas('transactions', function ($transactions) use ($expectedIds): bool {
                return $transactions->getCollection()->pluck('id')->sort()->values()->all() === collect($expectedIds)->sort()->values()->all();
            });
    }

    private function transaction(
        string $noBukti,
        string $date,
        bool $requiresReconciliation = false,
        string $sourceStatus = 'ACTIVE',
        ?string $packageStatus = null,
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

        if ($packageStatus) {
            $transaction->spjPackage()->create([
                'quarter_code' => 'TW-1',
                'semester_code' => 'SEM-I',
                'status' => $packageStatus,
            ]);
        }

        return $transaction;
    }
}
