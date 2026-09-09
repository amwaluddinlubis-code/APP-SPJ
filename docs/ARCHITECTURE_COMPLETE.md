# Arsitektur SPJ BOSP Web

Terakhir diverifikasi: **2026-09-10** terhadap head implementasi `ceb8df6f2a73c4e69cf13de8048ada2fff245fce`.

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

Menyimpan tahun anggaran, sumber dana, source RKAS/BKU, transaksi, item, detail kategori SPJ, paket, nomor dokumen, audit, importer staging/profile/run, dan data kerja sekolah.

Boundary canonical operasi tenant:

```text
School + Fiscal Year + Fund Source
```

Model yang memakai connection `school` tidak boleh dibaca/ditulis sebelum sekolah aktif diaktivasi. Untuk request yang bergantung tahun anggaran, fiscal year aktif juga harus divalidasi terlebih dahulu.

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

Identity layer aktif dapat menyimpan provenance dari ARKAS/PTK, Dapodik, dan data manual dengan identifier seperti NUPTK/NIP/NIK serta normalized name.

Keputusan bisnis permanen untuk auto-fill peserta `KONSUMSI` tetap:

```text
Auto-fill peserta = Employee.source_type DAPODIK
Participant manual = diperbolehkan
```

Karena implementasi identity/roster terbaru mulai membuka data lintas sumber, setiap jalur UI yang memperluas auto-fill melampaui Dapodik-only dianggap gap terhadap design contract dan dilacak di `CURRENT_PROGRESS.md` / `DEVELOPMENT_ROADMAP.md`. Arsitektur tidak mengubah rule permanen hanya karena implementasi sementara berbeda.

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

`SpjController` saat ini berfungsi sebagai HTTP boundary tipis dan meneruskan orchestration ke use case.

### Use cases SPJ aktif

```text
app/UseCases/Spj/
├── SpjWorkspaceUseCase.php
├── CreateSpjDraftUseCase.php
├── UpdateSpjPackageDetailsUseCase.php
├── SpjNumberingUseCase.php
├── SpjDocumentUseCase.php
├── SpjPackageCategoryUseCase.php
└── SpjReportUseCase.php
```

- `SpjWorkspaceUseCase` — workspace/tab/query/metrics/package navigation;
- `CreateSpjDraftUseCase` — gateway idempotent create/open DRAFT;
- `UpdateSpjPackageDetailsUseCase` — update data Paket tanpa menulis source tax/item;
- `SpjPackageCategoryUseCase` — mutation kategori Paket;
- `SpjNumberingUseCase` — workflow penomoran/lifecycle terkait;
- `SpjDocumentUseCase` — preview/download/generator;
- `SpjReportUseCase` — reporting/export/monitoring.

`SpjPackageUseCase` legacy sudah tidak ada dan tidak boleh direferensikan kembali.

`SpjNumberingUseCase` masih cukup besar dan dapat dipecah lebih lanjut setelah P0 correctness selesai, tetapi refactor tersebut bukan alasan menunda blocker importer/tenant.

### Services

Contoh service domain/reusable: `SpjTransactionDetailsService`, `SpjPackageValidationService`, document requirements, procurement policy, numbering service, ARKAS sync, employee identity, tenant database manager, dan operational audit.

### Frontend state

- Alpine: tab, row editor, pagination lokal, form helper;
- JS module: category AJAX, maintenance linkage, package workspace UI, action modal, document placement;
- Livewire: state server-backed pada area yang memang Livewire;
- business validation tetap backend.

## 7. Pipeline sinkronisasi ARKAS aktif

### 7.1 Canonical sync transaksi/source

Entry point runtime utama memakai coordinator `ArkasCanonicalSyncService`:

```text
ArkasCanonicalSyncService
  -> ArkasStagingService
  -> ArkasReferenceSynchronizationService
  -> adapter transaksi/SPJ
  -> reconciliation dan derived references
```

Adapter transaksi yang menjaga identitas transaksi, overlay manual, paket SPJ, source missing/returning, dan reconciliation tetap merupakan boundary release-safety utama.

### 7.2 Generic ARKAS Importer

Importer tambahan memakai:

```text
ArkasImporterController
→ ArkasDatabaseExplorer / Bridge
→ ArkasImportProfile
→ ArkasStagingService
→ ArkasReconciliationService
→ ArkasGenericImportService
→ ArkasDomainAdapter
→ target domain / raw snapshot
```

Snapshot disimpan pada `arkas_import_rows`; histori disimpan pada `arkas_import_runs`.

Kontrak arsitektur importer:

- ARKAS source tetap readonly;
- request importer yang memakai model tenant harus mengaktifkan sekolah yang benar terlebih dahulu;
- request yang bergantung tahun anggaran harus memvalidasi fiscal year aktif;
- source key harus deterministic dan konsisten antara preview, staging, reconciliation, dan sync;
- full refresh boleh membersihkan target dalam scope fiscal year/domain yang benar, tetapi tidak boleh menyapu tenant/konteks lain;
- queue job wajib mengaktifkan tenant sebelum query/write connection `school`.

Status implementasi Generic Importer pada head `ceb8df6` **belum operator-ready** karena dua gap release-safety yang dicatat sebagai P0-08:

1. `ArkasGenericImportService` memanggil resolver `sourceKey()` yang belum diimplementasikan pada service tersebut;
2. route importer belum seluruhnya berada pada boundary `active-school` + `active-year` meskipun membaca model connection `school`.

`ArkasFullSynchronizationService` dan `ArkasSynchronizationService` tidak digunakan sebagai entry point runtime baru. Detail operasional importer ada pada `docs/ARKAS_IMPORTER.md` dan status release pada `docs/CURRENT_PROGRESS.md`.

## 8. Workflow SPJ aktif

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

## 9. Workspace Paket SPJ

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

## 10. Tabel kategori non-BARANG

`row-editor.blade.php` menjadi tabel compact untuk kategori yang memakai editor generik. KONSUMSI dan PEMELIHARAAN memiliki tabel compact sendiri.

Kontrak UI:

- satu pagination lokal per tabel;
- tabel menandai `data-pagination="none"` agar global table standardizer tidak menambah pager kedua;
- satu radio `Penerima Utama`;
- integer untuk hari/porsi/kali;
- accounting `1.000` tanpa `Rp`/desimal untuk uang/tarif;
- field width mengikuti tipe data.

`primary_recipient_index` dipetakan backend ke `receipt_recipient_name`; model yang memiliki row-level flag dapat ikut disinkronkan.

## 11. Pemeliharaan bahan + upah

Relationship state:

```text
maintenance_material_transaction_id
maintenance_labor_transaction_id
```

Tetap transaction/context-owned dan disimpan melalui endpoint `transactions.maintenance-links.*`.

Selector ditampilkan di Paket SPJ untuk UX, tetapi tidak menjadi field package form.

Document context dapat membaca material dari transaksi bahan terkait dan pekerja/upah dari transaksi pasangan tanpa menulis ulang source BKU.

## 12. Pajak

PPN/PPh/SSPD/tax_total/net_amount adalah source transaction.

- Detail Transaksi menampilkan Total Pajak secara ringkas bersama Informasi Referensi ARKAS/BKU;
- summary Paket menampilkan Pajak;
- tab Rincian Pajak menampilkan detail readonly;
- `UpdateSpjPackageDetailsUseCase` mengabaikan request tax forged.

## 13. Tanggal pengadaan

Rule canonical:

```text
order_date <= transaction_date
order_date <= bap_date
bap_date <= bast_date
```

Frontend helper `spj-purchase-date-validation.js` mengikuti rule tersebut.

Tidak ada lagi compatibility backend `TransactionController::updateManualDescription()`; method/route tersebut sudah dipensiunkan.

## 14. Penomoran dokumen

- domain nomor dipisahkan per jenis dokumen;
- nomor mengikuti tanggal/peristiwa dokumen bila tersedia;
- nomor aktif tidak boleh ditimpa;
- cancelled number tetap menjadi history;
- NUMBERED/FINAL terkunci;
- nomor otomatis ditampilkan sebagai informasi, bukan input operator.

`SpjDocumentNumberService` menangani mapping otomatis termasuk PESANAN, BAP, BAST, SPK, RAB, dan dokumen yang dikonfigurasi.

## 15. SiPLah

SiPLah adalah procurement/payment channel, bukan `spj_category`.

Dukungan aktif:

- `payment_method = siplah`;
- `siplah_order_number`;
- vendor/owner/NPWP;
- invoice/reference;
- placeholder template;
- policy requirement SiPLah/Non-SiPLah.

Surat Pesanan internal Non-SiPLah dan nomor marketplace SiPLah adalah konsep berbeda.

## 16. Vite dan asset frontend

Entry Vite canonical:

```text
resources/css/app.css
resources/js/app.js
```

Feature JS diimpor melalui bootstrap/app bundle. View tidak boleh menghidupkan kembali stale standalone `@vite` entry hanya untuk memperbaiki manifest lama.

CSS sekarang memakai ordered entrypoint, tetapi masih memiliki compatibility/theme layers yang cukup banyak. Pengurangan layer/bundle adalah P2 setelah correctness P0 stabil.

## 17. GUI/theme

Acuan:

```text
docs/GUI_STANDARDIZATION.md
docs/CSS_USAGE_GUIDE.md
```

Gunakan token `--ui-*`, `--theme-*`, primitive `x-ui.*`, dan class `ui-*` untuk markup baru.

## 18. Security/authorization

Target role utama: ADMIN, OPERATOR, VIEWER/read-only.

Mutation sensitif harus dilindungi backend, terutama numbering, cancel/reopen/finalization, reset/restore tenant, configuration, dan reconciliation.

Authorization role dan tenant activation adalah dua boundary berbeda. Route administrator-only tetap dapat salah tenant bila connection `school` belum diaktivasi. Oleh karena itu setiap fitur tenant administratif tetap wajib melewati tenant context yang benar.

## 19. Testing

Checkpoint GitHub Actions pada head implementasi `ceb8df6`:

```text
frontend build         PASS
Blade compile/cache    PASS
SPJ Critical PHPUnit   PASS — 124 tests / 810 assertions
Pint repository-wide   WARN — 1 style issue
```

Pint masih advisory sehingga workflow dapat hijau walaupun style check belum clean. Generic Importer/Employee Identity belum seluruhnya tercakup oleh release-critical suite, sehingga critical PASS tidak boleh ditafsirkan sebagai pembuktian runtime untuk P0-08/P1-06.

Perubahan visual/JS tetap memerlukan browser QA dan rebuild. Jangan menyimpulkan browser runtime PASS hanya dari PHPUnit.

## 20. Area belum final

Urutan gap aktif saat ini:

1. Generic ARKAS Importer correctness + tenant context (P0-08);
2. E2E enam kategori pada database nyata sampai FINAL (P0-01);
3. generator/preview Word/Excel/PDF pada output nyata (P0-02);
4. APP DATA backup/reset/restore pada tenant nyata (P0-07);
5. browser QA Paket desktop/laptop;
6. JASA_LAINNYA output multi-penerima end-to-end;
7. PEMELIHARAAN bahan + upah full-document QA;
8. SiPLah end-to-end;
9. Employee/participant roster alignment terhadap kontrak Dapodik-only;
10. audit trail operasional end-to-end;
11. GUI/style/performance/repository cleanup;
12. laporan BOS/Pusat Laporan.

Mobile/responsive penuh adalah future development dan bukan release blocker target operator laptop/desktop saat ini.

## 21. Dokumen acuan

```text
README.md
AGENTS.md
docs/CURRENT_PROGRESS.md
docs/SPJ_DESIGN_DECISIONS.md
docs/GUI_STANDARDIZATION.md
docs/CSS_USAGE_GUIDE.md
docs/DEVELOPMENT_ROADMAP.md
docs/ARKAS_IMPORTER.md
docs/URGENT_TRANSACTION_SPJ_MIGRATION.md
docs/DOCUMENT_TEMPLATE_PLACEHOLDERS.md
docs/SIPLAH_MVP_PLAN.md
docs/MOBILE_VISUAL_QA_TODO.md
```
