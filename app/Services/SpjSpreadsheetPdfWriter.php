<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Dompdf as SpreadsheetPdfWriter;

/**
 * Writer PDF untuk spreadsheet SPJ yang memastikan font yang dipakai di
 * Excel ikut terrender di PDF. DomPDF bawaan hanya mengenal font vendor
 * (DejaVu + core AFM); font sistem seperti Calibri/Arial/Times New Roman
 * didaftarkan dari direktori font OS bila berkasnya tersedia. Keluarga font
 * yang tidak ditemukan dibiarkan mengikuti fallback DomPDF seperti semula.
 */
class SpjSpreadsheetPdfWriter extends SpreadsheetPdfWriter
{
    /** @var array<string, list<string>> normal, bold, italic, bold-italic. */
    private const SYSTEM_FONT_FILES = [
        'calibri' => ['calibri.ttf', 'calibrib.ttf', 'calibrii.ttf', 'calibriz.ttf'],
        'arial' => ['arial.ttf', 'arialbd.ttf', 'ariali.ttf', 'arialbi.ttf'],
        'times new roman' => ['times.ttf', 'timesbd.ttf', 'timesi.ttf', 'timesbi.ttf'],
        'tahoma' => ['tahoma.ttf', 'tahomabd.ttf'],
        'verdana' => ['verdana.ttf', 'verdanab.ttf', 'verdanai.ttf', 'verdanaz.ttf'],
        'trebuchet ms' => ['trebuc.ttf', 'trebucbd.ttf', 'trebucit.ttf', 'trebucbi.ttf'],
        'georgia' => ['georgia.ttf', 'georgiab.ttf', 'georgiai.ttf', 'georgiaz.ttf'],
    ];

    /** @var list<string> */
    private const SYSTEM_FONT_DIRECTORIES = [
        'C:\\Windows\\Fonts',
        '/usr/share/fonts/truetype/msttcorefonts',
        '/usr/share/fonts',
        '/usr/local/share/fonts',
    ];

    /** @var array<string, true> */
    private array $registeredFamilies = [];

    protected function createExternalWriterInstance(): Dompdf
    {
        $fontDir = storage_path('app/dompdf-fonts');
        if (! is_dir($fontDir)) {
            mkdir($fontDir, 0775, true);
        }

        $pdf = new Dompdf(new Options([
            'fontDir' => $fontDir,
            'fontCache' => $fontDir,
            'isRemoteEnabled' => false,
        ]));
        $this->registerWorkbookFonts($pdf);

        return $pdf;
    }

    /** @return list<string> nama keluarga font yang berhasil didaftarkan. */
    public function registeredFamilies(): array
    {
        return array_keys($this->registeredFamilies);
    }

    public function generateHTMLAll(): string
    {
        foreach ($this->spreadsheet->getWorksheetIterator() as $sheet) {
            $printArea = str_replace('$', '', (string) $sheet->getPageSetup()->getPrintArea());
            if (preg_match('/!([A-Z]+\\d+:[A-Z]+\\d+)$/i', $printArea, $matches) === 1) {
                $sheet->getPageSetup()->setPrintArea($matches[1]);
            }
        }

        return parent::generateHTMLAll();
    }

    private function registerWorkbookFonts(Dompdf $pdf): void
    {
        foreach ($this->workbookFontFamilies($this->spreadsheet) as $family) {
            $files = $this->resolveFontFiles($family);
            if ($files === [] || isset($this->registeredFamilies[$family])) {
                continue;
            }

            $metrics = $pdf->getFontMetrics();
            $variants = [
                ['normal', 'normal'],
                ['bold', 'normal'],
                ['normal', 'italic'],
                ['bold', 'italic'],
            ];
            foreach ($variants as $index => [$weight, $style]) {
                if (! isset($files[$index])) {
                    continue;
                }
                try {
                    $metrics->registerFont(['family' => $family, 'style' => $style, 'weight' => $weight], $files[$index]);
                } catch (\Throwable) {
                    continue;
                }
            }
            $this->registeredFamilies[$family] = true;
        }
    }

    /** @return list<string> nama keluarga unik dengan huruf kecil. */
    private function workbookFontFamilies(Spreadsheet $spreadsheet): array
    {
        $families = [mb_strtolower(trim((string) $spreadsheet->getDefaultStyle()->getFont()->getName()), 'UTF-8')];
        $scanned = 0;

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
                $name = mb_strtolower(trim((string) $sheet->getCell($coordinate)->getStyle()->getFont()->getName()), 'UTF-8');
                if ($name !== '' && ! in_array($name, $families, true)) {
                    $families[] = $name;
                }
                if (++$scanned >= 2000) {
                    break 2;
                }
            }
        }

        return array_values(array_filter($families));
    }

    /** @return list<string> path TTF yang ada, urutan normal/bold/italic/bold-italic. */
    private function resolveFontFiles(string $family): array
    {
        $fileNames = self::SYSTEM_FONT_FILES[mb_strtolower($family, 'UTF-8')] ?? null;
        if ($fileNames === null) {
            return [];
        }

        $resolved = [];
        foreach ($fileNames as $fileName) {
            foreach (self::SYSTEM_FONT_DIRECTORIES as $directory) {
                $path = rtrim($directory, '\\/').DIRECTORY_SEPARATOR.$fileName;
                if (is_file($path) && is_readable($path)) {
                    $resolved[] = $path;
                    break;
                }
            }
        }

        return $resolved;
    }
}
