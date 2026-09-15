<?php

namespace Tests\Unit;

use App\Services\ExtendedSpjTemplateService;
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
}
