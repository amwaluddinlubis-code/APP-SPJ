# SPJ BOSP Web

Aplikasi web penyusunan Surat Pertanggungjawaban (SPJ) BOSP berbasis Laravel. Branch pengembangan aktif: `gui-standardization`.

Terakhir diverifikasi terhadap kode: **2026-09-12**.

## Status branch saat ini

Root README adalah entry point project, bukan sumber angka/checkpoint release yang harus dipelihara terpisah. Jangan menyalin hash commit, nomor CI, atau jumlah test/assertion ke berkas ini karena cepat menjadi stale.

Gunakan sumber canonical berikut untuk kondisi project terkini:

- `docs/CURRENT_PROGRESS.md` — status release, blocker, RVR, dan evidence aktif;
- `docs/P0_VERIFICATION_KIT.md` §1 — CI code gate/release-safety evidence aktif;
- `docs/DEVELOPMENT_ROADMAP.md` — prioritas pekerjaan berikutnya.

Ringkasan status saat ini:

```text
FUNCTIONAL CORE : PASS
REAL-DATA       : VERIFICATION ACTIVE
OFFICIAL OUTPUT : RVR ACTIVE
BROWSER/RUNTIME : RVR ACTIVE
FINAL RELEASE   : NOT YET
```

## Stack riil (terverifikasi dari `composer.json` / `package.json`)

- PHP `^8.2` (runtime dev 8.4.0)
- Laravel 12 (`^12.0`) — struktur ramping (middleware di `bootstrap/app.php`, tanpa `app/Http/Kernel.php`)
- Livewire 3.7 + Alpine.js 3 (+ `@alpinejs/collapse`, `@alpinejs/persist`) + Chart.js 4
- Tailwind CSS 4 + Vite 6 (`resources/css/app.css`, `resources/js/app.js`)
- Filament 4 (`actions`, `schemas`, `tables` — dipakai pada komponen tabel Livewire, bukan full admin panel)
- SQLite multi-koneksi (1 central + 1 per sekolah), session driver `database`, queue `database`
- DomPDF (`barryvdh/laravel-dompdf`), PhpSpreadsheet, PHPWord
- Auth session + login kustom (`/masuk`, throttle 5:1) — **tanpa** Sanctum/Passport/JWT; **tanpa** `routes/api.php`
- Test: PHPUnit 11 (`tests/Unit`, `tests/Feature`, suite `SPJ Critical` di `phpunit.xml`)

## Prasyarat lokal

- PHP 8.2+ dengan ekstensi `sqlite3`, `mbstring`, `xml`, `gd`/`imagick` (untuk render dokumen), `zip`
- Composer 2, Node.js 18+ (Vite 6 + Tailwind 4)
- Git (branch kerja: `gui-standardization`)

## Instalasi lokal

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

Buka `http://localhost:8000/setup` pada instalasi kosong — akun administrator dibuat oleh pemilik sekolah di halaman tersebut (`DatabaseSeeder` sengaja kosong).

Mode dev (server + queue + log + vite paralel):

```powershell
composer run dev
```

## Environment penting (`.env`)

```env
APP_URL=http://localhost
DB_CONNECTION=sqlite
SESSION_DRIVER=database
QUEUE_CONNECTION=database
ARKAS_SYNC_ASYNC=false        # true = sync ARKAS via queue operations
SPJ_DATA_PATH=D:/lrvProject/spj-bosp-data   # kosong = storage/app
SCHOOL_DB_BUSY_TIMEOUT=5000
SCHOOL_DB_JOURNAL_MODE=WAL
```

## Database & migration

Central (default connection, `database/migrations/`, 4 berkas): `schools`, `users`, `school_databases`, `arkas_sources`, `school_backups`, `background_operations`, plus tabel framework (`sessions`, `cache`, `jobs`).

Tenant sekolah (koneksi `school`, `database/migrations/school/`, 47 berkas + view): RKAS/BKU, transaksi, paket SPJ, numbering, template, importer state. **Database tenant tidak dibuat via `migrate` manual** — diprovisi per NPSN oleh `SchoolDatabaseManager`:

```text
{SPJ_DATA_PATH}/school-databases/{NPSN}/spj.sqlite
```

Reset tenant hanya boleh merebuild database sekolah target. Database utama tidak boleh ikut dihapus.

Seeder: hanya `DatabaseSeeder` kosong (admin via `/setup`). Test memakai SQLite in-memory + session konteks aktif, bukan seeder.

## Perintah Artisan khusus

```powershell
php artisan spj:verify                        # release verification canonical
php artisan spj:audit-quarter <NPSN> --quarter=<1-4>   # audit read-only, tanpa mutasi
php artisan spj:audit-diff
php artisan spj:preflight-numbering           # preflight read-only sebelum penomoran
php artisan spj:repair-quarter-numbering
php artisan spj:test-numbering-copy           # numbering/cancel/rollback pada isolated copy
php artisan spj:test-cancel-copy
php artisan spj:test-tail-rollback-copy
php artisan spj:test-quarter-rollback-copy
php artisan arkas:reconcile
php artisan employees:fuse-duplicates
```

Audit real-data tidak boleh memutasi baseline; mutasi pengujian hanya pada isolated copy (`docs/SYNCHRONIZATION.md` §22).

## Verifikasi perubahan

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

Detail local-vs-CI ada di `docs/P0_VERIFICATION_KIT.md` §1–2.

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
- `NUMBERED`/`FINAL` terkunci dari edit normal, kecuali koreksi `item_description` dan `payment_description` pada `NUMBERED` (lihat `docs/SPJ_DESIGN_DECISIONS.md` §4.2/§4.4).

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

## Dokumentasi utama

- `ARCHITECTURE.md` — struktur folder, alur data, ERD (root)
- `API.md` — daftar route aktif + middleware + controller (root, digenerate dari `route:list`)
- `docs/README.md` — indeks dokumentasi dan source-of-truth
- `docs/SPJ_DESIGN_DECISIONS.md` — aturan bisnis/domain permanen
- `docs/ARCHITECTURE_COMPLETE.md` — arsitektur dan boundary tenant (detail)
- `docs/NUMBERING_CORRECTION_AND_ROLLBACK.md` — kontrak numbering/registry/cancel/rollback
- `docs/ARKAS_IMPORTER.md` — pipeline Generic ARKAS Importer
- `docs/SYNCHRONIZATION.md` — sinkronisasi, reconciliation, identity
- `docs/GUI_STANDARDIZATION.md` + `docs/CSS_USAGE_GUIDE.md` — kontrak GUI/theme
- `AGENTS.md` — aturan kerja agen AI di repositori ini
