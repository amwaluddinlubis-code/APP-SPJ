<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Services\SchoolDatabaseManager;
use App\Services\SpjOverlayMigrationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('spj:migrate-overlay
    {--school-id= : ID sekolah target}
    {--source-sql= : Path ekspor SQLite SQL lama}
    {--execute : Tulis hasil migrasi ke database tenant; default hanya dry-run}')]
#[Description('Migrasi overlay operator dari ekspor SQLite lama ke tabel fresh SPJ.')]
class MigrateSpjOverlay extends Command
{
    public function __construct(
        private readonly SpjOverlayMigrationService $migration,
        private readonly SchoolDatabaseManager $databases,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $school = School::findOrFail((int) $this->option('school-id'));
            $this->databases->ensureMigrated($school);
            $result = $this->migration->migrate(
                $school,
                (string) $this->option('source-sql'),
                (bool) $this->option('execute'),
            );
            $this->table(['Keterangan', 'Nilai'], [
                ['Mode', $result['mode']],
                ['Matched transaksi', $result['matched']],
                ['Tidak cocok', $result['unmatched']],
                ['Ambiguous', $result['ambiguous']],
                ['Item diproses', $result['items']],
                ['Paket diproses', $result['packages']],
                ['Field diisi dari database lama', $result['fields_migrated']],
                ['Field lama dipertahankan', $result['fields_preserved']],
                ['Backup', $result['backup'] ?? 'Tidak dibuat (dry-run)'],
                ['Report', $result['report']],
            ]);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
