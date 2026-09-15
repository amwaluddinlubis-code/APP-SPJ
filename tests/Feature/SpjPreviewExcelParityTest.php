<?php

namespace Tests\Feature;

use App\Models\DocumentTemplate;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\School;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Services\PreviewAlignedSpjTemplateService;
use App\Services\SpjSpreadsheetPdfConverter;
use App\Services\SpjTemplateService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SpjPreviewExcelParityTest extends TestCase
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
            'principal_name' => 'Kepala Sekolah Preview',
            'principal_nip' => '198001012000011001',
            'treasurer_name' => 'Bendahara Preview',
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

    public function test_preview_uses_the_same_canonical_layout_as_generated_excel(): void
    {
        $package = $this->package();
        $school = $this->school();
        [$rincian, $checklist] = $this->templates();

        $converter = new CapturingPreviewPdfConverter;
        $this->app->instance(SpjSpreadsheetPdfConverter::class, $converter);

        $service = app(SpjTemplateService::class);
        $this->assertInstanceOf(PreviewAlignedSpjTemplateService::class, $service);

        $individualPdf = $service->previewTemplatePdfBytes($rincian, $package, $school);
        $this->assertStringStartsWith('%PDF-', (string) $individualPdf);
        $this->assertSame(['TPL_RINCIAN'], array_keys($converter->snapshots[0]));
        $this->assertSame('A1:I34', $converter->snapshots[0]['TPL_RINCIAN']['print_area']);
        $this->assertSame(PageSetup::ORIENTATION_LANDSCAPE, $converter->snapshots[0]['TPL_RINCIAN']['orientation']);
        $this->assertSame('SD Negeri Preview', $converter->snapshots[0]['TPL_RINCIAN']['a1']);

        $templates = collect([$rincian, $checklist]);
        $packagePdf = $service->packagePreviewPdfBytes($templates, $package, $school);
        $this->assertStringStartsWith('%PDF-', $packagePdf);

        $previewSnapshot = $converter->snapshots[1];
        $this->assertSame(['TPL_RINCIAN', 'TPL_CHECKLIST_SPJ'], array_keys($previewSnapshot));

        $excelResponse = $service->downloadPackageExcel($templates, $package, $school);
        $excelPath = $excelResponse->getFile()->getPathname();

        try {
            $workbook = IOFactory::load($excelPath);

            foreach ($previewSnapshot as $sheetName => $expected) {
                $sheet = $workbook->getSheetByName($sheetName);
                $this->assertNotNull($sheet);
                $this->assertSame($expected['print_area'], $sheet->getPageSetup()->getPrintArea());
                $this->assertSame($expected['orientation'], $sheet->getPageSetup()->getOrientation());
                $this->assertEqualsWithDelta($expected['top_margin'], $sheet->getPageMargins()->getTop(), 0.0001);
                $this->assertSame($expected['a1'], (string) $sheet->getCell('A1')->getValue());
            }

            $workbook->disconnectWorksheets();
        } finally {
            @unlink($excelPath);
        }
    }

    private function package(): SpjPackage
    {
        $transaction = Transaction::query()->create([
            'fiscal_year_id' => $this->year->id,
            'fund_source_id' => $this->year->fund_source_id,
            'id_kas_umum' => 'PREVIEW-001',
            'no_bukti' => 'BKU-PREVIEW-001',
            'transaction_date' => '2026-01-10',
            'description' => 'Belanja preview uji',
            'payment_description' => 'Pembayaran preview uji',
            'payment_method' => 'tunai',
            'payment_reference' => 'REF-PREVIEW',
            'activity_code' => '02.01',
            'activity_name' => 'Kegiatan Preview',
            'account_code' => '5.1.02.01',
            'account_name' => 'Belanja Barang',
            'recipient_name' => 'Penerima Preview',
            'vendor_name' => 'Penyedia Preview',
            'vendor_npwp' => '00.000.000.0-000.000',
            'gross_amount' => 1000,
            'ppn' => 100,
            'tax_total' => 100,
            'net_amount' => 900,
            'spj_category' => 'BARANG',
            'source_key' => hash('sha256', 'PREVIEW-PARITY'),
        ]);

        $transaction->items()->create([
            'source_item_id' => 'ITEM-PREVIEW-001',
            'description' => 'Barang preview uji',
            'item_description' => 'Barang preview uji',
            'quantity' => 1,
            'unit' => 'unit',
            'unit_price' => 1000,
            'amount' => 1000,
        ]);

        $package = $transaction->spjPackage()->create([
            'document_number' => '001/SPJ/2026',
            'quarter_code' => 'TW1',
            'semester_code' => 'S1',
            'status' => 'READY',
        ]);

        $transaction->load(['items', 'goods', 'workOrder', 'workers', 'participants.item', 'serviceRecipients']);
        $package->setRelation('transaction', $transaction);

        return $package;
    }

    /** @return array{0:DocumentTemplate,1:DocumentTemplate} */
    private function templates(): array
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->setTitle('PLACEHOLDER_MAP')->setCellValue('A1', 'Teknis');

        $rincian = $book->createSheet()->setTitle('TPL_RINCIAN');
        $rincian->setCellValue('A1', '{{NAMA_SEKOLAH}}');
        $rincian->setCellValue('A2', '{{NOMOR_DOKUMEN}}');
        $rincian->getPageSetup()
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setPrintArea('A1:I34');
        $rincian->getPageMargins()->setTop(0.31)->setLeft(0.22)->setRight(0.22)->setBottom(0.41);

        $checklist = $book->createSheet()->setTitle('TPL_CHECKLIST_SPJ');
        $checklist->setCellValue('A1', '{{NAMA_SEKOLAH}}');
        $checklist->setCellValue('A2', '{{NAMA_KEPALA_SEKOLAH}}');
        $checklist->getPageSetup()
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setPrintArea('A1:F24');
        $checklist->getPageMargins()->setTop(0.44)->setLeft(0.25)->setRight(0.25)->setBottom(0.5);

        Storage::disk('local')->makeDirectory('document-templates');
        $relativePath = 'document-templates/preview-parity-'.uniqid().'.xlsx';
        (new Xlsx($book))->save(Storage::disk('local')->path($relativePath));
        $book->disconnectWorksheets();

        return [
            new DocumentTemplate([
                'fiscal_year_id' => $this->year->id,
                'document_type' => 'RINCIAN_BELANJA',
                'name' => 'Rincian Preview',
                'format' => 'xlsx',
                'file_path' => $relativePath,
                'applicable_categories' => ['SEMUA'],
                'is_active' => true,
            ]),
            new DocumentTemplate([
                'fiscal_year_id' => $this->year->id,
                'document_type' => 'SPJ_CHECKLIST',
                'name' => 'Checklist Preview',
                'format' => 'xlsx',
                'file_path' => $relativePath,
                'applicable_categories' => ['SEMUA'],
                'is_active' => true,
            ]),
        ];
    }

    private function school(): School
    {
        return new School([
            'npsn' => '12345678',
            'school_code' => 'SCH-PREVIEW',
            'name' => 'SD Negeri Preview',
            'address' => 'Jl. Preview No. 1',
            'desa' => 'Desa Preview',
            'district' => 'Kecamatan Preview',
            'regency' => 'Kabupaten Preview',
            'province' => 'Kepulauan Riau',
        ]);
    }
}

class CapturingPreviewPdfConverter extends SpjSpreadsheetPdfConverter
{
    /** @var array<int,array<string,array{print_area:string,orientation:string,top_margin:float,a1:string}>> */
    public array $snapshots = [];

    public function convert(Spreadsheet $spreadsheet): ?string
    {
        $this->snapshots[] = $this->snapshot($spreadsheet);

        return "%PDF-1.4\npackage-preview";
    }

    public function convertFile(string $sourcePath): ?string
    {
        $spreadsheet = IOFactory::load($sourcePath);

        try {
            $this->snapshots[] = $this->snapshot($spreadsheet);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }

        return "%PDF-1.4\nindividual-preview";
    }

    /** @return array<string,array{print_area:string,orientation:string,top_margin:float,a1:string}> */
    private function snapshot(Spreadsheet $spreadsheet): array
    {
        $result = [];

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $result[$sheet->getTitle()] = [
                'print_area' => (string) $sheet->getPageSetup()->getPrintArea(),
                'orientation' => (string) $sheet->getPageSetup()->getOrientation(),
                'top_margin' => (float) $sheet->getPageMargins()->getTop(),
                'a1' => (string) $sheet->getCell('A1')->getValue(),
            ];
        }

        return $result;
    }
}
