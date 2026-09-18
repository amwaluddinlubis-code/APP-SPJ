<?php

namespace App\Services;

use App\Models\ArkasSource;
use Illuminate\Support\Facades\DB;

final class ArkasRawMirrorService
{
    public function __construct(
        private readonly ArkasDatabaseExplorer $explorer,
        private readonly ArkasBridgeClient $bridge,
        private readonly ArkasSourceKeyResolver $sourceKeys,
    ) {}

    /**
     * @return array{tables:int, non_empty:int, rows:int, stale:int}
     */
    public function synchronize(ArkasSource $source, int $limit = 100000): array
    {
        $db = DB::connection('school');
        $tables = $this->explorer->tables($source);
        $seenTables = [];
        $rowCount = 0;
        $nonEmpty = 0;

        foreach ($tables as $tableName) {
            $seenTables[] = $tableName;
            $columns = $this->explorer->inspect($source, $tableName, 1)['columns'];
            $primaryKeyColumns = $this->primaryKeyColumns($columns);
            $records = $this->fetchAllRows($source, $tableName, $limit);
            $schema = array_values(array_map(static fn (array $column): array => [
                'name' => $column['name'],
                'type' => $column['type'],
                'nullable' => $column['nullable'],
                'primary' => $column['primary'],
                'primary_order' => $column['primary_order'] ?? '0',
            ], $columns));
            $schemaJson = json_encode($schema, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $now = now();
            $mirrorTable = $db->table('arkas_raw_mirror_tables')
                ->where('source_id', $source->id)
                ->where('source_table', $tableName)
                ->first();
            $attributes = [
                'schema' => $schemaJson,
                'schema_hash' => hash('sha256', $schemaJson),
                'row_count' => count($records),
                'status' => $records === [] ? 'EMPTY' : 'ACTIVE',
                'last_seen_at' => $now,
                'last_synced_at' => $now,
                'last_error' => null,
                'updated_at' => $now,
            ];
            $mirrorTableId = $mirrorTable?->id;
            if ($mirrorTableId === null) {
                $mirrorTableId = $db->table('arkas_raw_mirror_tables')->insertGetId([
                    'source_id' => $source->id,
                    'source_table' => $tableName,
                    ...$attributes,
                    'created_at' => $now,
                ]);
            } else {
                $db->table('arkas_raw_mirror_tables')->where('id', $mirrorTableId)->update($attributes);
            }

            $db->transaction(function () use ($db, $mirrorTableId, $records, $primaryKeyColumns, $now): void {
                $db->table('arkas_raw_mirror_rows')->where('mirror_table_id', $mirrorTableId)->delete();
                $seen = [];
                $batch = [];
                foreach ($records as $record) {
                    $payload = json_encode($record, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
                    $sourceKey = $primaryKeyColumns === []
                        ? $this->sourceKeys->resolve($record)
                        : $this->sourceKeys->resolveFromColumns($record, $primaryKeyColumns);
                    $ordinal = $seen[$sourceKey] ?? 0;
                    $seen[$sourceKey] = $ordinal + 1;
                    $batch[] = [
                        'mirror_table_id' => $mirrorTableId,
                        'source_key' => $sourceKey,
                        'ordinal' => $ordinal,
                        'payload' => $payload,
                        'payload_hash' => hash('sha256', $payload),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    if (count($batch) === 500) {
                        $db->table('arkas_raw_mirror_rows')->insert($batch);
                        $batch = [];
                    }
                }
                if ($batch !== []) {
                    $db->table('arkas_raw_mirror_rows')->insert($batch);
                }
            });

            $rowCount += count($records);
            $nonEmpty += $records === [] ? 0 : 1;
        }

        $staleQuery = $db->table('arkas_raw_mirror_tables')
            ->where('source_id', $source->id)
            ->whereNotIn('source_table', $seenTables);
        $stale = $staleQuery->update(['status' => 'STALE', 'updated_at' => now()]);

        return ['tables' => count($tables), 'non_empty' => $nonEmpty, 'rows' => $rowCount, 'stale' => $stale];
    }

    /**
     * @param  array<int, array<string, string>>  $columns
     * @return array<int, string>
     */
    private function primaryKeyColumns(array $columns): array
    {
        $primaryColumns = array_values(array_filter(
            $columns,
            static fn (array $column): bool => (int) ($column['primary_order'] ?? 0) > 0
                || ($column['primary'] ?? '—') === 'Ya',
        ));

        usort($primaryColumns, static function (array $left, array $right): int {
            $leftOrder = (int) ($left['primary_order'] ?? ((int) ($left['position'] ?? 0) + 1));
            $rightOrder = (int) ($right['primary_order'] ?? ((int) ($right['position'] ?? 0) + 1));

            return $leftOrder <=> $rightOrder;
        });

        return array_values(array_map(
            static fn (array $column): string => (string) $column['name'],
            $primaryColumns,
        ));
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchAllRows(ArkasSource $source, string $tableName, int $pageSize): array
    {
        $records = [];
        $offset = 0;
        $previousPageHash = null;
        do {
            $page = ArkasPipePayload::decode(
                $this->bridge->execute($source, 'rows', null, $tableName, null, $pageSize, $offset),
                'rows:'.$tableName,
            );
            $pageHash = hash('sha256', json_encode($page, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            if ($offset > 0 && $pageHash === $previousPageHash) {
                throw new \RuntimeException('ARKASBridge belum mendukung paging offset. Build ulang ARKASBridge terbaru sebelum menyinkronkan tabel lebih dari 100000 row.');
            }
            $previousPageHash = $pageHash;
            $records = [...$records, ...$page];
            $offset += count($page);
        } while (count($page) === $pageSize);

        return $records;
    }
}
