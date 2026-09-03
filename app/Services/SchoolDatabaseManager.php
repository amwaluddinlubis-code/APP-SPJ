<?php

namespace App\Services;

use App\Models\School;
use App\Models\SchoolDatabase;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SchoolDatabaseManager
{
    /**
     * Provision database file + migrate untuk sekolah.
     */
    public function provision(School $school): SchoolDatabase
    {
        $folder = storage_path('app/school-databases/'.preg_replace('/[^A-Za-z0-9_-]/', '_', $school->npsn));
        File::ensureDirectoryExists($folder);
        $path = $folder.'/spj.sqlite';
        $createdFile = false;
        if (! File::exists($path) || File::size($path) < 100) {
            // Buat file SQLite valid, bukan 0 byte
            $record = null;
            try {
                $pdo = new \PDO('sqlite:'.$path);
                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $pdo->exec('VACUUM;');
                $createdFile = true;
            } catch (\Throwable $exception) {
                throw new \RuntimeException('Database sekolah tidak dapat dibuat.', 0, $exception);
            }
        }
        try {
            $record = SchoolDatabase::updateOrCreate(
                ['school_id' => $school->id],
                ['database_path' => $path, 'status' => 'READY']
            );
            $this->activate($school);
            Artisan::call('migrate', [
                '--database' => 'school',
                '--path' => 'database/migrations/school',
                '--force' => true,
            ]);
            $record->forceFill(['last_migrated_at' => now()])->save();
        } catch (\Throwable $exception) {
            Log::error('School database provisioning failed.', ['school_id' => $school->id, 'exception' => $exception]);
            if ($createdFile && File::exists($path)) {
                File::delete($path);
            }
            $record?->delete();
            throw new \RuntimeException('Database sekolah gagal diprovision.', 0, $exception);
        }

        return $record;
    }

    /**
     * Aktifkan koneksi 'school' ke database sekolah tertentu.
     */
    public function activate(School|int $school): void
    {
        $school = $school instanceof School ? $school : School::findOrFail($school);
        $record = $school->databaseRecord ?: $this->provision($school);
        if (! File::exists($record->database_path)) {
            throw new \RuntimeException('Database lokal sekolah tidak ditemukan: '.$record->database_path);
        }
        Config::set('database.connections.school.database', $record->database_path);
        DB::purge('school');
        DB::reconnect('school');
        // ensure foreign keys
        DB::connection('school')->statement('PRAGMA foreign_keys=ON');
    }

    /**
     * Terapkan migration sekolah yang tertinggal sebelum database dipakai aplikasi.
     */
    public function ensureMigrated(School $school): void
    {
        $this->activate($school);

        if (Schema::connection('school')->hasTable('transaction_items')
            && Schema::connection('school')->hasColumn('transaction_items', 'item_description')
            && Schema::connection('school')->hasTable('spj_participants')
            && Schema::connection('school')->hasColumn('spj_participants', 'transaction_item_id')
            && Schema::connection('school')->hasColumn('spj_travels', 'transaction_id')
            && Schema::connection('school')->hasColumn('spj_travels', 'assignment_letter_number')
            && Schema::connection('school')->hasColumn('spj_travels', 'assignment_letter_date')
            && Schema::connection('school')->hasTable('spj_work_orders')
            && Schema::connection('school')->hasColumn('spj_work_orders', 'maintenance_id')
            && Schema::connection('school')->hasTable('spj_honors')
            && Schema::connection('school')->hasColumn('transactions', 'vendor_name')
            && Schema::connection('school')->hasColumn('transactions', 'ppn_rate')
            && $this->schoolMigrationsAreCurrent()) {
            return;
        }

        Artisan::call('migrate', [
            '--database' => 'school',
            '--path' => 'database/migrations/school',
            '--force' => true,
        ]);

        if (! Schema::connection('school')->hasColumn('transaction_items', 'item_description')) {
            Schema::connection('school')->table('transaction_items', function (Blueprint $table): void {
                $table->text('item_description')->nullable();
            });
        }

        $school->databaseRecord?->forceFill([
            'last_migrated_at' => now(),
            'status' => 'READY',
        ])->save();
    }

    private function schoolMigrationsAreCurrent(): bool
    {
        if (! Schema::connection('school')->hasTable('migrations')) {
            return false;
        }

        $availableMigrations = collect(File::files(database_path('migrations/school')))
            ->map(fn (\SplFileInfo $file): string => $file->getBasename('.php'));
        $completedMigrations = DB::connection('school')->table('migrations')->pluck('migration');

        return $availableMigrations->diff($completedMigrations)->isEmpty();
    }

    /** Pastikan dummy memiliki schema tenant terbaru tanpa data. */
    public function ensureDummyDatabase(): string
    {
        $path = storage_path('app/school-databases/_unselected.sqlite');
        File::ensureDirectoryExists(dirname($path));

        if ($this->dummySchemaIsCurrent($path)) {
            return $path;
        }

        if (Config::get('database.connections.school.database') === $path) {
            DB::purge('school');
        }

        foreach ([$path, $path.'-wal', $path.'-shm'] as $file) {
            if (File::exists($file) && ! File::delete($file)) {
                throw new \RuntimeException('Database dummy lama sedang digunakan dan tidak dapat diperbarui.');
            }
        }

        try {
            $pdo = new \PDO('sqlite:'.$path);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdo = null;

            $connection = Config::get('database.connections.school');
            $connection['database'] = $path;
            $connection['journal_mode'] = null;
            Config::set('database.connections.school_dummy', $connection);
            DB::purge('school_dummy');
            Artisan::call('migrate', [
                '--database' => 'school_dummy',
                '--path' => 'database/migrations/school',
                '--force' => true,
                '--no-interaction' => true,
            ]);
            DB::purge('school_dummy');
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Database dummy tidak dapat dibangun dari schema tenant.', 0, $exception);
        }

        return $path;
    }

    private function dummySchemaIsCurrent(string $path): bool
    {
        if (! File::exists($path) || File::size($path) < 100) {
            return false;
        }

        try {
            $pdo = new \PDO('sqlite:'.$path);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $transactionColumns = $pdo->query("PRAGMA table_info('transactions')")->fetchAll(\PDO::FETCH_COLUMN, 1);
            $travelColumns = $pdo->query("PRAGMA table_info('spj_travels')")->fetchAll(\PDO::FETCH_COLUMN, 1);
            $documentColumns = $pdo->query("PRAGMA table_info('spj_documents')")->fetchAll(\PDO::FETCH_COLUMN, 1);
            $workflowTables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(\PDO::FETCH_COLUMN);

            return in_array('source_key', $transactionColumns, true)
                && in_array('requires_reconciliation', $transactionColumns, true)
                && in_array('transaction_id', $travelColumns, true)
                && in_array('assignment_letter_number', $travelColumns, true)
                && in_array('assignment_letter_date', $travelColumns, true)
                && ! in_array('transaction_item_id', $travelColumns, true)
                && in_array('document_number', $documentColumns, true)
                && in_array('snapshot', $documentColumns, true)
                && in_array('replaces_document_id', $documentColumns, true)
                && in_array('fiscal_period_closures', $workflowTables, true)
                && in_array('transaction_payments', $workflowTables, true)
                && in_array('goods_receipts', $workflowTables, true);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Nonaktifkan koneksi aktif (kembali ke dummy).
     */
    public function deactivate(): void
    {
        $path = $this->ensureDummyDatabase();
        Config::set('database.connections.school.database', $path);
        DB::purge('school');
        try {
            DB::connection('school')->select('SELECT 1');
        } catch (\Throwable) {
        }
    }

    /**
     * Status lengkap untuk 1 sekolah - dipakai di dashboard maintenance.
     */
    public function status(School $school): array
    {
        $record = $school->databaseRecord;
        $path = $record?->database_path;
        $exists = $path && File::exists($path);
        $size = $exists ? File::size($path) : 0;
        $walSize = 0;
        $shmSize = 0;
        if ($exists) {
            $wal = $path.'-wal';
            $shm = $path.'-shm';
            if (File::exists($wal)) {
                $walSize = File::size($wal);
            }
            if (File::exists($shm)) {
                $shmSize = File::size($shm);
            }
        }
        $isActive = (int) session('active_school_id') === $school->id;
        $isWritable = $exists && is_writable($path);
        $connectionOk = false;
        $connectionError = null;
        $tableCounts = [];
        $integrity = null;

        if ($exists) {
            try {
                $this->activate($school);
                DB::connection('school')->select('SELECT 1');
                $connectionOk = true;
                // hitung tabel inti
                foreach (['fiscal_years', 'transactions', 'transaction_items', 'spj_packages', 'spj_workers', 'document_templates', 'school_profiles', 'fiscal_period_closures', 'transaction_payments', 'goods_receipts'] as $tbl) {
                    try {
                        $tableCounts[$tbl] = DB::connection('school')->table($tbl)->count();
                    } catch (\Throwable) {
                        $tableCounts[$tbl] = null;
                    }
                }
                $integrity = $this->integrityCheck($school);
            } catch (\Throwable $e) {
                $connectionError = $e->getMessage();
                $connectionOk = false;
            }
            // kembalikan ke aktif semula jika beda
            if (! $isActive && session('active_school_id')) {
                try {
                    $active = School::find(session('active_school_id'));
                    if ($active) {
                        $this->activate($active);
                    }
                } catch (\Throwable) {
                }
            }
        }

        return [
            'school' => $school,
            'record' => $record,
            'path' => $path,
            'exists' => $exists,
            'size' => $size,
            'walSize' => $walSize,
            'shmSize' => $shmSize,
            'totalSize' => $size + $walSize + $shmSize,
            'isActive' => $isActive,
            'isWritable' => $isWritable,
            'connectionOk' => $connectionOk,
            'connectionError' => $connectionError,
            'tableCounts' => $tableCounts,
            'integrity' => $integrity,
            'lastMigrated' => $record?->last_migrated_at,
            'status' => $record?->status,
        ];
    }

    /**
     * Health check ringkas: ok / warning / error.
     */
    public function health(School $school): array
    {
        $s = $this->status($school);
        $issues = [];
        if (! $s['exists']) {
            $issues[] = 'File database tidak ada';
        }
        if (! $s['isWritable']) {
            $issues[] = 'File tidak writable';
        }
        if (! $s['connectionOk']) {
            $issues[] = 'Koneksi gagal: '.($s['connectionError'] ?? 'unknown');
        }
        if ($s['integrity'] !== null && strtolower(trim($s['integrity'])) !== 'ok') {
            $issues[] = 'Integrity check gagal: '.$s['integrity'];
        }
        $level = empty($issues) ? 'ok' : (! $s['exists'] || ! $s['connectionOk'] ? 'error' : 'warning');

        return ['level' => $level, 'issues' => $issues, 'status' => $s];
    }

    /**
     * List semua sekolah dengan status.
     */
    public function listAll(): Collection
    {
        return School::with('databaseRecord')->orderBy('name')->get()->map(fn ($sch) => $this->status($sch));
    }

    /**
     * Info koneksi aktif saat ini.
     */
    public function activeInfo(): array
    {
        $schoolId = session('active_school_id');
        $yearId = session('active_fiscal_year_id');
        $school = $schoolId ? School::with('databaseRecord')->find($schoolId) : null;
        $db = Config::get('database.connections.school.database');

        // Jika belum pilih sekolah, jangan anggap error - ini state normal
        if (! $school) {
            $this->ensureDummyDatabase();
            // Set ke dummy jika masih null/berbeda
            $dummy = storage_path('app/school-databases/_unselected.sqlite');
            if ($db !== $dummy) {
                Config::set('database.connections.school.database', $dummy);
                DB::purge('school');
                $db = $dummy;
            }
            $connected = false;
            try {
                DB::connection('school')->select('SELECT 1');
                $connected = true;
            } catch (\Throwable $exception) {
                Log::warning('Dummy school database connection failed.', ['exception' => $exception]);
                $error = 'Database dummy tidak dapat diakses.';
            }

            return [
                'school' => null,
                'schoolId' => null,
                'yearId' => $yearId,
                'database' => $db,
                'connected' => $connected,
                'error' => $error ?? null,
                'isDummy' => true,
                'dummy' => true,
            ];
        }

        // Ada sekolah aktif - pastikan koneksinya valid
        $connected = false;
        $error = null;
        try {
            $this->activate($school);
            DB::connection('school')->select('SELECT 1');
            $connected = true;
        } catch (\Throwable $e) {
            Log::error('Active school database connection failed.', [
                'school_id' => $school->id,
                'exception' => $e,
            ]);
            $error = File::exists((string) ($school->databaseRecord?->database_path))
                ? 'Database sekolah tidak dapat diakses.'
                : 'File database tidak ditemukan. Klik Provision Ulang pada sekolah ini.';
        }

        return [
            'school' => $school,
            'schoolId' => $schoolId,
            'yearId' => $yearId,
            'database' => $db,
            'connected' => $connected,
            'error' => $error,
            'isDummy' => false,
        ];
    }

    public function migrate(School $school): string
    {
        $this->activate($school);
        Artisan::call('migrate', ['--database' => 'school', '--path' => 'database/migrations/school', '--force' => true]);
        $out = Artisan::output();
        $school->databaseRecord?->forceFill(['last_migrated_at' => now(), 'status' => 'READY'])->save();

        return $out;
    }

    public function checkpoint(School $school): void
    {
        $this->activate($school);
        DB::connection('school')->statement('PRAGMA wal_checkpoint(FULL)');
    }

    public function vacuum(School $school): void
    {
        $this->activate($school);
        DB::connection('school')->statement('VACUUM');
    }

    public function integrityCheck(School $school): string
    {
        $this->activate($school);
        $result = DB::connection('school')->select('PRAGMA integrity_check');
        // result is array of stdClass with integrity_check column
        $val = $result[0]->integrity_check ?? $result[0]->{'integrity_check'} ?? 'ok';
        if (is_object($val)) {
            $val = json_encode($val);
        }

        return is_string($val) ? $val : 'ok';
    }

    public function pendingMigrations(School $school): array
    {
        $this->activate($school);
        Artisan::call('migrate:status', [
            '--database' => 'school',
            '--path' => 'database/migrations/school',
        ]);

        return explode("\n", Artisan::output());
    }

    // ==================== TABLE MANAGER ====================

    /**
     * Daftar tabel pada database sekolah aktif (sqlite_master).
     *
     * @return array<int, array{name:string, sql:string, count:int|null}>
     */
    public function listTables(School $school): array
    {
        $this->activate($school);
        $rows = DB::connection('school')->select("SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
        $out = [];
        foreach ($rows as $r) {
            $cnt = null;
            try {
                $cnt = DB::connection('school')->table($r->name)->count();
            } catch (\Throwable) {
            }
            $out[] = ['name' => $r->name, 'sql' => $r->sql, 'count' => $cnt];
        }

        return $out;
    }

    /**
     * Schema kolom via PRAGMA table_info
     */
    public function tableSchema(School $school, string $table): array
    {
        $this->activate($school);
        // validasi nama tabel alfanum
        if (! preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            throw new \InvalidArgumentException('Nama tabel tidak valid');
        }

        return DB::connection('school')->select("PRAGMA table_info('".str_replace("'", "''", $table)."')");
    }

    /**
     * Data paginasi untuk tabel
     */
    public function tableData(School $school, string $table, int $perPage = 15): LengthAwarePaginator
    {
        $this->activate($school);
        if (! preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            throw new \InvalidArgumentException('Nama tabel tidak valid');
        }
        // ensure table exists
        $exists = DB::connection('school')->select("SELECT name FROM sqlite_master WHERE type='table' AND name=?", [$table]);
        if (empty($exists)) {
            throw new \RuntimeException('Tabel tidak ditemukan');
        }

        $paginator = DB::connection('school')->table($table)->paginate($perPage);
        $sensitiveColumns = config('spj.database_manager_sensitive_columns', []);
        $paginator->getCollection()->transform(function (object $row) use ($sensitiveColumns): object {
            foreach (get_object_vars($row) as $column => $value) {
                $normalized = strtolower((string) $column);
                foreach ($sensitiveColumns as $sensitiveColumn) {
                    if (str_contains($normalized, $sensitiveColumn)) {
                        $row->{$column} = '[REDACTED]';
                        break;
                    }
                }
            }

            return $row;
        });

        return $paginator;
    }

    public function tableIndexes(School $school, string $table): array
    {
        $this->activate($school);

        return DB::connection('school')->select("PRAGMA index_list('".str_replace("'", "''", $table)."')");
    }
}
