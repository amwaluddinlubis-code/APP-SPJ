<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Services\SpjDocumentLifecycleService;
use App\Services\SpjDocumentNumberService;
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

    public function test_each_document_type_has_an_independent_sequence_based_on_issuance_order(): void
    {
        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        $year = FiscalYear::query()->create(['year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
        $packageA = $this->package($year, 'A-001', '2026-01-05');
        $packageB = $this->package($year, 'B-001', '2026-01-10');
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
        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        $year = FiscalYear::query()->create(['year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
        $package = $this->package($year, 'A-001', '2026-01-05');
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
        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        $year = FiscalYear::query()->create(['year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
        $originalPackage = $this->package($year, 'A-001', '2026-01-05');
        $otherPackage = $this->package($year, 'B-001', '2026-01-10');
        $numbers = app(SpjDocumentNumberService::class);
        $original = $numbers->assign($originalPackage, 'SPJ', Carbon::parse('2026-01-05'), '10200001');

        app(SpjDocumentLifecycleService::class)->cancel($original, 1, 'Koreksi nomor transaksi');
        $replacement = $numbers->assign($otherPackage, 'SPJ', Carbon::parse('2026-01-10'), '10200001');
        $nextPackage = $this->package($year, 'C-001', '2026-01-15');
        $next = $numbers->assign($nextPackage, 'SPJ', Carbon::parse('2026-01-15'), '10200001');

        $this->assertSame(1, $replacement->sequence_number);
        $this->assertSame($original->document_number, $replacement->document_number);
        $this->assertSame($original->id, $replacement->replaces_document_id);
        $this->assertSame(2, $next->sequence_number);
        $this->assertSame('NUMBERED', $otherPackage->refresh()->status);
    }

    public function test_cancelled_document_can_be_reissued_on_the_same_package_without_inserting_a_duplicate_identity(): void
    {
        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        $year = FiscalYear::query()->create(['year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
        $package = $this->package($year, 'A-001', '2026-01-05');
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

    private function package(FiscalYear $year, string $proofNumber, string $date): SpjPackage
    {
        $transaction = Transaction::query()->create([
            'fiscal_year_id' => $year->id,
            'fund_source_id' => 1,
            'no_bukti' => $proofNumber,
            'transaction_date' => $date,
        ]);
        $transaction->items()->create(['description' => 'Barang', 'amount' => 1000]);

        return $transaction->spjPackage()->create(['status' => 'READY']);
    }
}
