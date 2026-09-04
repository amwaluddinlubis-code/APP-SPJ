# SPJ BOSP Web

Aplikasi web penyusunan Surat Pertanggungjawaban (SPJ) BOSP berbasis Laravel. Project ini merupakan arah pengembangan utama dari aplikasi SPJ berbasis Excel/VBA menuju aplikasi web multi-sekolah dengan sinkronisasi data ARKAS, workflow SPJ, penomoran dokumen, template dokumen, audit, dan design system internal.

Branch pengembangan aktif: `gui-standardization`.

## Stack utama

- PHP 8.2+
- Laravel 12
- Livewire 3
- Tailwind CSS 4
- Alpine.js 3 + Alpine Persist
- Vite 6
- Filament 4 components
- SQLite multi-koneksi (`main` + database tenant/sekolah)
- DomPDF untuk PDF
- PhpSpreadsheet untuk Excel
- PHPWord untuk Word
- PHPUnit 11 untuk automated test

## Arsitektur singkat

Aplikasi menggunakan dua lapisan database:

1. **Database utama** untuk user, sekolah, konfigurasi, sumber ARKAS, backup, dan metadata global.
2. **Database tenant/sekolah** untuk tahun anggaran, sumber dana, RKAS/BKU hasil sinkronisasi, transaksi, detail SPJ, paket dokumen, nomor dokumen, audit operasional, dan data kerja sekolah.

Data hasil ARKAS/BKU diperlakukan sebagai **data sumber resmi/read-only**, sedangkan data tambahan SPJ operator disimpan terpisah agar sinkronisasi tidak menimpa pekerjaan manual.

## Modul yang sudah tersedia

- setup awal dan login;
- pemilihan sekolah, tahun anggaran, dan sumber dana aktif;
- manajemen user dan impersonation administrator;
- konfigurasi sekolah dan database tenant;
- backup/restore database sekolah;
- **reset database sekolah secara penuh** dengan rebuild file SQLite tenant dan reset sequence/auto-increment;
- konfigurasi sumber ARKAS dan sinkronisasi ARKAS/BKU;
- penganggaran/RKAS;
- daftar dan detail transaksi BKU;
- rekonsiliasi perubahan/hilangnya data sumber;
- kategori SPJ: Barang, Konsumsi, Pemeliharaan, SPPD, Honor Pegawai, dan Jasa Lainnya;
- penerima kuitansi manual yang terpisah dari penerima BKU/ARKAS;
- paket SPJ dan checklist kelengkapan;
- penomoran dokumen dan format nomor;
- template dokumen Word/Excel dan katalog placeholder;
- generator/pratinjau/unduh dokumen yang sedang difinalisasi;
- audit operasional dan laporan audit;
- dashboard operasional;
- standardisasi GUI menyeluruh dan tema dinamis.

## Struktur use case SPJ

`SpjController` sudah direduksi menjadi adapter HTTP tipis. Tanggung jawab utamanya dipisahkan ke use case:

```text
app/UseCases/Spj/
├── SpjWorkspaceUseCase.php
├── SpjPackageUseCase.php
├── SpjNumberingUseCase.php
├── SpjDocumentUseCase.php
└── SpjReportUseCase.php
```

Route dan kontrak HTTP lama tetap dipertahankan agar refactor tidak mengubah aturan aplikasi.

## Workflow target operator

```text
Login
→ Pilih sekolah
→ Pilih tahun & sumber dana
→ Sinkronisasi ARKAS/BKU
→ Buka transaksi
→ Lengkapi data SPJ
→ Validasi kelengkapan
→ READY / Siap diproses
→ Penomoran
→ Pratinjau dokumen
→ Unduh / Cetak
→ FINAL / Arsip
```

Preview tidak boleh membuat nomor atau mengubah status secara diam-diam. Dokumen yang sudah bernomor/final harus mengikuti mekanisme locking dan revisi.

## Status GUI

Branch `gui-standardization` sekarang memakai design system global dengan pola:

```text
Header global
Breadcrumb sticky
Header halaman + summary
Toolbar / filter
Form / section
Tabel / workspace
Sticky actions / footer utilitas
```

Fondasi yang sudah diterapkan:

- sidebar persisten;
- breadcrumb global sticky;
- sticky control `Ke atas` untuk halaman panjang;
- form/input/button primitives;
- table standardization global;
- pagination dan per-page control yang seragam;
- page shell, toolbar, filter, tabs, stat card;
- alert, empty state, modal, badge, detail list;
- action menu, loading/skeleton, sticky actions, danger zone;
- status badge dengan bahasa operator;
- pemisahan visual data ARKAS/BKU (readonly) dan data SPJ operator (editable);
- compatibility layer untuk markup lama;
- dark mode dan seluruh accent non-semantik mengikuti tema aktif.

Token tema utama:

```text
--theme-accent
--theme-accent-strong
--theme-accent-soft
--theme-sidebar
--theme-sidebar-deep
```

Primitive UI baru tidak boleh hard-code accent color jika bukan warna semantik. Warna success/warning/danger tetap mengikuti makna tindakan.

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

Untuk development terpadu dapat menggunakan script Composer `dev` setelah dependency PHP dan Node tersedia.

Pada fase awal gunakan SQLite. Konfigurasikan `DB_CONNECTION` dan path database sesuai `.env.example`. Konfigurasi `SPJ_ARKAS_BRIDGE_COMMAND`/sumber ARKAS hanya dengan executable/perintah ARKASBridge yang sudah diuji pada komputer target.

## Testing

Database test dipisahkan dari database utama. Jangan mengubah proteksi ini.

Jalankan test yang relevan setelah perubahan backend:

```powershell
php artisan test
```

Setelah perubahan frontend:

```powershell
npm run build
```

Test suite mencakup antara lain sinkronisasi ARKAS aman, database tenant, pemilihan sekolah/tahun, transaksi Livewire, user management, impersonation, security hardening, penomoran, critical document workflow, serta test reset database sekolah yang memverifikasi auto-increment kembali ke awal setelah rebuild tenant.

## Dokumentasi utama

- `AGENTS.md` — aturan kerja agent/coder.
- `docs/CURRENT_PROGRESS.md` — snapshot implementasi terkini.
- `docs/ARCHITECTURE_COMPLETE.md` — arsitektur dan pembagian modul/use case.
- `docs/SPJ_DESIGN_DECISIONS.md` — keputusan bisnis yang harus dipertahankan.
- `docs/USER_SCENARIOS.md` — skenario operator dan peran pengguna.
- `docs/GUI_STANDARDIZATION.md` — design system dan aturan UX/tema.
- `docs/DEVELOPMENT_ROADMAP.md` — prioritas pengembangan menuju release.
- `docs/DOCUMENT_TEMPLATE_PLACEHOLDERS.md` — placeholder template dokumen.

## Fokus berikutnya

Prioritas utama tetap menuntaskan workflow SPJ end-to-end:

1. stabilisasi generator dan preview PDF/Excel/Word;
2. lifecycle status SPJ dan locking/revisi;
3. penomoran triwulan yang aman dan atomik;
4. rekonsiliasi ARKAS dengan snapshot/diff;
5. hardening authorization per role;
6. end-to-end test seluruh kategori SPJ;
7. penyempurnaan laporan BOS dan readiness release.

Generalisasi GUI utama sudah menjadi fondasi; pekerjaan berikutnya adalah migrasi view tersisa dan konsistensi penggunaan primitive yang sudah ada, bukan membuat design system baru lagi.
