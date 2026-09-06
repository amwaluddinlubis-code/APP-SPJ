# Penanda Template Dokumen SPJ

Terakhir diverifikasi: **2026-09-06**

Dokumen ini adalah referensi placeholder yang dipakai `SpjTemplateService` untuk template Word (`.docx`) dan Excel (`.xlsx`). Gunakan format kurung kurawal ganda, misalnya `{{NOMOR_SPJ}}`.

Daftar pada **Pengaturan → Template Dokumen** harus tetap memakai katalog yang sama agar UI dan generator tidak berbeda.

---

## 1. Dokumen dan periode

`NOMOR_SPJ`, `NOMOR_DOKUMEN`, `NO_BUKTI`, `NOMOR_BUKTI`, `TANGGAL_TRANSAKSI`, `TANGGAL_DOKUMEN`, `TAHUN_ANGGARAN`, `SUMBER_DANA`, `SUMBER_DANA_PERIODE`, `TRIWULAN`, `SEMESTER`, `JENIS_SPJ`.

---

## 2. Sekolah dan pejabat

`NAMA_SEKOLAH`, `NAMA_SATUAN_PENDIDIKAN`, `NPSN`, `ALAMAT_SEKOLAH`, `KECAMATAN`, `KOP_SURAT`, `NAMA_KEPALA_SEKOLAH`, `NIP_KEPALA_SEKOLAH`, `NAMA_KEPALA_SATUAN_PENDIDIKAN`, `NIP_KEPALA_SATUAN_PENDIDIKAN`, `NAMA_BENDAHARA`, `NIP_BENDAHARA`, `NAMA_BENDAHARA_BOSP`, `NIP_BENDAHARA_BOSP`.

---

## 3. Penerima dan penyedia

`NAMA_PENERIMA`, `NAMA_PENERIMA_BKU`, `NAMA_PENERIMA_KUITANSI`, `PENERIMA_PENYEDIA`, `NAMA_PENYEDIA`, `ALAMAT_PENYEDIA`, `NPWP_PENYEDIA`, `TELEPON_PENYEDIA`, `NAMA_PENANDATANGAN`, `JABATAN_PENANDATANGAN`, `SUDAH_TERIMA_DARI`.

Aturan ownership:

- `NAMA_PENERIMA_BKU` berasal dari source BKU/ARKAS;
- `NAMA_PENERIMA_KUITANSI` mengutamakan field operator `receipt_recipient_name`/effective receipt recipient;
- jangan menukar keduanya hanya untuk menyesuaikan template.

---

## 4. Transaksi dan pembayaran

`KODE_KEGIATAN`, `NAMA_KEGIATAN`, `KODE_REKENING`, `NAMA_REKENING`, `URAIAN_TRANSAKSI`, `UNTUK_PEMBAYARAN`, `CARA_BAYAR`, `REFERENSI_BAYAR`, `CARA_BAYAR_REFERENSI`.

`UNTUK_PEMBAYARAN` harus memakai uraian operator/canonical yang disediakan service, bukan menghidupkan kembali `manual_description`.

---

## 5. Pesanan dan pekerjaan

`NOMOR_PESANAN`, `TANGGAL_PESANAN`, `NOMOR_INVOICE`, `TANGGAL_INVOICE`, `STATUS_INVOICE`, `NOMOR_SPK`, `TANGGAL_SPK`, `TANGGAL_RAB`, `URAIAN_PEKERJAAN`, `LOKASI_PEKERJAAN`, `TANGGAL_MULAI`, `TANGGAL_SELESAI`, `TANGGAL_TANDA_TANGAN`, `TANGGAL_PENYERAHAN`, `TEMPAT_PENYERAHAN`.

### `NOMOR_PESANAN`

`NOMOR_PESANAN` adalah **Nomor Surat Pesanan SPJ internal** yang diterbitkan aplikasi melalui proses numbering `PESANAN`.

Nomor ini:

- boleh kosong pada tahap `DRAFT`/`READY`;
- tidak boleh menjadi blocker sebelum numbering;
- menjadi wajib setelah package `NUMBERED`/`FINAL` bila internal order applicable.

`TANGGAL_PESANAN` adalah bagian substansi yang harus tersedia lebih awal sesuai requirement pengadaan.

---

## 6. Pembelian SiPLah

Placeholder:

`SIPLAH_NOMOR_PESANAN`, `SIPLAH_PENYEDIA`, `SIPLAH_NOMOR_INVOICE`, `SIPLAH_TANGGAL_INVOICE`, `SIPLAH_REFERENSI_BAYAR`.

Perbedaan penting:

```text
SIPLAH_NOMOR_PESANAN = nomor marketplace/order SiPLah
NOMOR_PESANAN         = Nomor Surat Pesanan SPJ internal
```

Jangan saling mengganti kedua placeholder tersebut.

---

## 7. Nilai dan pajak

`NILAI_BRUTO`, `NILAI_PEKERJAAN`, `NILAI_PEKERJAAN_TERBILANG`, `PPN`, `PPH21`, `PPH22`, `PPH23`, `PPH4`, `SSPD`, `TOTAL_PAJAK`, `POTONGAN_PAJAK`, `NILAI_DIBAYARKAN`, `TERBILANG_NETO`.

Nilai uang diformat oleh aplikasi. `TERBILANG_NETO` dan `NILAI_PEKERJAAN_TERBILANG` menghasilkan teks terbilang bahasa Indonesia.

---

## 8. Ringkasan multibaris

`RINCIAN_BELANJA` dan `RINCIAN_UPAH` menghasilkan ringkasan teks multibaris.

Gunakan repeating row placeholders bila dokumen membutuhkan tabel terstruktur.

---

## 9. Repeating row barang

`ITEM_NO`, `ITEM_URAIAN`, `ITEM_VOLUME`, `ITEM_SATUAN`, `ITEM_HARGA_SATUAN`, `ITEM_JUMLAH`, `ITEM_KODE_REKENING`, `ITEM_NAMA_REKENING`.

Baris yang memuat `ITEM_NO` menjadi template row yang digandakan sesuai jumlah item.

---

## 10. Repeating row upah/honor

`UPAH_NO`, `UPAH_NAMA`, `UPAH_PEKERJAAN`, `UPAH_HARI`, `UPAH_TARIF_HARI`, `UPAH_JUMLAH`, `UPAH_PENERIMA_KUITANSI`.

Baris yang memuat `UPAH_NO` menjadi template row yang digandakan sesuai jumlah pekerja/penerima honor.

---

## 11. Catatan kompatibilitas

- Gunakan nama canonical untuk template baru.
- Alias lama tetap dipertahankan bila service masih mendukungnya.
- Field seperti `KOP_SURAT`, alamat/NPWP/telepon penyedia, dan kecamatan dapat kosong bila source belum tersedia.
- Tanggal ditampilkan dengan nama bulan Indonesia sesuai formatter service.
- Preview/download tidak boleh menerbitkan nomor hanya karena placeholder nomor masih kosong.

---

## 12. Lokasi dokumen pada UI Paket

Pada halaman Paket SPJ, daftar **Dokumen & Template** sekarang berada di sub-tab **Rincian**, sebagai panel terpisah dari Rincian Transaksi. Perubahan posisi UI ini tidak mengubah placeholder atau generator.
