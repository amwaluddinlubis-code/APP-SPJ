<?php

namespace Tests\Feature;

use App\Livewire\TransactionsTable;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\Transaction;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class TransactionsWorkflowFilterTest extends TestCase
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

    public function test_transaction_status_filter_uses_the_same_operator_workflow_as_spj_preparation(): void
    {
        $unprepared = $this->transaction('BPU-001', '2026-01-11');
        $draft = $this->transaction('BPU-002', '2026-01-12', 'DRAFT');
        $ready = $this->transaction('BPU-003', '2026-01-13', 'READY');
        $numbered = $this->transaction('BPU-004', '2026-01-14', 'NUMBERED', '001/SPJ/2026');
        $attention = $this->transaction('BPU-005', '2026-01-15', sourceStatus: 'SOURCE_MISSING');

        $this->assertFilteredIds('Belum Dikerjakan', [$unprepared->id]);
        $this->assertFilteredIds('Perlu Dilengkapi', [$draft->id]);
        $this->assertFilteredIds('Siap Dinomori', [$ready->id]);
        $this->assertFilteredIds('Sudah Bernomor', [$numbered->id]);
        $this->assertFilteredIds('Perlu Perhatian', [$attention->id]);
    }

    private function assertFilteredIds(string $status, array $expectedIds): void
    {
        Livewire::test(TransactionsTable::class)
            ->set('status', $status)
            ->assertViewHas('transactions', function ($transactions) use ($expectedIds): bool {
                return $transactions->getCollection()->pluck('id')->all() === $expectedIds;
            });
    }

    private function transaction(
        string $noBukti,
        string $date,
        ?string $packageStatus = null,
        ?string $documentNumber = null,
        string $sourceStatus = 'ACTIVE',
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
