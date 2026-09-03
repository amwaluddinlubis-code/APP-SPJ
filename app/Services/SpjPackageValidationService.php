<?php

namespace App\Services;

use App\Models\SpjPackage;

class SpjPackageValidationService
{
    /** @return array<int,array{label:string,message:string,url:string}> */
    public function validate(SpjPackage $package): array
    {
        $transaction = $package->transaction;
        $url = route('transactions.show', $transaction->id);
        $issues = [];
        $required = [
            ['Penerima kuitansi', $transaction->effective_receipt_recipient_name],
            ['Kode kegiatan', $transaction->activity_code],
            ['Nama kegiatan', $transaction->activity_name],
            ['Kode rekening', $transaction->account_code],
            ['Nama rekening', $transaction->account_name],
            ['Cara bayar', $transaction->payment_method],
        ];
        foreach ($required as [$label, $value]) {
            if (blank($value)) {
                $issues[] = ['label' => $label, 'message' => $label.' belum tersedia.', 'url' => $url];
            }
        }
        if ($transaction->items->isEmpty() && $transaction->goods->isEmpty()) {
            $issues[] = ['label' => 'Rincian barang/jasa', 'message' => 'Transaksi belum memiliki rincian barang atau jasa.', 'url' => $url];
        }
        $grossAmount = (float) $transaction->gross_amount;
        $itemTotal = (float) $transaction->items->sum('amount');
        if ($transaction->items->isNotEmpty() && abs($itemTotal - $grossAmount) > 0.01) {
            $issues[] = [
                'label' => 'Total rincian transaksi',
                'message' => sprintf('Total rincian Rp %s tidak sama dengan nilai bruto Rp %s.', number_format($itemTotal, 0, ',', '.'), number_format($grossAmount, 0, ',', '.')),
                'url' => $url.'#rincian-transaksi',
            ];
        }
        if (strtoupper((string) $transaction->spj_category) === 'BARANG') {
            if ($transaction->items->isEmpty()) {
                $issues[] = ['label' => 'Barang pesanan', 'message' => 'Pesanan wajib memiliki minimal satu barang.', 'url' => $url.'#rincian-transaksi'];
            }

            $incompleteItems = $transaction->items->filter(fn ($item) => blank($item->item_description) || (float) $item->quantity <= 0 || (float) $item->unit_price < 0);
            if ($incompleteItems->isNotEmpty()) {
                $issues[] = ['label' => 'Kelengkapan barang', 'message' => 'Setiap barang wajib memiliki uraian, jumlah lebih dari nol, dan harga yang valid.', 'url' => $url.'#rincian-transaksi'];
            }

            $invalidAmounts = $transaction->items->filter(fn ($item) => abs(((float) $item->quantity * (float) $item->unit_price) - (float) $item->amount) > 0.01);
            if ($invalidAmounts->isNotEmpty()) {
                $names = $invalidAmounts->map(fn ($item) => $item->item_description ?: $item->description ?: '#'.$item->id)->implode(', ');
                $issues[] = ['label' => 'Nilai item barang', 'message' => 'Jumlah × harga tidak sama dengan nilai item: '.$names.'.', 'url' => $url.'#rincian-transaksi'];
            }

            if ($transaction->goods->isEmpty()) {
                $issues[] = ['label' => 'Dokumen barang', 'message' => 'Data pesanan, BAP, dan BAST belum dibuat.', 'url' => $url.'#modul-buat-spj'];
            }

            $hasBapOrBast = $transaction->goods->contains(fn ($goods) => filled($goods->bap_number) || filled($goods->bap_date) || filled($goods->bast_number) || filled($goods->bast_date));
            if ($hasBapOrBast && ($incompleteItems->isNotEmpty() || $invalidAmounts->isNotEmpty())) {
                $issues[] = ['label' => 'Kelengkapan BAP/BAST', 'message' => 'BAP dan BAST belum dapat diterbitkan sebelum rincian barang lengkap dan konsisten.', 'url' => $url.'#modul-buat-spj'];
            }

            if (filled($transaction->invoice_number) && filled($transaction->vendor_name)) {
                $duplicateExists = $transaction->newQuery()
                    ->where('fiscal_year_id', $transaction->fiscal_year_id)
                    ->whereKeyNot($transaction->id)
                    ->whereRaw('LOWER(TRIM(invoice_number)) = ?', [mb_strtolower(trim((string) $transaction->invoice_number))])
                    ->whereRaw('LOWER(TRIM(vendor_name)) = ?', [mb_strtolower(trim((string) $transaction->vendor_name))])
                    ->exists();
                if ($duplicateExists) {
                    $issues[] = ['label' => 'Duplikasi invoice', 'message' => 'Nomor invoice ini sudah digunakan oleh vendor yang sama pada tahun anggaran aktif.', 'url' => $url.'#modul-buat-spj'];
                }
            }
        }
        if (strtoupper((string) $transaction->spj_category) === 'HONOR_PEGAWAI') {
            if ($transaction->honors->isEmpty()) {
                $issues[] = ['label' => 'Rincian penerima honorarium', 'message' => 'Honor Pegawai memerlukan minimal satu penerima.', 'url' => $url.'#modul-buat-spj'];
            } else {
                $honorTotal = (float) $transaction->honors->sum('gross_amount');
                if (abs($honorTotal - $grossAmount) > 0.01) {
                    $issues[] = [
                        'label' => 'Total honor',
                        'message' => sprintf('Total honor Rp %s tidak sama dengan nilai bruto Rp %s.', number_format($honorTotal, 0, ',', '.'), number_format($grossAmount, 0, ',', '.')),
                        'url' => $url.'#modul-buat-spj',
                    ];
                }
            }
        }

        return $issues;
    }
}
