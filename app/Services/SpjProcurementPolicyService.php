<?php

namespace App\Services;

use App\Models\Transaction;

class SpjProcurementPolicyService
{
    /**
     * @return array{
     *     channel:string,
     *     channel_label:string,
     *     a2_required:bool,
     *     procurement_evidence:array<int,string>,
     *     internal_documents:array<int,string>,
     *     notes:array<int,string>
     * }
     */
    public function forTransaction(Transaction $transaction): array
    {
        $isSiplah = (bool) $transaction->is_siplah || strtolower((string) $transaction->payment_method) === 'siplah';

        if ($isSiplah) {
            return [
                'channel' => 'SIPLAH',
                'channel_label' => 'SIPLah',
                'a2_required' => true,
                'procurement_evidence' => [
                    'Pesanan/transaksi marketplace SIPLah',
                    'Invoice atau tagihan dari penyedia',
                    'Rincian barang/jasa dari SIPLah',
                    'Bukti pembayaran SIPLah/bank',
                    'Bukti penerimaan barang/jasa',
                    'Bukti pajak jika ada kewajiban pajak',
                ],
                'internal_documents' => [
                    'Kuitansi / Bukti Kas Pengeluaran (A2)',
                    'Bukti penerimaan barang/jasa yang dapat ditelusuri',
                    'Dokumen SPJ lain sesuai kategori transaksi',
                ],
                'notes' => [
                    'Dokumen pemesanan, invoice, dan bukti transaksi dari marketplace SIPLah menjadi sumber utama pengadaan dan tidak dibuat ulang oleh aplikasi.',
                    'Surat Pesanan internal, BAP internal, dan BAST internal tidak diwajibkan untuk transaksi SIPLah; dokumen tersebut hanya dibuat jika kebijakan sekolah atau kebutuhan khusus mengharuskannya.',
                    'Bukti penerimaan barang/jasa tetap harus tersedia dan dapat ditelusuri.',
                    'Kuitansi/A2 tetap wajib dibuat dan dicetak dari aplikasi sebagai bukti pengeluaran internal sekolah.',
                    'Nilai SIPLah, BKU, transaksi, pembayaran, pajak, dan SPJ harus dapat ditelusuri dan direkonsiliasi.',
                ],
            ];
        }

        return [
            'channel' => 'NON_SIPLAH',
            'channel_label' => 'Non-SIPLah',
            'a2_required' => true,
            'procurement_evidence' => [
                'Dokumen pemesanan/pengadaan sesuai jenis transaksi',
                'Invoice, faktur, nota, atau tagihan dari penyedia',
                'Rincian barang/jasa',
                'Bukti pembayaran',
                'Bukti penerimaan barang/jasa',
                'Bukti pajak jika ada kewajiban pajak',
            ],
            'internal_documents' => [
                'Kuitansi / Bukti Kas Pengeluaran (A2)',
                'Surat pesanan/SPK jika diperlukan',
                'Berita acara/penerimaan yang diperlukan sesuai jenis belanja',
                'Dokumen SPJ lain sesuai kategori transaksi',
            ],
            'notes' => [
                'Dokumen pengadaan disiapkan sesuai jenis belanja dan cara pengadaan.',
                'Kuitansi/A2 wajib dibuat dan dicetak dari aplikasi.',
                'Nilai BKU, transaksi, pembayaran, pajak, dan SPJ harus dapat ditelusuri dan direkonsiliasi.',
            ],
        ];
    }

    public function isSiplah(Transaction $transaction): bool
    {
        return $this->forTransaction($transaction)['channel'] === 'SIPLAH';
    }
}
