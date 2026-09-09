<?php

namespace Tests\Feature;

use App\Models\DocumentTemplate;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\School;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SpjDocumentNumberService;
use App\UseCases\Spj\SpjDocumentUseCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\SpjScenarioFactory;
use Tests\TestCase;

class SpjSixCategoryE2eTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private FiscalYear $year;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');
        Storage::fake('local');

        Artisan::call('migrate', [
            '--database' => 'school',
            '--path' => 'database/migrations/school',
            '--force' => true,
        ]);

        $fund = FundSource::query()->create([
            'code' => 'BOSP',
            'name' => 'BOSP',
        ]);
        $this->year = FiscalYear::query()->create([
            'year' => 2026,
            'fund_source' => 'BOSP',
            'fund_source_id' => $fund->id,
            'is_active' => true,
        ]);
        DB::connection('school')->table('school_profiles')->insert([
            'fiscal_year_id' => $this->year->id,
            'principal_name' => 'Kepala Sekolah E2E',
            'principal_nip' => '198001012000011001',
            'treasurer_name' => 'Bendahara E2E',
            'treasurer_nip' => '198202022002022002',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->school = School::query()->create([
            'npsn' => '10208183',
            'school_code' => 'SCH-E2E',
            'name' => 'SD Negeri E2E SPJ',
            'address' => 'Jl. Pendidikan No. 1',
        ]);
        $this->user = User::factory()->create([
            'role' => 'ADMIN',
            'school_id' => $this->school->id,
        ]);

        $this->actingAs($this->user);
        $this->withoutMiddleware();
        session()->put([
            'active_school_id' => $this->school->id,
            'active_fiscal_year_id' => $this->year->id,
            'active_fund_source_id' => $fund->id,
        ]);

        $this->createUniversalTemplate();
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    #[DataProvider('canonicalCategories')]
    public function test_each_canonical_category_reaches_final_with_real_preview_and_download(string $category): void
    {
        $transaction = $this->createTransaction($category);

        $this->get(route('transactions.prepare-spj', $transaction->id))
            ->assertRedirect()
            ->assertSessionMissing('error');

        $package = $transaction->spjPackage()->firstOrFail();
        $this->assertSame('DRAFT', $package->status);

        $this->put(route('spj.update', $package->id), $this->packagePayload($category))
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('error');

        $transaction->refresh();
        $this->assertSame($category, $transaction->spj_category);
        $this->assertSame('DRAFT', $package->fresh()->status);

        if (in_array($category, ['BARANG', 'KONSUMSI'], true)) {
            $item = $transaction->items()->firstOrFail();
            $this->post(route('spj.receipts.store', $transaction->id), [
                'receipt_date' => '2026-01-12',
                'notes' => 'Penerimaan barang E2E '.$category,
                'items' => [[
                    'transaction_item_id' => $item->id,
                    'quantity_received' => 1,
                    'amount_received' => 1000,
                ]],
            ])->assertRedirect()->assertSessionMissing('error');
        }

        $this->post(route('spj.ready', $package->id))
            ->assertRedirect()
            ->assertSessionMissing('error');
        $this->assertSame('READY', $package->fresh()->status);

        $this->assignAuxiliaryNumbers($package->fresh(['transaction.goods', 'transaction.workOrder', 'transaction.travels']), $category);

        $this->post(route('spj.assign-number', $package->id))
            ->assertRedirect()
            ->assertSessionMissing('error');

        $package->refresh();
        $this->assertSame('NUMBERED', $package->status);
        $this->assertNotNull($package->document_number);
        $this->assertNotSame('', trim((string) $package->document_number));

        $spjDocument = $package->documents()
            ->where('document_type', 'SPJ')
            ->where('scope_key', 'MAIN')
            ->where('status', 'NUMBERED')
            ->firstOrFail();
        $this->assertSame($package->document_number, $spjDocument->document_number);

        $documentCount = $package->documents()->count();
        $sequenceCount = DB::connection('school')->table('document_number_sequences')->count();

        $preview = app(SpjDocumentUseCase::class)->previewPackage((string) $package->id);
        $this->assertInstanceOf(View::class, $preview);
        $previewHtml = (string) ($preview->getData()['previewHtml'] ?? '');
        $this->assertStringContainsString($category, $previewHtml);
        $this->assertStringContainsString('SD Negeri E2E SPJ', $previewHtml);
        $this->assertStringContainsString((string) $package->document_number, $previewHtml);

        $excel = app(SpjDocumentUseCase::class)->downloadPackageExcel((string) $package->id);
        $this->assertInstanceOf(BinaryFileResponse::class, $excel);
        $excelPath = $excel->getFile()->getPathname();

        try {
            $workbook = IOFactory::load($excelPath);
            $sheet = $workbook->getSheetByName('TPL_RINCIAN');
            $this->assertNotNull($sheet);
            $this->assertSame($category, $sheet->getCell('A1')->getValue());
            $this->assertSame('SD Negeri E2E SPJ', $sheet->getCell('A2')->getValue());
            $this->assertSame($package->document_number, $sheet->getCell('A3')->getValue());
            $this->assertSame('Rp 1.000', $sheet->getCell('A4')->getValue());
        } finally {
            if (is_file($excelPath)) {
                @unlink($excelPath);
            }
        }

        $pdf = app(SpjDocumentUseCase::class)->download((string) $package->id);
        $this->assertInstanceOf(Response::class, $pdf);
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', (string) $pdf->getContent());

        $this->assertSame($documentCount, $package->documents()->count(), 'Preview/download tidak boleh membuat identitas dokumen baru.');
        $this->assertSame($sequenceCount, DB::connection('school')->table('document_number_sequences')->count(), 'Preview/download tidak boleh mengalokasikan sequence baru.');

        foreach ($package->documents()->where('status', 'NUMBERED')->orderBy('id')->get() as $document) {
            $this->post(route('spj.documents.finalize', $document->id))
                ->assertRedirect()
                ->assertSessionMissing('error');
        }

        $package->refresh();
        $this->assertSame('FINAL', $package->status);
        $this->assertFalse($package->isEditable());
        $this->assertNotEmpty($package->snapshot);
        $this->assertSame(0, $package->documents()->where('status', '!=', 'FINAL')->count());
        $this->assertSame($documentCount, $package->documents()->count());

        $actions = DB::connection('school')->table('operational_audit_logs')
            ->where('fiscal_year_id', $this->year->id)
            ->pluck('action')
            ->all();
        $this->assertContains('BUAT_DRAFT', $actions);
        $this->assertContains('PERBARUI_ISIAN', $actions);
        $this->assertContains('PAKET_READY', $actions);
        $this->assertContains('TETAPKAN_NOMOR', $actions);
    }

    /** @return array<string, array{string}> */
    public static function canonicalCategories(): array
    {
        return collect(SpjScenarioFactory::CATEGORIES)
            ->mapWithKeys(fn (string $category): array => [$category => [$category]])
            ->all();
    }

    private function createTransaction(string $category): Transaction
    {
        $honor = $category === 'HONOR_PEGAWAI';
        $sequence = array_search($category, SpjScenarioFactory::CATEGORIES, true) + 1;

        $transaction = Transaction::query()->create([
            'fiscal_year_id' => $this->year->id,
            'fund_source_id' => $this->year->fund_source_id,
            'id_kas_umum' => (string) $sequence,
            'no_bukti' => 'BPU-E2E-'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT),
            'transaction_date' => '2026-01-15',
            'rkas_date' => '2026-01-01',
            'description' => 'Belanja E2E '.$category,
            'activity_code' => '02.01',
            'activity_name' => 'Kegiatan E2E SPJ',
            'account_code' => '5.1.02.01',
            'account_name' => 'Belanja E2E',
            'recipient_name' => 'Penerima BKU E2E',
            'gross_amount' => 1000,
            'ppn' => $honor ? 0 : 100,
            'pph21' => $honor ? 100 : 0,
            'pph21_rate' => $honor ? 10 : 0,
            'tax_total' => 100,
            'net_amount' => 900,
            'spj_category' => 'BARANG',
            'payment_method' => 'tunai',
            'source_status' => 'ACTIVE',
            'requires_reconciliation' => false,
            'source_key' => hash('sha256', 'P0-01-E2E-'.$category),
        ]);

        $transaction->items()->create([
            'source_item_id' => 'ITEM-E2E-'.$sequence,
            'description' => 'Rincian sumber E2E '.$category,
            'item_description' => 'Rincian SPJ E2E '.$category,
            'quantity' => 1,
            'unit' => 'unit',
            'unit_price' => 1000,
            'amount' => 1000,
        ]);

        return $transaction;
    }

    /** @return array<string, mixed> */
    private function packagePayload(string $category): array
    {
        $payload = SpjScenarioFactory::payload($category, 1000);

        if ($category === 'KONSUMSI') {
            $payload = [
                ...$payload,
                'vendor_name' => 'Penyedia Konsumsi E2E',
                'vendor_owner' => 'Pemilik Penyedia Konsumsi E2E',
                'vendor_npwp' => '00.000.000.0-000.000',
                'order_date' => '2026-01-10',
                'bap_date' => '2026-01-11',
                'bast_date' => '2026-01-12',
            ];
        }

        if ($category === 'JASA_LAINNYA') {
            $payload = [
                ...$payload,
                'vendor_name' => 'Penyedia Jasa E2E',
                'vendor_owner' => 'Pemilik Penyedia Jasa E2E',
                'vendor_npwp' => '00.000.000.0-000.000',
            ];
        }

        return $payload;
    }

    private function assignAuxiliaryNumbers(SpjPackage $package, string $category): void
    {
        $documentTypes = match ($category) {
            'BARANG', 'KONSUMSI' => ['PESANAN', 'BAP', 'BAST'],
            'PEMELIHARAAN' => ['SPK', 'RAB'],
            'SPPD' => ['SURAT_TUGAS_PERJALANAN_DINAS'],
            default => [],
        };

        if ($documentTypes === []) {
            return;
        }

        app(SpjDocumentNumberService::class)->assignAutomaticNumbers(
            $package,
            $this->school->school_code ?: $this->school->npsn,
            $this->school->npsn,
            $documentTypes,
        );
    }

    private function createUniversalTemplate(): DocumentTemplate
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('TPL_RINCIAN');
        $sheet->setCellValue('A1', '{{JENIS_SPJ}}');
        $sheet->setCellValue('A2', '{{NAMA_SEKOLAH}}');
        $sheet->setCellValue('A3', '{{NOMOR_DOKUMEN}}');
        $sheet->setCellValue('A4', '{{NILAI_BRUTO}}');
        $sheet->setCellValue('A5', '{{NAMA_PENERIMA}}');

        Storage::disk('local')->makeDirectory('document-templates');
        $relativePath = 'document-templates/p0-01-six-category-e2e.xlsx';
        (new Xlsx($book))->save(Storage::disk('local')->path($relativePath));
        $book->disconnectWorksheets();

        return DocumentTemplate::query()->create([
            'fiscal_year_id' => $this->year->id,
            'document_type' => 'RINCIAN_BELANJA',
            'name' => 'Template P0-01 E2E',
            'format' => 'xlsx',
            'file_path' => $relativePath,
            'applicable_categories' => ['SEMUA'],
            'is_active' => true,
        ]);
    }
}
