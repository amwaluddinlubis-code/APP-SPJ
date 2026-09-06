# SPJ BOSP Web — Skenario Pengguna & Alur Kerja

Terakhir diperbarui: **2026-09-06**

Dokumen ini menjelaskan alur pengguna yang harus terlihat dari perspektif operator sekolah. Baca bersama `SPJ_DESIGN_DECISIONS.md` dan `CURRENT_PROGRESS.md`.

---

## 1. Prinsip pengalaman pengguna

Operator harus selalu tahu:

1. sekolah/tahun/sumber dana aktif;
2. transaksi mana yang perlu dilengkapi;
3. mana data ARKAS/BKU dan mana data operator;
4. apa yang masih blocking;
5. dokumen apa yang tersedia;
6. apakah package masih editable;
7. langkah berikutnya: lengkapi → siap → numbering → preview/download → final.

UI tidak boleh mengharuskan operator memahami istilah teknis tenant, hash, migration, atau internal service.

---

## 2. Peran

### ADMIN

Mengelola sekolah, database tenant, backup/restore/reset, user/role, konfigurasi, template, impersonation, dan aksi sensitif sesuai authorization.

### OPERATOR

Mengelola transaksi/SPJ sekolah yang ditugaskan: sinkronisasi bila berhak, melengkapi detail, membuat package, memperbaiki draft, numbering/preview/download sesuai role.

### VIEWER

Targetnya read-only. Backend authorization harus benar-benar menolak mutation.

---

## 3. Alur utama operator

```text
Login
→ Pilih sekolah
→ Pilih tahun & sumber dana
→ Sinkronisasi ARKAS/BKU
→ Buka transaksi
→ Lengkapi data SPJ
→ Validasi
→ Siapkan package
→ READY
→ Penomoran
→ Preview / Unduh
→ FINAL / Arsip
```

Preview/download tidak boleh menghasilkan nomor secara otomatis.

---

## 4. Detail Transaksi

Sebagai operator, saya membuka transaksi untuk melihat source dan melengkapi data SPJ.

Struktur:

```text
Data ARKAS/BKU readonly
→ Data Umum SPJ
→ Detail kategori
→ Kelengkapan
→ Buat/Perbarui Paket
```

Aturan:

- `recipient_name` = penerima source BKU/ARKAS;
- `receipt_recipient_name` = penerima kuitansi operator;
- `payment_description` = uraian operator;
- `manual_description` tidak digunakan.

---

## 5. Kategori BARANG

Operator melengkapi vendor, invoice, tanggal pesanan/penerimaan, dan rincian item.

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

Catatan penting: form Detail Transaksi saat ini masih lebih ketat di backend karena BAP/BAST juga dibatasi <= Tanggal Transaksi. Ini known mismatch dan bukan requirement bisnis baru.

---

## 6. Kategori KONSUMSI

Sebagai operator, saya mengisi acara dan daftar peserta/porsi.

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

Tombol yang menjalankan:

```text
@click="fillTeachers()"
```

mengisi daftar peserta dari **data Dapodik saja**:

```text
Employee.source_type = DAPODIK
```

Record yang hanya berasal dari ARKAS tidak ikut dimasukkan.

Operator tetap dapat menambah peserta manual.

`participant_count` harus sama dengan total porsi sesuai validasi aktif.

Konsumsi Non-SiPLah juga mengikuti rule Surat Pesanan internal dan kronologi pengadaan bila procurement data diperlukan.

---

## 7. Kategori PEMELIHARAAN

```text
1 transaksi
└── 1 work order
    └── banyak workers
```

Operator mengisi deskripsi/lokasi pekerjaan, tanggal, SPK/RAB bila relevan, pekerja, hari, tarif, penerima kuitansi, dan catatan.

---

## 8. Kategori SPPD

```text
1 transaksi
└── banyak pelaksana perjalanan
```

Setiap pelaksana dapat memiliki tujuan, maksud, surat tugas, tanggal, transport, nilai, dan catatan.

Satu transaksi tidak dibatasi menjadi satu orang.

---

## 9. Kategori HONOR_PEGAWAI

Satu transaksi dapat memiliki banyak penerima honor. Total rincian honor harus konsisten dengan gross transaction sesuai validation yang aktif.

Honor dan worker pemeliharaan tidak boleh dicampur tanpa mapping yang jelas.

---

## 10. Kategori JASA_LAINNYA

Digunakan untuk jasa yang tidak masuk kategori utama lain. Operator mengisi uraian jasa, lokasi/unit, periode, penerima kuitansi, dan referensi pembayaran.

---

## 11. SiPLah

SiPLah bukan kategori SPJ.

Contoh:

```text
payment_method = siplah
spj_category = BARANG
```

atau:

```text
payment_method = siplah
spj_category = KONSUMSI
```

Nomor pesanan marketplace SiPLah berbeda dengan Nomor Surat Pesanan SPJ yang dibuat aplikasi.

---

## 12. Paket SPJ — skenario terbaru

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

Rincian harus menampilkan **dua panel berbeda**:

```text
Rincian Transaksi
Dokumen & Template
```

Panel Dokumen & Template berada di tab Rincian agar tidak menambah scroll ketika operator membuka Isian Manual/Penomoran.

Daftar dokumen ditampilkan compact. Operator tetap dapat melihat status, tipe/format, preview, dan download tanpa card besar per dokumen.

### Tab Isian Manual

Digunakan untuk mengubah data operator package selama package masih editable. Theme, background, form controls, panel pajak, dan spacing mengikuti profile/theme aktif.

### Tab Penomoran

Digunakan untuk menerbitkan nomor melalui flow yang sah. Package bernomor/final tidak boleh diedit normal.

---

## 13. Penomoran

Alur:

1. Data wajib lengkap.
2. Package menjadi kandidat READY.
3. User menjalankan numbering per package/quarter sesuai route yang tersedia.
4. Sistem menentukan nomor per jenis dokumen.
5. Nomor aktif tidak ditimpa.
6. Package/document dikunci sesuai lifecycle.

Nomor PESANAN, BAP, BAST, SPK, RAB, dan dokumen lain mengikuti domain nomor masing-masing.

---

## 14. Pembatalan dan penerbitan ulang

Jika nomor salah atau data perlu dikoreksi:

- nomor aktif dibatalkan dengan alasan;
- history tidak dihapus;
- package dibuka kembali hanya melalui flow yang sah;
- data diperbaiki;
- validation diulang;
- nomor diterbitkan ulang tanpa menimpa dokumen sukses lain.

---

## 15. Source ARKAS berubah/hilang

Jika source berubah:

- source fields dapat berubah sesuai safe sync;
- manual overlay dipertahankan;
- jika package sudah disiapkan/numbered, gunakan reconciliation.

Jika source hilang sementara:

- tandai `SOURCE_MISSING`;
- jangan hapus manual/package/number;
- jika muncul kembali, sambungkan ke data lama.

---

## 16. Validasi manusiawi

Pesan harus menjelaskan apa yang harus diperbaiki, misalnya:

- “Penerima kuitansi belum diisi.”
- “Isi Surat Pesanan belum lengkap.”
- “Tanggal Pesanan tidak boleh setelah Tanggal Transaksi.”
- “Tanggal BAP tidak boleh sebelum Tanggal Pesanan.”
- “Tanggal BAST tidak boleh sebelum Tanggal BAP.”
- “Jumlah peserta harus sama dengan total porsi.”

---

## 17. Kesalahan dan edge case

- salah sekolah/tahun/sumber dana → redirect/pesan aman;
- package terkunci → backend menolak mutation;
- endpoint update tidak boleh dipakai sebagai halaman GET;
- tab utama SPJ tetap memakai URL `?tab=...`;
- `package_id` dipertahankan hanya saat tab Paket;
- jangan memanipulasi root DOM sehingga state Alpine/Livewire rusak.

---

## 18. Queue/sinkronisasi latar belakang

Operasi sync dapat berjalan melalui queue. Jika worker tidak aktif, job dapat tetap QUEUED. Restart worker harus dilakukan secara operasional; user tidak boleh menekan sync berulang hanya karena job belum diproses.

Safe sync tetap wajib meskipun job gagal/retry.

---

## 19. Checklist sebelum fitur baru

1. Source atau manual data?
2. Role mana?
3. Apakah package numbered/final terdampak?
4. Perlu audit?
5. Sudah scoped school/year/fund source?
6. Berpengaruh ke numbering?
7. Dapat merusak safe sync?
8. Istilah UI mudah dipahami?
9. Theme/dark/mobile tetap aman?
10. Validation backend tetap authoritative?
