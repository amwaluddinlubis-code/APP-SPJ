<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use ZipArchive;

final class SpjUnresolvedPlaceholderGuard
{
    /** @return array<int,string> */
    public function findInFile(string $documentType, string $path, ?string $format = null): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('Berkas hasil generate tidak ditemukan untuk diperiksa.');
        }

        $extension = strtolower($format ?: pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'xlsx' => $this->findInExcel($documentType, $path),
            'docx' => $this->findInWord($path),
            default => throw new RuntimeException('Format hasil generate tidak didukung oleh placeholder guard.'),
        };
    }

    public function assertResolved(string $documentType, string $path, ?string $format = null): void
    {
        $markers = $this->findInFile($documentType, $path, $format);
        if ($markers === []) {
            return;
        }

        throw new RuntimeException(
            'Dokumen hasil generate masih memiliki placeholder yang belum terisi: '.implode(', ', $markers).'.'
        );
    }

    /** @return array<int,string> */
    private function findInExcel(string $documentType, string $path): array
    {
        $canonical = SpjDocumentTypeRegistry::canonical($documentType);
        $definition = $canonical ? SpjDocumentTypeRegistry::definition($canonical) : null;
        if (! $definition) {
            throw new RuntimeException('Document type tidak dikenal untuk pemeriksaan hasil generate.');
        }

        $spreadsheet = IOFactory::load($path);
        $expectedSheet = (string) $definition['sheet'];
        $selected = $spreadsheet->getSheetByName($expectedSheet);

        if (! $selected) {
            $technical = array_fill_keys(SpjDocumentTypeRegistry::technicalSheets(), true);
            $candidates = [];
            foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
                if (! isset($technical[$sheet->getTitle()])) {
                    $candidates[] = $sheet;
                }
            }

            if (count($candidates) === 1) {
                $selected = $candidates[0];
            } else {
                throw new RuntimeException('Sheet canonical '.$expectedSheet.' tidak ditemukan pada hasil generate.');
            }
        }

        $markers = [];
        foreach ($selected->getCellCollection()->getCoordinates() as $coordinate) {
            $value = $selected->getCell($coordinate)->getValue();
            if (! is_string($value)) {
                continue;
            }

            foreach ($this->extractMarkers($value) as $marker) {
                $markers[$marker] = true;
            }
        }

        return array_keys($markers);
    }

    /** @return array<int,string> */
    private function findInWord(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Dokumen DOCX hasil generate tidak dapat dibuka.');
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
                    $content .= ' '.html_entity_decode((string) preg_replace('/<[^>]+>/', '', $xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
                }
            }
        } finally {
            $zip->close();
        }

        return $this->extractMarkers($content);
    }

    /** @return array<int,string> */
    private function extractMarkers(string $content): array
    {
        preg_match_all('/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/u', $content, $matches);

        return collect($matches[1] ?? [])
            ->map(fn ($marker) => strtoupper(trim((string) $marker)))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
