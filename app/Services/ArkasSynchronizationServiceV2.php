<?php

namespace App\Services;

use App\Models\ArkasSource;
use App\Models\FiscalYear;
use App\Models\School;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Full-refresh importer. It prunes stale ARKAS rows so historical revisions
 * cannot remain in BKU or transaction-item totals after a resynchronization.
 */
class ArkasSynchronizationServiceV2
{
    public function __construct(private ArkasBridgeClient $bridge) {}

    /** @param array<int, array<string, mixed>>|null $stagedRkas @param array<int, array<string, mixed>>|null $stagedBku @param array<int, array<string, mixed>>|null $stagedPeriods @param array<string, mixed>|null $stagedIdentity */
    public function synchronize(School $school, FiscalYear $year, ArkasSource $source, ?array $stagedRkas = null, ?array $stagedBku = null, ?array $stagedPeriods = null, ?array $stagedIdentity = null): array
    {
        if (! $year->fund_source_id) {
            throw new \RuntimeException('Tahun anggaran belum memiliki sumber dana. Sinkronkan referensi sumber dana terlebih dahulu.');
        }

        $identity = $stagedIdentity ?? ArkasPipePayload::values($this->bridge->execute($source, 'identity'), 'identity');
        if (($identity['NPSN'] ?? '') !== $school->npsn) {
            throw new \RuntimeException('NPSN database ARKAS tidak sesuai dengan sekolah aktif.');
        }

        $runId = DB::connection('school')->table('sync_runs')->insertGetId([
            'fiscal_year_id' => $year->id, 'source' => 'ARKAS', 'status' => 'RUNNING',
            'records_read' => 0, 'records_written' => 0, 'started_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        try {
            $rkas = $stagedRkas ?? ArkasPipePayload::decode($this->bridge->execute($source, 'rkas', $year->year, null, $year->fund_source_id), 'rkas');
            $bku = $stagedBku ?? ArkasPipePayload::decode($this->bridge->execute($source, 'bku', $year->year, null, $year->fund_source_id), 'bku');
            $rkas = $this->uniqueRecordsByField(array_values(array_filter($rkas, fn (array $record): bool => (int) ($record['ID_REF_SUMBER_DANA'] ?? 0) === (int) $year->fund_source_id)), 'ID_RAPBS');
            $bku = $this->uniqueRecordsByField(array_values(array_filter($bku, fn (array $record): bool => (int) ($record['ID_REF_SUMBER_DANA'] ?? 0) === (int) $year->fund_source_id)), 'ID_KAS_UMUM');
            $rkasPeriods = $this->loadRkasPeriods($source, $year, $rkas, $stagedPeriods);

            DB::connection('school')->transaction(function () use ($year, $rkas, $rkasPeriods, $bku, $runId): void {
                // A full refresh updates ARKAS-derived values but must preserve
                // manually prepared SPJ packages, assigned document numbers, and
                // worker/payment details as long as the source transaction exists.
                $this->saveRkas($year, $rkas);
                $this->saveRkasPeriods($year, $rkasPeriods);
                $this->saveBkuAndTransactions($year, $bku, $runId);
            });

            DB::connection('school')->table('sync_runs')->where('id', $runId)->update([
                'status' => 'SUCCESS', 'records_read' => count($rkas) + count($bku),
                'records_written' => count($rkas) + count($bku), 'finished_at' => now(), 'updated_at' => now(),
            ]);
            $source->forceFill(['last_identity' => json_encode($identity, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE), 'last_synced_at' => now()])->save();

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
                    'amount' => $this->amount($record['JUMLAH'] ?? 0),
                    'source_created_at' => $this->sourceTimestamp($record, ['CREATE_DATE', 'create_date']),
                    'source_last_updated_at' => $this->sourceTimestamp($record, ['LAST_UPDATE', 'last_update']),
                    'payload' => json_encode($record, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE),
                    'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    /** @param array<int, array<string, mixed>> $rkas */
    private function loadRkasPeriods(ArkasSource $source, FiscalYear $year, array $rkas, ?array $stagedPeriods = null): ?array
    {
        $rkasIds = array_fill_keys($this->ids($rkas, 'ID_RAPBS'), true);
        if ($rkasIds === []) {
            return [];
        }

        if ($stagedPeriods !== null) {
            $rows = $stagedPeriods;
        } else {
            try {
                $rows = ArkasPipePayload::decode(
                    $this->bridge->execute($source, 'rows', null, 'rapbs_periode', null, 100000),
                    'rows:rapbs_periode'
                );
            } catch (\Throwable $exception) {
                Log::warning('Detail periode RKAS tidak tersedia; sinkronisasi ringkasan tetap dilanjutkan.', [
                    'fiscal_year_id' => $year->id,
                    'message' => $exception->getMessage(),
                ]);

                return null;
            }
        }

        return array_values(array_filter(array_map(function (array $row) use ($rkasIds): ?array {
            $rapbsId = $this->firstText($row, ['id_rapbs', 'ID_RAPBS']);
            $periodId = $this->firstText($row, ['id_periode', 'ID_PERIODE']);
            if ($rapbsId === null || $periodId === null || ! isset($rkasIds[$rapbsId])) {
                return null;
            }

            return [
                'source_rapbs_id' => $rapbsId,
                'source_period_id' => $periodId,
                'volume' => $this->amount($this->firstText($row, ['volume', 'VOLUME']) ?? 0),
                'amount' => $this->amount($this->firstText($row, ['jumlah', 'JUMLAH']) ?? 0),
                'payload' => $row,
            ];
        }, $rows), fn (?array $period): bool => $period !== null));
    }

    /** @param array<int, array<string, mixed>>|null $records */
    private function saveRkasPeriods(FiscalYear $year, ?array $records): void
    {
        if ($records === null) {
            return;
        }

        $db = DB::connection('school');
        $db->table('arkas_rkas_periods')
            ->where('fiscal_year_id', $year->id)
            ->where('fund_source_id', $year->fund_source_id)
            ->delete();

        if ($records === []) {
            return;
        }

        $periodNames = $db->table('arkas_periods')->pluck('name', 'source_period_id')->all();
        $now = now();
        foreach ($records as $record) {
            $periodId = (string) $record['source_period_id'];
            $periodName = (string) ($periodNames[$periodId] ?? '');
            [$month, $quarter, $semester] = $this->periodCoordinates($periodId, $periodName);
            $db->table('arkas_rkas_periods')->updateOrInsert(
                [
                    'fiscal_year_id' => $year->id,
                    'fund_source_id' => $year->fund_source_id,
                    'source_rapbs_id' => $record['source_rapbs_id'],
                    'source_period_id' => $periodId,
                ],
                [
                    'source_rapbs_period_id' => $record['payload']['id_rapbs_periode'] ?? $record['payload']['ID_RAPBS_PERIODE'] ?? null,
                    'period_name' => $periodName !== '' ? $periodName : null,
                    'month_number' => $month,
                    'quarter_number' => $quarter,
                    'semester_number' => $semester,
                    'volume' => $record['volume'],
                    'amount' => $record['amount'],
                    'payload' => json_encode($record['payload'], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE),
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    /** @return array{0:?int,1:?int,2:?int} */
    private function periodCoordinates(string $periodId, string $periodName): array
    {
        $numericId = (int) $periodId;
        $normalizedName = mb_strtolower(trim($periodName));
        $monthNames = ['januari', 'februari', 'maret', 'april', 'mei', 'juni', 'juli', 'agustus', 'september', 'oktober', 'november', 'desember'];
        $month = array_search($normalizedName, $monthNames, true);
        if ($month !== false) {
            $month++;
        } elseif ($numericId >= 81 && $numericId <= 92) {
            $month = $numericId - 80;
        } else {
            $month = null;
        }

        if ($month !== null) {
            $quarter = (int) ceil($month / 3);

            return [$month, $quarter, (int) ceil($month / 6)];
        }

        if ($numericId >= 1 && $numericId <= 4) {
            return [null, $numericId, (int) ceil($numericId / 2)];
        }

        return [null, null, null];
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
                ['source_rapbs_period_id' => $record['ID_RAPBS_PERIODE'] ?? null,
                    'fund_source_id' => $record['ID_REF_SUMBER_DANA'] ?? $year->fund_source_id,
                    'parent_kas_id' => $record['PARENT_ID_KAS_UMUM'] ?? null, 'category' => $record['KATEGORI_BKU'] ?? null,
                    'no_bukti' => $record['NO_BUKTI'] ?? null, 'transaction_date' => $record['TANGGAL_TRANSAKSI'] ?: null,
                    'amount' => $this->amount($record['JUMLAH'] ?? 0), 'payload' => json_encode($record, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE),
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
            $sourceCreatedAt = $this->earliestSourceTimestamp($items, ['CREATE_DATE', 'CREATED_AT', 'create_date', 'created_at']);
            $sourceLastUpdatedAt = $this->earliestSourceTimestamp($items, ['LAST_UPDATE', 'LAST_UPDATED_AT', 'UPDATED_AT', 'last_update', 'updated_at']);
            $rkasDate = collect($items)
                ->map(fn (array $item) => $rkas[$item['ID_RAPBS'] ?? '']->source_created_at ?? null)
                ->filter()
                ->map(fn ($date) => Carbon::parse($date))
                ->sortDesc()
                ->first();
            $isSiplah = (bool) ($first['IS_SIPLAH'] ?? false);
            $siplahMetadata = $isSiplah ? $this->siplahMetadata($first['DETAILS_JSON_HEX'] ?? null) : null;
            $siplahResponse = is_array($siplahMetadata['siplahResponse'] ?? null) ? $siplahMetadata['siplahResponse'] : ($siplahMetadata ?? []);
            $siplahOrderNumber = $this->siplahOrderNumber($siplahResponse);
            $data = ['fund_source_id' => $first['ID_REF_SUMBER_DANA'] ?? $year->fund_source_id,
                'id_kas_umum' => $first['ID_KAS_UMUM'], 'transaction_date' => $first['TANGGAL_TRANSAKSI'],
                'rkas_date' => $rkasDate?->toDateString(),
                'source_created_at' => $sourceCreatedAt, 'source_last_updated_at' => $sourceLastUpdatedAt,
                'description' => $first['URAIAN'] ?? null, 'payment_method' => $this->paymentMethod($first),
                'activity_code' => $reference->activity_code ?? null, 'activity_name' => $reference->activity_name ?? null,
                'account_code' => $first['KODE_REKENING'] ?? null, 'account_name' => $reference->description ?? null,
                'recipient_name' => $first['NAMA_TOKO'] ?? null, 'gross_amount' => $gross,
                'ppn' => $taxes['ppn'], 'pph21' => $taxes['pph21'], 'pph22' => $taxes['pph22'],
                'pph23' => $taxes['pph23'], 'pph4' => $taxes['pph4'], 'sspd' => $taxes['sspd'],
                'tax_total' => $taxTotal, 'net_amount' => $gross - $taxTotal,
                'is_siplah' => $isSiplah, 'source_key' => $sourceKey,
                'siplah_metadata' => $siplahMetadata === null ? null : json_encode($siplahMetadata, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE),
                'siplah_order_number' => $siplahOrderNumber,
                'siplah_transaction_id' => $this->text($siplahResponse, 'transaction_id'),
                'siplah_marketplace' => $this->text($siplahResponse, 'marketplace_displayname'),
                'siplah_merchant_address' => $this->text($siplahResponse, 'merchant_address'),
                'siplah_payment_date' => $this->dateTime($siplahResponse, 'payment_date'),
                'siplah_dq_passed' => $this->flag($siplahResponse, 'is_dq_passed'),
                'siplah_backfilled' => $this->flag($siplahResponse, 'is_backfilled'),
                'siplah_budget_mapping_rejected' => $this->flag($siplahResponse, 'is_rejected_budget_mapping'),
                'siplah_partially_mapped' => $this->flag($siplahResponse, 'is_partially_mapped'),
                'source_status' => 'ACTIVE', 'last_seen_sync_run_id' => $runId,
                'source_missing_since' => null, 'updated_at' => now()];
            if ($isSiplah) {
                $vendorName = $this->firstText($first, ['NAMA_TOKO']) ?: $this->text($siplahResponse, 'merchant');
                $vendorNpwp = $this->firstText($first, ['NPWP_REKANAN']) ?: $this->text($siplahResponse, 'merchant_npwp');
                $invoiceNumber = $this->firstText($first, ['NO_NOTA']) ?: $this->text($siplahResponse, 'invoice_number');
                $invoiceDate = $this->firstText($first, ['TANGGAL_NOTA']);
                if ($vendorName !== null) {
                    $data['vendor_name'] = $vendorName;
                }
                if ($vendorNpwp !== null) {
                    $data['vendor_npwp'] = $vendorNpwp;
                }
                if ($invoiceNumber !== null) {
                    $data['invoice_number'] = $invoiceNumber;
                }
                if ($invoiceDate !== null) {
                    $data['invoice_date'] = $invoiceDate;
                }
            }
            $hashPayload = $data;
            unset($hashPayload['last_seen_sync_run_id'], $hashPayload['source_missing_since'], $hashPayload['source_status'], $hashPayload['updated_at']);
            $hashWithOrderingMetadata = hash('sha256', json_encode($hashPayload, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
            unset($hashPayload['source_created_at'], $hashPayload['source_last_updated_at'], $hashPayload['rkas_date']);
            $sourceHash = hash('sha256', json_encode($hashPayload, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
            $existing = DB::connection('school')->table('transactions')
                ->where('fiscal_year_id', $year->id)->where('source_key', $sourceKey)->first();
            $existing ??= DB::connection('school')->table('transactions')
                ->where('fiscal_year_id', $year->id)->where('id_kas_umum', $first['ID_KAS_UMUM'])->first();
            $existing ??= DB::connection('school')->table('transactions')
                ->where(['fiscal_year_id' => $year->id, 'no_bukti' => $noBukti])->first();
            $hasPackage = $existing && DB::connection('school')->table('spj_packages')->where('transaction_id', $existing->id)->exists();
            $data['source_hash'] = $sourceHash;
            $isOrderingMetadataOnlyHash = $existing && $existing->source_hash === $hashWithOrderingMetadata;
            $data['requires_reconciliation'] = $isOrderingMetadataOnlyHash
                ? false
                : (bool) ($existing->requires_reconciliation ?? false)
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
            if ($existing && filled($existing->invoice_number)) {
                unset($data['invoice_number']);
            }
            if ($existing && filled($existing->invoice_date)) {
                unset($data['invoice_date']);
            }
            if ($existing && filled($existing->siplah_order_number)) {
                unset($data['siplah_order_number']);
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
                $rkasItemName = $this->firstText($referencePayload, ['URAIAN', 'DESCRIPTION', 'description'])
                    ?: $this->firstText($item, ['URAIAN']);
                $siplahItem = $this->siplahItem($siplahResponse, $item['ID_RAPBS'] ?? null, $rkasItemName);
                $siplahItemName = $this->text($siplahItem, 'siplah_item_name');
                $itemKey = ['transaction_id' => $transactionId, 'source_item_id' => $item['ID_KAS_UMUM']];
                $itemData = ['description' => $item['URAIAN'] ?? '', 'quantity' => $quantity, 'unit' => $unit,
                    'siplah_item_mpid' => $this->text($siplahItem, 'siplah_item_mpid'),
                    'rkas_item_code' => $this->text($siplahItem, 'rkas_item_code'),
                    'rkas_item_name' => $this->text($siplahItem, 'rkas_item_name'),
                    'siplah_item_name' => $siplahItemName,
                    'siplah_mapped_quantity' => $this->number($siplahItem, 'item_mapped_quantity'),
                    'siplah_quantity_received' => $this->number($siplahItem, 'quantity_received'),
                    'siplah_unit_dpp' => $this->number($siplahItem, 'unit_item_dpp'),
                    'siplah_unit_ppn' => $this->number($siplahItem, 'unit_item_ppn'),
                    'siplah_unit_price' => $this->number($siplahItem, 'unit_item_price'),
                    'siplah_unit_insurance_cost' => $this->number($siplahItem, 'unit_insurance_cost'),
                    'siplah_unit_packaging_cost' => $this->number($siplahItem, 'unit_packaging_cost'),
                    'siplah_item_metadata' => $siplahItem === null ? null : json_encode($siplahItem, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE),
                    'unit_price' => $amount / $quantity, 'amount' => $amount, 'source_status' => 'ACTIVE',
                    'last_seen_sync_run_id' => $runId, 'source_missing_since' => null, 'updated_at' => now()];
                $existingItem = DB::connection('school')->table('transaction_items')->where($itemKey)->first(['id', 'item_description']);
                if ($isSiplah && $siplahItemName !== null && blank($existingItem?->item_description)) {
                    $itemData['item_description'] = $siplahItemName;
                }
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

    /** @return array<string, mixed>|null */
    private function siplahMetadata(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $decodedHex = ctype_xdigit($value) ? hex2bin($value) : false;
        $decoded = json_decode($decodedHex !== false ? $decodedHex : $value, true);

        return is_array($decoded) ? $decoded : ['raw' => $value];
    }

    /** @param array<string, mixed> $response @return array<string, mixed>|null */
    private function siplahItem(array $response, ?string $rapbsId, ?string $rkasItemName = null): ?array
    {
        if ($rapbsId === null && blank($rkasItemName)) {
            return null;
        }

        $candidates = [];
        foreach (['items', 'transaction_items'] as $key) {
            if (is_array($response[$key] ?? null)) {
                $candidates = [...$candidates, ...$response[$key]];
            }
        }
        foreach ($response['activities'] ?? [] as $activity) {
            if (is_array($activity) && is_array($activity['items'] ?? null)) {
                $candidates = [...$candidates, ...$activity['items']];
            }
        }

        foreach ($candidates as $item) {
            if ($rapbsId !== null && is_array($item) && (string) ($item['rapbs_id'] ?? '') === $rapbsId) {
                return $item;
            }
        }

        $normalizedRkasItemName = $this->normalizeItemName($rkasItemName);
        if ($normalizedRkasItemName === null) {
            return null;
        }

        foreach ($candidates as $item) {
            if (is_array($item) && $this->normalizeItemName($item['rkas_item_name'] ?? null) === $normalizedRkasItemName) {
                return $item;
            }
        }

        return null;
    }

    private function normalizeItemName(mixed $value): ?string
    {
        $normalized = preg_replace('/\s+/u', ' ', mb_strtolower(trim((string) $value)));

        return $normalized === '' ? null : $normalized;
    }

    /** @param array<string, mixed> $response */
    private function siplahOrderNumber(array $response): ?string
    {
        $invoiceNumber = $this->text($response, 'invoice_number');
        if ($invoiceNumber === null) {
            return null;
        }

        $parts = array_values(array_filter(array_map('trim', explode('/', $invoiceNumber))));

        return $parts === [] ? null : end($parts);
    }

    /** @param array<string, mixed>|null $record */
    private function text(?array $record, string $key): ?string
    {
        $value = trim((string) ($record[$key] ?? ''));

        return $value === '' ? null : $value;
    }

    /** @param array<string, mixed>|null $record */
    private function number(?array $record, string $key): ?float
    {
        $value = $record[$key] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    /** @param array<string, mixed>|null $record */
    private function flag(?array $record, string $key): ?bool
    {
        if (! array_key_exists($key, $record ?? [])) {
            return null;
        }

        return filter_var($record[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? ((int) $record[$key] === 1);
    }

    /** @param array<string, mixed>|null $record */
    private function dateTime(?array $record, string $key): ?Carbon
    {
        $value = $this->text($record, $key);
        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function ids(array $records, string $field): array
    {
        return array_values(array_unique(array_filter(array_map(fn ($record) => (string) ($record[$field] ?? ''), $records))));
    }

    /** @param array<int, array<string, mixed>> $records @return array<int, array<string, mixed>> */
    private function uniqueRecordsByField(array $records, string $field): array
    {
        $indexed = [];
        foreach ($records as $record) {
            $key = trim((string) ($record[$field] ?? ''));
            if ($key !== '') {
                $indexed[$key] = $record;
            }
        }

        return array_values($indexed);
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @param  array<int, string>  $keys
     */
    private function earliestSourceTimestamp(array $records, array $keys): ?Carbon
    {
        return collect($records)
            ->map(fn (array $record): ?Carbon => $this->sourceTimestamp($record, $keys))
            ->filter()
            ->sort()
            ->first();
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<int, string>  $keys
     */
    private function sourceTimestamp(array $record, array $keys): ?Carbon
    {
        foreach ($keys as $key) {
            $value = trim((string) ($record[$key] ?? ''));
            if ($value === '') {
                continue;
            }

            try {
                return Carbon::parse($value);
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
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
