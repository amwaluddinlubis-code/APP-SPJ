# SPJ BOSP Web — Rencana Pengembangan

Terakhir diperbarui: **2026-09-10**

Roadmap ini memusatkan pekerjaan yang masih perlu diselesaikan pada branch `gui-standardization`. Detail historis migrasi ownership Detail Transaksi ↔ Paket SPJ tetap ada di `docs/URGENT_TRANSACTION_SPJ_MIGRATION.md`.

## Checkpoint functional utama

```text
P0-08  111de8c2781af6c8413661bcc512b651b09acc72
       test: prove ARKAS sync paths share tenant resource locks

P0-02  1a633f766179e709abdba015c6b8433330c74aad
       test: resolve template upload controller dependencies

P0-07  9721fb7f84e4a655389e90f315612380bda6b1be
       test: prove same-second backups stay distinct
       CI 34404104837 / job 102642898023
       163 tests / 1127 assertions

P0-01  b9611e3814380e3776bf8327c4df01ecb85e6eb9
       test: gate six-category E2E in SPJ critical suite
       CI 34405652728 / job 102647980543
       169 tests / 1407 assertions
```

`FUNCTIONAL PASS` pada roadmap ini berarti sudah dibuktikan oleh source + CI deterministic. `RVR` tetap membutuhkan real-value/runtime verification sebelum release target dinyatakan selesai.

# P0 — Core Release Safety

P0 harus cukup stabil sebelum aplikasi disebut aman menghasilkan SPJ pada data nyata. Correctness dan tenant safety selalu lebih prioritas daripada fitur baru atau polish GUI.

## P0 Verification Kit

**Status: functional CI PASS, real tenant RVR.**

Sudah tersedia:

- [x] `tests/Support/SpjScenarioFactory.php` untuk payload enam kategori;
- [x] PHPUnit suite `SPJ Critical`;
- [x] `php artisan spj:verify`;
- [x] `spj:audit-quarter --output=...`;
- [x] `spj:audit-diff before.json after.json`;
- [x] `--fail-on-regression`;
- [x] `.github/workflows/spj-critical.yml`;
- [x] source-key + tenant isolation + sync-mode Generic Importer regression;
- [x] preview reconciliation read-only + source-empty + schema-drift + queue activation regression;
- [x] tenant-scoped importer lock + timestamp + semantic import metrics regression;
- [x] document-generator real artifact + output-validator regression;
- [x] tenant maintenance backup/reset/restore regression;
- [x] primary-database deletion guard;
- [x] reset `sqlite_sequence` + WAL/SHM cleanup regression;
- [x] restore rollback + corrupt-backup rejection + same-second backup uniqueness regression;
- [x] six-category E2E deterministic melalui DRAFT -> READY -> NUMBERED -> preview/download -> FINAL;
- [x] real XLSX/PDF generation + preview side-effect regression pada keenam kategori;
- [x] CI canonical terbaru pada `b9611e3` PASS: **169 tests / 1407 assertions**;
- [x] docs-only changes tidak memicu verification run yang tidak perlu.

Masih RVR/TODO:

- [ ] first local `php artisan spj:verify` lengkap;
- [ ] first real-tenant verification pada database sekolah target;
- [ ] real-tenant audit before/after + `spj:audit-diff --fail-on-regression`;
- [ ] official-template generator verification;
- [ ] installed-runtime backup/reset/restore verification;
- [ ] evaluasi Bridge fetch limit `100000` pada tabel sangat besar;
- [ ] Bridge-side incremental delta fetch;
- [ ] bersihkan repository-wide Pint debt; `SyncProgressUiTest.php` masih mempunyai pre-existing `single_quote` warning.

---

## P0-01 — E2E enam kategori

**Status: FUNCTIONAL SIX-CATEGORY E2E PASS / REAL-TENANT RVR / READY FOR REAL-DATA VERIFICATION.**

Functional deterministic gate:

- [x] BARANG sampai FINAL + preview/download;
- [x] KONSUMSI sampai FINAL + preview/download;
- [x] PEMELIHARAAN sampai FINAL + preview/download;
- [x] JASA_LAINNYA sampai FINAL + preview/download;
- [x] SPPD sampai FINAL + preview/download;
- [x] HONOR_PEGAWAI sampai FINAL + preview/download;
- [x] seluruh kategori memakai gateway DRAFT, READY validation, numbering, preview/download, dan finalization runtime path aplikasi;
- [x] BARANG/KONSUMSI melewati endpoint penerimaan barang sebelum READY;
- [x] auxiliary document numbers applicable dibuat melalui numbering service canonical;
- [x] workbook XLSX nyata dibuka kembali dan PDF aktual diverifikasi;
- [x] preview/download tidak mengalokasikan document identity atau sequence baru;
- [x] Paket FINAL mempunyai snapshot, terkunci, dan seluruh dokumen NUMBERED selesai FINAL;
- [x] lifecycle audit dasar mencatat DRAFT/update/READY/numbering;
- [x] `tests/Feature/SpjSixCategoryE2eTest.php` masuk `SPJ Critical`;
- [x] CI `b9611e3` PASS **169 tests / 1407 assertions**.

Masih RVR sebelum P0-01 disebut real-data PASS:

- [ ] jalankan keenam kategori memakai transaksi ARKAS/BKU pada database sekolah target;
- [ ] verifikasi nilai bruto/pajak/neto, vendor/penerima/NPWP, serta metadata sumber pada transaksi nyata;
- [ ] simpan audit before/after real tenant;
- [ ] `spj:audit-diff --fail-on-regression` PASS pada real tenant;
- [ ] operator menjalankan alur installed runtime sampai FINAL dan membuka hasil dokumen aktual.

Perbaikan anomaly harus melalui workflow/source code, bukan SQL manual.

---

## P0-02 — Generator dokumen release-hardening

**Status: FUNCTIONAL GENERATOR PASS / OFFICIAL-TEMPLATE + REAL-TENANT RVR / READY FOR OPERATOR TEMPLATE TEST.**

Functional generator yang sudah ditutup:

- [x] DOCX/XLSX benar-benar digenerate dan dapat dibuka kembali oleh parser Office;
- [x] PDF individual/package divalidasi dengan signature `%PDF-` + `%%EOF`;
- [x] preview/download/package memakai render preflight;
- [x] unresolved placeholder memblokir output;
- [x] output final Office divalidasi dan workbook XLSX dibuka kembali;
- [x] Paket multi-template menghasilkan workbook multi-sheet dan PDF aktual;
- [x] generation tidak menambah `spj_documents` atau `document_number_sequences`;
- [x] nilai umum enam kategori canonical mempunyai placeholder sekolah, nomor, bruto, pajak, neto, kepala sekolah, dan bendahara;
- [x] upload template invalid tidak mengganti template aktif;
- [x] generator regression masuk `SPJ Critical`.

Masih RVR sebelum P0-02 disebut real-template PASS:

- [ ] template resmi/aktual untuk setiap document type aktif;
- [ ] vendor/penerima/NPWP/pajak/nomor pada transaksi nyata;
- [ ] visual/layout fidelity Word/Excel/PDF termasuk page break, print area, header/footer, tabel dinamis, hasil cetak;
- [ ] Paket nyata keenam kategori dengan seluruh template applicable;
- [ ] operator membuka output dengan Word/Excel/PDF viewer target.

---

## P0-03 — Numbering + lifecycle

**Status: FUNCTIONAL PASS.**

- [x] double-submit/idempotensi numbering;
- [x] nomor aktif tidak ganda;
- [x] NUMBERED/FINAL terkunci;
- [x] cancellation dengan alasan;
- [x] reopen/unlock/reissue;
- [x] histori nomor tidak hilang;
- [x] preview/download tidak mengalokasikan nomor.

---

## P0-04 — Authorization backend

**Status: FUNCTIONAL PASS untuk jalur yang sudah diregresikan.**

- [x] transaction mutation;
- [x] Paket mutation;
- [x] numbering/finalization;
- [x] cancellation/reissue/reopen;
- [x] template/configuration sensitif;
- [x] reconciliation guard;
- [x] reset/backup/restore tenant guard;
- [x] Generic ARKAS Importer administrator-only.

---

## P0-05 — Safe sync + reconciliation

**Status: FUNCTIONAL PASS untuk canonical transaction/source adapter.**

- [x] source unchanged tidak mengubah overlay;
- [x] source changed membuat reconciliation yang benar;
- [x] source item changes tercatat;
- [x] source missing tidak menghapus pekerjaan operator;
- [x] source returning menyambung ke identity lama;
- [x] manual overlay Paket aman;
- [x] NUMBERED/FINAL tidak dimutasi diam-diam;
- [x] before/after snapshot + field-level diff tersedia.

---

## P0-06 — Tenant/context isolation

**Status: FUNCTIONAL PASS untuk resource SPJ + Generic ARKAS Importer boundary.**

Boundary canonical:

```text
School + Fiscal Year + Fund Source
```

- [x] cross-school session/mutation ditolak sesuai role;
- [x] cross-year SPJ resource ditolak;
- [x] cross-fund-source resource ditolak;
- [x] previous/next Paket tidak keluar context;
- [x] forged SPJ resource ID tidak menjadi IDOR;
- [x] Generic Importer menjalankan `active-school` sebelum `active-year`;
- [x] forged importer profile lintas sekolah ditolak;
- [x] save mapping hanya menulis tenant aktif;
- [x] background job mengaktifkan tenant sebelum membaca model tenant.

---

## P0-07 — APP DATA / backup / reset / restore

**Status: FUNCTIONAL MAINTENANCE PASS / REAL-RUNTIME RVR / READY FOR OPERATOR RUNTIME TEST.**

Functional maintenance yang sudah ditutup:

- [x] reset menolak database tenant yang path-nya sama dengan database utama aplikasi;
- [x] reset hanya menghapus/rebuild tenant target;
- [x] file tenant `spj.sqlite`, `-wal`, dan `-shm` dibersihkan;
- [x] tenant diprovision ulang setelah reset;
- [x] `sqlite_sequence` bersih dan AUTOINCREMENT restart pada database baru;
- [x] backup melakukan WAL checkpoint sebelum snapshot;
- [x] source dan artifact backup melewati SQLite integrity check;
- [x] corrupt backup ditolak sebelum active tenant diganti;
- [x] restore memakai verified temporary copy;
- [x] restore membuat backup `SEBELUM_PEMULIHAN`;
- [x] post-restore integrity failure menjalankan rollback otomatis;
- [x] source backup tetap dipertahankan dan dilindungi dari retention selama restore;
- [x] retention backup SQLite-safe;
- [x] backup pada detik yang sama menghasilkan artifact path berbeda;
- [x] tenant tetap dapat switch sekolah setelah restore;
- [x] central test database dan tenant fixtures terisolasi sehingga maintenance tidak membocorkan lock antar-test;
- [x] `SchoolDatabaseMaintenanceHardeningTest` + `SchoolDatabaseResetServiceTest` masuk `SPJ Critical`;
- [x] CI `9721fb7` PASS **163 tests / 1127 assertions**.

Masih RVR sebelum P0-07 disebut runtime PASS:

- [ ] locking SQLite/WAL/SHM pada Windows installed runtime;
- [ ] backup/reset/restore pada file database sekolah nyata;
- [ ] restore database tenant berukuran produksi;
- [ ] alur operator nyata backup -> reset -> restore -> switch sekolah -> buka modul SPJ;
- [ ] recovery terhadap gangguan OS/power-loss bila masuk target release.

**Exit criteria P0-07 saat ini:** source-level maintenance safety sudah FUNCTIONAL PASS dan siap operator-runtime test; Windows/real-tenant runtime tetap RVR.

---

## P0-08 — Generic ARKAS Importer

**Status: IMPLEMENTED / SOURCE-KEY PASS / TENANT BOUNDARY PASS / SYNC-MODE PASS / RELEASE-GUARD PASS / HARDENING PASS / READY FOR OPERATOR TEST.**

Correctness/release guard yang sudah ditutup:

- [x] shared `ArkasSourceKeyResolver`;
- [x] configured key case-insensitive + canonical fallback;
- [x] administrator + active-school + active-year boundary;
- [x] Upsert / Incremental / Full Refresh deterministic;
- [x] preview full reconciliation read-only;
- [x] raw Upsert/Incremental tanpa stable source key ditolak;
- [x] source kosong mempunyai semantics aman;
- [x] schema drift blocking;
- [x] background tenant activation;
- [x] shared resource lock `school identity + source_table + fiscal_year_id`;
- [x] first-created `created_at` preservation;
- [x] semantic metrics `read/write/new/changed/unchanged/removed`;
- [x] CI checkpoint `111de8c` PASS `145 tests / 993 assertions`.

Scale/performance lanjutan:

- [ ] Bridge-side delta fetch untuk Incremental;
- [ ] evaluasi/paginasi di atas Bridge row limit `100000`.

---

# P1 — Feature Completeness & Operational Quality

## P1-01 — JASA_LAINNYA multi-penerima E2E

- [ ] gross/tax/net tiap penerima benar;
- [ ] kuitansi/dokumen per penerima bila diperlukan;
- [ ] preview/download/final multi-penerima;
- [ ] agregat gross/tax/net reconcile.

## P1-02 — PEMELIHARAAN bahan + upah

- [ ] linkage bahan/upah memakai active context;
- [ ] material berasal dari transaksi bahan;
- [ ] pekerja/upah berasal dari transaksi upah;
- [ ] RAB/SPK/kuitansi/A2 konsisten;
- [ ] source BKU tidak ditimpa/digabung permanen.

## P1-03 — SiPLah E2E

- [ ] source SiPLah authoritative;
- [ ] radio SiPLah/Non SiPLah benar pada desktop;
- [ ] marketplace order/invoice/payment reference benar;
- [ ] Surat Pesanan internal tidak diwajibkan untuk SiPLah;
- [ ] output SiPLah benar.

## P1-04 — Browser QA Paket SPJ — desktop/laptop

- [ ] category/payment controls benar;
- [ ] layout Data Umum stabil;
- [ ] tab Rincian Pajak benar;
- [ ] nomor otomatis read-only;
- [ ] tabel/pagination compact;
- [ ] previous/next Paket sesuai context.

Mobile/responsive QA penuh bukan blocker release saat ini.

## P1-05 — Audit trail operasional

Pastikan aksi sensitif seperti draft/update/ready/numbering/cancel/reissue/final/reopen/reconcile/reset/restore mempunyai actor, waktu, tenant context, entity, action, dan keterangan.

## P1-06 — Employee identity / participant roster

Kontrak canonical tetap:

```text
Auto-fill KONSUMSI = Employee.source_type DAPODIK
Participant manual  = diperbolehkan
```

- [ ] UI tidak memperluas auto-fill ke semua Employee aktif;
- [ ] normalized name saja tidak boleh silent-merge orang berbeda;
- [ ] NIP/NUPTK dipertahankan;
- [ ] regression nama sama + identifier berbeda.

---

# P2 — Product Polish & Maintainability

- field-level validation UX;
- GUI/compatibility cleanup;
- repository Pint/style cleanup;
- icon/action consistency;
- performance profiling;
- Bridge repository hygiene (`src/**/bin`, `src/**/obj`);
- report foundation.

---

# Future Development — Mobile / Responsive

`docs/MOBILE_VISUAL_QA_TODO.md` tetap backlog pengembangan masa depan, bukan release blocker operator laptop/desktop saat ini.

---

# P3 — Laporan resmi / ekspansi

K7A, K7, K8, SPTJM, K7B, K7C dan format resmi lain baru boleh disebut compliant setelah template/aturan resmi project dikonfirmasi dan core release safety stabil.

---

## Urutan kerja efektif dari checkpoint sekarang

```text
1. P0-01 real-tenant/data verification + audit diff
2. P0-02 official-template + real-tenant operator verification
3. P0-07 installed-runtime + real-tenant operator verification
4. P0-08 real-tenant operator verification
5. P1 blocker yang ditemukan dari kandidat nyata
6. P2 cleanup / performance / GUI polish
```

Bridge-side incremental delta fetch dan scale handling `>100000` row tetap optimasi/hardening lanjutan setelah operator-test correctness P0-08 selesai.

---

## Definition of Done release candidate

Release candidate belum selesai sampai:

- seluruh P0 mendapat runtime checkpoint PASS atau keputusan RVR/out-of-scope eksplisit;
- P0-08 mempertahankan source-key + tenant + sync-mode + release-guard + hardening PASS pada real-tenant verification;
- keenam kategori lulus E2E nyata sampai FINAL + preview/download;
- generator dokumen tervalidasi pada template resmi sekolah;
- numbering/lifecycle/revision aman;
- safe sync tidak merusak overlay/final document;
- authorization sensitif diuji;
- tenant maintenance pada `SPJ_DATA_PATH` lulus installed-runtime verification;
- critical build/tests berhasil;
- gap P1 yang benar-benar dibutuhkan sekolah target ditutup.

Baca bersama:

```text
docs/P0_VERIFICATION_KIT.md
docs/P0_01_SOURCE_AUDIT.md
docs/CURRENT_PROGRESS.md
docs/ARKAS_IMPORTER.md
docs/SPJ_DESIGN_DECISIONS.md
docs/ARCHITECTURE_COMPLETE.md
docs/GUI_STANDARDIZATION.md
```