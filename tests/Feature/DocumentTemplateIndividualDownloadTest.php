<?php

namespace Tests\Feature;

use App\Services\DocumentTemplateIndividualDownloadService;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class DocumentTemplateIndividualDownloadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_master_workbook_download_exposes_only_selected_canonical_document_sheet(): void
    {
        $relativePath = 'document-templates/2026/package/master.xlsx';
        Storage::disk('local')->makeDirectory(dirname($relativePath));

        $workbook = new Spreadsheet;
        $workbook->getActiveSheet()->setTitle('TPL_RINCIAN')->setCellValue('A1', 'Rincian');
        $workbook->createSheet()->setTitle('TPL_CHECKLIST_SPJ')->setCellValue('A1', 'Checklist');
        $workbook->createSheet()->setTitle('PLACEHOLDER_MAP')->setCellValue('A1', 'Teknis');
        (new Xlsx($workbook))->save(Storage::disk('local')->path($relativePath));
        $workbook->disconnectWorksheets();

        $preparedPath = app(DocumentTemplateIndividualDownloadService::class)->prepare(
            $relativePath,
            'RINCIAN_BELANJA',
            'xlsx',
        );

        $this->assertNotNull($preparedPath);
        $this->assertFileExists($preparedPath);

        try {
            $reader = IOFactory::createReader('Xlsx');
            $prepared = $reader->load($preparedPath);
            $visibleSheets = collect($prepared->getWorksheetIterator())
                ->filter(fn (Worksheet $sheet): bool => $sheet->getSheetState() === Worksheet::SHEETSTATE_VISIBLE)
                ->map(fn (Worksheet $sheet): string => $sheet->getTitle())
                ->values()
                ->all();

            $this->assertSame(['TPL_RINCIAN'], $visibleSheets);
            $this->assertSame('TPL_RINCIAN', $prepared->getActiveSheet()->getTitle());
            $this->assertSame(Worksheet::SHEETSTATE_VERYHIDDEN, $prepared->getSheetByName('TPL_CHECKLIST_SPJ')?->getSheetState());
            $this->assertSame(Worksheet::SHEETSTATE_VERYHIDDEN, $prepared->getSheetByName('PLACEHOLDER_MAP')?->getSheetState());
            $prepared->disconnectWorksheets();

            $source = $reader->load(Storage::disk('local')->path($relativePath));
            $sourceVisibleSheets = collect($source->getWorksheetIterator())
                ->filter(fn (Worksheet $sheet): bool => $sheet->getSheetState() === Worksheet::SHEETSTATE_VISIBLE)
                ->map(fn (Worksheet $sheet): string => $sheet->getTitle())
                ->values()
                ->all();

            $this->assertSame(['TPL_RINCIAN', 'TPL_CHECKLIST_SPJ', 'PLACEHOLDER_MAP'], $sourceVisibleSheets);
            $source->disconnectWorksheets();
        } finally {
            @unlink($preparedPath);
        }
    }

    public function test_single_sheet_workbook_does_not_create_an_unnecessary_temporary_copy(): void
    {
        $relativePath = 'document-templates/2026/single.xlsx';
        Storage::disk('local')->makeDirectory(dirname($relativePath));

        $workbook = new Spreadsheet;
        $workbook->getActiveSheet()->setTitle('TPL_RINCIAN')->setCellValue('A1', 'Rincian');
        (new Xlsx($workbook))->save(Storage::disk('local')->path($relativePath));
        $workbook->disconnectWorksheets();

        $preparedPath = app(DocumentTemplateIndividualDownloadService::class)->prepare(
            $relativePath,
            'RINCIAN_BELANJA',
            'xlsx',
        );

        $this->assertNull($preparedPath);
        Storage::disk('local')->assertExists($relativePath);
    }
}
