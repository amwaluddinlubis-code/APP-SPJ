<?php

namespace App\Services;

use App\Models\ArkasSource;

class ArkasDatabaseExplorer
{
    public function __construct(private readonly ArkasBridgeClient $bridge) {}

    /** @return array<int, string> */
    public function tables(ArkasSource $source): array
    {
        return collect(ArkasPipePayload::lines($this->bridge->execute($source, 'tables'), 'tables'))
            ->filter(fn (string $line): bool => str_starts_with($line, 'TABLE|'))
            ->map(fn (string $line): string => trim(substr($line, 6)))
            ->filter()
            ->values()
            ->all();
    }

    /** @return array{columns: array<int, array<string, string>>, rows: array<int, array<string, string>>} */
    public function inspect(ArkasSource $source, string $table, int $limit = 25): array
    {
        $schema = $this->bridge->execute($source, 'schema', null, $table);
        $columns = collect(ArkasPipePayload::lines($schema, 'schema:'.$table))
            ->filter(fn (string $line): bool => str_starts_with($line, 'COLUMN|'))
            ->map(function (string $line): array {
                $parts = array_pad(explode('|', $line), 7, '');

                return [
                    'position' => $parts[1],
                    'name' => $parts[2],
                    'type' => $parts[3],
                    'nullable' => $parts[4] === '0' ? 'Tidak' : 'Ya',
                    'primary' => $parts[6] === '1' ? 'Ya' : '—',
                ];
            })
            ->values()
            ->all();

        $rows = ArkasPipePayload::decode(
            $this->bridge->execute($source, 'rows', null, $table, null, min(max($limit, 1), 100)),
            'rows:'.$table,
        );

        return ['columns' => $columns, 'rows' => $rows];
    }
}
