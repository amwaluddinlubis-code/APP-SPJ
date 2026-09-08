# Arsitektur SPJ BOSP Web

Terakhir diverifikasi: **2026-09-08**

Dokumen ini menjelaskan arsitektur aktif branch `gui-standardization`. Untuk gap implementasi baca `CURRENT_PROGRESS.md`; untuk aturan bisnis permanen baca `SPJ_DESIGN_DECISIONS.md`.

## 1. Ringkasan

SPJ BOSP Web adalah aplikasi Laravel 12 untuk menyusun dokumen pertanggungjawaban BOSP berdasarkan RKAS/BKU yang disinkronkan dari ARKAS.

Tujuan arsitektur:

- memisahkan source ARKAS/BKU dan data operator;
- mendukung banyak sekolah melalui database tenant terpisah;
- menyediakan workflow transaksi → paket → validasi → numbering → dokumen → final;
- mempertahankan overlay operator saat source berubah/hilang;
- menyediakan audit, rekonsiliasi, backup/reset tenant, dan authorization;
- memisahkan controller dari orchestration/use case/domain;
- menggunakan design system internal theme-aware.

Stack utama: PHP 8.2+, Laravel 12, Livewire 3, Alpine.js 3, Tailwind CSS 4, Vite 6, SQLite multi-koneksi, DomPDF, PhpSpreadsheet, PHPWord, PHPUnit 11.

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

## 3. Source data vs operator overlay

### Source ARKAS/BKU

Contoh: nomor bukti, tanggal transaksi, uraian sumber, rekening, kegiatan, penerima sumber, nilai bruto/pajak/neto, dan payload sinkronisasi.

Source tidak diedit dari workspace operator.

### Overlay operator SPJ

Contoh:

- `item_description` pada Detail Transaksi;
- `payment_description`;
- `payment_method` / `payment_reference`;
- `receipt_recipient_name`;
- `spj_category`;
- detail barang/konsumsi/pemeliharaan/SPPD/honor/jasa;
- vendor/manual procurement fields;
- paket, nomor, status, dan audit.

`manual_description` tidak digunakan.

## 4. Ownership workspace

### Detail Transaksi

Workspace source/context. Mutation SPJ yang diperbolehkan hanya `item_description`.

```text
description      readonly
item_description editable
quantity         readonly
unit             readonly
unit_price       readonly
amount           readonly
```

Gateway Paket memblokir create/open draft bila `item_description` belum tersimpan.

### Paket SPJ

Satu-satunya workspace mutation untuk kategori, payment/vendor, data kategori, nomor/lifecycle, dan dokumen.

Paket hanya membaca item/source tax; ia tidak boleh menulis ulang pajak atau `item_description`.

## 5. Entitas dan relasi utama

### Transaction

Representasi transaksi BKU aplikasi. Relasi penting mencakup items, goods, workOrder/workers, participants, travels, honors, serviceRecipients, payments, package, dan dokumen terkait.

### Employee

Auto-fill peserta `KONSUMSI` di Paket SPJ memakai Employee aktif dengan `source_type = DAPODIK`. Participant manual tetap diperbolehkan.

### SpjPackage

Satu Paket SPJ per transaksi pada alur aktif. Lifecycle:

```text
DRAFT
READY
NUMBERED
FINAL
CANCELLED
```

`isEditable()` membatasi mutation normal pada package yang belum terkunci.

## 6. Layer aplikasi

### Controllers

Controller menangani HTTP entry point, authorization/request validation, kemudian mendelegasikan pekerjaan domain/orchestration.

### Use cases SPJ aktif

```text
app/UseCases/Spj/
├── SpjWorkspaceUseCase.php
├── CreateSpjDraftUseCase.php
├── UpdateSpjPackageDetailsUseCase.php
├── SpjNumberingUseCase.php
├── SpjDocumentUseCase.php
└── SpjReportUseCase.php
```

- `SpjWorkspaceUseCase` — workspace/tab/query/metrics/package navigation;
- `CreateSpjDraftUseCase` — gateway idempotent create/open DRAFT;
- `UpdateSpjPackageDetailsUseCase` — update data Paket tanpa menulis source tax/item;
- `SpjNumberingUseCase` — workflow penomoran;
- `SpjDocumentUseCase` — preview/download/generator;
- `SpjReportUseCase` — reporting/export/monitoring.

`SpjPackageUseCase` legacy sudah tidak ada dan tidak boleh direferensikan kembali.

### Services

Contoh service domain/reusable: `SpjTransactionDetailsService`, `SpjPackageValidationService`, document requirements, procurement policy, numbering service, ARKAS sync, tenant database manager, dan operational audit.

### Frontend state

- Alpine: tab, row editor, pagination lokal, form helper;
- JS module: category AJAX, maintenance linkage, package workspace UI, action modal, document placement;
- Livewire: state server-backed pada area yang memang Livewire;
- business validation tetap backend.

## 7. Workflow SPJ aktif

```text
Sinkronisasi ARKAS/BKU
→ Daftar Transaksi
→ Detail Transaksi
   → periksa source
   → simpan item_description
→ Create/Open Paket
→ Isian Manual Paket
→ validation
→ READY
→ Numbering
→ Preview/Download
→ FINAL
```

Preview/download tidak boleh menjadi shortcut tersembunyi untuk numbering.

## 8. Workspace Paket SPJ

Toolbar:

```text
Semua Paket | Paket Sebelumnya | Paket Setelahnya
```

`SpjWorkspaceUseCase::packageNavigation()` mencari Paket previous/next pada active context dan mengurutkan transaksi berdasarkan `transaction_date` lalu `id`.

Summary:

```text
Periode | Penerima | Bruto | Pajak | Nilai Dibayarkan
```

Sub-tab:

```text
1. Rincian
2. Isian Manual
3. Rincian Pajak
4. Penomoran
```

`Rincian Pajak` bersifat readonly dan mengambil source transaction tax.

### Isian Manual

Struktur utama:

```text
Kategori SPJ + kontrol konteks
→ Informasi nomor otomatis
→ Data Umum Dokumen
→ partial kategori
→ Simpan
```

BARANG menampilkan mode SiPLah/Non SiPLah sebagai radio group mutually-exclusive. PEMELIHARAAN menampilkan selector pasangan transaksi di context row.

Nomor otomatis tidak lagi menjadi form input operator. Backend detail synchronization menjaga nomor yang sudah diterbitkan bila request manual tidak mengirim field nomor tersebut.

## 9. Tabel kategori non-BARANG

`row-editor.blade.php` menjadi tabel compact untuk kategori yang memakai editor generik. KONSUMSI dan PEMELIHARAAN memiliki tabel compact sendiri.

Kontrak UI:

- satu pagination lokal per tabel;
- tabel menandai `data-pagination="none"` agar global table standardizer tidak menambah pager kedua;
- satu radio `Penerima Utama`;
- integer untuk hari/porsi/kali;
- accounting `1.000` tanpa `Rp`/desimal untuk uang/tarif;
- field width mengikuti tipe data.

`primary_recipient_index` dipetakan backend ke `receipt_recipient_name`; model yang memiliki row-level flag dapat ikut disinkronkan.

## 10. Pemeliharaan bahan + upah

Relationship state:

```text
maintenance_material_transaction_id
maintenance_labor_transaction_id
```

Tetap transaction/context-owned dan disimpan melalui endpoint `transactions.maintenance-links.*`.

Selector ditampilkan di Paket SPJ untuk UX, tetapi tidak menjadi field package form.

Document context dapat membaca material dari transaksi bahan terkait dan pekerja/upah dari transaksi pasangan tanpa menulis ulang source BKU.

## 11. Pajak

PPN/PPh/SSPD/tax_total/net_amount adalah source transaction.

- Detail Transaksi menampilkan Total Pajak secara ringkas bersama Informasi Referensi ARKAS/BKU;
- summary Paket menampilkan Pajak;
- tab Rincian Pajak menampilkan detail readonly;
- `UpdateSpjPackageDetailsUseCase` mengabaikan request tax forged.

## 12. Tanggal pengadaan

Rule canonical:

```text
order_date <= transaction_date
order_date <= bap_date
bap_date <= bast_date
```

Frontend helper `spj-purchase-date-validation.js` mengikuti rule tersebut.

Tidak ada lagi compatibility backend `TransactionController::updateManualDescription()`; method/route tersebut sudah dipensiunkan.

## 13. Penomoran dokumen

- domain nomor dipisahkan per jenis dokumen;
- nomor mengikuti tanggal/peristiwa dokumen bila tersedia;
- nomor aktif tidak boleh ditimpa;
- cancelled number tetap menjadi history;
- NUMBERED/FINAL terkunci;
- nomor otomatis ditampilkan sebagai informasi, bukan input operator.

`SpjDocumentNumberService` menangani mapping otomatis termasuk PESANAN, BAP, BAST, SPK, RAB, dan dokumen yang dikonfigurasi.

## 14. SiPLah

SiPLah adalah procurement/payment channel, bukan `spj_category`.

Dukungan aktif:

- `payment_method = siplah`;
- `siplah_order_number`;
- vendor/owner/NPWP;
- invoice/reference;
- placeholder template;
- policy requirement SiPLah/Non-SiPLah.

Surat Pesanan internal Non-SiPLah dan nomor marketplace SiPLah adalah konsep berbeda.

## 15. Vite dan asset frontend

Entry Vite canonical:

```text
resources/css/app.css
resources/js/app.js
```

Feature JS diimpor melalui bootstrap/app bundle. View tidak boleh menghidupkan kembali stale standalone `@vite` entry hanya untuk memperbaiki manifest lama.

## 16. GUI/theme

Acuan:

```text
docs/GUI_STANDARDIZATION.md
docs/CSS_USAGE_GUIDE.md
```

Gunakan token `--ui-*`, `--theme-*`, primitive `x-ui.*`, dan class `ui-*` untuk markup baru.

## 17. Security/authorization

Target role utama: ADMIN, OPERATOR, VIEWER/read-only.

Mutation sensitif harus dilindungi backend, terutama numbering, cancel/reopen/finalization, reset/restore tenant, configuration, dan reconciliation.

## 18. Testing

Focused regression suite SPJ dilaporkan user ALL PASS pada 2026-09-08 setelah refactor workspace besar.

Perubahan visual/JS setelah checkpoint tersebut tetap memerlukan browser QA dan rebuild. Jangan menyimpulkan browser runtime PASS hanya dari PHPUnit.

## 19. Area belum final

1. browser QA refinement Paket terbaru;
2. APP DATA runtime pada database nyata;
3. JASA_LAINNYA output multi-penerima end-to-end;
4. generator/preview Word/Excel/PDF release hardening;
5. lifecycle/locking/revision terpadu;
6. reconciliation snapshot/diff;
7. authorization per role;
8. end-to-end semua kategori sampai FINAL;
9. mobile regression;
10. laporan BOS/Pusat Laporan.

## 20. Dokumen acuan

```text
README.md
AGENTS.md
docs/CURRENT_PROGRESS.md
docs/SPJ_DESIGN_DECISIONS.md
docs/GUI_STANDARDIZATION.md
docs/CSS_USAGE_GUIDE.md
docs/DEVELOPMENT_ROADMAP.md
docs/URGENT_TRANSACTION_SPJ_MIGRATION.md
docs/DOCUMENT_TEMPLATE_PLACEHOLDERS.md
docs/SIPLAH_MVP_PLAN.md
docs/MOBILE_VISUAL_QA_TODO.md
```
