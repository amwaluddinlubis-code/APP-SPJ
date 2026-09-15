<?php

namespace Tests\Unit;

use App\Services\SpjRepeatingRowRenderer;
use App\Services\SpjTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class SpjTemplateWorkbookPreservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_spj_template_generation_preserves_canonical_sheet_page_setup(): void
    {
        Storage::fake('local');

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('TPL_COVER_SPJ');
        $sheet->setCellValue('A1', '{{NAMA_SEKOLAH}}');
        $sheet->getPageSetup()
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageMargins()
            ->setTop(0.4)
            ->setRight(0.5)
            ->setBottom(0.6)
            ->setLeft(0.7);
        $sheet->getPageSetup()->setPrintArea('A1:H40');

        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('TPL_SECOND');
        $sheet2->setCellValue('A1', 'UNCHANGED');
        $sheet2->getPageSetup()
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT);

        $templatePath = Storage::disk('local')->path('templates/preservation-test.xlsx');
        if (! is_dir(dirname($templatePath))) {
            mkdir(dirname($templatePath), 0755, true);
        }
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($templatePath);
        $spreadsheet->disconnectWorksheets();

        $document = (object) [
            'jenis_dokumen' => 'COVER_SPJ',
            'document_number' => 'COVER-TEST',
            'nomor_bukti' => 'BKU-TEST',
            'transaction_document_id' => 1,
            'foreign_id' => 'test',
            'parent' => (object) [
                'fiscal_year' => 2026,
                'sumber_dana' => 'BOSP',
                'school' => (object) [
                    'nama_sekolah' => 'SD TEST',
                    'alamat' => 'Jl. Test',
                ],
                'items' => collect(),
                'taxes' => collect(),
            ],
        ];

        $outputPath = app(SpjTemplateService::class)->generateFromTemplate($templatePath, $document);

        $generated = IOFactory::load(Storage::disk('local')->path($outputPath));

        $this->assertSame('TPL_COVER_SPJ', $generated->getActiveSheet()->getTitle());
        $this->assertSame(
            \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4,
            $generated->getActiveSheet()->getPageSetup()->getPaperSize(),
        );
        $this->assertSame(
            \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE,
            $generated->getActiveSheet()->getPageSetup()->getOrientation(),
        );
        $this->assertSame(1, $generated->getActiveSheet()->getPageSetup()->getFitToWidth());
        $this->assertSame(0, $generated->getActiveSheet()->getPageSetup()->getFitToHeight());
        $this->assertSame('A1:H40', $generated->getActiveSheet()->getPageSetup()->getPrintArea());
        $this->assertEqualsWithDelta(0.4, $generated->getActiveSheet()->getPageMargins()->getTop(), 0.0001);
        $this->assertEqualsWithDelta(0.5, $generated->getActiveSheet()->getPageMargins()->getRight(), 0.0001);
        $this->assertEqualsWithDelta(0.6, $generated->getActiveSheet()->getPageMargins()->getBottom(), 0.0001);
        $this->assertEqualsWithDelta(0.7, $generated->getActiveSheet()->getPageMargins()->getLeft(), 0.0001);
        $this->assertSame('UNCHANGED', $generated->getSheetByName('TPL_SECOND')?->getCell('A1')->getValue());
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
            [
                ['marker' => 'ITEM_NO', 'key' => 'no'],
                ['marker' => 'ITEM_URAIAN', 'key' => 'uraian'],
            ],
            [
                ['no' => 1, 'uraian' => 'ATK'],
                ['no' => 2, 'uraian' => 'Kertas'],
            ],
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
            [
                ['marker' => 'ITEM_NO', 'key' => 'no'],
                ['marker' => 'ITEM_URAIAN', 'key' => 'uraian'],
            ],
            [
                ['no' => 1, 'uraian' => 'ATK'],
                ['no' => 2, 'uraian' => 'Kertas'],
                ['no' => 3, 'uraian' => 'Tinta'],
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
            [
                ['marker' => 'ITEM_NO', 'key' => 'no'],
                ['marker' => 'ITEM_URAIAN', 'key' => 'uraian'],
            ],
            [],
        );

        $this->assertSame('', $sheet->getCell('A10')->getValue());
        $this->assertSame('Item: ', $sheet->getCell('B10')->getValue());
        $this->assertSame('', $sheet->getCell('A11')->getValue());
        $this->assertSame('', $sheet->getCell('B11')->getValue());
        $this->assertSame($beforeMergeCells, $sheet->getMergeCells());
        $this->assertSame($beforeRowHeight, $sheet->getRowDimension(10)->getRowHeight());
    }
}
