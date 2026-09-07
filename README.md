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

Data tenant ditempatkan di luar source project melalui:

```env
SPJ_DATA_PATH=D:/lrvProject/spj-bosp-data
```

Struktur target:

```text
D:/lrvProject/spj-bosp-data/
├── school-databases/
│   ├── _unselected.sqlite
│   └── {NPSN}/spj.sqlite
├── backups/
└── exports/
```

`SchoolDatabaseManager` memakai root tersebut untuk database sekolah/dummy. Backup sekolah juga memakai `{SPJ_DATA_PATH}/backups/{NPSN}`. Operasi runtime tetap harus diverifikasi pada dataset nyata sebelum dianggap release-ready.

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

## Status aktif

Jangan gunakan README sebagai checklist PASS/FAIL. Sumber tunggal gap aktif adalah:

```text
docs/CURRENT_PROGRESS.md
```

Saat ini fokus utama yang masih terbuka meliputi konsistensi validasi tanggal, konsistensi workflow Dashboard/Persiapan, PEMELIHARAAN bahan+upah sampai RAB, JASA_LAINNYA multi-penerima sampai dokumen, SiPLah end-to-end, generator/lifecycle/authorization hardening, tenant runtime verification, mobile QA, dan Pusat Laporan.

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

Pastikan `.env` lokal memuat `SPJ_DATA_PATH` yang sesuai lingkungan.

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
