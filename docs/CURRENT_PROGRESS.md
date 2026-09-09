# SPJ BOSP Web — Current Progress / Open Issues

Terakhir diperbarui: **2026-09-10**

Dokumen ini memuat kondisi release yang masih relevan pada branch `gui-standardization`. Status `FUNCTIONAL PASS` berarti sudah dibuktikan pada source + CI deterministic; status `RVR` berarti masih membutuhkan real-value/runtime verification pada database, template, atau lingkungan sekolah nyata.

## Checkpoint canonical

### P0-08 — Generic ARKAS Importer

```text
commit : 111de8c2781af6c8413661bcc512b651b09acc72
subject: test: prove ARKAS sync paths share tenant resource locks
CI     : PASS — 145 tests / 993 assertions
```

### P0-02 — Generator Dokumen

```text
commit : 1a633f766179e709abdba015c6b8433330c74aad
subject: test: resolve template upload controller dependencies
CI     : PASS — 157 tests / 1091 assertions
```

### P0-07 — APP DATA / backup / reset / restore

```text
commit : 9721fb7f84e4a655389e90f315612380bda6b1be
subject: test: prove same-second backups stay distinct
CI run : 34404104837
CI job : 102642898023
result : PASS — 163 tests / 1127 assertions
```

Repository-wide Pint tetap advisory dan masih mempunyai **1 pre-existing** `single_quote` warning pada `tests/Feature/SyncProgressUiTest.php`. Frontend build dan Blade compile pada checkpoint P0-07 PASS.

---

## P0-08 — Generic ARKAS Importer

**Status: IMPLEMENTED / SOURCE-KEY PASS / TENANT BOUNDARY PASS / SYNC-MODE PASS / RELEASE-GUARD PASS / HARDENING PASS / READY FOR OPERATOR TEST.**

Correctness yang sudah FUNCTIONAL PASS:

- shared `ArkasSourceKeyResolver` untuk Generic Import, Staging, Reconciliation, dan preview;
- Upsert / Incremental / Full Refresh deterministic dan tenant-scoped;
- preview reconciliation read-only;
- raw Upsert/Incremental wajib mempunyai stable source key;
- source kosong mempunyai semantics yang aman;
- schema drift blocking;
- queue/background tenant activation;
- lock resource bersama berdasarkan school identity + source table + fiscal year;
- first-created `created_at` dipertahankan;
- metric `read/write/new/changed/unchanged/removed` tersedia.

Sisa P0-08 bukan blocker correctness yang sudah dikenal:

- Bridge-side incremental delta fetch;
- evaluasi/paginasi di atas Bridge fetch limit `100000` row;
- real-tenant/operator verification.

---

## P0-02 — Generator Dokumen

**Status: FUNCTIONAL GENERATOR PASS / OFFICIAL-TEMPLATE + REAL-TENANT RVR / READY FOR OPERATOR TEMPLATE TEST.**

Functional PASS:

- DOCX/XLSX benar-benar digenerate dan dapat dibuka kembali oleh parser Office;
- PDF individual/package divalidasi dengan signature `%PDF-` dan marker akhir `%%EOF`;
- preview template, preview Paket, PDF template, PDF Paket, dan Excel Paket memakai render preflight;
- unresolved placeholder memblokir output dengan error yang menyebut marker bermasalah;
- XLSX final dibuka kembali oleh PhpSpreadsheet;
- Paket multi-template menghasilkan workbook multi-sheet dan PDF aktual;
- preview/download/package yang diregresikan tidak menambah `spj_documents` atau `document_number_sequences`;
- placeholder umum enam kategori canonical sudah diregresikan;
- upload template invalid tidak mengganti template aktif;
- final download artifact divalidasi sebelum diberikan ke operator.

Masih RVR:

- template resmi/aktual sekolah untuk seluruh document type aktif;
- vendor/penerima/NPWP/pajak/nomor pada transaksi nyata;
- visual fidelity Word/Excel/PDF: page break, print area, header/footer, tabel dinamis, ukuran halaman, hasil cetak;
- Paket nyata keenam kategori dengan seluruh template applicable;
- pembukaan hasil akhir di Microsoft Word/Excel/PDF viewer target.

---

## P0-07 — APP DATA / backup / reset / restore

**Status: FUNCTIONAL MAINTENANCE PASS / REAL-RUNTIME RVR / READY FOR OPERATOR RUNTIME TEST.**

Functional maintenance yang sudah ditutup:

- reset menolak target yang sama dengan database utama aplikasi;
- reset hanya membangun ulang database tenant yang dipilih;
- reset membersihkan file SQLite tenant beserta `-wal` dan `-shm`;
- tenant diprovision ulang setelah reset;
- `sqlite_sequence` dibersihkan dan AUTOINCREMENT kembali dari awal pada database baru;
- backup melakukan WAL checkpoint sebelum snapshot;
- source dan artifact backup menjalani SQLite `PRAGMA integrity_check`;
- corrupt backup ditolak **sebelum** database aktif diganti;
- restore memakai temporary verified copy sebelum file aktif ditukar;
- restore membuat snapshot `SEBELUM_PEMULIHAN` sebagai rollback point;
- bila post-restore integrity check gagal, database sebelum restore dipulihkan otomatis;
- backup sumber yang dipilih operator dipertahankan dan dilindungi dari retention selama restore;
- retention backup sudah SQLite-safe dan tidak lagi menghasilkan `OFFSET` tanpa `LIMIT`;
- dua backup pada detik yang sama mempunyai artifact path berbeda dan tidak saling menimpa;
- setelah restore, tenant dapat berpindah sekolah dan kembali lagi tanpa kehilangan data tenant;
- fixture regression memakai central database terisolasi sehingga maintenance tenant tidak mengunci/merusak database utama test berikutnya.

Regression P0-07 yang masuk `SPJ Critical`:

```text
tests/Feature/SchoolDatabaseMaintenanceHardeningTest.php
tests/Feature/SchoolDatabaseResetServiceTest.php
```

CI canonical P0-07 pada `9721fb7f84e4a655389e90f315612380bda6b1be`:

```text
frontend build         PASS
Blade view cache       PASS
SPJ Critical PHPUnit   PASS — 163 tests / 1127 assertions
repository Pint        WARN — 1 pre-existing style issue
```

Yang masih RVR dan tidak boleh diklaim PASS hanya dari CI Linux/fixture:

- locking file SQLite/WAL/SHM pada runtime Windows aplikasi terpasang;
- backup/reset/restore memakai file database sekolah nyata;
- restore database tenant berukuran produksi;
- alur operator nyata: backup -> reset -> restore -> switch sekolah -> buka modul SPJ;
- observasi recovery pada kondisi gangguan OS/power-loss bila diperlukan untuk release target.

**P0-07 sekarang READY FOR OPERATOR RUNTIME TEST.** Ini bukan berarti keseluruhan aplikasi release-ready.

---

## Baseline P0 lain

### P0-03 — Numbering + lifecycle

**FUNCTIONAL PASS.** Numbering idempotent, sequence stabil, NUMBERED/FINAL terkunci, cancel/reissue/reopen menyimpan histori, dan preview/download tidak mengalokasikan nomor.

### P0-04 — Authorization backend

**FUNCTIONAL PASS** untuk jalur yang sudah diregresikan. VIEWER read-only, OPERATOR mutation operasional normal, ADMINISTRATOR mutation sensitif/maintenance/lifecycle administratif.

### P0-05 — Safe sync + reconciliation

**FUNCTIONAL PASS** untuk canonical transaction/source adapter. Source sync tidak menghapus overlay manual, source missing/returning mempertahankan identity, dan NUMBERED/FINAL tidak dimutasi diam-diam.

### P0-06 — Tenant/context isolation

**FUNCTIONAL PASS** untuk resource SPJ + Generic ARKAS Importer boundary.

Boundary canonical:

```text
School + Fiscal Year + Fund Source
```

---

## P0 yang masih memerlukan data/runtime nyata

### P0-01 — E2E enam kategori

**RVR — menunggu database sekolah nyata.**

Target:

```text
BARANG
KONSUMSI
PEMELIHARAAN
JASA_LAINNYA
SPPD
HONOR_PEGAWAI
```

Jalankan kandidat nyata melalui Detail -> DRAFT -> READY -> NUMBERED -> preview/download -> FINAL, lalu bandingkan audit before/after dengan `spj:audit-diff --fail-on-regression`.

P0-02 masih mempunyai official-template RVR dan P0-07 masih mempunyai installed-runtime RVR sebagaimana dijelaskan pada bagian masing-masing.

---

## P1 aktif

- JASA_LAINNYA multi-penerima sampai output dokumen nyata;
- PEMELIHARAAN bahan + upah full-document QA;
- SiPLah end-to-end output;
- browser QA Paket SPJ desktop/laptop;
- audit trail operasional end-to-end;
- Employee identity/participant roster hardening tanpa mengubah keputusan DAPODIK-only untuk auto-fill KONSUMSI.

Mobile/responsive QA penuh bukan release blocker saat ini.

---

## P2 aktif

- field-level validation UX;
- GUI/compatibility cleanup;
- repository Pint/style cleanup;
- icon/action consistency;
- performance profiling setelah correctness P0 stabil;
- repository hygiene Bridge untuk generated `bin/obj`;
- report foundation.

---

## Kontrak aktif yang tidak boleh diregresikan

- ARKAS/BKU = source readonly; data operator SPJ = overlay.
- Source sync tidak menghapus overlay manual.
- Source missing/returning tidak membuat transaction/package identity baru.
- NUMBERED/FINAL tidak dimutasi diam-diam oleh sync.
- Kategori canonical: `BARANG`, `KONSUMSI`, `PEMELIHARAAN`, `JASA_LAINNYA`, `SPPD`, `HONOR_PEGAWAI`.
- SiPLah bukan kategori; gunakan payment/procurement channel.
- Detail Transaksi hanya menulis `item_description`.
- Paket SPJ adalah workspace mutation dokumen.
- Pajak source immutable dari Paket.
- Preview/download tidak mengalokasikan nomor.
- Generated DOCX/XLSX/PDF wajib lolos output validation dan tidak menyisakan placeholder unresolved.
- Resource SPJ harus berada dalam School + Fiscal Year + Fund Source aktif.
- Generic ARKAS Importer wajib melewati `administrator -> active-school -> active-year` sebelum query/write connection `school`.
- Raw Generic Importer pada Upsert/Incremental wajib mempunyai stable source key; raw tanpa stable key hanya boleh Full Refresh.
- ARKAS sync lock harus resource-scoped dengan school identity + source table + fiscal year.
- Existing staging/import row tidak boleh kehilangan first-created `created_at` ketika payload berubah.
- Histori import wajib membedakan read/write/new/changed/unchanged/removed.
- Reset tenant tidak boleh pernah menghapus database utama aplikasi.
- Restore harus memverifikasi backup sebelum replacement dan mempertahankan rollback point.
- Auto-fill peserta KONSUMSI tetap `Employee.source_type = DAPODIK`; participant manual tetap diperbolehkan.

Command verification canonical:

```powershell
php artisan spj:verify
```

Saat database sekolah target tersedia, gunakan audit real-tenant sesuai `docs/P0_VERIFICATION_KIT.md` dan `docs/P0_01_SOURCE_AUDIT.md`.
