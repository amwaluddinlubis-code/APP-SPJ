<?php

namespace App\Services;

use App\Models\ArkasSource;
use App\Models\FiscalYear;
use App\Models\School;
use Illuminate\Support\Facades\DB;

class ArkasSynchronizationService
{
    public function __construct(private ArkasBridgeClient $bridge) {}

    public function synchronize(School $school, FiscalYear $year, ArkasSource $source): array
    {
        $identity = ArkasPipePayload::values($this->bridge->execute($source, 'identity'), 'identity');
        if (($identity['NPSN'] ?? '') !== $school->npsn) {
            throw new \RuntimeException('NPSN database ARKAS tidak sesuai dengan sekolah aktif.');
        } $run = DB::connection('school')->table('sync_runs')->insertGetId(['fiscal_year_id' => $year->id, 'source' => 'ARKAS', 'status' => 'RUNNING', 'records_read' => 0, 'records_written' => 0, 'started_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        try {
            $rkas = ArkasPipePayload::decode($this->bridge->execute($source, 'rkas', $year->year), 'rkas');
            $bku = ArkasPipePayload::decode($this->bridge->execute($source, 'bku', $year->year), 'bku');
            DB::connection('school')->transaction(function () use ($year, $rkas, $bku) {
                $this->saveRkas($year, $rkas);
                $this->saveBkuAndTransactions($year, $bku);
            });
            DB::connection('school')->table('sync_runs')->where('id', $run)->update(['status' => 'SUCCESS', 'records_read' => count($rkas) + count($bku), 'records_written' => count($rkas) + count($bku), 'finished_at' => now(), 'updated_at' => now()]);
            $source->forceFill(['last_identity' => json_encode($identity, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE), 'last_synced_at' => now()])->save();

            return ['rkas' => count($rkas), 'bku' => count($bku)];
        } catch (\Throwable $e) {
            DB::connection('school')->table('sync_runs')->where('id', $run)->update(['status' => 'FAILED', 'message' => $e->getMessage(), 'finished_at' => now(), 'updated_at' => now()]);
            throw $e;
        }
    }

    private function saveRkas(FiscalYear $year, array $records): void
    {
        $ids = array_values(array_filter(array_map(fn (array $record) => (string) ($record['ID_RAPBS'] ?? ''), $records)));
        $query = DB::connection('school')->table('arkas_rkas_items')->where('fiscal_year_id', $year->id);
        if ($ids === []) {
            $query->delete();
        } else {
            $query->whereNotIn('source_rapbs_id', $ids)->delete();
        } foreach ($records as $r) {
            DB::connection('school')->table('arkas_rkas_items')->updateOrInsert(['fiscal_year_id' => $year->id, 'source_rapbs_id' => $r['ID_RAPBS']], ['fund_source_id' => $r['ID_REF_SUMBER_DANA'] ?? $year->fund_source_id, 'activity_code' => $r['KODE_KEGIATAN'] ?? null, 'activity_name' => $r['NAMA_KEGIATAN'] ?? null, 'account_code' => $r['KODE_REKENING'] ?? null, 'description' => $r['URAIAN'] ?? null, 'amount' => $this->amount($r['JUMLAH'] ?? 0), 'payload' => json_encode($r, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE), 'updated_at' => now(), 'created_at' => now()]);
        }
    }

    private function saveBkuAndTransactions(FiscalYear $year, array $records): void
    {
        $belanja = [];
        $tax = [];
        $rkas = DB::connection('school')->table('arkas_rkas_items')->where('fiscal_year_id', $year->id)->get()->keyBy('source_rapbs_id');
        foreach ($records as $r) {
            DB::connection('school')->table('arkas_bku_rows')->updateOrInsert(['fiscal_year_id' => $year->id, 'source_kas_id' => $r['ID_KAS_UMUM']], ['fund_source_id' => $r['ID_REF_SUMBER_DANA'] ?? $year->fund_source_id, 'parent_kas_id' => $r['PARENT_ID_KAS_UMUM'] ?? null, 'category' => $r['KATEGORI_BKU'] ?? null, 'no_bukti' => $r['NO_BUKTI'] ?? null, 'transaction_date' => $r['TANGGAL_TRANSAKSI'] ?: null, 'amount' => $this->amount($r['JUMLAH'] ?? 0), 'payload' => json_encode($r, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE), 'updated_at' => now(), 'created_at' => now()]);
            if (($r['KATEGORI_BKU'] ?? '') === 'BELANJA' && ! empty($r['NO_BUKTI'])) {
                $belanja[$r['NO_BUKTI']][] = $r;
            } if (($r['KATEGORI_BKU'] ?? '') === 'PAJAK' && ! empty($r['PARENT_ID_KAS_UMUM']) && ! $this->isTaxDeposit($r)) {
                $tax[$r['PARENT_ID_KAS_UMUM']][] = $r;
            }
        } foreach ($belanja as $no => $items) {
            $first = $items[0];
            $rk = $rkas[$first['ID_RAPBS'] ?? ''] ?? null;
            $gross = array_sum(array_map(fn ($x) => $this->amount($x['JUMLAH'] ?? 0), $items));
            $taxes = ['ppn' => 0, 'pph21' => 0, 'pph22' => 0, 'pph23' => 0, 'pph4' => 0, 'sspd' => 0];
            foreach ($items as $item) {
                foreach ($tax[$item['ID_KAS_UMUM']] ?? [] as $p) {
                    $value = $this->amount($p['JUMLAH'] ?? 0);
                    if (($p['IS_PPN'] ?? 0) == 1) {
                        $taxes['ppn'] += $value;
                    } elseif (($p['IS_PPH21'] ?? 0) == 1) {
                        $taxes['pph21'] += $value;
                    } elseif (($p['IS_PPH22'] ?? 0) == 1) {
                        $taxes['pph22'] += $value;
                    } elseif (($p['IS_PPH23'] ?? 0) == 1) {
                        $taxes['pph23'] += $value;
                    } elseif (($p['IS_PPH4'] ?? 0) == 1) {
                        $taxes['pph4'] += $value;
                    } elseif (($p['IS_SSPD'] ?? 0) == 1) {
                        $taxes['sspd'] += $value;
                    }
                }
            } $total = array_sum($taxes);
            $existing = DB::connection('school')->table('transactions')->where(['fiscal_year_id' => $year->id, 'no_bukti' => $no])->first();
            $isSiplah = (bool) ($first['IS_SIPLAH'] ?? false);
            $data = ['fund_source_id' => $first['ID_REF_SUMBER_DANA'] ?? $year->fund_source_id, 'id_kas_umum' => $first['ID_KAS_UMUM'], 'transaction_date' => $first['TANGGAL_TRANSAKSI'], 'description' => $first['URAIAN'] ?? null, 'payment_method' => $this->paymentMethod($first), 'activity_code' => $rk->activity_code ?? null, 'activity_name' => $rk->activity_name ?? null, 'account_code' => $first['KODE_REKENING'] ?? null, 'account_name' => $rk->description ?? null, 'recipient_name' => $first['NAMA_TOKO'] ?? null, 'gross_amount' => $gross, 'ppn' => $taxes['ppn'], 'pph21' => $taxes['pph21'], 'pph22' => $taxes['pph22'], 'pph23' => $taxes['pph23'], 'pph4' => $taxes['pph4'], 'sspd' => $taxes['sspd'], 'tax_total' => $total, 'net_amount' => $gross - $total, 'is_siplah' => $isSiplah, 'updated_at' => now()];
            if ($existing) {
                DB::connection('school')->table('transactions')->where('id', $existing->id)->update($data);
                $id = $existing->id;
            } else {
                $id = DB::connection('school')->table('transactions')->insertGetId($data + ['fiscal_year_id' => $year->id, 'no_bukti' => $no, 'status' => 'DRAFT', 'created_at' => now()]);
            } foreach ($items as $item) {
                $amount = $this->amount($item['JUMLAH'] ?? 0);
                DB::connection('school')->table('transaction_items')->updateOrInsert(['transaction_id' => $id, 'source_item_id' => $item['ID_KAS_UMUM']], ['description' => $item['URAIAN'] ?? '', 'quantity' => $this->amount($item['VOLUME'] ?? 1), 'unit_price' => $amount / max(1, $this->amount($item['VOLUME'] ?? 1)), 'amount' => $amount, 'updated_at' => now(), 'created_at' => now()]);
            }
        }
    }

    /** @param array<string, mixed> $record */
    private function isTaxDeposit(array $record): bool
    {
        $code = strtoupper(trim((string) ($record['KODE_BKU'] ?? '')));
        $description = mb_strtolower(trim(implode(' ', [$record['REK_BKU'] ?? '', $record['URAIAN'] ?? ''])));

        return $code === 'PBS' || str_contains($description, 'pajak belanja setor') || str_starts_with($description, 'setor ');
    }

    private function amount($value): float
    {
        return abs((float) str_replace(',', '.', (string) $value));
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
}
