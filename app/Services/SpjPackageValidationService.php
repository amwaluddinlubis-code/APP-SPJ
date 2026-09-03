<?php

namespace App\Services;

use App\Models\SpjPackage;

class SpjPackageValidationService
{
    public function __construct(private SpjProcurementPolicyService $procurementPolicy) {}

    /**
     * @return array<int,array{key:string,group:string,label:string,passed:bool,message:string,url:string}>
     */
    public function checklist(SpjPackage $package): array
    {
        $transaction = $package->transaction;
        $url = route('transactions.show', $transaction->id);
        $checks = [];
        $policy = $this->procurementPolicy->forTransaction($transaction);
        $channel = $policy['channel_label'];

        $this->addCheck($checks, 'procurement_channel', 'Cara pengadaan', 'Jalur transaksi', true, 'Transaksi dikenali sebagai '.$channel.'.', 'Jalur transaksi belum dapat dikenali.', $url.'#modul-buat-spj');
        $this->addCheck($checks, 'recipient', 'Umum', 'Penerima kuitansi', filled($transaction->effective_receipt_recipient_name), 'Penerima kuitansi sudah tersedia.', 'Penerima kuitansi belum tersedia.', $url);
        $this->addCheck($checks, 'activity_code', 'Umum', 'Kode kegiatan', filled($transaction->activity_code), 'Kode kegiatan sudah tersedia.', 'Kode kegiatan belum tersedia.', $url);
        $this->addCheck($checks, 'activity_name', 'Umum', 'Nama kegiatan', filled($transaction->activity_name), 'Nama kegiatan sudah tersedia.', 'Nama kegiatan belum tersedia.', $url);
        $this->addCheck($checks, 'account_code', 'Umum', 'Kode rekening', filled($transaction->account_code), 'Kode rekening sudah tersedia.', 'Kode rekening belum tersedia.', $url);
        $this->addCheck($checks, 'account_name', 'Umum', 'Nama rekening', filled($transaction->account_name), 'Nama rekening sudah tersedia.', 'Nama rekening belum tersedia.', $url);
        $this->addCheck($checks, 'payment_method', 'Umum', 'Cara bayar', filled($transaction->payment_method), 'Cara bayar sudah dipilih.', 'Cara bayar belum tersedia.', $url.'#modul-buat-spj');

        $a2Ready = filled($transaction->effective_receipt_recipient_name)
            && filled($transaction->payment_description ?: $transaction->description)
            && (float) $transaction->gross_amount > 0;
        $this->addCheck(
            $checks,
            'receipt_a2',
            'Dokumen wajib',
            'Kuitansi / Bukti Kas Pengeluaran (A2)',
            $a2Ready,
            'Data Kuitansi/A2 lengkap dan wajib dicetak untuk transaksi '.$channel.'.',
            'Kuitansi/A2 wajib untuk transaksi '.$channel.'. Lengkapi penerima, uraian pembayaran, dan nilai transaksi agar dapat dicetak.',
            $url.'#modul-buat-spj'
        );

        if ($policy['channel'] === 'SIPLAH') {
            $hasSiplahReference = filled($transaction->payment_reference) || filled($transaction->invoice_number);
            $this->addCheck(
                $checks,
                'siplah_reference',
                'Dokumen SIPLah',
                'Referensi pesanan/invoice SIPLah',
                $hasSiplahReference,
                'Referensi SIPLah tersedia.',
                'Transaksi SIPLah perlu memiliki referensi pesanan atau nomor invoice agar mudah ditelusuri.',
                $url.'#modul-buat-spj'
            );

            $this->addCheck(
                $checks,
                'siplah_vendor',
                'Dokumen SIPLah',
                'Identitas penyedia SIPLah',
                filled($transaction->vendor_name),
                'Penyedia SIPLah sudah tercatat.',
                'Nama penyedia SIPLah belum tersedia.',
                $url.'#modul-buat-spj'
            );
        }

        $hasDetails = $transaction->items->isNotEmpty() || $transaction->goods->isNotEmpty();
        $this->addCheck($checks, 'details', 'Rincian transaksi', 'Rincian barang/jasa', $hasDetails, 'Transaksi memiliki rincian barang/jasa.', 'Transaksi belum memiliki rincian barang atau jasa.', $url.'#rincian-transaksi');

        $grossAmount = (float) $transaction->gross_amount;
        $itemTotal = (float) $transaction->items->sum('amount');
        $itemTotalMatches = $transaction->items->isEmpty() || abs($itemTotal - $grossAmount) <= 0.01;
        $itemTotalMessage = $itemTotalMatches
            ? 'Total rincian sesuai dengan nilai bruto transaksi.'
            : sprintf('Total rincian Rp %s tidak sama dengan nilai bruto Rp %s.', number_format($itemTotal, 0, ',', '.'), number_format($grossAmount, 0, ',', '.'));
        $this->addCheck($checks, 'item_total', 'Rincian transaksi', 'Total rincian transaksi', $itemTotalMatches, $itemTotalMessage, $itemTotalMessage, $url.'#rincian-transaksi');

        $category = strtoupper((string) $transaction->spj_category);

        if ($category === 'BARANG') {
            $hasItems = $transaction->items->isNotEmpty();
            $this->addCheck($checks, 'goods_items', 'Belanja barang', 'Barang pesanan', $hasItems, 'Pesanan memiliki rincian barang.', 'Pesanan wajib memiliki minimal satu barang.', $url.'#rincian-transaksi');

            $incompleteItems = $transaction->items->filter(fn ($item) => blank($item->item_description) || (float) $item->quantity <= 0 || (float) $item->unit_price < 0);
            $itemsComplete = $hasItems && $incompleteItems->isEmpty();
            $this->addCheck($checks, 'goods_completeness', 'Belanja barang', 'Kelengkapan barang', $itemsComplete, 'Uraian, jumlah, dan harga setiap barang sudah lengkap.', 'Setiap barang wajib memiliki uraian, jumlah lebih dari nol, dan harga yang valid.', $url.'#rincian-transaksi');

            $invalidAmounts = $transaction->items->filter(fn ($item) => abs(((float) $item->quantity * (float) $item->unit_price) - (float) $item->amount) > 0.01);
            $amountsValid = $hasItems && $invalidAmounts->isEmpty();
            $invalidNames = $invalidAmounts->map(fn ($item) => $item->item_description ?: $item->description ?: '#'.$item->id)->implode(', ');
            $this->addCheck(
                $checks,
                'goods_amounts',
                'Belanja barang',
                'Nilai item barang',
                $amountsValid,
                'Perhitungan jumlah × harga setiap barang sudah konsisten.',
                $invalidNames !== '' ? 'Jumlah × harga tidak sama dengan nilai item: '.$invalidNames.'.' : 'Perhitungan nilai item barang belum lengkap.',
                $url.'#rincian-transaksi'
            );

            $hasGoodsDocuments = $transaction->goods->isNotEmpty();
            $goodsDocumentLabel = $policy['channel'] === 'SIPLAH' ? 'Data penerimaan barang' : 'Dokumen barang';
            $goodsDocumentPass = $policy['channel'] === 'SIPLAH'
                ? $transaction->goodsReceipts->isNotEmpty() || $hasGoodsDocuments
                : $hasGoodsDocuments;
            $this->addCheck(
                $checks,
                'goods_documents',
                'Belanja barang',
                $goodsDocumentLabel,
                $goodsDocumentPass,
                $policy['channel'] === 'SIPLAH'
                    ? 'Data penerimaan barang SIPLah sudah tersedia; dokumen pesanan platform tetap dipertahankan sebagai bukti sumber.'
                    : 'Data pesanan/BAP/BAST sudah dibuat.',
                $policy['channel'] === 'SIPLAH'
                    ? 'Transaksi SIPLah tetap memerlukan bukti penerimaan barang/jasa yang dapat ditelusuri.'
                    : 'Data pesanan, BAP, dan BAST belum dibuat.',
                $url.'#modul-buat-spj'
            );

            $hasBapOrBast = $transaction->goods->contains(fn ($goods) => filled($goods->bap_number) || filled($goods->bap_date) || filled($goods->bast_number) || filled($goods->bast_date));
            $bapBastReady = ! $hasBapOrBast || ($itemsComplete && $amountsValid);
            $this->addCheck($checks, 'goods_bap_bast', 'Belanja barang', 'Kelengkapan BAP/BAST', $bapBastReady, 'Rincian barang konsisten untuk BAP/BAST.', 'BAP dan BAST belum dapat diterbitkan sebelum rincian barang lengkap dan konsisten.', $url.'#modul-buat-spj');

            $duplicateExists = false;
            if (filled($transaction->invoice_number) && filled($transaction->vendor_name)) {
                $duplicateExists = $transaction->newQuery()
                    ->where('fiscal_year_id', $transaction->fiscal_year_id)
                    ->whereKeyNot($transaction->id)
                    ->whereRaw('LOWER(TRIM(invoice_number)) = ?', [mb_strtolower(trim((string) $transaction->invoice_number))])
                    ->whereRaw('LOWER(TRIM(vendor_name)) = ?', [mb_strtolower(trim((string) $transaction->vendor_name))])
                    ->exists();
            }
            $this->addCheck($checks, 'invoice_duplicate', 'Belanja barang', 'Keunikan invoice', ! $duplicateExists, 'Nomor invoice tidak terdeteksi sebagai duplikat.', 'Nomor invoice ini sudah digunakan oleh vendor yang sama pada tahun anggaran aktif.', $url.'#modul-buat-spj');
        }

        if ($category === 'HONOR_PEGAWAI') {
            $hasHonors = $transaction->honors->isNotEmpty();
            $this->addCheck($checks, 'honor_recipients', 'Honor pegawai', 'Rincian penerima honorarium', $hasHonors, 'Daftar penerima honorarium sudah tersedia.', 'Honor Pegawai memerlukan minimal satu penerima.', $url.'#modul-buat-spj');

            $honorTotal = (float) $transaction->honors->sum('gross_amount');
            $honorTotalMatches = $hasHonors && abs($honorTotal - $grossAmount) <= 0.01;
            $honorMessage = $honorTotalMatches
                ? 'Total honor sesuai dengan nilai bruto transaksi.'
                : sprintf('Total honor Rp %s tidak sama dengan nilai bruto Rp %s.', number_format($honorTotal, 0, ',', '.'), number_format($grossAmount, 0, ',', '.'));
            $this->addCheck($checks, 'honor_total', 'Honor pegawai', 'Total honor', $honorTotalMatches, $honorMessage, $honorMessage, $url.'#modul-buat-spj');
        }

        return $checks;
    }

    /** @return array<int,array{label:string,message:string,url:string}> */
    public function validate(SpjPackage $package): array
    {
        return collect($this->checklist($package))
            ->where('passed', false)
            ->map(fn (array $check) => [
                'label' => $check['label'],
                'message' => $check['message'],
                'url' => $check['url'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param array<int,array{key:string,group:string,label:string,passed:bool,message:string,url:string}> $checks
     */
    private function addCheck(array &$checks, string $key, string $group, string $label, bool $passed, string $passedMessage, string $failedMessage, string $url): void
    {
        $checks[] = [
            'key' => $key,
            'group' => $group,
            'label' => $label,
            'passed' => $passed,
            'message' => $passed ? $passedMessage : $failedMessage,
            'url' => $url,
        ];
    }
}
