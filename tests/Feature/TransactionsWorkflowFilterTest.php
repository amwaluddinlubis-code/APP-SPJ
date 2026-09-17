<?php

namespace Tests\Feature;

use App\Livewire\TransactionsTable;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\SpjFreshPackage;
use App\Models\SpjFreshTransaction;
use App\Models\SpjFreshTransactionItem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class TransactionsWorkflowFilterTest extends TestCase
{
    private int $mirrorTableId;

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
        $this->mirrorTableId = DB::connection('school')->table('arkas_raw_mirror_tables')->insertGetId([
            'source_id' => 1, 'source_table' => 'kas_umum', 'schema' => '{}', 'schema_hash' => str_repeat('a', 64),
            'row_count' => 0, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
        ]);

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
    ): SpjFreshTransaction {
        $rawRowId = DB::connection('school')->table('arkas_raw_mirror_rows')->insertGetId([
            'mirror_table_id' => $this->mirrorTableId, 'source_key' => $noBukti, 'ordinal' => 0,
            'payload' => json_encode(['no_bukti' => $noBukti, 'tanggal_transaksi' => $date, 'uraian' => 'Barang uji', 'jumlah' => 100000]),
            'payload_hash' => hash('sha256', $noBukti), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $transaction = SpjFreshTransaction::query()->create([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'source_id' => 1,
            'source_table' => 'kas_umum',
            'source_key' => $noBukti,
            'raw_mirror_row_id' => $rawRowId,
            'spj_category' => 'BARANG',
            'source_status' => $sourceStatus,
            'requires_reconciliation' => false,
        ]);

        SpjFreshTransactionItem::query()->create([
            'spj_fresh_transaction_id' => $transaction->id,
            'source_table' => 'kas_umum',
            'source_key' => $noBukti,
            'raw_mirror_row_id' => $rawRowId,
            'item_description' => 'Barang uji',
        ]);

        if ($packageStatus) {
            SpjFreshPackage::query()->create([
                'spj_fresh_transaction_id' => $transaction->id,
                'quarter_code' => 'TW-1',
                'semester_code' => 'SEM-I',
                'status' => $packageStatus,
                'document_number' => $documentNumber,
            ]);
        }

        return $transaction;
    }
}
