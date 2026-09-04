<?php

namespace App\Services;

use App\Models\School;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SchoolDatabaseResetService
{
    public function __construct(
        private readonly SchoolDatabaseManager $databaseManager,
    ) {
    }

    public function reset(School $school): void
    {
        $record = $school->databaseRecord;
        $path = $record?->database_path;

        if ($path && Config::get('database.connections.school.database') === $path) {
            DB::purge('school');
        }

        if ($path) {
            foreach ([$path, $path.'-wal', $path.'-shm'] as $file) {
                if (File::exists($file) && ! File::delete($file)) {
                    throw new \RuntimeException('File database sekolah tidak dapat dihapus: '.$file);
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

    private function hasSqliteSequence(): bool
    {
        return DB::connection('school')
            ->table('sqlite_master')
            ->where('type', 'table')
            ->where('name', 'sqlite_sequence')
            ->exists();
    }
}
