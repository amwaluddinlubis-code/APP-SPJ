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
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Html;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Dompdf as SpreadsheetPdfWriter;

class ExtendedSpjTemplateService extends SpjTemplateService
{
    /** @return array<string,array<int,string>> */
    public static function placeholderGroups(): array
    {
        return parent::placeholderGroups() + [
            'Konsumsi & kegiatan' => [
                'TANGGAL_KEGIATAN',
                'TEMPAT_KEGIATAN',
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
            $participantPrices[] = $this->rupiahValue($price);
            $participantAmounts[] = $this->rupiahValue($amount);
            $totalConsumption += $amount;
        }

        $fallback = SpjDocumentTypeRegistry::EMPTY_SCALAR_VALUE;

        return $values + [
            'TANGGAL_KEGIATAN' => $eventDate,
            'TEMPAT_KEGIATAN' => $eventLocation !== '' ? $eventLocation : $fallback,
            'NAMA_PENANGGUNG_JAWAB' => $fallback,
            'NIP_PENANGGUNG_JAWAB' => $fallback,
            'KONSUMSI_NO' => $participantNumbers !== [] ? implode("\n", $participantNumbers) : $fallback,
            'KONSUMSI_NAMA' => $participantNames !== [] ? implode("\n", $participantNames) : $fallback,
            'KONSUMSI_IDENTITAS' => $participantIdentities !== [] ? implode("\n", $participantIdentities) : $fallback,
            'KONSUMSI_PORSI' => $participantPortions !== [] ? implode("\n", $participantPortions) : $fallback,
            'KONSUMSI_HARGA_PORSI' => $participantPrices !== [] ? implode("\n", $participantPrices) : $fallback,
            'KONSUMSI_JUMLAH' => $participantAmounts !== [] ? implode("\n", $participantAmounts) : $fallback,
            'TOTAL_KONSUMSI' => $this->rupiahValue($totalConsumption),
        ];
    }

    /**
     * XLSX package imports now preserve the complete master workbook. Render only
     * the canonical worksheet for this document type and keep DOCX on the legacy
     * parent implementation.
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
                $template->document_type.'-'.$package->document_number.'.pdf',
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

            return response()->download(
                $temporaryFile,
                $this->safeDownloadName('PAKET-SPJ-'.$package->document_number.'.xlsx'),
            )->deleteFileAfterSend(true);
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
                'PAKET-SPJ-'.$package->document_number.'.pdf',
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

    private function canonicalSpreadsheet(DocumentTemplate $template, SpjPackage $package, School $school): Spreadsheet
    {
        $sourcePath = $this->templateSourcePathExtended($template);
        if (! is_file($sourcePath)) {
            throw new \RuntimeException('Berkas template tidak ditemukan. Unggah ulang template ini.');
        }

        $source = IOFactory::load($sourcePath);
        try {
            [$sheet, $sheetName] = $this->resolveCanonicalWorksheet($source, $template);
            $this->fillCanonicalWorksheet($sheet, $package, $school);

            return $this->copyWorksheetToStandaloneWorkbook($source, $sheetName);
        } finally {
            $source->disconnectWorksheets();
        }
    }

    /** @param Collection<int, DocumentTemplate> $templates */
    private function canonicalPackageSpreadsheet(Collection $templates, SpjPackage $package, School $school): Spreadsheet
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

    private function copyWorksheetToStandaloneWorkbook(Spreadsheet $source, string $sheetName): Spreadsheet
    {
        if (! $source->getSheetByName($sheetName)) {
            throw new \RuntimeException('Sheet canonical '.$sheetName.' tidak tersedia untuk dirender.');
        }

        // PhpSpreadsheet requires a copied worksheet to be attached to its source
        // workbook before addExternalSheet() can safely transfer styles/resources.
        $copy = $source->duplicateWorksheetByTitle($sheetName);

        $standalone = new Spreadsheet;
        $standalone->addExternalSheet($copy);
        $standalone->setActiveSheetIndex(1);
        $standalone->removeSheetByIndex(0);
        $standalone->getSheet(0)->setTitle($sheetName);
        $standalone->setActiveSheetIndex(0);

        return $standalone;
    }

    private function fillCanonicalWorksheet(Worksheet $sheet, SpjPackage $package, School $school): void
    {
        $values = $this->placeholders($package, $school);
        $this->fillExcelItemsExtended($sheet, $package);
        $this->fillExcelWorkersExtended($sheet, $package);
        $this->fillExcelLetterheadExtended($sheet, $school);

        $replacements = array_combine(
            array_map(fn ($key) => '{{'.$key.'}}', array_keys($values)),
            array_values($values),
        );

        foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
            $cell = $sheet->getCell($coordinate);
            if (is_string($cell->getValue())) {
                $cell->setValue(strtr($cell->getValue(), $replacements));
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
            'ITEM_HARGA_SATUAN' => $this->rupiahValue((float) $item->unit_price),
            'ITEM_JUMLAH' => $this->rupiahValue((float) $item->amount),
            'ITEM_KODE_REKENING' => (string) ($item->account_code ?: $package->transaction->account_code),
            'ITEM_NAMA_REKENING' => (string) ($item->account_name ?: $package->transaction->account_name),
        ];
    }

    private function fillExcelItemsExtended(Worksheet $sheet, SpjPackage $package): void
    {
        $rows = [];
        foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
            if (str_contains((string) $sheet->getCell($coordinate)->getValue(), '{{ITEM_NO}}')) {
                $rows[] = $sheet->getCell($coordinate)->getRow();
            }
        }

        $rows = array_values(array_unique($rows));
        $items = $package->transaction->items;
        if ($rows === [] || $items->isEmpty()) {
            return;
        }

        $row = $rows[0];
        $highestColumn = $sheet->getHighestColumn();
        $lastColumn = Coordinate::columnIndexFromString($highestColumn);
        $original = [];
        for ($column = 1; $column <= $lastColumn; $column++) {
            $original[$column] = $sheet->getCell(Coordinate::stringFromColumnIndex($column).$row)->getValue();
        }

        if ($items->count() > count($rows)) {
            $extra = $items->count() - count($rows);
            $insertAt = max($rows) + 1;
            $sheet->insertNewRowBefore($insertAt, $extra);
            for ($copyRow = $insertAt; $copyRow < $insertAt + $extra; $copyRow++) {
                $sheet->duplicateStyle($sheet->getStyle($row), 'A'.$copyRow.':'.$highestColumn.$copyRow);
                $sheet->getRowDimension($copyRow)->setRowHeight($sheet->getRowDimension($row)->getRowHeight());
                $rows[] = $copyRow;
            }
        }

        sort($rows);
        foreach ($items as $index => $item) {
            $replacements = [];
            foreach ($this->itemValuesExtended($package, $index + 1) as $key => $value) {
                $replacements['{{'.$key.'}}'] = $value;
            }
            foreach ($original as $column => $value) {
                if (is_string($value)) {
                    $sheet->getCell(Coordinate::stringFromColumnIndex($column).$rows[$index])->setValue(strtr($value, $replacements));
                }
            }
        }

        foreach (array_slice($rows, $items->count()) as $emptyRow) {
            foreach ($original as $column => $value) {
                if (is_string($value) && str_contains($value, '{{ITEM_')) {
                    $sheet->getCell(Coordinate::stringFromColumnIndex($column).$emptyRow)->setValue('');
                }
            }
        }
    }

    private function workerValuesExtended(SpjPackage $package, int $index): array
    {
        $worker = $package->transaction->workers[$index - 1];

        return [
            'UPAH_NO' => (string) $index,
            'UPAH_NAMA' => (string) $worker->name,
            'UPAH_PEKERJAAN' => (string) $worker->job_description,
            'UPAH_HARI' => (string) $worker->work_days,
            'UPAH_TARIF_HARI' => $this->rupiahValue((float) $worker->daily_rate),
            'UPAH_JUMLAH' => $this->rupiahValue((float) $worker->amount),
            'UPAH_PENERIMA_KUITANSI' => $worker->is_receipt_recipient ? 'YA' : 'TIDAK',
        ];
    }

    private function fillExcelWorkersExtended(Worksheet $sheet, SpjPackage $package): void
    {
        $row = null;
        foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
            if (str_contains((string) $sheet->getCell($coordinate)->getValue(), '{{UPAH_NO}}')) {
                $row = $sheet->getCell($coordinate)->getRow();
                break;
            }
        }

        $workers = $package->transaction->workers;
        if (! $row || $workers->isEmpty()) {
            return;
        }

        $highestColumn = $sheet->getHighestColumn();
        $lastColumn = Coordinate::columnIndexFromString($highestColumn);
        $original = [];
        for ($column = 1; $column <= $lastColumn; $column++) {
            $original[$column] = $sheet->getCell(Coordinate::stringFromColumnIndex($column).$row)->getValue();
        }

        if ($workers->count() > 1) {
            $sheet->insertNewRowBefore($row + 1, $workers->count() - 1);
            for ($copyRow = $row + 1; $copyRow < $row + $workers->count(); $copyRow++) {
                $sheet->duplicateStyle($sheet->getStyle($row), 'A'.$copyRow.':'.$highestColumn.$copyRow);
                $sheet->getRowDimension($copyRow)->setRowHeight($sheet->getRowDimension($row)->getRowHeight());
            }
        }

        foreach ($workers as $index => $worker) {
            $replacements = [];
            foreach ($this->workerValuesExtended($package, $index + 1) as $key => $value) {
                $replacements['{{'.$key.'}}'] = $value;
            }
            foreach ($original as $column => $value) {
                if (is_string($value)) {
                    $sheet->getCell(Coordinate::stringFromColumnIndex($column).($row + $index))->setValue(strtr($value, $replacements));
                }
            }
        }
    }

    private function fillExcelLetterheadExtended(Worksheet $sheet, School $school): void
    {
        $relativePath = $school->letterhead_path;
        $disk = Storage::disk('local');
        if (blank($relativePath) || ! $disk->exists($relativePath)) {
            return;
        }

        $path = $disk->path($relativePath);
        foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
            if (trim((string) $sheet->getCell($coordinate)->getValue()) !== '{{KOP_SURAT}}') {
                continue;
            }

            $sheet->setCellValue($coordinate, '');
            $drawing = new Drawing;
            $drawing->setPath($path);
            $drawing->setCoordinates($coordinate);
            $drawing->setHeight(115);
            $drawing->setOffsetX(2);
            $drawing->setOffsetY(2);
            $drawing->setWorksheet($sheet);
            break;
        }
    }

    private function spreadsheetPdfContentsExtended(Spreadsheet $spreadsheet, bool $allSheets): string
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), 'spj-pdf-');
        if ($temporaryFile === false) {
            throw new \RuntimeException('File sementara PDF tidak dapat dibuat.');
        }

        try {
            $writer = new SpreadsheetPdfWriter($spreadsheet);
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

    private function pdfResponseExtended(string $contents, string $fileName)
    {
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

    private function rupiahValue(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    private function plainNumber(float $value): string
    {
        return abs($value - round($value)) < 0.00001
            ? number_format($value, 0, ',', '.')
            : rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }
}
