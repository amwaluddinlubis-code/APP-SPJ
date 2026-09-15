<?php

namespace Tests\Unit;

use App\Services\ExtendedSpjTemplateService;
use App\Services\SpjRepeatingRowRenderer;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class SpjTemplateWorkbookPreservationTest extends TestCase
{
    public function test_canonical_sheet_pruning_preserves_template_layout_and_print_settings(): void
    {
        $workbook = new Spreadsheet;
        $workbook->getActiveSheet()->setTitle('PLACEHOLDER_MAP')->setCellValue('A1', 'Teknis');
        $sheet = $workbook->createSheet()->setTitle('TPL_RINCIAN');
        $sheet->setCellValue('A1', 'Rincian Belanja');
        $sheet->mergeCells('A1:I1');
        $sheet->getStyle('A1')->getFont()->setName('Arial')->setSize(11)->setBold(true);
        $sheet->getColumnDimension('B')->setWidth(24.5);
        $sheet->getRowDimension(5)->setRowHeight(27);
        $sheet->getPageSetup()
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setPrintArea('A1:I34');
        $sheet->getPageMargins()
            ->setTop(0.3)
            ->setRight(0.25)
            ->setBottom(0.4)
            ->setLeft(0.25)
            ->setHeader(0.15)
            ->setFooter(0.15);
        $sheet->getHeaderFooter()
            ->setOddHeader('&C{{NOMOR_DOKUMEN}}')
            ->setOddFooter('&RHalaman &P dari &N');

        $service = new ExtendedSpjTemplateService;
        $method = new ReflectionMethod($service, 'retainOnlyCanonicalWorksheet');
        $preserved = $method->invoke($service, $workbook, 'TPL_RINCIAN');

        $path = sys_get_temp_dir().'/spj-template-preservation-'.uniqid('', true).'.xlsx';

        try {
            (new Xlsx($preserved))->save($path);
            $reloaded = IOFactory::load($path);
            $actual = $reloaded->getSheet(0);

            $this->assertSame(1, $reloaded->getSheetCount());
            $this->assertSame('TPL_RINCIAN', $actual->getTitle());
            $this->assertSame(PageSetup::PAPERSIZE_A4, $actual->getPageSetup()->getPaperSize());
            $this->assertSame(PageSetup::ORIENTATION_PORTRAIT, $actual->getPageSetup()->getOrientation());
            $this->assertTrue($actual->getPageSetup()->getFitToPage());
            $this->assertSame(1, $actual->getPageSetup()->getFitToWidth());
            $this->assertSame(0, $actual->getPageSetup()->getFitToHeight());
            $this->assertSame('A1:I34', $actual->getPageSetup()->getPrintArea());
            $this->assertEqualsWithDelta(0.3, $actual->getPageMargins()->getTop(), 0.0001);
            $this->assertEqualsWithDelta(0.25, $actual->getPageMargins()->getRight(), 0.0001);
            $this->assertEqualsWithDelta(0.4, $actual->getPageMargins()->getBottom(), 0.0001);
            $this->assertEqualsWithDelta(0.25, $actual->getPageMargins()->getLeft(), 0.0001);
            $this->assertEqualsWithDelta(0.15, $actual->getPageMargins()->getHeader(), 0.0001);
            $this->assertEqualsWithDelta(0.15, $actual->getPageMargins()->getFooter(), 0.0001);
            $this->assertSame('&C{{NOMOR_DOKUMEN}}', $actual->getHeaderFooter()->getOddHeader());
            $this->assertSame('&RHalaman &P dari &N', $actual->getHeaderFooter()->getOddFooter());
            $this->assertEqualsWithDelta(24.5, $actual->getColumnDimension('B')->getWidth(), 0.0001);
            $this->assertEqualsWithDelta(27.0, $actual->getRowDimension(5)->getRowHeight(), 0.0001);
            $this->assertContains('A1:I1', array_values($actual->getMergeCells()));
            $this->assertSame('Arial', $actual->getStyle('A1')->getFont()->getName());
            $this->assertEqualsWithDelta(11.0, $actual->getStyle('A1')->getFont()->getSize(), 0.0001);
            $this->assertTrue($actual->getStyle('A1')->getFont()->getBold());

            $reloaded->disconnectWorksheets();
        } finally {
            @unlink($path);
            $preserved->disconnectWorksheets();
        }
    }

    public function test_equal_placeholders_inside_one_merged_range_are_resolved_at_the_anchor_without_unmerging(): void
    {
        $workbook = new Spreadsheet;
        $sheet = $workbook->getActiveSheet()->setTitle('TPL_RAB_PEMELIHARAAN');
        $sheet->mergeCells('G16:H16');
        $sheet->setCellValue('G16', '{{TOTAL_RAB}}');
        $sheet->setCellValue('H16', '{{NILAI_PEKERJAAN}}');

        $service = new ExtendedSpjTemplateService;
        $method = new ReflectionMethod($service, 'resolveMergedPlaceholderAnchors');
        $method->invoke($service, $sheet, [
            '{{TOTAL_RAB}}' => '125000',
            '{{NILAI_PEKERJAAN}}' => '125000',
        ]);

        $this->assertSame('125000', (string) $sheet->getCell('G16')->getValue());
        $this->assertSame('', (string) $sheet->getCell('H16')->getValue());
        $this->assertContains('G16:H16', array_values($sheet->getMergeCells()));

        $workbook->disconnectWorksheets();
    }

    public function test_conflicting_placeholders_inside_one_merged_range_fail_without_changing_the_template_merge(): void
    {
        $workbook = new Spreadsheet;
        $sheet = $workbook->getActiveSheet()->setTitle('TPL_RAB_PEMELIHARAAN');
        $sheet->mergeCells('G16:H16');
        $sheet->setCellValue('G16', '{{TOTAL_RAB}}');
        $sheet->setCellValue('H16', '{{NILAI_PEKERJAAN}}');

        $service = new ExtendedSpjTemplateService;
        $method = new ReflectionMethod($service, 'resolveMergedPlaceholderAnchors');

        try {
            $method->invoke($service, $sheet, [
                '{{TOTAL_RAB}}' => '125000',
                '{{NILAI_PEKERJAAN}}' => '100000',
            ]);
            $this->fail('Merged placeholder dengan nilai berbeda harus ditolak.');
        } catch (\Throwable $exception) {
            $actual = $exception->getPrevious() ?? $exception;
            $this->assertInstanceOf(RuntimeException::class, $actual);
            $this->assertStringContainsString('menghasilkan nilai berbeda', $actual->getMessage());
            $this->assertContains('G16:H16', array_values($sheet->getMergeCells()));
        } finally {
            $workbook->disconnectWorksheets();
        }
    }

    public function test_canonical_pipeline_normalizes_non_anchor_merged_placeholders_in_memory(): void
    {
        $workbook = new Spreadsheet;
        $sheet = $workbook->getActiveSheet()->setTitle('TPL_RAB_PEMELIHARAAN');
        $sheet->mergeCells('G16:H16');
        $sheet->setCellValue('G16', '{{TOTAL_RAB}}');
        $sheet->setCellValue('H16', '{{NILAI_PEKERJAAN}}');

        $service = new ExtendedSpjTemplateService;
        $normalize = new ReflectionMethod($service, 'normalizeMergedAnchorPlaceholders');
        $resolve = new ReflectionMethod($service, 'resolveMergedPlaceholderAnchors');

        $normalize->invoke($service, $sheet);
        $resolve->invoke($service, $sheet, [
            '{{TOTAL_RAB}}' => '125000',
            '{{NILAI_PEKERJAAN}}' => '100000',
        ]);

        $this->assertSame('125000', (string) $sheet->getCell('G16')->getValue());
        $this->assertSame('', (string) $sheet->getCell('H16')->getValue());
        $this->assertContains('G16:H16', array_values($sheet->getMergeCells()));

        $workbook->disconnectWorksheets();
    }

    public function test_repeating_row_renderer_clones_merge_formula_style_and_row_dimension(): void
    {
        $workbook = new Spreadsheet;
        $sheet = $workbook->getActiveSheet()->setTitle('TPL_RINCIAN');
        $sheet->setCellValue('A5', '{{ITEM_NO}}');
        $sheet->setCellValue('B5', '{{ITEM_URAIAN}}');
        $sheet->setCellValue('D5', '=E5*F5');
        $sheet->setCellValue('E5', '{{ITEM_VOLUME}}');
        $sheet->setCellValue('F5', '{{ITEM_HARGA_SATUAN}}');
        $sheet->mergeCells('B5:C5');
        $sheet->getStyle('B5')->getFont()->setBold(true);
        $sheet->getRowDimension(5)->setRowHeight(24);

        $renderer = new SpjRepeatingRowRenderer;
        $renderer->render(
            $sheet,
            '{{ITEM_NO}}',
            'ITEM_',
            3,
            fn (int $index): array => [
                'ITEM_NO' => (string) $index,
                'ITEM_URAIAN' => 'Barang '.$index,
                'ITEM_VOLUME' => (string) $index,
                'ITEM_HARGA_SATUAN' => '1000',
            ],
        );

        $this->assertSame('1', (string) $sheet->getCell('A5')->getValue());
        $this->assertSame('2', (string) $sheet->getCell('A6')->getValue());
        $this->assertSame('3', (string) $sheet->getCell('A7')->getValue());
        $this->assertSame('Barang 2', (string) $sheet->getCell('B6')->getValue());
        $this->assertSame('=E6*F6', (string) $sheet->getCell('D6')->getValue());
        $this->assertSame('=E7*F7', (string) $sheet->getCell('D7')->getValue());
        $this->assertContains('B5:C5', array_values($sheet->getMergeCells()));
        $this->assertContains('B6:C6', array_values($sheet->getMergeCells()));
        $this->assertContains('B7:C7', array_values($sheet->getMergeCells()));
        $this->assertTrue($sheet->getStyle('B6')->getFont()->getBold());
        $this->assertEqualsWithDelta(24.0, $sheet->getRowDimension(6)->getRowHeight(), 0.0001);
        $this->assertEqualsWithDelta(24.0, $sheet->getRowDimension(7)->getRowHeight(), 0.0001);

        $workbook->disconnectWorksheets();
    }

    public function test_repeating_row_renderer_reuses_preallocated_rows_and_clears_unused_markers(): void
    {
        $workbook = new Spreadsheet;
        $sheet = $workbook->getActiveSheet()->setTitle('TPL_RINCIAN');
        $sheet->setCellValue('A5', '{{ITEM_NO}}');
        $sheet->setCellValue('B5', '{{ITEM_URAIAN}}');
        $sheet->setCellValue('A6', '{{ITEM_NO}}');
        $sheet->setCellValue('B6', '{{ITEM_URAIAN}}');

        $renderer = new SpjRepeatingRowRenderer;
        $renderer->render(
            $sheet,
            '{{ITEM_NO}}',
            'ITEM_',
            1,
            fn (int $index): array => [
                'ITEM_NO' => (string) $index,
                'ITEM_URAIAN' => 'Satu item',
            ],
        );

        $this->assertSame('1', (string) $sheet->getCell('A5')->getValue());
        $this->assertSame('Satu item', (string) $sheet->getCell('B5')->getValue());
        $this->assertSame('', (string) $sheet->getCell('A6')->getValue());
        $this->assertSame('', (string) $sheet->getCell('B6')->getValue());

        $workbook->disconnectWorksheets();
    }
}
