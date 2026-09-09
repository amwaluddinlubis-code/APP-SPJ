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

    public function __construct(
        private readonly ArkasStagingService $staging,
        private readonly ArkasDomainAdapter $adapter,
        ?ArkasSourceKeyResolver $sourceKeys = null,
    ) {
        $this->sourceKeys = $sourceKeys ?? new ArkasSourceKeyResolver;
    }

    public function synchronize(ArkasImportProfile $profile, FiscalYear $year, ArkasSource $source): ArkasImportRun
    {
        $lock = Cache::lock('arkas-import:'.$profile->id.':'.$year->id, 900);
        if (! $lock->get()) {
            throw new \RuntimeException('Importer ARKAS untuk profil dan tahun anggaran ini sedang berjalan. Tunggu sampai proses sebelumnya selesai.');
        }

        $db = DB::connection('school');
        $run = ArkasImportRun::query()->create([
            'profile_id' => $profile->id,
            'fiscal_year_id' => $year->id,
            'status' => 'RUNNING',
            'started_at' => now(),
        ]);

        try {
            $preset = ArkasDomainAdapter::presetFor($profile->source_table);
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

            $written = 0;
            $domainWritten = 0;
            $sourceKeyColumn = $profile->source_key_column ?: $preset['source_key_column'];
            $db->transaction(function () use ($db, $profile, $year, $records, $sourceKeyColumn, &$written, &$domainWritten): void {
                if ($profile->sync_mode === 'full_refresh') {
                    $db->table('arkas_import_rows')->where('profile_id', $profile->id)->where('fiscal_year_id', $year->id)->delete();
                }

                foreach ($records as $record) {
                    $sourceKey = $this->sourceKeys->resolve($record, $sourceKeyColumn);
                    $payload = json_encode($record, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
                    $db->table('arkas_import_rows')->updateOrInsert(
                        ['profile_id' => $profile->id, 'fiscal_year_id' => $year->id, 'source_key' => $sourceKey],
                        ['parent_source_key' => $this->mappedValue($record, $profile, 'parent'), 'relation_type' => $profile->source_table, 'payload' => $payload, 'payload_hash' => hash('sha256', $payload), 'updated_at' => now(), 'created_at' => now()],
                    );
                    $written++;
                }
                $domainWritten = $this->adapter->synchronize($profile, $year, $records);
            });

            $run->update(['status' => 'SUCCESS', 'records_read' => count($records), 'records_written' => $written, 'message' => $profile->target_domain === 'raw' ? "Snapshot generik tersimpan melalui Bridge {$bridgeCommand}." : "Snapshot dan {$domainWritten} baris domain {$profile->target_domain} tersimpan melalui Bridge {$bridgeCommand}.", 'finished_at' => now()]);
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

    /** @param array<string, mixed> $record */
    private function mappedValue(array $record, ArkasImportProfile $profile, string $role): ?string
    {
        $column = array_search($role, $profile->mapping ?? [], true);
        if ($column === false) {
            return null;
        }
        $value = $this->recordValue($record, (string) $column);

        return $value === null ? null : (string) $value;
    }
}
