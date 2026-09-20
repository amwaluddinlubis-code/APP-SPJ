<?php

namespace App\Services;

use RuntimeException;

final class ArkasReferenceVersionKey
{
    /** @param array<int, string> $supportedReleases */
    public function __construct(private readonly array $supportedReleases = []) {}

    public function validateRelease(?string $arkasRelease): void
    {
        if ($arkasRelease === null || trim($arkasRelease) === '') {
            throw new RuntimeException('ARKAS release version wajib diisi.');
        }

        if ($this->supportedReleases !== [] && ! in_array($arkasRelease, $this->supportedReleases, true)) {
            throw new RuntimeException('ARKAS release tidak didukung: '.$arkasRelease.'.');
        }
    }

    /** @param array<string, mixed> $entry @param array<string, mixed> $row */
    /** @param array<string, scalar|null> $versionContext */
    public function resolve(array $entry, array $row, ?string $arkasRelease = null, array $versionContext = []): string
    {
        $naturalKey = $this->values($entry, $row, 'canonical_key');
        $version = [];

        foreach ((array) ($entry['version_dimensions'] ?? []) as $dimension) {
            $dimension = (string) $dimension;
            if ($dimension === 'arkas_release') {
                $this->validateRelease($arkasRelease);
                $version[$dimension] = $arkasRelease;

                continue;
            }

            $value = $this->rowValue($row, $dimension) ?? ($versionContext[$dimension] ?? null);
            if ($value === null || trim((string) $value) === '') {
                throw new RuntimeException(sprintf('Version dimension %s kosong pada tabel %s.', $dimension, $entry['source_table']));
            }
            $version[$dimension] = (string) $value;
        }

        return json_encode(['natural_key' => $naturalKey, 'version' => $version], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @param array<string, mixed> $entry @param array<int, array<string, mixed>> $rows @return array<string, string> */
    /** @param array<string, scalar|null> $versionContext */
    public function merge(array $entry, array $rows, ?string $arkasRelease = null, array $versionContext = []): array
    {
        $merged = [];
        foreach ($rows as $row) {
            $identity = $this->resolve($entry, $row, $arkasRelease, $versionContext);
            $semantic = $this->semanticRow($row);
            if (isset($merged[$identity]) && $merged[$identity] !== $semantic) {
                throw new RuntimeException('Konflik semantic reference pada identity '.$identity.'.');
            }
            $merged[$identity] = $semantic;
        }
        ksort($merged, SORT_STRING);

        return $merged;
    }

    /** @param array<string, mixed> $entry @param array<string, mixed> $row @return array<int, string> */
    private function values(array $entry, array $row, string $field): array
    {
        $values = [];
        foreach ((array) ($entry[$field] ?? []) as $column) {
            $value = $this->rowValue($row, (string) $column);
            if ($value === null || trim((string) $value) === '') {
                throw new RuntimeException(sprintf('Natural key %s kosong pada tabel %s.', $column, $entry['source_table']));
            }
            $values[] = (string) $value;
        }

        return $values;
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

    /** @param array<string, mixed> $row */
    private function semanticRow(array $row): string
    {
        $normalized = [];
        foreach ($row as $name => $value) {
            if (in_array(strtolower((string) $name), ['create_date', 'last_update'], true)) {
                continue;
            }
            $normalized[strtolower((string) $name)] = $value === null ? null : (string) $value;
        }
        ksort($normalized);

        return json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
