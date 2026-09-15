<?php

namespace Tests\Unit;

use App\Models\DocumentTemplate;
use App\Models\School;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Services\ExtendedSpjTemplateService;
use App\Services\SpjDocumentTypeRegistry;
use App\Services\SpjRepeatingRowRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use Tests\TestCase;

class SpjTemplateWorkbookPreservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_spj_template_generation_preserves_canonical_sheet_page_setup(): void
    {
        Storage::fake('local');

        $definition = SpjDocumentTypeRegistry::definition(SpjDocumentTypeRegistry::SPJ_COVER);
        $this->assertNotNull($definition);
        $canonicalSheet = (string) $definition['sheet'];

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($canonicalSheet);
        $sheet->setCellValue('A1', '{{NAMA_SEKOLAH}}');
        $sheet->getPageSetup()
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageMargins()
            ->setTop(0.4)
            ->setRight(0.5)
            ->setBottom(0.6)
            ->setLeft(0.7);
        $sheet->getPageSetup()->setPrintArea('A1:H40');

        $secondSheet = $spreadsheet->createSheet();
        $secondSheet->setTitle('TPL_SECOND');
        $secondSheet->setCellValue('A1', 'UNCHANGED');

        $templateRelativePath = 'templates/preservation-test.xlsx';
        $templatePath = Storage::disk('local')->path($templateRelativePath);
        if (! is_dir(dirname($templatePath))) {
            mkdir(dirname($templatePath), 0755, true);
        }
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($templatePath);
        $spreadsheet->disconnectWorksheets();

        $template = (new DocumentTemplate)->forceFill([
            'document_type' => SpjDocumentTypeRegistry::SPJ_COVER,
            'format' => 'xlsx',
            'file_path' => $templateRelativePath,
        ]);
        $transaction = new Transaction;
        $transaction->setRelation('items', collect());
        $transaction->setRelation('workers', collect());
        $package = (new SpjPackage)->forceFill(['document_number' => 'COVER-TEST']);
        $package->setRelation('transaction', $transaction);
        $school = new School;

        $service = new class extends ExtendedSpjTemplateService
        {
            public function placeholders(SpjPackage $package, School $school): array
            {
                return ['NAMA_SEKOLAH' => 'SD TEST'];
            }

            public function renderCanonical(
                DocumentTemplate $template,
                SpjPackage $package,
                School $school,
            ): Spreadsheet {
                return $this->canonicalSpreadsheet($template, $package, $school);
            }
        };

        $rendered = $service->renderCanonical($template, $package, $school);
        $outputPath = Storage::disk('local')->path('generated/preservation-output.xlsx');
        if (! is_dir(dirname($outputPath))) {
            mkdir(dirname($outputPath), 0755, true);
        }
        IOFactory::createWriter($rendered, 'Xlsx')->save($outputPath);
        $rendered->disconnectWorksheets();

        $generated = IOFactory::load($outputPath);

        try {
            $generatedSheet = $generated->getActiveSheet();

            $this->assertSame(1, $generated->getSheetCount());
            $this->assertSame($canonicalSheet, $generatedSheet->getTitle());
            $this->assertSame('SD TEST', $generatedSheet->getCell('A1')->getValue());
            $this->assertSame(PageSetup::PAPERSIZE_A4, $generatedSheet->getPageSetup()->getPaperSize());
            $this->assertSame(PageSetup::ORIENTATION_LANDSCAPE, $generatedSheet->getPageSetup()->getOrientation());
            $this->assertSame(1, $generatedSheet->getPageSetup()->getFitToWidth());
            $this->assertSame(0, $generatedSheet->getPageSetup()->getFitToHeight());
            $this->assertSame('A1:H40', str_replace('$', '', $generatedSheet->getPageSetup()->getPrintArea()));
            $this->assertEqualsWithDelta(0.4, $generatedSheet->getPageMargins()->getTop(), 0.0001);
            $this->assertEqualsWithDelta(0.5, $generatedSheet->getPageMargins()->getRight(), 0.0001);
            $this->assertEqualsWithDelta(0.6, $generatedSheet->getPageMargins()->getBottom(), 0.0001);
            $this->assertEqualsWithDelta(0.7, $generatedSheet->getPageMargins()->getLeft(), 0.0001);
            $this->assertNull($generated->getSheetByName('TPL_SECOND'));
        } finally {
            $generated->disconnectWorksheets();
        }
    }

    public function test_repeating_rows_can_copy_inserted_template_rows_without_lost_coordinates(): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', '{{ITEM_NO}}');
        $sheet->setCellValue('B1', '{{ITEM_URAIAN}}');

        app(SpjRepeatingRowRenderer::class)->render(
            $sheet,
            '{{ITEM_NO}}',
            'ITEM_',
            3,
            fn (int $index): array => [
                'ITEM_NO' => $index,
                'ITEM_URAIAN' => 'Item '.$index,
            ],
        );

        $this->assertSame('1', (string) $sheet->getCell('A1')->getValue());
        $this->assertSame('Item 2', $sheet->getCell('B2')->getValue());
        $this->assertSame('Item 3', $sheet->getCell('B3')->getValue());
    }

    public function test_repeating_row_renderer_uses_existing_template_rows_without_inserting_extra_rows(): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A10', '{{ITEM_NO}}');
        $sheet->setCellValue('B10', '{{ITEM_URAIAN}}');
        $sheet->setCellValue('A11', '{{ITEM_NO}}');
        $sheet->setCellValue('B11', '{{ITEM_URAIAN}}');

        app(SpjRepeatingRowRenderer::class)->render(
            $sheet,
            '{{ITEM_NO}}',
            'ITEM_',
            2,
            fn (int $index): array => $index === 1
                ? ['ITEM_NO' => 1, 'ITEM_URAIAN' => 'ATK']
                : ['ITEM_NO' => 2, 'ITEM_URAIAN' => 'Kertas'],
        );

        $this->assertSame(11, $sheet->getHighestDataRow());
        $this->assertSame('1', $sheet->getCell('A10')->getValue());
        $this->assertSame('ATK', $sheet->getCell('B10')->getValue());
        $this->assertSame('2', $sheet->getCell('A11')->getValue());
        $this->assertSame('Kertas', $sheet->getCell('B11')->getValue());
    }

    public function test_repeating_row_renderer_inserts_only_overflow_rows_and_carries_horizontal_merge_and_height(): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A10', '{{ITEM_NO}}');
        $sheet->setCellValue('B10', '{{ITEM_URAIAN}}');
        $sheet->mergeCells('B10:C10');
        $sheet->getRowDimension(10)->setRowHeight(26);

        app(SpjRepeatingRowRenderer::class)->render(
            $sheet,
            '{{ITEM_NO}}',
            'ITEM_',
            3,
            fn (int $index): array => [
                'ITEM_NO' => $index,
                'ITEM_URAIAN' => ['ATK', 'Kertas', 'Tinta'][$index - 1],
            ],
        );

        $this->assertSame('1', $sheet->getCell('A10')->getValue());
        $this->assertSame('2', $sheet->getCell('A11')->getValue());
        $this->assertSame('3', $sheet->getCell('A12')->getValue());
        $this->assertContains('B11:C11', $sheet->getMergeCells());
        $this->assertContains('B12:C12', $sheet->getMergeCells());
        $this->assertEqualsWithDelta(26.0, $sheet->getRowDimension(11)->getRowHeight(), 0.0001);
        $this->assertEqualsWithDelta(26.0, $sheet->getRowDimension(12)->getRowHeight(), 0.0001);
    }

    public function test_repeating_row_renderer_clears_empty_repeat_markers_without_changing_template_layout(): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A10', '{{ITEM_NO}}');
        $sheet->setCellValue('B10', 'Item: {{ITEM_URAIAN}}');
        $sheet->setCellValue('A11', '{{ITEM_NO}}');
        $sheet->setCellValue('B11', '{{ITEM_URAIAN}}');
        $sheet->mergeCells('B10:C10');
        $sheet->getRowDimension(10)->setRowHeight(26);

        $beforeMergeCells = $sheet->getMergeCells();
        $beforeRowHeight = $sheet->getRowDimension(10)->getRowHeight();

        app(SpjRepeatingRowRenderer::class)->render(
            $sheet,
            '{{ITEM_NO}}',
            'ITEM_',
            0,
            fn (int $index): array => [],
        );

        $this->assertSame('', $sheet->getCell('A10')->getValue());
        $this->assertSame('Item: ', $sheet->getCell('B10')->getValue());
        $this->assertSame('', $sheet->getCell('A11')->getValue());
        $this->assertSame('', $sheet->getCell('B11')->getValue());
        $this->assertSame($beforeMergeCells, $sheet->getMergeCells());
        $this->assertSame($beforeRowHeight, $sheet->getRowDimension(10)->getRowHeight());
    }
}
