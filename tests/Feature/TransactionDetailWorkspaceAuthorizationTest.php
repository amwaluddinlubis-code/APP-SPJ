<?php

namespace Tests\Feature;

use App\Livewire\TransactionDetailWorkspace;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\SpjFreshTransaction;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class TransactionDetailWorkspaceAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');
        Artisan::call('migrate', ['--database' => 'school', '--path' => 'database/migrations/school', '--force' => true]);

        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        FiscalYear::query()->create(['id' => 1, 'year' => 2025, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
        FiscalYear::query()->create(['id' => 2, 'year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
        session(['active_fiscal_year_id' => 1, 'active_fund_source_id' => 1, 'active_school_id' => 1]);
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    public function test_fresh_lookup_stays_inside_active_year_when_source_key_is_reused(): void
    {
        $sourceKey = 'REUSED-SOURCE-KEY';
        $active = $this->freshTransaction(1, $sourceKey, 101, '2025 active');
        $this->freshTransaction(2, $sourceKey, 202, '2026 hidden');

        Livewire::actingAs(User::factory()->create(['role' => User::ROLE_VIEWER]))
            ->test(TransactionDetailWorkspace::class, ['transactionId' => $sourceKey])
            ->assertViewHas('transaction', fn (Transaction $transaction): bool => $transaction->id === $active->id && $transaction->description === '2025 active');
    }

    public function test_viewer_cannot_invoke_livewire_description_or_reconciliation_mutations(): void
    {
        $transaction = Transaction::query()->create([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'source_key' => 'LEGACY-BOUNDARY',
            'no_bukti' => 'BPU-BOUNDARY',
            'transaction_date' => '2025-01-10',
            'description' => 'Legacy transaction',
            'gross_amount' => 100000,
            'net_amount' => 100000,
            'source_status' => 'ACTIVE',
        ]);
        $item = $transaction->items()->create(['description' => 'Item lama', 'item_description' => 'Sebelum']);
        $viewer = User::factory()->create(['role' => User::ROLE_VIEWER]);

        Livewire::actingAs($viewer)
            ->test(TransactionDetailWorkspace::class, ['transactionId' => $transaction->source_key])
            ->set('paymentDescription', 'Tidak boleh')
            ->set('itemDescriptions', [$item->id => 'Tidak boleh'])
            ->call('saveDescriptions')
            ->assertStatus(403);

        Livewire::actingAs($viewer)
            ->test(TransactionDetailWorkspace::class, ['transactionId' => $transaction->source_key])
            ->call('resolveReconciliation', 'ACCEPT_SOURCE')
            ->assertStatus(403);

        $this->assertSame('Sebelum', $item->fresh()->item_description);
        $this->assertNull($transaction->fresh()->payment_description);
    }

    private function freshTransaction(int $fiscalYearId, string $sourceKey, int $sourceId, string $description): SpjFreshTransaction
    {
        $tableId = DB::connection('school')->table('arkas_raw_mirror_tables')->insertGetId([
            'source_id' => $sourceId,
            'source_table' => 'kas_umum',
            'schema' => json_encode(['description' => 'text']),
            'schema_hash' => hash('sha256', 'schema-'.$sourceId),
            'row_count' => 1,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $payload = json_encode(['id_kas_umum' => $sourceKey, 'description' => $description, 'saldo' => 1000], JSON_THROW_ON_ERROR);
        $rowId = DB::connection('school')->table('arkas_raw_mirror_rows')->insertGetId([
            'mirror_table_id' => $tableId,
            'source_key' => $sourceKey,
            'ordinal' => 0,
            'payload' => $payload,
            'payload_hash' => hash('sha256', $payload),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $fresh = SpjFreshTransaction::query()->create([
            'fiscal_year_id' => $fiscalYearId,
            'fund_source_id' => 1,
            'source_id' => $sourceId,
            'source_table' => 'kas_umum',
            'source_key' => $sourceKey,
            'raw_mirror_row_id' => $rowId,
            'source_status' => 'ACTIVE',
        ]);
        $fresh->items()->create([
            'source_table' => 'kas_umum',
            'source_key' => $sourceKey,
            'raw_mirror_row_id' => $rowId,
            'sort_order' => 0,
        ]);

        return $fresh;
    }
}
