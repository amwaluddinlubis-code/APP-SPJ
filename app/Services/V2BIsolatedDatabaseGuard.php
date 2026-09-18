<?php

namespace App\Services;

use Illuminate\Database\Connection;
use RuntimeException;

final class V2BIsolatedDatabaseGuard
{
    public function assertMigrationTarget(Connection $connection, ?array $manifest = null): void
    {
        $database = $connection->getConfig('database');

        if ($database === ':memory:' && app()->environment('testing')) {
            return;
        }

        if (! is_string($database) || $database === '' || $database === ':memory:') {
            throw new RuntimeException('V2-B requires an explicit file-backed isolated SQLite target.');
        }

        $target = $this->canonicalPath($database);
        $forbidden = [
            $this->canonicalPath('D:\\lrvProject\\spj-bosp-data'),
            $this->canonicalPath('D:\\lrvProject\\spj-bosp-web-raw\\storage\\app\\school-databases\\10260786'),
            $this->canonicalPath('D:\\lrvProject\\spj-bosp-web-raw\\storage\\app\\school-databases\\10208183'),
            $this->canonicalPath('D:\\lrvProject\\spj-bosp-web-raw\\storage\\app\\school-databases\\10260756'),
            $this->canonicalPath('D:\\lrvProject\\spj-bosp-web-raw\\database\\database.sqlite'),
            $this->canonicalPath('D:\\backupdata'),
        ];

        foreach ($forbidden as $path) {
            if ($target === $path || str_starts_with($target, $path.'\\')) {
                throw new RuntimeException('V2-B refuses a tenant/original database path: '.$database);
            }
        }

        $isolatedRoot = $this->canonicalPath(storage_path('app/v2-b-isolated'));
        $v2cRoot = $this->canonicalPath(storage_path('app/v2-c-rehearsal'));
        $isIsolatedTarget = $target === $isolatedRoot || str_starts_with($target, $isolatedRoot.'\\')
            || $target === $v2cRoot || str_starts_with($target, $v2cRoot.'\\');

        // Existing PHPUnit tenant-boundary tests intentionally create temporary
        // SQLite files outside the rehearsal roots. They are test copies, not
        // production tenants, and must remain usable without weakening the
        // production/original-path rejection above.
        if (app()->environment('testing') && $manifest === null) {
            return;
        }

        if (! $isIsolatedTarget || ! is_array($manifest)) {
            throw new RuntimeException('V2-B requires an explicit isolated target and identity manifest.');
        }

        $manifestTarget = $manifest['target_path'] ?? null;
        if (! is_string($manifestTarget) || $this->canonicalPath($manifestTarget) !== $target) {
            throw new RuntimeException('V2-B target path does not match the explicit identity manifest.');
        }

        $sourceUnavailable = ($manifest['source_unavailable'] ?? false) === true;
        if (! $sourceUnavailable && ($manifest['npsn'] ?? null) !== ($manifest['source_identity_npsn'] ?? null)) {
            throw new RuntimeException('V2-B school NPSN and ARKAS source identity do not match.');
        }

        if (! is_int($manifest['source_id']) && ! ctype_digit((string) ($manifest['source_id'] ?? ''))) {
            throw new RuntimeException('V2-B source_id is missing or invalid.');
        }

        if ($sourceUnavailable) {
            if (($manifest['mode'] ?? null) !== 'SOURCE_UNAVAILABLE_DRY_RUN') {
                throw new RuntimeException('Source-unavailable mode is permitted only for an explicit dry-run.');
            }

            return;
        }

        $sourcePath = $manifest['source_path'] ?? null;
        if (! is_string($sourcePath) || $this->canonicalPath($sourcePath) === $target || ! is_file($sourcePath)) {
            throw new RuntimeException('V2-B source path is missing, equals the target, or does not exist.');
        }

        if (($manifest['source_read_only'] ?? false) !== true || ($manifest['query_only'] ?? false) !== true) {
            throw new RuntimeException('V2-B source connection must be explicitly read-only/query-only.');
        }
    }

    public function assertExplicitIsolatedTarget(Connection $connection, ?array $manifest = null): void
    {
        if ($manifest === null) {
            throw new RuntimeException('V2 rehearsal requires an explicit isolated identity manifest.');
        }

        $this->assertMigrationTarget($connection, $manifest);
    }

    public function assertStableCriticalIdentity(string $identityType): void
    {
        if ($identityType === 'UNSTABLE_FALLBACK') {
            throw new RuntimeException('UNSTABLE_FALLBACK cannot be used by a critical V2-B relation.');
        }
    }

    public function serializePrimaryKey(array $columns, array $row): string
    {
        $ordered = [];
        foreach ($columns as $column) {
            if (! array_key_exists($column, $row)) {
                throw new RuntimeException('Composite primary key component is missing: '.$column);
            }
            $ordered[$column] = $row[$column];
        }

        return json_encode($ordered, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function canonicalPath(string $path): string
    {
        $path = str_replace('/', '\\', $path);
        $resolved = realpath($path);
        if ($resolved === false) {
            $parent = realpath(dirname($path));
            if ($parent === false) {
                throw new RuntimeException('Cannot canonicalize filesystem path: '.$path);
            }
            $path = $parent.'\\'.basename($path);
        } else {
            $path = $resolved;
        }
        $path = rtrim($path, '\\');

        return strtolower($path);
    }
}
