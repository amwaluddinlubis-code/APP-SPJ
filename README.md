# SPJ BOSP Web

Aplikasi web penyusunan Surat Pertanggungjawaban (SPJ) BOSP berbasis Laravel. Branch pengembangan aktif: `gui-standardization`.

Terakhir diperbarui: **2026-09-11**.

## Status branch saat ini

Checkpoint kode terbaru yang sudah melewati release gate:

```text
commit : 0df9b2ffbf14ed191e36c063e6355f9cb63c4a66
subject: test: gate template upload routing regression
CI run : 34578276166
CI job : 103195683045
result : PASS — 243 tests / 1848 assertions
```

Gate pada checkpoint tersebut:

```text
Frontend build       PASS
Blade compile/cache  PASS
SPJ Critical         PASS — 243 tests / 1848 assertions
Repository Pint      ADVISORY — 2 style issues
```

Dua style issue Pint yang masih advisory berada di `app/Services/ArkasStagingService.php` dan `tests/Feature/SyncProgressUiTest.php`. Keduanya bukan blocker functional gate saat ini.

Status release keseluruhan tetap **belum final release**. Core SPJ sudah mempunyai deterministic regression yang kuat, real-data verification sudah dimulai, sedangkan installed-runtime verification saat ini tidak menjadi fokus pekerjaan berikutnya.

## Dokumentasi utama

- `docs/CURRENT_PROGRESS.md` — status release, checkpoint, gap aktif, dan evidence terbaru.
- `docs/DEVELOPMENT_ROADMAP.md` — prioritas pekerjaan berikutnya.
- `docs/SPJ_DESIGN_DECISIONS.md` — aturan bisnis/domain permanen.
- `docs/ARCHITECTURE_COMPLETE.md` — arsitektur aplikasi dan boundary tenant.
- `docs/ARKAS_IMPORTER.md` — pipeline Generic ARKAS Importer.
- `docs/GUI_STANDARDIZATION.md` — kontrak GUI.
- `docs/CSS_USAGE_GUIDE.md` — kontrak CSS/theme.
- `docs/SIPLAH_MVP_PLAN.md` — batas MVP SiPLah.

## Kontrak arsitektur inti

ARKAS/BKU adalah source readonly. Data operator SPJ adalah overlay yang dipertahankan ketika source disinkronkan ulang.

Boundary tenant canonical:

```text
School + Fiscal Year + Fund Source
```

Ownership final:

```text
Detail Transaksi = source ARKAS/BKU + item_description
Paket SPJ        = kategori, payment/procurement channel, vendor/penerima,
                    detail kategori, numbering, template, preview/download,
                    lifecycle, dan finalisasi
```

Aturan yang tidak boleh diregresikan:

- Detail Transaksi hanya menulis `item_description`;
- nilai source, kuantitas, unit, harga, pajak, dan metadata ARKAS/BKU tidak dimutasi dari Paket SPJ;
- kategori canonical: `BARANG`, `KONSUMSI`, `PEMELIHARAAN`, `JASA_LAINNYA`, `SPPD`, `HONOR_PEGAWAI`;
- SiPLah adalah procurement/payment channel, bukan kategori;
- Paket `READY` yang benar-benar berganti kategori wajib kembali ke `DRAFT` untuk revalidation;
- preview/download tidak boleh menerbitkan nomor baru;
- `NUMBERED`/`FINAL` terkunci dari edit normal.

## Workflow operator

```text
Login
→ Pilih sekolah / tahun / sumber dana
→ Sinkronisasi ARKAS/BKU
→ Daftar Transaksi
→ Detail Transaksi
   → periksa source
   → koreksi item_description
→ Siapkan / buka Paket SPJ
→ Lengkapi Isian Manual
→ READY
→ Penomoran
→ Preview / Unduh
→ FINAL / Arsip
```

## Template Dokumen — upload sudah di-hardening

Halaman **Pengaturan Template Dokumen** mendukung dua jalur berbeda:

```text
Import Paket Template  = 1 workbook XLSX master → 11 template canonical
Upload Satu Template   = 1 file DOCX/XLSX → 1 document type
```

Perbaikan upload terbaru:

- form memakai mode eksplisit `?upload=package` dan `?upload=single`;
- mode tetap dapat dikenali walaupun PHP membuang body POST karena `post_max_size` terlampaui;
- error paket dan error upload individual memakai error bag terpisah;
- validasi file memakai extension contract (`docx`, `xlsx`) dan tidak lagi bergantung pada MIME Windows yang bisa berbeda;
- UI menampilkan `upload_max_filesize`, `post_max_size`, dan batas efektif server;
- request yang melampaui `post_max_size` menghasilkan pesan yang menjelaskan batas PHP;
- file template selalu disimpan, divalidasi, diunduh, dan dihapus melalui disk `local` yang sama dengan generator;
- replacement tetap atomic: file lama dipertahankan sampai perubahan database berhasil;
- upload invalid tidak mengganti template aktif.

Regression yang mengunci jalur ini:

```text
tests/Feature/DocumentTemplateUploadValidationTest.php
tests/Feature/DocumentTemplateUploadRoutingRegressionTest.php
```

Keduanya berada di suite `SPJ Critical`.

## Generator dokumen

Functional generator sudah mencakup:

- DOCX/XLSX nyata yang dapat dibuka parser Office;
- PDF nyata dengan signature dan EOF marker;
- render preflight sebelum output;
- unresolved placeholder guard;
- final artifact validation;
- package XLSX multi-sheet dan PDF;
- preview/download tanpa numbering side effect;
- placeholder umum untuk enam kategori canonical.

Yang masih perlu real-template/operator verification adalah visual fidelity template resmi: print area, page break, header/footer, tabel dinamis, ukuran halaman, serta hasil akhir di Microsoft Word/Excel/PDF viewer target.

## Real-data checkpoint

Database sekolah nyata terbaru yang dianalisis mempunyai transaksi dan detail transaksi nyata serta Paket SPJ yang sudah disiapkan. Checkpoint real-data utama saat ini:

```text
transactions       170
transaction_items  407
spj_packages        66
package status      66 READY
spj_documents        0
number sequences     0
number formats       0
```

Kategori tahun 2026 tersedia untuk `BARANG`, `HONOR_PEGAWAI`, `JASA_LAINNYA`, `KONSUMSI`, dan `PEMELIHARAAN`. Data `SPPD` nyata tersedia pada tahun 2025, sehingga tidak boleh dibuat data SPPD 2026 hanya untuk memaksakan six-category real-data coverage.

Real-data berikutnya harus tetap mengikuti aturan: audit read-only lebih dulu, numbering canonical order, berhenti pada blocker legitimate, dan tidak mengarang penerima/vendor/template/data source yang tidak tersedia.

## Generic ARKAS Importer

Generic ARKAS Importer sudah melewati functional correctness gate untuk:

- stable source key;
- tenant boundary;
- Upsert / Incremental / Full Refresh;
- preview reconciliation read-only;
- schema drift blocking;
- source-empty semantics;
- queue tenant activation;
- shared tenant/resource lock;
- created-at preservation;
- semantic import metrics.

Pekerjaan lanjutan importer terutama scale/performance: Bridge-side delta fetch dan evaluasi/paginasi di atas limit fetch besar.

## APP DATA / database tenant

Root data tenant dapat dipindahkan dari source project:

```env
SPJ_DATA_PATH=D:/lrvProject/spj-bosp-data
```

Fallback ketika env kosong: `storage/app`.

Struktur target:

```text
{SPJ_DATA_PATH}/
├── school-databases/
│   ├── _unselected.sqlite
│   └── {NPSN}/spj.sqlite
├── backups/
└── exports/
```

Reset tenant hanya boleh merebuild database sekolah target. Database utama aplikasi tidak boleh ikut dihapus.

## Stack

- PHP 8.2+
- Laravel 12
- Livewire 3
- Alpine.js 3
- Tailwind CSS 4
- Vite 6
- SQLite multi-koneksi
- DomPDF
- PhpSpreadsheet
- PHPWord
- PHPUnit 11

Vite canonical:

```text
resources/css/app.css
resources/js/app.js
```

## Menjalankan project

```powershell
copy .env.example .env
composer install
New-Item database\database.sqlite -ItemType File
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan serve
```

Minimum verification setelah perubahan relevan:

```powershell
npm run theme:qa
npm run build
php artisan view:cache --no-interaction
git diff --check
php artisan test --compact <focused-test>
```

Untuk perubahan PHP:

```powershell
php vendor/bin/pint --dirty --format agent
```

Release gate canonical:

```powershell
php artisan spj:verify
```

Gunakan `--strict-style` bila repository-wide Pint ingin dijadikan blocking gate.
