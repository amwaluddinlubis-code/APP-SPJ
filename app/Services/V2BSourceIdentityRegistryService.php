<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use RuntimeException;

final class V2BSourceIdentityRegistryService
{
    private const ALLOWED_IDENTITY_TYPES = [
        'PRIMARY_KEY',
        'COMPOSITE_PRIMARY_KEY',
        'DETERMINISTIC_FALLBACK',
        'UNSTABLE_FALLBACK',
    ];

    public function __construct(
        private readonly V2BIsolatedDatabaseGuard $guard,
    ) {}

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
        if (! in_array($identityType, self::ALLOWED_IDENTITY_TYPES, true)) {
            throw new RuntimeException('Unknown source identity type: '.$identityType);
        }
        if (trim($sourceKey) === '') {
            throw new RuntimeException('Source identity key cannot be empty.');
        }
        $this->guard->assertStableCriticalIdentity($identityType);
        $this->assertRawMirrorLineage($connection, $sourceId, $sourceTable, $sourceKey, $rawMirrorRowId, $payloadHash);
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

    private function assertRawMirrorLineage(
        Connection $connection,
        int $sourceId,
        string $sourceTable,
        string $sourceKey,
        ?int $rawMirrorRowId,
        ?string $payloadHash,
    ): void {
        if ($rawMirrorRowId === null) {
            return;
        }

        $lineage = $connection->table('arkas_raw_mirror_rows as rows')
            ->join('arkas_raw_mirror_tables as tables', 'tables.id', '=', 'rows.mirror_table_id')
            ->where('rows.id', $rawMirrorRowId)
            ->first([
                'tables.source_id',
                'tables.source_table',
                'rows.source_key',
                'rows.payload_hash',
            ]);

        if ($lineage === null
            || (int) $lineage->source_id !== $sourceId
            || (string) $lineage->source_table !== $sourceTable
            || (string) $lineage->source_key !== $sourceKey
            || ($payloadHash !== null && (string) $lineage->payload_hash !== $payloadHash)) {
            throw new RuntimeException('Raw mirror lineage does not match the requested source identity.');
        }
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
