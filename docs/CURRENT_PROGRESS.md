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
- sinkronisasi ARKAS/BKU sudah menjadi sumber data operasional utama;
- GUI sedang distandarkan pada branch `gui-standardization`;
- dark component theme sudah dimuat secara global.

Fokus pengembangan saat ini:

1. menyelesaikan standardisasi GUI lintas halaman;
2. menjadikan Detail Transaksi sebagai workspace operator;
3. menuntaskan workflow SPJ end-to-end;
4. menstabilkan generator/preview dokumen;
5. memfinalkan lifecycle, penomoran, locking, revisi, dan authorization;
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

Test impersonation dan user management tersedia.

### 4.3 Database sekolah dan backup

Sudah tersedia fondasi untuk:

- provision database sekolah;
- aktivasi koneksi tenant;
- migrasi tenant;
- integrity check/vacuum/checkpoint;
- backup dan restore database sekolah.

`SchoolDatabaseManagerTest` tersedia untuk area ini.

### 4.4 Proteksi database testing

Test diarahkan ke database testing terpisah (`database/testing.sqlite`) agar tidak mengosongkan database utama.

Aturan ini tidak boleh dilemahkan.

### 4.5 Sinkronisasi ARKAS/BKU

Sinkronisasi ARKAS sudah memiliki controller/service dan adapter ARKASBridge.

Prinsip safe sync yang sudah diterapkan:

- data sumber disimpan sebagai hasil sinkronisasi;
- transaksi dapat ditandai hilang dari sumber tanpa menghapus data manual;
- transaksi dapat ditandai perlu rekonsiliasi;
- data manual/operator tetap dipertahankan.

Test `SafeArkasSynchronizationTest` dan test entitas hasil sync tersedia.

Pengembangan lanjutan yang masih diperlukan:

- snapshot sebelum/sesudah;
- diff rekonsiliasi yang mudah dibaca;
- keputusan rekonsiliasi eksplisit;
- perlindungan ekstra terhadap dokumen final.

### 4.6 Modul transaksi

Modul transaksi sudah mulai modular menggunakan Livewire:

```text
app/Livewire/TransactionsTable.php
resources/views/livewire/transactions-table.blade.php
resources/views/transactions/index.blade.php
```

Fungsi yang sudah tersedia mencakup:

- pencarian transaksi;
- filter/status;
- urutan status lalu ID;
- penerima BKU/ARKAS;
- penerima kuitansi manual;
- status data sumber;
- indikator rekonsiliasi;
- metode pembayaran canonical;
- modal perubahan data SPJ;
- navigasi ke detail transaksi.

Test Livewire transaksi tersedia.

### 4.7 Metode pembayaran canonical

Nilai backend:

```text
transfer_bank
siplah
tunai
```

Label operator:

```text
Transfer Bank (CMS / Non Tunai)
SiPLah Kemdikbud
Tunai Kas BOS
```

Default ditentukan dari data sumber seperti `IS_SIPLAH`, `NO_BUKTI`, dan `KODE_BKU`.

### 4.8 Penerima kuitansi manual

Field utama:

```text
transactions.receipt_recipient_name
```

Prinsip:

- `recipient_name` tetap merupakan data sumber BKU/ARKAS;
- `receipt_recipient_name` merupakan data operator untuk kuitansi;
- sinkronisasi tidak boleh menimpanya;
- accessor penerima efektif menggunakan fallback bila field manual kosong.

Generator/template dan validasi paket sudah diarahkan memakai penerima kuitansi efektif.

### 4.9 Detail transaksi sebagai workspace operator

Halaman Detail Transaksi sedang diposisikan sebagai pusat kerja operator, bukan hanya halaman read-only.

Struktur target yang sudah mulai diterapkan:

```text
Data ARKAS/BKU (readonly)
→ Data Umum SPJ (editable)
→ Detail kategori
→ Checklist kelengkapan
→ Buat/siapkan paket SPJ
→ Penomoran / preview / cetak / final
```

Status sumber yang tampil mencakup:

- data ARKAS aktif;
- tidak muncul pada sinkronisasi terakhir;
- perlu rekonsiliasi.

Checklist menggunakan bahasa operator.

### 4.10 Kategori SPJ

Kategori utama:

- `BARANG`;
- `KONSUMSI`;
- `PEMELIHARAAN`;
- `SPPD`;
- `HONOR_PEGAWAI`;
- `JASA_LAINNYA`.

Detail kategori yang sudah mulai didukung:

- Barang: invoice/faktur, pesanan, BAP, BAST, item barang;
- Konsumsi: pembelian, acara, peserta/porsi;
- Pemeliharaan: work order dan banyak pekerja;
- SPPD: banyak pelaksana dan tanggal perjalanan;
- Honor Pegawai: banyak penerima honor;
- Jasa Lainnya: isian jasa ringkas.

`SpjTransactionDetailsService` menangani penyimpanan detail kategori secara lebih terstruktur.

### 4.11 Paket SPJ dan checklist

Sudah tersedia fondasi untuk:

- membuat/menyiapkan paket SPJ;
- mengecek kelengkapan;
- memisahkan workflow paket dari transaksi sumber;
- mengarahkan operator pada kekurangan data.

Validasi sebelum READY/penomoran tetap perlu terus diperketat agar konsisten antar kategori.

### 4.12 Penomoran dokumen

Sudah tersedia:

- `SpjNumberingWorkflowController`;
- pengaturan format nomor dokumen;
- workflow numbering;
- test format nomor;
- test workflow penomoran;
- critical document workflow test.

Target final yang belum dianggap selesai:

- simulasi nomor sebelum commit;
- penomoran triwulan atomik;
- domain urutan per jenis dokumen;
- pencegahan nomor ganda;
- audit lengkap;
- tidak ada numbering implisit saat preview/download.

### 4.13 Template dan generator dokumen

Sudah tersedia:

- `DocumentTemplateController`;
- `SpjDocumentController`;
- `SpjTemplateService`;
- DomPDF;
- PHPWord;
- PhpSpreadsheet;
- placeholder template terpusat;
- katalog placeholder di UI;
- dokumentasi `DOCUMENT_TEMPLATE_PLACEHOLDERS.md`.

`REFERENSI_BAYAR` sudah terhubung ke referensi pembayaran transaksi sehingga placeholder terkait cara bayar dapat diproses konsisten.

Status saat ini:

- fondasi generator sudah ada;
- Word/Excel/PDF bukan lagi fitur yang “belum tersedia”;
- namun generator dan preview lintas format masih perlu stabilisasi end-to-end dari browser.

### 4.14 Audit dan laporan

Sudah terdapat controller/service audit dan log operasional.

Laporan audit sudah memiliki fondasi export.

Laporan BOS lengkap seperti K7/K7A/K8/SPTJM/K7B/K7C dan buku pembantu masih termasuk tahap lanjutan setelah workflow inti stabil.

### 4.15 Dashboard operasional

Dashboard dan operational dashboard sudah tersedia sebagai fondasi monitoring.

Penyempurnaan berikutnya adalah menyelaraskan dashboard dengan status operator, triwulan, kelengkapan SPJ, dan rekonsiliasi.

---

## 5. Standardisasi GUI — kondisi terbaru

Dokumen acuan: `docs/GUI_STANDARDIZATION.md`.

### Sudah diterapkan

- sidebar persisten;
- breadcrumb global sticky;
- page header + summary pattern;
- fondasi komponen `x-ui.*`;
- status badge dengan bahasa operator;
- compatibility layer untuk status lama;
- pemisahan readonly ARKAS/BKU dan editable SPJ;
- dark component layer;
- dark theme dimuat secara global.

### Sedang berlangsung

- migrasi form lama ke `x-ui.field`, `x-ui.input`, `x-ui.select`, `x-ui.textarea`, `x-ui.button`;
- standardisasi spacing, section, tabel, filter, dan empty state;
- Detail Transaksi sebagai workspace operator;
- migrasi semua badge/status teknis ke label manusiawi;
- penyelarasan halaman user management dan workspace SPJ.

### Belum dianggap selesai

- UI rekonsiliasi;
- workflow SPJ & penomoran yang sepenuhnya konsisten;
- dashboard operasional final;
- authorization/safety audit dari sisi UI + backend;
- lifecycle dokumen dan revisi final.

---

## 6. Test suite yang sudah tersedia

Feature test mencakup antara lain:

- `CriticalDocumentWorkflowTest`;
- `DocumentNumberFormatSettingsTest`;
- `DocumentNumberingWorkflowTest`;
- `ImpersonationTest`;
- `SafeArkasSynchronizationTest`;
- `SchoolDatabaseManagerTest`;
- `SchoolYearSelectionFlowTest`;
- `SecurityHardeningTest`;
- `SyncedDataSpjEntitiesTest`;
- `TransactionsTableLivewireTest`;
- `UserManagementTest`.

Catatan penting:

- keberadaan test tidak berarti seluruh test pasti lulus pada setiap commit;
- setelah perubahan backend jalankan test relevan atau `php artisan test`;
- setelah perubahan frontend jalankan `npm run build`.

---

## 7. Technical debt / area perhatian

### Controller SPJ terlalu besar

`SpjController.php` telah menjadi controller besar dan perlu terus diarahkan ke service/use-case terpisah agar domain tidak semakin sulit dipelihara.

Gunakan service yang sudah tersedia sebelum menambah logika baru langsung ke controller.

### Route web semakin besar

`routes/web.php` sudah memuat banyak domain. Pemisahan route per domain dapat dipertimbangkan setelah workflow inti stabil agar tidak menambah risiko saat fase GUI aktif.

### Dokumentasi historis

Dokumen yang masih memakai istilah/field lama harus dianggap historis jika bertentangan dengan:

1. `SPJ_DESIGN_DECISIONS.md`;
2. `CURRENT_PROGRESS.md`;
3. implementasi kode aktif.

Jangan menghidupkan kembali `manual_description` atau workflow numbering lama hanya karena masih disebut di catatan historis.

---

## 8. Prioritas pengembangan berikutnya

Urutan utama:

1. stabilkan generator PDF/Excel/Word;
2. preview terpadu di browser yang sama tanpa side effect;
3. finalisasi lifecycle/status paket SPJ;
4. finalisasi penomoran triwulan;
5. finalisasi locking, revisi, pembatalan, dan audit;
6. rekonsiliasi ARKAS dengan snapshot/diff;
7. hardening role/authorization;
8. end-to-end test operator seluruh kategori;
9. UX produktivitas (filter persisten, pencarian, simpan & berikutnya);
10. laporan BOS lengkap dan release hardening.

Ikuti `docs/DEVELOPMENT_ROADMAP.md` sebagai urutan kerja resmi.

---

## 9. Definisi kondisi project saat ini

Project sudah melewati tahap prototype dasar. Fondasi domain utama sudah tersedia, tetapi belum layak dianggap release final.

Posisi saat ini paling tepat disebut:

> Fondasi bisnis dan arsitektur sudah terbentuk; pengembangan sedang menuntaskan standardisasi GUI dan workflow SPJ end-to-end sebelum production hardening.

Jangan membangun ulang arsitektur dari nol kecuali ditemukan blocker struktural yang dapat dibuktikan.

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
