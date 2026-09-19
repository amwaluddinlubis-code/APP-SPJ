<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use RuntimeException;

final class SpjV2CanonicalReadService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function forContext(
        Connection $db,
        int $fiscalYearId,
        int $fundSourceId,
        int $sourceId,
    ): Collection {
        $this->assertSchema($db);

        $transactions = $db->table('spj_transactions')
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('fund_source_id', $fundSourceId)
            ->where('source_id', $sourceId)
            ->where('canonical_context_status', 'ACTIVE_CANONICAL')
            ->whereIn('source_status', ['ACTIVE', 'SOURCE_MISSING'])
            ->orderBy('id')
            ->get();

        return $this->hydrate($db, $transactions, $sourceId);
    }

    /**
     * Resolve either the canonical membership hash or a legacy source key.
     *
     * @return array<string, mixed>|null
     */
    public function findBySourceIdentifier(
        Connection $db,
        int $fiscalYearId,
        int $fundSourceId,
        int $sourceId,
        string $sourceIdentifier,
    ): ?array {
        $this->assertSchema($db);

        $sourceIdentifier = trim($sourceIdentifier);
        if ($sourceIdentifier === '') {
            return null;
        }

        $transaction = $db->table('spj_transactions')
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('fund_source_id', $fundSourceId)
            ->where('source_id', $sourceId)
            ->where('canonical_context_status', 'ACTIVE_CANONICAL')
            ->where('source_membership_hash', $sourceIdentifier)
            ->first();

        if ($transaction === null) {
            $transaction = $db->table('legacy_transaction_v2_map as provenance')
                ->join('spj_transactions as transactions', 'transactions.id', '=', 'provenance.spj_transaction_id')
                ->where('transactions.fiscal_year_id', $fiscalYearId)
                ->where('transactions.fund_source_id', $fundSourceId)
                ->where('transactions.source_id', $sourceId)
                ->where('transactions.canonical_context_status', 'ACTIVE_CANONICAL')
                ->where('provenance.legacy_source_key', $sourceIdentifier)
                ->select('transactions.*')
                ->first();
        }

        if ($transaction === null) {
            return null;
        }

        return $this->hydrate($db, collect([$transaction]), $sourceId)->first();
    }

    /**
     * @param Collection<int, object> $transactions
     * @return Collection<int, array<string, mixed>>
     */
    private function hydrate(Connection $db, Collection $transactions, int $sourceId): Collection
    {
        if ($transactions->isEmpty()) {
            return collect();
        }

        $transactionIds = $transactions->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $overlays = $db->table('spj_transaction_overlays')
            ->whereIn('spj_transaction_id', $transactionIds)
            ->get()
            ->keyBy('spj_transaction_id');

        $provenance = $db->table('legacy_transaction_v2_map')
            ->whereIn('spj_transaction_id', $transactionIds)
            ->orderBy('legacy_transaction_id')
            ->get()
            ->groupBy('spj_transaction_id');

        $sourceRows = $db->table('spj_transaction_sources as source_link')
            ->join('arkas_source_identity_registry as identity', 'identity.id', '=', 'source_link.arkas_source_identity_id')
            ->leftJoin('arkas_raw_mirror_rows as raw', 'raw.id', '=', 'identity.current_raw_mirror_row_id')
            ->leftJoin('spj_item_overlays as item_overlay', 'item_overlay.spj_transaction_source_id', '=', 'source_link.id')
            ->whereIn('source_link.spj_transaction_id', $transactionIds)
            ->orderBy('source_link.spj_transaction_id')
            ->orderBy('source_link.sort_order')
            ->orderBy('source_link.id')
            ->get([
                'source_link.id as source_link_id',
                'source_link.spj_transaction_id',
                'source_link.sort_order',
                'identity.source_key',
                'identity.source_status as identity_source_status',
                'raw.payload',
                'item_overlay.item_description',
                'item_overlay.operator_metadata as item_operator_metadata',
            ])
            ->groupBy('spj_transaction_id');

        $allSourceKeys = $sourceRows->flatten(1)
            ->pluck('source_key')
            ->map(fn ($key): string => trim((string) $key))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $taxByParent = $this->taxBreakdownByParent($db, $sourceId, $allSourceKeys);
        $notaById = $this->notaById($db, $sourceId);

        return $transactions->map(function (object $transaction) use ($overlays, $provenance, $sourceRows, $taxByParent, $notaById): array {
            $items = collect($sourceRows->get($transaction->id, collect()))
                ->map(function (object $row): array {
                    $payload = json_decode((string) ($row->payload ?? ''), true);

                    return [
                        'source_link_id' => (int) $row->source_link_id,
                        'source_key' => trim((string) $row->source_key),
                        'sort_order' => (int) $row->sort_order,
                        'source_status' => (string) $row->identity_source_status,
                        'payload' => is_array($payload) ? $payload : [],
                        'item_description' => $this->normalize($row->item_description),
                        'operator_metadata' => $this->decodeMetadata($row->item_operator_metadata),
                    ];
                })
                ->values();

            $overlay = $overlays->get($transaction->id);
            $legacyMaps = collect($provenance->get($transaction->id, collect()));

            $gross = (float) $items->sum(function (array $item): float {
                $payload = $item['payload'];
                $ref = (int) ($payload['id_ref_bku'] ?? 0);
                if (! in_array($ref, [4, 15, 24, 35], true) || (int) ($payload['soft_delete'] ?? 0) === 1) {
                    return 0.0;
                }

                return $this->amount($payload);
            });

            $taxes = ['ppn' => 0.0, 'pph21' => 0.0, 'pph22' => 0.0, 'pph23' => 0.0, 'pph4' => 0.0, 'sspd' => 0.0];
            foreach ($items as $item) {
                $payload = $item['payload'];
                $parentKey = trim((string) ($payload['id_kas_umum'] ?? $item['source_key']));
                foreach (array_keys($taxes) as $taxField) {
                    $taxes[$taxField] += (float) ($taxByParent[$parentKey][$taxField] ?? 0.0);
                }
            }
            $tax = (float) array_sum($taxes);

            $notaId = $this->firstSourceValue($items, ['id_kas_nota']);
            $recipient = $this->firstSourceValue($items, ['nama_penerima', 'penerima', 'recipient_name']);
            if ($recipient === null && $notaId !== null) {
                $recipient = $this->normalize($notaById[$notaId] ?? null);
            }

            return [
                'id' => (int) $transaction->id,
                'fiscal_year_id' => (int) $transaction->fiscal_year_id,
                'fund_source_id' => (int) $transaction->fund_source_id,
                'source_id' => (int) $transaction->source_id,
                'source_membership_hash' => (string) $transaction->source_membership_hash,
                'source_status' => (string) $transaction->source_status,
                'requires_reconciliation' => (bool) $transaction->requires_reconciliation,
                'canonical_context_status' => (string) $transaction->canonical_context_status,
                'canonical_context_reason' => $this->normalize($transaction->canonical_context_reason ?? null),
                'legacy_source_keys' => $legacyMaps->pluck('legacy_source_key')
                    ->map(fn ($value): ?string => $this->normalize($value))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
                'legacy_provenance' => $legacyMaps->map(fn (object $map): array => [
                    'legacy_transaction_id' => (int) $map->legacy_transaction_id,
                    'legacy_source_key' => $this->normalize($map->legacy_source_key ?? null),
                    'mapping_status' => (string) $map->mapping_status,
                    'canonical_context_status' => (string) ($map->canonical_context_status ?? ''),
                ])->values()->all(),
                'no_bukti' => $this->firstSourceValue($items, ['no_bukti', 'nomor_bukti']),
                'transaction_date' => $this->firstSourceValue($items, ['tanggal_transaksi', 'tanggal']),
                'description' => $this->firstSourceValue($items, ['uraian', 'description']),
                'account_code' => $this->firstSourceValue($items, ['kode_rekening', 'account_code']),
                'activity_code' => $this->firstSourceValue($items, ['kode_kegiatan', 'activity_code']),
                'recipient_name' => $recipient,
                'gross_amount' => $gross,
                'ppn' => $taxes['ppn'],
                'pph21' => $taxes['pph21'],
                'pph22' => $taxes['pph22'],
                'pph23' => $taxes['pph23'],
                'pph4' => $taxes['pph4'],
                'sspd' => $taxes['sspd'],
                'tax_total' => $tax,
                'net_amount' => $gross - $tax,
                'overlay' => [
                    'spj_category' => $this->normalize($overlay?->spj_category ?? null),
                    'payment_description' => $this->normalize($overlay?->payment_description ?? null),
                    'payment_method' => $this->normalize($overlay?->payment_method ?? null),
                    'payment_reference' => $this->normalize($overlay?->payment_reference ?? null),
                    'receipt_recipient_name' => $this->normalize($overlay?->receipt_recipient_name ?? null),
                    'operator_metadata' => $this->decodeMetadata($overlay?->operator_metadata ?? null),
                ],
                'items' => $items->all(),
            ];
        })->values();
    }

    /**
     * @param array<int, string> $sourceKeys
     * @return array<string, array{ppn: float, pph21: float, pph22: float, pph23: float, pph4: float, sspd: float}>
     */
    private function taxBreakdownByParent(Connection $db, int $sourceId, array $sourceKeys): array
    {
        if ($sourceKeys === []) {
            return [];
        }

        $wanted = array_fill_keys($sourceKeys, true);
        $taxes = [];

        foreach ($db->table('arkas_raw_mirror_rows as raw')
            ->join('arkas_raw_mirror_tables as mirror_table', 'mirror_table.id', '=', 'raw.mirror_table_id')
            ->where('mirror_table.source_id', $sourceId)
            ->where('mirror_table.source_table', 'kas_umum')
            ->where('mirror_table.status', 'ACTIVE')
            ->whereRaw("CAST(COALESCE(json_extract(raw.payload, '$.id_ref_bku'), 0) AS INTEGER) IN (10, 30)")
            ->whereRaw("COALESCE(json_extract(raw.payload, '$.soft_delete'), '0') != '1'")
            ->get(['raw.payload']) as $row) {
            $payload = json_decode((string) $row->payload, true);
            if (! is_array($payload)) {
                continue;
            }

            $parent = trim((string) ($payload['parent_id_kas_umum'] ?? ''));
            if ($parent === '' || ! isset($wanted[$parent])) {
                continue;
            }

            $taxes[$parent] ??= ['ppn' => 0.0, 'pph21' => 0.0, 'pph22' => 0.0, 'pph23' => 0.0, 'pph4' => 0.0, 'sspd' => 0.0];
            $field = $this->taxField($payload);
            if ($field !== null) {
                $taxes[$parent][$field] += $this->amount($payload);
            }
        }

        return $taxes;
    }

    /** @param array<string, mixed> $payload */
    private function taxField(array $payload): ?string
    {
        foreach ([
            'ppn' => ['is_ppn'],
            'pph21' => ['is_pph_21', 'is_pph21'],
            'pph22' => ['is_pph_22', 'is_pph22'],
            'pph23' => ['is_pph_23', 'is_pph23'],
            'pph4' => ['is_pph_4', 'is_pph4'],
            'sspd' => ['is_sspd'],
        ] as $field => $keys) {
            foreach ($keys as $key) {
                if ((int) ($payload[$key] ?? 0) === 1) {
                    return $field;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function notaById(Connection $db, int $sourceId): array
    {
        $table = $db->table('arkas_raw_mirror_tables')
            ->where('source_id', $sourceId)
            ->where('source_table', 'kas_umum_nota')
            ->where('status', 'ACTIVE')
            ->first();

        if ($table === null) {
            return [];
        }

        $result = [];
        foreach ($db->table('arkas_raw_mirror_rows')->where('mirror_table_id', $table->id)->get(['payload']) as $row) {
            $payload = json_decode((string) $row->payload, true);
            if (! is_array($payload)) {
                continue;
            }

            $id = trim((string) ($payload['id_kas_nota'] ?? ''));
            $name = $this->normalize($payload['nama_toko'] ?? null);
            if ($id !== '' && $name !== null) {
                $result[$id] = $name;
            }
        }

        return $result;
    }

    /**
     * @param Collection<int, array<string, mixed>> $items
     * @param array<int, string> $keys
     */
    private function firstSourceValue(Collection $items, array $keys): ?string
    {
        foreach ($items as $item) {
            $payload = $item['payload'];
            foreach ($keys as $key) {
                $value = $this->normalize($payload[$key] ?? null);
                if ($value !== null) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function amount(array $payload): float
    {
        return (float) ($payload['saldo'] ?? $payload['jumlah'] ?? $payload['nilai'] ?? $payload['nominal'] ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeMetadata(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function assertSchema(Connection $db): void
    {
        foreach ([
            'spj_transactions',
            'spj_transaction_sources',
            'spj_transaction_overlays',
            'spj_item_overlays',
            'legacy_transaction_v2_map',
            'arkas_source_identity_registry',
            'arkas_raw_mirror_rows',
            'arkas_raw_mirror_tables',
        ] as $table) {
            if (! $db->getSchemaBuilder()->hasTable($table)) {
                throw new RuntimeException('V2-D canonical read adapter requires table: '.$table);
            }
        }
    }
}
