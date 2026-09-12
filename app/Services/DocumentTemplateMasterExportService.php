<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use App\Support\ActiveSpjContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Throwable;

final class DocumentTemplateMasterExportService
{
    public function __construct(
        private readonly ActiveSpjContext $context,
        private readonly SpjTemplatePackageImporter $packageImporter,
    ) {}

    /** @return array{path:string,download_name:string} */
    public function generate(): array
    {
        $definitions = SpjDocumentTypeRegistry::all();
        $templates = $this->activeXlsxTemplates(array_keys($definitions));
        $this->ensureCompleteTemplateSet($definitions, $templates);

        $directory = storage_path('app/generated-documents');
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Direktori keluaran master template tidak dapat dibuat.');
        }

        $path = $directory.'/MASTER-TEMPLATE-SPJ-TERBARU-'.bin2hex(random_bytes(6)).'.xlsx';
        $master = new Spreadsheet;

        try {
            foreach ($definitions as $documentType => $definition) {
                /** @var DocumentTemplate $template */
                $template = $templates->get($documentType);
                $sourcePath = $this->storedTemplatePath($template, $documentType);
                $source = IOFactory::load($sourcePath);

                try {
                    $expectedSheet = (string) $definition['sheet'];
                    $selected = $this->canonicalSheet($source, $documentType, $expectedSheet);
                    $copy = clone $selected;
                    $copy->setTitle($expectedSheet);
                    $copy->setSheetState(Worksheet::SHEETSTATE_VISIBLE);
                    $master->addExternalSheet($copy);
                } finally {
                    $source->disconnectWorksheets();
                }
            }

            // Spreadsheet always starts with one empty sheet. Remove it only after
            // every canonical sheet has been copied so the workbook is never left
            // temporarily without a worksheet during composition.
            $master->removeSheetByIndex(0);
            $master->setActiveSheetIndex(0);

            (new Xlsx($master))->save($path);
        } catch (Throwable $exception) {
            if (is_file($path)) {
                @unlink($path);
            }

            if ($exception instanceof RuntimeException) {
                throw $exception;
            }

            throw new RuntimeException(
                'Master template terbaru gagal dibuat dari template XLSX aktif.',
                0,
                $exception,
            );
        } finally {
            $master->disconnectWorksheets();
        }

        try {
            $validation = $this->packageImporter->validatePackage($path);
        } catch (Throwable $exception) {
            @unlink($path);

            throw new RuntimeException(
                'Master template terbaru berhasil disusun tetapi gagal dibaca ulang untuk validasi.',
                0,
                $exception,
            );
        }

        if (! $validation['valid']) {
            @unlink($path);
            $summary = collect($validation['errors'])
                ->take(3)
                ->map(fn (array $issue): string => '['.$issue['document_type'].'] '.$issue['message'])
                ->implode(' ');

            throw new RuntimeException(
                'Master template terbaru gagal validasi canonical.'.($summary !== '' ? ' '.$summary : '')
            );
        }

        return [
            'path' => $path,
            'download_name' => 'MASTER-TEMPLATE-SPJ-TERBARU.xlsx',
        ];
    }

    /**
     * @param  array<int,string>  $documentTypes
     * @return Collection<string,DocumentTemplate>
     */
    private function activeXlsxTemplates(array $documentTypes): Collection
    {
        return DocumentTemplate::query()
            ->where('fiscal_year_id', $this->context->fiscalYearId())
            ->where('format', 'xlsx')
            ->where('is_active', true)
            ->whereIn('document_type', $documentTypes)
            ->get()
            ->keyBy(fn (DocumentTemplate $template): string => (string) (
                SpjDocumentTypeRegistry::canonical((string) $template->document_type)
                    ?? strtoupper(trim((string) $template->document_type))
            ));
    }

    /**
     * @param  array<string,array<string,mixed>>  $definitions
     * @param  Collection<string,DocumentTemplate>  $templates
     */
    private function ensureCompleteTemplateSet(array $definitions, Collection $templates): void
    {
        $missing = collect(array_keys($definitions))
            ->reject(fn (string $documentType): bool => $templates->has($documentType))
            ->values();

        if ($missing->isEmpty()) {
            return;
        }

        throw new RuntimeException(
            'Master template belum dapat dibuat. Template XLSX aktif belum tersedia untuk: '.$missing->implode(', ').'.'
        );
    }

    private function storedTemplatePath(DocumentTemplate $template, string $documentType): string
    {
        $disk = Storage::disk('local');
        $relativePath = trim((string) $template->file_path);

        if ($relativePath === '' || ! $disk->exists($relativePath)) {
            throw new RuntimeException(
                'Master template belum dapat dibuat. Berkas template XLSX aktif tidak ditemukan untuk: '.$documentType.'.'
            );
        }

        return $disk->path($relativePath);
    }

    private function canonicalSheet(Spreadsheet $workbook, string $documentType, string $expectedSheet): Worksheet
    {
        $sheet = $workbook->getSheetByName($expectedSheet);
        if ($sheet instanceof Worksheet) {
            return $sheet;
        }

        foreach ($workbook->getAllSheets() as $candidate) {
            if (strcasecmp($candidate->getTitle(), $expectedSheet) === 0) {
                return $candidate;
            }
        }

        $technicalSheets = array_map('strtoupper', SpjDocumentTypeRegistry::technicalSheets());
        $candidates = array_values(array_filter(
            $workbook->getAllSheets(),
            fn (Worksheet $candidate): bool => ! in_array(strtoupper($candidate->getTitle()), $technicalSheets, true),
        ));

        if (count($candidates) === 1) {
            return $candidates[0];
        }

        throw new RuntimeException(
            'Master template belum dapat dibuat. Template '.$documentType.' tidak memiliki sheet canonical '.$expectedSheet.' yang dapat dikenali.'
        );
    }
}
