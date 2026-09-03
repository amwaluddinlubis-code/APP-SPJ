# Penanda Template Dokumen SPJ

Dokumen ini adalah referensi penanda yang didukung oleh `SpjTemplateService` untuk template Word (`.docx`) dan Excel (`.xlsx`). Gunakan format kurung kurawal ganda, misalnya `{{NOMOR_SPJ}}`. Daftar pada halaman **Pengaturan → Template Dokumen** mengambil sumber katalog yang sama dengan layanan generator agar UI dan implementasi tidak berbeda.

## Dokumen dan periode

`NOMOR_SPJ`, `NOMOR_DOKUMEN`, `NO_BUKTI`, `NOMOR_BUKTI`, `TANGGAL_TRANSAKSI`, `TANGGAL_DOKUMEN`, `TAHUN_ANGGARAN`, `SUMBER_DANA`, `SUMBER_DANA_PERIODE`, `TRIWULAN`, `SEMESTER`, `JENIS_SPJ`.

## Sekolah dan pejabat

`NAMA_SEKOLAH`, `NAMA_SATUAN_PENDIDIKAN`, `NPSN`, `ALAMAT_SEKOLAH`, `KECAMATAN`, `KOP_SURAT`, `NAMA_KEPALA_SEKOLAH`, `NIP_KEPALA_SEKOLAH`, `NAMA_KEPALA_SATUAN_PENDIDIKAN`, `NIP_KEPALA_SATUAN_PENDIDIKAN`, `NAMA_BENDAHARA`, `NIP_BENDAHARA`, `NAMA_BENDAHARA_BOSP`, `NIP_BENDAHARA_BOSP`.

## Penerima dan penyedia

`NAMA_PENERIMA`, `NAMA_PENERIMA_BKU`, `NAMA_PENERIMA_KUITANSI`, `PENERIMA_PENYEDIA`, `NAMA_PENYEDIA`, `ALAMAT_PENYEDIA`, `NPWP_PENYEDIA`, `TELEPON_PENYEDIA`, `NAMA_PENANDATANGAN`, `JABATAN_PENANDATANGAN`, `SUDAH_TERIMA_DARI`.

## Transaksi dan pembayaran

`KODE_KEGIATAN`, `NAMA_KEGIATAN`, `KODE_REKENING`, `NAMA_REKENING`, `URAIAN_TRANSAKSI`, `UNTUK_PEMBAYARAN`, `CARA_BAYAR`, `REFERENSI_BAYAR`, `CARA_BAYAR_REFERENSI`.

## Pesanan dan pekerjaan

`NOMOR_PESANAN`, `TANGGAL_PESANAN`, `NOMOR_INVOICE`, `TANGGAL_INVOICE`, `STATUS_INVOICE`, `NOMOR_SPK`, `TANGGAL_SPK`, `TANGGAL_RAB`, `URAIAN_PEKERJAAN`, `LOKASI_PEKERJAAN`, `TANGGAL_MULAI`, `TANGGAL_SELESAI`, `TANGGAL_TANDA_TANGAN`, `TANGGAL_PENYERAHAN`, `TEMPAT_PENYERAHAN`.

## Nilai dan pajak

`NILAI_BRUTO`, `NILAI_PEKERJAAN`, `NILAI_PEKERJAAN_TERBILANG`, `PPN`, `PPH21`, `PPH22`, `PPH23`, `PPH4`, `SSPD`, `TOTAL_PAJAK`, `POTONGAN_PAJAK`, `NILAI_DIBAYARKAN`, `TERBILANG_NETO`.

Nilai uang sudah diformat sebagai rupiah oleh aplikasi. Penanda `TERBILANG_NETO` dan `NILAI_PEKERJAAN_TERBILANG` menghasilkan teks terbilang berbahasa Indonesia.

## Ringkasan multibaris

`RINCIAN_BELANJA` dan `RINCIAN_UPAH` menghasilkan ringkasan teks multibaris. Gunakan penanda baris berulang di bawah bila dokumen membutuhkan tabel yang terstruktur.

## Baris berulang rincian barang

`ITEM_NO`, `ITEM_URAIAN`, `ITEM_VOLUME`, `ITEM_SATUAN`, `ITEM_HARGA_SATUAN`, `ITEM_JUMLAH`, `ITEM_KODE_REKENING`, `ITEM_NAMA_REKENING`.

Letakkan penanda `ITEM_*` pada satu baris tabel contoh. Baris yang memuat `ITEM_NO` menjadi acuan yang digandakan sesuai jumlah item transaksi.

## Baris berulang upah atau honor

`UPAH_NO`, `UPAH_NAMA`, `UPAH_PEKERJAAN`, `UPAH_HARI`, `UPAH_TARIF_HARI`, `UPAH_JUMLAH`, `UPAH_PENERIMA_KUITANSI`.

Letakkan penanda `UPAH_*` pada satu baris tabel contoh. Baris yang memuat `UPAH_NO` menjadi acuan yang digandakan sesuai jumlah pekerja atau penerima honor.

## Catatan kompatibilitas

- Nama kanonis disarankan untuk template baru, misalnya `NOMOR_SPJ`, `NO_BUKTI`, dan `NAMA_SEKOLAH`.
- Alias seperti `NOMOR_DOKUMEN`, `NOMOR_BUKTI`, `NAMA_SATUAN_PENDIDIKAN`, dan nama pejabat versi `*_BOSP` dipertahankan untuk template lama.
- `KOP_SURAT`, `ALAMAT_PENYEDIA`, `NPWP_PENYEDIA`, `TELEPON_PENYEDIA`, dan `KECAMATAN` dapat kosong bila sumber datanya belum tersedia.
- Tanggal ditampilkan dengan nama bulan bahasa Indonesia.
