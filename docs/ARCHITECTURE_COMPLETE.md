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
- menyediakan audit, rekonsiliasi, backup, reset tenant, dan kontrol akses;
- memisahkan tanggung jawab HTTP dari use case/domain;
- menyatukan pengalaman pengguna melalui design system internal yang theme-aware.

Stack utama:

- PHP 8.2+;
- Laravel 12;
- Livewire 3;
- Tailwind CSS 4;
- Alpine.js + Persist;
- Vite 6;
- Filament 4 components;
- SQLite multi-koneksi;
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

### 2.3 Reset database tenant

Administrator memiliki alur reset database sekolah aktif yang bersifat destruktif namun terisolasi dari database utama.

Strategi reset:

```text
purge koneksi tenant
→ hapus file SQLite tenant
→ hapus file -wal/-shm bila ada
→ provision file SQLite baru
→ jalankan migration tenant
→ reset sqlite_sequence
→ hapus session tahun/sumber dana aktif
```

Tujuannya bukan sekadar menghapus row, tetapi mengembalikan tenant ke kondisi seperti instalasi baru sehingga auto-increment kembali dari awal.

Database utama/global tidak ikut dihapus.

---

## 3. Prinsip data sumber dan data operator

Aplikasi membedakan dua lapisan data.

### Data ARKAS/BKU

Karakteristik:

- berasal dari sinkronisasi;
- menjadi referensi resmi operator;
- tidak diedit dari workspace Detail Transaksi;
- perubahan/hilangnya data dapat memicu rekonsiliasi.

### Data SPJ operator

Karakteristik:

- diisi/dilengkapi operator;
- tidak boleh ditimpa oleh sinkronisasi ARKAS;
- digunakan untuk dokumen pertanggungjawaban.

Contoh field penting:

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

Berada pada database utama. User memiliki role aplikasi dan dapat terkait dengan sekolah. Administrator dapat mengelola banyak sekolah. Operator harus dibatasi pada konteks sekolah yang sah.

### FiscalYear dan FundSource

Berada pada tenant database dan membentuk konteks kerja aktif. Sumber dana harus konsisten dengan tahun anggaran aktif.

### Transaction

Merepresentasikan transaksi BKU yang telah diproyeksikan menjadi entitas aplikasi.

Field domain penting meliputi:

- identitas transaksi sumber;
- tanggal dan nomor bukti;
- uraian sumber;
- `payment_description`;
- `payment_method`;
- `payment_reference`;
- `recipient_name` sebagai penerima sumber ARKAS/BKU;
- `receipt_recipient_name` sebagai penerima kuitansi manual;
- nilai bruto, pajak, dan neto;
- kategori SPJ;
- status sumber/sinkronisasi;
- flag rekonsiliasi.

### Detail kategori SPJ

Data kategori dipisahkan berdasarkan kebutuhan domain. Implementasi aktif mencakup:

- barang/pembelian;
- konsumsi/acara/peserta;
- pemeliharaan/work order/pekerja;
- SPPD/perjalanan dan banyak pelaksana;
- honor dan banyak penerima;
- jasa lainnya.

`SpjTransactionDetailsService` tetap menangani sinkronisasi/persistensi detail kategori.

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

UI menampilkan label manusiawi:

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

Backend tidak boleh mengandalkan tampilan UI untuk menjaga aturan bisnis; validasi dan authorization tetap ditegakkan pada controller/use case/service/middleware/policy yang relevan.

---

## 7. Arsitektur use case SPJ

`SpjController` sudah direduksi menjadi adapter HTTP tipis. Route dan action publik tetap dipertahankan, tetapi orkestrasi dipindahkan ke use case:

```text
app/UseCases/Spj/
├── SpjWorkspaceUseCase.php
├── SpjPackageUseCase.php
├── SpjNumberingUseCase.php
├── SpjDocumentUseCase.php
└── SpjReportUseCase.php
```

Pembagian tanggung jawab:

### SpjWorkspaceUseCase

- query tab/workspace;
- metrics;
- roster peserta;
- filter/list yang terkait ruang kerja.

### SpjPackageUseCase

- prepare transaksi menjadi paket;
- update data paket;
- validasi kategori;
- pajak;
- sinkronisasi detail kategori;
- audit persiapan/perubahan.

### SpjNumberingUseCase

- numbering paket/dokumen;
- batch numbering triwulan;
- lifecycle numbering;
- final/cancel/replace;
- close/reopen quarter;
- settlement dan event date yang terkait workflow.

### SpjDocumentUseCase

- preview/download;
- validasi sebelum generate;
- integrasi template/generator.

### SpjReportUseCase

- query laporan;
- monitoring;
- export PDF/Excel/honor;
- agregasi laporan SPJ.

Aturan utama: controller baru tidak boleh kembali menjadi tempat penumpukan logika domain.

---

## 8. Penomoran dokumen

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

---

## 9. Dokumen dan template

Fondasi generator dokumen sudah tersedia melalui controller/service template, DomPDF, PhpSpreadsheet, dan PHPWord.

Referensi placeholder resmi:

```text
docs/DOCUMENT_TEMPLATE_PLACEHOLDERS.md
```

Status arsitektur:

- generator sudah ada;
- preview/download lintas format masih dalam fase stabilisasi;
- preview harus bebas side effect;
- lifecycle dokumen harus dipisahkan dari aksi render file.

---

## 10. Modul aplikasi

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
- backup/restore;
- reset tenant bersih dengan rebuild database.

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

---

## 11. Lapisan aplikasi

### Controllers

Menangani HTTP request, authorization entry point, dan delegasi ke use case/service. Controller harus tipis.

### Use cases

Menangani orkestrasi satu alur aplikasi yang memiliki beberapa langkah/service/model.

### Services

Menangani aturan domain dan operasi reusable, termasuk sinkronisasi ARKAS, tenant database, detail transaksi SPJ, validasi paket, template/generator, numbering, dan audit.

### Livewire

Dipakai untuk state/data reaktif pada modul yang membutuhkan interaksi tabel/form dinamis.

### Alpine

Hanya untuk state UI ringan seperti toggle, modal, collapse, sticky utility, dan persist UI. Alpine tidak boleh berebut state data bisnis dengan Livewire.

### Tailwind / Blade components

Menangani visual dan design system.

---

## 12. Arsitektur GUI

Acuan resmi: `docs/GUI_STANDARDIZATION.md`.

Urutan layout standar:

```text
Header global
Breadcrumb sticky
Header halaman + summary
Toolbar / filter
Form / section
Tabel / workspace
Sticky actions / utility footer
```

Primitive tema-aware yang tersedia mencakup page shell, alert, empty state, badge, detail list, toolbar, modal, action menu, loading/skeleton, sticky actions, danger zone, tabel, form controls, dan status badge.

Prinsip penting:

- jangan membuat breadcrumb lokal ganda;
- bedakan readonly source dan editable operator;
- status teknis diterjemahkan menjadi bahasa operator;
- sidebar persisten;
- sticky `Ke atas` tersedia pada halaman panjang;
- table system dan pagination memakai pola global;
- accent non-semantik berasal dari token `--theme-*`;
- dark mode memakai token surface/text/border yang sama;
- success/warning/danger tetap semantik;
- perubahan GUI tidak boleh mengubah aturan bisnis secara implisit.

---

## 13. Security dan authorization

Role utama diarahkan ke ADMIN, OPERATOR, dan VIEWER/read-only bila digunakan.

Target hardening:

- route mutation harus dilindungi backend;
- VIEWER tidak boleh mutation;
- aksi sensitif seperti finalisasi, pembatalan nomor, buka kunci, restore, reset database, dan konfigurasi harus memiliki authorization eksplisit;
- reset database hanya untuk database sekolah aktif dan administrator;
- koneksi tenant harus sesuai sekolah aktif;
- seluruh aksi penting dicatat pada audit bila relevan.

---

## 14. Testing

Testing memakai database terpisah dari database utama.

Area yang telah memiliki feature test mencakup:

- safe ARKAS sync;
- tenant database manager;
- reset tenant database/auto-increment;
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

## 15. Area yang belum final

1. stabilisasi generator/preview PDF, Excel, Word;
2. lifecycle dokumen lengkap;
3. locking, revisi, pembatalan dan histori nomor;
4. penomoran triwulan atomik final;
5. rekonsiliasi snapshot/diff;
6. hardening authorization seluruh role;
7. end-to-end browser test seluruh kategori;
8. migrasi view legacy tersisa ke primitive UI;
9. laporan BOS lengkap;
10. production hardening dan release process.

---

## 16. Dokumen acuan

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
