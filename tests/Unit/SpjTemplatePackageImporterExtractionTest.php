<?php

namespace Tests\Unit;

use App\Models\School;
use App\Services\DocumentTemplateStoragePathService;
use App\Services\SpjTemplatePackageImporter;
use App\Services\SpjTemplateValidator;
use App\Support\ActiveSpjContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use ReflectionMethod;
use Tests\TestCase as ApplicationTestCase;

class SpjTemplatePackageImporterExtractionTest extends ApplicationTestCase
{
    use RefreshDatabase;

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

        $school = School::query()->create([
            'npsn' => '10208183',
            'name' => 'Sekolah Import Test',
        ]);
        $context = new ActiveSpjContext($school->id, 2026);
        $storagePaths = new DocumentTemplateStoragePathService($context);
        $importer = new SpjTemplatePackageImporter(new SpjTemplateValidator, $storagePaths);
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

    public function test_same_fiscal_year_uses_different_storage_paths_for_different_schools(): void
    {
        $schoolA = School::query()->create([
            'npsn' => '10208183',
            'name' => 'Sekolah Import A',
        ]);
        $schoolB = School::query()->create([
            'npsn' => '10208184',
            'name' => 'Sekolah Import B',
        ]);
        $fiscalYearId = 2026;

        $pathA = (new DocumentTemplateStoragePathService(
            new ActiveSpjContext($schoolA->id, $fiscalYearId),
        ))->directory($fiscalYearId, 'package');
        $pathB = (new DocumentTemplateStoragePathService(
            new ActiveSpjContext($schoolB->id, $fiscalYearId),
        ))->directory($fiscalYearId, 'package');

        $this->assertSame('document-templates/10208183/2026/package', $pathA);
        $this->assertSame('document-templates/10208184/2026/package', $pathB);
        $this->assertNotSame($pathA, $pathB);
    }
}
