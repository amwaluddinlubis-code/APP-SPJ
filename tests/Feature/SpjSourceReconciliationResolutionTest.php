<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\Transaction;
use App\Services\SpjSourceReconciliationService;
use DomainException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SpjSourceReconciliationResolutionTest extends TestCase
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

    public function test_empty_business_diff_can_be_marked_reviewed_and_clears_reconciliation_flag(): void
    {
        $transaction = $this->transaction();
        $eventId = $this->event($transaction, ['gross_amount' => 100000], ['gross_amount' => 100000]);

        $result = app(SpjSourceReconciliationService::class)->resolve(
            $transaction,
            SpjSourceReconciliationService::REVIEWED_NO_BUSINESS_CHANGE,
            'Metadata sudah diperiksa.',
            7,
            $eventId,
        );

        $this->assertSame(SpjSourceReconciliationService::REVIEWED_NO_BUSINESS_CHANGE, $result['resolution']);
        $this->assertFalse((bool) $transaction->fresh()->requires_reconciliation);
        $this->assertDatabaseHas('transaction_source_reconciliations', [
            'transaction_id' => $transaction->id,
            'source_event_id' => $eventId,
            'resolution' => SpjSourceReconciliationService::REVIEWED_NO_BUSINESS_CHANGE,
            'resolved_by' => 7,
        ], 'school');

        $report = app(SpjSourceReconciliationService::class)->forTransaction($transaction->fresh());
        $this->assertFalse($report['needs_attention']);
        $this->assertSame('Sudah ditinjau — tidak ada perubahan nilai bisnis', $report['latest_resolution']->label);
    }

    public function test_draft_package_can_keep_manual_overlay_after_real_source_change(): void
    {
        $transaction = $this->transaction();
        $transaction->update(['payment_description' => 'Overlay manual tetap']);
        $eventId = $this->event($transaction, ['gross_amount' => 100000], ['gross_amount' => 125000]);

        app(SpjSourceReconciliationService::class)->resolve(
            $transaction,
            SpjSourceReconciliationService::KEEP_OVERLAY,
            'Perubahan bruto ditinjau; uraian manual tetap dipakai.',
            9,
            $eventId,
        );

        $transaction->refresh();
        $this->assertFalse((bool) $transaction->requires_reconciliation);
        $this->assertSame('Overlay manual tetap', $transaction->payment_description);
        $this->assertDatabaseHas('transaction_source_reconciliations', [
            'transaction_id' => $transaction->id,
            'resolution' => SpjSourceReconciliationService::KEEP_OVERLAY,
        ], 'school');
    }

    public function test_numbered_or_final_package_cannot_close_real_source_diff_directly(): void
    {
        $transaction = $this->transaction('FINAL');
        $eventId = $this->event($transaction, ['gross_amount' => 100000], ['gross_amount' => 150000]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('workflow pembatalan, reissue, atau revisi resmi');

        try {
            app(SpjSourceReconciliationService::class)->resolve(
                $transaction,
                SpjSourceReconciliationService::ACCEPT_SOURCE,
                null,
                11,
                $eventId,
            );
        } finally {
            $this->assertTrue((bool) $transaction->fresh()->requires_reconciliation);
            $this->assertSame(0, DB::connection('school')->table('transaction_source_reconciliations')->count());
        }
    }

    public function test_stale_source_event_cannot_be_resolved_after_newer_change_arrives(): void
    {
        $transaction = $this->transaction();
        $oldEventId = $this->event($transaction, ['gross_amount' => 100000], ['gross_amount' => 110000]);
        $this->event($transaction, ['gross_amount' => 110000], ['gross_amount' => 120000]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('berubah lagi sejak panel dibuka');

        app(SpjSourceReconciliationService::class)->resolve(
            $transaction,
            SpjSourceReconciliationService::KEEP_OVERLAY,
            null,
            12,
            $oldEventId,
        );
    }

    public function test_resolution_route_is_operator_protected_and_active_context_guarded(): void
    {
        $route = Route::getRoutes()->getByName('transactions.source-reconciliation.resolve');

        $this->assertNotNull($route);
        $this->assertContains('operator-or-administrator', $route->gatherMiddleware());
        $this->assertContains('spj-active-context', $route->gatherMiddleware());
    }

    public function test_empty_diff_panel_exposes_review_completion_action(): void
    {
        $transaction = $this->transaction();
        $this->event($transaction, ['gross_amount' => 100000], ['gross_amount' => 100000]);
        $transaction->load('spjPackage');

        $html = view('transactions.partials.detail.source-reconciliation', compact('transaction'))->render();

        $this->assertStringContainsString('Tandai Sudah Ditinjau', $html);
        $this->assertStringContainsString('REVIEWED_NO_BUSINESS_CHANGE', $html);
        $this->assertStringContainsString('tidak ada perubahan nilai bisnis aktif', $html);
    }

    private function transaction(string $packageStatus = 'DRAFT'): Transaction
    {
        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        $year = FiscalYear::query()->create([
            'year' => 2026,
            'fund_source' => 'BOSP',
            'fund_source_id' => 1,
        ]);
        $transaction = Transaction::query()->create([
            'fiscal_year_id' => $year->id,
            'fund_source_id' => 1,
            'id_kas_umum' => 'KAS-001',
            'no_bukti' => 'BPU01',
            'transaction_date' => '2026-03-06',
            'description' => 'Belanja ARKAS',
            'payment_description' => 'Pembayaran SPJ',
            'gross_amount' => 100000,
            'tax_total' => 0,
            'net_amount' => 100000,
            'source_hash' => str_repeat('a', 64),
            'source_status' => 'ACTIVE',
            'requires_reconciliation' => true,
        ]);
        $transaction->spjPackage()->create([
            'status' => $packageStatus,
            'document_number' => in_array($packageStatus, ['NUMBERED', 'FINAL'], true) ? '001/SPJ/2026' : null,
            'numbered_at' => in_array($packageStatus, ['NUMBERED', 'FINAL'], true) ? now() : null,
            'finalized_at' => $packageStatus === 'FINAL' ? now() : null,
        ]);

        return $transaction->fresh(['spjPackage']);
    }

    /** @param array<string,mixed> $before @param array<string,mixed> $after */
    private function event(Transaction $transaction, array $before, array $after): int
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
