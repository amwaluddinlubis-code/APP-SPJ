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
        $add(
            'internal_order', 'Pengadaan', 'Surat pesanan internal', 'Dibuat aplikasi',
            ! $isSiplah && $goodsCategory, ! $isSiplah && $goodsCategory,
            filled($firstGoods?->order_number) && filled($firstGoods?->order_date),
            'Surat pesanan internal tersedia.',
            'Surat pesanan internal belum lengkap.'
        );

        $receiptReady = $transaction->goodsReceipts->isNotEmpty()
            || (filled($firstGoods?->bap_number) && filled($firstGoods?->bap_date))
            || (filled($firstGoods?->bast_number) && filled($firstGoods?->bast_date));
        $add(
            'goods_receipt', 'Penerimaan', 'Bukti penerimaan barang', $isSiplah ? 'SIPLah / dokumen penerimaan' : 'Aplikasi / dokumen sumber',
            $goodsCategory, $goodsCategory, $receiptReady,
            'Bukti penerimaan barang tersedia.',
            $isSiplah
                ? 'Transaksi SIPLah tetap memerlukan bukti penerimaan barang yang dapat ditelusuri.'
                : 'Belum ada bukti penerimaan barang, BAP, atau BAST.'
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
            filled($transaction->workOrder?->rab_number) && filled($transaction->workOrder?->rab_date),
            'RAB pekerjaan tersedia.',
            'RAB pekerjaan belum lengkap.'
        );
        $add(
            'work_spk', 'Pekerjaan', 'SPK pekerjaan', 'Dibuat aplikasi',
            $workCategory, $workCategory,
            filled($transaction->workOrder?->spk_number) && filled($transaction->workOrder?->spk_date),
            'SPK pekerjaan tersedia.',
            'SPK pekerjaan belum lengkap.'
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
