<?php

namespace App\Services;

use App\Models\ArkasSource;
use App\Models\FiscalYear;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

final class SpjFreshProjectionService
{
    /**
     * @return array{transactions:int, items:int, skipped:int}
     */
    public function project(FiscalYear $year, int $fundSourceId, ArkasSource $source): array
    {
        $db = DB::connection('school');
        $mirrorTable = $db->table('arkas_raw_mirror_tables')
            ->where('source_id', $source->id)
            ->where('source_table', 'kas_umum')
            ->where('status', 'ACTIVE')
            ->first();

        if ($mirrorTable === null) {
            return ['transactions' => 0, 'items' => 0, 'skipped' => 0];
        }

        $yearValue = (string) $year->year;
        $approvedBudgetIds = $this->approvedBudgetIds($db, $source, $yearValue, $fundSourceId);
        if ($approvedBudgetIds === []) {
            return ['transactions' => 0, 'items' => 0, 'skipped' => 0];
        }

        /** @var array<string, array<string, array{row:object,payload:array<string,mixed>}>> $groups */
        $groups = [];
        $skipped = 0;

        $db->table('arkas_raw_mirror_rows')
            ->where('mirror_table_id', $mirrorTable->id)
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$groups, &$skipped, $approvedBudgetIds, $yearValue): void {
                foreach ($rows as $row) {
                    $payload = json_decode((string) $row->payload, true);
                    if (! is_array($payload)
                        || ! $this->belongsToFiscalYear($payload, $yearValue)
                        || ! isset($approvedBudgetIds[(string) $this->value($payload, 'id_anggaran')])
                        || ! $this->isSpending($payload)
                        || $this->isSoftDeleted($payload)) {
                        $skipped++;

                        continue;
                    }

                    $idKasUmum = trim((string) $this->value($payload, 'id_kas_umum'));
                    $noBukti = trim((string) $this->value($payload, 'no_bukti'));
                    if ($idKasUmum === '' || $noBukti === '') {
                        $skipped++;

                        continue;
                    }

                    $groups[$noBukti][$idKasUmum] = [
                        'row' => $row,
                        'payload' => $payload,
                    ];
                }
            }, 'id');

        $transactionCount = 0;
        $itemCount = 0;
        $projectedTransactionKeys = [];

        foreach ($groups as $itemsBySourceId) {
            $sourceItemIds = array_keys($itemsBySourceId);
            sort($sourceItemIds, SORT_STRING);
            $sourceKey = hash('sha256', implode('|', $sourceItemIds));
            $projectedTransactionKeys[$sourceKey] = true;

            $firstItem = $itemsBySourceId[$sourceItemIds[0]];
            $transaction = $db->table('spj_fresh_transactions')
                ->where('fiscal_year_id', $year->id)
                ->where('fund_source_id', $fundSourceId)
                ->where('source_id', $source->id)
                ->where('source_table', 'kas_umum')
                ->where('source_key', $sourceKey)
                ->first();

            $attributes = [
                'fiscal_year_id' => $year->id,
                'fund_source_id' => $fundSourceId,
                'source_id' => $source->id,
                'source_table' => 'kas_umum',
                'source_key' => $sourceKey,
                'raw_mirror_row_id' => $firstItem['row']->id,
                'source_status' => 'ACTIVE',
                'source_missing_since' => null,
                'updated_at' => now(),
            ];

            if ($transaction === null) {
                $transactionId = $db->table('spj_fresh_transactions')->insertGetId($attributes + ['created_at' => now()]);
                $transactionCount++;
            } else {
                $transactionId = $transaction->id;
                $db->table('spj_fresh_transactions')->where('id', $transactionId)->update($attributes);
            }

            foreach ($sourceItemIds as $sortOrder => $sourceItemId) {
                $itemRow = $itemsBySourceId[$sourceItemId]['row'];
                $item = $db->table('spj_fresh_transaction_items')
                    ->where('spj_fresh_transaction_id', $transactionId)
                    ->where('source_table', 'kas_umum')
                    ->where('source_key', $sourceItemId)
                    ->first();
                $itemAttributes = [
                    'spj_fresh_transaction_id' => $transactionId,
                    'source_table' => 'kas_umum',
                    'source_key' => $sourceItemId,
                    'raw_mirror_row_id' => $itemRow->id,
                    'sort_order' => $sortOrder,
                    'updated_at' => now(),
                ];

                if ($item === null) {
                    $db->table('spj_fresh_transaction_items')->insert($itemAttributes + ['created_at' => now()]);
                    $itemCount++;
                } else {
                    $db->table('spj_fresh_transaction_items')->where('id', $item->id)->update($itemAttributes);
                }
            }
        }

        $existingTransactions = $db->table('spj_fresh_transactions')
            ->where('fiscal_year_id', $year->id)
            ->where('fund_source_id', $fundSourceId)
            ->where('source_id', $source->id)
            ->where('source_table', 'kas_umum')
            ->whereIn('source_status', ['ACTIVE', 'SOURCE_MISSING'])
            ->get(['id', 'source_key']);

        foreach ($existingTransactions as $existingTransaction) {
            if (isset($projectedTransactionKeys[(string) $existingTransaction->source_key])) {
                continue;
            }

            $db->table('spj_fresh_transactions')
                ->where('id', $existingTransaction->id)
                ->update([
                    'source_status' => 'SOURCE_MISSING',
                    'source_missing_since' => $db->raw('COALESCE(source_missing_since, CURRENT_TIMESTAMP)'),
                    'updated_at' => now(),
                ]);
        }

        return ['transactions' => $transactionCount, 'items' => $itemCount, 'skipped' => $skipped];
    }

    /** @return array<string, bool> */
    private function approvedBudgetIds(Connection $db, ArkasSource $source, string $year, int $fundSourceId): array
    {
        $mirrorTableId = $db->table('arkas_raw_mirror_tables')
            ->where('source_id', $source->id)
            ->where('source_table', 'anggaran')
            ->where('status', 'ACTIVE')
            ->value('id');
        if ($mirrorTableId === null) {
            return [];
        }

        $rows = $db->table('arkas_raw_mirror_rows')
            ->where('mirror_table_id', $mirrorTableId)
            ->get()
            ->map(fn (object $row): array => json_decode((string) $row->payload, true) ?: [])
            ->filter(fn (array $row): bool => (string) $this->value($row, 'tahun_anggaran') === $year
                && (int) $this->value($row, 'id_ref_sumber_dana') === $fundSourceId
                && (string) $this->value($row, 'is_approve') === '1'
                && (string) $this->value($row, 'is_aktif') === '1'
                && ! $this->isSoftDeleted($row));

        if ($rows->isEmpty()) {
            return [];
        }

        $revision = (int) $rows->max(fn (array $row): int => (int) $this->value($row, 'is_revisi'));
        $latest = $rows->filter(fn (array $row): bool => (int) $this->value($row, 'is_revisi') === $revision);
        $lastUpdate = (string) $latest->max(fn (array $row): string => (string) $this->value($row, 'last_update'));

        return $latest
            ->filter(fn (array $row): bool => (string) $this->value($row, 'last_update') === $lastUpdate)
            ->mapWithKeys(function (array $row): array {
                $idAnggaran = trim((string) $this->value($row, 'id_anggaran'));

                return $idAnggaran === '' ? [] : [$idAnggaran => true];
            })
            ->all();
    }

    /** @param array<string, mixed> $payload */
    private function belongsToFiscalYear(array $payload, string $year): bool
    {
        foreach (['tanggal_transaksi', 'tanggal', 'create_date', 'tahun', 'year'] as $key) {
            $value = $this->value($payload, $key);
            if (blank($value)) {
                continue;
            }

            $value = (string) $value;
            if ($key === 'tahun' || $key === 'year') {
                return $value === $year;
            }

            return str_starts_with($value, $year);
        }

        return false;
    }

    /** @param array<string, mixed> $payload */
    private function isSpending(array $payload): bool
    {
        if (strtoupper(trim((string) $this->value($payload, 'kategori_bku'))) === 'BELANJA') {
            return true;
        }

        return in_array((int) $this->value($payload, 'id_ref_bku'), [4, 15, 24, 35], true);
    }

    /** @param array<string, mixed> $payload */
    private function isSoftDeleted(array $payload): bool
    {
        return (string) $this->value($payload, 'soft_delete') === '1';
    }

    /** @param array<string, mixed> $payload */
    private function value(array $payload, string $column): mixed
    {
        foreach ($payload as $key => $value) {
            if (strcasecmp((string) $key, $column) === 0) {
                return $value;
            }
        }

        return null;
    }
}
