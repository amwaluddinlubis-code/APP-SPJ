<?php

namespace App\Services;

use App\Models\Transaction;

class SpjDocumentRequirementService
{
    public function __construct(private SpjProcurementPolicyService $procurementPolicy) {}

    /**
     * Menentukan dokumen/data yang wajib, opsional, atau tidak berlaku untuk satu transaksi.
     *
     * @return array<int,array{
     *     key:string,
     *     group:string,
     *     label:string,
     *     source:string,
     *     required:bool,
     *     applicable:bool,
     *     available:bool,
     *     status:string,
     *     message:string
     * }>
     */
    public function forTransaction(Transaction $transaction): array
    {
        $policy = $this->procurementPolicy->forTransaction($transaction);
        $isSiplah = $policy['channel'] === 'SIPLAH';
        $category = strtoupper((string) ($transaction->spj_category ?: 'LAINNYA'));
        $requirements = [];

        $add = function (
            string $key,
            string $group,
            string $label,
            string $source,
            bool $required,
            bool $applicable,
            bool $available,
            string $readyMessage,
            string $missingMessage,
        ) use (&$requirements): void {
            $status = ! $applicable
                ? 'TIDAK_BERLAKU'
                : ($available ? 'TERSEDIA' : ($required ? 'WAJIB_BELUM_LENGKAP' : 'OPSIONAL_BELUM_LENGKAP'));

            $requirements[] = [
                'key' => $key,
                'group' => $group,
                'label' => $label,
                'source' => $source,
                'required' => $required,
                'applicable' => $applicable,
                'available' => $available,
                'status' => $status,
                'message' => ! $applicable ? 'Tidak diperlukan untuk transaksi ini.' : ($available ? $readyMessage : $missingMessage),
            ];
        };

        $a2Ready = filled($transaction->effective_receipt_recipient_name)
            && filled($transaction->payment_description ?: $transaction->description)
            && filled($transaction->payment_method)
            && (float) $transaction->gross_amount > 0;

        $add(
            'a2', 'Dokumen inti', 'Kuitansi / Bukti Kas Pengeluaran (A2)', 'Dibuat aplikasi',
            true, true, $a2Ready,
            'Data A2 lengkap dan siap dibuat/dicetak.',
            'A2 wajib untuk transaksi SIPLah maupun Non-SIPLah. Lengkapi penerima, uraian, cara bayar, dan nilai transaksi.'
        );
        $add(
            'transaction_details', 'Dokumen inti', 'Rincian transaksi', 'ARKAS/BKU & aplikasi',
            true, true, $transaction->items->isNotEmpty(),
            'Rincian transaksi tersedia.',
            'Rincian barang/jasa belum tersedia.'
        );
        $add(
            'payment_evidence', 'Pembayaran', 'Bukti / referensi pembayaran', $isSiplah ? 'SIPLah / bank' : 'Bank / kas / bukti bayar',
            false, true, $transaction->payments->isNotEmpty() || filled($transaction->payment_reference),
            'Bukti atau referensi pembayaran tersedia.',
            'Bukti atau referensi pembayaran belum tersedia. Data ini bersifat pendukung dan tidak memblokir cetak.'
        );

        $taxApplies = (float) $transaction->tax_total > 0;
        $add(
            'tax_evidence', 'Pajak', 'Bukti setor / bukti pajak', 'Dokumen sumber pajak',
            false, $taxApplies, false,
            'Bukti pajak tersedia.',
            'Nilai pajak sudah tercatat. Bukti setor/pajak perlu dicocokkan secara manual sampai fitur unggah bukti tersedia.'
        );

        $purchaseCategory = in_array($category, ['BARANG', 'BELANJA_MODAL', 'KONSUMSI', 'JASA', 'JASA_LAINNYA'], true);
        $goodsCategory = in_array($category, ['BARANG', 'BELANJA_MODAL', 'KONSUMSI'], true);
        $firstGoods = $transaction->goods->first();

        $add(
            'siplah_order', 'Pengadaan', 'Pesanan / referensi transaksi SIPLah', 'SIPLah',
            true, $isSiplah && $purchaseCategory,
            filled($transaction->siplah_order_number) || filled($transaction->payment_reference) || filled($transaction->invoice_number),
            'Referensi pengadaan SIPLah tersedia.',
            'Referensi pesanan/transaksi SIPLah belum tersedia.'
        );
        $add(
            'vendor', 'Pengadaan', 'Identitas penyedia', $isSiplah ? 'SIPLah' : 'Dokumen pengadaan',
            $purchaseCategory, $purchaseCategory,
            filled($transaction->vendor_name),
            'Identitas penyedia tersedia.',
            'Nama penyedia belum tersedia.'
        );
        $add(
            'invoice', 'Pengadaan', 'Invoice / faktur / tagihan', $isSiplah ? 'SIPLah / penyedia' : 'Penyedia',
            false, $purchaseCategory,
            filled($transaction->invoice_number),
            'Invoice/faktur/tagihan tersedia.',
            'Nomor invoice/faktur/tagihan belum tersedia. Data ini bersifat pendukung dan tidak memblokir cetak.'
        );

        /*
         * Surat Pesanan Internal memiliki dua tahap validasi:
         * 1) isi/substansi wajib lengkap sebelum paket boleh READY/dinomori;
         * 2) nomor surat baru wajib setelah paket sudah NUMBERED/FINAL.
         * Nomor yang diterbitkan aplikasi tidak boleh menjadi blocker sebelum proses penomoran.
         *
         * Field `unit` berasal dari rincian sumber ARKAS dan tidak dapat dilengkapi melalui
         * form uraian SPJ operator. Karena itu `unit` tidak boleh menjadi hidden blocker.
         * Kelengkapan item mengikuti validasi barang canonical: uraian, jumlah, dan harga.
         *
         * UI Isian Manual menampilkan `recipient_name` sebagai fallback pada field Nama Toko
         * ketika `vendor_name` masih kosong. Validasi harus memakai fallback yang sama agar
         * nilai yang terlihat lengkap di UI tidak ditolak sebagai penyedia kosong.
         */
        $internalOrderApplicable = ! $isSiplah && $goodsCategory;
        $providerName = $transaction->vendor_name ?: $transaction->recipient_name;
        $orderDate = $firstGoods?->order_date ?: $transaction->transaction_date;
        $orderItemsComplete = $transaction->items->isNotEmpty()
            && $transaction->items->every(fn ($item) => filled($item->item_description ?: $item->description)
                && (float) $item->quantity > 0
                && (float) $item->unit_price >= 0
            );
        $internalOrderContentReady = filled($providerName)
            && filled($orderDate)
            && $orderItemsComplete
            && (float) $transaction->gross_amount > 0;

        $missingInternalOrderParts = collect([
            blank($providerName) ? 'penyedia' : null,
            blank($orderDate) ? 'tanggal pesanan' : null,
            $transaction->items->isEmpty() ? 'rincian barang' : null,
            $transaction->items->contains(fn ($item) => blank($item->item_description ?: $item->description)) ? 'uraian barang' : null,
            $transaction->items->contains(fn ($item) => (float) $item->quantity <= 0) ? 'jumlah barang' : null,
            $transaction->items->contains(fn ($item) => (float) $item->unit_price < 0) ? 'harga barang' : null,
            (float) $transaction->gross_amount <= 0 ? 'nilai transaksi' : null,
        ])->filter()->values();
        $internalOrderMissingMessage = $missingInternalOrderParts->isEmpty()
            ? 'Isi Surat Pesanan belum lengkap.'
            : 'Isi Surat Pesanan belum lengkap pada: '.$missingInternalOrderParts->implode(', ').'.';

        $add(
            'internal_order_content', 'Pengadaan', 'Kelengkapan isi Surat Pesanan', 'Dibuat aplikasi',
            $internalOrderApplicable, $internalOrderApplicable,
            $internalOrderContentReady,
            'Isi Surat Pesanan lengkap dan siap masuk proses penomoran.',
            $internalOrderMissingMessage
        );

        $packageStatus = strtoupper((string) ($transaction->spjPackage?->status ?? ''));
        $internalOrderNumberRequired = $internalOrderApplicable && in_array($packageStatus, ['NUMBERED', 'FINAL'], true);
        $internalOrderNumber = $firstGoods?->order_number ?: $transaction->order_number;
        $add(
            'internal_order_number', 'Pengadaan', 'Nomor Surat Pesanan', 'Diterbitkan aplikasi',
            $internalOrderNumberRequired, $internalOrderApplicable,
            filled($internalOrderNumber),
            'Nomor Surat Pesanan sudah diterbitkan.',
            $internalOrderNumberRequired
                ? 'Paket sudah bernomor/final tetapi Nomor Surat Pesanan belum tersedia. Jalankan atau perbaiki penomoran dokumen PESANAN.'
                : 'Nomor Surat Pesanan belum diterbitkan. Nomor ini tidak memblokir tahap persiapan dan akan diwajibkan setelah paket bernomor.'
        );

        /*
         * Bukti penerimaan mempunyai dua tahap yang sama seperti Surat Pesanan:
         * data/peristiwa penerimaan harus sudah tersedia sebelum penomoran, tetapi
         * nomor BAP/BAST diterbitkan oleh proses penomoran dan tidak boleh menjadi
         * prasyarat bagi proses yang menerbitkannya.
         */
        $receiptReady = $transaction->goodsReceipts->isNotEmpty()
            || $transaction->goods->contains(fn ($goods): bool => filled($goods->bap_date) || filled($goods->bast_date));
        $add(
            'goods_receipt', 'Penerimaan', 'Bukti penerimaan barang', $isSiplah ? 'SIPLah / dokumen penerimaan' : 'Aplikasi / dokumen sumber',
            ! $isSiplah && $goodsCategory, ! $isSiplah && $goodsCategory, $receiptReady,
            'Data penerimaan barang tersedia dan siap masuk proses penomoran.',
            'Belum ada data penerimaan barang atau tanggal BAP/BAST.'
        );
        $add(
            'bap', 'Penerimaan', 'Berita Acara Pemeriksaan/Penerimaan (BAP)', 'Dibuat aplikasi',
            false, ! $isSiplah && $goodsCategory,
            filled($firstGoods?->bap_number) && filled($firstGoods?->bap_date),
            'BAP tersedia.',
            'BAP belum dibuat. Dokumen ini dapat diwajibkan sesuai jenis/nilai pengadaan dan kebijakan sekolah.'
        );
        $add(
            'bast', 'Penerimaan', 'Berita Acara Serah Terima (BAST)', 'Dibuat aplikasi',
            false, ! $isSiplah && $goodsCategory,
            filled($firstGoods?->bast_number) && filled($firstGoods?->bast_date),
            'BAST tersedia.',
            'BAST belum dibuat. Dokumen ini dapat diwajibkan sesuai jenis/nilai pengadaan dan kebijakan sekolah.'
        );

        $workCategory = in_array($category, ['PEMELIHARAAN', 'UPAH'], true);
        $add(
            'work_rab', 'Pekerjaan', 'RAB pekerjaan', 'Dibuat aplikasi',
            $workCategory, $workCategory,
            filled($transaction->workOrder?->rab_date),
            'RAB pekerjaan tersedia.',
            'Tanggal RAB pekerjaan belum diisi.'
        );
        $add(
            'work_spk', 'Pekerjaan', 'SPK pekerjaan', 'Dibuat aplikasi',
            $workCategory, $workCategory,
            filled($transaction->workOrder?->spk_date),
            'SPK pekerjaan tersedia.',
            'Tanggal SPK pekerjaan belum diisi.'
        );
        $add(
            'workers', 'Pekerjaan', 'Daftar pekerja / penerima upah', 'Dibuat aplikasi',
            $workCategory, $workCategory,
            $transaction->workers->isNotEmpty(),
            'Daftar pekerja/upah tersedia.',
            'Daftar pekerja/upah belum tersedia.'
        );

        $travelCategory = in_array($category, ['SPPD', 'PERJALANAN_DINAS'], true);
        $add(
            'travel', 'Perjalanan dinas', 'Rincian perjalanan dinas', 'Dibuat aplikasi',
            $travelCategory, $travelCategory,
            $transaction->travels->isNotEmpty(),
            'Rincian perjalanan dinas tersedia.',
            'Rincian perjalanan dinas belum tersedia.'
        );

        $honorCategory = in_array($category, ['HONOR_PEGAWAI', 'JASA_HONORARIUM'], true);
        $add(
            'honor', 'Honorarium', 'Daftar penerima honor', 'Dibuat aplikasi',
            $honorCategory, $honorCategory,
            $transaction->honors->isNotEmpty(),
            'Daftar penerima honor tersedia.',
            'Daftar penerima honor belum tersedia.'
        );

        $consumptionCategory = $category === 'KONSUMSI';
        $add(
            'participants', 'Konsumsi', 'Daftar peserta / penerima konsumsi', 'Dibuat aplikasi',
            $consumptionCategory, $consumptionCategory,
            $transaction->participants->isNotEmpty(),
            'Daftar peserta/penerima konsumsi tersedia.',
            'Daftar peserta/penerima konsumsi belum tersedia.'
        );

        return $requirements;
    }

    /** @return array<int,array<string,mixed>> */
    public function blockingRequirements(Transaction $transaction): array
    {
        return collect($this->forTransaction($transaction))
            ->filter(fn (array $item): bool => $item['applicable'] && $item['required'] && ! $item['available'])
            ->values()
            ->all();
    }

    public function summary(Transaction $transaction): array
    {
        $items = collect($this->forTransaction($transaction));
        $applicable = $items->where('applicable', true);
        $required = $applicable->where('required', true);
        $requiredReady = $required->where('available', true)->count();

        return [
            'channel' => $this->procurementPolicy->forTransaction($transaction)['channel_label'],
            'total_applicable' => $applicable->count(),
            'required_total' => $required->count(),
            'required_ready' => $requiredReady,
            'missing_required' => $required->count() - $requiredReady,
            'is_ready' => $required->count() === $requiredReady,
        ];
    }
}
