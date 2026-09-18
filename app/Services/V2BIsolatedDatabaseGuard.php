<?php

namespace App\Services;

use Illuminate\Database\Connection;
use RuntimeException;

final class V2BIsolatedDatabaseGuard
{
    public function assertMigrationTarget(Connection $connection, ?array $manifest = null): void
    {
        $database = $connection->getConfig('database');

        if (! is_string($database) || $database === ':memory:' || $database === '') {
            throw new RuntimeException('V2-B requires an explicit file-backed isolated SQLite target.');
        }

        $target = $this->normalisePath($database);
        $forbidden = [
            $this->normalisePath('D:\\lrvProject\\spj-bosp-data'),
            $this->normalisePath('D:\\lrvProject\\spj-bosp-web-raw\\storage\\app\\school-databases\\10260786'),
            $this->normalisePath('D:\\lrvProject\\spj-bosp-web-raw\\storage\\app\\school-databases\\10208183'),
            $this->normalisePath('D:\\lrvProject\\spj-bosp-web-raw\\storage\\app\\school-databases\\10260756'),
        ];

        foreach ($forbidden as $path) {
            if ($target === $path || str_starts_with($target, $path . '\\')) {
                throw new RuntimeException('V2-B refuses a tenant/original database path: ' . $database);
            }
        }

        $testingDatabase = $this->normalisePath(base_path('database'));
        $isolatedRoot = $this->normalisePath(storage_path('app/v2-b-isolated'));
        $isTestingTarget = $target === $testingDatabase . '\\testing.sqlite'
            || str_starts_with($target, $testingDatabase . '\\');
        $isIsolatedTarget = $target === $isolatedRoot || str_starts_with($target, $isolatedRoot . '\\');

        if (app()->environment('testing') && $isTestingTarget && $manifest === null) {
            return;
        }

        if (! $isIsolatedTarget || ! is_array($manifest)) {
            throw new RuntimeException('V2-B requires an explicit isolated target and identity manifest.');
        }

        $manifestTarget = $manifest['target_path'] ?? null;
        if (! is_string($manifestTarget) || $this->normalisePath($manifestTarget) !== $target) {
            throw new RuntimeException('V2-B target path does not match the explicit identity manifest.');
        }

        if (($manifest['npsn'] ?? null) !== ($manifest['source_identity_npsn'] ?? null)) {
            throw new RuntimeException('V2-B school NPSN and ARKAS source identity do not match.');
        }

        if (! is_int($manifest['source_id']) && ! ctype_digit((string) ($manifest['source_id'] ?? ''))) {
            throw new RuntimeException('V2-B source_id is missing or invalid.');
        }

        $sourcePath = $manifest['source_path'] ?? null;
        if (! is_string($sourcePath) || $this->normalisePath($sourcePath) === $target || ! is_file($sourcePath)) {
            throw new RuntimeException('V2-B source path is missing, equals the target, or does not exist.');
        }

        if (($manifest['source_read_only'] ?? false) !== true || ($manifest['query_only'] ?? false) !== true) {
            throw new RuntimeException('V2-B source connection must be explicitly read-only/query-only.');
        }
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
                throw new RuntimeException('Composite primary key component is missing: ' . $column);
            }
            $ordered[$column] = $row[$column];
        }

        return json_encode($ordered, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function normalisePath(string $path): string
    {
        $path = str_replace('/', '\\', $path);
        $path = rtrim($path, '\\');

        return strtolower($path);
    }
}
