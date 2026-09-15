<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use App\Models\School;
use App\Models\SpjPackage;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class PreviewAlignedSpjTemplateService extends ExtendedSpjTemplateService
{
    public function download(DocumentTemplate $template, SpjPackage $package, School $school)
    {
        if (strtolower((string) $template->format) !== 'xlsx') {
            return parent::download($template, $package, $school);
        }

        [$preparedTemplate] = $this->prepareMergedAnchorTemplate($template);

        return parent::download($preparedTemplate, $package, $school);
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
        return $this->canonicalPackageSpreadsheet($templates, $package, $school);
    }

    /**
     * Compatibility hook retained for the preview pipeline. Merged-cell anchor
     * normalization now happens in-memory inside ExtendedSpjTemplateService,
     * after the canonical worksheet is loaded and before any repeating row is
     * expanded. No intermediate XLSX rewrite is needed anymore.
     *
     * @return array{0:DocumentTemplate,1:null}
     */
    private function prepareMergedAnchorTemplate(DocumentTemplate $template): array
    {
        return [$template, null];
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
