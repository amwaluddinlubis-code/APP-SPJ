<?php

namespace App\Console\Commands;

use App\Services\SpjV2ReadParityService;
use App\Services\V2BIsolatedDatabaseGuard;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Throwable;

#[Signature('spj:v2-read-parity
    {--database= : Explicit isolated SQLite target path}
    {--npsn= : Verified school NPSN for the isolated copy}
    {--source-id= : ARKAS source id represented by the raw mirror}
    {--source-path= : Read-only ARKAS source path used for identity evidence}
    {--source-npsn= : NPSN proven by the ARKAS source identity}')]
#[Description('Compare the current fresh read model with canonical V2 on an isolated rehearsal database.')]
final class AuditSpjV2ReadParity extends Command
{
    public function __construct(
        private readonly SpjV2ReadParityService $parity,
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
            $sourcePath = (string) $this->option('source-path');
            $sourceNpsn = (int) $this->option('source-npsn');

            if ($target === '' || $npsn <= 0 || $sourceId <= 0 || $sourcePath === '' || $sourceNpsn <= 0) {
                throw new \RuntimeException('V2-D parity requires --database, --npsn, --source-id, --source-path, and --source-npsn.');
            }

            $manifest = [
                'target_path' => $target,
                'npsn' => $npsn,
                'source_id' => $sourceId,
                'source_identity_npsn' => $sourceNpsn,
                'source_path' => $sourcePath,
                'source_read_only' => true,
                'query_only' => true,
                'source_unavailable' => false,
                'mode' => 'V2_D_SHADOW_READ_PARITY',
            ];

            Config::set('database.connections.school.database', $target);
            Config::set('spj.v2_b_isolated_manifest', $manifest);
            DB::purge('school');
            $db = DB::connection('school');
            $this->guard->assertExplicitIsolatedTarget($db, $manifest);

            $result = $this->parity->compare($db);
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $result['status'] === 'PASS' ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
