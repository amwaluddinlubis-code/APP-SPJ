<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Schema;

class ArkasImportConfigurationService
{
    private const SETTING_PREFIX = 'arkas.importer.mapping.';

    /** @return array<string, mixed>|null */
    public function get(string $sourceTable): ?array
    {
        if (! Schema::hasTable('app_settings')) {
            return null;
        }

        $value = AppSetting::query()->where('key', self::SETTING_PREFIX.$sourceTable)->value('value');
        $configuration = is_string($value) ? json_decode($value, true) : null;

        return is_array($configuration) ? $configuration : null;
    }

    /** @param array<string, mixed> $configuration */
    public function put(string $sourceTable, array $configuration): void
    {
        if (! Schema::hasTable('app_settings')) {
            return;
        }

        AppSetting::query()->updateOrCreate(
            ['key' => self::SETTING_PREFIX.$sourceTable],
            ['value' => json_encode($configuration, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)],
        );
    }
}
