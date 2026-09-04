# Arsitektur SPJ BOSP Web

Terakhir diperbarui: 2026-09-04

Dokumen ini menjelaskan arsitektur aktif SPJ BOSP Web pada branch `gui-standardization`. Jika terdapat catatan historis yang bertentangan dengan dokumen ini, `SPJ_DESIGN_DECISIONS.md`, `CURRENT_PROGRESS.md`, atau kode aktif, gunakan sumber yang lebih baru tersebut.

---

## 1. Ringkasan

SPJ BOSP Web adalah aplikasi Laravel 12 untuk menyusun dokumen pertanggungjawaban BOSP berdasarkan data RKAS/BKU yang disinkronkan dari ARKAS.

Tujuan arsitektur:

- memisahkan data sumber ARKAS/BKU dari data manual SPJ operator;
- mendukung banyak sekolah melalui database tenant terpisah;
- menyediakan workflow transaksi → paket SPJ → validasi → penomoran → dokumen → final;
- menjaga data operator saat sumber ARKAS berubah atau hilang;
- menyediakan audit, rekonsiliasi, backup, dan kontrol akses;
- menyatukan pengalaman pengguna melalui TALL stack dan design system internal.

Stack utama:

- PHP 8.2+;
- Laravel 12;
- Livewire 3;
- Tailwind CSS 4;
- Alpine.js + Persist;
- Vite 6;
- Filament 4 components;
- SQLite multi-koneksi pada fase pengembangan;
- DomPDF;
- PhpSpreadsheet;
- PHPWord;
- PHPUnit 11.

---

## 2. Pola multi-database

Aplikasi memakai dua konteks database.

### 2.1 Database utama

Menampung data global:

- `users`;
- `schools`;
- metadata database sekolah;
- konfigurasi sumber ARKAS;
- backup database sekolah;
- cache/jobs/migration global;
- konfigurasi aplikasi yang tidak spesifik transaksi tenant.

### 2.2 Database sekolah / tenant

Menampung data operasional sekolah:

- fiscal year/tahun anggaran;
- sumber dana;
- RKAS hasil sinkronisasi;
- raw BKU hasil sinkronisasi;
- transaksi;
- item transaksi;
- metadata manual SPJ;
- detail kategori SPJ;
- paket SPJ;
- template dan nomor dokumen;
- audit operasional;
- status sinkronisasi/rekonsiliasi.

Setiap query operasional harus berjalan pada konteks sekolah dan tahun anggaran yang aktif.

---

## 3. Prinsip data sumber dan data operator

Aplikasi membedakan dua lapisan data.

### Data ARKAS/BKU

Karakteristik:

- berasal dari sinkronisasi;
- menjadi referensi resmi operator;
- tidak diedit dari workspace Detail Transaksi;
- perubahan/hilangnya data dapat memicu rekonsiliasi.

Contoh:

- nomor bukti;
- tanggal transaksi;
- penerima sumber;
- kode kegiatan;
- kode rekening;
- nilai bruto/pajak/neto;
- item BKU/RKAS.

### Data SPJ operator

Karakteristik:

- diisi/dilengkapi operator;
- tidak boleh ditimpa oleh sinkronisasi ARKAS;
- digunakan untuk dokumen pertanggungjawaban.

Contoh:

- `payment_description`;
- `receipt_recipient_name`;
- metode dan referensi pembayaran;
- penandatangan;
- kategori SPJ;
- rincian barang, konsumsi, pemeliharaan, perjalanan, honor, dan jasa.

`manual_description` sudah tidak digunakan dan tidak boleh dihidupkan kembali.

---

## 4. Entitas utama

### User dan School

Berada pada database utama.

User memiliki role aplikasi dan dapat terkait dengan sekolah. Administrator dapat mengelola banyak sekolah. Operator harus dibatasi pada konteks sekolah yang sah.

### FiscalYear dan FundSource

Berada pada tenant database dan membentuk konteks kerja aktif.

Sumber dana harus konsisten dengan tahun anggaran aktif.

### Transaction

Merepresentasikan transaksi BKU yang telah diproyeksikan menjadi entitas aplikasi.

Field domain penting meliputi:

- identitas transaksi sumber;
- tanggal dan nomor bukti;
- uraian sumber;
- `payment_description` untuk uraian operator/SPJ;
- `payment_method`;
- `payment_reference`;
- `recipient_name` sebagai penerima sumber ARKAS/BKU;
- `receipt_recipient_name` sebagai penerima kuitansi manual;
- nilai bruto, pajak, dan neto;
- kategori SPJ;
- status sumber/sinkronisasi;
- flag rekonsiliasi.

Penerima kuitansi efektif memakai fallback yang ditentukan model/service ketika nilai manual kosong.

### TransactionItem

Menyimpan rincian item sumber untuk referensi dan rekonsiliasi. Data sumber tidak dipakai sebagai tempat edit manual operator.

### Detail kategori SPJ

Data kategori dipisahkan berdasarkan kebutuhan domain. Implementasi aktif mencakup pola untuk:

- barang/pembelian;
- konsumsi/acara/peserta;
- pemeliharaan/work order/pekerja;
- SPPD/perjalanan dan banyak pelaksana;
- honor dan banyak penerima;
- jasa lainnya.

Penyimpanan detail kategori diarahkan melalui `SpjTransactionDetailsService` dan service terkait, bukan menumpuk logika baru di controller.

### SpjPackage

Merepresentasikan paket dokumen pertanggungjawaban untuk sebuah transaksi.

Lifecycle target backend:

```text
DRAFT
→ READY
→ NUMBERED
→ PRINTED / DICETAK
→ FINAL / ARCHIVED
```

UI menampilkan label manusiawi seperti:

```text
Belum lengkap
→ Siap diproses
→ Sudah bernomor
→ Sudah dicetak
→ Final
```

Preview tidak boleh mengubah lifecycle atau menerbitkan nomor.

---

## 5. Sinkronisasi ARKAS

Alur konseptual:

```text
Konfigurasi sumber ARKAS
→ ArkasBridgeClient
→ ambil identity / RKAS / BKU
→ simpan raw data sumber
→ proyeksikan/update transaksi dan item
→ tandai data yang hilang/berubah
→ pertahankan data manual operator
→ catat hasil sinkronisasi
→ tampilkan kebutuhan rekonsiliasi
```

Service utama mencakup `ArkasSynchronizationService`, varian sinkronisasi yang lebih baru bila digunakan, serta `ArkasBridgeClient`.

Prinsip safe sync:

- tidak menghapus data manual ketika source hilang;
- tidak menimpa penerima kuitansi manual;
- perubahan sumber dapat memicu `requires_reconciliation`;
- transaksi yang tidak lagi muncul dapat ditandai sebagai data sumber hilang;
- dokumen final tidak boleh berubah otomatis akibat sinkronisasi.

Pengembangan berikutnya: snapshot before/after dan diff rekonsiliasi yang eksplisit.

---

## 6. Workflow transaksi dan SPJ

Alur target:

```text
Sinkronisasi ARKAS/BKU
→ Daftar transaksi
→ Detail Transaksi
→ Data ARKAS/BKU readonly
→ Data umum SPJ editable
→ Detail kategori
→ Checklist kelengkapan
→ Siapkan paket SPJ
→ Validasi
→ READY
→ Penomoran
→ Preview
→ Unduh/Cetak
→ FINAL
```

Detail Transaksi adalah workspace operator utama.

Backend tidak boleh mengandalkan tampilan UI untuk menjaga aturan bisnis; validasi dan authorization harus tetap ditegakkan pada controller/service/middleware/policy yang relevan.

---

## 7. Penomoran dokumen

Penomoran diperlakukan sebagai workflow domain tersendiri.

Aturan:

- setiap jenis dokumen mempunyai domain nomor sendiri;
- nomor tidak mengikuti urutan input transaksi;
- kandidat harus READY;
- strategi utama adalah numbering per triwulan;
- preview/download tidak boleh membuat nomor secara otomatis;
- nomor ganda harus dicegah;
- commit numbering harus atomik;
- hasil numbering harus dapat diaudit;
- dokumen bernomor/final terkunci dari perubahan normal.

Komponen aktif mencakup controller/service workflow numbering, pengaturan format nomor, serta test khusus numbering.

---

## 8. Dokumen dan template

Fondasi generator dokumen sudah tersedia.

Komponen penting:

- `DocumentTemplateController`;
- `SpjDocumentController`;
- `SpjTemplateService`;
- DomPDF;
- PhpSpreadsheet;
- PHPWord.

Template mendukung placeholder terpusat. Referensi resmi placeholder berada pada:

```text
docs/DOCUMENT_TEMPLATE_PLACEHOLDERS.md
```

Katalog placeholder pada UI mengambil sumber dari service generator agar tidak terjadi perbedaan kemampuan antara dokumentasi UI dan backend.

Status arsitektur:

- generator sudah ada;
- preview/download lintas format masih dalam fase stabilisasi;
- preview harus bebas side effect;
- lifecycle dokumen harus dipisahkan dari aksi render file.

---

## 9. Modul aplikasi

### Authentication & setup

- initial setup;
- login/logout;
- pemilihan sekolah;
- pemilihan tahun/sumber dana.

### User administration

- user management;
- role;
- impersonation administrator.

### Database management

- provision tenant;
- activate connection;
- migrate;
- integrity check;
- checkpoint/vacuum;
- backup/restore.

### ARKAS & synced data

- source configuration;
- sinkronisasi;
- RKAS/BKU monitoring;
- reconciliation.

### Transactions

- daftar transaksi Livewire;
- pencarian/filter;
- detail transaksi;
- data manual SPJ;
- status sumber dan rekonsiliasi.

### SPJ

- persiapan paket;
- checklist;
- kategori SPJ;
- numbering;
- template;
- preview/download;
- finalization/lifecycle.

### Audit & reporting

- operational audit log;
- audit report/export;
- laporan BOS lengkap masih tahap lanjutan.

### Dashboard

- dashboard utama;
- operational dashboard;
- ringkasan progres kerja operator.

---

## 10. Lapisan aplikasi

### Controllers

Menangani HTTP request, validasi request, authorization entry point, dan koordinasi service.

Controller tidak seharusnya menjadi tempat seluruh aturan domain. `SpjController` sudah besar dan harus terus dikurangi melalui pemindahan logika ke service/use-case.

### Services

Tempat utama aturan domain dan operasi lintas model, termasuk:

- sinkronisasi ARKAS;
- database tenant;
- detail transaksi SPJ;
- validasi paket;
- template/generator;
- numbering;
- audit.

### Livewire

Dipakai untuk state/data reaktif pada modul yang membutuhkan interaksi tabel/form dinamis. `TransactionsTable` adalah implementasi utama yang sudah tersedia.

### Alpine

Hanya untuk state UI ringan seperti toggle, modal sederhana, collapse/persist UI. Alpine tidak boleh berebut state data bisnis dengan Livewire.

### Tailwind / Blade components

Menangani visual dan design system.

---

## 11. Arsitektur GUI

Acuan resmi: `docs/GUI_STANDARDIZATION.md`.

Urutan layout standar:

```text
Header global
Breadcrumb global sticky
Header halaman + summary
Form/filter/section
Tabel/workspace
```

Komponen baru/halaman yang disentuh harus mengutamakan komponen `x-ui.*` yang sudah ada.

Prinsip penting:

- tidak membuat breadcrumb lokal ganda;
- bedakan readonly source dan editable operator;
- status teknis diterjemahkan menjadi bahasa operator;
- sidebar persisten;
- dark component layer tersedia secara global;
- layout tidak boleh berubah menjadi satu card raksasa;
- perubahan GUI tidak boleh mengubah aturan bisnis secara implisit.

---

## 12. Security dan authorization

Role utama diarahkan ke:

- ADMIN;
- OPERATOR;
- VIEWER/read-only bila digunakan.

Target hardening:

- route mutation harus dilindungi backend;
- VIEWER tidak boleh mutation;
- aksi sensitif seperti finalisasi, pembatalan nomor, buka kunci, restore, dan konfigurasi harus memiliki authorization eksplisit;
- impersonation harus dibatasi;
- koneksi tenant harus sesuai sekolah aktif;
- seluruh aksi penting dicatat pada audit.

`SecurityHardeningTest` dan test authorization terkait menjadi bagian dari fondasi, tetapi audit menyeluruh masih termasuk pekerjaan sebelum release.

---

## 13. Testing

Testing memakai database terpisah dari database utama.

Area yang telah memiliki feature test mencakup:

- safe ARKAS sync;
- tenant database manager;
- school/year selection;
- transactions Livewire;
- user management;
- impersonation;
- security hardening;
- numbering;
- critical document workflow;
- synced data entities.

Setelah perubahan backend jalankan test relevan atau `php artisan test`. Setelah perubahan frontend jalankan `npm run build`.

---

## 14. Area yang belum final

Arsitektur inti sudah terbentuk, tetapi area berikut belum dianggap selesai:

1. stabilisasi generator/preview PDF, Excel, Word;
2. lifecycle dokumen lengkap;
3. locking, revisi, pembatalan dan histori nomor;
4. penomoran triwulan atomik final;
5. rekonsiliasi snapshot/diff;
6. hardening authorization seluruh role;
7. end-to-end browser test seluruh kategori;
8. laporan BOS lengkap;
9. production hardening dan release process.

---

## 15. Dokumen acuan

Baca bersama:

```text
AGENTS.md
docs/CURRENT_PROGRESS.md
docs/SPJ_DESIGN_DECISIONS.md
docs/USER_SCENARIOS.md
docs/GUI_STANDARDIZATION.md
docs/DEVELOPMENT_ROADMAP.md
docs/DOCUMENT_TEMPLATE_PLACEHOLDERS.md
```

`CURRENT_PROGRESS.md` menjelaskan keadaan implementasi terkini, sedangkan dokumen ini menjelaskan bentuk arsitektur yang harus dipertahankan.
