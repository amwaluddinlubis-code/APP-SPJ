<?php

namespace App\Services;

use App\Models\ArkasImportProfile;
use App\Models\FiscalYear;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ArkasImportRowSynchronizer
{
    private readonly ArkasSourceKeyResolver $sourceKeys;

    public function __construct(?ArkasSourceKeyResolver $sourceKeys = null)
    {
        $this->sourceKeys = $sourceKeys ?? new ArkasSourceKeyResolver;
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array{new:int,changed:int,unchanged:int,removed:int,written:int}
     */
    public function synchronize(
        ArkasImportProfile $profile,
        FiscalYear $year,
        array $records,
        ?string $sourceKeyColumn,
    ): array {
        $db = DB::connection('school');
        $existing = $this->existingRows($db, $profile, $year);
        $incomingKeys = [];
        $metrics = [
            'new' => 0,
            'changed' => 0,
            'unchanged' => 0,
            'removed' => 0,
            'written' => 0,
        ];
        $timestamp = now();

        foreach ($records as $record) {
            $sourceKey = $this->sourceKeys->resolve($record, $sourceKeyColumn);
            $incomingKeys[$sourceKey] = true;
            $payload = json_encode($record, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
            $payloadHash = hash('sha256', $payload);
            $existingRow = $existing->get($sourceKey);

            if ($existingRow === null) {
                $db->table('arkas_import_rows')->insert([
                    'profile_id' => $profile->id,
                    'fiscal_year_id' => $year->id,
                    'source_key' => $sourceKey,
                    'parent_source_key' => $this->mappedValue($record, $profile, 'parent'),
                    'relation_type' => $profile->source_table,
                    'payload' => $payload,
                    'payload_hash' => $payloadHash,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
                $metrics['new']++;
                $metrics['written']++;

                continue;
            }

            if ((string) $existingRow->payload_hash === $payloadHash) {
                $metrics['unchanged']++;

                continue;
            }

            $db->table('arkas_import_rows')
                ->where('profile_id', $profile->id)
                ->where('fiscal_year_id', $year->id)
                ->where('source_key', $sourceKey)
                ->update([
                    'parent_source_key' => $this->mappedValue($record, $profile, 'parent'),
                    'relation_type' => $profile->source_table,
                    'payload' => $payload,
                    'payload_hash' => $payloadHash,
                    'updated_at' => $timestamp,
                ]);
            $metrics['changed']++;
            $metrics['written']++;
        }

        if ($profile->sync_mode === 'full_refresh') {
            $removedKeys = $existing->keys()
                ->reject(static fn (string $sourceKey): bool => isset($incomingKeys[$sourceKey]))
                ->values();

            if ($removedKeys->isNotEmpty()) {
                $metrics['removed'] = $db->table('arkas_import_rows')
                    ->where('profile_id', $profile->id)
                    ->where('fiscal_year_id', $year->id)
                    ->whereIn('source_key', $removedKeys->all())
                    ->delete();
            }
        }

        return $metrics;
    }

    private function existingRows(ConnectionInterface $db, ArkasImportProfile $profile, FiscalYear $year): Collection
    {
        return $db->table('arkas_import_rows')
            ->where('profile_id', $profile->id)
            ->where('fiscal_year_id', $year->id)
            ->get(['source_key', 'payload_hash', 'created_at', 'updated_at'])
            ->keyBy('source_key');
    }

    /** @param array<string, mixed> $record */
    private function mappedValue(array $record, ArkasImportProfile $profile, string $role): ?string
    {
        $column = array_search($role, $profile->mapping ?? [], true);
        if ($column === false) {
            return null;
        }

        foreach ($record as $key => $value) {
            if (strcasecmp((string) $key, (string) $column) === 0) {
                return filled($value) ? (string) $value : null;
            }
        }

        return null;
    }
}
