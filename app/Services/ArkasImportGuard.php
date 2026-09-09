<?php

namespace App\Services;

use App\Models\ArkasImportProfile;

class ArkasImportGuard
{
    /** @param array<string, string> $mapping @return array<int, string> */
    public function configurationErrors(
        string $sourceTable,
        string $targetDomain,
        string $syncMode,
        ?string $sourceKeyColumn,
        ?string $sourceUpdatedColumn,
        array $mapping,
    ): array {
        $errors = [];
        $preset = ArkasDomainAdapter::presetFor($sourceTable);
        $effectiveSourceKey = filled($sourceKeyColumn) ? $sourceKeyColumn : $preset['source_key_column'];

        if ($syncMode === 'incremental' && blank($sourceUpdatedColumn)) {
            $errors[] = 'Mode Incremental memerlukan kolom terakhir berubah.';
        }

        if ($targetDomain === 'raw' && $syncMode !== 'full_refresh' && blank($effectiveSourceKey)) {
            $errors[] = 'Target raw pada mode Upsert/Incremental memerlukan source key stabil. Tanpa source key stabil gunakan Full Refresh.';
        }

        if (filled($sourceKeyColumn) && ($mapping[$sourceKeyColumn] ?? null) !== 'source_key') {
            $errors[] = 'Kolom kunci sumber harus ikut dipetakan sebagai Kunci.';
        }

        return $errors;
    }

    /** @param array<int, string> $currentColumns @return array<int, string> */
    public function schemaErrors(ArkasImportProfile $profile, array $currentColumns): array
    {
        $available = [];
        foreach ($currentColumns as $column) {
            $available[mb_strtolower(trim($column))] = true;
        }

        $required = [];
        foreach ($profile->mapping ?? [] as $column => $role) {
            if ($role !== 'ignore' && filled($role)) {
                $required[(string) $column] = 'mapped';
            }
        }

        $preset = ArkasDomainAdapter::presetFor($profile->source_table);
        $effectiveSourceKey = $profile->source_key_column ?: $preset['source_key_column'];
        if (filled($effectiveSourceKey)) {
            $required[(string) $effectiveSourceKey] = 'source_key';
        }
        if ($profile->sync_mode === 'incremental' && filled($profile->source_updated_column)) {
            $required[(string) $profile->source_updated_column] = 'updated';
        }
        if (filled($profile->year_column)) {
            $required[(string) $profile->year_column] = 'year';
        }
        if (filled($profile->fund_source_column)) {
            $required[(string) $profile->fund_source_column] = 'fund_source';
        }

        $missing = [];
        foreach (array_keys($required) as $column) {
            if (! isset($available[mb_strtolower(trim($column))])) {
                $missing[] = $column;
            }
        }

        if ($missing === []) {
            return [];
        }

        return ['Schema ARKAS berubah. Kolom yang masih dipakai mapping/import sudah hilang: '.implode(', ', $missing).'. Perbaiki mapping sebelum sinkronisasi.'];
    }
}
