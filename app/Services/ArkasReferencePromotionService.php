<?php

namespace App\Services;

use RuntimeException;

final class ArkasReferencePromotionService
{
    /** @var array<string, array<string, array{natural_key: array<int, string>, version: array<string, string>, data: string}>> */
    private array $central = [];

    /** @var array<string, array<string, array<string, array<string, mixed>>>> */
    private array $extensions = [];

    /** @var array<string, array{variant_key: string, data: array<string, mixed>, release: string}> */
    private array $codeVariants = [];

    /** @var array<string, array<string, mixed>> */
    private array $codeApplicability = [];

    public function __construct(private readonly ArkasReferenceVersionKey $versionKey = new ArkasReferenceVersionKey) {}

    /** @param array<int, array<string, mixed>> $rows @param array<string, scalar|null> $versionContext */
    public function promote(string $table, array $rows, string $release, array $versionContext = []): int
    {
        $this->versionKey->validateRelease($release);

        if ($table === 'ref_acuan_barang') {
            return $this->promoteReport($table, $rows, $release, $versionContext)['accepted'];
        }

        if ($table === 'ref_kode') {
            throw new RuntimeException('ref_kode wajib dipromosikan melalui promoteCodeVariants() agar semantic variant dan applicability tidak ambigu.');
        }

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

    /** @param array<int, array<string, mixed>> $rows @param array<string, scalar|null> $versionContext @return array{accepted: int, quarantined: array<int, array{status: string, row: array<string, mixed>, reason: string}>, diagnostics: array{accepted: int, quarantined: int}} */
    public function promoteReport(string $table, array $rows, string $release, array $versionContext = []): array
    {
        $this->versionKey->validateRelease($release);
        $schema = ArkasReferenceCentralSchema::for($table);
        $quarantined = [];
        $valid = [];
        foreach ($rows as $row) {
            $reason = $this->invalidIdentityReason($schema, $row);
            if ($reason !== null) {
                $quarantined[] = ['status' => 'QUARANTINED_INVALID_ID', 'row' => $row, 'reason' => $reason];

                continue;
            }
            $valid[] = $row;
        }
        $accepted = $this->promoteRows($table, $valid, $release, $versionContext);

        return ['accepted' => $accepted, 'quarantined' => $quarantined, 'diagnostics' => ['accepted' => $accepted, 'quarantined' => count($quarantined)]];
    }

    /** @param array<int, array<string, mixed>> $rows @return array{accepted: int, variant_count: int, applicability_count: int} */
    public function promoteCodeVariants(string $tenantId, array $rows, string $release): array
    {
        $report = $this->promoteCodeVariantsReport($tenantId, $rows, $release);

        return ['accepted' => $report['accepted'], 'variant_count' => count($this->codeVariants), 'applicability_count' => count($this->codeApplicability)];
    }

    /** @param array<int, array<string, mixed>> $rows @return array{accepted: int, quarantined: array<int, array{status: string, row: array<string, mixed>, reason: string}>, diagnostics: array{accepted: int, quarantined: int}} */
    public function promoteCodeVariantsReport(string $tenantId, array $rows, string $release): array
    {
        $this->versionKey->validateRelease($release);
        $schema = ArkasReferenceCentralSchema::for('ref_kode');
        $stagedVariants = $this->codeVariants;
        $stagedApplicability = $this->codeApplicability;
        $quarantined = [];
        $groups = [];
        $accepted = 0;
        foreach ($rows as $row) {
            try {
                $this->values($schema['natural_key'], $row);
                $applicability = $this->codeApplicabilityData($tenantId, $row, $release, 'pending');
                $contextIdentity = $this->codeContextIdentity($tenantId, $row, $release, $applicability);
                $variantData = $this->semanticData($schema, $row);
                $variantKey = $this->semanticVariantKey($variantData);
                $groups[$contextIdentity][] = ['row' => $row, 'variant_data' => $variantData, 'variant_key' => $variantKey, 'applicability' => $applicability];
            } catch (RuntimeException $exception) {
                $quarantined[] = ['status' => 'QUARANTINED_INVALID_CONTEXT', 'row' => $row, 'reason' => $exception->getMessage()];
            }
        }

        foreach ($groups as $contextIdentity => $group) {
            $variantKeys = array_values(array_unique(array_column($group, 'variant_key')));
            $existing = $stagedApplicability[$contextIdentity]['variant_key'] ?? null;
            if (count($variantKeys) > 1 || ($existing !== null && $existing !== $variantKeys[0])) {
                foreach ($group as $entry) {
                    $quarantined[] = ['status' => 'QUARANTINED_SEMANTIC_CONFLICT', 'row' => $entry['row'], 'reason' => 'Contradictory semantic definition for same applicability context '.$contextIdentity.'.'];
                }

                continue;
            }
            $accepted++;
            $entry = $group[0];
            $variantIdentity = json_encode([$release, (string) $entry['row']['id_kode'], $entry['variant_key']], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $stagedVariants[$variantIdentity] = ['variant_key' => $entry['variant_key'], 'data' => $entry['variant_data'], 'release' => $release];
            $stagedApplicability[$contextIdentity] = $entry['applicability'] + ['variant_key' => $entry['variant_key'], 'context_identity' => $contextIdentity];
        }
        $this->codeVariants = $stagedVariants;
        $this->codeApplicability = $stagedApplicability;

        return ['accepted' => $accepted, 'quarantined' => $quarantined, 'diagnostics' => ['accepted' => $accepted, 'quarantined' => count($quarantined)]];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    public function attachCodeApplicability(string $tenantId, array $row, string $release, string $variantKey): array
    {
        $variantIdentity = json_encode([$release, (string) ($row['id_kode'] ?? ''), $variantKey], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! isset($this->codeVariants[$variantIdentity])) {
            throw new RuntimeException('Orphan ref_kode applicability for '.$variantIdentity.'.');
        }
        $applicability = $this->codeApplicabilityData($tenantId, $row, $release, $variantKey);
        $contextIdentity = $this->codeContextIdentity($tenantId, $row, $release, $applicability);
        foreach ($this->codeApplicability as $existing) {
            if ($existing['context_identity'] === $contextIdentity && $existing['variant_key'] !== $variantKey) {
                throw new RuntimeException('ref_kode contradictory semantic definition for same applicability context '.$contextIdentity.'.');
            }
        }
        $this->codeApplicability[$contextIdentity] = $applicability + ['context_identity' => $contextIdentity];

        return $this->codeApplicability[$contextIdentity];
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
        if ($table === 'ref_kode') {
            $rows = $this->codeVariants;
            ksort($rows, SORT_STRING);

            return array_values($rows);
        }
        $rows = $this->central[$table] ?? [];
        ksort($rows, SORT_STRING);

        return array_map(static fn (array $row): array => array_replace($row, ['data' => json_decode($row['data'], true, 512, JSON_THROW_ON_ERROR)]), array_values($rows));
    }

    /** @return array<int, array<string, mixed>> */
    public function readTenantExtension(string $table, string $tenantId): array
    {
        if ($table === 'ref_kode') {
            $rows = array_filter($this->codeApplicability, static fn (array $row): bool => $row['tenant_id'] === $tenantId);
            ksort($rows, SORT_STRING);

            return array_values($rows);
        }
        $rows = $this->extensions[$table][$tenantId] ?? [];
        ksort($rows, SORT_STRING);

        return array_values($rows);
    }

    /** @return array<string, array<string, mixed>> */
    public function snapshot(): array
    {
        return ['central' => $this->central, 'extensions' => $this->extensions, 'code_variants' => $this->codeVariants, 'code_applicability' => $this->codeApplicability];
    }

    /** @param array<string, mixed> $schema @param array<string, mixed> $row @param array<string, scalar|null> $versionContext */
    private function identity(array $schema, array $row, string $release, array $versionContext): string
    {
        return json_encode(['natural_key' => $this->values($schema['natural_key'], $row), 'version' => $this->version($schema, $row, $release, $versionContext)], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @param array<string, mixed> $schema @param array<string, mixed> $row */
    private function invalidIdentityReason(array $schema, array $row): ?string
    {
        foreach ($schema['natural_key'] as $column) {
            $value = $this->rowValue($row, $column);
            if ($value === null || trim((string) $value) === '') {
                return $column.' kosong';
            }
            if (preg_match('/[[:cntrl:]]/', (string) $value) === 1) {
                return $column.' malformed: contains control character';
            }
        }

        return null;
    }

    /** @param array<string, mixed> $schema @param array<int, array<string, mixed>> $rows @param array<string, scalar|null> $versionContext */
    private function promoteRows(string $table, array $rows, string $release, array $versionContext): int
    {
        if ($rows === []) {
            return 0;
        }
        $schema = ArkasReferenceCentralSchema::for($table);
        $staged = $this->central[$table] ?? [];
        foreach ($rows as $row) {
            $identity = $this->identity($schema, $row, $release, $versionContext);
            $data = json_encode($this->semanticData($schema, $row), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (isset($staged[$identity]) && $staged[$identity]['data'] !== $data) {
                throw new RuntimeException('Central reference semantic conflict for '.$table.' identity '.$identity.'.');
            }
            $staged[$identity] = ['natural_key' => $this->values($schema['natural_key'], $row), 'version' => $this->version($schema, $row, $release, $versionContext), 'data' => $data];
        }
        $this->central[$table] = $staged;

        return count($rows);
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

    /** @param array<string, mixed> $data */
    private function semanticVariantKey(array $data): string
    {
        ksort($data);

        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function codeApplicabilityData(string $tenantId, array $row, string $release, string $variantKey): array
    {
        foreach (['tahun', 'sumber_dana_id', 'bentuk_pendidikan_id'] as $dimension) {
            if ($this->rowValue($row, $dimension) === null || trim((string) $this->rowValue($row, $dimension)) === '') {
                throw new RuntimeException('ref_kode applicability dimension '.$dimension.' kosong.');
            }
        }

        return ['tenant_id' => $tenantId, 'release' => $release, 'source_id' => (string) $row['id_kode'], 'variant_key' => $variantKey, 'tahun' => (string) $row['tahun'], 'sumber_dana_id' => (string) $row['sumber_dana_id'], 'bentuk_pendidikan_id' => (string) $row['bentuk_pendidikan_id']];
    }

    /** @param array<string, mixed> $row @param array<string, mixed> $applicability */
    private function codeContextIdentity(string $tenantId, array $row, string $release, array $applicability): string
    {
        return json_encode([$tenantId, $release, $row['id_kode'], $applicability['tahun'], $applicability['sumber_dana_id'], $applicability['bentuk_pendidikan_id']], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @param array<string, mixed> $row */
    private function rowValue(array $row, string $column): mixed
    {
        foreach ($row as $name => $value) {
            if (strcasecmp((string) $name, $column) === 0) {
                return $value;
            }
        }

        return null;
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
