<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Services\SchoolDatabaseManager;
use App\Services\SpjOverlayMigrationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('spj:overlay-decision-support
    {--school-id= : ID sekolah target}
    {--source-sql= : Path ekspor SQLite SQL lama}
    {--output-dir= : Direktori output writable; default project storage report directory}')]
#[Description('Membuat artefak read-only untuk keputusan manual reconciliation overlay.')]
class GenerateSpjOverlayDecisionSupport extends Command
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
            $result = $this->migration->decisionSupport($school, (string) $this->option('source-sql'), $this->option('output-dir') ?: null);
            $this->table(['Keterangan', 'Nilai'], [
                ['Ambiguous', $result['ambiguous']],
                ['Unmatched', $result['unmatched']],
                ['Possible manual lookup', $result['possible_manual_lookup']],
                ['Truly missing/unverified', $result['truly_missing_or_unverified']],
                ['JSON', $result['json']],
                ['CSV', $result['csv']],
            ]);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
