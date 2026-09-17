<?php

namespace App\Services;

use App\Models\ArkasSource;
use App\Models\FiscalYear;
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
            ->first();

        if ($mirrorTable === null) {
            return ['transactions' => 0, 'items' => 0, 'skipped' => 0];
        }

        $transactionCount = 0;
        $itemCount = 0;
        $skipped = 0;
        $yearValue = (string) $year->year;

        $db->table('arkas_raw_mirror_rows')
            ->where('mirror_table_id', $mirrorTable->id)
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($db, $year, $fundSourceId, $source, $yearValue, &$transactionCount, &$itemCount, &$skipped): void {
                foreach ($rows as $row) {
                    $payload = json_decode((string) $row->payload, true);
                    if (! is_array($payload) || ! $this->belongsToFiscalYear($payload, $yearValue)) {
                        $skipped++;

                        continue;
                    }

                    $sourceKey = (string) $row->source_key;
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
                        'raw_mirror_row_id' => $row->id,
                        'source_status' => ((string) ($payload['soft_delete'] ?? '0')) === '1' ? 'DELETED' : 'ACTIVE',
                        'updated_at' => now(),
                    ];
                    if ($transaction === null) {
                        $transactionId = $db->table('spj_fresh_transactions')->insertGetId($attributes + ['created_at' => now()]);
                        $transactionCount++;
                    } else {
                        $transactionId = $transaction->id;
                        $db->table('spj_fresh_transactions')->where('id', $transactionId)->update($attributes);
                    }

                    $item = $db->table('spj_fresh_transaction_items')
                        ->where('spj_fresh_transaction_id', $transactionId)
                        ->where('source_table', 'kas_umum')
                        ->where('source_key', $sourceKey)
                        ->first();
                    $itemAttributes = [
                        'spj_fresh_transaction_id' => $transactionId,
                        'source_table' => 'kas_umum',
                        'source_key' => $sourceKey,
                        'raw_mirror_row_id' => $row->id,
                        'sort_order' => 0,
                        'updated_at' => now(),
                    ];
                    if ($item === null) {
                        $db->table('spj_fresh_transaction_items')->insert($itemAttributes + ['created_at' => now()]);
                        $itemCount++;
                    } else {
                        $db->table('spj_fresh_transaction_items')->where('id', $item->id)->update($itemAttributes);
                    }
                }
            }, 'id');

        return ['transactions' => $transactionCount, 'items' => $itemCount, 'skipped' => $skipped];
    }

    /** @param array<string, mixed> $payload */
    private function belongsToFiscalYear(array $payload, string $year): bool
    {
        foreach (['tanggal_transaksi', 'tanggal', 'create_date', 'tahun', 'year'] as $key) {
            if (! array_key_exists($key, $payload) || blank($payload[$key])) {
                continue;
            }

            $value = (string) $payload[$key];
            if ($key === 'tahun' || $key === 'year') {
                return $value === $year;
            }

            return str_starts_with($value, $year);
        }

        return false;
    }
}
