# Arsitektur SPJ BOSP Web

Terakhir diverifikasi: **2026-09-06**

Dokumen ini menjelaskan arsitektur aktif branch `gui-standardization`. Untuk kondisi implementasi paling mutakhir, baca bersama `CURRENT_PROGRESS.md`. Untuk aturan bisnis yang tidak boleh berubah, baca `SPJ_DESIGN_DECISIONS.md`.

---

## 1. Ringkasan

SPJ BOSP Web adalah aplikasi Laravel 12 untuk menyusun dokumen pertanggungjawaban BOSP berdasarkan RKAS/BKU yang disinkronkan dari ARKAS.

Tujuan arsitektur:

- memisahkan source ARKAS/BKU dan data operator;
- mendukung banyak sekolah melalui database tenant terpisah;
- menyediakan workflow transaksi → paket → validasi → numbering → dokumen → final;
- mempertahankan data manual saat source berubah/hilang;
- menyediakan audit, rekonsiliasi, backup/reset tenant, dan authorization;
- memisahkan HTTP/controller dari orchestration/domain;
- menggunakan design system internal theme-aware.

Stack utama: PHP 8.2+, Laravel 12, Livewire 3, Alpine.js, Tailwind CSS 4, Vite 6, Filament components, SQLite multi-koneksi, DomPDF, PhpSpreadsheet, PHPWord, PHPUnit 11.

---

## 2. Multi-database

### Database utama

Menyimpan user, sekolah, konfigurasi tenant, sumber ARKAS, backup, setup, dan metadata global.

### Database tenant/sekolah

Menyimpan tahun anggaran, sumber dana, source RKAS/BKU, transaksi, item, detail kategori SPJ, paket, nomor dokumen, audit, dan data kerja sekolah.

Semua operasi tenant harus berada pada konteks sekolah/tahun/sumber dana aktif.

### Reset tenant

Reset sekolah aktif menggunakan strategi rebuild SQLite:

```text
purge koneksi tenant
→ hapus file tenant + -wal/-shm
→ provision database baru
→ jalankan migration tenant
→ reset sqlite_sequence
→ bersihkan session konteks tenant
```

Database utama tidak ikut dihapus.

---

## 3. Source data vs operator data

### Source ARKAS/BKU

Contoh: nomor bukti, tanggal transaksi, uraian sumber, rekening, kegiatan, penerima sumber, nilai bruto/pajak/neto, dan payload sinkronisasi.

Source tidak diedit dari workspace operator.

### Data operator SPJ

Contoh:

- `payment_description`;
- `payment_method` / `payment_reference`;
- `receipt_recipient_name`;
- `spj_category`;
- detail barang/konsumsi/pemeliharaan/SPPD/honor/jasa;
- vendor/manual procurement fields;
- paket, nomor, status, dan audit.

`manual_description` tidak digunakan.

---

## 4. Entitas dan relasi utama

### Transaction

Merepresentasikan transaksi BKU yang sudah diproyeksikan menjadi entitas aplikasi.

Relasi penting mencakup item transaksi, goods, workers/work order, participants, travels, honors, payments, package, dan detail dokumen terkait.

### Employee

Data pegawai dapat berasal dari beberapa source. Untuk kebutuhan tertentu source harus eksplisit.

Khusus auto-fill peserta kategori `KONSUMSI` pada Detail Transaksi, daftar `$dapodikTeachers` hanya berisi `Employee` aktif dengan:

```text
source_type = DAPODIK
```

Data yang hanya berasal dari ARKAS tidak dipakai oleh `fillTeachers()`.

### SpjPackage

Merepresentasikan paket SPJ untuk sebuah transaksi. Status aktif yang digunakan codebase mencakup `DRAFT`, `READY`, `NUMBERED`, `FINAL`, dan `CANCELLED` pada alur yang relevan.

`isEditable()` membatasi perubahan normal pada kondisi yang belum terkunci.

---

## 5. Safe synchronization

Alur konseptual:

```text
ARKAS Bridge/source
→ ambil RKAS/BKU
→ simpan/update source
→ proyeksikan transaction/items
→ tandai source hilang/berubah
→ pertahankan manual overlay
→ rekonsiliasi bila diperlukan
```

Prinsip:

- source hilang tidak otomatis menghapus transaksi manual;
- `receipt_recipient_name` dan field operator tidak ditimpa;
- perubahan dapat memicu `requires_reconciliation`;
- final/numbered document tidak boleh berubah diam-diam karena sync.

---

## 6. Layer aplikasi

### Controllers

Menangani request/response, entry point authorization/validation, lalu delegasi.

### Use cases SPJ

```text
app/UseCases/Spj/
├── SpjWorkspaceUseCase.php
├── SpjPackageUseCase.php
├── SpjNumberingUseCase.php
├── SpjDocumentUseCase.php
└── SpjReportUseCase.php
```

- `SpjWorkspaceUseCase`: workspace, tab, query/filter/metrics.
- `SpjPackageUseCase`: prepare/update package, kategori, pajak, detail package.
- `SpjNumberingUseCase`: penomoran, quarter workflow, lifecycle numbering.
- `SpjDocumentUseCase`: preview/download/generator.
- `SpjReportUseCase`: reporting/export/monitoring.

### Services

Menangani aturan reusable/domain, misalnya `SpjTransactionDetailsService`, package validator, document requirements, procurement policy, numbering service, ARKAS sync, tenant database, dan audit.

### Livewire / Alpine

Livewire memegang state server-backed. Alpine digunakan untuk state UI ringan seperti tab internal, modal, drag/reorder, dan helper form. Business validation tetap backend.

---

## 7. Workflow SPJ aktif

```text
Sinkronisasi ARKAS/BKU
→ Daftar transaksi
→ Detail Transaksi
→ Lengkapi data SPJ
→ Prepare package
→ Package validation
→ READY
→ Numbering
→ Preview/Download
→ Final
```

Preview/download tidak boleh menjadi shortcut tersembunyi untuk numbering.

---

## 8. Arsitektur validasi Surat Pesanan

`SpjDocumentRequirementService` memisahkan Surat Pesanan internal menjadi dua requirement:

### `internal_order_content`

Applicable untuk Non-SiPLah + kategori barang (`BARANG`, `BELANJA_MODAL`, `KONSUMSI`).

Blocking sampai substansi tersedia:

- vendor;
- tanggal pesanan;
- item transaksi;
- uraian item;
- quantity > 0;
- satuan;
- unit price/amount valid;
- gross amount > 0.

### `internal_order_number`

Applicable pada transaksi yang sama, tetapi baru `required=true` ketika package `NUMBERED` atau `FINAL`.

Ini mencegah circular dependency: nomor PESANAN diterbitkan oleh aplikasi saat numbering, sehingga nomor tidak boleh menjadi blocker sebelum proses tersebut.

---

## 9. Arsitektur tanggal pengadaan

`SpjPackageUseCase` menggunakan constraint utama:

```text
order_date <= transaction_date
bap_date >= order_date
bast_date >= bap_date
```

Frontend helper `spj-purchase-date-validation.js` menyelaraskan min/max dan custom validity pada form yang memiliki `order_date`, `bap_date`, dan `bast_date`.

**Perbedaan aktif yang harus dicatat:** `TransactionController::updateManualDescription()` masih menambahkan upper bound `bap_date <= transaction_date` dan `bast_date <= transaction_date`. Jadi dua entry point backend belum 100% identik. Ini technical debt, bukan aturan domain baru yang sengaja ditetapkan.

---

## 10. Penomoran dokumen

Aturan arsitektur:

- domain nomor dipisahkan per jenis dokumen;
- nomor tidak mengikuti urutan input transaksi;
- tanggal peristiwa/dokumen menjadi basis urutan bila tersedia;
- numbering harus idempotent/aman terhadap nomor aktif;
- nomor dibatalkan disimpan sebagai histori, bukan dihapus;
- package bernomor/final dikunci dari edit normal.

`SpjDocumentNumberService` menangani mapping otomatis termasuk `PESANAN`, `BAP`, `BAST`, `SPK`, `RAB`, dan dokumen lain yang dikonfigurasi.

---

## 11. SiPLah

SiPLah adalah procurement/payment channel, bukan `spj_category`.

Dukungan saat ini:

- `payment_method = siplah`;
- `siplah_order_number`;
- vendor/owner/NPWP;
- invoice/reference;
- placeholder template SiPLah;
- policy requirement yang membedakan SiPLah/Non-SiPLah.

Dokumen tetap ditentukan oleh kategori SPJ. Surat Pesanan internal Non-SiPLah dan nomor marketplace SiPLah adalah konsep berbeda.

---

## 12. Arsitektur GUI/theme

Acuan utama:

```text
docs/GUI_STANDARDIZATION.md
docs/CSS_USAGE_GUIDE.md
```

Entry point CSS:

```text
resources/css/app.css
└── resources/css/theme-system.css
```

Layer relevan:

- `token-native-components.css` — contract `ui-*`;
- `transactions-standardization.css` — transaksi/detail;
- `spj-workspace-standardization.css` — SPJ workspace;
- `dark-form-controls.css` — dark form safety;
- `spj-package-theme-fix.css` — Paket/Isian Manual compatibility/theme;
- `spj-package-document-placement.css` — layout panel Rincian + Dokumen Template.

### Paket SPJ

Pada Paket, sub-tab internal adalah `Rincian`, `Isian Manual`, `Penomoran`.

`Dokumen & Template` secara DOM dipindahkan ke sub-tab `Rincian` oleh `resources/js/spj-package-document-placement.js`. Di Rincian, transaksi dan template ditampilkan sebagai panel terpisah dengan header theme-aware. Ini presentation layer; business workflow tetap backend.

---

## 13. Security/authorization

Target role utama: ADMIN, OPERATOR, VIEWER/read-only.

Mutation penting harus dilindungi backend, terutama numbering, cancel/reopen/finalization, reset/restore tenant, configuration, dan reconciliation.

---

## 14. Testing

Database testing dipisahkan dari database utama.

Setelah perubahan backend gunakan focused test yang relevan. Setelah perubahan frontend jalankan `npm run build`.

Jangan menyimpulkan full suite hijau hanya dari satu focused test.

---

## 15. Area belum final

1. konsistensi backend purchase-date rules;
2. generator/preview PDF/Word/Excel end-to-end;
3. lifecycle/locking/revision lengkap;
4. numbering quarter hardening;
5. reconciliation snapshot/diff;
6. authorization per role;
7. end-to-end browser test semua kategori;
8. mobile regression;
9. migration view legacy ke primitive canonical;
10. laporan BOS dan production hardening.

---

## 16. Dokumen acuan

```text
README.md
AGENTS.md
docs/CURRENT_PROGRESS.md
docs/SPJ_DESIGN_DECISIONS.md
docs/USER_SCENARIOS.md
docs/GUI_STANDARDIZATION.md
docs/CSS_USAGE_GUIDE.md
docs/DEVELOPMENT_ROADMAP.md
docs/DOCUMENT_TEMPLATE_PLACEHOLDERS.md
docs/SIPLAH_MVP_PLAN.md
docs/MOBILE_VISUAL_QA_TODO.md
```
