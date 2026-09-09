# SPJ BOSP Web — Current Progress / Open Issues

Terakhir diperbarui: **2026-09-10**

Dokumen ini memuat kondisi release yang masih relevan pada branch `gui-standardization`. Item yang sudah ditutup diringkas sebagai baseline dan tidak dipelihara sebagai backlog aktif.

Checkpoint correctness P0-08 terbaru:

```text
branch : gui-standardization
commit : 111de8c2781af6c8413661bcc512b651b09acc72
subject: test: prove ARKAS sync paths share tenant resource locks
```

Checkpoint functional P0-02 terbaru:

```text
branch : gui-standardization
commit : d13004663e5f95cf678abaea772e6095b811c4e2
subject: test: add output validator to SPJ critical suite
```

## P0-08 — Generic ARKAS Importer

**Status: IMPLEMENTED / SOURCE-KEY PASS / TENANT BOUNDARY PASS / SYNC-MODE PASS / RELEASE-GUARD PASS / HARDENING PASS / READY FOR OPERATOR TEST.**

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
- **Full Refresh PASS:** staging hanya diganti untuk `profile_id + fiscal_year_id` aktif dan domain RKAS hanya diganti pada fiscal year aktif; staging profile lain dan fiscal year lain tetap utuh;
- **Preview reconciliation read-only PASS:** preview tidak menulis staging, domain target, maupun histori import;
- **Raw stable-key policy PASS:** raw Upsert/Incremental wajib mempunyai effective stable source key; raw tanpa stable key hanya diperbolehkan pada Full Refresh;
- **Source kosong PASS:** Upsert/Incremental mempertahankan staging lama; Full Refresh membersihkan hanya scope profile/year/domain aktif dan menjaga tahun lain;
- **Schema drift blocking PASS:** source key, incremental timestamp, atau mapped source column yang hilang memblokir sync sebelum importer berjalan;
- **Queue/background tenant activation PASS:** job mengaktifkan sekolah yang benar sebelum membaca `ArkasImportProfile`/`FiscalYear`, lalu menerapkan configuration/schema guard yang sama;
- **Concurrency lock PASS:** staging dan Generic Import menggunakan resource lock yang sama berdasarkan `school identity + source_table + fiscal_year_id`; tenant berbeda tidak saling memblokir, resource yang sama tidak bisa berjalan paralel;
- **Timestamp semantics PASS:** row baru menetapkan `created_at` + `updated_at`; row berubah hanya memperbarui `updated_at`; row unchanged mempertahankan keduanya;
- **Import metrics PASS:** histori menyimpan `records_read`, `records_written`, `records_new`, `records_changed`, `records_unchanged`, dan `records_removed`; `records_written` sekarang berarti insert + update aktual, sedangkan delete Full Refresh tercatat terpisah di `records_removed`;
- background operation result juga membawa metric semantic tersebut.

Regression canonical P0-08 yang sudah masuk `SPJ Critical`:

```text
tests/Unit/ArkasSourceKeyResolverTest.php
tests/Feature/ArkasGenericImportSourceKeyTest.php
tests/Feature/ArkasGenericImportSyncModeTest.php
tests/Feature/ArkasGenericImportReleaseSafetyTest.php
tests/Feature/ArkasImportHardeningTest.php
tests/Feature/ArkasImporterTenantBoundaryTest.php
tests/Feature/ArkasImporterRuntimeGuardTest.php
tests/Feature/ArkasImportQueueTenantTest.php
```

CI P0-08 pada `111de8c2781af6c8413661bcc512b651b09acc72`:

```text
frontend build         PASS
Blade view cache       PASS
SPJ Critical PHPUnit   PASS — 145 tests / 993 assertions
repository Pint        WARN — 1 pre-existing style issue
```

### P0-08 yang masih terbuka

Correctness/hardening release-critical yang ditargetkan untuk operator test sudah ditutup. Sisa pekerjaan Generic Importer sekarang berupa optimasi/scale verification, bukan blocker correctness yang sudah dikenal:

- Bridge-side incremental delta fetch agar mode Incremental tidak perlu membaca snapshot penuh lalu memfilter di aplikasi;
- evaluasi batas fetch Bridge `100000` row pada sumber besar agar tidak terjadi truncation diam-diam pada deployment dengan tabel sangat besar;
- real-tenant/operator verification dengan database sekolah target.

**Generic Importer sekarang READY FOR OPERATOR TEST**, tetapi status tersebut bukan berarti keseluruhan aplikasi release-ready.

Panduan teknis: `docs/ARKAS_IMPORTER.md`.

---

## P0-02 — Generator Dokumen

**Status: FUNCTIONAL GENERATOR PASS / OFFICIAL-TEMPLATE + REAL-TENANT RVR / READY FOR OPERATOR TEMPLATE TEST.**

Hardening yang sekarang FUNCTIONAL PASS:

- seluruh jalur preview template, preview Paket, PDF template, PDF Paket, dan Excel Paket melakukan render preflight sebelum output disajikan;
- direct download template tetap memakai unresolved-placeholder guard;
- placeholder yang tidak terselesaikan menyebabkan output ditolak dengan pesan yang menyebut marker bermasalah;
- `SpjGeneratedDocumentValidator` memeriksa output final: DOCX/XLSX harus berupa paket Office valid, XLSX harus dapat dibuka kembali oleh PhpSpreadsheet, PDF harus mempunyai `%PDF-` dan `%%EOF`;
- file XLSX nyata berhasil dibuat, dibuka kembali, dan terbukti berisi identitas sekolah, nomor dokumen, nomor bukti, nilai bruto, serta dynamic item row yang sudah dirender;
- file DOCX nyata berhasil dibuat, dibuka sebagai paket Word, dan tidak menyisakan marker unresolved;
- Paket multi-template XLSX benar-benar menghasilkan beberapa sheet hasil render dan Paket PDF menghasilkan payload PDF aktual;
- regression membuktikan proses generate/preview/package yang diuji tidak membuat `spj_documents` baru dan tidak mengubah `document_number_sequences`;
- placeholder umum enam kategori canonical sudah diregresikan untuk kategori, identitas sekolah, nomor, bruto, pajak, neto, kepala sekolah, dan bendahara;
- final download artifact juga divalidasi sebelum diberikan kepada operator.

Regression P0-02 yang masuk `SPJ Critical`:

```text
tests/Feature/SpjDocumentGeneratorHardeningTest.php
tests/Feature/SpjGeneratedDocumentValidatorTest.php
tests/Feature/DocumentTemplateUploadValidationTest.php
```

CI canonical terbaru pada `d13004663e5f95cf678abaea772e6095b811c4e2`:

```text
frontend build         PASS
Blade view cache       PASS
SPJ Critical PHPUnit   PASS — 155 tests / 1082 assertions
repository Pint        WARN — 1 pre-existing style issue
```

Pint masih hanya menemukan `single_quote` pada `tests/Feature/SyncProgressUiTest.php`. Repository-wide Pint tetap advisory/`continue-on-error`; tidak ada style regression baru dari hardening generator.

### P0-02 yang masih RVR

Functional engine dan artifact safety sudah ditutup. Yang belum dapat disebut PASS tanpa template/data nyata adalah:

- template resmi/aktual sekolah untuk setiap document type aktif;
- vendor/penerima/NPWP/pajak/nomor pada transaksi sekolah nyata;
- visual fidelity Word/Excel/PDF: page break, print area, header/footer, row dinamis, ukuran halaman, dan hasil cetak;
- Paket nyata keenam kategori dengan seluruh template applicable;
- pembukaan hasil akhir memakai Microsoft Word/Excel/PDF viewer pada runtime sekolah target.

**P0-02 sekarang READY FOR OPERATOR TEMPLATE TEST.** Ini bukan berarti seluruh P0-02 real-template sudah selesai; official-template + real-tenant visual/output verification tetap RVR.

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
- Generated DOCX/XLSX/PDF wajib lolos output validation dan tidak boleh menyisakan placeholder unresolved.
- Resource SPJ harus berada dalam School + Fiscal Year + Fund Source aktif.
- Generic ARKAS Importer wajib melewati `administrator -> active-school -> active-year` sebelum query/write connection `school`.
- Raw Generic Importer pada Upsert/Incremental wajib mempunyai stable source key; raw tanpa stable key hanya boleh Full Refresh.
- ARKAS sync lock harus resource-scoped dengan school identity + source table + fiscal year.
- Existing staging/import row tidak boleh kehilangan first-created `created_at` ketika payload berubah.
- Histori import wajib membedakan read/write/new/changed/unchanged/removed.
- Auto-fill peserta KONSUMSI tetap `Employee.source_type = DAPODIK`; participant manual tetap diperbolehkan.

Command verification canonical:

```powershell
php artisan spj:verify
```

Saat database sekolah target tersedia, gunakan audit real-tenant sesuai `docs/P0_VERIFICATION_KIT.md` dan `docs/P0_01_SOURCE_AUDIT.md`.
