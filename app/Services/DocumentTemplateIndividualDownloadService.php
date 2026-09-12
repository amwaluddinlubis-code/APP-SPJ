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
            $changed = $this->keepOnlySelectedSheet(
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

    private function keepOnlySelectedSheet(string $path, string $expectedSheet): bool
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Workbook XLSX tidak dapat dibuka.');
        }

        try {
            $workbookXml = $zip->getFromName('xl/workbook.xml');
            if (! is_string($workbookXml) || $workbookXml === '') {
                throw new RuntimeException('Metadata workbook XLSX tidak ditemukan.');
            }

            $workbook = new DOMDocument;
            $workbook->preserveWhiteSpace = true;
            if (! $workbook->loadXML($workbookXml, LIBXML_NONET)) {
                throw new RuntimeException('Metadata workbook XLSX tidak dapat dibaca.');
            }

            $workbookNamespace = $workbook->documentElement?->namespaceURI;
            if (! is_string($workbookNamespace) || $workbookNamespace === '') {
                throw new RuntimeException('Namespace workbook XLSX tidak dikenali.');
            }

            $workbookXPath = new DOMXPath($workbook);
            $workbookXPath->registerNamespace('main', $workbookNamespace);
            $sheetNodes = $workbookXPath->query('/main:workbook/main:sheets/main:sheet');
            if ($sheetNodes === false || $sheetNodes->length <= 1) {
                return false;
            }

            $selectedIndex = $this->selectedSheetIndex($sheetNodes, $expectedSheet);
            if ($selectedIndex === null) {
                throw new RuntimeException('Sheet canonical '.$expectedSheet.' tidak ditemukan pada workbook template.');
            }

            $selectedSheet = $sheetNodes->item($selectedIndex);
            if (! $selectedSheet instanceof DOMElement) {
                throw new RuntimeException('Sheet canonical '.$expectedSheet.' tidak dapat dibaca.');
            }

            $relationshipNamespace = $workbook->documentElement?->lookupNamespaceURI('r');
            $selectedRelationshipId = $this->sheetRelationshipId($selectedSheet, $relationshipNamespace);
            if ($selectedRelationshipId === '') {
                throw new RuntimeException('Relasi sheet canonical '.$expectedSheet.' tidak ditemukan.');
            }

            $relationshipsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
            if (! is_string($relationshipsXml) || $relationshipsXml === '') {
                throw new RuntimeException('Relasi workbook XLSX tidak ditemukan.');
            }

            $relationships = new DOMDocument;
            $relationships->preserveWhiteSpace = true;
            if (! $relationships->loadXML($relationshipsXml, LIBXML_NONET)) {
                throw new RuntimeException('Relasi workbook XLSX tidak dapat dibaca.');
            }

            $relationshipsNamespace = $relationships->documentElement?->namespaceURI;
            if (! is_string($relationshipsNamespace) || $relationshipsNamespace === '') {
                throw new RuntimeException('Namespace relasi workbook XLSX tidak dikenali.');
            }

            $relationshipsXPath = new DOMXPath($relationships);
            $relationshipsXPath->registerNamespace('rels', $relationshipsNamespace);
            $relationshipNodes = $relationshipsXPath->query('/rels:Relationships/rels:Relationship');
            if ($relationshipNodes === false) {
                throw new RuntimeException('Relasi workbook XLSX tidak dapat dipetakan.');
            }

            $relationshipsById = [];
            foreach ($relationshipNodes as $relationshipNode) {
                if ($relationshipNode instanceof DOMElement) {
                    $relationshipsById[$relationshipNode->getAttribute('Id')] = $relationshipNode;
                }
            }

            $selectedRelationship = $relationshipsById[$selectedRelationshipId] ?? null;
            if (! $selectedRelationship instanceof DOMElement) {
                throw new RuntimeException('Target sheet canonical '.$expectedSheet.' tidak ditemukan.');
            }

            $selectedTarget = $this->relationshipPartPath('xl/workbook.xml', $selectedRelationship);
            if ($selectedTarget === null || $zip->locateName($selectedTarget) === false) {
                throw new RuntimeException('Part worksheet canonical '.$expectedSheet.' tidak ditemukan.');
            }

            $sheets = [];
            foreach ($sheetNodes as $sheetNode) {
                if ($sheetNode instanceof DOMElement) {
                    $sheets[] = $sheetNode;
                }
            }

            $partsToDelete = [];
            foreach ($sheets as $index => $sheetNode) {
                if ($index === $selectedIndex) {
                    $sheetNode->removeAttribute('state');

                    continue;
                }

                $relationshipId = $this->sheetRelationshipId($sheetNode, $relationshipNamespace);
                if ($relationshipId === '') {
                    throw new RuntimeException('Relasi salah satu sheet workbook tidak ditemukan.');
                }

                $relationship = $relationshipsById[$relationshipId] ?? null;
                if (! $relationship instanceof DOMElement) {
                    throw new RuntimeException('Target salah satu sheet workbook tidak ditemukan.');
                }

                $partPath = $this->relationshipPartPath('xl/workbook.xml', $relationship);
                if ($partPath !== null) {
                    $partsToDelete[] = $partPath;
                }

                $sheetNode->parentNode?->removeChild($sheetNode);
                $relationship->parentNode?->removeChild($relationship);
            }

            $definedNames = $workbookXPath->query('/main:workbook/main:definedNames/main:definedName');
            if ($definedNames !== false) {
                $definedNameNodes = [];
                foreach ($definedNames as $definedName) {
                    if ($definedName instanceof DOMElement) {
                        $definedNameNodes[] = $definedName;
                    }
                }

                foreach ($definedNameNodes as $definedName) {
                    if (! $definedName->hasAttribute('localSheetId')) {
                        continue;
                    }

                    if ((int) $definedName->getAttribute('localSheetId') !== $selectedIndex) {
                        $definedName->parentNode?->removeChild($definedName);

                        continue;
                    }

                    $definedName->setAttribute('localSheetId', '0');
                }
            }

            $viewNodes = $workbookXPath->query('/main:workbook/main:bookViews/main:workbookView');
            if ($viewNodes !== false) {
                foreach ($viewNodes as $viewNode) {
                    if ($viewNode instanceof DOMElement) {
                        $viewNode->setAttribute('activeTab', '0');
                        $viewNode->setAttribute('firstSheet', '0');
                    }
                }
            }

            $relationshipNodesToInspect = [];
            foreach ($relationshipNodes as $relationshipNode) {
                if ($relationshipNode instanceof DOMElement) {
                    $relationshipNodesToInspect[] = $relationshipNode;
                }
            }

            foreach ($relationshipNodesToInspect as $relationshipNode) {
                if ($relationshipNode->parentNode === null) {
                    continue;
                }

                $type = $relationshipNode->getAttribute('Type');
                if (! str_ends_with($type, '/calcChain')) {
                    continue;
                }

                $partPath = $this->relationshipPartPath('xl/workbook.xml', $relationshipNode);
                if ($partPath !== null) {
                    $partsToDelete[] = $partPath;
                }

                $relationshipNode->parentNode?->removeChild($relationshipNode);
            }

            $calculationProperties = $workbookXPath->query('/main:workbook/main:calcPr');
            if ($calculationProperties !== false) {
                foreach ($calculationProperties as $calculationProperty) {
                    if ($calculationProperty instanceof DOMElement) {
                        $calculationProperty->setAttribute('fullCalcOnLoad', '1');
                        $calculationProperty->setAttribute('forceFullCalc', '1');
                    }
                }
            }

            $updatedWorkbook = $workbook->saveXML();
            $updatedRelationships = $relationships->saveXML();
            if (! is_string($updatedWorkbook) || $updatedWorkbook === ''
                || ! is_string($updatedRelationships) || $updatedRelationships === '') {
                throw new RuntimeException('Metadata workbook individual gagal disusun.');
            }

            if (! $zip->addFromString('xl/workbook.xml', $updatedWorkbook)
                || ! $zip->addFromString('xl/_rels/workbook.xml.rels', $updatedRelationships)) {
                throw new RuntimeException('Metadata workbook individual gagal disimpan.');
            }

            $partsToDelete = array_values(array_unique($partsToDelete));
            foreach ($partsToDelete as $partPath) {
                $this->deletePart($zip, $partPath);
            }

            $this->removeContentTypeOverrides($zip, $partsToDelete);

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

    private function sheetRelationshipId(DOMElement $sheet, ?string $relationshipNamespace): string
    {
        if (is_string($relationshipNamespace) && $relationshipNamespace !== '') {
            $relationshipId = trim($sheet->getAttributeNS($relationshipNamespace, 'id'));
            if ($relationshipId !== '') {
                return $relationshipId;
            }
        }

        return trim($sheet->getAttribute('r:id'));
    }

    private function relationshipPartPath(string $sourcePart, DOMElement $relationship): ?string
    {
        if (strcasecmp($relationship->getAttribute('TargetMode'), 'External') === 0) {
            return null;
        }

        $target = trim(str_replace('\\', '/', $relationship->getAttribute('Target')));
        if ($target === '') {
            return null;
        }

        if (str_starts_with($target, '/')) {
            return ltrim($target, '/');
        }

        $segments = explode('/', dirname($sourcePart).'/'.$target);
        $normalized = [];
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($normalized);

                continue;
            }

            $normalized[] = $segment;
        }

        return implode('/', $normalized);
    }

    private function deletePart(ZipArchive $zip, string $partPath): void
    {
        if ($zip->locateName($partPath) !== false) {
            $zip->deleteName($partPath);
        }

        $relationshipsPath = dirname($partPath).'/_rels/'.basename($partPath).'.rels';
        if ($zip->locateName($relationshipsPath) !== false) {
            $zip->deleteName($relationshipsPath);
        }
    }

    /** @param list<string> $partPaths */
    private function removeContentTypeOverrides(ZipArchive $zip, array $partPaths): void
    {
        if ($partPaths === []) {
            return;
        }

        $contentTypesXml = $zip->getFromName('[Content_Types].xml');
        if (! is_string($contentTypesXml) || $contentTypesXml === '') {
            return;
        }

        $contentTypes = new DOMDocument;
        $contentTypes->preserveWhiteSpace = true;
        if (! $contentTypes->loadXML($contentTypesXml, LIBXML_NONET)) {
            return;
        }

        $contentTypesNamespace = $contentTypes->documentElement?->namespaceURI;
        if (! is_string($contentTypesNamespace) || $contentTypesNamespace === '') {
            return;
        }

        $contentTypesXPath = new DOMXPath($contentTypes);
        $contentTypesXPath->registerNamespace('types', $contentTypesNamespace);
        $overrideNodes = $contentTypesXPath->query('/types:Types/types:Override');
        if ($overrideNodes === false) {
            return;
        }

        $parts = array_fill_keys(array_map(
            fn (string $partPath): string => '/'.ltrim($partPath, '/'),
            $partPaths,
        ), true);
        $overrides = [];
        foreach ($overrideNodes as $overrideNode) {
            if ($overrideNode instanceof DOMElement) {
                $overrides[] = $overrideNode;
            }
        }

        foreach ($overrides as $overrideNode) {
            if (isset($parts[$overrideNode->getAttribute('PartName')])) {
                $overrideNode->parentNode?->removeChild($overrideNode);
            }
        }

        $updatedContentTypes = $contentTypes->saveXML();
        if (is_string($updatedContentTypes) && $updatedContentTypes !== '') {
            $zip->addFromString('[Content_Types].xml', $updatedContentTypes);
        }
    }
}
