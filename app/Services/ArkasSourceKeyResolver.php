<?php

namespace App\Services;

class ArkasSourceKeyResolver
{
    /** @var array<int, string> */
    private const FALLBACK_COLUMNS = [
        'ID_REF_KODE',
        'ID_RAPBS',
        'ID_KAS_NOTA_PAJAK',
        'ID_KAS_NOTA',
        'ID_KAS_UMUM',
        'ID_LEVEL_KODE',
        'ID_REKENING',
        'ID_KODE',
        'ID_PERIODE',
        'ID',
    ];

    /** @param array<string, mixed> $record */
    public function resolve(array $record, ?string $configuredColumn = null): string
    {
        foreach ($this->candidates($configuredColumn) as $candidate) {
            $value = $this->valueForColumn($record, $candidate);
            if (filled($value)) {
                return (string) $value;
            }
        }

        return hash('sha256', $this->payload($record));
    }

    /**
     * Resolve a row from the table's actual primary-key columns.
     *
     * @param array<string, mixed> $record
     * @param array<int, string> $columns
     */
    public function resolveFromColumns(array $record, array $columns): string
    {
        $columns = array_values(array_unique(array_filter(
            array_map(static fn (string $column): string => strtoupper(trim($column)), $columns),
            filled(...),
        )));

        if ($columns === []) {
            return $this->resolve($record);
        }

        $identity = [];
        foreach ($columns as $column) {
            $value = $this->valueForColumn($record, $column);
            if (! filled($value)) {
                return hash('sha256', $this->payload($record));
            }

            $identity[$column] = (string) $value;
        }

        if (count($identity) === 1) {
            return (string) reset($identity);
        }

        return hash('sha256', json_encode(
            $identity,
            JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE,
        ));
    }

    /** @return array<int, string> */
    private function candidates(?string $configuredColumn): array
    {
        $candidates = array_filter([$configuredColumn, ...self::FALLBACK_COLUMNS], filled(...));

        return array_values(array_unique(array_map(static fn (string $column): string => strtoupper($column), $candidates)));
    }

    /** @param array<string, mixed> $record */
    private function valueForColumn(array $record, string $column): mixed
    {
        foreach ($record as $key => $value) {
            if (strcasecmp((string) $key, $column) === 0) {
                return $value;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $record */
    private function payload(array $record): string
    {
        return json_encode($record, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
