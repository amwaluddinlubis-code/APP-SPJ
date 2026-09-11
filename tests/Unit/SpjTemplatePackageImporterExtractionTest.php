<?php

namespace Tests\Unit;

use App\Services\SpjTemplatePackageImporter;
use App\Services\SpjTemplateValidator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class SpjTemplatePackageImporterExtractionTest extends TestCase
{
    public function test_extracts_target_sheet_when_source_active_sheet_is_different(): void
    {
        $source = new Spreadsheet;
        $source->getActiveSheet()->setTitle('FIRST_SHEET')->setCellValue('A1', 'first');
        $source->createSheet()->setTitle('TPL_SURAT_PESANAN')->setCellValue('A1', '{{NOMOR_PESANAN}}');
        $source->createSheet()->setTitle('LAST_SHEET')->setCellValue('A1', 'last');
        $source->setActiveSheetIndex(2);

        $sourcePath = tempnam(sys_get_temp_dir(), 'spj-package-source-').'.xlsx';
        $destinationPath = tempnam(sys_get_temp_dir(), 'spj-package-destination-').'.xlsx';
        (new Xlsx($source))->save($sourcePath);
        $source->disconnectWorksheets();

        $importer = new SpjTemplatePackageImporter(new SpjTemplateValidator);
        $method = new ReflectionMethod($importer, 'extractCanonicalSheet');
        $method->setAccessible(true);
        $method->invoke($importer, $sourcePath, 'TPL_SURAT_PESANAN', $destinationPath);

        $result = IOFactory::load($destinationPath);
        try {
            $this->assertSame(['TPL_SURAT_PESANAN'], $result->getSheetNames());
            $this->assertSame('{{NOMOR_PESANAN}}', $result->getActiveSheet()->getCell('A1')->getValue());
        } finally {
            $result->disconnectWorksheets();
            @unlink($sourcePath);
            @unlink($destinationPath);
        }
    }
}
