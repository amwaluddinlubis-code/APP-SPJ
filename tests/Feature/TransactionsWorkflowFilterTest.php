<?php

namespace Tests\Feature;

use App\Livewire\TransactionsTable;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\SpjFreshPackage;
use App\Models\SpjFreshTransaction;
use App\Models\SpjFreshTransactionItem;
use App\UseCases\Spj\SpjWorkspaceUseCase;
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
        FundSource::query()->create(['id' => 2, 'code' => 'BOSK', 'name' => 'BOS Kinerja']);
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

    public function test_summary_stats_follow_the_same_fresh_filters_as_transaction_table(): void
    {
        $draft = $this->transaction('BPU-101', '2026-01-11', 'DRAFT', amount: 100000, tax: 10000);
        $ready = $this->transaction('BPU-102', '2026-04-12', 'READY', amount: 200000, tax: 20000);
        $this->transaction('BPU-999', '2026-01-20', 'READY', amount: 900000, tax: 90000, fundSourceId: 2);

        $component = Livewire::test(TransactionsTable::class)
            ->assertViewHas('filteredStats', fn (object $stats): bool => (int) $stats->count === 2
                && (float) $stats->gross === 300000.0
                && (float) $stats->tax === 30000.0
                && (float) $stats->net === 270000.0);

        $component
            ->set('quarter', 1)
            ->assertViewHas('transactions', fn ($transactions): bool => $transactions->getCollection()->pluck('id')->all() === [$draft->id])
            ->assertViewHas('filteredStats', fn (object $stats): bool => (int) $stats->count === 1
                && (float) $stats->gross === 100000.0
                && (float) $stats->tax === 10000.0
                && (float) $stats->net === 90000.0);

        $component
            ->set('quarter', null)
            ->set('status', 'Siap Dinomori')
            ->assertViewHas('transactions', fn ($transactions): bool => $transactions->getCollection()->pluck('id')->all() === [$ready->id])
            ->assertViewHas('filteredStats', fn (object $stats): bool => (int) $stats->count === 1
                && (float) $stats->gross === 200000.0
                && (float) $stats->tax === 20000.0
                && (float) $stats->net === 180000.0);

        $component
            ->set('status', '')
            ->set('q', 'BPU-102')
            ->assertViewHas('transactions', fn ($transactions): bool => $transactions->getCollection()->pluck('id')->all() === [$ready->id])
            ->assertViewHas('filteredStats', fn (object $stats): bool => (int) $stats->count === 1
                && (float) $stats->gross === 200000.0
                && (float) $stats->tax === 20000.0
                && (float) $stats->net === 180000.0);
    }

    public function test_spj_preparation_reads_fresh_projection_when_legacy_transactions_are_empty(): void
    {
        $transaction = $this->transaction('BPU-FRESH-001', '2026-02-10');

        $data = app(SpjWorkspaceUseCase::class)->preparationData([
            'month' => null,
            'quarter' => null,
            'spj_category' => null,
            'state' => 'all',
        ], 15);

        $this->assertSame([$transaction->id], $data['transactions']->getCollection()->pluck('id')->all());
        $this->assertSame(1, $data['workQueueCounts']['all']);
        $this->assertSame('BPU-FRESH-001', $data['transactions']->first()->no_bukti);
    }

    public function test_spj_preparation_fresh_fallback_honors_livewire_page_state(): void
    {
        foreach (range(1, 16) as $number) {
            $this->transaction(sprintf('BPU-PAGE-%02d', $number), '2026-02-10');
        }

        $data = app(SpjWorkspaceUseCase::class)->preparationData([
            'month' => null,
            'quarter' => null,
            'spj_category' => null,
            'state' => 'all',
        ], 15, 2);

        $this->assertSame(['BPU-PAGE-16'], $data['transactions']->getCollection()->pluck('no_bukti')->all());
        $this->assertSame(2, $data['transactions']->currentPage());
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
        float $amount = 100000,
        float $tax = 0,
        int $fundSourceId = 1,
    ): SpjFreshTransaction {
        $sourceItemId = 'KAS-'.$noBukti;
        $payload = [
            'id_kas_umum' => $sourceItemId,
            'id_ref_bku' => 4,
            'no_bukti' => $noBukti,
            'tanggal_transaksi' => $date,
            'uraian' => 'Barang uji',
            'kode_rekening' => '5.1.02',
            'saldo' => $amount,
            'soft_delete' => 0,
        ];
        $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR);
        $rawRowId = DB::connection('school')->table('arkas_raw_mirror_rows')->insertGetId([
            'mirror_table_id' => $this->mirrorTableId, 'source_key' => $sourceItemId, 'ordinal' => 0,
            'payload' => $encodedPayload,
            'payload_hash' => hash('sha256', $encodedPayload), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $transaction = SpjFreshTransaction::query()->create([
            'fiscal_year_id' => 1,
            'fund_source_id' => $fundSourceId,
            'source_id' => 1,
            'source_table' => 'kas_umum',
            'source_key' => hash('sha256', $sourceItemId),
            'raw_mirror_row_id' => $rawRowId,
            'spj_category' => 'BARANG',
            'source_status' => $sourceStatus,
            'requires_reconciliation' => false,
        ]);

        SpjFreshTransactionItem::query()->create([
            'spj_fresh_transaction_id' => $transaction->id,
            'source_table' => 'kas_umum',
            'source_key' => $sourceItemId,
            'raw_mirror_row_id' => $rawRowId,
            'item_description' => 'Barang uji',
        ]);

        if ($tax > 0) {
            $taxPayload = [
                'id_kas_umum' => 'PBT-'.$sourceItemId,
                'parent_id_kas_umum' => $sourceItemId,
                'id_ref_bku' => 10,
                'saldo' => $tax,
                'is_ppn' => 1,
                'soft_delete' => 0,
            ];
            $encodedTax = json_encode($taxPayload, JSON_THROW_ON_ERROR);
            DB::connection('school')->table('arkas_raw_mirror_rows')->insert([
                'mirror_table_id' => $this->mirrorTableId,
                'source_key' => 'PBT-'.$sourceItemId,
                'ordinal' => 0,
                'payload' => $encodedTax,
                'payload_hash' => hash('sha256', $encodedTax),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

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
