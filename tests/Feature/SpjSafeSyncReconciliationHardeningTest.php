<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\SpjDocument;
use App\Models\Transaction;
use App\Services\ArkasBridgeClient;
use App\Services\ArkasSynchronizationServiceV2;
use App\Services\SpjSourceReconciliationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class SpjSafeSyncReconciliationHardeningTest extends TestCase
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

    public function test_unchanged_source_preserves_overlay_without_creating_reconciliation(): void
    {
        [$year, $sync] = $this->context();
        $sync($this->sourceRecord());

        $transaction = Transaction::query()->with('items')->firstOrFail();
        $transaction->update([
            'payment_description' => 'Pembayaran manual',
            'receipt_recipient_name' => 'Penerima manual',
            'payment_method' => 'tunai',
            'vendor_name' => 'Vendor manual',
            'vendor_owner' => 'Pemilik manual',
            'vendor_npwp' => '99.999.999.9-999.999',
            'spj_category' => 'BARANG',
        ]);
        $transaction->items->first()->update(['item_description' => 'Uraian dokumen manual']);
        $transaction->spjPackage()->create(['status' => 'DRAFT']);
        $sourceHash = $transaction->fresh()->source_hash;

        $sync($this->sourceRecord());
        $transaction->refresh()->load('items');

        $this->assertFalse((bool) $transaction->requires_reconciliation);
        $this->assertSame($sourceHash, $transaction->source_hash);
        $this->assertSame('Pembayaran manual', $transaction->payment_description);
        $this->assertSame('Penerima manual', $transaction->receipt_recipient_name);
        $this->assertSame('tunai', $transaction->payment_method);
        $this->assertSame('Vendor manual', $transaction->vendor_name);
        $this->assertSame('Pemilik manual', $transaction->vendor_owner);
        $this->assertSame('99.999.999.9-999.999', $transaction->vendor_npwp);
        $this->assertSame('BARANG', $transaction->spj_category);
        $this->assertSame('Uraian dokumen manual', $transaction->items->first()->item_description);
        $this->assertSame(0, DB::connection('school')->table('transaction_source_events')->count());
    }

    public function test_reordered_source_items_keep_operator_descriptions_by_source_identity(): void
    {
        [$year, $sync] = $this->context();
        $records = [
            $this->sourceRecord(id: 'KAS-001', proof: 'BKU-GROUP', amount: 100000),
            $this->sourceRecord(id: 'KAS-002', proof: 'BKU-GROUP', amount: 200000),
        ];
        $sync($records);

        $transaction = Transaction::query()->with('items')->firstOrFail();
        $transaction->update(['payment_description' => 'Overlay grup']);
        $transaction->items->firstWhere('source_item_id', 'KAS-001')->update(['item_description' => 'Deskripsi operator A']);
        $transaction->items->firstWhere('source_item_id', 'KAS-002')->update(['item_description' => 'Deskripsi operator B']);
        $transactionId = $transaction->id;
        $sourceKey = $transaction->source_key;

        $sync(array_reverse($records));
        $transaction->refresh()->load('items');

        $this->assertSame($transactionId, $transaction->id);
        $this->assertSame($sourceKey, $transaction->source_key);
        $this->assertSame('Overlay grup', $transaction->payment_description);
        $this->assertSame('Deskripsi operator A', $transaction->items->firstWhere('source_item_id', 'KAS-001')->item_description);
        $this->assertSame('Deskripsi operator B', $transaction->items->firstWhere('source_item_id', 'KAS-002')->item_description);
        $this->assertCount(1, DB::connection('school')->table('transactions')->where('source_key', $sourceKey)->get());
    }

    public function test_projection_sync_preserves_draft_numbered_and_final_packages(): void
    {
        [$year, $sync] = $this->context();
        $statuses = ['DRAFT', 'NUMBERED', 'FINAL'];
        $packages = [];

        foreach ($statuses as $index => $status) {
            $proof = 'BKU-STATUS-'.($index + 1);
            $sync($this->sourceRecord(id: 'KAS-STATUS-'.($index + 1), proof: $proof));
            $transaction = Transaction::query()->where('no_bukti', $proof)->firstOrFail();
            $attributes = ['status' => $status, 'quarter_code' => 'TW1', 'document_number' => sprintf('%03d/SPJ/2026', $index + 1)];
            if ($status !== 'DRAFT') {
                $attributes['numbered_at'] = now()->subMinutes(2);
            }
            if ($status === 'FINAL') {
                $attributes['finalized_at'] = now()->subMinute();
                $attributes['finalized_by'] = 1;
                $attributes['snapshot'] = ['locked' => 'continuity-'.$status];
            }
            $packages[$transaction->id] = $transaction->spjPackage()->create($attributes);
        }

        $sync([
            $this->sourceRecord(id: 'KAS-STATUS-1', proof: 'BKU-STATUS-1', amount: 110000),
            $this->sourceRecord(id: 'KAS-STATUS-2', proof: 'BKU-STATUS-2', amount: 120000),
            $this->sourceRecord(id: 'KAS-STATUS-3', proof: 'BKU-STATUS-3', amount: 130000),
        ]);

        foreach ($packages as $transactionId => $package) {
            $transaction = Transaction::query()->with('spjPackage')->findOrFail($transactionId);
            $package->refresh();
            $this->assertSame($package->id, $transaction->spjPackage?->id);
            $this->assertSame($package->status, $transaction->spjPackage?->status);
            $this->assertSame($package->document_number, $transaction->spjPackage?->document_number);
            $this->assertSame($package->numbered_at?->toDateTimeString(), $transaction->spjPackage?->numbered_at?->toDateTimeString());
            $this->assertSame($package->finalized_at?->toDateTimeString(), $transaction->spjPackage?->finalized_at?->toDateTimeString());
            $this->assertSame($package->snapshot, $transaction->spjPackage?->snapshot);
        }

        $this->assertSame(3, DB::connection('school')->table('transactions')->count());
        $this->assertSame(3, DB::connection('school')->table('spj_packages')->count());
    }

    public function test_source_membership_change_reuses_legacy_transaction_and_preserves_package(): void
    {
        [$year, $sync] = $this->context();
        $sync([
            $this->sourceRecord(id: 'KAS-001', proof: 'BKU-MEMBERSHIP', amount: 100000),
            $this->sourceRecord(id: 'KAS-002', proof: 'BKU-MEMBERSHIP', amount: 200000),
        ]);

        $transaction = Transaction::query()->with('items')->where('no_bukti', 'BKU-MEMBERSHIP')->firstOrFail();
        $transaction->update([
            'payment_description' => 'Overlay membership',
            'spj_category' => 'BARANG',
            'receipt_recipient_name' => 'Penerima membership',
        ]);
        $transaction->items->firstWhere('source_item_id', 'KAS-001')->update(['item_description' => 'Item lama A']);
        $transaction->items->firstWhere('source_item_id', 'KAS-002')->update(['item_description' => 'Item lama B']);
        $package = $transaction->spjPackage()->create([
            'status' => 'NUMBERED',
            'document_number' => '005/SPJ/2026',
            'numbered_at' => now()->subMinute(),
        ]);
        $transactionId = $transaction->id;
        $oldSourceKey = $transaction->source_key;

        $sync([
            $this->sourceRecord(id: 'KAS-001', proof: 'BKU-MEMBERSHIP', amount: 100000),
            $this->sourceRecord(id: 'KAS-002', proof: 'BKU-MEMBERSHIP', amount: 200000),
            $this->sourceRecord(id: 'KAS-003', proof: 'BKU-MEMBERSHIP', amount: 300000),
        ]);

        $transaction->refresh()->load(['items', 'spjPackage']);
        $this->assertNotSame($oldSourceKey, $transaction->source_key);
        $this->assertSame($transactionId, $transaction->id);
        $this->assertSame($package->id, $transaction->spjPackage?->id);
        $this->assertSame('NUMBERED', $transaction->spjPackage?->status);
        $this->assertSame('005/SPJ/2026', $transaction->spjPackage?->document_number);
        $this->assertSame('Overlay membership', $transaction->payment_description);
        $this->assertSame('BARANG', $transaction->spj_category);
        $this->assertSame('Penerima membership', $transaction->receipt_recipient_name);
        $this->assertSame('Item lama A', $transaction->items->firstWhere('source_item_id', 'KAS-001')->item_description);
        $this->assertSame('Item lama B', $transaction->items->firstWhere('source_item_id', 'KAS-002')->item_description);
        $this->assertSame('ACTIVE', $transaction->source_status);
        $this->assertSame(1, DB::connection('school')->table('transactions')->where('no_bukti', 'BKU-MEMBERSHIP')->count());
        $this->assertSame(1, DB::connection('school')->table('spj_packages')->where('transaction_id', $transactionId)->count());
    }

    public function test_source_and_item_changes_create_diff_without_overwriting_manual_overlay(): void
    {
        [$year, $sync] = $this->context();
        $sync($this->sourceRecord());

        $transaction = Transaction::query()->with('items')->firstOrFail();
        $transaction->update([
            'payment_description' => 'Overlay tetap',
            'vendor_name' => 'Vendor overlay',
            'spj_category' => 'BARANG',
        ]);
        $transaction->items->first()->update(['item_description' => 'Nama barang dokumen']);
        $transaction->spjPackage()->create(['status' => 'DRAFT']);

        $changed = $this->sourceRecord(amount: 125000, volume: 5);
        $sync($changed);
        $transaction->refresh()->load('items');

        $this->assertTrue((bool) $transaction->requires_reconciliation);
        $this->assertSame('125000.00', $transaction->gross_amount);
        $this->assertSame('Overlay tetap', $transaction->payment_description);
        $this->assertSame('Vendor overlay', $transaction->vendor_name);
        $this->assertSame('BARANG', $transaction->spj_category);
        $this->assertSame('Nama barang dokumen', $transaction->items->first()->item_description);

        $eventTypes = DB::connection('school')->table('transaction_source_events')
            ->where('transaction_id', $transaction->id)
            ->pluck('event_type');
        $this->assertContains('SOURCE_CHANGED', $eventTypes);
        $this->assertContains('SOURCE_ITEM_CHANGED', $eventTypes);

        $report = app(SpjSourceReconciliationService::class)->forTransaction($transaction);
        $this->assertTrue($report['needs_attention']);
        $this->assertNotEmpty($report['latest']->changes);
        $this->assertNotNull($report['action_hint']);
    }

    public function test_source_missing_and_returning_reconnects_same_transaction_and_package(): void
    {
        [$year, $sync] = $this->context();
        $sync($this->sourceRecord());

        $transaction = Transaction::query()->with('items')->firstOrFail();
        $transaction->update(['payment_description' => 'Tetap aman']);
        $transaction->items->first()->update(['item_description' => 'Tetap aman']);
        $package = $transaction->spjPackage()->create(['status' => 'DRAFT']);
        $transactionId = $transaction->id;

        $sync([]);
        $transaction->refresh()->load(['items', 'spjPackage']);
        $this->assertSame('SOURCE_MISSING', $transaction->source_status);
        $this->assertSame($package->id, $transaction->spjPackage?->id);
        $this->assertSame('Tetap aman', $transaction->payment_description);
        $this->assertSame('Tetap aman', $transaction->items->first()->item_description);

        $sync($this->sourceRecord());
        $transaction = Transaction::query()->with(['items', 'spjPackage'])->findOrFail($transactionId);
        $this->assertSame('ACTIVE', $transaction->source_status);
        $this->assertNull($transaction->source_missing_since);
        $this->assertSame($package->id, $transaction->spjPackage?->id);
        $this->assertSame('Tetap aman', $transaction->payment_description);
        $this->assertSame('Tetap aman', $transaction->items->first()->item_description);

        $events = DB::connection('school')->table('transaction_source_events')
            ->where('transaction_id', $transactionId)
            ->orderBy('id')
            ->pluck('event_type')
            ->all();
        $this->assertContains('SOURCE_MISSING', $events);
        $this->assertContains('SOURCE_RETURNED', $events);
    }

    public function test_sync_does_not_mutate_numbered_or_final_document_identity_and_snapshots(): void
    {
        [$year, $sync] = $this->context();
        $sync($this->sourceRecord());

        $transaction = Transaction::query()->firstOrFail();
        $package = $transaction->spjPackage()->create([
            'status' => 'FINAL',
            'document_number' => '001/SPJ/2026',
            'numbered_at' => now(),
            'finalized_at' => now(),
            'snapshot' => ['locked' => 'package-before-sync'],
        ]);
        $document = SpjDocument::query()->create([
            'spj_package_id' => $package->id,
            'document_type' => 'SPJ',
            'scope_key' => 'MAIN',
            'document_number' => '001/SPJ/2026',
            'sequence_number' => 1,
            'document_date' => '2026-08-31',
            'status' => 'FINAL',
            'numbered_at' => now(),
            'finalized_at' => now(),
            'snapshot' => ['locked' => 'document-before-sync'],
        ]);

        $sync($this->sourceRecord(amount: 150000, volume: 2));
        $transaction->refresh();
        $package->refresh();
        $document->refresh();

        $this->assertTrue((bool) $transaction->requires_reconciliation);
        $this->assertSame('FINAL', $package->status);
        $this->assertSame('001/SPJ/2026', $package->document_number);
        $this->assertSame(['locked' => 'package-before-sync'], $package->snapshot);
        $this->assertSame('FINAL', $document->status);
        $this->assertSame('001/SPJ/2026', $document->document_number);
        $this->assertSame(['locked' => 'document-before-sync'], $document->snapshot);

        $report = app(SpjSourceReconciliationService::class)->forTransaction($transaction->load('spjPackage'));
        $this->assertStringContainsString('workflow pembatalan/reissue/revisi resmi', (string) $report['action_hint']);
    }

    public function test_transaction_detail_reconciliation_panel_exposes_latest_diff(): void
    {
        [$year, $sync] = $this->context();
        $sync($this->sourceRecord());
        $transaction = Transaction::query()->firstOrFail();
        $transaction->spjPackage()->create(['status' => 'DRAFT']);

        $sync($this->sourceRecord(amount: 175000));
        $transaction->refresh()->load(['items', 'spjPackage']);

        $html = view('transactions.partials.detail.source-reconciliation', compact('transaction'))->render();
        $this->assertStringContainsString('Rekonsiliasi Sumber ARKAS/BKU', $html);
        $this->assertStringContainsString('PERLU REKONSILIASI', $html);
        $this->assertStringContainsString('Bruto', $html);
        $this->assertStringContainsString('100.000', $html);
        $this->assertStringContainsString('175.000', $html);
    }

    /**
     * @return array{FiscalYear, callable(array<int,array<string,mixed>>|array<string,mixed>):void}
     */
    private function context(): array
    {
        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        $year = FiscalYear::query()->create(['year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
        $service = new ArkasSynchronizationServiceV2(Mockery::mock(ArkasBridgeClient::class));
        $method = new ReflectionMethod($service, 'saveBkuAndTransactions');

        $sync = function (array $records) use ($service, $method, $year): void {
            if ($records !== [] && array_key_exists('ID_KAS_UMUM', $records)) {
                $records = [$records];
            }
            $method->invoke($service, $year, $records, $this->createSyncRun($year));
        };

        return [$year, $sync];
    }

    private function createSyncRun(FiscalYear $year): int
    {
        return DB::connection('school')->table('sync_runs')->insertGetId([
            'fiscal_year_id' => $year->id,
            'source' => 'TEST',
            'status' => 'RUNNING',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array<string,mixed> */
    private function sourceRecord(int $amount = 100000, int $volume = 1, string $id = 'KAS-001', string $proof = 'BKU-001'): array
    {
        return [
            'ID_KAS_UMUM' => $id,
            'ID_REF_SUMBER_DANA' => 1,
            'KATEGORI_BKU' => 'BELANJA',
            'NO_BUKTI' => $proof,
            'TANGGAL_TRANSAKSI' => '2026-08-31',
            'JUMLAH' => $amount,
            'VOLUME' => $volume,
            'URAIAN' => 'Belanja dari ARKAS',
            'KODE_REKENING' => '5.1.02.01',
            'NAMA_TOKO' => 'Penyedia ARKAS',
            'NPWP_REKANAN' => '12.345.678.9-012.000',
            'IS_SIPLAH' => 1,
            'KODE_BKU' => 'BNU',
            'CREATE_DATE' => '2026-08-31 09:15:00',
            'LAST_UPDATE' => '2026-08-31 09:16:00',
        ];
    }
}
