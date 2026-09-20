<?php

namespace App\Services;

use Illuminate\Support\Collection;

final class ArkasReferenceReadBoundary
{
    public function __construct(
        private readonly ArkasReferenceResolver $resolver,
        private readonly ArkasPersistentReferenceAuthority $authority,
    ) {}

    /** @param Collection<int, array<string, mixed>>|null $legacyRows @param array<string, scalar|null> $context @return Collection<int, array<string, mixed>>|null */
    public function resolve(string $table, ?Collection $legacyRows, ?string $tenantKey = null, array $context = []): ?Collection
    {
        $mode = (string) config('arkas.reference_read_mode', ArkasReferenceResolver::CENTRAL_COMPAT);
        if ($mode === ArkasReferenceResolver::LEGACY_RAW) {
            return $legacyRows;
        }

        if ($legacyRows === null) {
            $centralRows = $tenantKey !== null && in_array($table, ['ref_kode', 'ref_sumber_dana'], true)
                ? $this->authority->readForTenant($table, $tenantKey, $context)
                : $this->authority->read($table, $context);

            return $centralRows === []
                ? null
                : collect(array_map(static fn (array $row): array => array_change_key_case($row, CASE_UPPER), $centralRows));
        }

        return collect($this->resolver->readPersistent($mode, $this->authority, $table, $legacyRows->all(), $tenantKey, $context));
    }

    /** @param array<string, scalar|null> $context @return Collection<int, array<string, mixed>>|null */
    public function resolveCentral(string $table, ?string $tenantKey = null, array $context = []): ?Collection
    {
        $rows = $tenantKey !== null && in_array($table, ['ref_kode', 'ref_sumber_dana'], true)
            ? $this->authority->readForTenant($table, $tenantKey, $context)
            : $this->authority->read($table, $context);

        return $rows === []
            ? null
            : collect(array_map(static fn (array $row): array => array_change_key_case($row, CASE_UPPER), $rows));
    }
}
