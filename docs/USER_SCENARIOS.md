# SPJ BOSP Web — Skenario Pengguna & Alur Kerja

Terakhir diperbarui: **2026-09-08**

Dokumen ini menjelaskan alur dari perspektif operator sekolah. Baca bersama `SPJ_DESIGN_DECISIONS.md`, `CURRENT_PROGRESS.md`, dan `GUI_STANDARDIZATION.md`.

## 1. Prinsip pengalaman pengguna

Operator harus selalu tahu:

1. sekolah/tahun/sumber dana aktif;
2. transaksi mana yang perlu dilengkapi;
3. mana data ARKAS/BKU dan mana data operator;
4. apa yang masih blocking;
5. dokumen apa yang tersedia;
6. apakah Paket masih editable;
7. langkah berikutnya.

Prinsip UX canonical:

```text
Detail Transaksi = fakta transaksi + koreksi item_description
Paket SPJ        = pekerjaan dokumen pertanggungjawaban
```

Operator tidak boleh menemukan field SPJ yang sama editable di dua halaman.

## 2. Peran

### ADMIN

Mengelola sekolah, database tenant, backup/restore/reset, user/role, konfigurasi, template, impersonation, dan aksi sensitif sesuai authorization.

### OPERATOR

Memeriksa transaksi, menyimpan `item_description`, membuat/membuka Paket, melengkapi data dokumen, dan menjalankan workflow yang diizinkan.

### VIEWER

Target read-only. Backend harus menolak mutation.

## 3. Alur utama operator

```text
Login
→ Pilih sekolah
→ Pilih tahun & sumber dana
→ Sinkronisasi ARKAS/BKU
→ Daftar Transaksi
→ Aksi → Detail Transaksi
→ Periksa Informasi Referensi ARKAS/BKU
→ Koreksi item_description bila perlu
→ Simpan Uraian Barang/Jasa
→ Siapkan / Lihat Paket SPJ
→ Lengkapi Isian Manual
→ Validasi
→ READY
→ Penomoran
→ Preview / Unduh
→ FINAL / Arsip
```

Preview/download tidak boleh menghasilkan nomor secara otomatis.

## 4. Daftar Transaksi

Setiap row pada layout aktif memiliki satu tombol **Aksi**. Modal Aksi menyediakan pilihan navigasi yang relevan seperti Detail Transaksi dan Paket SPJ.

Tidak ada editor kategori/payment/vendor SPJ di tabel transaksi.

## 5. Detail Transaksi

Struktur canonical:

```text
Header Transaksi
→ Informasi Referensi ARKAS/BKU + Total Pajak
→ Rincian Barang/Jasa
   → item_description editable
   → quantity/unit/harga/nilai readonly
→ Status Paket SPJ
```

Detail Transaksi tidak lagi menjadi tempat mengisi:

```text
Kategori SPJ
Uraian pembayaran
Metode/referensi pembayaran
Penerima Utama
Vendor/invoice
Peserta konsumsi
Pekerja pemeliharaan
Pelaksana SPPD
Penerima honor
Penerima jasa lainnya
```

### Validasi sebelum Paket

Semua `item_description` harus tersimpan. Perubahan yang baru diketik tetapi belum disimpan harus memblokir navigasi Paket.

Jika data database masih kosong, gateway backend mengarahkan kembali ke `#rincian-transaksi`.

## 6. Pajak

Detail Transaksi cukup menampilkan **Total Pajak** sebagai bagian dari Informasi Referensi ARKAS/BKU.

Pada Paket SPJ:

- summary menampilkan **Pajak**;
- tab **Rincian Pajak** menampilkan PPN/PPh/SSPD lengkap secara readonly;
- operator tidak dapat mengubah source tax dari Paket.

## 7. Membuka Paket SPJ

```text
Belum ada package  → Siapkan Paket SPJ
Sudah ada package  → Lihat Paket SPJ
```

Keduanya melewati gateway:

```text
validasi item_description tersimpan
→ firstOrCreate DRAFT
→ /spj?tab=paket&package_id=...
```

Operator tidak diminta mengisi form SPJ pada saat menekan gateway.

## 8. Navigasi Paket

Toolbar:

```text
[ Semua Paket ] [ Paket Sebelumnya ] [ Paket Setelahnya ]        [ Lihat Transaksi ]
```

Previous/next hanya bergerak pada sekolah, tahun anggaran, dan sumber dana aktif yang sama. Bila tidak ada Paket tujuan, user mendapat warning yang jelas.

Summary:

```text
Periode | Penerima | Bruto | Pajak | Nilai Dibayarkan
```

Nilai uang ditampilkan sebagai accounting, misalnya `1.250.000`, tanpa `Rp`/desimal.

## 9. Sub-tab Paket

```text
1. Rincian
2. Isian Manual
3. Rincian Pajak
4. Penomoran
```

### Rincian

Rincian transaksi readonly + Dokumen & Template.

### Isian Manual

Satu-satunya workspace pengisian data dokumen selama Paket editable.

### Rincian Pajak

Readonly source tax.

### Penomoran

Penerbitan nomor melalui flow sah. NUMBERED/FINAL tidak diedit normal.

## 10. Isian Manual — kategori dan nomor

Baris pertama desktop:

```text
Kategori SPJ 1/4 | Kontrol konteks 3/4
```

### BARANG

```text
Kategori BARANG | ○ SiPLah  ○ Non SiPLah
```

Radio hanya dapat memilih satu. Jika source memang SiPLah, Non SiPLah dapat dikunci.

### PEMELIHARAAN

Selector transaksi pasangan bahan/upah berada sejajar dengan combo kategori.

Setelah baris kategori, nomor otomatis ditampilkan sebagai **informasi horizontal**, bukan input operator.

## 11. Data Umum Dokumen

Desktop:

```text
kiri  : Uraian pembayaran, textarea 5 baris
kanan : Metode, Referensi, Penerima Utama, Penyedia, Pemilik, NPWP
```

Semua input umum selain textarea berada di kolom kanan.

## 12. Tabel kategori non-BARANG

Tabel dibuat compact. Filter, pilihan jumlah baris, dan pagination berada di bawah tabel dan hanya ada **satu pagination**.

Semua tabel memakai istilah **Penerima Utama** untuk pihak utama/penanda tangan kuitansi. UI menggunakan radio karena hanya satu yang utama.

Pattern field:

```text
Tarif/Harga/Nilai → accounting 1.000
Hari/Porsi/Kali   → integer
Tanggal           → date
```

## 13. Kategori BARANG

Data BARANG hanya diedit di Paket SPJ. Operator melengkapi data vendor, invoice, tanggal/dokumen pengadaan yang memang manual.

Rincian item dibaca readonly dari transaksi menggunakan `item_description` yang sudah tersimpan.

Rule tanggal:

```text
Tanggal Pesanan <= Tanggal Transaksi
Tanggal Pesanan <= Tanggal BAP
Tanggal BAP <= Tanggal BAST
```

Nomor internal otomatis diterbitkan oleh numbering, bukan diketik pada Isian Manual.

## 14. Kategori KONSUMSI

Operator mengisi acara, tempat/tanggal, jumlah peserta, dan daftar peserta/porsi.

Auto-fill peserta hanya mengambil Employee aktif dari Dapodik. Participant manual tetap diperbolehkan.

`participant_count` harus sama dengan total porsi. Porsi ditampilkan sebagai integer.

## 15. Kategori PEMELIHARAAN

```text
1 transaksi
└── 1 work order
    └── banyak workers
```

Operator mengisi data pekerjaan dan tabel pekerja di Paket. Hari integer, tarif accounting, dan satu Penerima Utama.

Jika bahan dan upah berasal dari transaksi berbeda, linkage disimpan melalui endpoint maintenance-link khusus dan tidak menimpa source BKU.

## 16. Kategori SPPD

Satu transaksi dapat memiliki banyak pelaksana perjalanan. Data pelaksana diisi di Paket SPJ dan tabel memakai satu Penerima Utama.

## 17. Kategori HONOR_PEGAWAI

Satu transaksi dapat memiliki banyak penerima honor. Bulan/kali integer dan tarif accounting. Total rincian harus mengikuti validasi gross aktif.

## 18. Kategori JASA_LAINNYA

Satu transaksi dapat memiliki banyak penerima jasa. Gross/tax/net tetap harus direkonsiliasi dengan source transaction.

Output multi-penerima sampai dokumen/final masih termasuk release-hardening aktif.

## 19. SiPLah

SiPLah bukan kategori SPJ.

UI eksplisit radio SiPLah/Non SiPLah saat ini berada pada kategori BARANG. Pemilihan tersebut menyinkronkan `payment_method` dan tidak membuat lifecycle/numbering baru.

Nomor order marketplace SiPLah berbeda dengan nomor Surat Pesanan internal aplikasi.

## 20. Penomoran dan lifecycle

1. Data wajib Paket lengkap.
2. Paket menjadi kandidat READY.
3. User menjalankan numbering.
4. Sistem menentukan nomor per jenis dokumen.
5. Nomor aktif tidak ditimpa.
6. NUMBERED/FINAL terkunci.
7. Preview/download tidak mengalokasikan nomor.

## 21. Source berubah/hilang

Safe sync mempertahankan overlay operator. Source hilang ditandai, bukan menghapus manual/package/number. Source berubah pada pekerjaan yang sudah lanjut memakai reconciliation.

## 22. Validasi manusiawi

Pesan harus mengarahkan user ke workspace pemiliknya, misalnya:

- `Simpan seluruh Uraian Barang/Jasa terlebih dahulu.` → Detail Transaksi.
- `Penerima Utama belum dipilih.` → Paket SPJ.
- `Tanggal Pesanan tidak boleh setelah Tanggal Transaksi.` → Paket SPJ.
- `Tanggal BAP tidak boleh sebelum Tanggal Pesanan.` → Paket SPJ.
- `Tanggal BAST tidak boleh sebelum Tanggal BAP.` → Paket SPJ.
- `Jumlah peserta harus sama dengan total porsi.` → Paket SPJ.

## 23. Checklist sebelum fitur baru

1. Source atau operator data?
2. Pemilik field Detail Transaksi atau Paket?
3. Apakah field sudah editable di tempat lain?
4. Role mana?
5. Apakah NUMBERED/FINAL terdampak?
6. Perlu audit?
7. Sudah scoped school/year/fund source?
8. Berpengaruh ke numbering?
9. Dapat merusak safe sync?
10. Theme/mobile tetap aman?
11. Validation backend authoritative?
