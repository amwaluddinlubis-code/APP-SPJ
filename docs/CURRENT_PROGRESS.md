# SPJ BOSP Web — Current Progress / Open Issues

Terakhir diperbarui: **2026-09-10**

Dokumen ini memuat kondisi release yang masih relevan pada branch `gui-standardization`. Item yang sudah ditutup diringkas sebagai baseline dan tidak dipelihara sebagai backlog aktif.

Checkpoint correctness P0-08 terbaru:

```text
branch : gui-standardization
commit : 50794b4872d3be273ee73fbaccdd438fcca4569d
subject: test: prove ARKAS upsert preserves absent rows
```

## P0-08 — Generic ARKAS Importer

**Status: IMPLEMENTED / SOURCE-KEY PASS / TENANT BOUNDARY PASS / SYNC-MODE PASS / HARDENING OPEN.**

Alur canonical:

```text
Bridge -> staging -> mapping -> reconciliation -> domain adapter
```

Correctness yang sudah FUNCTIONAL PASS:

- source-key resolver bersama `ArkasSourceKeyResolver` dipakai Generic Import, Staging, Reconciliation, dan preview importer;
- configured key diprioritaskan case-insensitive, lalu fallback identity ARKAS canonical, lalu hash payload sebagai fallback terakhir;
- Generic Import record non-kosong tidak lagi mempunyai undefined `sourceKey()` runtime path;
- seluruh action `ArkasImporterController` melewati `active-school` lalu `active-year`; guard route tetap `administrator`;
- GET importer, save mapping, preview, dan sync sudah diregresikan terhadap cross-school/cross-year context;
- forged profile tenant lain pada preview/sync menghasilkan 404;
- stale fiscal-year ID tenant lain ditolak setelah tenant sekolah aktif dipilih;
- **Upsert PASS:** stable key yang sama di-update, key baru ditambah, dan record lama yang tidak ada pada snapshot berikutnya tidak disapu;
- **Incremental PASS:** hanya record dengan source-updated timestamp setelah `last_synced_at` yang diproses; record stale tidak menimpa staging;
- **Full Refresh PASS:** staging hanya diganti untuk `profile_id + fiscal_year_id` aktif dan domain RKAS hanya diganti pada fiscal year aktif; staging profile lain dan fiscal year lain tetap utuh.

Regression canonical P0-08 yang sudah masuk `SPJ Critical`:

```text
tests/Unit/ArkasSourceKeyResolverTest.php
tests/Feature/ArkasGenericImportSourceKeyTest.php
tests/Feature/ArkasGenericImportSyncModeTest.php
tests/Feature/ArkasImporterTenantBoundaryTest.php
```

CI `SPJ Critical Verification` pada `50794b4872d3be273ee73fbaccdd438fcca4569d`:

```text
frontend build         PASS
Blade view cache       PASS
SPJ Critical PHPUnit   PASS — 134 tests / 896 assertions
repository Pint        WARN — 1 pre-existing style issue
```

Pint masih menemukan `single_quote` pada `tests/Feature/SyncProgressUiTest.php`. Repository-wide Pint tetap advisory/`continue-on-error`; warning tersebut bukan regression dari pekerjaan importer ini.

### P0-08 yang masih terbuka

- preview full reconciliation harus dibuktikan read-only terhadap domain target;
- raw profile tanpa stable key perlu policy eksplisit agar perubahan payload tidak meninggalkan versi record lama pada Upsert/Incremental;
- source kosong harus diregresikan untuk ketiga mode yang relevan;
- schema drift harus diregresikan, termasuk hilangnya source-key/mandatory mapping;
- queue/background import harus membuktikan tenant diaktifkan sebelum profile/fiscal year tenant dibaca;
- staging/import lock harus tenant-scoped, bukan hanya profile/year lokal;
- `created_at` existing staging row tidak boleh di-reset bila bermakna first-created timestamp;
- histori import perlu membedakan read/new/changed/unchanged/removed, bukan sekadar processed count;
- Bridge-side incremental delta fetch tetap optimasi setelah correctness selesai; implementasi sekarang membaca snapshot lalu memfilter di aplikasi.

**Generic Importer belum release-ready.** Source-key, tenant boundary, dan tiga mode sinkronisasi utama sudah PASS, tetapi raw/source-empty/schema-drift/queue dan hardening di atas masih harus ditutup sebelum status dinaikkan.

Panduan teknis: `docs/ARKAS_IMPORTER.md`.

---

## Baseline P0 yang sudah ditutup

### P0-03 — Numbering + lifecycle

**FUNCTIONAL PASS.** Numbering idempotent, sequence stabil, NUMBERED/FINAL terkunci, cancel/reissue/reopen menyimpan histori, dan preview/download tidak mengalokasikan nomor.

### P0-04 — Authorization backend

**FUNCTIONAL PASS** untuk jalur yang sudah diregresikan.

```text
VIEWER        = read-only
OPERATOR      = mutation operasional normal
ADMINISTRATOR = mutation sensitif / maintenance / lifecycle administratif
```

Generic ARKAS Importer tetap administrator-only dan mempunyai tenant-context regression sendiri.

### P0-05 — Safe sync + reconciliation

**FUNCTIONAL PASS** untuk canonical transaction/source adapter.

- source unchanged tidak mengubah overlay;
- source changed membuat reconciliation event;
- source missing tidak menghapus pekerjaan operator;
- source returning menyambung ke identity lama;
- `item_description`, payment/vendor/category detail tetap aman;
- NUMBERED/FINAL tidak dimutasi diam-diam oleh source sync;
- before/after snapshot dan field-level diff tersedia.

### P0-06 — Tenant/context isolation

**FUNCTIONAL PASS untuk resource SPJ + Generic ARKAS Importer boundary.**

Boundary canonical:

```text
School + Fiscal Year + Fund Source
```

Cross-school, cross-year, cross-fund resource SPJ sudah diregresikan. Generic Importer memakai boundary `administrator -> active-school -> active-year -> connection school`.

---

## P0 yang masih memerlukan runtime nyata

### P0-01 — E2E enam kategori

**RVR — menunggu database sekolah nyata.**

Target kategori:

```text
BARANG
KONSUMSI
PEMELIHARAAN
JASA_LAINNYA
SPPD
HONOR_PEGAWAI
```

Saat database tersedia, jalankan kandidat nyata melalui Detail -> DRAFT -> READY -> NUMBERED -> preview/download -> FINAL dan bandingkan audit sebelum/sesudah dengan `spj:audit-diff --fail-on-regression`.

### P0-02 — Generator dokumen

**RVR/OPEN.** Word/Excel/PDF, unresolved-placeholder guard, preview, download, dan package export sudah mempunyai foundation, tetapi output nyata enam kategori masih harus dibuktikan.

### P0-07 — APP DATA / backup / reset / restore

**RVR — memerlukan runtime tenant nyata.**

Harus dibuktikan:

```text
provision
switch tenant
backup
reset total + sqlite_sequence
restore
WAL/SHM cleanup
```

Database utama tidak boleh ikut rusak/terhapus dan restore harus mengembalikan data yang dibackup.

---

## P1 aktif

- JASA_LAINNYA multi-penerima sampai output dokumen nyata;
- PEMELIHARAAN bahan + upah full-document QA;
- SiPLah end-to-end output;
- browser QA Paket SPJ desktop/laptop;
- audit trail operasional end-to-end;
- Employee identity/participant roster hardening tanpa mengubah keputusan DAPODIK-only untuk auto-fill KONSUMSI.

Mobile/responsive QA penuh bukan release blocker saat ini dan tetap berada pada backlog future development.

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
- Resource SPJ harus berada dalam School + Fiscal Year + Fund Source aktif.
- Generic ARKAS Importer wajib melewati `administrator -> active-school -> active-year` sebelum query/write connection `school`.
- Auto-fill peserta KONSUMSI tetap `Employee.source_type = DAPODIK`; participant manual tetap diperbolehkan.

Command verification canonical:

```powershell
php artisan spj:verify
```

Saat database sekolah target tersedia, gunakan audit real-tenant sesuai `docs/P0_VERIFICATION_KIT.md` dan `docs/P0_01_SOURCE_AUDIT.md`.
