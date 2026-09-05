<?php

namespace App\Services;

use App\Models\ArkasSource;
use App\Models\FiscalYear;
use App\Models\School;
use Illuminate\Support\Facades\DB;

/**
 * Full-refresh importer. It prunes stale ARKAS rows so historical revisions
 * cannot remain in BKU or transaction-item totals after a resynchronization.
 */
class ArkasSynchronizationServiceV2
{
    public function __construct(private ArkasBridgeClient $bridge) {}

    public function synchronize(School $school, FiscalYear $year, ArkasSource $source): array
    {
        if (! $year->fund_source_id) {
            throw new \RuntimeException('Tahun anggaran belum memiliki sumber dana. Sinkronkan referensi sumber dana terlebih dahulu.');
        }

        $identity = ArkasPipePayload::values($this->bridge->execute($source, 'identity'));
        if (($identity['NPSN'] ?? '') !== $school->npsn) {
            throw new \RuntimeException('NPSN database ARKAS tidak sesuai dengan sekolah aktif.');
        }

        $runId = DB::connection('school')->table('sync_runs')->insertGetId([
            'fiscal_year_id' => $year->id, 'source' => 'ARKAS', 'status' => 'RUNNING',
            'records_read' => 0, 'records_written' => 0, 'started_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        try {
            $rkas = ArkasPipePayload::decode($this->bridge->execute($source, 'rkas', $year->year, null, $year->fund_source_id));
            $bku = ArkasPipePayload::decode($this->bridge->execute($source, 'bku', $year->year, null, $year->fund_source_id));
            $rkas = array_values(array_filter($rkas, fn (array $record): bool => (int) ($record['ID_REF_SUMBER_DANA'] ?? 0) === (int) $year->fund_source_id));
            $bku = array_values(array_filter($bku, fn (array $record): bool => (int) ($record['ID_REF_SUMBER_DANA'] ?? 0) === (int) $year->fund_source_id));

            DB::connection('school')->transaction(function () use ($year, $rkas, $bku, $runId): void {
                // A full refresh updates ARKAS-derived values but must preserve
                // manually prepared SPJ packages, assigned document numbers, and
                // worker/payment details as long as the source transaction exists.
                $this->saveRkas($year, $rkas);
                $this->saveBkuAndTransactions($year, $bku, $runId);
            });

            DB::connection('school')->table('sync_runs')->where('id', $runId)->update([
                'status' => 'SUCCESS', 'records_read' => count($rkas) + count($bku),
                'records_written' => count($rkas) + count($bku), 'finished_at' => now(), 'updated_at' => now(),
            ]);
            $source->forceFill(['last_identity' => json_encode($identity), 'last_synced_at' => now()])->save();

            return ['rkas' => count($rkas), 'bku' => count($bku)];
        } catch (\Throwable $exception) {
            DB::connection('school')->table('sync_runs')->where('id', $runId)->update([
                'status' => 'FAILED', 'message' => $exception->getMessage(), 'finished_at' => now(), 'updated_at' => now(),
            ]);
            throw $exception;
        }
    }

    private function saveRkas(FiscalYear $year, array $records): void
    {
        $ids = $this->ids($records, 'ID_RAPBS');
        $query = DB::connection('school')->table('arkas_rkas_items')
            ->where('fiscal_year_id', $year->id)->where('fund_source_id', $year->fund_source_id);
        $ids ? $query->whereNotIn('source_rapbs_id', $ids)->delete() : $query->delete();

        foreach ($records as $record) {
            DB::connection('school')->table('arkas_rkas_items')->updateOrInsert(
                ['fiscal_year_id' => $year->id, 'source_rapbs_id' => $record['ID_RAPBS']],
                ['fund_source_id' => $record['ID_REF_SUMBER_DANA'] ?? $year->fund_source_id,
                    'activity_code' => $record['KODE_KEGIATAN'] ?? null, 'activity_name' => $record['NAMA_KEGIATAN'] ?? null,
                    'account_code' => $record['KODE_REKENING'] ?? null, 'description' => $record['URAIAN'] ?? null,
                    'amount' => $this->amount($record['JUMLAH'] ?? 0), 'payload' => json_encode($record),
                    'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    private function saveBkuAndTransactions(FiscalYear $year, array $records, int $runId): void
    {
        $sourceIds = $this->ids($records, 'ID_KAS_UMUM');
        $bkuQuery = DB::connection('school')->table('arkas_bku_rows')
            ->where('fiscal_year_id', $year->id)->where('fund_source_id', $year->fund_source_id);
        $sourceIds ? $bkuQuery->whereNotIn('source_kas_id', $sourceIds)->delete() : $bkuQuery->delete();

        $belanja = [];
        $taxesByParent = [];
        $rkas = DB::connection('school')->table('arkas_rkas_items')->where('fiscal_year_id', $year->id)->get()->keyBy('source_rapbs_id');
        $accountReferences = DB::connection('school')->table('account_references')
            ->where('fiscal_year_id', $year->id)->get()->keyBy('account_code');
        foreach ($records as $record) {
            DB::connection('school')->table('arkas_bku_rows')->updateOrInsert(
                ['fiscal_year_id' => $year->id, 'source_kas_id' => $record['ID_KAS_UMUM']],
                ['fund_source_id' => $record['ID_REF_SUMBER_DANA'] ?? $year->fund_source_id,
                    'parent_kas_id' => $record['PARENT_ID_KAS_UMUM'] ?? null, 'category' => $record['KATEGORI_BKU'] ?? null,
                    'no_bukti' => $record['NO_BUKTI'] ?? null, 'transaction_date' => $record['TANGGAL_TRANSAKSI'] ?: null,
                    'amount' => $this->amount($record['JUMLAH'] ?? 0), 'payload' => json_encode($record),
                    'updated_at' => now(), 'created_at' => now()]
            );
            if (($record['KATEGORI_BKU'] ?? '') === 'BELANJA' && ! empty($record['NO_BUKTI'])) {
                $belanja[$record['NO_BUKTI']][] = $record;
            }
            // PBT (Pajak Belanja Terima) adalah pajak yang dipungut dari transaksi.
            // PBS (Pajak Belanja Setor) hanya penyetoran pajak yang sama dan tidak
            // boleh dihitung kembali sebagai potongan transaksi.
            if (($record['KATEGORI_BKU'] ?? '') === 'PAJAK'
                && ! empty($record['PARENT_ID_KAS_UMUM'])
                && ! $this->isTaxDeposit($record)) {
                $taxesByParent[$record['PARENT_ID_KAS_UMUM']][] = $record;
            }
        }

        $processedTransactionIds = [];
        foreach ($belanja as $noBukti => $items) {
            $first = $items[0];
            $reference = $rkas[$first['ID_RAPBS'] ?? ''] ?? null;
            $gross = array_sum(array_map(fn ($item) => $this->amount($item['JUMLAH'] ?? 0), $items));
            $taxes = ['ppn' => 0, 'pph21' => 0, 'pph22' => 0, 'pph23' => 0, 'pph4' => 0, 'sspd' => 0];
            foreach ($items as $item) {
                foreach ($taxesByParent[$item['ID_KAS_UMUM']] ?? [] as $tax) {
                    $value = $this->amount($tax['JUMLAH'] ?? 0);
                    if (($tax['IS_PPN'] ?? 0) == 1) {
                        $taxes['ppn'] += $value;
                    } elseif (($tax['IS_PPH21'] ?? 0) == 1) {
                        $taxes['pph21'] += $value;
                    } elseif (($tax['IS_PPH22'] ?? 0) == 1) {
                        $taxes['pph22'] += $value;
                    } elseif (($tax['IS_PPH23'] ?? 0) == 1) {
                        $taxes['pph23'] += $value;
                    } elseif (($tax['IS_PPH4'] ?? 0) == 1) {
                        $taxes['pph4'] += $value;
                    } elseif (($tax['IS_SSPD'] ?? 0) == 1) {
                        $taxes['sspd'] += $value;
                    }
                }
            }
            $taxTotal = array_sum($taxes);
            $sourceItemIds = $this->ids($items, 'ID_KAS_UMUM');
            sort($sourceItemIds);
            $sourceKey = hash('sha256', implode('|', $sourceItemIds));
            $isSiplah = (bool) ($first['IS_SIPLAH'] ?? false);
            $data = ['fund_source_id' => $first['ID_REF_SUMBER_DANA'] ?? $year->fund_source_id,
                'id_kas_umum' => $first['ID_KAS_UMUM'], 'transaction_date' => $first['TANGGAL_TRANSAKSI'],
                'description' => $first['URAIAN'] ?? null, 'payment_method' => $this->paymentMethod($first),
                'activity_code' => $reference->activity_code ?? null, 'activity_name' => $reference->activity_name ?? null,
                'account_code' => $first['KODE_REKENING'] ?? null, 'account_name' => $reference->description ?? null,
                'recipient_name' => $first['NAMA_TOKO'] ?? null, 'gross_amount' => $gross,
                'ppn' => $taxes['ppn'], 'pph21' => $taxes['pph21'], 'pph22' => $taxes['pph22'],
                'pph23' => $taxes['pph23'], 'pph4' => $taxes['pph4'], 'sspd' => $taxes['sspd'],
                'tax_total' => $taxTotal, 'net_amount' => $gross - $taxTotal,
                'is_siplah' => $isSiplah, 'source_key' => $sourceKey,
                'source_status' => 'ACTIVE', 'last_seen_sync_run_id' => $runId,
                'source_missing_since' => null, 'updated_at' => now()];
            if ($isSiplah) {
                $vendorName = $this->firstText($first, ['NAMA_TOKO']);
                $vendorNpwp = $this->firstText($first, ['NPWP_REKANAN']);
                if ($vendorName !== null) {
                    $data['vendor_name'] = $vendorName;
                }
                if ($vendorNpwp !== null) {
                    $data['vendor_npwp'] = $vendorNpwp;
                }
            }
            $hashPayload = $data;
            unset($hashPayload['last_seen_sync_run_id'], $hashPayload['source_missing_since'], $hashPayload['source_status'], $hashPayload['updated_at']);
            $sourceHash = hash('sha256', json_encode($hashPayload, JSON_THROW_ON_ERROR));
            $existing = DB::connection('school')->table('transactions')
                ->where('fiscal_year_id', $year->id)->where('source_key', $sourceKey)->first();
            $existing ??= DB::connection('school')->table('transactions')
                ->where('fiscal_year_id', $year->id)->where('id_kas_umum', $first['ID_KAS_UMUM'])->first();
            $existing ??= DB::connection('school')->table('transactions')
                ->where(['fiscal_year_id' => $year->id, 'no_bukti' => $noBukti])->first();
            $hasPackage = $existing && DB::connection('school')->table('spj_packages')->where('transaction_id', $existing->id)->exists();
            $data['source_hash'] = $sourceHash;
            $data['requires_reconciliation'] = (bool) ($existing->requires_reconciliation ?? false)
                || ($hasPackage && filled($existing->source_hash) && $existing->source_hash !== $sourceHash);
            if ($existing && DB::connection('school')->table('spj_work_orders')->where('transaction_id', $existing->id)->exists()) {
                // For an upah/pemeliharaan package, the checked worker is the
                // intentional receipt recipient; do not replace it with ARKAS vendor.
                unset($data['recipient_name']);
            }
            if ($existing && filled($existing->payment_method)) {
                unset($data['payment_method']);
            }
            if ($existing && filled($existing->vendor_name)) {
                unset($data['vendor_name']);
            }
            if ($existing && filled($existing->vendor_npwp)) {
                unset($data['vendor_npwp']);
            }
            if (! $existing || blank($existing->spj_category)) {
                $accountCode = $first['KODE_REKENING'] ?? $reference?->account_code;
                $data['spj_category'] = $this->spjCategory(
                    $accountCode,
                    $accountReferences->get($accountCode)
                );
            }
            $transactionId = $existing
                ? tap($existing->id, fn () => DB::connection('school')->table('transactions')->where('id', $existing->id)->update($data + ['no_bukti' => $noBukti]))
                : DB::connection('school')->table('transactions')->insertGetId($data + ['fiscal_year_id' => $year->id, 'no_bukti' => $noBukti, 'status' => 'DRAFT', 'created_at' => now()]);
            $processedTransactionIds[] = $transactionId;

            $itemQuery = DB::connection('school')->table('transaction_items')->where('transaction_id', $transactionId);
            $sourceItemIds
                ? $itemQuery->whereNotIn('source_item_id', $sourceItemIds)->update([
                    'source_status' => 'SOURCE_MISSING',
                    'source_missing_since' => DB::raw('COALESCE(source_missing_since, CURRENT_TIMESTAMP)'),
                    'updated_at' => now(),
                ])
                : $itemQuery->update([
                    'source_status' => 'SOURCE_MISSING',
                    'source_missing_since' => DB::raw('COALESCE(source_missing_since, CURRENT_TIMESTAMP)'),
                    'updated_at' => now(),
                ]);
            foreach ($items as $item) {
                $amount = $this->amount($item['JUMLAH'] ?? 0);
                $quantity = max(1, $this->amount($item['VOLUME'] ?? 1));
                $reference = $rkas[$item['ID_RAPBS'] ?? ''] ?? null;
                $referencePayload = $this->payload($reference?->payload);

                // ARKAS tidak selalu mengirim SATUAN pada hasil BKU. Dalam
                // kondisi tersebut, ID_RAPBS menunjuk ke baris RKAS yang
                // memiliki satuan anggaran asli. BKU tetap menjadi prioritas
                // apabila versi ARKAS yang digunakan memang mengirimkannya.
                $unit = $this->firstText($item, ['SATUAN', 'SATUAN_BARANG', 'UNIT'])
                    ?: $this->firstText($referencePayload, ['SATUAN', 'SATUAN_BARANG', 'UNIT']);
                $itemKey = ['transaction_id' => $transactionId, 'source_item_id' => $item['ID_KAS_UMUM']];
                $itemData = ['description' => $item['URAIAN'] ?? '', 'quantity' => $quantity, 'unit' => $unit,
                    'unit_price' => $amount / $quantity, 'amount' => $amount, 'source_status' => 'ACTIVE',
                    'last_seen_sync_run_id' => $runId, 'source_missing_since' => null, 'updated_at' => now()];
                $existingItem = DB::connection('school')->table('transaction_items')->where($itemKey)->exists();
                $existingItem
                    ? DB::connection('school')->table('transaction_items')->where($itemKey)->update($itemData)
                    : DB::connection('school')->table('transaction_items')->insert($itemKey + $itemData + ['created_at' => now()]);
            }
        }

        $missingTransactions = DB::connection('school')->table('transactions')
            ->where('fiscal_year_id', $year->id)
            ->where('fund_source_id', $year->fund_source_id);
        if ($processedTransactionIds !== []) {
            $missingTransactions->whereNotIn('id', $processedTransactionIds);
        }
        $missingTransactionIds = (clone $missingTransactions)->pluck('id');
        $missingTransactions->update([
            'source_status' => 'SOURCE_MISSING',
            'source_missing_since' => DB::raw('COALESCE(source_missing_since, CURRENT_TIMESTAMP)'),
            'updated_at' => now(),
        ]);
        if ($missingTransactionIds->isNotEmpty()) {
            DB::connection('school')->table('transaction_items')
                ->whereIn('transaction_id', $missingTransactionIds)
                ->update([
                    'source_status' => 'SOURCE_MISSING',
                    'source_missing_since' => DB::raw('COALESCE(source_missing_since, CURRENT_TIMESTAMP)'),
                    'updated_at' => now(),
                ]);
        }
    }

    /** @param array<string, mixed> $record */
    private function isTaxDeposit(array $record): bool
    {
        $code = strtoupper(trim((string) ($record['KODE_BKU'] ?? '')));
        $description = mb_strtolower(trim(implode(' ', [
            $record['REK_BKU'] ?? '',
            $record['URAIAN'] ?? '',
        ])));

        return $code === 'PBS' || str_contains($description, 'pajak belanja setor') || str_starts_with($description, 'setor ');
    }

    private function ids(array $records, string $field): array
    {
        return array_values(array_filter(array_map(fn ($record) => (string) ($record[$field] ?? ''), $records)));
    }

    private function amount(mixed $value): float
    {
        return abs((float) str_replace(',', '.', (string) $value));
    }

    /** @return array<string, mixed> */
    private function payload(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }
        if (! is_string($payload) || $payload === '') {
            return [];
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, mixed> $record */
    private function firstText(array $record, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($record[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $record */
    private function paymentMethod(array $record): string
    {
        if ((bool) ($record['IS_SIPLAH'] ?? false)) {
            return 'siplah';
        }

        $proofNumber = strtolower((string) ($record['NO_BUKTI'] ?? ''));
        $bkuCode = strtolower((string) ($record['KODE_BKU'] ?? ''));

        if (
            str_contains($proofNumber, 'non_tunai')
            || str_contains($proofNumber, 'non tunai')
            || str_contains($bkuCode, 'non_tunai')
            || str_contains($bkuCode, 'non tunai')
            || str_starts_with($bkuCode, 'bnu')
        ) {
            return 'transfer_bank';
        }

        return 'tunai';
    }

    private function spjCategory(?string $accountCode, mixed $reference): ?string
    {
        $referenceCategory = strtoupper(trim((string) ($reference?->spj_category ?? '')));
        if ($referenceCategory !== '') {
            $normalizedCategory = match (true) {
                str_contains($referenceCategory, 'HONOR') => 'HONOR_PEGAWAI',
                str_contains($referenceCategory, 'MODAL') => 'BARANG',
                str_contains($referenceCategory, 'KONSUMSI') => 'KONSUMSI',
                str_contains($referenceCategory, 'PEMELIHARAAN') => 'PEMELIHARAAN',
                str_contains($referenceCategory, 'PERJALANAN'), str_contains($referenceCategory, 'SPPD') => 'SPPD',
                str_contains($referenceCategory, 'BARANG') => 'BARANG',
                default => null,
            };
            if ($normalizedCategory !== null) {
                return $normalizedCategory;
            }
        }

        if ($reference?->is_honor) {
            return 'HONOR_PEGAWAI';
        }

        $code = strtoupper(trim((string) $accountCode));

        return match (true) {
            str_starts_with($code, '5.2.') => 'BARANG',
            str_starts_with($code, '5.1.02.03') => 'PEMELIHARAAN',
            str_starts_with($code, '5.1.02.04') => 'SPPD',
            str_starts_with($code, '5.1.02.01') => 'BARANG',
            default => null,
        };
    }
}
