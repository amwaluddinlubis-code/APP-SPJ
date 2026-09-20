<?php

namespace App\Services;

use Illuminate\Support\Collection;

final class ArkasReferenceReadBoundary
{
    public function __construct(
        private readonly ArkasReferenceResolver $resolver,
        private readonly ArkasPersistentReferenceAuthority $authority,
    ) {}

    /** @param Collection<int, array<string, mixed>>|null $legacyRows @return Collection<int, array<string, mixed>>|null */
    public function resolve(string $table, ?Collection $legacyRows, ?string $tenantKey = null): ?Collection
    {
        if ($legacyRows === null) {
            return null;
        }

        $mode = (string) config('arkas.reference_read_mode', env('ARKAS_REFERENCE_READ_MODE', ArkasReferenceResolver::LEGACY_RAW));
        if ($mode === ArkasReferenceResolver::LEGACY_RAW) {
            return $legacyRows;
        }

        return collect($this->resolver->readPersistent($mode, $this->authority, $table, $legacyRows->all(), $tenantKey));
    }
}
