<?php

namespace App\Services;

use App\Models\School;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class SchoolDatabaseResetService
{
    public function __construct(
        private readonly SchoolDatabaseManager $databaseManager,
    ) {}

    public function reset(School $school): void
    {
        $record = $school->databaseRecord;
        $path = $record?->database_path;

        if (is_string($path) && trim($path) !== '') {
            $this->assertNotPrimaryDatabase($path);
        }

        if ($path && Config::get('database.connections.school.database') === $path) {
            DB::purge('school');
        }

        if ($path) {
            foreach ([$path, $path.'-wal', $path.'-shm'] as $file) {
                if (File::exists($file) && ! File::delete($file)) {
                    throw new RuntimeException('File database sekolah tidak dapat dihapus: '.$file);
                }
            }
        }

        $school->unsetRelation('databaseRecord');
        $this->databaseManager->provision($school);

        DB::connection('school')->statement('PRAGMA foreign_keys=ON');

        if ($this->hasSqliteSequence()) {
            DB::connection('school')->statement('DELETE FROM sqlite_sequence');
        }
    }

    private function assertNotPrimaryDatabase(string $tenantPath): void
    {
        $defaultConnection = (string) Config::get('database.default');
        $primaryPath = Config::get('database.connections.'.$defaultConnection.'.database');

        if (! is_string($primaryPath) || trim($primaryPath) === '' || $primaryPath === ':memory:') {
            return;
        }

        if ($this->normalizePath($tenantPath) === $this->normalizePath($primaryPath)) {
            throw new RuntimeException('Reset dibatalkan karena path database sekolah menunjuk ke database utama aplikasi.');
        }
    }

    private function normalizePath(string $path): string
    {
        if (! preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~', $path)) {
            $path = base_path($path);
        }

        $resolved = realpath($path);
        $normalized = str_replace('\\', '/', $resolved !== false ? $resolved : $path);

        return PHP_OS_FAMILY === 'Windows' ? strtolower($normalized) : $normalized;
    }

    private function hasSqliteSequence(): bool
    {
        return DB::connection('school')
            ->table('sqlite_master')
            ->where('type', 'table')
            ->where('name', 'sqlite_sequence')
            ->exists();
    }
}
