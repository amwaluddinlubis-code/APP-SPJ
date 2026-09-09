<?php

namespace App\Services;

use App\Models\School;
use App\Models\SchoolBackup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PDO;
use RuntimeException;
use Throwable;

final class SchoolBackupService
{
    public function __construct(private readonly SchoolDatabaseManager $databases) {}

    /** @param array<int,int|string> $protectedBackupIds */
    public function create(
        School $school,
        string $reason,
        ?int $userId,
        array $protectedBackupIds = [],
    ): SchoolBackup {
        $this->databases->activate($school);
        $source = $this->databasePath($school);
        DB::connection('school')->statement('PRAGMA wal_checkpoint(TRUNCATE)');
        $this->assertSqliteHealthy($source, 'Database sekolah');

        $folder = $this->backupFolder($school);
        File::ensureDirectoryExists($folder);
        $name = 'spj-'.$school->npsn.'-'.now()->format('Ymd-His').'-'.strtolower($reason).'.sqlite';
        $target = $folder.DIRECTORY_SEPARATOR.$name;
        $temporaryTarget = $target.'.tmp';

        try {
            $this->copyVerified($source, $temporaryTarget, 'Backup database');
            if (File::exists($target) && ! File::delete($target)) {
                throw new RuntimeException('Berkas backup lama dengan nama yang sama tidak dapat diganti.');
            }
            if (! File::move($temporaryTarget, $target)) {
                throw new RuntimeException('Berkas backup final tidak dapat dibuat.');
            }
        } finally {
            File::delete($temporaryTarget);
        }

        try {
            $backup = SchoolBackup::query()->create([
                'school_id' => $school->id,
                'file_path' => 'backups/'.$school->npsn.'/'.$name,
                'file_name' => $name,
                'file_size' => File::size($target),
                'reason' => $reason,
                'created_by' => $userId,
            ]);
        } catch (Throwable $exception) {
            File::delete($target);
            throw $exception;
        }

        $this->prune($school, array_merge($protectedBackupIds, [$backup->id]));

        return $backup;
    }

    public function restore(School $school, SchoolBackup $backup, ?int $userId): void
    {
        if ((int) $backup->school_id !== (int) $school->id) {
            throw new RuntimeException('Backup tidak berasal dari sekolah yang sedang aktif.');
        }

        $source = $this->absoluteBackupPath($backup);
        $target = $this->databasePath($school);
        $this->assertSqliteHealthy($source, 'Berkas backup');

        $rollback = $this->create(
            $school,
            'SEBELUM_PEMULIHAN',
            $userId,
            [$backup->id],
        );
        $rollbackSource = $this->absoluteBackupPath($rollback);
        $temporaryTarget = $target.'.restore.tmp';

        try {
            $this->copyVerified($source, $temporaryTarget, 'Berkas pemulihan');
            DB::purge('school');
            $this->deleteSidecars($target);
            $this->replaceDatabaseFile($temporaryTarget, $target);
            $this->deleteSidecars($target);

            $this->databases->activate($school);
            $integrity = $this->databases->integrityCheck($school);
            if (strtolower(trim($integrity)) !== 'ok') {
                throw new RuntimeException('Integrity check database hasil pemulihan tidak lulus: '.$integrity);
            }
        } catch (Throwable $exception) {
            $this->restoreRollback($school, $rollbackSource, $target);

            throw new RuntimeException('Pemulihan database sekolah gagal dan rollback otomatis dijalankan.', 0, $exception);
        } finally {
            File::delete($temporaryTarget);
        }
    }

    private function copyVerified(string $source, string $target, string $label): void
    {
        if (! File::exists($source) || File::size($source) === 0) {
            throw new RuntimeException($label.' sumber tidak ditemukan atau kosong.');
        }

        File::ensureDirectoryExists(dirname($target));
        File::delete($target);
        if (! File::copy($source, $target)) {
            throw new RuntimeException($label.' tidak dapat disalin.');
        }
        if (File::size($target) !== File::size($source)) {
            File::delete($target);
            throw new RuntimeException($label.' tidak konsisten setelah disalin.');
        }

        $this->assertSqliteHealthy($target, $label);
    }

    private function replaceDatabaseFile(string $source, string $target): void
    {
        if (File::exists($target) && ! File::delete($target)) {
            throw new RuntimeException('Database sekolah aktif tidak dapat diganti.');
        }
        if (! File::move($source, $target)) {
            throw new RuntimeException('Berkas hasil pemulihan tidak dapat dipasang sebagai database aktif.');
        }
    }

    private function restoreRollback(School $school, string $rollbackSource, string $target): void
    {
        DB::purge('school');
        $this->deleteSidecars($target);
        File::delete($target);

        if (File::exists($rollbackSource)) {
            File::copy($rollbackSource, $target);
        }

        try {
            $this->databases->activate($school);
        } catch (Throwable) {
        }
    }

    private function deleteSidecars(string $path): void
    {
        foreach ([$path.'-wal', $path.'-shm'] as $sidecar) {
            if (File::exists($sidecar) && ! File::delete($sidecar)) {
                throw new RuntimeException('File SQLite sidecar tidak dapat dibersihkan: '.$sidecar);
            }
        }
    }

    /** @param array<int,int|string> $protectedBackupIds */
    private function prune(School $school, array $protectedBackupIds): void
    {
        $retention = max(1, (int) config('spj.backup_retention', 30));
        $protected = array_map('strval', $protectedBackupIds);
        $expired = SchoolBackup::query()
            ->where('school_id', $school->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->slice($retention);

        foreach ($expired as $oldBackup) {
            if (in_array((string) $oldBackup->id, $protected, true)) {
                continue;
            }

            File::delete($this->absoluteBackupPath($oldBackup));
            $oldBackup->delete();
        }
    }

    private function assertSqliteHealthy(string $path, string $label): void
    {
        if (! File::exists($path) || File::size($path) === 0) {
            throw new RuntimeException($label.' tidak ditemukan atau kosong.');
        }

        try {
            $pdo = new PDO('sqlite:'.$path);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $result = $pdo->query('PRAGMA integrity_check')->fetchColumn();
            $pdo = null;
        } catch (Throwable $exception) {
            throw new RuntimeException($label.' bukan database SQLite yang dapat dibaca.', 0, $exception);
        }

        if (strtolower(trim((string) $result)) !== 'ok') {
            throw new RuntimeException($label.' gagal integrity check: '.(string) $result);
        }
    }

    private function databasePath(School $school): string
    {
        $school->load('databaseRecord');
        $path = $school->databaseRecord?->database_path;
        if (! is_string($path) || trim($path) === '' || ! File::exists($path)) {
            throw new RuntimeException('Database lokal sekolah tidak ditemukan.');
        }

        return $path;
    }

    private function backupFolder(School $school): string
    {
        return rtrim((string) config('spj.data_path'), '/\\')
            .DIRECTORY_SEPARATOR.'backups'
            .DIRECTORY_SEPARATOR.$school->npsn;
    }

    private function absoluteBackupPath(SchoolBackup $backup): string
    {
        return rtrim((string) config('spj.data_path'), '/\\')
            .DIRECTORY_SEPARATOR.$backup->file_path;
    }
}
