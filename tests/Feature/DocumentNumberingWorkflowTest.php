<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Services\SpjDocumentLifecycleService;
use App\Services\SpjDocumentNumberService;
use App\UseCases\Spj\SpjNumberingUseCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DocumentNumberingWorkflowTest extends TestCase
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

    public function test_spj_numbers_follow_transaction_date_even_when_packages_were_created_in_reverse_order(): void
    {
        $year = $this->year();
        $laterPackage = $this->package($year, 'B-002', '2026-01-20', '200');
        $earlierPackage = $this->package($year, 'B-001', '2026-01-05', '900');
        $workflow = app(SpjNumberingUseCase::class);
        $numbers = app(SpjDocumentNumberService::class);

        $ordered = $workflow->orderedPackagesForDocumentType(collect([$laterPackage, $earlierPackage]), 'SPJ');
        $first = $numbers->assign($ordered[0], 'SPJ', $workflow->documentEventDate($ordered[0], 'SPJ'), '10200001');
        $second = $numbers->assign($ordered[1], 'SPJ', $workflow->documentEventDate($ordered[1], 'SPJ'), '10200001');

        $this->assertSame($earlierPackage->id, $ordered[0]->id);
        $this->assertSame($laterPackage->id, $ordered[1]->id);
        $this->assertSame(1, $first->sequence_number);
        $this->assertSame(2, $second->sequence_number);
        $this->assertSame('B-001', $earlierPackage->transaction->fresh()->no_bukti);
        $this->assertSame('B-002', $laterPackage->transaction->fresh()->no_bukti);
    }

    public function test_same_date_spj_numbers_follow_stable_bku_source_order_not_local_package_id(): void
    {
        $year = $this->year();
        $laterSourcePackage = $this->package($year, 'B-200', '2026-02-10', '200');
        $earlierSourcePackage = $this->package($year, 'B-100', '2026-02-10', '100');

        $this->assertLessThan($earlierSourcePackage->id, $laterSourcePackage->id, 'Test setup must create the later BKU source first so local package IDs point the wrong way.');

        $workflow = app(SpjNumberingUseCase::class);
        $numbers = app(SpjDocumentNumberService::class);
        $ordered = $workflow->orderedPackagesForDocumentType(collect([$laterSourcePackage, $earlierSourcePackage]), 'SPJ');
        $first = $numbers->assign($ordered[0], 'SPJ', $workflow->documentEventDate($ordered[0], 'SPJ'), '10200001');
        $second = $numbers->assign($ordered[1], 'SPJ', $workflow->documentEventDate($ordered[1], 'SPJ'), '10200001');

        $this->assertSame($earlierSourcePackage->id, $ordered[0]->id);
        $this->assertSame($laterSourcePackage->id, $ordered[1]->id);
        $this->assertSame(1, $first->sequence_number);
        $this->assertSame(2, $second->sequence_number);
    }

    public function test_non_spj_document_order_uses_its_event_date_then_bku_source_order(): void
    {
        $year = $this->year();
        $packageA = $this->package($year, 'B-010', '2026-01-05', '100');
        $packageB = $this->package($year, 'B-020', '2026-01-10', '200');
        $packageA->transaction->items->first()->goods()->create(['order_date' => '2026-01-25']);
        $packageB->transaction->items->first()->goods()->create(['order_date' => '2026-01-15']);
        $packageA->load('transaction.goods');
        $packageB->load('transaction.goods');

        $workflow = app(SpjNumberingUseCase::class);
        $numbers = app(SpjDocumentNumberService::class);
        $ordered = $workflow->orderedPackagesForDocumentType(collect([$packageA, $packageB]), 'PESANAN');
        $first = $numbers->assign($ordered[0], 'PESANAN', $workflow->documentEventDate($ordered[0], 'PESANAN'), '10200001');
        $second = $numbers->assign($ordered[1], 'PESANAN', $workflow->documentEventDate($ordered[1], 'PESANAN'), '10200001');

        $this->assertSame($packageB->id, $ordered[0]->id);
        $this->assertSame('2026-01-15', $first->document_date->format('Y-m-d'));
        $this->assertSame(1, $first->sequence_number);
        $this->assertSame(2, $second->sequence_number);
    }

    public function test_each_document_type_has_an_independent_sequence_based_on_issuance_order(): void
    {
        $year = $this->year();
        $packageA = $this->package($year, 'A-001', '2026-01-05', '101');
        $packageB = $this->package($year, 'B-001', '2026-01-10', '102');
        $numbers = app(SpjDocumentNumberService::class);

        $orderA = $numbers->assign($packageA, 'ORDER', Carbon::parse('2026-01-05'), '10200001');
        $orderB = $numbers->assign($packageB, 'ORDER', Carbon::parse('2026-01-10'), '10200001');
        $bastB = $numbers->assign($packageB, 'BAST', Carbon::parse('2026-01-20'), '10200001');
        $bastA = $numbers->assign($packageA, 'BAST', Carbon::parse('2026-01-25'), '10200001');

        $this->assertSame(1, $orderA->sequence_number);
        $this->assertSame(2, $orderB->sequence_number);
        $this->assertSame(1, $bastB->sequence_number);
        $this->assertSame(2, $bastA->sequence_number);
        $this->assertStringContainsString('/PESANAN/', $orderA->document_number);
        $this->assertStringContainsString('/BAST/', $bastB->document_number);
    }

    public function test_final_document_is_snapshotted_and_cannot_be_edited_as_a_draft(): void
    {
        $year = $this->year();
        $package = $this->package($year, 'A-001', '2026-01-05', '101');
        $document = app(SpjDocumentNumberService::class)
            ->assign($package, 'SPJ', Carbon::parse('2026-01-05'), '10200001');

        app(SpjDocumentLifecycleService::class)->finalize($document, 1);
        $document->refresh();
        $package->refresh();

        $this->assertSame('FINAL', $document->status);
        $this->assertNotEmpty($document->snapshot);
        $this->assertSame('FINAL', $package->status);
        $this->assertFalse($package->isEditable());
    }

    public function test_cancelled_number_is_reused_by_the_next_document_in_the_same_domain_and_period(): void
    {
        $year = $this->year();
        $originalPackage = $this->package($year, 'A-001', '2026-01-05', '101');
        $otherPackage = $this->package($year, 'B-001', '2026-01-10', '102');
        $numbers = app(SpjDocumentNumberService::class);
        $original = $numbers->assign($originalPackage, 'SPJ', Carbon::parse('2026-01-05'), '10200001');

        app(SpjDocumentLifecycleService::class)->cancel($original, 1, 'Koreksi nomor transaksi');
        $replacement = $numbers->assign($otherPackage, 'SPJ', Carbon::parse('2026-01-10'), '10200001');
        $nextPackage = $this->package($year, 'C-001', '2026-01-15', '103');
        $next = $numbers->assign($nextPackage, 'SPJ', Carbon::parse('2026-01-15'), '10200001');

        $this->assertSame(1, $replacement->sequence_number);
        $this->assertSame($original->document_number, $replacement->document_number);
        $this->assertSame($original->id, $replacement->replaces_document_id);
        $this->assertSame(2, $next->sequence_number);
        $this->assertSame('NUMBERED', $otherPackage->refresh()->status);
    }

    public function test_cancelled_document_can_be_reissued_on_the_same_package_without_inserting_a_duplicate_identity(): void
    {
        $year = $this->year();
        $package = $this->package($year, 'A-001', '2026-01-05', '101');
        $numbers = app(SpjDocumentNumberService::class);
        $original = $numbers->assign($package, 'SPJ', Carbon::parse('2026-01-05'), '10200001');

        app(SpjDocumentLifecycleService::class)->cancel($original, 1, 'Koreksi nomor transaksi');
        $reissued = $numbers->assign($package->refresh(), 'SPJ', Carbon::parse('2026-01-05'), '10200001');

        $this->assertSame($original->id, $reissued->id);
        $this->assertSame(1, $reissued->sequence_number);
        $this->assertSame('NUMBERED', $reissued->status);
        $this->assertNull($reissued->cancelled_at);
        $this->assertSame(1, $package->documents()->where('document_type', 'SPJ')->where('scope_key', 'MAIN')->count());
    }

    private function year(): FiscalYear
    {
        FundSource::query()->firstOrCreate(['id' => 1], ['code' => 'BOSP', 'name' => 'BOSP']);

        return FiscalYear::query()->create(['year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
    }

    private function package(FiscalYear $year, string $proofNumber, string $date, ?string $sourceId = null): SpjPackage
    {
        $transaction = Transaction::query()->create([
            'fiscal_year_id' => $year->id,
            'fund_source_id' => 1,
            'id_kas_umum' => $sourceId,
            'no_bukti' => $proofNumber,
            'transaction_date' => $date,
            'source_key' => $sourceId ? hash('sha256', $sourceId) : null,
        ]);
        $transaction->items()->create([
            'source_item_id' => $sourceId,
            'description' => 'Barang',
            'amount' => 1000,
        ]);

        return $transaction->spjPackage()->create(['status' => 'READY']);
    }
}
