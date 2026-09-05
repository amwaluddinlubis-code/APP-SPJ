<?php

namespace Tests\Unit;

use App\Services\SpjDocumentTypeRegistry;
use App\Services\SpjTemplatePackageImporter;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpjTemplatePackageImporterTest extends TestCase
{
    #[Test]
    public function complete_canonical_workbook_passes_package_validation(): void
    {
        $path = $this->makePackageWorkbook();

        try {
            $result = app(SpjTemplatePackageImporter::class)->validatePackage($path);

            $this->assertTrue($result['valid']);
            $this->assertSame([], $result['errors']);
            $this->assertCount(count(SpjDocumentTypeRegistry::codes()), $result['results']);
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function missing_canonical_sheet_rejects_the_package(): void
    {
        $path = $this->makePackageWorkbook(SpjDocumentTypeRegistry::KUITANSI_A2);

        try {
            $result = app(SpjTemplatePackageImporter::class)->validatePackage($path);
            $missing = collect($result['errors'])->firstWhere('code', 'PACKAGE_SHEET_MISSING');

            $this->assertFalse($result['valid']);
            $this->assertNotNull($missing);
            $this->assertSame(SpjDocumentTypeRegistry::KUITANSI_A2, $missing['document_type']);
        } finally {
            @unlink($path);
        }
    }

    private function makePackageWorkbook(?string $skipDocumentType = null): string
    {
        $book = new Spreadsheet;
        $book->removeSheetByIndex(0);

        foreach (SpjDocumentTypeRegistry::all() as $documentType => $definition) {
            if ($documentType === $skipDocumentType) {
                continue;
            }

            $sheet = $book->createSheet();
            $sheet->setTitle((string) $definition['sheet']);

            $row = 1;
            foreach ($definition['required'] as $marker) {
                $sheet->setCellValue('A'.$row, '{{'.$marker.'}}');
                $row++;
            }

            $groups = [];
            foreach ($definition['repeat_required'] as $marker) {
                $prefix = str_contains($marker, '_') ? strstr($marker, '_', true) : $marker;
                $groups[$prefix][] = $marker;
            }

            foreach ($groups as $markers) {
                $column = 1;
                foreach ($markers as $marker) {
                    $coordinate = Coordinate::stringFromColumnIndex($column).$row;
                    $sheet->setCellValue($coordinate, '{{'.$marker.'}}');
                    $column++;
                }
                $row++;
            }
        }

        $book->setActiveSheetIndex(0);
        $path = sys_get_temp_dir().'/spj-template-package-'.uniqid('', true).'.xlsx';
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return $path;
    }
}
