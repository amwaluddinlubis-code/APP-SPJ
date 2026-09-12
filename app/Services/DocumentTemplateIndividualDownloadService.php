<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

final class DocumentTemplateIndividualDownloadService
{
    public function prepare(string $relativePath, string $documentType, string $format): ?string
    {
        if (strtolower(trim($format)) !== 'xlsx') {
            return null;
        }

        $canonical = SpjDocumentTypeRegistry::canonical($documentType);
        $definition = $canonical ? SpjDocumentTypeRegistry::definition($canonical) : null;
        if (! $definition) {
            return null;
        }

        $source = Storage::disk('local')->path(ltrim($relativePath, '/\\'));
        if (! is_file($source)) {
            throw new RuntimeException('Berkas template tidak ditemukan pada penyimpanan.');
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'spj-template-individual-');
        if ($temporaryPath === false) {
            throw new RuntimeException('File sementara untuk download template tidak dapat dibuat.');
        }

        if (! copy($source, $temporaryPath)) {
            @unlink($temporaryPath);
            throw new RuntimeException('Template sementara tidak dapat disiapkan.');
        }

        try {
            $changed = $this->showOnlySelectedSheet(
                $temporaryPath,
                (string) $definition['sheet'],
            );

            if (! $changed) {
                @unlink($temporaryPath);

                return null;
            }

            return $temporaryPath;
        } catch (\Throwable $exception) {
            @unlink($temporaryPath);

            throw new RuntimeException(
                'Template individual tidak dapat disiapkan: '.$exception->getMessage(),
                0,
                $exception,
            );
        }
    }

    private function showOnlySelectedSheet(string $path, string $expectedSheet): bool
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Workbook XLSX tidak dapat dibuka.');
        }

        try {
            $xml = $zip->getFromName('xl/workbook.xml');
            if (! is_string($xml) || $xml === '') {
                throw new RuntimeException('Metadata workbook XLSX tidak ditemukan.');
            }

            $document = new DOMDocument;
            $document->preserveWhiteSpace = true;
            if (! $document->loadXML($xml, LIBXML_NONET)) {
                throw new RuntimeException('Metadata workbook XLSX tidak dapat dibaca.');
            }

            $namespace = $document->documentElement?->namespaceURI;
            if (! is_string($namespace) || $namespace === '') {
                throw new RuntimeException('Namespace workbook XLSX tidak dikenali.');
            }

            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('main', $namespace);
            $sheetNodes = $xpath->query('/main:workbook/main:sheets/main:sheet');
            if ($sheetNodes === false || $sheetNodes->length <= 1) {
                return false;
            }

            $selectedIndex = $this->selectedSheetIndex($sheetNodes, $expectedSheet);
            if ($selectedIndex === null) {
                throw new RuntimeException('Sheet canonical '.$expectedSheet.' tidak ditemukan pada workbook template.');
            }

            foreach ($sheetNodes as $index => $sheetNode) {
                if (! $sheetNode instanceof DOMElement) {
                    continue;
                }

                if ($index === $selectedIndex) {
                    $sheetNode->removeAttribute('state');
                } else {
                    $sheetNode->setAttribute('state', 'veryHidden');
                }
            }

            $viewNodes = $xpath->query('/main:workbook/main:bookViews/main:workbookView');
            if ($viewNodes !== false) {
                foreach ($viewNodes as $viewNode) {
                    if ($viewNode instanceof DOMElement) {
                        $viewNode->setAttribute('activeTab', (string) $selectedIndex);
                        $viewNode->setAttribute('firstSheet', (string) $selectedIndex);
                    }
                }
            }

            $updated = $document->saveXML();
            if (! is_string($updated) || $updated === '' || ! $zip->addFromString('xl/workbook.xml', $updated)) {
                throw new RuntimeException('Metadata workbook individual gagal disimpan.');
            }

            return true;
        } finally {
            $zip->close();
        }
    }

    private function selectedSheetIndex(\DOMNodeList $sheetNodes, string $expectedSheet): ?int
    {
        $fallbackCandidates = [];
        $technical = array_fill_keys(
            array_map('strtoupper', SpjDocumentTypeRegistry::technicalSheets()),
            true,
        );

        foreach ($sheetNodes as $index => $sheetNode) {
            if (! $sheetNode instanceof DOMElement) {
                continue;
            }

            $name = trim($sheetNode->getAttribute('name'));
            if (strcasecmp($name, $expectedSheet) === 0) {
                return $index;
            }

            if ($name !== '' && ! isset($technical[strtoupper($name)])) {
                $fallbackCandidates[] = $index;
            }
        }

        return count($fallbackCandidates) === 1 ? $fallbackCandidates[0] : null;
    }
}
