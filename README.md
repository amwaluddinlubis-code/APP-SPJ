# SPJ BOSP Web

Aplikasi web penyusunan Surat Pertanggungjawaban (SPJ) BOSP berbasis Laravel. Branch pengembangan aktif: `gui-standardization`.

Terakhir diverifikasi terhadap branch aktif: **2026-09-07**.

Dokumentasi status tidak lagi menyimpan daftar PASS panjang. Gap aktif dikumpulkan di `docs/CURRENT_PROGRESS.md`; aturan bisnis permanen ada di `docs/SPJ_DESIGN_DECISIONS.md`; roadmap hanya berisi pekerjaan yang belum selesai.

## Stack

- PHP 8.2+
- Laravel 12
- Livewire 3
- Tailwind CSS 4
- Alpine.js 3
- Vite 6
- SQLite multi-koneksi
- DomPDF / PhpSpreadsheet / PHPWord
- PHPUnit 11

## Arsitektur inti

Aplikasi menggunakan database utama dan database tenant/sekolah. ARKAS/BKU adalah source readonly; data operator SPJ disimpan sebagai overlay terpisah.

Orkestrasi SPJ utama:

```text
app/UseCases/Spj/
├── SpjWorkspaceUseCase.php
├── SpjPackageUseCase.php
├── SpjNumberingUseCase.php
├── SpjDocumentUseCase.php
└── SpjReportUseCase.php
```

Kategori canonical:

```text
BARANG
KONSUMSI
PEMELIHARAAN
JASA_LAINNYA
SPPD
HONOR_PEGAWAI
```

SiPLah bukan kategori SPJ; gunakan `payment_method = siplah`.

## APP DATA

Data tenant dapat ditempatkan di luar source project melalui:

```env
SPJ_DATA_PATH=D:/lrvProject/spj-bosp-data
```

Jika `SPJ_DATA_PATH` tidak diisi, aplikasi memakai `storage/app` agar setup development tetap portable.

Struktur target root eksternal:

```text
D:/lrvProject/spj-bosp-data/
├── school-databases/
│   ├── _unselected.sqlite
│   └── {NPSN}/spj.sqlite
├── backups/
└── exports/
```

`SchoolDatabaseManager` memakai root tersebut untuk database sekolah/dummy. Backup sekolah memakai `{SPJ_DATA_PATH}/backups/{NPSN}`. Operasi runtime tetap harus diverifikasi pada dataset nyata sebelum dianggap release-ready.

## Workflow operator

```text
Login
→ Pilih sekolah/tahun/sumber dana
→ Sinkronisasi ARKAS/BKU
→ Lengkapi Detail Transaksi
→ DRAFT / READY
→ Penomoran
→ Preview / Unduh
→ FINAL / Arsip
```

Preview/download tidak boleh menerbitkan nomor secara diam-diam. NUMBERED/FINAL mengikuti locking dan lifecycle backend.

Workflow status operator memakai kontrak bersama `SpjWorkflowFilterService`; keberadaan `transaction_items` tidak digunakan sebagai penanda apakah operator sudah mulai mengerjakan SPJ.

Kronologi pengadaan canonical:

```text
Tanggal Pesanan <= Tanggal Transaksi
Tanggal Pesanan <= Tanggal BAP
Tanggal BAP <= Tanggal BAST
```

Untuk `PEMELIHARAAN`, Detail Transaksi dapat menautkan transaksi bahan/barang dan transaksi upah. Preview/download memakai document context yang mengambil rincian material dari sisi bahan dan daftar pekerja dari sisi upah tanpa menulis ulang source BKU.

## Status aktif

Jangan gunakan README sebagai checklist PASS/FAIL. Sumber tunggal gap aktif adalah:

```text
docs/CURRENT_PROGRESS.md
```

Source terbaru masih membutuhkan runtime verification untuk kronologi pengadaan, workflow canonical, linkage dokumen pemeliharaan, APP DATA eksternal, dan SiPLah. Gap implementasi yang benar-benar masih terbuka terutama JASA_LAINNYA multi-penerima sampai output dokumen, release-hardening generator/lifecycle/authorization/reconciliation, end-to-end semua kategori, mobile QA, dan Pusat Laporan.

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

Jika menggunakan data root eksternal, isi `.env` dengan `SPJ_DATA_PATH` sesuai lingkungan.

Minimum verification setelah perubahan relevan:

```powershell
npm run theme:qa
npm run build
php artisan view:cache --no-interaction
git diff --check
php artisan test --compact <focused-test>
```

Untuk perubahan PHP, jalankan juga:

```powershell
php vendor/bin/pint --dirty --format agent
```

## Dokumentasi utama

- `docs/CURRENT_PROGRESS.md` — register FAIL/RVR/PLANNED aktif.
- `docs/DEVELOPMENT_ROADMAP.md` — urutan pekerjaan yang belum selesai.
- `docs/SPJ_DESIGN_DECISIONS.md` — aturan bisnis permanen.
- `docs/ARCHITECTURE_COMPLETE.md` — referensi arsitektur.
- `docs/USER_SCENARIOS.md` — skenario operator.
- `docs/GUI_STANDARDIZATION.md` — contract GUI.
- `docs/CSS_USAGE_GUIDE.md` — contract CSS/theme.
- `docs/SIPLAH_MVP_PLAN.md` — batas MVP SiPLah.
- `docs/MOBILE_VISUAL_QA_TODO.md` — QA mobile yang belum ditutup.
