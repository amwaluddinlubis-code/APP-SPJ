# SPJ BOSP Web — Skenario Pengguna & Alur Kerja

Terakhir diperbarui: **2026-09-08**

Dokumen ini menjelaskan alur pengguna yang harus terlihat dari perspektif operator sekolah. Baca bersama `SPJ_DESIGN_DECISIONS.md`, `CURRENT_PROGRESS.md`, dan `URGENT_TRANSACTION_SPJ_MIGRATION.md`.

---

## 1. Prinsip pengalaman pengguna

Operator harus selalu tahu:

1. sekolah/tahun/sumber dana aktif;
2. transaksi mana yang perlu dilengkapi;
3. mana data ARKAS/BKU dan mana data operator;
4. apa yang masih blocking;
5. dokumen apa yang tersedia;
6. apakah package masih editable;
7. langkah berikutnya: simpan uraian item → buka Paket → lengkapi → siap → numbering → preview/download → final.

UI tidak boleh mengharuskan operator memahami istilah teknis tenant, hash, migration, atau internal service.

Prinsip baru yang wajib terlihat di UX:

```text
Detail Transaksi = detail transaksi
Paket SPJ        = pekerjaan dokumen SPJ
```

Operator tidak boleh menemukan field SPJ yang sama untuk diedit di dua halaman.

---

## 2. Peran

### ADMIN

Mengelola sekolah, database tenant, backup/restore/reset, user/role, konfigurasi, template, impersonation, dan aksi sensitif sesuai authorization.

### OPERATOR

Mengelola transaksi/SPJ sekolah yang ditugaskan: sinkronisasi bila berhak, memeriksa detail transaksi, menyimpan `item_description`, membuat/membuka package, melengkapi Paket SPJ, numbering/preview/download sesuai role.

### VIEWER

Targetnya read-only. Backend authorization harus benar-benar menolak mutation.

---

## 3. Alur utama operator

```text
Login
→ Pilih sekolah
→ Pilih tahun & sumber dana
→ Sinkronisasi ARKAS/BKU
→ Buka Detail Transaksi
→ Periksa source + pajak + rincian
→ Koreksi item_description bila perlu
→ Simpan Uraian Barang/Jasa
→ Siapkan / Lihat Paket SPJ
→ Lengkapi data dokumen di Paket SPJ
→ Validasi
→ READY
→ Penomoran
→ Preview / Unduh
→ FINAL / Arsip
```

Preview/download tidak boleh menghasilkan nomor secara otomatis.

---

## 4. Detail Transaksi

Sebagai operator, saya membuka transaksi untuk melihat fakta transaksi dan menyesuaikan nama/uraian item yang akan dipakai dokumen SPJ.

Struktur target:

```text
Data ARKAS/BKU readonly
→ Ringkasan nilai transaksi
→ PPN / PPh / SSPD
→ Rincian Barang/Jasa
   → item_description editable
   → quantity/unit/harga/nilai readonly
→ Status Paket SPJ
→ Siapkan / Lihat Paket SPJ
```

Detail Transaksi **tidak** lagi menjadi tempat mengisi:

```text
Kategori SPJ
Uraian dokumen/pembayaran
Metode pembayaran
Referensi pembayaran
Penerima kuitansi
Vendor/invoice
Peserta konsumsi
Pekerja pemeliharaan
Pelaksana SPPD
Penerima honor
Penerima jasa lainnya
```

Aturan source:

- `recipient_name` = penerima source BKU/ARKAS;
- `description` item = uraian source;
- `item_description` = uraian/nama item untuk dokumen SPJ;
- `manual_description` tidak digunakan.

### Validasi sebelum Paket SPJ

Semua `item_description` harus sudah tersimpan.

Jika Operator baru mengetik perubahan tetapi belum menekan **Simpan Uraian Barang/Jasa**, tombol Paket SPJ harus memblokir navigasi dan meminta penyimpanan lebih dahulu.

Jika ada `item_description` kosong di database, gateway backend tetap menolak create/open Paket SPJ dan mengarahkan kembali ke `#rincian-transaksi`.

---

## 5. Pajak transaksi

Detail Transaksi menampilkan pajak source secara terpisah:

```text
PPN
PPh 21
PPh 22
PPh 23
PPh 4(2)
SSPD / Pajak Daerah
Total Pajak
Nilai Netto
```

Paket SPJ boleh menampilkan data tersebut sebagai referensi readonly, tetapi tidak boleh mengubah atau menghitung ulang nilai pajak transaksi.

---

## 6. Membuka / menyiapkan Paket SPJ

Aksi dari Detail Transaksi:

```text
Belum ada package  → Siapkan Paket SPJ
Sudah ada package  → Lihat Paket SPJ
```

Keduanya melewati gateway validasi Detail Transaksi → Paket SPJ.

Target alur:

```text
Detail Transaksi
→ validasi item_description tersimpan
→ firstOrCreate DRAFT
→ /spj?tab=paket&package_id=...
```

Operator tidak diminta mengisi form SPJ saat menekan tombol tersebut.

---

## 7. Kategori BARANG

Data kategori BARANG hanya diedit di Paket SPJ.

Operator melengkapi vendor, invoice, tanggal pesanan/penerimaan, dan metadata dokumen pengadaan yang diperlukan.

Rincian item dibaca readonly dari transaksi menggunakan `item_description` yang sudah tersimpan.

### Surat Pesanan internal

Sebelum package READY, yang wajib adalah **isi/substansi Surat Pesanan**, bukan nomor suratnya.

Blocking content:

- penyedia;
- tanggal pesanan;
- item dan uraian;
- quantity;
- satuan;
- harga/nilai;
- nilai transaksi valid.

Nomor Surat Pesanan diterbitkan saat numbering. Pada DRAFT/READY, nomor yang belum ada tidak memblokir persiapan. Pada NUMBERED/FINAL, nomor harus tersedia.

### Tanggal pengadaan

Rule domain Paket:

```text
Tanggal Pesanan <= Tanggal Transaksi
Tanggal Pesanan <= Tanggal BAP
Tanggal BAP <= Tanggal BAST
```

---

## 8. Kategori KONSUMSI

Sebagai operator, saya mengisi acara dan daftar peserta/porsi di Paket SPJ.

Data:

- nama acara;
- tempat;
- tanggal kegiatan;
- jumlah peserta;
- daftar peserta;
- jabatan/instansi;
- porsi;
- data pembelian/pengadaan bila relevan.

### Auto-fill peserta

Auto-fill peserta hanya mengambil data Dapodik:

```text
Employee.source_type = DAPODIK
```

Record yang hanya berasal dari ARKAS tidak ikut dimasukkan. Operator tetap dapat menambah peserta manual.

`participant_count` harus sama dengan total porsi sesuai validasi aktif.

Konsumsi tidak boleh menampilkan blok input SiPLah bila flow/kondisi SiPLah tidak berlaku.

---

## 9. Kategori PEMELIHARAAN

```text
1 transaksi
└── 1 work order
    └── banyak workers
```

Operator mengisi data pekerjaan di Paket SPJ: deskripsi/lokasi pekerjaan, tanggal, SPK/RAB bila relevan, pekerja, hari, tarif, penerima kuitansi, dan catatan.

Jika bahan dan upah berasal dari transaksi berbeda, linkage transaksi tetap mengikuti aturan domain dan rendering dokumen tidak boleh menimpa source BKU.

---

## 10. Kategori SPPD

```text
1 transaksi
└── banyak pelaksana perjalanan
```

Data pelaksana perjalanan diisi di Paket SPJ. Setiap pelaksana dapat memiliki tujuan, maksud, surat tugas, tanggal, transport, nilai, dan catatan.

Satu transaksi tidak dibatasi menjadi satu orang.

---

## 11. Kategori HONOR_PEGAWAI

Satu transaksi dapat memiliki banyak penerima honor. Data penerima honor dikelola di Paket SPJ, bukan di Detail Transaksi.

Total rincian honor harus konsisten dengan gross transaction sesuai validation yang aktif.

Honor dan worker pemeliharaan tidak boleh dicampur tanpa mapping yang jelas.

---

## 12. Kategori JASA_LAINNYA

Digunakan untuk jasa yang tidak masuk kategori utama lain. Data penerima/vendor jasa, uraian jasa, periode, referensi pembayaran, dan detail kategori dikelola di Paket SPJ.

Jumlah gross/tax/net penerima harus tetap direkonsiliasi dengan transaksi source.

---

## 13. SiPLah

SiPLah bukan kategori SPJ.

Contoh:

```text
payment_method = siplah
spj_category = BARANG
```

Nomor pesanan marketplace SiPLah berbeda dengan Nomor Surat Pesanan SPJ yang dibuat aplikasi.

Perubahan kategori tidak boleh mengubah fakta bahwa SiPLah adalah payment/procurement channel, bukan kategori.

---

## 14. Paket SPJ — skenario terbaru

Ketika package dibuka:

```text
/spj?tab=paket&package_id=...
```

operator melihat sub-tab:

```text
Rincian
Isian Manual
Penomoran
```

### Tab Rincian

Rincian transaksi bersifat readonly dan menggunakan `item_description` yang sudah tersimpan dari Detail Transaksi.

Tidak ada edit kedua untuk quantity, unit, harga, nilai, `item_description`, atau pajak.

### Tab Isian Manual

Ini adalah workspace utama untuk seluruh data dokumen SPJ selama package masih editable.

Kategori SPJ dapat diganti tanpa full page reload. Setelah Combo berubah:

```text
section form berubah langsung
→ kategori dipersist via AJAX
→ sukses: tetap di halaman
→ gagal: Combo/section kembali ke kategori sebelumnya
```

Pajak yang ditampilkan di Paket adalah reference readonly dari transaksi/BKU.

### Tab Penomoran

Digunakan untuk menerbitkan nomor melalui flow yang sah. Package bernomor/final tidak boleh diedit normal.

---

## 15. Penomoran

Alur:

1. Data wajib Paket lengkap.
2. Package menjadi kandidat READY.
3. User menjalankan numbering per package/quarter sesuai route yang tersedia.
4. Sistem menentukan nomor per jenis dokumen.
5. Nomor aktif tidak ditimpa.
6. Package/document dikunci sesuai lifecycle.

Nomor PESANAN, BAP, BAST, SPK, RAB, dan dokumen lain mengikuti domain nomor masing-masing.

---

## 16. Pembatalan dan penerbitan ulang

Jika nomor salah atau data perlu dikoreksi:

- nomor aktif dibatalkan dengan alasan;
- history tidak dihapus;
- package dibuka kembali hanya melalui flow yang sah;
- data diperbaiki di workspace pemiliknya;
- validation diulang;
- nomor diterbitkan ulang tanpa menimpa dokumen sukses lain.

---

## 17. Source ARKAS berubah/hilang

Jika source berubah:

- source fields dapat berubah sesuai safe sync;
- manual overlay dipertahankan;
- jika package sudah disiapkan/numbered, gunakan reconciliation.

Jika source hilang sementara:

- tandai `SOURCE_MISSING`;
- jangan hapus manual/package/number;
- jika muncul kembali, sambungkan ke data lama.

---

## 18. Validasi manusiawi

Pesan harus menjelaskan workspace yang harus diperbaiki, misalnya:

- “Simpan seluruh Uraian Barang/Jasa untuk SPJ terlebih dahulu.” → Detail Transaksi.
- “Penerima kuitansi belum diisi.” → Paket SPJ.
- “Isi Surat Pesanan belum lengkap.” → Paket SPJ.
- “Tanggal Pesanan tidak boleh setelah Tanggal Transaksi.” → Paket SPJ.
- “Tanggal BAP tidak boleh sebelum Tanggal Pesanan.” → Paket SPJ.
- “Tanggal BAST tidak boleh sebelum Tanggal BAP.” → Paket SPJ.
- “Jumlah peserta harus sama dengan total porsi.” → Paket SPJ.

---

## 19. Kesalahan dan edge case

- salah sekolah/tahun/sumber dana → redirect/pesan aman;
- package terkunci → backend menolak mutation;
- endpoint update tidak boleh dipakai sebagai halaman GET;
- tab utama SPJ tetap memakai URL `?tab=...`;
- `package_id` dipertahankan hanya saat tab Paket;
- jangan memanipulasi root DOM sehingga state Alpine/Livewire rusak;
- category switch AJAX gagal → rollback Combo/section ke nilai persisted terakhir;
- request manual ke Paket tidak boleh dapat mengubah PPN/PPh/SSPD source.

---

## 20. Queue/sinkronisasi latar belakang

Operasi sync dapat berjalan melalui queue. Jika worker tidak aktif, job dapat tetap QUEUED. Restart worker harus dilakukan secara operasional; user tidak boleh menekan sync berulang hanya karena job belum diproses.

Safe sync tetap wajib meskipun job gagal/retry.

---

## 21. Checklist sebelum fitur baru

1. Source atau manual data?
2. Workspace pemiliknya Detail Transaksi atau Paket SPJ?
3. Apakah field sudah editable di tempat lain?
4. Role mana?
5. Apakah package numbered/final terdampak?
6. Perlu audit?
7. Sudah scoped school/year/fund source?
8. Berpengaruh ke numbering?
9. Dapat merusak safe sync?
10. Istilah UI mudah dipahami?
11. Theme/dark/mobile tetap aman?
12. Validation backend tetap authoritative?
13. Apakah perubahan kategori dapat berjalan tanpa full page reload?
