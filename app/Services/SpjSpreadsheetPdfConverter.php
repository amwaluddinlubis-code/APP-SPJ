<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class SpjSpreadsheetPdfConverter
{
    public function convert(Spreadsheet $spreadsheet): ?string
    {
        $directory = rtrim(sys_get_temp_dir(), '\\/').DIRECTORY_SEPARATOR.'spj-pdf-'.bin2hex(random_bytes(8));
        if (! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            return null;
        }

        $source = $directory.DIRECTORY_SEPARATOR.'source.xlsx';

        try {
            (new Xlsx($spreadsheet))->save($source);

            return $this->convertFile($source);
        } finally {
            if (is_file($source)) {
                @unlink($source);
            }
            @rmdir($directory);
        }
    }

    /** Convert any LibreOffice-readable office file to PDF. Null when LibreOffice is unavailable. */
    public function convertFile(string $sourcePath): ?string
    {
        $binary = $this->binary();
        if ($binary === null || ! is_file($sourcePath)) {
            return null;
        }

        $directory = rtrim(sys_get_temp_dir(), '\\/').DIRECTORY_SEPARATOR.'spj-pdf-'.bin2hex(random_bytes(8));
        if (! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            return null;
        }

        $baseName = pathinfo($sourcePath, PATHINFO_FILENAME);
        $output = $directory.DIRECTORY_SEPARATOR.$baseName.'.pdf';
        $profileDirectory = $directory.DIRECTORY_SEPARATOR.'profile';

        if (! mkdir($profileDirectory, 0775, true) && ! is_dir($profileDirectory)) {
            @rmdir($directory);

            return null;
        }

        $profileUrl = 'file:///'.str_replace(' ', '%20', str_replace('\\', '/', $profileDirectory));

        try {
            $process = new Process([
                $binary,
                '--headless',
                '-env:UserInstallation='.$profileUrl,
                '--convert-to',
                $this->exportFilter($sourcePath),
                '--outdir',
                $directory,
                $sourcePath,
            ]);
            $process->setTimeout(120);
            $process->run();

            if (! $process->isSuccessful() || ! is_file($output)) {
                return null;
            }

            $contents = file_get_contents($output);

            return $contents === false ? null : $contents;
        } finally {
            if (is_file($output)) {
                @unlink($output);
            }
            File::deleteDirectory($profileDirectory);
            @rmdir($directory);
        }
    }

    public function isAvailable(): bool
    {
        return $this->binary() !== null;
    }

    private function exportFilter(string $sourcePath): string
    {
        return match (strtolower((string) pathinfo($sourcePath, PATHINFO_EXTENSION))) {
            'xlsx', 'xls', 'ods', 'csv' => 'pdf:calc_pdf_Export',
            default => 'pdf:writer_pdf_Export',
        };
    }

    private function binary(): ?string
    {
        $configured = trim((string) env('SPJ_LIBREOFFICE_BINARY', ''));
        if ($configured !== '') {
            if (is_file($configured)) {
                return $configured;
            }
            // Terima path direktori instalasi (mis. C:\Program Files\LibreOffice\program)
            // dengan mencoba soffice.exe / soffice di dalamnya.
            if (is_dir($configured)) {
                foreach (['soffice.exe', 'soffice'] as $candidate) {
                    $full = rtrim($configured, '\\/').DIRECTORY_SEPARATOR.$candidate;
                    if (is_file($full)) {
                        return $full;
                    }
                }
            }

            return (new ExecutableFinder)->find($configured);
        }

        $binary = (new ExecutableFinder)->find('soffice')
            ?? (new ExecutableFinder)->find('libreoffice');

        if ($binary !== null) {
            return $binary;
        }

        foreach (array_unique(array_filter([
            getenv('ProgramW6432') ?: null,
            getenv('ProgramFiles') ?: null,
            getenv('ProgramFiles(x86)') ?: null,
        ])) as $programFiles) {
            $candidate = rtrim($programFiles, '\\/').DIRECTORY_SEPARATOR.'LibreOffice'.DIRECTORY_SEPARATOR.'program'.DIRECTORY_SEPARATOR.'soffice.exe';

            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
