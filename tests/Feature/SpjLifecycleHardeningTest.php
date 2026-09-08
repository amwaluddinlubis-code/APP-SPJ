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
use RuntimeException;
use Tests\TestCase;

class SpjLifecycleHardeningTest extends TestCase
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

    public function test_numbering_same_identity_twice_is_idempotent(): void
    {
        $package = $this->package();
        $numbers = app(SpjDocumentNumberService::class);

        $first = $numbers->assign($package, 'SPJ', Carbon::parse('2026-01-10'), '10208183');
        $second = $numbers->assign($package->fresh(), 'SPJ', Carbon::parse('2026-01-10'), '10208183');

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->document_number, $second->document_number);
        $this->assertSame(1, $package->documents()->where('document_type', 'SPJ')->where('scope_key', 'MAIN')->count());
        $this->assertSame(1, DB::connection('school')->table('document_number_sequences')->value('last_number'));
    }

    public function test_cancel_requires_reason_and_preserves_number_history(): void
    {
        $package = $this->package();
        $document = app(SpjDocumentNumberService::class)
            ->assign($package, 'SPJ', Carbon::parse('2026-01-10'), '10208183');
        $originalNumber = $document->document_number;
        $lifecycle = app(SpjDocumentLifecycleService::class);

        try {
            $lifecycle->cancel($document, 1, '');
            $this->fail('Blank cancellation reason was accepted.');
        } catch (\InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $lifecycle->cancel($document->fresh(), 1, 'Koreksi dokumen');
        $document->refresh();
        $package->refresh();

        $this->assertSame('CANCELLED', $document->status);
        $this->assertSame($originalNumber, $document->document_number);
        $this->assertSame('Koreksi dokumen', $document->cancellation_reason);
        $this->assertNotNull($document->cancelled_at);
        $this->assertSame('CANCELLED', $package->status);
        $this->assertNull($package->document_number);
    }

    public function test_cancelled_package_can_be_unlocked_without_deleting_cancelled_document_history(): void
    {
        $package = $this->package();
        $document = app(SpjDocumentNumberService::class)
            ->assign($package, 'SPJ', Carbon::parse('2026-01-10'), '10208183');
        $originalId = $document->id;
        $originalNumber = $document->document_number;
        $lifecycle = app(SpjDocumentLifecycleService::class);

        $lifecycle->cancel($document, 1, 'Perlu koreksi');
        $lifecycle->unlock($package->fresh(), 1, 'Perbaiki data sumber dokumen');

        $package->refresh();
        $historical = $package->documents()->findOrFail($originalId);

        $this->assertSame('DRAFT', $package->status);
        $this->assertTrue($package->isEditable());
        $this->assertSame('CANCELLED', $historical->status);
        $this->assertSame($originalNumber, $historical->document_number);
        $this->assertSame('Perbaiki data sumber dokumen', $package->unlock_reason);
    }

    public function test_final_package_cannot_be_unlocked_directly(): void
    {
        $package = $this->package();
        $document = app(SpjDocumentNumberService::class)
            ->assign($package, 'SPJ', Carbon::parse('2026-01-10'), '10208183');
        $lifecycle = app(SpjDocumentLifecycleService::class);
        $lifecycle->finalize($document, 1);

        $this->assertSame('FINAL', $package->fresh()->status);
        $this->assertFalse($package->fresh()->isEditable());

        $this->expectException(RuntimeException::class);
        $lifecycle->unlock($package->fresh(), 1, 'Tidak boleh langsung membuka FINAL');
    }

    public function test_preview_and_download_use_case_does_not_reference_number_allocator(): void
    {
        $source = file_get_contents(app_path('UseCases/Spj/SpjDocumentUseCase.php'));

        $this->assertIsString($source);
        $this->assertStringNotContainsString('SpjDocumentNumberService', $source);
        $this->assertStringNotContainsString('assignAutomaticNumbers(', $source);
        $this->assertStringNotContainsString('->assignNumber(', $source);
    }

    private function package(): SpjPackage
    {
        $fund = FundSource::query()->create(['code' => 'BOSP', 'name' => 'BOSP']);
        $year = FiscalYear::query()->create(['year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => $fund->id]);
        $transaction = Transaction::query()->create([
            'fiscal_year_id' => $year->id,
            'fund_source_id' => $fund->id,
            'id_kas_umum' => '100',
            'no_bukti' => 'BKU-001',
            'transaction_date' => '2026-01-10',
            'source_key' => hash('sha256', 'SPJ-LIFECYCLE-HARDENING'),
        ]);
        $transaction->items()->create([
            'source_item_id' => '100',
            'description' => 'Barang uji',
            'item_description' => 'Barang uji',
            'amount' => 1000,
        ]);

        return $transaction->spjPackage()->create(['status' => 'READY']);
    }
}
