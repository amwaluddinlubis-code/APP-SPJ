# SPJ BOSP Web

Aplikasi web penyusunan Surat Pertanggungjawaban (SPJ) BOSP berbasis Laravel. Branch pengembangan aktif adalah `gui-standardization`.

Dokumen ini adalah ringkasan project. Kondisi implementasi paling rinci ada di `docs/CURRENT_PROGRESS.md`, keputusan bisnis permanen di `docs/SPJ_DESIGN_DECISIONS.md`, dan aturan UI/CSS di `docs/GUI_STANDARDIZATION.md` serta `docs/CSS_USAGE_GUIDE.md`.

Terakhir diverifikasi terhadap branch aktif: **2026-09-06**.

## Stack utama

- PHP 8.2+
- Laravel 12
- Livewire 3
- Tailwind CSS 4
- Alpine.js 3 + Alpine Persist
- Vite 6
- Filament 4 components
- SQLite multi-koneksi (`main` + database tenant/sekolah)
- DomPDF, PhpSpreadsheet, PHPWord
- PHPUnit 11

## Arsitektur singkat

Aplikasi memakai dua lapisan database:

1. **Database utama**: user, sekolah, konfigurasi tenant, sumber ARKAS, backup, dan metadata global.
2. **Database tenant/sekolah**: tahun anggaran, sumber dana, RKAS/BKU hasil sinkronisasi, transaksi, detail SPJ, paket, nomor dokumen, audit, serta data kerja sekolah.

Data ARKAS/BKU adalah **data sumber/read-only**. Data operator SPJ disimpan terpisah agar sinkronisasi tidak menimpa pekerjaan manual.

## Modul yang tersedia

- setup awal, login, sekolah/tahun/sumber dana aktif;
- user management dan impersonation administrator;
- provision, backup/restore, dan reset database tenant secara penuh;
- konfigurasi dan sinkronisasi ARKAS/BKU;
- RKAS/penganggaran dan transaksi BKU;
- rekonsiliasi perubahan atau hilangnya data sumber;
- Detail Transaksi sebagai workspace operator;
- kategori SPJ: Barang, Konsumsi, Pemeliharaan, SPPD, Honor Pegawai, Jasa Lainnya;
- paket SPJ, checklist, penomoran, lifecycle, template Word/Excel, preview/download;
- audit dan laporan operasional;
- design system internal theme-aware.

## Struktur use case SPJ

`SpjController` adalah adapter HTTP tipis. Orkestrasi utama berada di:

```text
app/UseCases/Spj/
├── SpjWorkspaceUseCase.php
├── SpjPackageUseCase.php
├── SpjNumberingUseCase.php
├── SpjDocumentUseCase.php
└── SpjReportUseCase.php
```

Route/kontrak HTTP lama dipertahankan selama refactor agar aturan aplikasi tidak berubah diam-diam.

## Aturan bisnis penting yang sudah aktif

### Surat Pesanan internal

Untuk Non-SiPLah pada kategori barang/konsumsi, validasi Surat Pesanan dipisah menjadi dua tahap:

- **Kelengkapan isi Surat Pesanan** memblokir bila substansi belum lengkap: penyedia, tanggal pesanan, rincian item, satuan, jumlah, harga, dan nilai transaksi.
- **Nomor Surat Pesanan** tidak memblokir tahap persiapan. Nomor diterbitkan aplikasi pada proses penomoran dan menjadi wajib ketika paket sudah `NUMBERED`/`FINAL`.

### Kronologi tanggal pengadaan

Aturan domain yang dituju pada Paket SPJ:

```text
TGL PESANAN <= TGL TRANSAKSI
TGL PESANAN <= TGL BAP
TGL BAP <= TGL BAST
```

`SpjPackageUseCase` sudah mengikuti relasi tersebut. **Catatan implementasi:** endpoint Detail Transaksi saat ini masih lebih ketat karena `TransactionController` juga membatasi TGL BAP dan TGL BAST agar tidak melewati tanggal transaksi. Perbedaan ini dicatat sebagai technical debt dan tidak boleh disembunyikan di dokumentasi.

### Konsumsi dan Dapodik

Pada Detail Transaksi kategori `KONSUMSI`, aksi Alpine `fillTeachers()` menggunakan daftar peserta yang berasal **hanya dari record Employee dengan `source_type = DAPODIK`**. Record yang hanya berasal dari ARKAS tidak ikut diisikan ke daftar peserta konsumsi.

### SiPLah

SiPLah **bukan kategori SPJ**. Ia adalah kanal/metode pembelian (`payment_method = siplah`) dan dapat dipakai bersama kategori seperti `BARANG` atau `KONSUMSI`. Dukungan field SiPLah, placeholder template, dan tampilan dasar sudah ada; verifikasi end-to-end masih perlu dilanjutkan.

## Paket SPJ — struktur UI terbaru

Halaman `/spj?tab=paket&package_id=...` memiliki sub-tab:

```text
Rincian
├── Panel Rincian Transaksi
└── Panel Dokumen & Template
Isian Manual
Penomoran
```

`Dokumen & Template` dipindahkan ke sub-tab **Rincian** agar tidak menambah scroll pada Isian Manual/Penomoran. Rincian transaksi dan dokumen template ditampilkan sebagai **dua panel terpisah** dengan header mengikuti tema aktif. Daftar dokumen dibuat compact.

Tab **Isian Manual** sudah dinormalisasi agar background, font, form control, panel pajak, hover, serta jarak antar panel mengikuti token tema. Halaman `/spj/penomoran` juga memiliki hover card triwulan yang theme-aware.

## Design system dan CSS

Entry point CSS:

```text
resources/css/app.css
└── resources/css/theme-system.css
```

Layer penting saat ini termasuk:

```text
token-native-components.css
transactions-standardization.css
spj-workspace-standardization.css
dark-form-controls.css
spj-package-theme-fix.css
spj-package-document-placement.css
```

Kode baru harus mengutamakan `x-ui.*`, class `ui-*`, dan token `--ui-*`, `--theme-*`, atau `--spj-*`. Jangan menambah hard-coded `bg-white`, `text-slate-*`, `hover:bg-slate-50`, atau accent statis untuk surface yang harus mengikuti tema.

Panduan rinci: `docs/CSS_USAGE_GUIDE.md`.

## Workflow operator

```text
Login
→ Pilih sekolah
→ Pilih tahun & sumber dana
→ Sinkronisasi ARKAS/BKU
→ Buka transaksi
→ Lengkapi data SPJ
→ Validasi
→ READY
→ Penomoran
→ Pratinjau / Unduh
→ FINAL / Arsip
```

Preview/download tidak boleh menerbitkan nomor secara diam-diam. Dokumen bernomor/final mengikuti locking dan lifecycle yang sah.

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

Setelah perubahan backend, jalankan test yang relevan. Setelah perubahan CSS/JS/Blade, jalankan:

```powershell
npm run build
```

## Dokumentasi utama

- `AGENTS.md` — aturan kerja coder/agent; bukan snapshot fitur.
- `docs/CURRENT_PROGRESS.md` — sumber utama kondisi implementasi terkini.
- `docs/ARCHITECTURE_COMPLETE.md` — arsitektur aktif.
- `docs/SPJ_DESIGN_DECISIONS.md` — aturan bisnis permanen.
- `docs/USER_SCENARIOS.md` — skenario operator.
- `docs/GUI_STANDARDIZATION.md` — aturan GUI/theme.
- `docs/CSS_USAGE_GUIDE.md` — contract CSS aktual.
- `docs/DEVELOPMENT_ROADMAP.md` — prioritas menuju release.
- `docs/DOCUMENT_TEMPLATE_PLACEHOLDERS.md` — placeholder template.
- `docs/SIPLAH_MVP_PLAN.md` — status dan batas dukungan SiPLah.
- `docs/MOBILE_VISUAL_QA_TODO.md` — pekerjaan QA mobile yang belum ditutup.

## Fokus berikutnya

Prioritas tetap menuntaskan workflow end-to-end dan mengurangi perbedaan aturan antar entry point, terutama:

1. samakan validasi tanggal Detail Transaksi dan Paket;
2. stabilkan generator/preview PDF, Word, dan Excel;
3. hardening lifecycle, locking, revisi, pembatalan, dan numbering;
4. rekonsiliasi ARKAS snapshot/diff;
5. authorization per role;
6. end-to-end test seluruh kategori;
7. mobile visual QA;
8. laporan BOS dan release hardening.
