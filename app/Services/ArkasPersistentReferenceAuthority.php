<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ArkasPersistentReferenceAuthority
{
    /** @param array<int, array<string, mixed>> $rows @param array<string, scalar|null> $versionContext @return array{accepted: int, quarantined: int} */
    public function promote(string $table, array $rows, string $release, array $versionContext = [], ?string $sourceDump = null): array
    {
        $schema = ArkasReferenceCentralSchema::for($table);
        $accepted = 0;
        $quarantined = 0;
        DB::transaction(function () use ($table, $rows, $release, $versionContext, $sourceDump, $schema, &$accepted, &$quarantined): void {
            foreach ($rows as $row) {
                $invalid = $this->invalidReason($schema, $row);
                if ($invalid !== null) {
                    $this->quarantine($table, 'QUARANTINED_INVALID_ID', $this->contextKey($table, $row, $release, $versionContext), $invalid, $row);
                    $quarantined++;

                    continue;
                }
                $identity = $this->identity($schema, $row, $release, $versionContext);
                $payload = $this->semanticPayload($schema, $row);
                $existing = DB::table('central_reference_rows')->where(['source_table' => $table, 'natural_key' => $identity['natural_key'], 'version_key' => $identity['version_key']])->first();
                if ($existing !== null && (string) $existing->semantic_payload !== $payload) {
                    throw new RuntimeException('Persistent central semantic conflict for '.$table.' '.$identity['natural_key'].' '.$identity['version_key'].'.');
                }
                DB::table('central_reference_rows')->updateOrInsert(['source_table' => $table, 'natural_key' => $identity['natural_key'], 'version_key' => $identity['version_key']], ['arkas_release' => $release, 'semantic_payload' => $payload, 'source_dump' => $sourceDump, 'source_row_key' => $identity['natural_key'], 'updated_at' => now(), 'created_at' => $existing?->created_at ?? now()]);
                $accepted++;
            }
        });

        return compact('accepted', 'quarantined');
    }

    /** @return array<int, array<string, mixed>> */
    public function read(string $table): array
    {
        return DB::table('central_reference_rows')->where('source_table', $table)->orderBy('natural_key')->orderBy('version_key')->get()->map(fn (object $row): array => json_decode((string) $row->semantic_payload, true, 512, JSON_THROW_ON_ERROR))->all();
    }

    /** @param array<int, array<string, mixed>> $rows @return array{accepted: int, quarantined: int} */
    public function promoteCodeVariants(string $tenantKey, array $rows, string $release, ?string $sourceDump = null): array
    {
        $rehearsal = new ArkasReferencePromotionService;
        $report = $rehearsal->promoteCodeVariantsReport($tenantKey, $rows, $release);
        $quarantinedHashes = [];
        foreach ($report['quarantined'] as $entry) {
            $quarantinedHashes[hash('sha256', json_encode($entry['row'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))] = true;
            $this->quarantine('ref_kode', $entry['status'], json_encode([$tenantKey, $release, $entry['row']['id_kode'] ?? null, $entry['row']['tahun'] ?? null, $entry['row']['sumber_dana_id'] ?? null, $entry['row']['bentuk_pendidikan_id'] ?? null], JSON_THROW_ON_ERROR), $entry['reason'], $entry['row']);
        }
        $accepted = 0;
        DB::transaction(function () use ($tenantKey, $rows, $release, $quarantinedHashes, &$accepted): void {
            foreach ($rows as $row) {
                $hash = hash('sha256', json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                if (isset($quarantinedHashes[$hash])) {
                    continue;
                }
                $variantData = ['id_kode' => $row['id_kode'], 'parent_kode' => $row['parent_kode'] ?? null, 'uraian_kode' => $row['uraian_kode'] ?? null, 'id_level_kode' => $row['id_level_kode'] ?? null, 'tipe' => $row['tipe'] ?? null];
                $variantKey = hash('sha256', json_encode($variantData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                DB::table('central_code_variants')->updateOrInsert(['source_code' => (string) $row['id_kode'], 'variant_key' => $variantKey, 'arkas_release' => $release], ['parent_code' => $row['parent_kode'] ?? null, 'description' => $row['uraian_kode'] ?? null, 'level_code' => (string) ($row['id_level_kode'] ?? ''), 'variant_type' => (string) ($row['tipe'] ?? ''), 'updated_at' => now(), 'created_at' => now()]);
                $variantId = DB::table('central_code_variants')->where(['source_code' => (string) $row['id_kode'], 'variant_key' => $variantKey, 'arkas_release' => $release])->value('id');
                $context = ['tenant_key' => $tenantKey, 'source_code' => (string) $row['id_kode'], 'arkas_release' => $release, 'fiscal_year' => (string) $row['tahun'], 'fund_source' => (string) $row['sumber_dana_id'], 'education_level' => (string) $row['bentuk_pendidikan_id']];
                $existing = DB::table('central_code_applicabilities')->where($context)->first();
                if ($existing !== null && (int) $existing->variant_id !== (int) $variantId) {
                    $this->quarantine('ref_kode', 'QUARANTINED_SEMANTIC_CONFLICT', json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'Contradictory semantic definition for an existing applicability context.', $row);

                    continue;
                }
                DB::table('central_code_applicabilities')->updateOrInsert($context, ['variant_id' => $variantId, 'applicability_payload' => json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'updated_at' => now(), 'created_at' => now()]);
                $accepted++;
            }
        });

        return ['accepted' => $accepted, 'quarantined' => count($quarantinedHashes)];
    }

    /** @return array<int, array<string, mixed>> */
    public function readForTenant(string $table, string $tenantKey): array
    {
        if ($table === 'ref_kode') {
            return DB::table('central_code_applicabilities as applicability')
                ->join('central_code_variants as variant', 'variant.id', '=', 'applicability.variant_id')
                ->where('applicability.tenant_key', $tenantKey)
                ->orderBy('applicability.source_code')
                ->get()
                ->map(function (object $row): array {
                    $payload = json_decode((string) $row->applicability_payload, true, 512, JSON_THROW_ON_ERROR);
                    $payload['parent_kode'] ??= $row->parent_code;
                    $payload['uraian_kode'] ??= $row->description;
                    $payload['id_level_kode'] ??= $row->level_code;
                    $payload['tipe'] ??= $row->variant_type;

                    return $payload;
                })->all();
        }

        if ($table === 'ref_sumber_dana') {
            $baseRows = DB::table('central_reference_rows')->where('source_table', $table)->get()->keyBy(fn (object $row): string => $row->natural_key.'|'.$row->version_key);
            $extensionRows = DB::table('central_reference_extensions')->where(['source_table' => $table, 'tenant_key' => $tenantKey])->orderBy('natural_key')->get();

            return $extensionRows->map(function (object $row) use ($baseRows): array {
                $base = $baseRows->get($row->natural_key.'|'.$row->version_key);
                $basePayload = $base === null ? [] : json_decode((string) $base->semantic_payload, true, 512, JSON_THROW_ON_ERROR);

                return array_replace($basePayload, json_decode((string) $row->payload, true, 512, JSON_THROW_ON_ERROR));
            })->all();
        }

        return DB::table('central_reference_extensions')->where(['source_table' => $table, 'tenant_key' => $tenantKey])->orderBy('natural_key')->get()->map(fn (object $row): array => json_decode((string) $row->payload, true, 512, JSON_THROW_ON_ERROR))->all();
    }

    /** @param array<string, mixed> $row */
    public function attachExtension(string $table, string $tenantKey, array $row, string $release, array $versionContext = []): void
    {
        $schema = ArkasReferenceCentralSchema::for($table);
        $identity = $this->identity($schema, $row, $release, $versionContext);
        if (DB::table('central_reference_rows')->where(['source_table' => $table, 'natural_key' => $identity['natural_key'], 'version_key' => $identity['version_key']])->doesntExist()) {
            throw new RuntimeException('Orphan persistent central extension for '.$table.'.');
        }
        DB::table('central_reference_extensions')->updateOrInsert(['source_table' => $table, 'tenant_key' => $tenantKey, 'natural_key' => $identity['natural_key'], 'version_key' => $identity['version_key']], ['payload' => json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'updated_at' => now(), 'created_at' => now()]);
    }

    /** @return array<int, array<string, mixed>> */
    public function quarantines(?string $table = null): array
    {
        return DB::table('central_reference_quarantines')->when($table !== null, fn ($query) => $query->where('source_table', $table))->orderBy('id')->get()->map(fn (object $row): array => ['status' => $row->status, 'context_key' => $row->context_key, 'reason' => $row->reason, 'row' => json_decode((string) $row->row_payload, true, 512, JSON_THROW_ON_ERROR)])->all();
    }

    /** @param array<string, mixed> $schema @param array<string, mixed> $row */
    private function invalidReason(array $schema, array $row): ?string
    {
        foreach ($schema['natural_key'] as $column) {
            $value = $this->value($row, $column);
            if ($value === null || trim((string) $value) === '') {
                return $column.' kosong';
            }
        }

        return null;
    }

    /** @param array<string, mixed> $schema @param array<string, mixed> $row @param array<string, scalar|null> $versionContext @return array{natural_key: string, version_key: string} */
    private function identity(array $schema, array $row, string $release, array $versionContext): array
    {
        $natural = array_map(fn (string $column): string => (string) $this->value($row, $column), $schema['natural_key']);
        $version = [];
        foreach ($schema['version_dimensions'] as $dimension) {
            $version[$dimension] = $dimension === 'arkas_release' ? $release : (string) ($versionContext[$dimension] ?? $this->value($row, $dimension));
        }

        return ['natural_key' => json_encode($natural, JSON_THROW_ON_ERROR), 'version_key' => json_encode($version, JSON_THROW_ON_ERROR)];
    }

    /** @param array<string, mixed> $schema @param array<string, mixed> $row */
    private function semanticPayload(array $schema, array $row): string
    {
        $payload = [];
        foreach ($schema['semantic_columns'] as $column) {
            $payload[$column] = $this->value($row, $column);
        }
        ksort($payload);

        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @param array<string, mixed> $row @param array<string, scalar|null> $versionContext */
    private function contextKey(string $table, array $row, string $release, array $versionContext): string
    {
        return json_encode([$table, $release, $versionContext, $row], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @param array<string, mixed> $row */
    private function value(array $row, string $column): mixed
    {
        foreach ($row as $name => $value) {
            if (strcasecmp((string) $name, $column) === 0) {
                return $value;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $row */
    private function quarantine(string $table, string $status, string $contextKey, string $reason, array $row): void
    {
        $payload = json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        DB::table('central_reference_quarantines')->updateOrInsert(['source_table' => $table, 'status' => $status, 'context_key' => $contextKey, 'payload_hash' => hash('sha256', $payload)], ['reason' => $reason, 'row_payload' => $payload, 'updated_at' => now(), 'created_at' => now()]);
    }
}
