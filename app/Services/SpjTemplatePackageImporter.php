<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Throwable;

final class SpjTemplatePackageImporter
{
    public function __construct(private readonly SpjTemplateValidator $validator) {}

    /**
     * Memeriksa workbook master terhadap seluruh kontrak document type canonical.
     *
     * @return array{
     *     valid:bool,
     *     results:array<string,array<string,mixed>>,
     *     errors:array<int,array{document_type:string,code:string,message:string}>,
     *     warnings:array<int,array{document_type:string,code:string,message:string}>
     * }
     */
    public function validatePackage(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('Workbook paket template tidak ditemukan.');
        }

        $workbook = IOFactory::load($path);
        try {
            $sheetNames = $workbook->getSheetNames();
        } finally {
            $workbook->disconnectWorksheets();
        }

        $results = [];
        $errors = [];
        $warnings = [];

        foreach (SpjDocumentTypeRegistry::all() as $documentType => $definition) {
            $expectedSheet = (string) $definition['sheet'];
            if (! in_array($expectedSheet, $sheetNames, true)) {
                $errors[] = [
                    'document_type' => $documentType,
                    'code' => 'PACKAGE_SHEET_MISSING',
                    'message' => $documentType.' memerlukan sheet '.$expectedSheet.'.',
                ];

                continue;
            }

            $result = $this->validator->validateFile($documentType, $path, 'xlsx');
            $results[$documentType] = $result;

            foreach ($result['errors'] as $issue) {
                $errors[] = [
                    'document_type' => $documentType,
                    'code' => (string) $issue['code'],
                    'message' => (string) $issue['message'],
                ];
            }
            foreach ($result['warnings'] as $issue) {
                $warnings[] = [
                    'document_type' => $documentType,
                    'code' => (string) $issue['code'],
                    'message' => (string) $issue['message'],
                ];
            }
        }

        return [
            'valid' => $errors === [],
            'results' => $results,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Import atomik: semua kontrak harus valid sebelum file maupun record diganti.
     * Setiap document type disimpan sebagai workbook XLSX independen berisi satu
     * sheet canonical agar template dapat diunduh dan direvisi secara terpisah.
     *
     * @return array<string,mixed>
     */
    public function importPackage(int $fiscalYearId, string $path, bool $replaceExisting = false): array
    {
        $validation = $this->validatePackage($path);
        if (! $validation['valid']) {
            return $validation + ['imported' => 0, 'replaced' => 0];
        }

        $definitions = SpjDocumentTypeRegistry::all();
        $existing = DocumentTemplate::query()
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('format', 'xlsx')
            ->whereIn('document_type', array_keys($definitions))
            ->get()
            ->keyBy('document_type');

        if (! $replaceExisting && $existing->isNotEmpty()) {
            $types = $existing->keys()->sort()->implode(', ');
            throw new RuntimeException(
                'Template canonical XLSX sudah tersedia: '.$types.'. Centang "Ganti template yang sudah ada" untuk menggantinya.'
            );
        }

        $disk = Storage::disk('local');
        $directory = 'document-templates/'.$fiscalYearId.'/package';
        $disk->makeDirectory($directory);

        $newPaths = [];
        $oldPaths = [];

        try {
            foreach ($definitions as $documentType => $definition) {
                $fileName = strtolower($documentType).'-'.bin2hex(random_bytes(8)).'.xlsx';
                $relativePath = $directory.'/'.$fileName;
                $this->extractCanonicalSheet($path, (string) $definition['sheet'], $disk->path($relativePath));
                $newPaths[$documentType] = $relativePath;
            }

            DB::connection('school')->transaction(function () use (
                $definitions,
                $existing,
                $fiscalYearId,
                $newPaths,
                &$oldPaths
            ): void {
                foreach ($definitions as $documentType => $definition) {
                    $current = $existing->get($documentType);
                    if ($current && $current->file_path) {
                        $oldPaths[] = (string) $current->file_path;
                    }

                    DocumentTemplate::query()->updateOrCreate(
                        [
                            'fiscal_year_id' => $fiscalYearId,
                            'document_type' => $documentType,
                            'format' => 'xlsx',
                        ],
                        [
                            'name' => (string) $definition['label'],
                            'file_path' => $newPaths[$documentType],
                            'applicable_categories' => $definition['applicable_categories'] ?? [],
                            'is_active' => true,
                        ]
                    );
                }
            });
        } catch (Throwable $exception) {
            foreach ($newPaths as $newPath) {
                $disk->delete($newPath);
            }

            throw $exception;
        }

        foreach (array_unique($oldPaths) as $oldPath) {
            if (! in_array($oldPath, $newPaths, true)) {
                $disk->delete($oldPath);
            }
        }

        return $validation + [
            'imported' => count($definitions),
            'replaced' => $existing->count(),
        ];
    }

    private function extractCanonicalSheet(string $sourcePath, string $sheetName, string $destinationPath): void
    {
        $source = IOFactory::load($sourcePath);
        $single = null;

        try {
            $target = $source->getSheetByName($sheetName);
            if (! $target) {
                throw new RuntimeException('Sheet '.$sheetName.' tidak ditemukan saat pemisahan paket template.');
            }

            // Do not mutate the source workbook by deleting its other sheets. On
            // complex workbooks PhpSpreadsheet can leave the active-sheet pointer
            // referencing a removed worksheet and fail later with "Sheet does not exist.".
            // Instead, copy only the canonical worksheet into a fresh workbook.
            $single = new Spreadsheet;
            $single->addExternalSheet(clone $target, 0);

            // The new Spreadsheet starts with one blank worksheet. After inserting
            // the canonical sheet at index 0, remove only that known blank sheet.
            if ($single->getSheetCount() > 1) {
                $single->removeSheetByIndex(1);
            }

            $single->setActiveSheetIndex(0);
            (new Xlsx($single))->save($destinationPath);
        } finally {
            if ($single instanceof Spreadsheet) {
                $single->disconnectWorksheets();
            }
            $source->disconnectWorksheets();
        }
    }
}
