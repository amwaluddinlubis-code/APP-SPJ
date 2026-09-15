<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use App\Models\School;
use App\Models\SpjPackage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class PreviewAlignedSpjTemplateService extends ExtendedSpjTemplateService
{
    public function download(DocumentTemplate $template, SpjPackage $package, School $school)
    {
        if (strtolower((string) $template->format) !== 'xlsx') {
            return parent::download($template, $package, $school);
        }

        [$preparedTemplate, $temporarySource] = $this->prepareMergedAnchorTemplate($template);

        try {
            return parent::download($preparedTemplate, $package, $school);
        } finally {
            if ($temporarySource !== null) {
                @unlink($temporarySource);
            }
        }
    }

    public function previewTemplatePdfBytes(DocumentTemplate $template, SpjPackage $package, School $school): ?string
    {
        if (strtolower((string) $template->format) !== 'xlsx') {
            return parent::previewTemplatePdfBytes($template, $package, $school);
        }

        $response = $this->download($template, $package, $school);
        $path = $response->getFile()->getPathname();

        try {
            $nativePdf = app(SpjSpreadsheetPdfConverter::class)->convertFile($path);
            if ($nativePdf !== null) {
                return $nativePdf;
            }

            $spreadsheet = IOFactory::load($path);
            try {
                return $this->fallbackPdfContents($spreadsheet, false);
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        } finally {
            @unlink($path);
        }
    }

    /** @param Collection<int, DocumentTemplate> $templates */
    public function packagePreviewPdfBytes(Collection $templates, SpjPackage $package, School $school): string
    {
        $spreadsheet = $this->packageSpreadsheetForOutput($templates, $package, $school);

        try {
            return $this->spreadsheetPdfContents($spreadsheet, true);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    /** @param Collection<int, DocumentTemplate> $templates */
    public function downloadPackageExcel(Collection $templates, SpjPackage $package, School $school)
    {
        $spreadsheet = $this->packageSpreadsheetForOutput($templates, $package, $school);
        $temporaryFile = tempnam(sys_get_temp_dir(), 'spj-xlsx-');
        if ($temporaryFile === false) {
            $spreadsheet->disconnectWorksheets();
            throw new \RuntimeException('File sementara Excel tidak dapat dibuat.');
        }

        try {
            IOFactory::createWriter($spreadsheet, 'Xlsx')->save($temporaryFile);

            $downloadName = $this->safeDownloadName('PAKET-SPJ-'.$package->document_number.'.xlsx');
            $stored = app(DocumentStoragePathService::class)->persist($temporaryFile, $package, $downloadName);
            @unlink($temporaryFile);

            return response()->download($stored, $downloadName);
        } catch (\Throwable $exception) {
            @unlink($temporaryFile);
            throw $exception;
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    /** @param Collection<int, DocumentTemplate> $templates */
    public function downloadPackagePdf(Collection $templates, SpjPackage $package, School $school)
    {
        $spreadsheet = $this->packageSpreadsheetForOutput($templates, $package, $school);

        try {
            return $this->pdfResponse(
                $this->spreadsheetPdfContents($spreadsheet, true),
                'PAKET-SPJ-'.$package->document_number.'.pdf',
                $package,
            );
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    /** @param Collection<int, DocumentTemplate> $templates */
    private function packageSpreadsheetForOutput(Collection $templates, SpjPackage $package, School $school): Spreadsheet
    {
        if ($templates->isEmpty()) {
            throw new \RuntimeException('Belum ada template dokumen aktif yang sesuai dengan kategori paket ini.');
        }

        $packageSpreadsheet = null;

        foreach ($templates as $template) {
            if (strtolower((string) $template->format) !== 'xlsx') {
                throw new \RuntimeException('Paket dokumen saat ini hanya mendukung template Excel aktif.');
            }

            $response = $this->download($template, $package, $school);
            $path = $response->getFile()->getPathname();

            try {
                $single = IOFactory::load($path);
            } finally {
                @unlink($path);
            }

            if (! $packageSpreadsheet instanceof Spreadsheet) {
                $packageSpreadsheet = $single;

                continue;
            }

            try {
                $sheetName = $single->getSheet(0)->getTitle();
                $copy = $single->duplicateWorksheetByTitle($sheetName);
                $packageSpreadsheet->addExternalSheet($copy);
                $copy->setTitle($sheetName);
            } finally {
                $single->disconnectWorksheets();
            }
        }

        if (! $packageSpreadsheet instanceof Spreadsheet) {
            throw new \RuntimeException('Paket template tidak menghasilkan worksheet canonical.');
        }

        $packageSpreadsheet->setActiveSheetIndex(0);

        return $packageSpreadsheet;
    }

    /**
     * Excel hanya menampilkan nilai pada sel anchor (kiri-atas) sebuah merged range.
     * Jika template menyimpan placeholder tambahan di sel non-anchor, anchor adalah
     * sumber kebenaran visual. Normalisasi dilakukan pada salinan sementara saja;
     * master template yang tersimpan tidak pernah dimutasi.
     *
     * @return array{0:DocumentTemplate,1:?string}
     */
    private function prepareMergedAnchorTemplate(DocumentTemplate $template): array
    {
        $relativePath = ltrim((string) $template->file_path, '/\\');
        $disk = Storage::disk('local');
        $sourcePath = $disk->exists($relativePath)
            ? $disk->path($relativePath)
            : storage_path('app/'.$relativePath);

        if (! is_file($sourcePath)) {
            return [$template, null];
        }

        $spreadsheet = IOFactory::load($sourcePath);
        $changed = false;

        try {
            $canonical = SpjDocumentTypeRegistry::canonical((string) $template->document_type);
            $definition = $canonical ? SpjDocumentTypeRegistry::definition($canonical) : null;
            $expectedSheet = trim((string) ($definition['sheet'] ?? ''));
            $sheet = $expectedSheet !== '' ? $spreadsheet->getSheetByName($expectedSheet) : null;

            if ($sheet === null && $spreadsheet->getSheetCount() === 1) {
                $sheet = $spreadsheet->getSheet(0);
            }

            if ($sheet === null) {
                return [$template, null];
            }

            foreach (array_values($sheet->getMergeCells()) as $range) {
                [[$startColumn, $startRow], [$endColumn, $endRow]] = Coordinate::rangeBoundaries($range);
                $anchor = Coordinate::stringFromColumnIndex($startColumn).$startRow;
                $anchorValue = $sheet->getCell($anchor)->getValue();

                if (! is_string($anchorValue)
                    || ! preg_match('/^\s*\{\{[A-Za-z0-9_]+\}\}\s*$/u', $anchorValue)) {
                    continue;
                }

                for ($row = $startRow; $row <= $endRow; $row++) {
                    for ($column = $startColumn; $column <= $endColumn; $column++) {
                        $coordinate = Coordinate::stringFromColumnIndex($column).$row;
                        if ($coordinate === $anchor) {
                            continue;
                        }

                        $value = $sheet->getCell($coordinate)->getValue();
                        if (is_string($value)
                            && preg_match('/^\s*\{\{[A-Za-z0-9_]+\}\}\s*$/u', $value)) {
                            $sheet->getCell($coordinate)->setValue('');
                            $changed = true;
                        }
                    }
                }
            }

            if (! $changed) {
                return [$template, null];
            }

            $disk->makeDirectory('generated-documents');
            $temporaryRelativePath = 'generated-documents/template-anchor-'.uniqid('', true).'.xlsx';
            $temporaryPath = $disk->path($temporaryRelativePath);
            IOFactory::createWriter($spreadsheet, 'Xlsx')->save($temporaryPath);

            $preparedTemplate = clone $template;
            $preparedTemplate->setAttribute('file_path', $temporaryRelativePath);

            return [$preparedTemplate, $temporaryPath];
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    private function spreadsheetPdfContents(Spreadsheet $spreadsheet, bool $allSheets): string
    {
        $nativePdf = app(SpjSpreadsheetPdfConverter::class)->convert($spreadsheet);
        if ($nativePdf !== null) {
            return $nativePdf;
        }

        return $this->fallbackPdfContents($spreadsheet, $allSheets);
    }

    private function fallbackPdfContents(Spreadsheet $spreadsheet, bool $allSheets): string
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), 'spj-pdf-');
        if ($temporaryFile === false) {
            throw new \RuntimeException('File sementara PDF tidak dapat dibuat.');
        }

        try {
            $writer = new SpjSpreadsheetPdfWriter($spreadsheet);
            if ($allSheets) {
                $writer->writeAllSheets();
            } else {
                $writer->setSheetIndex(0);
            }
            $writer->save($temporaryFile);

            return (string) file_get_contents($temporaryFile);
        } finally {
            @unlink($temporaryFile);
        }
    }

    private function pdfResponse(string $contents, string $fileName, SpjPackage $package)
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), 'spj-pdf-');
        if ($temporaryFile === false || file_put_contents($temporaryFile, $contents) === false) {
            throw new \RuntimeException('File sementara PDF tidak dapat disimpan.');
        }

        $downloadName = $this->safeDownloadName($fileName);
        $stored = app(DocumentStoragePathService::class)->persist($temporaryFile, $package, $downloadName);
        @unlink($temporaryFile);

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$downloadName.'"',
            'Content-Length' => (string) strlen($contents),
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    private function safeDownloadName(string $name): string
    {
        return preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?: 'dokumen-spj';
    }
}
