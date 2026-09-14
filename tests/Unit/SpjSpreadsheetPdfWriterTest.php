<?php

namespace Tests\Unit;

use App\Services\SpjSpreadsheetPdfWriter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class SpjSpreadsheetPdfWriterTest extends TestCase
{
    public function test_pdf_renders_with_workbook_fonts(): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'Uraian belanja BOSP');
        $sheet->getStyle('A1')->getFont()->setName('Calibri')->setBold(true);
        $sheet->setCellValue('A2', 'Baris kedua');
        $sheet->getStyle('A2')->getFont()->setName('FontYangTidakAda123');

        $output = tempnam(sys_get_temp_dir(), 'spj-font-');
        $writer = new SpjSpreadsheetPdfWriter($spreadsheet);
        $writer->save($output);

        $contents = (string) file_get_contents($output);
        $this->assertStringStartsWith('%PDF', $contents);
        $this->assertGreaterThan(1000, strlen($contents));

        if (is_file('C:\\Windows\\Fonts\\calibri.ttf')) {
            $this->assertContains('calibri', $writer->registeredFamilies());
        }

        @unlink($output);
    }

    public function test_unknown_fonts_fall_back_without_error(): void
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->setCellValue('A1', 'Tetap terrender');
        $spreadsheet->getActiveSheet()->getStyle('A1')->getFont()->setName('FontYangTidakAda123');

        $output = tempnam(sys_get_temp_dir(), 'spj-font-');
        (new SpjSpreadsheetPdfWriter($spreadsheet))->save($output);

        $this->assertStringStartsWith('%PDF', (string) file_get_contents($output));

        @unlink($output);
    }
}
