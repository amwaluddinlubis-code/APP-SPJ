<?php

namespace Tests\Feature;

use App\Models\DocumentTemplate;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\School;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Services\SpjDocumentTypeRegistry;
use App\Services\SpjTemplateRenderPreflight;
use App\Services\SpjTemplateService;
use App\Services\SpjUnresolvedPlaceholderGuard;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\PhpWord;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class SpjDocumentGeneratorHardeningTest extends TestCase
{
    private FiscalYear $year;

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

        $fund = FundSource::query()->create(['code' => 'BOSP', 'name' => 'BOSP']);
        $this->year = FiscalYear::query()->create([
            'year' => 2026,
            'fund_source' => 'BOSP',
            'fund_source_id' => $fund->id,
            'is_active' => true,
        ]);
        DB::connection('school')->table('school_profiles')->insert([
            'fiscal_year_id' => $this->year->id,
            'principal_name' => 'Kepala Sekolah Uji',
            'principal_nip' => '198001012000011001',
            'treasurer_name' => 'Bendahara Uji',
            'treasurer_nip' => '198202022002022002',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    public function test_generated_xlsx_is_openable_resolves_core_fields_and_does_not_allocate_numbers(): void
    {
        $package = $this->package('BARANG', 1);
        $template = $this->xlsxTemplate([
            'A1' => '{{NAMA_SEKOLAH}}',
            'A2' => '{{NOMOR_DOKUMEN}}',
            'A3' => '{{NOMOR_BUKTI}}',
            'A4' => '{{NILAI_BRUTO}}',
            'A6' => '{{ITEM_NO}}',
            'B6' => '{{ITEM_URAIAN}}',
            'C6' => '{{ITEM_VOLUME}}',
            'D6' => '{{ITEM_SATUAN}}',
            'E6' => '{{ITEM_HARGA_SATUAN}}',
            'F6' => '{{ITEM_JUMLAH}}',
        ]);
        $school = $this->school();
        $documentsBefore = DB::connection('school')->table('spj_documents')->count();
        $sequencesBefore = DB::connection('school')->table('document_number_sequences')->count();

        $response = app(SpjTemplateService::class)->download($template, $package, $school);
        $path = $response->getFile()->getPathname();

        try {
            $generated = IOFactory::load($path);
            $sheet = $generated->getSheetByName('TPL_RINCIAN');

            $this->assertNotNull($sheet);
            $this->assertSame('SD Negeri Generator Uji', $sheet->getCell('A1')->getValue());
            $this->assertSame('001/SPJ/2026', $sheet->getCell('A2')->getValue());
            $this->assertSame('BKU-001', $sheet->getCell('A3')->getValue());
            $this->assertSame('Rp 1.000', $sheet->getCell('A4')->getValue());
            $this->assertSame('1', (string) $sheet->getCell('A6')->getValue());
            $this->assertSame('Barang generator uji', $sheet->getCell('B6')->getValue());
            $this->assertSame([], app(SpjUnresolvedPlaceholderGuard::class)->findInFile('RINCIAN_BELANJA', $path, 'xlsx'));
        } finally {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        $this->assertSame($documentsBefore, DB::connection('school')->table('spj_documents')->count());
        $this->assertSame($sequencesBefore, DB::connection('school')->table('document_number_sequences')->count());
    }

    public function test_generated_docx_is_openable_and_has_no_unresolved_markers(): void
    {
        $package = $this->package('BARANG', 2);
        $template = $this->docxTemplate();
        $response = app(SpjTemplateService::class)->download($template, $package, $this->school());
        $path = $response->getFile()->getPathname();

        try {
            $this->assertGreaterThan(0, filesize($path));
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path) === true);
            $documentXml = (string) $zip->getFromName('word/document.xml');
            $zip->close();

            $this->assertStringContainsString('SD Negeri Generator Uji', $documentXml);
            $this->assertStringContainsString('002/SPJ/2026', $documentXml);
            $this->assertSame([], app(SpjUnresolvedPlaceholderGuard::class)->findInFile('SPJ_CHECKLIST', $path, 'docx'));
        } finally {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    public function test_package_multitemplate_excel_and_pdf_are_real_outputs_without_numbering_side_effects(): void
    {
        $package = $this->package('BARANG', 3);
        $school = $this->school();
        $templates = collect([
            $this->xlsxTemplate([
                'A1' => 'Rincian {{NOMOR_DOKUMEN}}',
                'A2' => '{{NAMA_SEKOLAH}}',
            ]),
            $this->xlsxTemplate([
                'A1' => 'Checklist {{NOMOR_DOKUMEN}}',
                'A2' => '{{NAMA_KEPALA_SEKOLAH}}',
            ], 'SPJ_CHECKLIST', 'TPL_CHECKLIST_SPJ'),
        ]);
        $documentsBefore = DB::connection('school')->table('spj_documents')->count();
        $sequencesBefore = DB::connection('school')->table('document_number_sequences')->count();

        app(SpjTemplateRenderPreflight::class)->assertAllRenderable($templates, $package, $school);
        $excelResponse = app(SpjTemplateService::class)->downloadPackageExcel($templates, $package, $school);
        $excelPath = $excelResponse->getFile()->getPathname();

        try {
            $workbook = IOFactory::load($excelPath);
            $this->assertSame(['TPL_RINCIAN', 'TPL_CHECKLIST_SPJ'], $workbook->getSheetNames());
            $this->assertSame('Rincian 003/SPJ/2026', $workbook->getSheetByName('TPL_RINCIAN')?->getCell('A1')->getValue());
            $this->assertSame('Checklist 003/SPJ/2026', $workbook->getSheetByName('TPL_CHECKLIST_SPJ')?->getCell('A1')->getValue());
        } finally {
            if (is_file($excelPath)) {
                @unlink($excelPath);
            }
        }

        $pdfResponse = app(SpjTemplateService::class)->downloadPackagePdf($templates, $package, $school);
        $pdf = (string) $pdfResponse->getContent();
        $this->assertSame('application/pdf', $pdfResponse->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(1000, strlen($pdf));

        $this->assertSame($documentsBefore, DB::connection('school')->table('spj_documents')->count());
        $this->assertSame($sequencesBefore, DB::connection('school')->table('document_number_sequences')->count());
    }

    public function test_preflight_rejects_unresolved_placeholder_before_preview_or_package_output(): void
    {
        $package = $this->package('BARANG', 4);
        $template = $this->xlsxTemplate([
            'A1' => '{{NOMOR_DOKUMEN}}',
            'A2' => '{{UNKNOWN_RELEASE_MARKER}}',
        ]);

        try {
            app(SpjTemplateRenderPreflight::class)->assertRenderable($template, $package, $this->school());
            $this->fail('Template dengan marker yang tidak dikenal seharusnya ditolak oleh preflight.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('UNKNOWN_RELEASE_MARKER', $exception->getMessage());
            $this->assertStringContainsString('placeholder yang belum terisi', $exception->getMessage());
        }
    }

    public function test_all_six_canonical_categories_expose_resolved_common_generator_values(): void
    {
        $service = app(SpjTemplateService::class);
        $school = $this->school();

        foreach (SpjDocumentTypeRegistry::categories() as $index => $category) {
            $values = $service->placeholders($this->package($category, 10 + $index), $school);

            $this->assertSame($category, $values['JENIS_SPJ']);
            $this->assertSame('SD Negeri Generator Uji', $values['NAMA_SEKOLAH']);
            $this->assertNotSame('', trim($values['NOMOR_DOKUMEN']));
            $this->assertNotSame('', trim($values['NOMOR_BUKTI']));
            $this->assertSame('Rp 1.000', $values['NILAI_BRUTO']);
            $this->assertSame('Rp 100', $values['TOTAL_PAJAK']);
            $this->assertSame('Rp 900', $values['NILAI_DIBAYARKAN']);
            $this->assertSame('Kepala Sekolah Uji', $values['NAMA_KEPALA_SEKOLAH']);
            $this->assertSame('Bendahara Uji', $values['NAMA_BENDAHARA_BOSP']);
        }
    }

    private function package(string $category, int $suffix): SpjPackage
    {
        $transaction = Transaction::query()->create([
            'fiscal_year_id' => $this->year->id,
            'fund_source_id' => $this->year->fund_source_id,
            'id_kas_umum' => (string) $suffix,
            'no_bukti' => 'BKU-'.str_pad((string) $suffix, 3, '0', STR_PAD_LEFT),
            'transaction_date' => '2026-01-10',
            'description' => 'Belanja generator uji',
            'payment_description' => 'Pembayaran generator uji',
            'payment_method' => 'tunai',
            'payment_reference' => 'REF-'.$suffix,
            'activity_code' => '02.01',
            'activity_name' => 'Kegiatan Generator',
            'account_code' => '5.1.02.01',
            'account_name' => 'Belanja Barang',
            'recipient_name' => 'Penerima Uji',
            'vendor_name' => 'Penyedia Uji',
            'vendor_npwp' => '00.000.000.0-000.000',
            'gross_amount' => 1000,
            'ppn' => 100,
            'tax_total' => 100,
            'net_amount' => 900,
            'spj_category' => $category,
            'source_key' => hash('sha256', 'GENERATOR-'.$category.'-'.$suffix),
        ]);
        $transaction->items()->create([
            'source_item_id' => 'ITEM-'.$suffix,
            'description' => 'Barang generator uji',
            'item_description' => 'Barang generator uji',
            'quantity' => 1,
            'unit' => 'unit',
            'unit_price' => 1000,
            'amount' => 1000,
        ]);

        return $transaction->spjPackage()->create([
            'document_number' => str_pad((string) $suffix, 3, '0', STR_PAD_LEFT).'/SPJ/2026',
            'quarter_code' => 'TW1',
            'semester_code' => 'S1',
            'status' => 'READY',
        ]);
    }

    /** @param array<string,string> $cells */
    private function xlsxTemplate(
        array $cells,
        string $documentType = 'RINCIAN_BELANJA',
        string $sheetName = 'TPL_RINCIAN',
    ): DocumentTemplate {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle($sheetName);
        foreach ($cells as $coordinate => $value) {
            $sheet->setCellValue($coordinate, $value);
        }

        Storage::disk('local')->makeDirectory('document-templates');
        $relativePath = 'document-templates/generator-'.uniqid().'.xlsx';
        (new Xlsx($book))->save(Storage::disk('local')->path($relativePath));
        $book->disconnectWorksheets();

        return new DocumentTemplate([
            'fiscal_year_id' => $this->year->id,
            'document_type' => $documentType,
            'name' => 'Template Generator Uji',
            'format' => 'xlsx',
            'file_path' => $relativePath,
            'applicable_categories' => ['SEMUA'],
            'is_active' => true,
        ]);
    }

    private function docxTemplate(): DocumentTemplate
    {
        $word = new PhpWord;
        $section = $word->addSection();
        $section->addText('{{NAMA_SEKOLAH}}');
        $section->addText('{{NOMOR_DOKUMEN}}');

        Storage::disk('local')->makeDirectory('document-templates');
        $relativePath = 'document-templates/generator-'.uniqid().'.docx';
        WordIOFactory::createWriter($word, 'Word2007')->save(Storage::disk('local')->path($relativePath));

        return new DocumentTemplate([
            'fiscal_year_id' => $this->year->id,
            'document_type' => 'SPJ_CHECKLIST',
            'name' => 'Template Word Generator Uji',
            'format' => 'docx',
            'file_path' => $relativePath,
            'applicable_categories' => ['SEMUA'],
            'is_active' => true,
        ]);
    }

    private function school(): School
    {
        return new School([
            'npsn' => '12345678',
            'school_code' => 'SCH-UJI',
            'name' => 'SD Negeri Generator Uji',
            'address' => 'Jl. Pendidikan No. 1',
            'desa' => 'Desa Uji',
            'district' => 'Kecamatan Uji',
            'regency' => 'Kabupaten Uji',
            'province' => 'Sumatera Utara',
        ]);
    }
}
