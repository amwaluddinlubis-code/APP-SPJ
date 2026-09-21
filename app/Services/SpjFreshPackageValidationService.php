<?php

namespace App\Services;

use App\Models\SpjFreshPackage;

final class SpjFreshPackageValidationService
{
    /** @return array<int, array{key:string,label:string,message:string}> */
    public function validate(SpjFreshPackage $package): array
    {
        $transaction = $package->transaction;
        if ($transaction === null) {
            return [['key' => 'transaction', 'label' => 'Transaksi sumber', 'message' => 'Transaksi fresh tidak ditemukan.']];
        }

        $issues = [];
        $this->required($issues, 'spj_category', 'Kategori SPJ', $transaction->spj_category, 'Kategori SPJ wajib dipilih sebelum paket diproses.');
        $this->required($issues, 'payment_description', 'Uraian pembayaran', $transaction->payment_description ?: $transaction->description, 'Uraian pembayaran wajib diisi.');
        $this->required($issues, 'recipient', 'Penerima kuitansi', $transaction->effective_receipt_recipient_name, 'Penerima kuitansi wajib diisi.');
        $this->required($issues, 'payment_method', 'Cara bayar', $transaction->payment_method, 'Cara bayar wajib dipilih.');

        if ((float) $transaction->gross_amount <= 0) {
            $issues[] = ['key' => 'gross_amount', 'label' => 'Nilai transaksi', 'message' => 'Nilai bruto transaksi harus lebih besar dari nol.'];
        }

        if ($transaction->items->isEmpty()) {
            $issues[] = ['key' => 'items', 'label' => 'Rincian transaksi', 'message' => 'Transaksi fresh harus memiliki minimal satu item.'];
        } else {
            foreach ($transaction->items as $item) {
                if (blank(trim((string) $item->item_description))) {
                    $issues[] = ['key' => 'item_description', 'label' => 'Uraian item', 'message' => 'Setiap item wajib memiliki uraian.'];
                    break;
                }
            }
        }

        return $issues;
    }

    /** @param array<int, array{key:string,label:string,message:string}> $issues */
    private function required(array &$issues, string $key, string $label, mixed $value, string $message): void
    {
        if (filled($value)) {
            return;
        }

        $issues[] = compact('key', 'label', 'message');
    }
}
