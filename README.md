# SPJ BOSP Web

Aplikasi web penyusunan Surat Pertanggungjawaban (SPJ) BOSP berbasis Laravel. Branch pengembangan aktif: `gui-standardization`.

Terakhir diperbarui terhadap branch aktif: **2026-09-08**.

Sumber status utama:

- `docs/CURRENT_PROGRESS.md` — gap aktif yang belum release-ready;
- `docs/SPJ_DESIGN_DECISIONS.md` — aturan bisnis permanen;
- `docs/DEVELOPMENT_ROADMAP.md` — pekerjaan berikutnya;
- `docs/URGENT_TRANSACTION_SPJ_MIGRATION.md` — arsip migrasi ownership Detail Transaksi ↔ Paket SPJ yang sudah selesai.

## Status migrasi ownership SPJ

**PASS — ownership boundary selesai.**

Kontrak final:

```text
Detail Transaksi = source ARKAS/BKU + item_description
Paket SPJ        = seluruh isian dokumen pertanggungjawaban
```

Aturan utama:

- `item_description` hanya diedit di Detail Transaksi dan wajib tersimpan sebelum Paket SPJ dapat dibuat/dibuka;
- `description`, quantity, unit, unit price, amount adalah readonly source;
- PPN, PPh 21/22/23/4(2), SSPD, `tax_total`, dan `net_amount` tetap milik transaksi/source;
- Paket SPJ hanya membaca pajak sebagai referensi readonly;
- `spj_category`, payment fields, penerima utama, vendor/invoice, data kategori, numbering, preview/download/finalisasi hanya dikelola di Paket SPJ;
- category switch Paket SPJ berjalan tanpa full page reload;
- route/use-case/write-path legacy yang menulis SPJ dari halaman transaksi sudah dipensiunkan.

Focused regression suite SPJ yang dijalankan user pada 2026-09-08 dilaporkan **ALL PASS**. Perubahan visual setelah checkpoint tersebut tetap perlu browser QA setelah pull/build.

## Stack

- PHP 8.2+
- Laravel 12
- Livewire 3
- Alpine.js 3
- Tailwind CSS 4
- Vite 6
- SQLite multi-koneksi
- DomPDF / PhpSpreadsheet / PHPWord
- PHPUnit 11

Vite canonical hanya memakai entry utama:

```text
resources/css/app.css
resources/js/app.js
```

Feature JS aktif diimpor melalui bundle aplikasi, bukan melalui `@vite` standalone di view.

## Arsitektur inti

Aplikasi memakai database utama dan database tenant/sekolah. ARKAS/BKU adalah source readonly; data operator SPJ adalah overlay yang dipertahankan saat sinkronisasi ulang.

Sinkronisasi ARKAS menggunakan pipeline kanonik `Bridge -> staging -> mapping -> domain adapter`. Operator dapat mengatur tabel tambahan, preview rekonsiliasi, histori import, dan mode incremental melalui `/pengaturan/arkas/importer`. Panduan lengkap tersedia di [docs/ARKAS_IMPORTER.md](docs/ARKAS_IMPORTER.md).

Use case SPJ utama:

```text
app/UseCases/Spj/
├── SpjWorkspaceUseCase.php
├── CreateSpjDraftUseCase.php
├── UpdateSpjPackageDetailsUseCase.php
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

SiPLah bukan kategori SPJ; ia adalah mode pembelian/pembayaran (`payment_method = siplah`).

## Workflow operator

```text
Login
→ Pilih sekolah/tahun/sumber dana
→ Sinkronisasi ARKAS/BKU
→ Daftar Transaksi
→ Detail Transaksi
   → periksa Informasi Referensi ARKAS/BKU
   → koreksi & simpan item_description
→ Siapkan / Lihat Paket SPJ
→ Isian Manual Paket SPJ
→ READY
→ Penomoran
→ Preview / Unduh
→ FINAL / Arsip
```

Detail Transaksi tidak lagi menjadi workspace pengisian kategori/payment/vendor SPJ.

## Workspace Paket SPJ saat ini

Navigasi Paket:

```text
Semua Paket | Paket Sebelumnya | Paket Setelahnya
```

Prev/next dibatasi pada konteks **sekolah + tahun anggaran + sumber dana aktif** dan mengikuti urutan transaksi (`transaction_date`, lalu `id`).

Ringkasan Paket:

```text
Periode | Penerima | Bruto | Pajak | Nilai Dibayarkan
```

Format nilai uang UI menggunakan accounting Indonesia tanpa simbol `Rp` dan tanpa desimal, contoh `1.000`, `1.250.000`.

Sub-tab Paket:

```text
1. Rincian
2. Isian Manual
3. Rincian Pajak
4. Penomoran
```

Pada Isian Manual:

- Kategori SPJ berada di baris atas;
- `BARANG` menampilkan radio mutually-exclusive `SiPLah / Non SiPLah`;
- `PEMELIHARAAN` menampilkan selector transaksi pasangan di baris kategori;
- nomor otomatis tidak menjadi input operator dan ditampilkan sebagai strip informasi;
- Data Umum Dokumen: textarea uraian 5 baris di kiri, seluruh field umum lain terkumpul di kanan;
- tabel kategori non-BARANG dibuat compact, memiliki satu pagination lokal, radio `Penerima Utama`, integer untuk hari/porsi/kali, dan accounting untuk tarif/nilai.

Pajak detail tidak berada di Isian Manual. Ia tampil readonly di tab **Rincian Pajak**.

## Detail Transaksi saat ini

Urutan utama disederhanakan menjadi:

```text
Header transaksi
→ Informasi Referensi ARKAS/BKU + Total Pajak
→ Rincian Barang/Jasa
→ Status Paket SPJ
```

Rincian PPN/PPh/SSPD tidak mendominasi halaman transaksi; detail pajak tersedia di Paket SPJ pada tab Rincian Pajak.

Daftar transaksi memakai satu tombol **Aksi** per row yang membuka pilihan navigasi Detail/Paket SPJ melalui modal. Write-path editor SPJ lama di Livewire tabel transaksi sudah dihapus.

## APP DATA

Root data tenant dapat dipindahkan dari source project:

```env
SPJ_DATA_PATH=D:/lrvProject/spj-bosp-data
```

Fallback bila env kosong: `storage/app`.

Struktur target:

```text
{SPJ_DATA_PATH}/
├── school-databases/
│   ├── _unselected.sqlite
│   └── {NPSN}/spj.sqlite
├── backups/
└── exports/
```

Reset tenant hanya boleh merebuild database sekolah aktif, termasuk WAL/SHM dan sequence; database utama tidak ikut dihapus.

## Aturan penting

Kronologi pengadaan canonical:

```text
order_date <= transaction_date
order_date <= bap_date
bap_date <= bast_date
```

Jangan menambahkan rule `bap_date <= transaction_date` atau `bast_date <= transaction_date` tanpa keputusan domain baru.

Untuk `PEMELIHARAAN`, transaksi bahan/barang dan transaksi upah dapat ditautkan melalui endpoint khusus. Relationship state tetap milik transaksi/context, walaupun selector ditampilkan di workspace Paket SPJ.

Preview/download tidak boleh menerbitkan nomor secara diam-diam. `NUMBERED`/`FINAL` terkunci dari edit normal.

## Status aktif

Ownership migration sudah selesai. Gap release yang masih terbuka dipusatkan di `docs/CURRENT_PROGRESS.md`, terutama:

- JASA_LAINNYA multi-penerima sampai output dokumen;
- generator/lifecycle/authorization/reconciliation release hardening;
- end-to-end semua kategori sampai FINAL + preview/download;
- APP DATA runtime pada database nyata;
- mobile visual QA;
- Pusat Laporan dan laporan BOS resmi.

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

## Dokumentasi utama

- `docs/CURRENT_PROGRESS.md` — gap aktif.
- `docs/DEVELOPMENT_ROADMAP.md` — pekerjaan berikutnya.
- `docs/SPJ_DESIGN_DECISIONS.md` — aturan bisnis permanen.
- `docs/ARCHITECTURE_COMPLETE.md` — arsitektur aktif.
- `docs/GUI_STANDARDIZATION.md` — contract GUI.
- `docs/CSS_USAGE_GUIDE.md` — contract CSS/theme.
- `docs/URGENT_TRANSACTION_SPJ_MIGRATION.md` — arsip migrasi ownership yang sudah PASS.
- `docs/SIPLAH_MVP_PLAN.md` — batas MVP SiPLah.
- `docs/MOBILE_VISUAL_QA_TODO.md` — QA mobile yang belum ditutup.
