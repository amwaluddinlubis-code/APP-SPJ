<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use RuntimeException;

final class V2BSourceIdentityRegistryService
{
    public function __construct(
        private readonly V2BIsolatedDatabaseGuard $guard,
    ) {
    }

    public function registerOrRefresh(
        Connection $connection,
        int $sourceId,
        string $sourceTable,
        string $sourceKey,
        array $primaryKey,
        string $identityType,
        ?int $rawMirrorRowId,
        ?string $payloadHash,
    ): int {
        $this->guard->assertStableCriticalIdentity($identityType);
        $now = Carbon::now();
        $encodedPrimaryKey = json_encode($primaryKey, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $existing = $connection->table('arkas_source_identity_registry')
            ->where('source_id', $sourceId)
            ->where('source_table', $sourceTable)
            ->where('source_key', $sourceKey)
            ->first();

        if ($existing === null) {
            return (int) $connection->table('arkas_source_identity_registry')->insertGetId([
                'source_id' => $sourceId,
                'source_table' => $sourceTable,
                'source_key' => $sourceKey,
                'primary_key_json' => $encodedPrimaryKey,
                'identity_type' => $identityType,
                'current_raw_mirror_row_id' => $rawMirrorRowId,
                'payload_hash' => $payloadHash,
                'source_status' => 'ACTIVE',
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $connection->table('arkas_source_identity_registry')->where('id', $existing->id)->update([
            'primary_key_json' => $encodedPrimaryKey,
            'identity_type' => $identityType,
            'current_raw_mirror_row_id' => $rawMirrorRowId,
            'payload_hash' => $payloadHash,
            'source_status' => 'ACTIVE',
            'last_seen_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $existing->id;
    }

    public function markMissing(Connection $connection, int $sourceId, string $sourceTable, string $sourceKey): void
    {
        $updated = $connection->table('arkas_source_identity_registry')
            ->where('source_id', $sourceId)
            ->where('source_table', $sourceTable)
            ->where('source_key', $sourceKey)
            ->update([
                'source_status' => 'SOURCE_MISSING',
                'current_raw_mirror_row_id' => null,
                'updated_at' => Carbon::now(),
            ]);

        if ($updated === 0) {
            throw new RuntimeException('Cannot mark an unknown source identity missing.');
        }
    }
}
