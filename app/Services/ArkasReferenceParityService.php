<?php

namespace App\Services;

use RuntimeException;

final class ArkasReferenceParityService
{
    /**
     * @param  array<string, array{schema: array<int, array<string, mixed>>, rows: array<int, array<string, mixed>>}>  $sources
     * @param  array<int, string>  $keyColumns
     * @return array{status:string, schema_hashes:array<string, string>, rowset_hashes:array<string, string>, rows:array<string, int>, common_rows:int, different_common_rows:int, only_rows:array<string, int>, duplicate_keys:array<string, int>, confirmed_global:bool}
     */
    public function compare(array $sources, array $keyColumns): array
    {
        if (count($sources) < 2 || $keyColumns === []) {
            throw new RuntimeException('Parity ARKAS membutuhkan minimal dua source dan key columns.');
        }

        $sourceMaps = [];
        $schemaHashes = [];
        $rowsetHashes = [];
        $rowCounts = [];
        $duplicateKeys = [];

        foreach ($sources as $sourceName => $source) {
            $schemaHashes[$sourceName] = hash('sha256', $this->canonicalSchema($source['schema']));
            $map = [];
            $duplicates = 0;

            foreach ($source['rows'] as $row) {
                $identity = [];
                foreach ($keyColumns as $column) {
                    if (! array_key_exists($column, $row) || $row[$column] === null || trim((string) $row[$column]) === '') {
                        throw new RuntimeException(sprintf('Key parity ARKAS kosong: %s pada source %s.', $column, $sourceName));
                    }

                    $identity[] = (string) $row[$column];
                }

                $identityKey = json_encode($identity, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (isset($map[$identityKey])) {
                    $duplicates++;
                }

                $map[$identityKey] = $this->canonicalRow($row);
            }

            ksort($map, SORT_STRING);
            $sourceMaps[$sourceName] = $map;
            $rowCounts[$sourceName] = count($source['rows']);
            $duplicateKeys[$sourceName] = $duplicates;
            $rowsetHashes[$sourceName] = hash('sha256', implode("\n", $map));
        }

        $allKeys = [];
        foreach ($sourceMaps as $map) {
            $allKeys = [...$allKeys, ...array_keys($map)];
        }
        $allKeys = array_values(array_unique($allKeys));

        $sourceNames = array_keys($sources);
        $commonRows = 0;
        $differentCommonRows = 0;
        $onlyRows = [];

        foreach ($allKeys as $key) {
            $present = [];
            foreach ($sourceNames as $sourceName) {
                if (array_key_exists($key, $sourceMaps[$sourceName])) {
                    $present[] = $sourceName;
                }
            }

            if (count($present) === count($sourceNames)) {
                $commonRows++;
                if (count(array_unique(array_map(fn (string $sourceName): string => $sourceMaps[$sourceName][$key], $sourceNames))) > 1) {
                    $differentCommonRows++;
                }

                continue;
            }

            $presence = implode('', $present);
            $onlyRows[$presence] = ($onlyRows[$presence] ?? 0) + 1;
        }

        $schemaParity = count(array_unique($schemaHashes)) === 1;
        $confirmed = $schemaParity
            && $differentCommonRows === 0
            && $onlyRows === []
            && array_sum($duplicateKeys) === 0;

        return [
            'status' => $confirmed ? 'GLOBAL_REFERENCE_CONFIRMED' : 'PARITY_CONFLICT',
            'schema_hashes' => $schemaHashes,
            'rowset_hashes' => $rowsetHashes,
            'rows' => $rowCounts,
            'common_rows' => $commonRows,
            'different_common_rows' => $differentCommonRows,
            'only_rows' => $onlyRows,
            'duplicate_keys' => $duplicateKeys,
            'confirmed_global' => $confirmed,
        ];
    }

    /** @param array<int, array<string, mixed>> $schema */
    private function canonicalSchema(array $schema): string
    {
        $columns = [];
        foreach ($schema as $column) {
            $normalized = [];
            foreach ($column as $name => $value) {
                $normalized[strtolower((string) $name)] = $value;
            }
            ksort($normalized);
            $columns[] = $normalized;
        }

        return json_encode($columns, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @param array<string, mixed> $row */
    private function canonicalRow(array $row): string
    {
        $normalized = [];
        foreach ($row as $name => $value) {
            $normalized[strtolower((string) $name)] = $value === null ? null : (string) $value;
        }
        ksort($normalized);

        return json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
