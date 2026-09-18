<?php

namespace App\Console\Commands;

use App\Services\SpjV2LegacyMigrationService;
use App\Services\V2BIsolatedDatabaseGuard;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Throwable;

#[Signature('spj:v2-migrate
    {--database= : Explicit isolated SQLite target path}
    {--npsn= : Verified school NPSN for the isolated copy}
    {--source-id= : ARKAS source id represented by the raw mirror}
    {--source-path= : Read-only ARKAS source path used for identity evidence}
    {--source-npsn= : NPSN proven by the ARKAS source identity}
    {--source-unavailable : Explicitly report an orphan/source-unavailable dry-run}
    {--dry-run : Classify and report without changing the database}
    {--execute : Apply V2-C to the isolated copy}
    {--verify : Verify an already migrated isolated copy}')]
#[Description('Full legacy-to-V2 rehearsal on an explicitly isolated tenant database.')]
final class MigrateSpjV2 extends Command
{
    public function __construct(
        private readonly SpjV2LegacyMigrationService $migration,
        private readonly V2BIsolatedDatabaseGuard $guard,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $target = (string) $this->option('database');
            $npsn = (int) $this->option('npsn');
            $sourceId = (int) $this->option('source-id');
            $dryRun = (bool) $this->option('dry-run');
            $execute = (bool) $this->option('execute');
            $verify = (bool) $this->option('verify');
            $modeCount = (int) $dryRun + (int) $execute + (int) $verify;

            if ($target === '' || $npsn <= 0 || $sourceId <= 0 || $modeCount !== 1) {
                throw new \RuntimeException('V2-C requires --database, --npsn, --source-id, and exactly one of --dry-run, --execute, or --verify.');
            }

            $sourceUnavailable = (bool) $this->option('source-unavailable');
            if ($sourceUnavailable && ! $dryRun) {
                throw new \RuntimeException('--source-unavailable is permitted only with --dry-run.');
            }

            $manifest = [
                'target_path' => $target,
                'npsn' => $npsn,
                'source_id' => $sourceId,
                'source_identity_npsn' => $sourceUnavailable ? null : (int) $this->option('source-npsn'),
                'source_path' => $this->option('source-path'),
                'source_read_only' => ! $sourceUnavailable,
                'query_only' => ! $sourceUnavailable,
                'source_unavailable' => $sourceUnavailable,
                'mode' => $sourceUnavailable ? 'SOURCE_UNAVAILABLE_DRY_RUN' : null,
            ];
            Config::set('database.connections.school.database', $target);
            Config::set('spj.v2_b_isolated_manifest', $manifest);
            DB::purge('school');
            $db = DB::connection('school');
            $this->guard->assertMigrationTarget($db, $manifest);

            $targetHashBefore = is_file($target) ? hash_file('sha256', $target) : null;
            $sourcePath = $manifest['source_path'];
            $sourceHashBefore = is_string($sourcePath) && is_file($sourcePath) ? hash_file('sha256', $sourcePath) : null;

            if ($verify) {
                $result = $this->migration->verify($db);
                $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                foreach ($result['gates'] as $gate => $passed) {
                    $this->line(sprintf('Gate %-24s %s', $gate, $passed ? 'PASS' : 'FAIL'));
                }

                return $result['status'] === 'PASS' ? self::SUCCESS : self::FAILURE;
            }

            if ($execute) {
                Artisan::call('migrate', [
                    '--database' => 'school',
                    '--path' => 'database/migrations/school',
                    '--force' => true,
                    '--no-interaction' => true,
                ]);
                Artisan::call('migrate', [
                    '--database' => 'school',
                    '--path' => 'database/migrations/v2-rehearsal',
                    '--force' => true,
                    '--no-interaction' => true,
                ]);
                $db = DB::connection('school');
            }

            $reportPath = storage_path('app/v2-c-rehearsal/reports/'.$npsn.'-'.($execute ? 'execute' : 'dry-run').'-'.now()->format('Ymd_His').'.json');
            $result = $this->migration->migrate($db, $sourceId, $execute, $reportPath, $npsn);
            $result['identity'] = [
                'npsn' => $npsn,
                'source_id' => $sourceId,
                'target_path' => $target,
                'source_path' => $sourcePath,
                'source_unavailable' => $sourceUnavailable,
            ];
            $result['hashes'] = [
                'target_before' => $targetHashBefore,
                'target_after' => is_file($target) ? hash_file('sha256', $target) : null,
                'source_before' => $sourceHashBefore,
                'source_after' => is_string($sourcePath) && is_file($sourcePath) ? hash_file('sha256', $sourcePath) : null,
            ];
            file_put_contents($reportPath, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $this->table(['Field', 'Value'], [
                ['Mode', $result['mode']],
                ['NPSN', $result['npsn']],
                ['EXACT', $result['classification']['EXACT']],
                ['DETERMINISTIC', $result['classification']['DETERMINISTIC']],
                ['PARTIAL', $result['classification']['PARTIAL']],
                ['SOURCE_MISSING', $result['classification']['SOURCE_MISSING']],
                ['AMBIGUOUS', $result['classification']['AMBIGUOUS']],
                ['LEGACY_ONLY', $result['classification']['LEGACY_ONLY']],
                ['V2 transactions', $result['migrated']['v2_transactions']],
                ['V2 source links', $result['migrated']['source_links']],
                ['Transaction overlays', $result['migrated']['transaction_overlays']],
                ['Item overlays', $result['migrated']['item_overlays']],
                ['Legacy maps', $result['migrated']['legacy_maps']],
                ['Package V2 links', $result['migrated']['package_v2_links']],
                ['Protected continuity', $result['package_document_continuity']['unchanged'] ? 'PASS' : 'FAIL'],
                ['Report', $result['report_path']],
            ]);

            return $result['errors'] === [] && $result['package_document_continuity']['unchanged'] ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
