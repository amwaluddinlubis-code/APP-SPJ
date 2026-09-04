# SPJ BOSP Web — Catatan Progres Terakhir

Terakhir diperbarui: 2026-09-04

Dokumen ini adalah snapshot kondisi implementasi branch `gui-standardization`. Gunakan dokumen ini sebagai titik awal sesi pengembangan berikutnya agar tidak mengandalkan catatan lama atau asumsi dari fase Excel/VBA.

---

## 1. Kondisi project saat ini

Jenis project:

- Laravel 12;
- PHP 8.2+;
- arah stack TALL: Tailwind, Alpine, Laravel, Livewire;
- Filament dipakai pada level komponen tertentu, bukan sebagai admin panel generik utama;
- database multi-koneksi: database utama + database tenant/sekolah;
- sinkronisasi ARKAS/BKU menjadi sumber data operasional utama;
- `SpjController` sudah direduksi menjadi adapter HTTP tipis dan domain SPJ dipisahkan ke use case;
- GUI memiliki design system global dengan primitive tema-aware;
- dark mode dan pilihan tema dimuat secara global.

Fokus pengembangan saat ini:

1. menuntaskan workflow SPJ end-to-end;
2. menstabilkan generator/preview dokumen;
3. memfinalkan lifecycle, penomoran, locking, revisi, dan authorization;
4. menyelesaikan rekonsiliasi ARKAS dengan snapshot/diff;
5. memigrasikan view tersisa ke primitive UI yang sudah digeneralisasi;
6. menyiapkan test dan hardening menuju release candidate.

---

## 2. Arsitektur database

Aplikasi menggunakan dua lapisan database.

### Database utama

Menyimpan data global seperti:

- user/login;
- daftar sekolah;
- konfigurasi database sekolah;
- konfigurasi sumber ARKAS;
- backup database sekolah;
- konfigurasi global dan metadata aplikasi.

### Database tenant/sekolah

Menyimpan data operasional per sekolah, antara lain:

- tahun anggaran;
- sumber dana;
- RKAS/BKU hasil sinkronisasi;
- transaksi dan item transaksi;
- data manual SPJ;
- kategori SPJ;
- paket SPJ;
- format dan nomor dokumen;
- detail barang/konsumsi/pemeliharaan/SPPD/honor/jasa;
- audit operasional;
- status sinkronisasi dan rekonsiliasi.

Prinsip wajib:

- data sumber ARKAS/BKU tidak boleh ditimpa oleh data manual operator;
- sinkronisasi tidak boleh menghapus pekerjaan manual SPJ;
- query operasional harus selalu berada dalam konteks sekolah dan tahun anggaran aktif.

---

## 3. Keputusan bisnis yang tetap berlaku

1. Sumber data utama berasal dari hasil sinkronisasi ARKAS/BKU.
2. Data manual SPJ dipisahkan dari data sumber.
3. `manual_description` tidak digunakan; uraian operator memakai `payment_description` atau field kategori yang sesuai.
4. Sumber dana dikunci pada konteks tahun anggaran aktif.
5. Satu pembayaran SPPD dapat memiliki lebih dari satu pelaksana.
6. Satu transaksi pemeliharaan memiliki satu work order dan dapat memiliki banyak pekerja.
7. Penomoran dokumen harus otomatis dan dilakukan setelah data siap.
8. Setiap jenis dokumen memiliki domain nomor sendiri.
9. Urutan input transaksi tidak boleh menentukan urutan nomor dokumen.
10. Penomoran massal per triwulan untuk item READY adalah strategi utama.
11. Preview/download tidak boleh membuat nomor secara diam-diam.
12. Dokumen bernomor/final harus dikunci dan perubahan berikutnya harus melalui mekanisme revisi/buka kunci yang sah.
13. Sinkronisasi ARKAS tidak boleh menimpa `receipt_recipient_name` atau data operator lain.

---

## 4. Implementasi yang sudah tersedia

### 4.1 Setup, login, sekolah, dan tahun aktif

Sudah tersedia:

- setup awal aplikasi;
- login/logout;
- pemilihan sekolah;
- pemilihan tahun anggaran;
- konteks sumber dana aktif;
- pengalihan alur sekolah → tahun sebelum sinkronisasi/operasi tenant.

Test terkait tersedia, termasuk `SchoolYearSelectionFlowTest`.

### 4.2 User management dan impersonation

Administrator sudah dapat:

- mengelola user;
- menguji alur sebagai operator melalui impersonation;
- kembali sebagai administrator dari banner global.

Proteksi utama:

- admin tidak dapat impersonate dirinya sendiri;
- admin tidak dapat impersonate admin lain;
- konteks sekolah user target dapat diaktifkan saat impersonation.

### 4.3 Database sekolah, backup, dan reset bersih

Sudah tersedia:

- provision database sekolah;
- aktivasi koneksi tenant;
- migrasi tenant;
- integrity check/vacuum/checkpoint;
- backup dan restore database sekolah;
- menu administrator **Reset Database** untuk sekolah aktif;
- konfirmasi reset menggunakan `RESET <NPSN>`;
- rebuild database tenant dengan menghapus file SQLite tenant dan membuat ulang migration;
- pembersihan file `-wal` / `-shm` terkait;
- reset `sqlite_sequence` sehingga auto-increment kembali dari awal;
- pembersihan session tahun anggaran/sumber dana aktif setelah reset.

Database utama/global tidak ikut dihapus.

Test khusus reset database tersedia dan memverifikasi insert setelah reset kembali menghasilkan ID awal.

### 4.4 Proteksi database testing

Test diarahkan ke database testing terpisah (`database/testing.sqlite`) agar tidak mengosongkan database utama. Aturan ini tidak boleh dilemahkan.

### 4.5 Sinkronisasi ARKAS/BKU

Prinsip safe sync yang sudah diterapkan:

- data sumber disimpan sebagai hasil sinkronisasi;
- transaksi dapat ditandai hilang dari sumber tanpa menghapus data manual;
- transaksi dapat ditandai perlu rekonsiliasi;
- data manual/operator tetap dipertahankan.

Pengembangan lanjutan:

- snapshot sebelum/sesudah;
- diff rekonsiliasi yang mudah dibaca;
- keputusan rekonsiliasi eksplisit;
- perlindungan ekstra terhadap dokumen final.

### 4.6 Modul transaksi

Modul transaksi menggunakan Livewire dan mencakup pencarian/filter, status sumber, penerima BKU/ARKAS, penerima kuitansi manual, metode pembayaran canonical, modal perubahan data SPJ, dan navigasi ke workspace detail transaksi.

### 4.7 Penerima kuitansi manual

Field utama:

```text
transactions.receipt_recipient_name
```

`recipient_name` tetap sumber BKU/ARKAS, sedangkan `receipt_recipient_name` adalah data operator. Sinkronisasi tidak boleh menimpanya.

### 4.8 Detail transaksi sebagai workspace operator

Struktur kerja:

```text
Data ARKAS/BKU (readonly)
→ Data Umum SPJ (editable)
→ Detail kategori
→ Checklist kelengkapan
→ Buat/siapkan paket SPJ
→ Penomoran / preview / cetak / final
```

### 4.9 Kategori SPJ

Kategori utama:

- `BARANG`;
- `KONSUMSI`;
- `PEMELIHARAAN`;
- `SPPD`;
- `HONOR_PEGAWAI`;
- `JASA_LAINNYA`.

`SpjTransactionDetailsService` tetap menjadi bagian penting penyimpanan detail kategori.

### 4.10 Refactor `SpjController`

`SpjController` tidak lagi menjadi God Controller. Route dan nama action tetap dipertahankan, tetapi tanggung jawab dipisahkan ke:

```text
app/UseCases/Spj/
├── SpjWorkspaceUseCase.php
├── SpjPackageUseCase.php
├── SpjNumberingUseCase.php
├── SpjDocumentUseCase.php
└── SpjReportUseCase.php
```

Pembagian tanggung jawab:

- `SpjWorkspaceUseCase`: query/tab workspace, metrics, roster peserta;
- `SpjPackageUseCase`: prepare/edit paket, validasi kategori, pajak, detail paket;
- `SpjNumberingUseCase`: numbering, lifecycle, quarter workflow, settlement;
- `SpjDocumentUseCase`: preview/download/generator integration;
- `SpjReportUseCase`: laporan dan export.

Refactor ini bertujuan mengubah organisasi kode tanpa mengubah aturan bisnis atau kontrak HTTP.

### 4.11 Paket SPJ, checklist, numbering, dan dokumen

Fondasi paket/checklist, workflow numbering, format nomor, template dokumen, placeholder, PDF/Word/Excel, serta audit sudah tersedia. Area ini belum dianggap final sampai alur browser end-to-end stabil.

---

## 5. Standardisasi GUI — kondisi terbaru

Acuan: `docs/GUI_STANDARDIZATION.md`.

### Fondasi global yang sudah diterapkan

- sidebar persisten;
- breadcrumb global sticky;
- sticky footer/control `Ke atas` untuk halaman panjang;
- page header + summary;
- form/input/select/textarea/button primitives;
- table standardization global;
- client/server pagination yang diseragamkan secara visual;
- responsive horizontal table wrapper;
- status badge manusiawi;
- compatibility layer status lama;
- visual readonly ARKAS/BKU vs editable SPJ;
- dark mode global;
- dynamic accent theme.

### Primitive UI yang tersedia

```text
<x-ui.page-shell>
<x-ui.alert>
<x-ui.empty-state>
<x-ui.badge>
<x-ui.detail-list>
<x-ui.detail-item>
<x-ui.toolbar>
<x-ui.modal>
<x-ui.action-menu>
<x-ui.loading>
<x-ui.sticky-actions>
<x-ui.danger-zone>
<x-ui.table>
<x-ui.field>
<x-ui.input>
<x-ui.select>
<x-ui.textarea>
<x-ui.button>
<x-ui.form-section>
<x-ui.status-badge>
```

Komponen legacy yang sudah diarahkan ke sistem baru antara lain:

- `page-filter`;
- `tabs`;
- `stat-item`;
- `error-alert`;
- `loading-spinner`;
- `page-table-per-page`.

### Aturan tema

Accent non-semantik memakai:

```text
--theme-accent
--theme-accent-strong
--theme-accent-soft
--theme-sidebar
--theme-sidebar-deep
```

Surface/text/border memakai token `--ui-*`. Success/warning/danger tetap warna semantik agar makna tindakan tidak berubah saat tema diganti.

### Pekerjaan GUI yang masih tersisa

- migrasi view yang masih menulis kelas Tailwind panjang/hard-coded;
- UI rekonsiliasi final;
- workflow SPJ/numbering final;
- dashboard operasional final;
- authorization/safety audit;
- lifecycle dokumen dan revisi final.

Design system dasar tidak perlu dibangun ulang; halaman baru harus memakai primitive yang sudah ada.

---

## 6. Test suite

Feature test mencakup antara lain:

- `CriticalDocumentWorkflowTest`;
- `DocumentNumberFormatSettingsTest`;
- `DocumentNumberingWorkflowTest`;
- `ImpersonationTest`;
- `SafeArkasSynchronizationTest`;
- `SchoolDatabaseManagerTest`;
- `SchoolDatabaseResetServiceTest`;
- `SchoolYearSelectionFlowTest`;
- `SecurityHardeningTest`;
- `SyncedDataSpjEntitiesTest`;
- `TransactionsTableLivewireTest`;
- `UserManagementTest`.

Catatan: keberadaan test bukan klaim bahwa seluruh suite sedang hijau. Jalankan `php artisan test` dan `npm run build` pada environment project sebelum release.

---

## 7. Technical debt / area perhatian

### Controller SPJ

Masalah controller besar sudah ditangani pada tingkat utama melalui use case extraction. Technical debt berikutnya adalah menjaga use case tetap kecil, menghindari duplikasi service, dan memastikan aturan domain tidak kembali masuk ke controller.

### Route web

`routes/web.php` masih besar dan dapat dipisahkan per domain setelah workflow inti stabil.

### Migrasi UI legacy

Compatibility layer masih diperlukan selama seluruh Blade belum dimigrasikan. Kode baru tidak boleh memperbanyak hard-coded accent color.

---

## 8. Prioritas pengembangan berikutnya

1. stabilkan generator PDF/Excel/Word;
2. preview terpadu tanpa side effect;
3. finalisasi lifecycle/status paket SPJ;
4. finalisasi penomoran triwulan;
5. finalisasi locking, revisi, pembatalan, dan audit;
6. rekonsiliasi ARKAS dengan snapshot/diff;
7. hardening role/authorization;
8. end-to-end test operator seluruh kategori;
9. migrasi view tersisa ke primitive UI dan UX produktivitas;
10. laporan BOS lengkap dan release hardening.

---

## 9. Definisi kondisi project saat ini

Project sudah melewati tahap prototype dasar. Fondasi domain, tenancy, use case SPJ, dan design system utama sudah terbentuk, tetapi aplikasi belum layak dianggap release final.

Posisi saat ini:

> Fondasi bisnis, arsitektur, dan design system sudah terbentuk; pengembangan sedang menuntaskan workflow SPJ end-to-end, rekonsiliasi, lifecycle dokumen, dan production hardening.

---

## 10. Dokumen yang wajib dibaca bersama

```text
AGENTS.md
docs/ARCHITECTURE_COMPLETE.md
docs/SPJ_DESIGN_DECISIONS.md
docs/USER_SCENARIOS.md
docs/GUI_STANDARDIZATION.md
docs/DEVELOPMENT_ROADMAP.md
docs/DOCUMENT_TEMPLATE_PLACEHOLDERS.md
```
