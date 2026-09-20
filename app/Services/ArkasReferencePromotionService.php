<?php

namespace App\Services;

use RuntimeException;

final class ArkasReferencePromotionService
{
    /** @var array<string, array<string, array{natural_key: array<int, string>, version: array<string, string>, data: string}>> */
    private array $central = [];

    /** @var array<string, array<string, array<string, array<string, mixed>>>> */
    private array $extensions = [];

    public function __construct(private readonly ArkasReferenceVersionKey $versionKey = new ArkasReferenceVersionKey) {}

    /** @param array<int, array<string, mixed>> $rows @param array<string, scalar|null> $versionContext */
    public function promote(string $table, array $rows, string $release, array $versionContext = []): int
    {
        $this->versionKey->validateRelease($release);
        $schema = ArkasReferenceCentralSchema::for($table);
        $staged = $this->central[$table] ?? [];
        foreach ($rows as $row) {
            $identity = $this->identity($schema, $row, $release, $versionContext);
            $data = json_encode($this->semanticData($schema, $row), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (isset($staged[$identity]) && $staged[$identity]['data'] !== $data) {
                throw new RuntimeException('Central reference semantic conflict for '.$table.' identity '.$identity.' existing='.json_encode($staged[$identity]['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).' incoming='.json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).'.');
            }
            $staged[$identity] = ['natural_key' => $this->values($schema['natural_key'], $row), 'version' => $this->version($schema, $row, $release, $versionContext), 'data' => $data];
        }
        $this->central[$table] = $staged;

        return count($rows);
    }

    /** @param array<int, array<string, mixed>> $rows @param array<string, scalar|null> $versionContext */
    public function attachTenantExtension(string $table, string $tenantId, array $rows, string $release, array $versionContext = []): int
    {
        $this->versionKey->validateRelease($release);
        $entry = (new ArkasMirrorManifest)->entry($table);
        if ($entry['classification'] !== 'HYBRID_CENTRAL_BASE_TENANT_EXTENSION') {
            throw new RuntimeException('Tenant extension is only valid for hybrid reference: '.$table.'.');
        }

        $schema = ArkasReferenceCentralSchema::for($table);
        foreach ($rows as $row) {
            $baseIdentity = $this->identity($schema, $row, $release, []);
            if (! isset($this->central[$table][$baseIdentity])) {
                throw new RuntimeException('Orphan tenant extension for '.$table.' identity '.$baseIdentity.'.');
            }
            $extensionIdentity = json_encode([$tenantId, $this->versionKey->resolve($entry, $row, $release, $versionContext)], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $this->extensions[$table][$tenantId][$extensionIdentity] = $this->extensionData($schema, $row) + ['tenant_id' => $tenantId];
        }

        return count($rows);
    }

    /** @return array<int, array<string, mixed>> */
    public function readCentral(string $table): array
    {
        $rows = $this->central[$table] ?? [];
        ksort($rows, SORT_STRING);

        return array_map(static fn (array $row): array => array_replace($row, ['data' => json_decode($row['data'], true, 512, JSON_THROW_ON_ERROR)]), array_values($rows));
    }

    /** @return array<int, array<string, mixed>> */
    public function readTenantExtension(string $table, string $tenantId): array
    {
        $rows = $this->extensions[$table][$tenantId] ?? [];
        ksort($rows, SORT_STRING);

        return array_values($rows);
    }

    /** @return array<string, array<string, mixed>> */
    public function snapshot(): array
    {
        return ['central' => $this->central, 'extensions' => $this->extensions];
    }

    /** @param array<string, mixed> $schema @param array<string, mixed> $row @param array<string, scalar|null> $versionContext */
    private function identity(array $schema, array $row, string $release, array $versionContext): string
    {
        return json_encode(['natural_key' => $this->values($schema['natural_key'], $row), 'version' => $this->version($schema, $row, $release, $versionContext)], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @param array<int, string> $columns @param array<string, mixed> $row @return array<int, string> */
    private function values(array $columns, array $row): array
    {
        return array_map(function (string $column) use ($row): string {
            foreach ($row as $name => $value) {
                if (strcasecmp($name, $column) === 0 && $value !== null && trim((string) $value) !== '') {
                    return (string) $value;
                }
            }
            throw new RuntimeException('Central reference key '.$column.' kosong pada row '.json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).'.');
        }, $columns);
    }

    /** @param array<string, mixed> $schema @param array<string, mixed> $row @param array<string, scalar|null> $versionContext @return array<string, string> */
    private function version(array $schema, array $row, string $release, array $versionContext): array
    {
        $version = [];
        foreach ($schema['version_dimensions'] as $dimension) {
            if ($dimension === 'arkas_release') {
                $version[$dimension] = $release;

                continue;
            }
            $value = $versionContext[$dimension] ?? null;
            foreach ($row as $name => $rowValue) {
                if (strcasecmp($name, $dimension) === 0) {
                    $value = $rowValue;
                    break;
                }
            }
            if ($value === null || trim((string) $value) === '') {
                throw new RuntimeException('Central reference version dimension '.$dimension.' kosong.');
            }
            $version[$dimension] = (string) $value;
        }

        return $version;
    }

    /** @param array<string, mixed> $schema @param array<string, mixed> $row @return array<string, mixed> */
    private function semanticData(array $schema, array $row): array
    {
        $data = [];
        foreach ($schema['semantic_columns'] as $column) {
            foreach ($row as $name => $value) {
                if (strcasecmp($name, $column) === 0) {
                    $data[$column] = $value;
                    break;
                }
            }
        }
        ksort($data);

        return $data;
    }

    /** @param array<string, mixed> $schema @param array<string, mixed> $row @return array<string, mixed> */
    private function extensionData(array $schema, array $row): array
    {
        $columns = $schema['extension_columns'] ?? $schema['semantic_columns'];
        $data = [];
        foreach ($columns as $column) {
            foreach ($row as $name => $value) {
                if (strcasecmp($name, $column) === 0) {
                    $data[$column] = $value;
                    break;
                }
            }
        }
        ksort($data);

        return $data;
    }
}
