<?php

namespace App\Services;

use App\Models\ArkasSource;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Adapter for the cross-platform ARKASBridge executable.
 * It operates as a schema-aware boundary to decrypt and query ARKAS SQLite databases
 * safely across Windows, Linux, and macOS environments.
 */
class ArkasBridgeClient
{
    /** Execute one read-only ARKASBridge command for a registered school source. */
    public function execute(ArkasSource $source, string $command, ?int $year = null, ?string $table = null, ?int $fundSourceId = null): string
    {
        $bridgePath = $this->resolveBridgeExecutable($source);

        if (! File::isFile($bridgePath)) {
            throw new \RuntimeException("Executable ARKASBridge tidak ditemukan di: {$bridgePath}");
        }

        if (! File::isFile($source->database_path)) {
            throw new \RuntimeException("Database ARKAS tidak ditemukan di: {$source->database_path}");
        }

        // Pastikan permission execute aktif pada lingkungan Linux / macOS
        if (PHP_OS_FAMILY !== 'Windows' && ! is_executable($bridgePath)) {
            @chmod($bridgePath, 0755);
        }

        // ARKASBridge adalah executable .NET single-file. Pada proses web, .NET
        // kadang memilih direktori sistem sebagai cache ekstraksi dan gagal karena
        // keterbatasan hak tulis. Gunakan direktori aplikasi yang terisolasi.
        $extractDirectory = storage_path('app/arkas-bridge-runtime');
        File::ensureDirectoryExists($extractDirectory);
        $temporaryDirectory = $extractDirectory.DIRECTORY_SEPARATOR.'tmp';
        File::ensureDirectoryExists($temporaryDirectory);

        $arguments = [$bridgePath, '--db', $source->database_path, '--command', $command];
        if ($year !== null) {
            $arguments[] = '--year';
            $arguments[] = (string) $year;
        }
        if ($table !== null) {
            $arguments[] = '--table';
            $arguments[] = $table;
        }
        if ($fundSourceId !== null) {
            $arguments[] = '--fund-source';
            $arguments[] = (string) $fundSourceId;
        }

        $process = new Process($arguments, base_path(), [
            'ARKAS_BRIDGE_PASSWORD' => $source->database_password,
            'DOTNET_BUNDLE_EXTRACT_BASE_DIR' => $extractDirectory,
            'TEMP' => $temporaryDirectory,
            'TMP' => $temporaryDirectory,
        ], null, 300);

        $process->run();

        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput()) ?: 'ARKASBridge gagal dijalankan.';
            if (str_contains(strtolower($error), 'command tidak dikenal: fund-sources')) {
                throw new \RuntimeException('ARKASBridge yang digunakan masih versi lama dan belum mendukung fund-sources. Build ulang bridge terbaru dari bridge/src/ARKASBridge lalu arahkan kembali ke file hasil build.');
            }

            throw new \RuntimeException($error);
        }

        return $process->getOutput();
    }

    /**
     * Resolves the appropriate bridge binary path for current operating system.
     */
    public function resolveBridgeExecutable(?ArkasSource $source = null): string
    {
        // 1. Jika pada sumber database terdefinisi path khusus dan valid, gunakan itu
        if ($source && filled($source->bridge_path) && File::isFile($source->bridge_path)) {
            return $source->bridge_path;
        }

        // 2. Deteksi binary bawaan sesuai OS
        $isWindows = PHP_OS_FAMILY === 'Windows';
        $bundledPath = $isWindows
            ? base_path('bridge/bin/win-x64/ARKASBridge.exe')
            : base_path('bridge/bin/linux-x64/ARKASBridge');

        if (File::isFile($bundledPath)) {
            return $bundledPath;
        }

        // 3. Fallback ke legacy build path
        $legacyPath = storage_path('app/arkas-bridge-build/ARKASBridge.exe');
        if (File::isFile($legacyPath)) {
            return $legacyPath;
        }

        return $bundledPath;
    }

    public function sync(): array
    {
        $command = config('spj.arkas_bridge_command');

        if (blank($command)) {
            return ['ok' => false, 'message' => 'ARKAS Bridge belum dikonfigurasi. Isi SPJ_ARKAS_BRIDGE_COMMAND pada file .env.'];
        }

        try {
            $process = Process::fromShellCommandline($command, base_path(), timeout: 300);
            $process->run();

            if (! $process->isSuccessful()) {
                Log::warning('ARKAS Bridge gagal', ['error' => $process->getErrorOutput()]);

                return ['ok' => false, 'message' => 'Sinkronisasi ARKAS gagal. Periksa log aplikasi dan konfigurasi Bridge.'];
            }

            return ['ok' => true, 'message' => 'ARKAS Bridge selesai dijalankan. Tahap impor data akan menampilkan hasilnya di sini.'];
        } catch (\Throwable $exception) {
            Log::error('ARKAS Bridge tidak dapat dijalankan', ['exception' => $exception]);

            return ['ok' => false, 'message' => 'ARKAS Bridge tidak dapat dijalankan: '.$exception->getMessage()];
        }
    }
}
