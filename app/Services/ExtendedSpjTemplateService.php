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
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Html;

class ExtendedSpjTemplateService extends SpjTemplateService
{
    /** @return array<string,array<int,string>> */
    public static function placeholderGroups(): array
    {
        return parent::placeholderGroups() + [
            'Konsumsi & kegiatan' => [
                'NAMA_ACARA',
                'TANGGAL_KEGIATAN',
                'TEMPAT_KEGIATAN',
                'TANGGAL_ACARA',
                'TEMPAT_ACARA',
                'NAMA_PENANGGUNG_JAWAB',
                'NIP_PENANGGUNG_JAWAB',
                'KONSUMSI_NO',
                'KONSUMSI_NAMA',
                'KONSUMSI_IDENTITAS',
                'KONSUMSI_PORSI',
                'KONSUMSI_HARGA_PORSI',
                'KONSUMSI_JUMLAH',
                'TOTAL_KONSUMSI',
            ],
        ];
    }

    /** @return array<string,string> */
    public static function placeholderGroupScopes(): array
    {
        return parent::placeholderGroupScopes() + [
            'Konsumsi & kegiatan' => parent::PLACEHOLDER_SCOPE_KHUSUS,
        ];
    }

    /** @return array<string,string> */
    public function placeholders(SpjPackage $package, School $school): array
    {
        $values = parent::placeholders($package, $school);
        $transaction = $package->transaction;
        $transaction->loadMissing(['participants.item']);

        $eventDate = $transaction->event_date?->translatedFormat('d F Y')
            ?: $transaction->transaction_date?->translatedFormat('d F Y')
            ?: SpjDocumentTypeRegistry::EMPTY_SCALAR_VALUE;
        $eventLocation = trim((string) $transaction->event_location);

        $participants = $transaction->participants->values();
        $participantNumbers = [];
        $participantNames = [];
        $participantIdentities = [];
        $participantPortions = [];
        $participantPrices = [];
        $participantAmounts = [];
        $totalConsumption = 0.0;

        foreach ($participants as $index => $participant) {
            $portions = (float) $participant->portions;
            $price = (float) ($participant->item?->unit_price ?? 0);
            $amount = $portions * $price;
            $identity = collect([
                trim((string) $participant->position),
                filled($participant->nip) ? 'NIP '.trim((string) $participant->nip) : null,
                filled($participant->nuptk) ? 'NUPTK '.trim((string) $participant->nuptk) : null,
            ])->filter()->implode(' / ');

            $participantNumbers[] = (string) ($index + 1);
            $participantNames[] = trim((string) $participant->name) ?: SpjDocumentTypeRegistry::EMPTY_SCALAR_VALUE;
            $participantIdentities[] = $identity ?: SpjDocumentTypeRegistry::EMPTY_SCALAR_VALUE;
            $participantPortions[] = $this->plainNumber($portions);
            $participantPrices[] = app(SpjPlaceholderValueFormatter::class)->amount($price);
            $participantAmounts[] = app(SpjPlaceholderValueFormatter::class)->amount($amount);
            $totalConsumption += $amount;
        }

        $fallback = SpjDocumentTypeRegistry::EMPTY_SCALAR_VALUE;

        return $values + [
            'NAMA_ACARA' => (string) ($transaction->event_name ?: $fallback),
            'TANGGAL_KEGIATAN' => $eventDate,
            'TEMPAT_KEGIATAN' => $eventLocation !== '' ? $eventLocation : $fallback,
            'TANGGAL_ACARA' => $eventDate,
            'TEMPAT_ACARA' => $eventLocation !== '' ? $eventLocation : $fallback,
            'NAMA_PENANGGUNG_JAWAB' => $fallback,
            'NIP_PENANGGUNG_JAWAB' => $fallback,
            'KONSUMSI_NO' => $participantNumbers !== [] ? implode("\n", $participantNumbers) : $fallback,
            'KONSUMSI_NAMA' => $participantNames !== [] ? implode("\n", $participantNames) : $fallback,
            'KONSUMSI_IDENTITAS' => $participantIdentities !== [] ? implode("\n", $participantIdentities) : $fallback,
            'KONSUMSI_PORSI' => $participantPortions !== [] ? implode("\n", $participantPortions) : $fallback,
            'KONSUMSI_HARGA_PORSI' => $participantPrices !== [] ? implode("\n", $participantPrices) : $fallback,
            'KONSUMSI_JUMLAH' => $participantAmounts !== [] ? implode("\n", $participantAmounts) : $fallback,
            'TOTAL_KONSUMSI' => app(SpjPlaceholderValueFormatter::class)->amount($totalConsumption),
        ];
    }

    /**
     * XLSX package imports preserve the complete master workbook. Runtime output
     * edits the canonical worksheet in that loaded workbook and then removes the
     * unrelated worksheets instead of rebuilding the selected sheet in a fresh
     * Spreadsheet. This keeps worksheet print/layout metadata owned by the
     * template (page setup, margins, print area, dimensions, styles, merges,
     * header/footer, drawings) attached to the original worksheet object.
     */
    public function download(DocumentTemplate $template, SpjPackage $package, School $school)
    {
        if (strtolower((string) $template->format) !== 'xlsx') {
            return parent::download($template, $package, $school);
        }

        $spreadsheet = $this->canonicalSpreadsheet($template, $package, $school);
        $output = storage_path('app/generated-documents/'.uniqid('spj_', true).'.xlsx');
        if (! is_dir(dirname($output))) {
            mkdir(dirname($output), 0775, true);
        }

        try {
            IOFactory::createWriter($spreadsheet, 'Xlsx')->save($output);
            (new SpjUnresolvedPlaceholderGuard)->assertResolved((string) $template->document_type, $output, 'xlsx');
        } catch (\Throwable $exception) {
            @unlink($output);
            throw $exception;
        } finally {
            $spreadsheet->disconnectWorksheets();
        }

        return response()->download(
            $output,
            $this->safeDownloadName($template->document_type.'-'.$package->document_number.'.xlsx'),
        )->deleteFileAfterSend(true);
    }

    public function previewHtml(DocumentTemplate $template, SpjPackage $package, School $school): ?string
    {
        if (strtolower((string) $template->format) !== 'xlsx') {
            return parent::previewHtml($template, $package, $school);
        }

        $spreadsheet = $this->canonicalSpreadsheet($template, $package, $school);
        try {
            $writer = new Html($spreadsheet);
            $writer->setSheetIndex(0)->setEmbedImages(true)->setUseInlineCss(true);

            return $writer->generateHtmlAll();
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    public function downloadPdf(DocumentTemplate $template, SpjPackage $package, School $school)
    {
        if (strtolower((string) $template->format) !== 'xlsx') {
            return parent::downloadPdf($template, $package, $school);
        }

        $spreadsheet = $this->canonicalSpreadsheet($template, $package, $school);
        try {
            return $this->pdfResponseExtended(
                $this->spreadsheetPdfContentsExtended($spreadsheet, false),
                $template->document_type.'-'.$package->document_number.'.pdf', $package,
            );
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    /** @param Collection<int, DocumentTemplate> $templates */
    public function downloadPackageExcel(Collection $templates, SpjPackage $package, School $school)
    {
        $spreadsheet = $this->canonicalPackageSpreadsheet($templates, $package, $school);
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
        $spreadsheet = $this->canonicalPackageSpreadsheet($templates, $package, $school);
        try {
            return $this->pdfResponseExtended(
                $this->spreadsheetPdfContentsExtended($spreadsheet, true),
                'PAKET-SPJ-'.$package->document_number.'.pdf', $package,
            );
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    /** @param Collection<int, DocumentTemplate> $templates */
    public function packagePreviewHtml(Collection $templates, SpjPackage $package, School $school): string
    {
        $spreadsheet = $this->canonicalPackageSpreadsheet($templates, $package, $school);
        try {
            $writer = new Html($spreadsheet);
            $writer->writeAllSheets()->setEmbedImages(true)->setUseInlineCss(true);

            return $writer->generateHtmlAll();
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    protected function canonicalSpreadsheet(DocumentTemplate $template, SpjPackage $package, School $school): Spreadsheet
    {
        $sourcePath = $this->templateSourcePathExtended($template);
        if (! is_file($sourcePath)) {
            throw new \RuntimeException('Berkas template tidak ditemukan. Unggah ulang template ini.');
        }

        $source = IOFactory::load($sourcePath);

        try {
            [$sheet, $sheetName] = $this->resolveCanonicalWorksheet($source, $template);
            $this->fillCanonicalWorksheet($sheet, $package, $school);

            return $this->retainOnlyCanonicalWorksheet($source, $sheetName);
        } catch (\Throwable $exception) {
            $source->disconnectWorksheets();
            throw $exception;
        }
    }

    /** @param Collection<int, DocumentTemplate> $templates */
    protected function canonicalPackageSpreadsheet(Collection $templates, SpjPackage $package, School $school): Spreadsheet
    {
        if ($templates->isEmpty()) {
            throw new \RuntimeException('Belum ada template dokumen aktif yang sesuai dengan kategori paket ini.');
        }

        $packageSpreadsheet = null;

        foreach ($templates as $template) {
            if (strtolower((string) $template->format) !== 'xlsx') {
                throw new \RuntimeException('Paket dokumen saat ini hanya mendukung template Excel aktif.');
            }

            $single = $this->canonicalSpreadsheet($template, $package, $school);
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

    /** @return array{0:Worksheet,1:string} */
    private function resolveCanonicalWorksheet(Spreadsheet $spreadsheet, DocumentTemplate $template): array
    {
        $canonical = SpjDocumentTypeRegistry::canonical((string) $template->document_type);
        $definition = $canonical ? SpjDocumentTypeRegistry::definition($canonical) : null;
        $expectedSheet = trim((string) ($definition['sheet'] ?? ''));

        if ($expectedSheet !== '') {
            $sheet = $spreadsheet->getSheetByName($expectedSheet);
            if ($sheet instanceof Worksheet) {
                return [$sheet, $expectedSheet];
            }
        }

        if ($spreadsheet->getSheetCount() === 1) {
            $sheet = $spreadsheet->getSheet(0);

            return [$sheet, $sheet->getTitle()];
        }

        throw new \RuntimeException(
            'Sheet canonical '.($expectedSheet !== '' ? $expectedSheet : strtoupper((string) $template->document_type))
            .' tidak ditemukan pada workbook template.'
        );
    }

    private function retainOnlyCanonicalWorksheet(Spreadsheet $source, string $sheetName): Spreadsheet
    {
        $canonical = $source->getSheetByName($sheetName);
        if (! $canonical instanceof Worksheet) {
            throw new \RuntimeException('Sheet canonical '.$sheetName.' tidak tersedia untuk dirender.');
        }

        for ($index = $source->getSheetCount() - 1; $index >= 0; $index--) {
            if ($source->getSheet($index) === $canonical) {
                continue;
            }

            $source->removeSheetByIndex($index);
        }

        $canonical->setTitle($sheetName);
        $source->setActiveSheetIndex(0);

        return $source;
    }

    private function fillCanonicalWorksheet(Worksheet $sheet, SpjPackage $package, School $school): void
    {
        $values = $this->placeholders($package, $school);
        $this->normalizeMergedAnchorPlaceholders($sheet);
        $this->fillExcelItemsExtended($sheet, $package);
        $this->fillExcelWorkersExtended($sheet, $package);
        $this->fillExcelLetterhead($sheet, $school);

        $replacements = array_combine(
            array_map(fn ($key) => '{{'.$key.'}}', array_keys($values)),
            array_values($values),
        );

        $this->resolveMergedPlaceholderAnchors($sheet, $replacements);

        foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
            $cell = $sheet->getCell($coordinate);
            if (is_string($cell->getValue())) {
                $cell->setValue(strtr($cell->getValue(), $replacements));
            }
        }
        $this->replaceExcelHeaderFooterPlaceholders($sheet, $values);
    }

    private function normalizeMergedAnchorPlaceholders(Worksheet $sheet): void
    {
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
                    }
                }
            }
        }
    }

    /** @param array<string,string> $replacements */
    private function resolveMergedPlaceholderAnchors(Worksheet $sheet, array $replacements): void
    {
        foreach (array_values($sheet->getMergeCells()) as $range) {
            [[$startColumn, $startRow], [$endColumn, $endRow]] = Coordinate::rangeBoundaries($range);
            $anchor = Coordinate::stringFromColumnIndex($startColumn).$startRow;
            $placeholderCells = [];
            $resolvedValues = [];

            for ($row = $startRow; $row <= $endRow; $row++) {
                for ($column = $startColumn; $column <= $endColumn; $column++) {
                    $coordinate = Coordinate::stringFromColumnIndex($column).$row;
                    $value = $sheet->getCell($coordinate)->getValue();
                    if (! is_string($value) || ! preg_match('/^\s*\{\{[A-Za-z0-9_]+\}\}\s*$/u', $value)) {
                        continue;
                    }

                    $rendered = trim(strtr($value, $replacements));
                    $placeholderCells[$coordinate] = $rendered;
                    if ($rendered !== '') {
                        $resolvedValues[$rendered] = true;
                    }
                }
            }

            if ($placeholderCells === []) {
                continue;
            }

            if (count($resolvedValues) > 1) {
                throw new \RuntimeException(
                    'Placeholder pada merged range '.$sheet->getTitle().'!'.$range
                    .' menghasilkan nilai berbeda. Perbaiki kontrak placeholder tanpa mengubah merge template.'
                );
            }

            $resolved = array_key_first($resolvedValues) ?? '';
            $anchorValue = $sheet->getCell($anchor)->getValue();
            $anchorOwnsPlaceholder = array_key_exists($anchor, $placeholderCells);
            $anchorIsEmpty = $anchorValue === null || trim((string) $anchorValue) === '';

            if (! $anchorOwnsPlaceholder && ! $anchorIsEmpty) {
                throw new \RuntimeException(
                    'Merged range '.$sheet->getTitle().'!'.$range
                    .' menyimpan placeholder di luar anchor sementara anchor memiliki konten lain.'
                );
            }

            $sheet->getCell($anchor)->setValue($resolved);
            foreach (array_keys($placeholderCells) as $coordinate) {
                if ($coordinate !== $anchor) {
                    $sheet->getCell($coordinate)->setValue('');
                }
            }
        }
    }

    private function itemValuesExtended(SpjPackage $package, int $index): array
    {
        $item = $package->transaction->items[$index - 1];

        return [
            'ITEM_NO' => (string) $index,
            'ITEM_URAIAN' => (string) ($item->item_description ?: $item->description),
            'ITEM_VOLUME' => (string) $item->quantity,
            'ITEM_SATUAN' => (string) ($item->unit ?: '—'),
            'ITEM_HARGA_SATUAN' => app(SpjPlaceholderValueFormatter::class)->amount($item->unit_price),
            'ITEM_JUMLAH' => app(SpjPlaceholderValueFormatter::class)->amount($item->amount),
            'ITEM_KODE_REKENING' => (string) ($item->account_code ?: $package->transaction->account_code),
            'ITEM_NAMA_REKENING' => (string) ($item->account_name ?: $package->transaction->account_name),
        ];
    }

    private function fillExcelItemsExtended(Worksheet $sheet, SpjPackage $package): void
    {
        $items = $package->transaction->items;

        app(SpjRepeatingRowRenderer::class)->render(
            $sheet,
            '{{ITEM_NO}}',
            'ITEM_',
            $items->count(),
            fn (int $index): array => $this->itemValuesExtended($package, $index),
        );
    }

    private function workerValuesExtended(SpjPackage $package, int $index): array
    {
        $worker = $package->transaction->workers[$index - 1];

        return [
            'UPAH_NO' => (string) $index,
            'UPAH_NAMA' => (string) $worker->name,
            'UPAH_PEKERJAAN' => (string) $worker->job_description,
            'UPAH_HARI' => (string) $worker->work_days,
            'UPAH_TARIF_HARI' => app(SpjPlaceholderValueFormatter::class)->amount($worker->daily_rate),
            'UPAH_JUMLAH' => app(SpjPlaceholderValueFormatter::class)->amount($worker->amount),
            'UPAH_PENERIMA_KUITANSI' => $worker->is_receipt_recipient ? 'YA' : 'TIDAK',
        ];
    }

    private function fillExcelWorkersExtended(Worksheet $sheet, SpjPackage $package): void
    {
        $workers = $package->transaction->workers;

        app(SpjRepeatingRowRenderer::class)->render(
            $sheet,
            '{{UPAH_NO}}',
            'UPAH_',
            $workers->count(),
            fn (int $index): array => $this->workerValuesExtended($package, $index),
        );
    }

    private function spreadsheetPdfContentsExtended(Spreadsheet $spreadsheet, bool $allSheets): string
    {
        $nativePdf = app(SpjSpreadsheetPdfConverter::class)->convert($spreadsheet);
        if ($nativePdf !== null) {
            return $nativePdf;
        }

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

    private function pdfResponseExtended(string $contents, string $fileName, ?SpjPackage $package = null)
    {
        if ($package) {
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

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->safeDownloadName($fileName).'"',
            'Content-Length' => (string) strlen($contents),
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    private function templateSourcePathExtended(DocumentTemplate $template): string
    {
        $relativePath = ltrim((string) $template->file_path, '/\\');
        $disk = Storage::disk('local');
        if ($disk->exists($relativePath)) {
            return $disk->path($relativePath);
        }

        return storage_path('app/'.$relativePath);
    }

    private function safeDownloadName(string $name): string
    {
        return preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?: 'dokumen-spj';
    }

    private function plainNumber(float $value): string
    {
        return app(SpjPlaceholderValueFormatter::class)->number($value);
    }
}
