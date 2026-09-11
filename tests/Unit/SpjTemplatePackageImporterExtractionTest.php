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
    public function test_copies_validated_master_workbook_without_rewriting_worksheets(): void
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

        $sourceHash = hash_file('sha256', $sourcePath);

        $importer = new SpjTemplatePackageImporter(new SpjTemplateValidator);
        $method = new ReflectionMethod($importer, 'copyValidatedMasterWorkbook');
        $method->setAccessible(true);
        $method->invoke($importer, $sourcePath, $destinationPath);

        $this->assertSame($sourceHash, hash_file('sha256', $destinationPath));

        $result = IOFactory::load($destinationPath);
        try {
            $this->assertSame(
                ['FIRST_SHEET', 'TPL_SURAT_PESANAN', 'LAST_SHEET'],
                $result->getSheetNames(),
            );
            $this->assertSame('last', $result->getActiveSheet()->getCell('A1')->getValue());
            $this->assertSame(
                '{{NOMOR_PESANAN}}',
                $result->getSheetByName('TPL_SURAT_PESANAN')?->getCell('A1')->getValue(),
            );
        } finally {
            $result->disconnectWorksheets();
            @unlink($sourcePath);
            @unlink($destinationPath);
        }
    }
}
