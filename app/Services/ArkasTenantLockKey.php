<?php

namespace App\Services;

use App\Models\ArkasSource;

final class ArkasTenantLockKey
{
    public static function import(ArkasSource $source, string $sourceTable, int $fiscalYearId): string
    {
        return self::resource($source, $sourceTable, $fiscalYearId);
    }

    public static function staging(ArkasSource $source, string $sourceTable, int $fiscalYearId): string
    {
        return self::resource($source, $sourceTable, $fiscalYearId);
    }

    private static function resource(ArkasSource $source, string $sourceTable, int $fiscalYearId): string
    {
        return sprintf(
            'arkas-sync:%s:table:%s:year:%d',
            self::tenantIdentity($source),
            $sourceTable,
            $fiscalYearId,
        );
    }

    private static function tenantIdentity(ArkasSource $source): string
    {
        if ($source->school_id) {
            return 'school:'.$source->school_id;
        }

        $tenantDatabase = trim((string) config('database.connections.school.database'));
        if ($tenantDatabase === '') {
            throw new \RuntimeException('Identitas tenant tidak tersedia untuk membentuk lock ARKAS.');
        }

        return 'school-db:'.hash('sha256', $tenantDatabase);
    }
}
