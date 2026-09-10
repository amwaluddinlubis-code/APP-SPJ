<?php

namespace App\Services;

/**
 * Human guide for the school database table explorer. Technical table
 * names are paired with an Indonesian group, label, and one-line blurb
 * so operators can tell what each table stores without SQL knowledge.
 */
class SchoolDatabaseTableGuide
{
    /**
     * @return array{group:string, label:string, blurb:string}
     */
    public function describe(string $table): array
    {
        return self::DICTIONARY[$table] ?? [
            'group' => 'Sistem',
            'label' => ucwords(str_replace('_', ' ', $table)),
            'blurb' => 'Tabel internal aplikasi.',
        ];
    }

    /** @return array<int, string> */
    public function groups(): array
    {
        return ['Transaksi & BKU', 'Dokumen SPJ', 'Anggaran (ARKAS)', 'Referensi', 'Operasional', 'Sistem'];
    }

    private const DICTIONARY = [
        'transactions' => ['group' => 'Transaksi & BKU', 'label' => 'Transaksi belanja', 'blurb' => 'Setiap bukti belanja dari BKU/ARKAS beserta isian SPJ operator.'],
        'transaction_items' => ['group' => 'Transaksi & BKU', 'label' => 'Rincian transaksi', 'blurb' => 'Rincian barang/jasa per transaksi (uraian, jumlah, harga).'],
        'transaction_payments' => ['group' => 'Transaksi & BKU', 'label' => 'Pembayaran bertahap', 'blurb' => 'Cicilan pembayaran per transaksi.'],
        'transaction_source_events' => ['group' => 'Transaksi & BKU', 'label' => 'Jejak perubahan sumber', 'blurb' => 'Catatan setiap data ARKAS/BKU berubah setelah diproses.'],
        'transaction_source_reconciliations' => ['group' => 'Operasional', 'label' => 'Penyelesaian rekonsiliasi', 'blurb' => 'Hasil peninjauan operator atas data sumber yang berubah/hilang.'],
        'spj_packages' => ['group' => 'Dokumen SPJ', 'label' => 'Paket SPJ', 'blurb' => 'Wadah dokumen SPJ per transaksi: status, nomor, dan siklusnya.'],
        'spj_documents' => ['group' => 'Dokumen SPJ', 'label' => 'Dokumen & nomor', 'blurb' => 'Setiap dokumen dalam paket beserta nomor dan statusnya.'],
        'spj_goods' => ['group' => 'Dokumen SPJ', 'label' => 'Data pesanan barang', 'blurb' => 'Surat pesanan, tanggal, BAP, dan BAST per barang.'],
        'spj_honors' => ['group' => 'Dokumen SPJ', 'label' => 'Penerima honor', 'blurb' => 'Daftar penerima honorarium beserta perhitungannya.'],
        'spj_travels' => ['group' => 'Dokumen SPJ', 'label' => 'Perjalanan dinas', 'blurb' => 'Rincian SPPD: tujuan, tanggal, dan biaya.'],
        'spj_workers' => ['group' => 'Dokumen SPJ', 'label' => 'Pekerja pemeliharaan', 'blurb' => 'Daftar pekerja/upah pada SPJ pemeliharaan.'],
        'spj_work_orders' => ['group' => 'Dokumen SPJ', 'label' => 'Perintah kerja & RAB', 'blurb' => 'SPK, RAB, dan data pekerjaan pemeliharaan.'],
        'spj_participants' => ['group' => 'Dokumen SPJ', 'label' => 'Peserta konsumsi', 'blurb' => 'Daftar hadir/peserta kegiatan konsumsi + NIP/NUPTK.'],
        'spj_service_recipients' => ['group' => 'Dokumen SPJ', 'label' => 'Penerima jasa', 'blurb' => 'Penerima pembayaran jasa lainnya + pajaknya.'],
        'spj_maintenances' => ['group' => 'Dokumen SPJ', 'label' => 'Tautan pemeliharaan', 'blurb' => 'Kaitan transaksi material dan jasa pemeliharaan.'],
        'goods_receipts' => ['group' => 'Dokumen SPJ', 'label' => 'Penerimaan barang', 'blurb' => 'Berita penerimaan barang bertahap per transaksi.'],
        'goods_receipt_items' => ['group' => 'Dokumen SPJ', 'label' => 'Rincian penerimaan', 'blurb' => 'Rincian barang pada tiap penerimaan.'],
        'arkas_rkas_items' => ['group' => 'Anggaran (ARKAS)', 'label' => 'Pagu RKAS', 'blurb' => 'Anggaran per rekening dari hasil sinkronisasi ARKAS.'],
        'arkas_rkas_periods' => ['group' => 'Anggaran (ARKAS)', 'label' => 'Pagu per bulan', 'blurb' => 'Rincian pagu RKAS per bulan/triwulan/semester.'],
        'arkas_bku_rows' => ['group' => 'Anggaran (ARKAS)', 'label' => 'Baris BKU', 'blurb' => 'Realisasi belanja dari Buku Kas Umum ARKAS.'],
        'arkas_periods' => ['group' => 'Anggaran (ARKAS)', 'label' => 'Periode ARKAS', 'blurb' => 'Daftar periode/bulan anggaran ARKAS.'],
        'account_hierarchies' => ['group' => 'Anggaran (ARKAS)', 'label' => 'Bagan akun', 'blurb' => 'Struktur kode rekening belanja.'],
        'account_references' => ['group' => 'Anggaran (ARKAS)', 'label' => 'Referensi rekening', 'blurb' => 'Sifat rekening (honor, pajak, kas) + kategori SPJ.'],
        'activity_references' => ['group' => 'Anggaran (ARKAS)', 'label' => 'Referensi kegiatan', 'blurb' => 'Daftar kegiatan dari ARKAS.'],
        'employees' => ['group' => 'Referensi', 'label' => 'Pegawai', 'blurb' => 'GTK/pegawai dari Dapodik & ARKAS + tambahan manual.'],
        'students' => ['group' => 'Referensi', 'label' => 'Siswa', 'blurb' => 'Peserta didik dari Dapodik.'],
        'business_partners' => ['group' => 'Referensi', 'label' => 'Penyedia/visa', 'blurb' => 'Data penyedia barang/jasa.'],
        'fiscal_years' => ['group' => 'Referensi', 'label' => 'Tahun anggaran', 'blurb' => 'Periode tahun + sumber dana yang dikelola.'],
        'fund_sources' => ['group' => 'Referensi', 'label' => 'Sumber dana', 'blurb' => 'Daftar sumber dana (BOSP dll).'],
        'school_profiles' => ['group' => 'Referensi', 'label' => 'Profil sekolah', 'blurb' => 'Identitas sekolah, kepala sekolah, bendahara per tahun.'],
        'document_templates' => ['group' => 'Referensi', 'label' => 'Template dokumen', 'blurb' => 'Contoh/format dokumen SPJ yang bisa dipakai.'],
        'document_number_formats' => ['group' => 'Referensi', 'label' => 'Format penomoran', 'blurb' => 'Pola nomor dokumen per jenis dokumen.'],
        'document_number_sequences' => ['group' => 'Referensi', 'label' => 'Urutan nomor', 'blurb' => 'Penghitung nomor terakhir per format.'],
        'dapodik_connections' => ['group' => 'Referensi', 'label' => 'Koneksi Dapodik', 'blurb' => 'Pengaturan akses web service Dapodik.'],
        'fiscal_period_closures' => ['group' => 'Operasional', 'label' => 'Tutup triwulan', 'blurb' => 'Status buka/tutup periode penomoran per triwulan.'],
        'quarter_numbering_runs' => ['group' => 'Operasional', 'label' => 'Riwayat penomoran', 'blurb' => 'Jejak setiap proses penomoran triwulan.'],
        'sync_runs' => ['group' => 'Operasional', 'label' => 'Riwayat sinkronisasi', 'blurb' => 'Jejak setiap sinkronisasi ARKAS/Dapodik.'],
        'arkas_import_profiles' => ['group' => 'Operasional', 'label' => 'Profil impor ARKAS', 'blurb' => 'Pengaturan impor file ARKAS.'],
        'arkas_import_rows' => ['group' => 'Operasional', 'label' => 'Baris impor ARKAS', 'blurb' => 'Data mentah hasil impor file ARKAS.'],
        'arkas_import_runs' => ['group' => 'Operasional', 'label' => 'Riwayat impor ARKAS', 'blurb' => 'Jejak setiap proses impor file ARKAS.'],
        'operational_audit_logs' => ['group' => 'Operasional', 'label' => 'Jejak aktivitas', 'blurb' => 'Log siapa mengubah apa di aplikasi.'],
        'migrations' => ['group' => 'Sistem', 'label' => 'Versi struktur', 'blurb' => 'Catatan migrasi struktur database (internal).'],
    ];
}
