<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use App\Models\School;
use App\Models\SpjPackage;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PreviewAlignedSpjTemplateService extends ExtendedSpjTemplateService
{
    private const CONSUMPTION_ROW_PLACEHOLDERS = [
        'KONSUMSI_NO',
        'KONSUMSI_NAMA',
        'KONSUMSI_IDENTITAS',
        'KONSUMSI_PORSI',
        'KONSUMSI_HARGA_PORSI',
        'KONSUMSI_JUMLAH',
    ];

    private bool $renderingCanonicalSpreadsheet = false;

    /** @return array<string,string> */
    public function placeholders(SpjPackage $package, School $school): array
    {
        $values = parent::placeholders($package, $school);

        if (! $this->renderingCanonicalSpreadsheet) {
            return $values;
        }

        foreach (self::CONSUMPTION_ROW_PLACEHOLDERS as $placeholder) {
            unset($values[$placeholder]);
        }

        return $values;
    }

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
        [$spreadsheet, $temporaryFiles] = $this->packageSpreadsheetForOutput($templates, $package, $school);

        try {
            return $this->spreadsheetPdfContents($spreadsheet, true);
        } finally {
            $spreadsheet->disconnectWorksheets();
            $this->removeTemporaryFiles($temporaryFiles);
        }
    }

    /** @param Collection<int, DocumentTemplate> $templates */
    public function downloadPackageExcel(Collection $templates, SpjPackage $package, School $school)
    {
        [$spreadsheet, $temporaryFiles] = $this->packageSpreadsheetForOutput($templates, $package, $school);
        $temporaryFile = tempnam(sys_get_temp_dir(), 'spj-xlsx-');
        if ($temporaryFile === false) {
            $spreadsheet->disconnectWorksheets();
            $this->removeTemporaryFiles($temporaryFiles);
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
            $this->removeTemporaryFiles($temporaryFiles);
        }
    }

    /** @param Collection<int, DocumentTemplate> $templates */
    public function downloadPackagePdf(Collection $templates, SpjPackage $package, School $school)
    {
        [$spreadsheet, $temporaryFiles] = $this->packageSpreadsheetForOutput($templates, $package, $school);

        try {
            return $this->pdfResponse(
                $this->spreadsheetPdfContents($spreadsheet, true),
                'PAKET-SPJ-'.$package->document_number.'.pdf',
                $package,
            );
        } finally {
            $spreadsheet->disconnectWorksheets();
            $this->removeTemporaryFiles($temporaryFiles);
        }
    }

    /**
     * Package output is built directly from the canonical in-memory workbooks.
     * The second tuple element is retained for compatibility with the cleanup
     * flow used by preview/download callers.
     *
     * @return array{0:Spreadsheet,1:list<string>}
     */
    private function packageSpreadsheetForOutput(Collection $templates, SpjPackage $package, School $school): array
    {
        return [$this->canonicalPackageSpreadsheet($templates, $package, $school), []];
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

    protected function canonicalSpreadsheet(DocumentTemplate $template, SpjPackage $package, School $school): Spreadsheet
    {
        $this->renderingCanonicalSpreadsheet = true;

        try {
            $spreadsheet = parent::canonicalSpreadsheet($template, $package, $school);
        } finally {
            $this->renderingCanonicalSpreadsheet = false;
        }

        try {
            $this->fillExcelConsumptionRows($spreadsheet->getSheet(0), $package);

            return $spreadsheet;
        } catch (\Throwable $exception) {
            $spreadsheet->disconnectWorksheets();
            throw $exception;
        }
    }

    private function fillExcelConsumptionRows(Worksheet $sheet, SpjPackage $package): void
    {
        $participants = $package->transaction->participants;

        app(SpjRepeatingRowRenderer::class)->render(
            $sheet,
            '{{KONSUMSI_NO}}',
            'KONSUMSI_',
            $participants->count(),
            fn (int $index): array => $this->consumptionRowValues($package, $index),
        );
    }

    /** @return array<string,string> */
    private function consumptionRowValues(SpjPackage $package, int $index): array
    {
        $participant = $package->transaction->participants[$index - 1];
        $portions = (float) $participant->portions;
        $price = (float) ($participant->item?->unit_price ?? 0);
        $amount = $portions * $price;
        $identity = collect([
            trim((string) $participant->position),
            filled($participant->nip) ? 'NIP '.trim((string) $participant->nip) : null,
            filled($participant->nuptk) ? 'NUPTK '.trim((string) $participant->nuptk) : null,
        ])->filter()->implode(' / ');

        return [
            'KONSUMSI_NO' => (string) $index,
            'KONSUMSI_NAMA' => trim((string) $participant->name) ?: SpjDocumentTypeRegistry::EMPTY_SCALAR_VALUE,
            'KONSUMSI_IDENTITAS' => $identity ?: SpjDocumentTypeRegistry::EMPTY_SCALAR_VALUE,
            'KONSUMSI_PORSI' => app(SpjPlaceholderValueFormatter::class)->number($portions),
            'KONSUMSI_HARGA_PORSI' => app(SpjPlaceholderValueFormatter::class)->amount($price),
            'KONSUMSI_JUMLAH' => app(SpjPlaceholderValueFormatter::class)->amount($amount),
        ];
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

    /** @param list<string> $paths */
    private function removeTemporaryFiles(array $paths): void
    {
        foreach ($paths as $path) {
            if (is_string($path) && $path !== '' && is_file($path)) {
                @unlink($path);
            }
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
