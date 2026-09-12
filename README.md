# SPJ BOSP Web

Aplikasi web penyusunan Surat Pertanggungjawaban (SPJ) BOSP berbasis Laravel. Branch pengembangan aktif: `gui-standardization`.

Terakhir diperbarui: **2026-09-12**.

## Status branch saat ini

Root README adalah entry point project, bukan sumber angka/checkpoint release yang harus dipelihara terpisah.

Gunakan sumber canonical berikut untuk kondisi project terkini:

- `docs/CURRENT_PROGRESS.md` — status release, blocker, RVR, dan evidence aktif;
- `docs/P0_VERIFICATION_KIT.md` §1 — CI code gate/release-safety evidence aktif;
- `docs/DEVELOPMENT_ROADMAP.md` — prioritas pekerjaan berikutnya.

Ringkasan status saat ini tetap:

```text
FUNCTIONAL CORE : PASS
REAL-DATA       : VERIFICATION ACTIVE
OFFICIAL OUTPUT : RVR ACTIVE
BROWSER/RUNTIME : RVR ACTIVE
FINAL RELEASE   : NOT YET
```

Jangan menyalin hash commit, nomor CI, atau jumlah test/assertion ke README ini karena cepat menjadi stale. Commit dokumentasi-only setelah code gate juga tidak dianggap sebagai code gate baru.

## Dokumentasi utama

- `docs/README.md` — indeks dokumentasi, urutan source-of-truth, dan pemisahan dokumen aktif vs historis.
- `docs/CURRENT_PROGRESS.md` — status release, checkpoint, gap aktif, dan evidence terbaru.
- `docs/P0_VERIFICATION_KIT.md` — release-safety gate, command canonical, dan real-tenant audit.
- `docs/DEVELOPMENT_ROADMAP.md` — prioritas pekerjaan berikutnya.
- `docs/SPJ_DESIGN_DECISIONS.md` — aturan bisnis/domain permanen.
- `docs/ARCHITECTURE_COMPLETE.md` — arsitektur aplikasi dan boundary tenant.
- `docs/ARKAS_IMPORTER.md` — pipeline Generic ARKAS Importer.
- `docs/GUI_STANDARDIZATION.md` — kontrak GUI.
- `docs/CSS_USAGE_GUIDE.md` — kontrak CSS/theme.
- `docs/SIPLAH_MVP_PLAN.md` — legacy filename untuk verification guide SiPLah aktif.

Untuk menentukan kondisi project saat ini, utamakan `docs/CURRENT_PROGRESS.md` dan `docs/P0_VERIFICATION_KIT.md`; gunakan `docs/DEVELOPMENT_ROADMAP.md` untuk urutan pekerjaan. Dokumen yang berstatus `HISTORICAL`, `SUPERSEDED`, atau `ARCHIVED` hanya dipertahankan sebagai jejak keputusan dan tidak boleh mengalahkan status aktif.

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

Perbaikan upload yang sudah diregresikan:

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

Keduanya berada pada release-safety regression aktif.

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

Baseline audit real-data yang masih dirujuk pada status aktif mempunyai transaksi dan detail transaksi nyata serta Paket SPJ yang sudah disiapkan:

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

Real-data berikutnya harus tetap mengikuti aturan: audit read-only lebih dulu, numbering canonical order, berhenti pada blocker legitimate, dan tidak mengarang penerima/vendor/template/data source yang tidak tersedia. Status real-data terbaru tetap dibaca dari `docs/CURRENT_PROGRESS.md`.

## Generic ARKAS Importer

Generic ARKAS Importer sudah melewati functional correctness/hardening untuk:

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

Pekerjaan lanjutan importer terutama operator-data verification dan scale/performance: Bridge-side delta fetch serta evaluasi/paginasi di atas limit fetch besar.

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

Release verification canonical:

```powershell
php artisan spj:verify
```

Evidence CI code gate aktif dan perbedaan antara local verification vs GitHub CI berada di `docs/P0_VERIFICATION_KIT.md` §1–2. Gunakan `--strict-style` bila repository-wide Pint ingin dijadikan blocking gate.
