<?php

namespace Tests\Unit;

use App\Models\School;
use App\Services\SpjTemplateService;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Shared\Drawing as SharedDrawing;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Font;
use Tests\TestCase;

class SpjLetterheadAutofitTest extends TestCase
{
    public function test_kop_a1_autofits_print_width_and_keeps_aspect_ratio(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('kop/test.png', $this->png(400, 100));

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        foreach (['A', 'B', 'C', 'D'] as $column) {
            $sheet->getColumnDimension($column)->setWidth(20);
        }
        $sheet->getPageSetup()->setPrintArea('A1:D20');
        $sheet->setCellValue('A1', '{{KOP_SURAT}}');

        $service = app(SpjTemplateService::class);
        $method = new \ReflectionMethod(SpjTemplateService::class, 'fillExcelLetterhead');
        $method->setAccessible(true);
        $method->invoke($service, $sheet, new School(['letterhead_path' => 'kop/test.png']));

        $this->assertSame('', $sheet->getCell('A1')->getValue());

        $drawings = iterator_to_array($sheet->getDrawingCollection());
        $this->assertCount(1, $drawings);
        $drawing = reset($drawings);
        $this->assertSame('A1', $drawing->getCoordinates());

        $expectedWidth = 0;
        foreach (['A', 'B', 'C', 'D'] as $column) {
            $expectedWidth += SharedDrawing::cellDimensionToPixels(20, new Font(false));
        }
        $expectedWidth = max(1, $expectedWidth - 4);
        $this->assertSame($expectedWidth, $drawing->getWidth());
        $this->assertEqualsWithDelta(100 / 400, $drawing->getHeight() / $drawing->getWidth(), 0.01);

        $expectedRowHeight = SharedDrawing::pixelsToPoints((int) round($expectedWidth * 100 / 400)) + 4;
        $this->assertEqualsWithDelta($expectedRowHeight, $sheet->getRowDimension(1)->getRowHeight(), 0.01);
    }

    public function test_missing_letterhead_adds_no_drawing(): void
    {
        Storage::fake('local');

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', '{{KOP_SURAT}}');

        $service = app(SpjTemplateService::class);
        $method = new \ReflectionMethod(SpjTemplateService::class, 'fillExcelLetterhead');
        $method->setAccessible(true);
        $method->invoke($service, $sheet, new School(['letterhead_path' => 'kop/hilang.png']));

        $this->assertCount(0, $sheet->getDrawingCollection());
    }

    private function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 30, 99, 227));
        ob_start();
        imagepng($image);
        $contents = (string) ob_get_clean();
        imagedestroy($image);

        return $contents;
    }
}
