<?php

namespace App\Services;

use App\Models\ArkasImportProfile;
use App\Models\ArkasImportRun;
use App\Models\ArkasSource;
use App\Models\FiscalYear;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ArkasGenericImportService
{
    private readonly ArkasSourceKeyResolver $sourceKeys;

    private readonly ArkasImportRowSynchronizer $rows;

    public function __construct(
        private readonly ArkasStagingService $staging,
        private readonly ArkasDomainAdapter $adapter,
        ?ArkasSourceKeyResolver $sourceKeys = null,
        ?ArkasImportRowSynchronizer $rows = null,
    ) {
        $this->sourceKeys = $sourceKeys ?? new ArkasSourceKeyResolver;
        $this->rows = $rows ?? new ArkasImportRowSynchronizer($this->sourceKeys);
    }

    public function synchronize(ArkasImportProfile $profile, FiscalYear $year, ArkasSource $source): ArkasImportRun
    {
        $preset = ArkasDomainAdapter::presetFor($profile->source_table);
        $sourceKeyColumn = $profile->source_key_column ?: $preset['source_key_column'];
        $guardErrors = (new ArkasImportGuard)->configurationErrors(
            $profile->source_table,
            $profile->target_domain,
            $profile->sync_mode,
            $profile->source_key_column,
            $profile->source_updated_column,
            $profile->mapping ?? [],
        );
        if ($guardErrors !== []) {
            throw new \RuntimeException(implode(' ', $guardErrors));
        }

        $lock = Cache::lock(
            ArkasTenantLockKey::import($source, $profile->source_table, (int) $year->id),
            900,
        );
        if (! $lock->get()) {
            throw new \RuntimeException('Importer ARKAS untuk tabel, sekolah, dan tahun anggaran ini sedang berjalan. Tunggu sampai proses sebelumnya selesai.');
        }

        $db = DB::connection('school');
        $run = ArkasImportRun::query()->create([
            'profile_id' => $profile->id,
            'fiscal_year_id' => $year->id,
            'status' => 'RUNNING',
            'started_at' => now(),
        ]);

        try {
            $bridgeCommand = $preset['bridge_command'];
            $bridgeYear = in_array($bridgeCommand, ['bku', 'rkas'], true) ? $year->year : null;
            $bridgeFundSource = in_array($bridgeCommand, ['bku', 'rkas'], true) ? $year->fund_source_id : null;
            $records = $this->staging->fetch($profile, $year, $source, $bridgeCommand, 'decode', $bridgeYear, $bridgeFundSource);
            if ($profile->sync_mode === 'incremental' && $profile->last_synced_at && $profile->source_updated_column) {
                $lastSyncedAt = $profile->last_synced_at;
                $records = array_values(array_filter($records, function (array $record) use ($profile, $lastSyncedAt): bool {
                    $value = $this->recordValue($record, $profile->source_updated_column);
                    if ($value === null) {
                        return false;
                    }
                    try {
                        return Carbon::parse((string) $value)->greaterThan($lastSyncedAt);
                    } catch (\Throwable) {
                        return false;
                    }
                }));
            }

            $metrics = [
                'new' => 0,
                'changed' => 0,
                'unchanged' => 0,
                'removed' => 0,
                'written' => 0,
            ];
            $domainWritten = 0;
            $db->transaction(function () use ($profile, $year, $records, $sourceKeyColumn, &$metrics, &$domainWritten): void {
                $metrics = $this->rows->synchronize($profile, $year, $records, $sourceKeyColumn);
                $domainWritten = $this->adapter->synchronize($profile, $year, $records);
            });

            $run->update([
                'status' => 'SUCCESS',
                'records_read' => count($records),
                'records_written' => $metrics['written'],
                'records_new' => $metrics['new'],
                'records_changed' => $metrics['changed'],
                'records_unchanged' => $metrics['unchanged'],
                'records_removed' => $metrics['removed'],
                'message' => $profile->target_domain === 'raw'
                    ? "Snapshot generik tersimpan melalui Bridge {$bridgeCommand}."
                    : "Snapshot dan {$domainWritten} baris domain {$profile->target_domain} tersimpan melalui Bridge {$bridgeCommand}.",
                'finished_at' => now(),
            ]);
            $profile->update(['last_synced_at' => now()]);
        } catch (\Throwable $exception) {
            $run->update(['status' => 'FAILED', 'message' => $exception->getMessage(), 'finished_at' => now()]);
            throw $exception;
        } finally {
            $lock->release();
        }

        return $run->fresh();
    }

    /** @param array<string, mixed> $record */
    private function recordValue(array $record, string $column): mixed
    {
        foreach ($record as $key => $value) {
            if (strcasecmp((string) $key, $column) === 0) {
                return filled($value) ? $value : null;
            }
        }

        return null;
    }
}
