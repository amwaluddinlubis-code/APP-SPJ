# SPJ BOSP Web — Catatan Progres Terakhir

Terakhir diperbarui: **2026-09-06**

Dokumen ini adalah snapshot utama kondisi branch `gui-standardization`. Jika ada dokumen historis atau handoff lama yang bertentangan dengan dokumen ini, gunakan dokumen ini bersama `SPJ_DESIGN_DECISIONS.md` dan kode aktif.

---

## 1. Kondisi project saat ini

Project aktif:

- Laravel 12 / PHP 8.2+;
- Livewire + Alpine + Tailwind;
- database multi-koneksi: database utama + tenant/sekolah;
- ARKAS/BKU adalah sumber data operasional;
- data operator SPJ dipisahkan dari source;
- `SpjController` sudah dipisah ke use case;
- design system global theme-aware sudah menjadi fondasi utama.

Project sudah melewati tahap prototype, tetapi **belum release final**. Fokus tetap pada workflow SPJ end-to-end, consistency/hardening, dan pengujian.

---

## 2. Arsitektur data yang berlaku

### Database utama

Menyimpan user, sekolah, konfigurasi tenant, sumber ARKAS, backup, dan metadata global.

### Database tenant/sekolah

Menyimpan tahun anggaran, sumber dana, RKAS/BKU hasil sinkronisasi, transaksi, item, detail SPJ, paket, nomor dokumen, audit, serta data pendukung sekolah.

Prinsip wajib:

- source ARKAS/BKU tidak ditimpa data manual;
- safe sync tidak boleh menghapus pekerjaan operator;
- query operasional harus berada pada sekolah, tahun anggaran, dan sumber dana aktif;
- reset database sekolah hanya menyasar tenant dan membangun ulang SQLite agar sequence/auto-increment kembali dari awal.

---

## 3. Use case SPJ

`SpjController` berfungsi sebagai adapter HTTP tipis. Orkestrasi utama:

```text
app/UseCases/Spj/
├── SpjWorkspaceUseCase.php
├── SpjPackageUseCase.php
├── SpjNumberingUseCase.php
├── SpjDocumentUseCase.php
└── SpjReportUseCase.php
```

Boundary tersebut harus dipertahankan. Jangan memindahkan logika domain kembali ke controller tanpa alasan kuat.

---

## 4. Detail Transaksi — kondisi terbaru

Detail Transaksi adalah workspace operator untuk:

```text
Data ARKAS/BKU readonly
→ Data Umum SPJ
→ Detail kategori
→ Kelengkapan
→ Buat/Perbarui Paket
```

### 4.1 Kategori Konsumsi — peserta dari Dapodik

Pada kategori `KONSUMSI`, tombol yang menjalankan Alpine `fillTeachers()` sekarang mengisi peserta dari `$dapodikTeachers` yang sudah difilter:

```text
Employee.source_type = DAPODIK
```

Record yang hanya berasal dari ARKAS tidak ikut masuk ke daftar peserta konsumsi.

Catatan UI: label tombol di view masih berbunyi “Ambil semua pegawai terdaftar”; secara data sumber, hasilnya sekarang adalah Dapodik-only. Rename label merupakan cleanup UI yang masih dapat dilakukan.

### 4.2 Dark form controls

`resources/css/dark-form-controls.css` dimuat melalui `theme-system.css` sebagai safety layer untuk input/select/textarea pada dark appearance. Background, border, font, placeholder, readonly, disabled, dan autofill dinormalisasi agar tidak kembali putih.

### 4.3 Rincian transaksi

`transactions-standardization.css` menormalisasi panel/form Detail Transaksi terhadap tema dan membuat daftar uraian barang lebih compact.

---

## 5. Validasi pengadaan — kondisi aktual

### 5.1 Surat Pesanan internal

Validasi Surat Pesanan Non-SiPLah untuk kategori `BARANG`, `BELANJA_MODAL`, dan `KONSUMSI` sudah dipisahkan:

1. **Kelengkapan isi Surat Pesanan** — blocking pada tahap persiapan jika substansi belum lengkap.
2. **Nomor Surat Pesanan** — tidak blocking pada `DRAFT`/`READY`; menjadi wajib pada `NUMBERED`/`FINAL`.

Substansi yang diperiksa mencakup penyedia, tanggal pesanan, uraian item, quantity, harga, dan nilai transaksi.

Field `unit`/satuan berasal dari rincian source ARKAS dan tidak dapat dilengkapi melalui form uraian SPJ operator. Karena itu satuan **bukan blocker** untuk `Kelengkapan isi Surat Pesanan`; validasi item mengikuti rule canonical barang: uraian, jumlah lebih dari nol, dan harga valid. Konsistensi nilai item tetap diperiksa oleh validasi paket yang relevan.

Nomor Surat Pesanan diterbitkan aplikasi melalui domain numbering `PESANAN`; requirement nomor tidak boleh menciptakan circular blocker sebelum numbering.

### 5.2 Kronologi tanggal pengadaan

Aturan domain Paket SPJ yang sudah diterapkan pada `SpjPackageUseCase`:

```text
order_date <= transaction_date
bap_date   >= order_date
bast_date  >= bap_date
```

Frontend juga memiliki `resources/js/spj-purchase-date-validation.js` untuk menjaga constraint interaktif pada Detail Transaksi dan Paket → Isian Manual.

**Known mismatch yang harus diketahui:** `TransactionController::updateManualDescription()` saat ini masih memiliki validasi tambahan `bap_date <= transaction_date` dan `bast_date <= transaction_date`. Jadi Detail Transaksi masih lebih ketat daripada `SpjPackageUseCase`. Dokumentasi sebelumnya yang menyatakan kedua entry point sudah identik tidak lagi dianggap benar sampai rule backend ini diseragamkan.

---

## 6. Paket SPJ — layout dan theme terbaru

URL kerja:

```text
/spj?tab=paket&package_id=...
```

Sub-tab internal saat ini:

```text
Rincian
Isian Manual
Penomoran
```

### 6.1 Rincian

Sub-tab `Rincian` sekarang berisi dua panel berbeda:

```text
Panel Rincian Transaksi
Panel Dokumen & Template
```

`Dokumen & Template` dipindahkan ke tab Rincian melalui `resources/js/spj-package-document-placement.js` agar tidak ikut memperpanjang halaman ketika user membuka Isian Manual/Penomoran.

Kedua panel dipisahkan secara visual, memiliki border/radius/shadow sendiri, dan header theme-aware. CSS placement berada di:

```text
resources/css/spj-package-document-placement.css
```

### 6.2 Dokumen & Template

Daftar template dibuat compact:

- padding baris dikurangi;
- metadata tipe/format diringkas;
- tombol preview/download dipadatkan;
- template aktif dapat dipratinjau serta diunduh per jenis sebagai Excel atau PDF; Unduh Paket PDF menggabungkan template aktif yang sesuai kategori;
- zebra/hover mengikuti theme;
- header grup dokumen tidak lagi mendominasi vertical space.

Workflow/validasi/download tidak diubah oleh perubahan layout ini.

### 6.3 Isian Manual

Tab Isian Manual dinormalisasi agar mengikuti tema aktif:

- surface utama dan surface sekunder;
- font strong/normal/muted;
- input/select/textarea;
- readonly/disabled;
- panel semantic;
- panel pajak;
- focus state;
- margin/gap antar panel.

Spacing top-level panel saat ini dinormalisasi sekitar `.875rem` desktop dan `.75rem` mobile pada compatibility layer Paket.

### 6.4 Penomoran

Quarter selector `/spj/penomoran` memiliki normal/hover/active state yang mengikuti token theme, bukan `hover:bg-slate-50` statis.

---

## 7. CSS/theme contract aktif

Entry point:

```text
resources/css/app.css
└── resources/css/theme-system.css
```

Bagian akhir cascade saat ini:

```text
dark-form-controls.css
spj-package-theme-fix.css
spj-package-document-placement.css
```

`spj-package-theme-fix.css` menjadi compatibility/safety layer untuk markup Paket yang masih memiliki class warna Tailwind lama. `spj-package-document-placement.css` khusus struktur dua panel di sub-tab Rincian.

Aturan kode baru:

- gunakan `x-ui.*` / `ui-*` bila tersedia;
- warna utama memakai token `--ui-*`, `--theme-*`, atau `--spj-*`;
- Tailwind tetap dipakai untuk layout/spacing/responsive;
- jangan menambah hard-coded surface/accent yang seharusnya theme-aware.

Acuan rinci: `docs/CSS_USAGE_GUIDE.md`.

---

## 8. SiPLah — status saat ini

SiPLah bukan kategori SPJ. Kategori tetap:

```text
BARANG
KONSUMSI
PEMELIHARAAN
SPPD
HONOR_PEGAWAI
JASA_LAINNYA
```

Dukungan yang sudah ada mencakup:

- `payment_method = siplah`;
- `siplah_order_number`;
- vendor/owner/NPWP;
- invoice dan referensi pembayaran;
- placeholder template SiPLah;
- policy requirement yang membedakan SiPLah vs Non-SiPLah.

Status bukan lagi “belum ada sama sekali”, tetapi **partial/in progress**. End-to-end verification dan ownership field masih harus dijaga.

---

## 9. GUI standardization — status

Fondasi global tersedia:

- sidebar persisten;
- breadcrumb sticky;
- page header/summary;
- sticky `Ke atas`;
- form/button/table primitives;
- pagination/per-page;
- alert, empty state, modal, badge, detail, toolbar;
- loading/skeleton, action menu, sticky actions, danger zone;
- theme profile + dark appearance;
- compatibility layers untuk markup lama.

Target sekarang bukan membuat design system baru, tetapi mengurangi markup legacy saat halaman disentuh dan menjaga konsistensi theme.

---

## 10. Mobile QA

`docs/MOBILE_VISUAL_QA_TODO.md` masih berstatus TODO/RVR. Perubahan Paket terbaru juga harus masuk regression mobile sebelum aplikasi disebut mobile-verified.

---

## 11. Testing dan verification

Jangan menganggap seluruh suite hijau hanya karena test tertentu pernah PASS.

Setelah backend berubah:

```text
php artisan test --compact <test relevan>
```

Setelah frontend berubah:

```text
npm run build
```

Perubahan dokumentasi saja tidak membutuhkan build.

---

## 12. Technical debt yang harus terlihat jelas

1. Validasi tanggal pengadaan belum identik antara Detail Transaksi dan Paket.
2. Label tombol peserta konsumsi masih generic walaupun sumber `fillTeachers()` sekarang Dapodik-only.
3. Paket SPJ masih memakai compatibility layer/DOM placement karena view besar belum sepenuhnya direfaktor menjadi komponen kecil.
4. Mobile regression belum ditutup.
5. Generator/lifecycle/reconciliation/authorization masih membutuhkan hardening end-to-end.

---

## 13. Prioritas berikutnya

1. Seragamkan purchase-date rules antar endpoint.
2. Tambahkan/rapikan focused test untuk Surat Pesanan dan kronologi tanggal.
3. Stabilkan generator/preview PDF/Word/Excel.
4. Finalisasi lifecycle/locking/revisi/numbering.
5. Finalisasi rekonsiliasi ARKAS snapshot/diff.
6. Hardening authorization per role.
7. End-to-end test seluruh kategori.
8. Selesaikan mobile visual QA.
9. Laporan BOS dan release hardening.

---

## 14. Dokumen yang harus dibaca bersama

```text
README.md
AGENTS.md
docs/ARCHITECTURE_COMPLETE.md
docs/SPJ_DESIGN_DECISIONS.md
docs/USER_SCENARIOS.md
docs/GUI_STANDARDIZATION.md
docs/CSS_USAGE_GUIDE.md
docs/DEVELOPMENT_ROADMAP.md
docs/DOCUMENT_TEMPLATE_PLACEHOLDERS.md
docs/SIPLAH_MVP_PLAN.md
docs/MOBILE_VISUAL_QA_TODO.md
```
