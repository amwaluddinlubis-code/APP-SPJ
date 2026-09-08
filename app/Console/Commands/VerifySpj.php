<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class VerifySpj extends Command
{
    protected $signature = 'spj:verify
        {--npsn= : NPSN tenant nyata yang ikut diaudit setelah static verification}
        {--quarter=1 : Triwulan untuk audit tenant nyata}
        {--year= : Tahun anggaran audit tenant nyata}
        {--fund-source= : ID, kode, atau nama sumber dana audit tenant nyata}
        {--strict-style : Jadikan Pint repository-wide sebagai blocking gate}
        {--skip-style : Lewati Pint check}
        {--skip-build : Lewati npm build dan view cache}
        {--skip-tests : Lewati SPJ Critical test suite}';

    protected $description = 'Jalankan verification kit SPJ canonical dan opsional audit read-only tenant nyata.';

    /** @var array<int, array{name:string,status:string}> */
    private array $results = [];

    public function handle(): int
    {
        $this->newLine();
        $this->info('SPJ VERIFICATION KIT');
        $this->line('Mode: source/build/test verification'.($this->option('npsn') ? ' + real tenant read-only audit' : ' (real tenant audit belum diminta)'));
        $this->newLine();

        if (! $this->option('skip-style')) {
            $styleBlocking = (bool) $this->option('strict-style');
            if (! $this->runStep(
                'Repository Pint',
                [PHP_BINARY, base_path('vendor/bin/pint'), '--test'],
                blocking: $styleBlocking,
            )) {
                return $this->finish(false);
            }
        } else {
            $this->results[] = ['name' => 'Repository Pint', 'status' => 'SKIP'];
        }

        if (! $this->option('skip-tests')) {
            if (! $this->runStep(
                'SPJ Critical tests',
                [
                    PHP_BINARY,
                    base_path('artisan'),
                    'test',
                    '--testsuite=SPJ Critical',
                    '--compact',
                ],
                environment: ['APP_ENV' => 'testing'],
            )) {
                return $this->finish(false);
            }
        } else {
            $this->results[] = ['name' => 'SPJ Critical tests', 'status' => 'SKIP'];
        }

        if (! $this->option('skip-build')) {
            $npm = PHP_OS_FAMILY === 'Windows' ? 'npm.cmd' : 'npm';
            if (! $this->runStep('Frontend build', [$npm, 'run', 'build'])) {
                return $this->finish(false);
            }
            if (! $this->runStep('Blade view cache', [PHP_BINARY, base_path('artisan'), 'view:cache', '--no-interaction'])) {
                return $this->finish(false);
            }
        } else {
            $this->results[] = ['name' => 'Frontend build', 'status' => 'SKIP'];
            $this->results[] = ['name' => 'Blade view cache', 'status' => 'SKIP'];
        }

        $npsn = trim((string) ($this->option('npsn') ?? ''));
        if ($npsn !== '') {
            $audit = [
                PHP_BINARY,
                base_path('artisan'),
                'spj:audit-quarter',
                $npsn,
                '--quarter='.(int) $this->option('quarter'),
            ];
            $year = trim((string) ($this->option('year') ?? ''));
            if ($year !== '') {
                $audit[] = '--year='.$year;
            }
            $fundSource = trim((string) ($this->option('fund-source') ?? ''));
            if ($fundSource !== '') {
                $audit[] = '--fund-source='.$fundSource;
            }

            if (! $this->runStep('Real tenant read-only audit', $audit)) {
                return $this->finish(false);
            }
        } else {
            $this->results[] = ['name' => 'Real tenant read-only audit', 'status' => 'RVR'];
        }

        return $this->finish(true);
    }

    /**
     * @param  array<int, string>  $command
     * @param  array<string, string>|null  $environment
     */
    private function runStep(string $name, array $command, bool $blocking = true, ?array $environment = null): bool
    {
        $process = new Process($command, base_path(), $environment, null, 1200);

        $this->components->task($name, function () use ($process): bool {
            $process->run(function (string $type, string $buffer): void {
                $this->output->write($buffer);
            });

            return $process->isSuccessful();
        });

        if ($process->isSuccessful()) {
            $this->results[] = ['name' => $name, 'status' => 'PASS'];

            return true;
        }

        $status = $blocking ? 'FAIL' : 'WARN';
        $this->results[] = ['name' => $name, 'status' => $status];
        $this->newLine();

        if ($blocking) {
            $this->error($name.' gagal dengan exit code '.$process->getExitCode().'.');

            return false;
        }

        $this->warn($name.' belum bersih (exit code '.$process->getExitCode().'). Style debt dicatat sebagai WARN; verification release-safety tetap dilanjutkan. Gunakan --strict-style untuk menjadikannya blocking.');

        return true;
    }

    private function finish(bool $passed): int
    {
        $this->newLine();
        $this->table(
            ['Checkpoint', 'Status'],
            array_map(fn (array $result): array => [$result['name'], $result['status']], $this->results),
        );

        if ($passed) {
            $hasRvr = collect($this->results)->contains(fn (array $result): bool => $result['status'] === 'RVR');
            $hasWarnings = collect($this->results)->contains(fn (array $result): bool => $result['status'] === 'WARN');

            if ($hasRvr || $hasWarnings) {
                $parts = [];
                if ($hasWarnings) {
                    $parts[] = 'style/advisory WARN';
                }
                if ($hasRvr) {
                    $parts[] = 'real tenant RVR';
                }
                $this->warn('STATIC VERIFICATION PASS WITH '.strtoupper(implode(' + ', $parts)).'.');
            } else {
                $this->info('SPJ VERIFICATION PASS.');
            }

            return self::SUCCESS;
        }

        $this->error('SPJ VERIFICATION FAIL. Hentikan release checkpoint dan perbaiki step blocking pertama yang gagal.');

        return self::FAILURE;
    }
}
