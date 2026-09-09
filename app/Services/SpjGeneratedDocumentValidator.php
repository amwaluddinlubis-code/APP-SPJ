<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use ZipArchive;

final class SpjGeneratedDocumentValidator
{
    public function __construct(private readonly SpjUnresolvedPlaceholderGuard $placeholderGuard) {}

    public function assertBinaryResponse(
        BinaryFileResponse $response,
        string $format,
        string $documentLabel,
        ?string $documentType = null,
    ): void {
        $this->assertFile(
            $response->getFile()->getPathname(),
            $format,
            $documentLabel,
            $documentType,
        );
    }

    public function assertFile(
        string $path,
        string $format,
        string $documentLabel,
        ?string $documentType = null,
    ): void {
        if (! is_file($path) || ! is_readable($path) || filesize($path) === 0) {
            throw new RuntimeException($documentLabel.' gagal dibuat karena berkas output kosong atau tidak dapat dibaca.');
        }

        $extension = strtolower(ltrim($format, '.'));
        if (! in_array($extension, ['docx', 'xlsx'], true)) {
            throw new RuntimeException($documentLabel.' menggunakan format output yang belum didukung validator.');
        }

        $this->assertOfficePackage($path, $extension, $documentLabel);

        if ($documentType !== null && trim($documentType) !== '') {
            $this->placeholderGuard->assertResolved($documentType, $path, $extension);
        }
    }

    public function assertPdfResponse(Response $response, string $documentLabel): void
    {
        $contents = $response->getContent();
        if (! is_string($contents)) {
            throw new RuntimeException($documentLabel.' gagal dibuat karena output PDF tidak dapat dibaca.');
        }

        $this->assertPdfContents($contents, $documentLabel);
    }

    public function assertPdfContents(string $contents, string $documentLabel): void
    {
        if ($contents === '' || ! str_starts_with($contents, '%PDF-')) {
            throw new RuntimeException($documentLabel.' gagal dibuat karena signature PDF tidak valid.');
        }

        $tail = substr($contents, -4096);
        if (! str_contains($tail, '%%EOF')) {
            throw new RuntimeException($documentLabel.' gagal dibuat karena penanda akhir PDF tidak ditemukan.');
        }
    }

    private function assertOfficePackage(string $path, string $format, string $documentLabel): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException($documentLabel.' gagal dibuat karena paket Office tidak dapat dibuka.');
        }

        $requiredEntries = $format === 'docx'
            ? ['[Content_Types].xml', '_rels/.rels', 'word/document.xml']
            : ['[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml'];

        try {
            foreach ($requiredEntries as $entry) {
                $contents = $zip->getFromName($entry);
                if (! is_string($contents) || trim($contents) === '') {
                    throw new RuntimeException(
                        $documentLabel.' gagal dibuat karena struktur '.$format.' tidak lengkap: '.$entry.'.'
                    );
                }
            }
        } finally {
            $zip->close();
        }

        if ($format !== 'xlsx') {
            return;
        }

        try {
            $spreadsheet = IOFactory::load($path);
            $spreadsheet->disconnectWorksheets();
        } catch (Throwable $exception) {
            throw new RuntimeException(
                $documentLabel.' gagal dibuat karena workbook XLSX tidak dapat dibuka kembali.',
                previous: $exception,
            );
        }
    }
}
