# SPJ BOSP Web — Current Progress / Open Issues

Terakhir diperbarui: **2026-09-11**

Dokumen ini adalah sumber status release utama untuk branch `gui-standardization`.

Definisi status:

- **FUNCTIONAL PASS**: dibuktikan oleh source + deterministic CI/regression;
- **REAL-DATA VERIFIED**: dibuktikan pada salinan database sekolah nyata tanpa mengarang data yang tidak tersedia;
- **RVR**: masih memerlukan real-value/runtime/operator verification untuk aspek yang tidak bisa dibuktikan hanya dari CI;
- **DEFERRED**: sengaja tidak dikerjakan pada fokus pengembangan saat ini, bukan berarti PASS.

## Checkpoint terbaru

### Release gate branch aktif

```text
commit : 0df9b2ffbf14ed191e36c063e6355f9cb63c4a66
subject: test: gate template upload routing regression
CI run : 34578276166
CI job : 103195683045
result : PASS — 243 tests / 1848 assertions
```

Gate:

```text
Frontend build       PASS
Blade compile/cache  PASS
SPJ Critical         PASS — 243 tests / 1848 assertions
Repository Pint      ADVISORY — 2 style issues
```

Pint advisory saat ini berada di:

- `app/Services/ArkasStagingService.php`;
- `tests/Feature/SyncProgressUiTest.php`.

Style debt tersebut tidak memblokir functional release gate, tetapi tetap masuk backlog cleanup.

### Checkpoint canonical sebelumnya

```text
P0-08 Generic ARKAS Importer
111de8c2781af6c8413661bcc512b651b09acc72
PASS — 145 tests / 993 assertions

P0-02 Generator Dokumen baseline
1a633f766179e709abdba015c6b8433330c74aad
PASS — 157 tests / 1091 assertions

P0-07 APP DATA / backup / reset / restore
9721fb7f84e4a655389e90f315612380bda6b1be
CI 34404104837 / job 102642898023
PASS — 163 tests / 1127 assertions

P0-01 Six-category deterministic E2E
b9611e3814380e3776bf8327c4df01ecb85e6eb9
CI 34405652728 / job 102647980543
PASS — 169 tests / 1407 assertions

P0-01 Category lifecycle hardening
45c7acb58950cc5cba219142d94868e99dc0b9d3
CI 34414132079 / job 102675070244
PASS — 171 tests / 1418 assertions
```

---

## P0-01 — E2E enam kategori

**Status: FUNCTIONAL SIX-CATEGORY E2E PASS / REAL-DATA VERIFICATION STARTED / INSTALLED-RUNTIME DEFERRED.**

Kategori canonical:

```text
BARANG
KONSUMSI
PEMELIHARAAN
JASA_LAINNYA
SPPD
HONOR_PEGAWAI
```

Deterministic E2E yang sudah PASS membuktikan:

- source transaction mempunyai item SPJ sebelum Paket disiapkan;
- Paket dibuat melalui gateway DRAFT aplikasi;
- kategori disimpan melalui runtime use case canonical;
- BARANG/KONSUMSI menjalankan requirement penerimaan barang yang sesuai;
- semua kategori melewati READY validation;
- auxiliary number dibuat melalui numbering service canonical;
- Paket mencapai NUMBERED;
- preview nyata berhasil dirender;
- XLSX nyata dapat dibuka ulang oleh PhpSpreadsheet;
- PDF nyata berhasil dibuat;
- preview/download tidak menambah document identity atau number sequence;
- Paket dapat difinalkan menjadi FINAL dengan snapshot;
- FINAL terkunci dari edit normal;
- lifecycle audit dasar tercatat;
- enam kategori berjalan di suite `SPJ Critical`.

### Real-data baseline utama

Database sekolah nyata terbaru yang dianalisis berbeda dari baseline lama dan sekarang menjadi baseline real-data utama.

Health:

```text
SQLite integrity_check  ok
foreign_key_check       0 violation
jumlah tabel            43
```

Counts:

```text
transactions                  170
transaction_items             407
spj_packages                   66
spj_documents                   0
document_number_sequences       0
document_number_formats         0
operational_audit_logs        309
fiscal_years                    6
fund_sources                    2
```

Semua **66 Paket SPJ tahun 2026 berstatus READY** dan belum mempunyai numbering maupun generated document.

Kategori transaksi 2026:

```text
BARANG             41
HONOR_PEGAWAI      12
JASA_LAINNYA        9
KONSUMSI             2
PEMELIHARAAN         2
SPPD                  0
```

Data SPPD nyata tersedia pada tahun 2025 sebanyak 14 transaksi. Tidak ada satu fiscal year nyata yang mempunyai seluruh enam kategori, sehingga aplikasi **tidak boleh membuat/fabrikasi SPPD 2026** hanya untuk memenuhi six-category real-data coverage.

Real-data rule untuk tahap berikutnya:

- original upload tetap immutable;
- audit 66 READY package secara read-only sebelum mutation;
- numbering hanya pada isolated copy;
- numbering harus mengikuti canonical order dan tidak boleh melompati blocker lebih awal;
- penerima/vendor/SPPD/template yang tidak tersedia tidak boleh ditebak;
- source transaction dan `transaction_items` tidak boleh dimutasi untuk memaksa PASS.

Installed-runtime verification saat ini **DEFERRED sesuai fokus pengembangan**, sehingga tidak boleh ditulis sebagai PASS.

---

## P0-02 — Generator Dokumen + Upload Template

**Status: FUNCTIONAL GENERATOR PASS / TEMPLATE UPLOAD HARDENED PASS / OFFICIAL-TEMPLATE VISUAL RVR.**

Functional generator yang sudah PASS:

- DOCX/XLSX nyata dapat dibuat dan dibuka ulang oleh parser Office;
- PDF individual/package mempunyai signature valid dan EOF marker;
- preview/package/download memakai render preflight;
- unresolved placeholder memblokir output;
- final Office artifact divalidasi;
- Paket multi-template menghasilkan workbook multi-sheet dan PDF aktual;
- preview/download tidak menambah `spj_documents` atau `document_number_sequences`;
- common placeholder enam kategori sudah diregresikan;
- upload invalid tidak mengganti template aktif;
- replacement file/database bersifat atomic.

### Perbaikan halaman Upload Template — PASS

Masalah operator bahwa halaman tidak dapat upload template sudah ditutup pada source + CI.

Perbaikan yang sekarang aktif:

- halaman mempunyai dua mode eksplisit:
  - `?upload=package` untuk workbook master XLSX;
  - `?upload=single` untuk satu template DOCX/XLSX;
- routing form tidak lagi hanya bergantung pada `hasFile()`;
- jika PHP membuang POST body karena `post_max_size`, mode upload tetap diketahui dari query string;
- error paket dan single-template memakai error bag terpisah:
  - `templatePackageUpload`;
  - `templateUpload`;
- validasi extension tidak bergantung pada MIME Office dari Windows;
- single template menerima `.docx` / `.xlsx` maksimum 10 MB pada Laravel layer;
- package master menerima `.xlsx` maksimum 20 MB pada Laravel layer;
- UI menampilkan `upload_max_filesize`, `post_max_size`, dan batas efektif PHP;
- oversized request menghasilkan pesan yang menyebut batas PHP;
- storage lifecycle template dikunci ke disk `local` untuk save, validation, download, dan delete;
- generator dan template library sekarang membaca disk yang sama;
- file lama baru dihapus setelah database replacement berhasil.

Regression:

```text
tests/Feature/DocumentTemplateUploadValidationTest.php
tests/Feature/DocumentTemplateUploadRoutingRegressionTest.php
```

Kasus yang dibuktikan PASS:

- upload invalid tidak mengganti existing template;
- valid XLSX dengan warning tetap dapat disimpan;
- package upload failure tetap kembali ke validation path paket;
- valid XLSX tidak tergantung MIME detection Windows;
- uploaded template selalu berada di local disk;
- valid replacement mengganti record sebelum menghapus file lama;
- database failure mempertahankan template lama dan membersihkan file baru;
- explicit package mode tetap package walaupun body POST kosong;
- explicit single mode tetap single walaupun body POST kosong;
- Blade memakai explicit upload modes + separate error bags + server upload limit display.

Canonical gate untuk upload hardening:

```text
commit : 0df9b2ffbf14ed191e36c063e6355f9cb63c4a66
CI run : 34578276166
CI job : 103195683045
PASS   : 243 tests / 1848 assertions
```

Masih RVR untuk P0-02:

- template resmi/aktual sekolah untuk semua document type yang diperlukan;
- visual fidelity Word/Excel/PDF: print area, page break, header/footer, tabel dinamis, ukuran halaman;
- hasil cetak nyata;
- Paket nyata dengan seluruh template applicable;
- pembukaan output di Microsoft Word/Excel/PDF viewer target.

---

## P0-03 — Numbering + lifecycle

**Status: FUNCTIONAL PASS.**

Sudah diregresikan:

- numbering idempotent;
- nomor aktif tidak ganda;
- urutan berdasarkan transaction/source event canonical;
- NUMBERED/FINAL terkunci;
- cancel/reissue/reopen menyimpan histori;
- preview/download tidak mengalokasikan nomor;
- perubahan kategori Paket READY mengembalikan Paket ke DRAFT hanya ketika kategori benar-benar berubah.

---

## P0-04 — Authorization backend

**Status: FUNCTIONAL PASS untuk jalur yang diregresikan.**

- VIEWER read-only;
- OPERATOR mutation operasional;
- ADMINISTRATOR mutation sensitif, maintenance, dan administrative lifecycle;
- template/configuration sensitif mempunyai authorization guard;
- tenant/context guard terpisah dari role authorization.

---

## P0-05 — Safe sync + reconciliation

**Status: FUNCTIONAL PASS.**

- source sync tidak menghapus overlay manual;
- source changes menghasilkan reconciliation diff;
- source missing/returning mempertahankan transaction/package identity;
- NUMBERED/FINAL tidak dimutasi diam-diam;
- field-level before/after diff tersedia;
- reconciliation resolution mempunyai guard untuk stale source event.

---

## P0-06 — Tenant/context isolation

**Status: FUNCTIONAL PASS.**

Boundary canonical:

```text
School + Fiscal Year + Fund Source
```

Cross-school, cross-year, cross-fund-source, previous/next Paket, resource mutation, serta Generic ARKAS Importer sudah mempunyai regression boundary.

---

## P0-07 — APP DATA / backup / reset / restore

**Status: FUNCTIONAL MAINTENANCE PASS / INSTALLED-RUNTIME DEFERRED.**

Sudah FUNCTIONAL PASS:

- primary database deletion guard;
- tenant-only reset;
- WAL/SHM cleanup;
- tenant reprovision;
- `sqlite_sequence` reset;
- WAL checkpoint sebelum backup;
- source + backup integrity check;
- corrupt backup ditolak sebelum active database diganti;
- verified temp restore copy;
- `SEBELUM_PEMULIHAN` rollback snapshot;
- rollback otomatis bila post-restore integrity gagal;
- restore source backup dipertahankan dari retention;
- same-second backup mempunyai path unik;
- switch tenant setelah restore tetap aman.

Windows installed-runtime verification sengaja **DEFERRED** pada fokus pengembangan saat ini.

---

## P0-08 — Generic ARKAS Importer

**Status: FUNCTIONAL HARDENING PASS / READY FOR OPERATOR DATA TEST.**

Sudah PASS:

- shared `ArkasSourceKeyResolver`;
- stable key enforcement;
- tenant boundary;
- Upsert / Incremental / Full Refresh deterministic;
- reconciliation preview read-only;
- source-empty semantics;
- schema drift blocking;
- background tenant activation;
- shared resource lock berdasarkan school + source table + fiscal year;
- first-created timestamp preservation;
- semantic metrics `read/write/new/changed/unchanged/removed`.

Scale/performance lanjutan:

- Bridge-side incremental delta fetch;
- evaluasi/paginasi di atas Bridge row limit `100000`.

---

## P1 aktif

Prioritas setelah upload template ditutup:

1. audit read-only seluruh 66 READY package pada real-data baseline terbaru;
2. perbaiki blocker Paket yang benar-benar berasal dari source/rule aplikasi tanpa fabrikasi data;
3. lanjutkan JASA_LAINNYA multi-penerima sampai output dokumen;
4. PEMELIHARAAN bahan + upah full-document QA;
5. SiPLah end-to-end output;
6. browser QA Paket SPJ desktop/laptop;
7. audit trail operasional E2E;
8. Employee identity / participant roster hardening dengan kontrak auto-fill KONSUMSI tetap DAPODIK-only.

Mobile/responsive penuh bukan release blocker target operator laptop/desktop saat ini.

---

## P2 aktif

- field-level validation UX;
- repository Pint/style cleanup;
- GUI/compatibility cleanup;
- icon/action consistency;
- performance profiling;
- Bridge generated `bin/obj` hygiene;
- report foundation.

---

## Kontrak aktif yang tidak boleh diregresikan

- ARKAS/BKU = source readonly; operator SPJ = overlay.
- Source sync tidak menghapus overlay manual.
- Source missing/returning tidak membuat identity baru.
- NUMBERED/FINAL tidak dimutasi diam-diam oleh sync.
- Kategori canonical: `BARANG`, `KONSUMSI`, `PEMELIHARAAN`, `JASA_LAINNYA`, `SPPD`, `HONOR_PEGAWAI`.
- SiPLah bukan kategori; gunakan procurement/payment channel.
- Detail Transaksi hanya menulis `item_description`.
- Paket SPJ adalah workspace mutation dokumen.
- Pajak source immutable dari Paket.
- READY + category changed => DRAFT untuk revalidation.
- Preview/download tidak menerbitkan nomor.
- Upload template harus menggunakan explicit form mode dan disk `local` yang sama dengan generator.
- Jangan fabrikasi source data, penerima, vendor, SPPD, atau template untuk memenuhi coverage.

## Catatan release

Functional CI branch saat ini kuat dan latest gate PASS. Namun **functional PASS tidak sama dengan final production verification**. Official-template visual QA dan beberapa real-data checks tetap perlu dilakukan sebelum aplikasi disebut final release-ready.
