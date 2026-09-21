<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Services\SchoolDatabaseManager;
use App\Services\SpjOverlayMigrationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('spj:validate-overlay-mapping
    {--school-id= : ID sekolah target}
    {--source-sql= : Path ekspor SQLite SQL lama}
    {--mapping= : Explicit JSON mapping file}
    {--output-dir= : Direktori output writable}')]
#[Description('Memvalidasi mapping manual overlay tanpa menulis database tenant.')]
class ValidateSpjOverlayMapping extends Command
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
            $result = $this->migration->validateMapping($school, (string) $this->option('source-sql'), (string) $this->option('mapping'), $this->option('output-dir') ?: null);
            $this->info($result['valid'] ? 'Mapping valid.' : 'Mapping invalid.');
            $this->line('Accepted: '.$result['accepted']);
            foreach ($result['errors'] as $error) {
                $this->error($error);
            }
            $this->line('Report: '.$result['report']);

            return $result['valid'] ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
