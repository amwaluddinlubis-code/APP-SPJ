<?php

namespace Tests\Feature;

use App\Livewire\TransactionDetailWorkspace;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\SpjFreshTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SpjSourceReconciliationService;
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
        FundSource::query()->create(['id' => 2, 'code' => 'KINERJA', 'name' => 'BOSP Kinerja']);
        FiscalYear::query()->create(['id' => 1, 'year' => 2025, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
        FiscalYear::query()->create(['id' => 2, 'year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
        FiscalYear::query()->create(['id' => 3, 'year' => 2025, 'fund_source' => 'KINERJA', 'fund_source_id' => 2]);
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

    public function test_legacy_and_fresh_lookup_stay_inside_active_fund_source(): void
    {
        $legacyActive = $this->legacyTransaction(1, 'SAME-KEY', 'Reguler legacy', 1000);
        $this->legacyTransaction(3, 'SAME-KEY', 'Kinerja legacy', 9000);

        Livewire::actingAs(User::factory()->create(['role' => User::ROLE_VIEWER]))
            ->test(TransactionDetailWorkspace::class, ['transactionId' => 'SAME-KEY'])
            ->assertViewHas('transaction', fn (Transaction $transaction): bool => $transaction->id === $legacyActive->id
                && $transaction->description === 'Reguler legacy'
                && (float) $transaction->gross_amount === 1000.0);

        $freshActive = $this->freshTransaction(1, 'FRESH-SAME-KEY', 301, 'Reguler fresh', 1, ['activity_code' => 'REGULER-A']);
        $this->freshTransaction(3, 'FRESH-SAME-KEY', 302, 'Kinerja fresh', 2, ['activity_code' => 'KINERJA-B', 'saldo' => 9000]);

        Livewire::actingAs(User::factory()->create(['role' => User::ROLE_VIEWER]))
            ->test(TransactionDetailWorkspace::class, ['transactionId' => 'FRESH-SAME-KEY'])
            ->assertViewHas('transaction', fn (Transaction $transaction): bool => $transaction->id === $freshActive->id
                && $transaction->description === 'Reguler fresh'
                && $transaction->activity_code === 'REGULER-A'
                && (float) $transaction->gross_amount === 1000.0);
    }

    public function test_rkas_fallback_uses_fiscal_year_and_fund_source_context(): void
    {
        DB::connection('school')->table('arkas_rkas_periods')->insert([
            [
                'fiscal_year_id' => 1,
                'fund_source_id' => 1,
                'source_rapbs_id' => 'RAPBS-001',
                'source_period_id' => 'PERIOD-001',
                'source_rapbs_period_id' => 'PERIOD-001',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'fiscal_year_id' => 3,
                'fund_source_id' => 2,
                'source_rapbs_id' => 'RAPBS-001',
                'source_period_id' => 'PERIOD-001',
                'source_rapbs_period_id' => 'PERIOD-001',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        DB::connection('school')->table('arkas_rkas_items')->insert([
            [
                'fiscal_year_id' => 1,
                'fund_source_id' => 1,
                'source_rapbs_id' => 'RAPBS-001',
                'activity_code' => 'KEGIATAN-A',
                'payload' => '{}',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'fiscal_year_id' => 3,
                'fund_source_id' => 2,
                'source_rapbs_id' => 'RAPBS-001',
                'activity_code' => 'KEGIATAN-B',
                'payload' => '{}',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $regular = $this->freshTransaction(1, 'RKAS-FRESH-1', 401, 'Reguler', 1, ['id_rapbs_periode' => 'PERIOD-001']);
        $kinerja = $this->freshTransaction(3, 'RKAS-FRESH-2', 402, 'Kinerja', 2, ['id_rapbs_periode' => 'PERIOD-001']);

        $this->assertSame('KEGIATAN-A', $regular->activity_code);
        $this->assertSame('KEGIATAN-B', $kinerja->activity_code);
    }

    public function test_admin_and_operator_can_save_descriptions_and_are_audited(): void
    {
        foreach ([User::ROLE_ADMIN, User::ROLE_OPERATOR] as $role) {
            $transaction = $this->legacyTransaction(1, 'SAVE-'.$role, 'Sebelum', 1000);
            $item = $transaction->items()->first();
            $actor = User::factory()->create(['role' => $role]);

            Livewire::actingAs($actor)
                ->test(TransactionDetailWorkspace::class, ['transactionId' => $transaction->source_key])
                ->set('paymentDescription', 'Sesudah '.$role)
                ->set('itemDescriptions', [$item->id => 'Item sesudah '.$role])
                ->call('saveDescriptions');

            $this->assertSame('Sesudah '.$role, $transaction->fresh()->payment_description);
            $this->assertSame('Item sesudah '.$role, $item->fresh()->item_description);
            $this->assertDatabaseHas('operational_audit_logs', [
                'entity_type' => 'TRANSACTION',
                'entity_id' => (string) $transaction->id,
                'action' => 'DESCRIPTION_UPDATED',
                'user_id' => $actor->id,
            ], 'school');
        }
    }

    public function test_admin_and_operator_can_resolve_valid_reconciliation_and_are_audited(): void
    {
        foreach ([User::ROLE_ADMIN, User::ROLE_OPERATOR] as $role) {
            $transaction = $this->legacyTransaction(1, 'RECON-'.$role, 'Overlay', 1000, true);
            $eventId = $this->sourceEvent($transaction, ['gross_amount' => 1000], ['gross_amount' => 1250]);
            $actor = User::factory()->create(['role' => $role]);

            Livewire::actingAs($actor)
                ->test(TransactionDetailWorkspace::class, ['transactionId' => $transaction->source_key])
                ->set('sourceEventId', $eventId)
                ->call('resolveReconciliation', SpjSourceReconciliationService::KEEP_OVERLAY);

            $this->assertFalse((bool) $transaction->fresh()->requires_reconciliation);
            $this->assertDatabaseHas('transaction_source_reconciliations', [
                'transaction_id' => $transaction->id,
                'resolution' => SpjSourceReconciliationService::KEEP_OVERLAY,
                'resolved_by' => $actor->id,
            ], 'school');
            $this->assertDatabaseHas('operational_audit_logs', [
                'entity_type' => 'TRANSACTION',
                'entity_id' => (string) $transaction->id,
                'action' => 'SOURCE_RECONCILIATION_RESOLVED',
                'user_id' => $actor->id,
            ], 'school');
        }
    }

    public function test_numbered_allows_description_correction_without_changing_number(): void
    {
        $transaction = $this->legacyTransaction(1, 'NUMBERED-1', 'Sebelum', 1000);
        $package = $transaction->spjPackage()->create(['status' => 'NUMBERED', 'document_number' => '0001/SPJ/2025']);
        $item = $transaction->items()->first();
        $actor = User::factory()->create(['role' => User::ROLE_OPERATOR]);

        Livewire::actingAs($actor)
            ->test(TransactionDetailWorkspace::class, ['transactionId' => $transaction->source_key])
            ->set('paymentDescription', 'Koreksi NUMBERED')
            ->set('itemDescriptions', [$item->id => 'Item NUMBERED'])
            ->call('saveDescriptions');

        $this->assertSame('Koreksi NUMBERED', $transaction->fresh()->payment_description);
        $this->assertSame('Item NUMBERED', $item->fresh()->item_description);
        $this->assertSame('NUMBERED', $package->fresh()->status);
        $this->assertSame('0001/SPJ/2025', $package->fresh()->document_number);
    }

    public function test_final_rejects_description_correction_without_mutating_anything(): void
    {
        $transaction = $this->legacyTransaction(1, 'FINAL-1', 'Sebelum', 1000);
        $transaction->update(['payment_description' => 'Payment sebelum']);
        $package = $transaction->spjPackage()->create(['status' => 'FINAL', 'document_number' => '0002/SPJ/2025']);
        $item = $transaction->items()->first();
        $actor = User::factory()->create(['role' => User::ROLE_OPERATOR]);

        Livewire::actingAs($actor)
            ->test(TransactionDetailWorkspace::class, ['transactionId' => $transaction->source_key])
            ->set('paymentDescription', 'Tidak boleh')
            ->set('itemDescriptions', [$item->id => 'Tidak boleh'])
            ->call('saveDescriptions');

        $this->assertSame('Payment sebelum', $transaction->fresh()->payment_description);
        $this->assertSame('Sebelum', $item->fresh()->item_description);
        $this->assertSame('FINAL', $package->fresh()->status);
        $this->assertSame('0002/SPJ/2025', $package->fresh()->document_number);
    }

    private function freshTransaction(int $fiscalYearId, string $sourceKey, int $sourceId, string $description, int $fundSourceId = 1, array $extraPayload = []): SpjFreshTransaction
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
        $payload = json_encode(['id_kas_umum' => $sourceKey, 'description' => $description, 'saldo' => 1000, ...$extraPayload], JSON_THROW_ON_ERROR);
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
            'fund_source_id' => $fundSourceId,
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

    private function legacyTransaction(int $fiscalYearId, string $sourceKey, string $description, float $grossAmount, bool $requiresReconciliation = false): Transaction
    {
        $transaction = Transaction::query()->create([
            'fiscal_year_id' => $fiscalYearId,
            'fund_source_id' => $fiscalYearId === 3 ? 2 : 1,
            'source_key' => $sourceKey,
            'no_bukti' => $sourceKey,
            'transaction_date' => '2025-01-10',
            'description' => $description,
            'gross_amount' => $grossAmount,
            'tax_total' => 0,
            'net_amount' => $grossAmount,
            'source_status' => 'ACTIVE',
            'requires_reconciliation' => $requiresReconciliation,
        ]);
        $transaction->items()->create(['description' => 'Item '.$description, 'item_description' => 'Sebelum', 'amount' => $grossAmount]);

        return $transaction->fresh(['items', 'spjPackage']);
    }

    /** @param array<string, mixed> $before @param array<string, mixed> $after */
    private function sourceEvent(Transaction $transaction, array $before, array $after): int
    {
        return DB::connection('school')->table('transaction_source_events')->insertGetId([
            'transaction_id' => $transaction->id,
            'event_type' => 'SOURCE_CHANGED',
            'before_snapshot' => json_encode($before, JSON_THROW_ON_ERROR),
            'after_snapshot' => json_encode($after, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }
}
