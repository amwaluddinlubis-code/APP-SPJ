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
        private readonly ?ArkasMirrorManifest $manifest = null,
        private readonly ?ArkasMirrorContractValidator $contracts = null,
    ) {}

    /**
     * @return array{tables:int, skipped_tables:int, optional_unavailable:array<int, string>, non_empty:int, rows:int, stale:int}
     */
    public function synchronize(ArkasSource $source, int $limit = 100000): array
    {
        $db = DB::connection('school');
        $preflight = $this->prepare($source, $limit);
        $availableTables = $preflight['available_tables'];
        $tables = $preflight['tables'];
        $optionalUnavailable = $preflight['optional_unavailable'];
        $seenTables = [];
        $rowCount = 0;
        $nonEmpty = 0;
        $prepared = $preflight['prepared'];

        foreach ($prepared as $tableName => $data) {
            $seenTables[] = $tableName;
            $records = $data['records'];
            $keyColumns = $data['keyColumns'];
            $attributes = $data['attributes'];
            $now = $data['now'];
            $mirrorTable = $db->table('arkas_raw_mirror_tables')
                ->where('source_id', $source->id)
                ->where('source_table', $tableName)
                ->first();
            $mirrorTableId = $mirrorTable?->id;

            $db->transaction(function () use (&$mirrorTableId, $db, $source, $tableName, $attributes, $records, $keyColumns, $now): void {
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

                $db->table('arkas_raw_mirror_rows')->where('mirror_table_id', $mirrorTableId)->delete();
                $batch = [];
                foreach ($records as $record) {
                    $payload = json_encode($record, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
                    $sourceKey = $this->sourceKeys->resolveStrictFromColumns($record, $keyColumns);
                    $batch[] = [
                        'mirror_table_id' => $mirrorTableId,
                        'source_key' => $sourceKey,
                        'ordinal' => 0,
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

        $stale = $db->table('arkas_raw_mirror_tables')
            ->where('source_id', $source->id)
            ->whereNotIn('source_table', $seenTables)
            ->update(['status' => 'STALE', 'updated_at' => now()]);

        return [
            'tables' => count($tables),
            'skipped_tables' => count(array_diff($availableTables, $tables)),
            'optional_unavailable' => $optionalUnavailable,
            'non_empty' => $nonEmpty,
            'rows' => $rowCount,
            'stale' => $stale,
        ];
    }

    /**
     * Validate and count a source without writing mirror metadata or rows.
     *
     * @return array{dry_run:bool, tables:int, skipped_tables:int, optional_unavailable:array<int, string>, non_empty:int, rows:int}
     */
    public function dryRun(ArkasSource $source, int $limit = 100000): array
    {
        $preflight = $this->prepare($source, $limit);
        $rows = array_sum(array_map(static fn (array $data): int => count($data['records']), $preflight['prepared']));

        return [
            'dry_run' => true,
            'tables' => count($preflight['tables']),
            'skipped_tables' => count(array_diff($preflight['available_tables'], $preflight['tables'])),
            'optional_unavailable' => $preflight['optional_unavailable'],
            'non_empty' => count(array_filter($preflight['prepared'], static fn (array $data): bool => $data['records'] !== [])),
            'rows' => $rows,
        ];
    }

    /**
     * @return array{available_tables:array<int, string>, tables:array<int, string>, optional_unavailable:array<int, string>, prepared:array<string, array<string, mixed>>}
     */
    private function prepare(ArkasSource $source, int $limit): array
    {
        $manifest = $this->manifest ?? new ArkasMirrorManifest;
        $contracts = $this->contracts ?? new ArkasMirrorContractValidator;
        $availableTables = $this->explorer->tables($source);
        $missingRequired = array_values(array_diff($manifest->requiredSourceTables(), $availableTables));
        if ($missingRequired !== []) {
            throw new \RuntimeException('Tabel ARKAS wajib tidak tersedia: '.implode(', ', $missingRequired));
        }

        $tables = $manifest->importableTables($availableTables);
        $optionalUnavailable = array_values(array_diff($manifest->optionalSourceTables(), $availableTables));
        $prepared = [];
        foreach ($tables as $tableName) {
            $entry = $manifest->entry($tableName);
            $bridgeClass = $entry['bridge'];
            $bridge = new $bridgeClass;
            if (! in_array($tableName, $bridge->sourceTables(), true)) {
                throw new \RuntimeException('Kontrak bridge ARKAS tidak mendukung tabel manifest: '.$tableName);
            }
            if ($entry['category'] === 'TENANT' && $bridge->scope() !== 'TENANT') {
                throw new \RuntimeException('Scope bridge ARKAS tidak sesuai manifest untuk tabel '.$tableName.'.');
            }

            $columns = $this->explorer->inspect($source, $tableName, 1)['columns'];
            $records = $this->fetchAllRows($source, $tableName, $limit);
            $keyColumns = $contracts->validate($entry, $columns, $records);
            $schema = array_values(array_map(static fn (array $column): array => [
                'name' => $column['name'],
                'type' => $column['type'],
                'nullable' => $column['nullable'],
                'primary' => $column['primary'],
                'primary_order' => $column['primary_order'] ?? '0',
            ], $columns));
            $schemaJson = json_encode($schema, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $now = now();
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
            $prepared[$tableName] = compact('records', 'keyColumns', 'attributes', 'now');
        }

        $this->validateTenantRelations($prepared);

        return [
            'available_tables' => $availableTables,
            'tables' => $tables,
            'optional_unavailable' => $optionalUnavailable,
            'prepared' => $prepared,
        ];
    }

    /** @param array<string, array<string, mixed>> $prepared */
    private function validateTenantRelations(array $prepared): void
    {
        if (! isset($prepared['anggaran'], $prepared['kas_umum'])) {
            return;
        }

        $budgetIds = [];
        foreach ($prepared['anggaran']['records'] as $record) {
            $budgetId = $this->recordValue($record, 'id_anggaran');
            if ($budgetId !== null && trim((string) $budgetId) !== '') {
                $budgetIds[(string) $budgetId] = true;
            }
        }

        foreach ($prepared['kas_umum']['records'] as $record) {
            $budgetId = $this->recordValue($record, 'id_anggaran');
            if ($budgetId === null || ! isset($budgetIds[(string) $budgetId])) {
                throw new \RuntimeException('Relasi tenant ARKAS orphan: kas_umum.id_anggaran tidak ditemukan pada anggaran.');
            }
        }
    }

    /** @param array<string, mixed> $record */
    private function recordValue(array $record, string $column): mixed
    {
        foreach ($record as $name => $value) {
            if (strcasecmp((string) $name, $column) === 0) {
                return $value;
            }
        }

        return null;
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
