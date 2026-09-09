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
            foreach ($record as $key => $value) {
                if (strcasecmp((string) $key, $candidate) === 0 && filled($value)) {
                    return (string) $value;
                }
            }
        }

        return hash('sha256', $this->payload($record));
    }

    /** @return array<int, string> */
    private function candidates(?string $configuredColumn): array
    {
        $candidates = array_filter([$configuredColumn, ...self::FALLBACK_COLUMNS], filled(...));

        return array_values(array_unique(array_map(static fn (string $column): string => strtoupper($column), $candidates)));
    }

    /** @param array<string, mixed> $record */
    private function payload(array $record): string
    {
        return json_encode($record, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
