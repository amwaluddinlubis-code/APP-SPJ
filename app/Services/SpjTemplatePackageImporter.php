<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

final class SpjTemplatePackageImporter
{
    public function __construct(private readonly SpjTemplateValidator $validator) {}

    /**
     * Memeriksa workbook master terhadap seluruh kontrak document type canonical.
     * Workbook hanya dimuat sekali agar validasi paket besar tidak menghabiskan
     * memori dengan memuat file yang sama berulang kali.
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
        $results = [];
        $errors = [];
        $warnings = [];

        try {
            foreach (SpjDocumentTypeRegistry::all() as $documentType => $definition) {
                $expectedSheet = (string) $definition['sheet'];
                $sheet = $workbook->getSheetByName($expectedSheet);

                if (! $sheet) {
                    $errors[] = [
                        'document_type' => $documentType,
                        'code' => 'PACKAGE_SHEET_MISSING',
                        'message' => $documentType.' memerlukan sheet '.$expectedSheet.'.',
                    ];

                    continue;
                }

                [$markers, $markerRows] = $this->extractExcelMarkers($sheet);
                $result = $this->validator->validateMarkers(
                    $documentType,
                    $markers,
                    $markerRows,
                    $expectedSheet,
                );
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
        } finally {
            $workbook->disconnectWorksheets();
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
     *
     * Workbook master yang sudah lolos validasi disalin byte-for-byte untuk setiap
     * document type. Importer sengaja TIDAK lagi mengekstrak, menghapus, memindah,
     * atau menulis ulang worksheet dengan PhpSpreadsheet. Beberapa workbook Excel
     * valid memiliki relasi internal antarbagian OOXML yang dapat membuat writer
     * melempar "Sheet does not exist." ketika workbook dipecah menjadi satu sheet.
     * Pemilihan sheet canonical menjadi tanggung jawab renderer berdasarkan
     * document_type; tahap import hanya menyimpan sumber yang tervalidasi.
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
                $destinationPath = $disk->path($relativePath);

                try {
                    $this->copyValidatedMasterWorkbook($path, $destinationPath);
                } catch (Throwable $exception) {
                    throw new RuntimeException(
                        'Gagal menyimpan sumber template '.$documentType.': '.$exception->getMessage(),
                        0,
                        $exception,
                    );
                }

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

    private function copyValidatedMasterWorkbook(string $sourcePath, string $destinationPath): void
    {
        if (! is_file($sourcePath)) {
            throw new RuntimeException('Workbook master sumber tidak ditemukan.');
        }

        $directory = dirname($destinationPath);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Direktori penyimpanan template tidak dapat dibuat.');
        }

        if (! copy($sourcePath, $destinationPath)) {
            throw new RuntimeException('Workbook master gagal disalin ke penyimpanan template.');
        }

        if (! is_file($destinationPath) || filesize($destinationPath) !== filesize($sourcePath)) {
            @unlink($destinationPath);
            throw new RuntimeException('Salinan workbook master tidak lengkap.');
        }
    }

    /** @return array{0:array<int,string>,1:array<string,array<int,int>>} */
    private function extractExcelMarkers(Worksheet $sheet): array
    {
        $markers = [];
        $markerRows = [];

        foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
            $value = $sheet->getCell($coordinate)->getValue();
            if (! is_string($value)) {
                continue;
            }

            preg_match_all('/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/u', $value, $matches);
            foreach ($matches[1] ?? [] as $marker) {
                $marker = strtoupper(trim((string) $marker));
                if ($marker === '') {
                    continue;
                }

                $row = $sheet->getCell($coordinate)->getRow();
                $markers[$marker] = true;
                $markerRows[$marker][(int) $row] = (int) $row;
            }
        }

        return [array_keys($markers), array_map('array_values', $markerRows)];
    }
}
