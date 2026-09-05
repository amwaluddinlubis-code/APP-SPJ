<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use ZipArchive;

final class SpjTemplateValidator
{
    /**
     * Validate a DOCX/XLSX template file against the canonical document contract.
     *
     * @return array{
     *     valid: bool,
     *     document_type: string|null,
     *     sheet: string|null,
     *     markers: array<int,string>,
     *     errors: array<int,array{code:string,message:string,markers:array<int,string>}>,
     *     warnings: array<int,array{code:string,message:string,markers:array<int,string>}>
     * }
     */
    public function validateFile(string $documentType, string $path, ?string $format = null): array
    {
        $canonical = SpjDocumentTypeRegistry::canonical($documentType);
        $definition = $canonical ? SpjDocumentTypeRegistry::definition($canonical) : null;

        if (! $canonical || ! $definition) {
            return $this->invalidDocumentType($documentType);
        }

        if (! is_file($path)) {
            throw new RuntimeException('Berkas template tidak ditemukan untuk divalidasi.');
        }

        $extension = strtolower($format ?: pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'xlsx' => $this->validateExcel($canonical, $definition, $path),
            'docx' => $this->validateWord($canonical, $definition, $path),
            default => throw new RuntimeException('Format template tidak didukung. Gunakan DOCX atau XLSX.'),
        };
    }

    /**
     * Validate an already extracted marker set. Useful for focused tests and
     * future template editors without requiring a physical document file.
     *
     * @param  array<int,string>  $markers
     * @param  array<string,array<int,int>>  $markerRows
     */
    public function validateMarkers(string $documentType, array $markers, array $markerRows = [], ?string $sheet = null): array
    {
        $canonical = SpjDocumentTypeRegistry::canonical($documentType);
        $definition = $canonical ? SpjDocumentTypeRegistry::definition($canonical) : null;

        if (! $canonical || ! $definition) {
            return $this->invalidDocumentType($documentType);
        }

        $markers = collect($markers)
            ->map(fn ($marker) => strtoupper(trim((string) $marker)))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $errors = [];
        $warnings = [];
        $present = array_fill_keys($markers, true);

        $required = array_values(array_unique(array_merge(
            $definition['required'],
            $definition['repeat_required'],
        )));
        $missingRequired = array_values(array_filter(
            $required,
            fn (string $marker): bool => ! isset($present[$marker])
        ));

        if ($missingRequired) {
            $errors[] = $this->issue(
                'MISSING_REQUIRED',
                'Placeholder wajib belum tersedia: '.implode(', ', $missingRequired).'.',
                $missingRequired,
            );
        }

        foreach (SpjDocumentTypeRegistry::pairedPlaceholders() as [$nameMarker, $nipMarker]) {
            $hasName = isset($present[$nameMarker]);
            $hasNip = isset($present[$nipMarker]);

            if ($hasName === $hasNip) {
                continue;
            }

            $missing = $hasName ? $nipMarker : $nameMarker;
            $existing = $hasName ? $nameMarker : $nipMarker;
            $errors[] = $this->issue(
                'PAIRED_PLACEHOLDER_MISSING',
                "{$existing} harus dipasangkan dengan {$missing}.",
                [$existing, $missing],
            );
        }

        $known = $this->knownPlaceholders();
        $unknown = array_values(array_filter($markers, fn (string $marker): bool => ! isset($known[$marker])));
        if ($unknown) {
            $errors[] = $this->issue(
                'UNKNOWN_PLACEHOLDER',
                'Placeholder tidak dikenal oleh engine template: '.implode(', ', $unknown).'.',
                $unknown,
            );
        }

        $allowed = array_fill_keys(SpjDocumentTypeRegistry::placeholdersFor($canonical), true);
        $unexpectedKnown = array_values(array_filter(
            $markers,
            fn (string $marker): bool => isset($known[$marker]) && ! isset($allowed[$marker])
        ));
        if ($unexpectedKnown) {
            $warnings[] = $this->issue(
                'UNEXPECTED_PLACEHOLDER',
                'Placeholder dikenal tetapi tidak termasuk kontrak '.$canonical.': '.implode(', ', $unexpectedKnown).'.',
                $unexpectedKnown,
            );
        }

        if ($markerRows) {
            foreach ($this->repeatGroups($definition['repeat_required']) as $group => $groupMarkers) {
                $rows = [];
                foreach ($groupMarkers as $marker) {
                    foreach ($markerRows[$marker] ?? [] as $row) {
                        $rows[$marker][(int) $row] = true;
                    }
                }

                if (count($rows) !== count($groupMarkers)) {
                    continue; // Presence is already handled by MISSING_REQUIRED.
                }

                $commonRows = null;
                foreach ($rows as $rowSet) {
                    $rowNumbers = array_keys($rowSet);
                    $commonRows = $commonRows === null
                        ? $rowNumbers
                        : array_values(array_intersect($commonRows, $rowNumbers));
                }

                if ($commonRows === []) {
                    $errors[] = $this->issue(
                        'REPEAT_ROW_MISMATCH',
                        'Placeholder repeat '.$group.' wajib berada pada satu baris contoh yang sama.',
                        $groupMarkers,
                    );
                }
            }
        }

        return [
            'valid' => $errors === [],
            'document_type' => $canonical,
            'sheet' => $sheet,
            'markers' => $markers,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /** @param array<string,mixed> $definition */
    private function validateExcel(string $canonical, array $definition, string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $expectedSheet = (string) $definition['sheet'];
        $technical = array_fill_keys(SpjDocumentTypeRegistry::technicalSheets(), true);
        $selected = null;
        $sheetWarning = null;

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            if ($sheet->getTitle() === $expectedSheet) {
                $selected = $sheet;
                break;
            }
        }

        if (! $selected) {
            $candidates = [];
            foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
                if (! isset($technical[$sheet->getTitle()])) {
                    $candidates[] = $sheet;
                }
            }

            if (count($candidates) === 1) {
                $selected = $candidates[0];
                $sheetWarning = $this->issue(
                    'NON_CANONICAL_SHEET_NAME',
                    'Nama sheet sebaiknya '.$expectedSheet.'; saat ini '.$selected->getTitle().'.',
                    [],
                );
            } else {
                $result = $this->validateMarkers($canonical, [], [], null);
                $result['errors'][] = $this->issue(
                    'EXPECTED_SHEET_MISSING',
                    'Sheet canonical '.$expectedSheet.' tidak ditemukan pada workbook multi-sheet.',
                    [],
                );
                $result['valid'] = false;

                return $result;
            }
        }

        [$markers, $markerRows] = $this->extractExcelMarkers($selected);
        $result = $this->validateMarkers($canonical, $markers, $markerRows, $selected->getTitle());

        if ($sheetWarning) {
            $result['warnings'][] = $sheetWarning;
        }

        return $result;
    }

    /** @param array<string,mixed> $definition */
    private function validateWord(string $canonical, array $definition, string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Template DOCX tidak dapat dibuka.');
        }

        $content = '';
        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);
                if (! is_string($name) || ! preg_match('#^word/(document|header\d*|footer\d*)\.xml$#', $name)) {
                    continue;
                }

                $xml = $zip->getFromIndex($index);
                if (is_string($xml)) {
                    // Strip XML tags so macros split across Word runs can still be detected.
                    $content .= ' '.html_entity_decode((string) preg_replace('/<[^>]+>/', '', $xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
                }
            }
        } finally {
            $zip->close();
        }

        return $this->validateMarkers($canonical, $this->extractMarkers($content));
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

            $cellMarkers = $this->extractMarkers($value);
            if (! $cellMarkers) {
                continue;
            }

            $row = $sheet->getCell($coordinate)->getRow();
            foreach ($cellMarkers as $marker) {
                $markers[$marker] = true;
                $markerRows[$marker][(int) $row] = (int) $row;
            }
        }

        return [array_keys($markers), array_map('array_values', $markerRows)];
    }

    /** @return array<int,string> */
    private function extractMarkers(string $content): array
    {
        preg_match_all('/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/u', $content, $matches);

        return collect($matches[1] ?? [])
            ->map(fn ($marker) => strtoupper(trim((string) $marker)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<string,true> */
    private function knownPlaceholders(): array
    {
        $markers = collect(SpjTemplateService::placeholderGroups())->flatten()->all();

        return array_fill_keys(array_map(fn ($marker) => strtoupper((string) $marker), $markers), true);
    }

    /**
     * @param array<int,string> $markers
     * @return array<string,array<int,string>>
     */
    private function repeatGroups(array $markers): array
    {
        $groups = [];
        foreach ($markers as $marker) {
            $prefix = str_contains($marker, '_') ? strstr($marker, '_', true) : $marker;
            $groups[$prefix][] = $marker;
        }

        return $groups;
    }

    /** @param array<int,string> $markers */
    private function issue(string $code, string $message, array $markers): array
    {
        return [
            'code' => $code,
            'message' => $message,
            'markers' => array_values($markers),
        ];
    }

    private function invalidDocumentType(string $documentType): array
    {
        return [
            'valid' => false,
            'document_type' => null,
            'sheet' => null,
            'markers' => [],
            'errors' => [$this->issue(
                'UNKNOWN_DOCUMENT_TYPE',
                'Document type tidak dikenal: '.strtoupper(trim($documentType)).'.',
                [],
            )],
            'warnings' => [],
        ];
    }
}
