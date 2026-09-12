<?php

namespace Tests\Unit;

use App\Models\DocumentTemplate;
use App\Services\ArkasActivityHierarchyResolver;
use App\Services\SpjTemplateService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use ReflectionMethod;
use Tests\TestCase;

class SpjTemplateHtmlPreviewTest extends TestCase
{
    public function test_html_preview_uses_canonical_excel_sheet_instead_of_first_workbook_sheet(): void
    {
        $workbook = new Spreadsheet;
        $workbook->getActiveSheet()
            ->setTitle('TPL_CHECKLIST_SPJ')
            ->setCellValue('A1', 'SHEET PERTAMA BUKAN TEMPLATE TERPILIH');
        $workbook->createSheet()
            ->setTitle('TPL_RINCIAN')
            ->setCellValue('A1', 'PREVIEW DARI SHEET EXCEL RINCIAN');

        $html = $this->renderWorkbookPreview($workbook, 'RINCIAN_BELANJA');

        $this->assertStringContainsString('PREVIEW DARI SHEET EXCEL RINCIAN', $html);
        $this->assertStringNotContainsString('SHEET PERTAMA BUKAN TEMPLATE TERPILIH', $html);

        $workbook->disconnectWorksheets();
    }

    public function test_html_preview_falls_back_to_only_non_technical_excel_sheet(): void
    {
        $workbook = new Spreadsheet;
        $workbook->getActiveSheet()
            ->setTitle('RINCIAN_CUSTOM')
            ->setCellValue('A1', 'PREVIEW DARI SATU SHEET NON TEKNIS');
        $workbook->createSheet()
            ->setTitle('PLACEHOLDER_MAP')
            ->setCellValue('A1', 'SHEET TEKNIS');

        $html = $this->renderWorkbookPreview($workbook, 'RINCIAN_BELANJA');

        $this->assertStringContainsString('PREVIEW DARI SATU SHEET NON TEKNIS', $html);
        $this->assertStringNotContainsString('SHEET TEKNIS', $html);

        $workbook->disconnectWorksheets();
    }

    private function renderWorkbookPreview(Spreadsheet $workbook, string $documentType): string
    {
        $service = new SpjTemplateService(new ArkasActivityHierarchyResolver);
        $template = new DocumentTemplate([
            'document_type' => $documentType,
            'format' => 'xlsx',
        ]);
        $method = new ReflectionMethod($service, 'spreadsheetHtmlFromWorkbook');

        return (string) $method->invoke($service, $workbook, $template);
    }
}
