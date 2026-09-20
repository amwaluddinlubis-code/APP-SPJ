<?php

namespace App\Services;

use RuntimeException;

final class ArkasReferenceResolver
{
    public const LEGACY_RAW = 'LEGACY_RAW';

    public const CENTRAL_COMPAT = 'CENTRAL_COMPAT';

    public const CENTRAL_ONLY = 'CENTRAL_ONLY';

    /** @param array<int, array<string, mixed>> $legacyRows @param array<int, array<string, mixed>> $centralRows */
    public function read(string $mode, array $legacyRows, array $centralRows): array
    {
        return match ($mode) {
            self::LEGACY_RAW => $legacyRows,
            self::CENTRAL_COMPAT => $this->compatibilityRead($legacyRows, $centralRows),
            self::CENTRAL_ONLY => $centralRows,
            default => throw new RuntimeException('Unknown ARKAS reference resolver mode: '.$mode.'.'),
        };
    }

    /** @param array<int, array<string, mixed>> $legacyRows @param array<int, array<string, mixed>> $centralRows */
    public function compare(array $legacyRows, array $centralRows): array
    {
        $legacy = $this->normalize($legacyRows);
        $central = $this->normalize($centralRows);

        return ['equal' => $legacy === $central, 'legacy' => $legacy, 'central' => $central];
    }

    /** @param array<int, array<string, mixed>> $legacyRows */
    public function readPersistent(string $mode, ArkasPersistentReferenceAuthority $authority, string $table, array $legacyRows, ?string $tenantKey = null): array
    {
        $centralRows = $tenantKey !== null && in_array($table, ['ref_kode', 'ref_sumber_dana'], true)
            ? $authority->readForTenant($table, $tenantKey)
            : $authority->read($table);

        return $this->read($mode, $legacyRows, $centralRows);
    }

    /** @param array<int, array<string, mixed>> $legacyRows @param array<int, array<string, mixed>> $centralRows @return array<int, array<string, mixed>> */
    private function compatibilityRead(array $legacyRows, array $centralRows): array
    {
        $comparison = $this->compare($legacyRows, $centralRows);
        if (! $comparison['equal']) {
            throw new RuntimeException('ARKAS CENTRAL_COMPAT shadow parity mismatch; read path tetap closed.');
        }

        return $centralRows;
    }

    /** @param array<int, array<string, mixed>> $rows @return array<int, array<string, mixed>> */
    private function normalize(array $rows): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            $item = [];
            foreach ($row as $key => $value) {
                if (in_array(strtolower((string) $key), ['create_date', 'last_update'], true)) {
                    continue;
                }
                $item[strtolower((string) $key)] = $value === null ? null : (string) $value;
            }
            ksort($item);
            $normalized[] = $item;
        }
        usort($normalized, static fn (array $left, array $right): int => strcmp(json_encode($left, JSON_THROW_ON_ERROR), json_encode($right, JSON_THROW_ON_ERROR)));

        return $normalized;
    }
}
