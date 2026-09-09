<?php

namespace App\Services;

use App\Models\ArkasSource;

final class ArkasTenantLockKey
{
    public static function import(ArkasSource $source, int $profileId, int $fiscalYearId): string
    {
        return sprintf(
            'arkas-import:%s:profile:%d:year:%d',
            self::tenantIdentity($source),
            $profileId,
            $fiscalYearId,
        );
    }

    public static function staging(ArkasSource $source, string $sourceTable, int $fiscalYearId): string
    {
        return sprintf(
            'arkas-staging:%s:table:%s:year:%d',
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
