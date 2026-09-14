<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\FiscalYear;
use App\Models\SpjPackage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentStoragePathService
{
    public const SETTING_KEY = 'document_storage_path';

    public function configuredPath(): ?string
    {
        if (! Schema::hasTable('app_settings')) {
            return app()->environment('testing') ? storage_path('app/generated-documents') : null;
        }

        $path = trim((string) AppSetting::query()->where('key', self::SETTING_KEY)->value('value'));

        if ($path !== '') {
            return $path;
        }

        return app()->environment('testing') ? storage_path('app/generated-documents') : null;
    }

    public function validatePath(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return 'Path penyimpanan dokumen wajib diisi.';
        }
        if (! preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/]{1,2})/', $path)) {
            return 'Path harus berupa lokasi absolut, misalnya D:\\Dokumen-SPJ.';
        }
        if (! is_dir($path)) {
            return 'Folder path tidak ditemukan. Buat folder tersebut atau masukkan path yang benar.';
        }
        if (! is_writable($path)) {
            return 'Folder path tidak dapat ditulisi oleh aplikasi.';
        }

        return null;
    }

    public function savePath(string $path): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => trim($path)],
        );
    }

    public function persist(string $source, SpjPackage $package, string $fileName): string
    {
        $basePath = $this->configuredPath();
        if (app()->environment('testing') && $basePath !== null && ! is_dir($basePath)) {
            mkdir($basePath, 0775, true);
        }
        $error = $this->validatePath($basePath);
        if ($error !== null) {
            throw ValidationException::withMessages(['document_storage_path' => $error]);
        }

        $year = (string) (FiscalYear::query()->whereKey($package->transaction->fiscal_year_id)->value('year') ?: $package->transaction->fiscal_year_id);
        $documentNumber = $this->safeSegment((string) ($package->document_number ?: 'DRAFT-'.$package->id));
        $directory = rtrim((string) $basePath, '\\/').DIRECTORY_SEPARATOR.$year.DIRECTORY_SEPARATOR.$documentNumber;
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Folder dokumen tidak dapat dibuat: '.$directory);
        }

        $destination = $directory.DIRECTORY_SEPARATOR.$this->safeSegment($fileName);
        if (! copy($source, $destination)) {
            throw new \RuntimeException('Dokumen gagal disimpan ke path yang telah ditentukan.');
        }

        return $destination;
    }

    /**
     * Simpan salinan laporan agregat (rekap/honor) ke folder tahun berjalan.
     * Tidak terikat satu paket sehingga memakai subfolder LAPORAN.
     */
    public function persistReport(string $source, string $fileName, int $year): string
    {
        $basePath = $this->configuredPath();
        if (app()->environment('testing') && $basePath !== null && ! is_dir($basePath)) {
            mkdir($basePath, 0775, true);
        }
        $error = $this->validatePath($basePath);
        if ($error !== null) {
            throw ValidationException::withMessages(['document_storage_path' => $error]);
        }

        $directory = rtrim((string) $basePath, '\\/').DIRECTORY_SEPARATOR.$year.DIRECTORY_SEPARATOR.'LAPORAN';
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Folder dokumen tidak dapat dibuat: '.$directory);
        }

        $destination = $directory.DIRECTORY_SEPARATOR.$this->safeSegment($fileName);
        if (! copy($source, $destination)) {
            throw new \RuntimeException('Dokumen gagal disimpan ke path yang telah ditentukan.');
        }

        return $destination;
    }

    /**
     * Unduh file laporan: dari folder dokumen bila path terkonfigurasi,
     * fallback file sementara yang dihapus setelah dikirim.
     */
    public function downloadReportFile(string $source, string $fileName, int $year): BinaryFileResponse
    {
        if ($this->configuredPath() !== null) {
            $stored = $this->persistReport($source, $fileName, $year);
            @unlink($source);

            return response()->download($stored, $fileName);
        }

        return response()->download($source, $fileName)->deleteFileAfterSend(true);
    }

    /**
     * Arsipkan salinan PDF laporan ke folder dokumen; null bila path
     * belum dikonfigurasi (respon stream pemanggil tidak berubah).
     */
    public function archiveReportPdf(string $contents, string $fileName, int $year): ?string
    {
        if ($this->configuredPath() === null) {
            return null;
        }

        $temporary = tempnam(sys_get_temp_dir(), 'spjpdf');
        if ($temporary === false) {
            throw new \RuntimeException('File sementara PDF tidak dapat dibuat.');
        }
        file_put_contents($temporary, $contents);

        try {
            return $this->persistReport($temporary, $fileName, $year);
        } finally {
            @unlink($temporary);
        }
    }

    private function safeSegment(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9._-]+/', '-', trim($value)) ?: 'dokumen';
    }
}
