<?php

namespace Tests\Feature;

use App\Services\DocumentTemplateIndividualDownloadService;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;
use ZipArchive;

class DocumentTemplateIndividualDownloadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_master_workbook_download_contains_only_the_selected_canonical_document_sheet(): void
    {
        $relativePath = 'document-templates/2026/package/master.xlsx';
        Storage::disk('local')->makeDirectory(dirname($relativePath));

        $workbook = new Spreadsheet;
        $workbook->getActiveSheet()->setTitle('TPL_CHECKLIST_SPJ')->setCellValue('A1', 'Checklist');
        $workbook->createSheet()->setTitle('TPL_RINCIAN')->setCellValue('A1', 'Rincian');
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

            $this->assertSame(1, $prepared->getSheetCount());
            $this->assertSame(['TPL_RINCIAN'], $prepared->getSheetNames());
            $this->assertSame('TPL_RINCIAN', $prepared->getActiveSheet()->getTitle());
            $this->assertSame('Rincian', $prepared->getActiveSheet()->getCell('A1')->getValue());
            $this->assertNull($prepared->getSheetByName('TPL_CHECKLIST_SPJ'));
            $this->assertNull($prepared->getSheetByName('PLACEHOLDER_MAP'));
            $prepared->disconnectWorksheets();

            $archive = new ZipArchive;
            $this->assertTrue($archive->open($preparedPath) === true);
            try {
                $this->assertFalse($archive->locateName('xl/worksheets/sheet1.xml'));
                $this->assertNotFalse($archive->locateName('xl/worksheets/sheet2.xml'));
                $this->assertFalse($archive->locateName('xl/worksheets/sheet3.xml'));

                $workbookXml = $archive->getFromName('xl/workbook.xml');
                $this->assertIsString($workbookXml);
                $this->assertStringContainsString('name="TPL_RINCIAN"', $workbookXml);
                $this->assertStringNotContainsString('TPL_CHECKLIST_SPJ', $workbookXml);
                $this->assertStringNotContainsString('PLACEHOLDER_MAP', $workbookXml);
            } finally {
                $archive->close();
            }

            $source = $reader->load(Storage::disk('local')->path($relativePath));
            $this->assertSame(
                ['TPL_CHECKLIST_SPJ', 'TPL_RINCIAN', 'PLACEHOLDER_MAP'],
                $source->getSheetNames(),
            );
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
