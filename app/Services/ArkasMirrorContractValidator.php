<?php

namespace App\Services;

use RuntimeException;

final class ArkasMirrorContractValidator
{
    /**
     * @param  array<string, mixed>  $entry
     * @param  array<int, array<string, string>>  $columns
     * @param  array<int, array<string, mixed>>  $records
     * @return array<int, string>
     */
    public function validate(array $entry, array $columns, array $records): array
    {
        $columnNames = [];
        $primaryColumns = [];
        $types = [];
        foreach ($columns as $column) {
            $name = (string) ($column['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $normalized = strtoupper($name);
            $columnNames[$normalized] = $name;
            $types[$normalized] = strtoupper((string) preg_replace('/\s*\(.*/', '', (string) ($column['type'] ?? '')));
            if ((int) ($column['primary_order'] ?? 0) > 0 || ($column['primary'] ?? '') === 'Ya') {
                $primaryColumns[(int) ($column['primary_order'] ?? 0)] = $name;
            }
        }
        ksort($primaryColumns);
        $primaryColumns = array_values($primaryColumns);

        foreach ((array) ($entry['required_columns'] ?? []) as $requiredColumn) {
            if (! isset($columnNames[strtoupper($requiredColumn)])) {
                throw new RuntimeException(sprintf(
                    'Schema ARKAS tidak didukung: kolom wajib %s tidak ada pada tabel %s.',
                    $requiredColumn,
                    $entry['source_table'],
                ));
            }
        }

        $keyStrategy = $entry['key_strategy'] ?? null;
        $configuredKeyColumns = array_values(array_map('strval', $entry['key_columns'] ?? []));
        $keyColumns = $configuredKeyColumns;
        if ($keyStrategy === 'PRIMARY_KEY') {
            if ($primaryColumns === []) {
                throw new RuntimeException('Schema ARKAS tidak didukung: tabel '.$entry['source_table'].' tidak memiliki primary key.');
            }

            if ($configuredKeyColumns !== [] && array_map('strtoupper', $configuredKeyColumns) !== array_map('strtoupper', $primaryColumns)) {
                throw new RuntimeException('Schema ARKAS berubah: primary key tabel '.$entry['source_table'].' tidak sesuai manifest.');
            }

            $keyColumns = $primaryColumns;
        } elseif ($keyStrategy !== 'COMPOSITE' || $configuredKeyColumns === []) {
            throw new RuntimeException('Kontrak key ARKAS tidak valid untuk tabel '.$entry['source_table'].'.');
        }

        foreach ($keyColumns as $keyColumn) {
            if (! isset($columnNames[strtoupper($keyColumn)])) {
                throw new RuntimeException(sprintf(
                    'Schema ARKAS tidak didukung: kolom key %s tidak ada pada tabel %s.',
                    $keyColumn,
                    $entry['source_table'],
                ));
            }
        }

        foreach ((array) ($entry['expected_types'] ?? []) as $column => $allowedTypes) {
            $actualType = $types[strtoupper((string) $column)] ?? '';
            $matches = false;
            foreach ((array) $allowedTypes as $allowedType) {
                if ($actualType === strtoupper((string) $allowedType)) {
                    $matches = true;
                    break;
                }
            }
            if (! $matches) {
                throw new RuntimeException(sprintf(
                    'Tipe kolom ARKAS tidak didukung: %s pada tabel %s (%s).',
                    $column,
                    $entry['source_table'],
                    $actualType,
                ));
            }
        }

        $seen = [];
        foreach ($records as $record) {
            $identity = [];
            foreach ($keyColumns as $keyColumn) {
                $value = $this->value($record, $keyColumn);
                if ($value === null || trim((string) $value) === '') {
                    throw new RuntimeException(sprintf(
                        'Identitas key ARKAS kosong: %s pada tabel %s.',
                        implode('+', $keyColumns),
                        $entry['source_table'],
                    ));
                }

                $identity[] = (string) $value;
            }

            $identityKey = json_encode($identity, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            if (isset($seen[$identityKey])) {
                throw new RuntimeException(sprintf(
                    'Duplikat identitas ARKAS pada tabel %s: %s.',
                    $entry['source_table'],
                    implode('+', $identity),
                ));
            }
            $seen[$identityKey] = true;
        }

        return $keyColumns;
    }

    /** @param array<string, mixed> $record */
    private function value(array $record, string $column): mixed
    {
        foreach ($record as $name => $value) {
            if (strcasecmp((string) $name, $column) === 0) {
                return $value;
            }
        }

        return null;
    }
}
