# SPJ BOSP Web — Rencana Pengembangan

Terakhir diperbarui: **2026-09-10**

Roadmap ini memusatkan pekerjaan yang masih perlu diselesaikan pada branch `gui-standardization`. Detail historis migrasi ownership Detail Transaksi ↔ Paket SPJ tetap ada di `docs/URGENT_TRANSACTION_SPJ_MIGRATION.md`.

Baseline correctness P0-08 terbaru:

```text
50794b4872d3be273ee73fbaccdd438fcca4569d
test: prove ARKAS upsert preserves absent rows
```

Checkpoint tersebut mencakup source-key, tenant boundary, dan regression tiga mode sinkronisasi Generic ARKAS Importer.

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
- [x] source-key Generic Importer regression;
- [x] tenant activation/isolation + cross-school/cross-year regression;
- [x] Upsert / Incremental / Full Refresh regression;
- [x] CI pada `50794b4` PASS: `134 tests / 896 assertions`;
- [x] docs-only changes tidak memicu verification run yang tidak perlu.

Masih RVR/TODO:

- [ ] first local `php artisan spj:verify` lengkap;
- [ ] first real-tenant verification pada database sekolah target;
- [ ] preview full reconciliation read-only regression;
- [ ] raw stable-key policy regression;
- [ ] source kosong regression;
- [ ] schema drift blocking regression;
- [ ] queue/background tenant activation regression;
- [ ] tenant-scoped lock / timestamp / import-metrics hardening;
- [ ] bersihkan repository-wide Pint debt; `SyncProgressUiTest.php` masih mempunyai pre-existing `single_quote` warning.

---

## P0-01 — E2E enam kategori berbasis database nyata

**Status: RVR — verification kit siap, menunggu database nyata.**

Target:

- [ ] BARANG sampai FINAL + preview/download;
- [ ] KONSUMSI sampai FINAL + preview/download;
- [ ] PEMELIHARAAN sampai FINAL + preview/download;
- [ ] JASA_LAINNYA sampai FINAL + preview/download;
- [ ] SPPD sampai FINAL + preview/download;
- [ ] HONOR_PEGAWAI sampai FINAL + preview/download;
- [ ] simpan audit before/after;
- [ ] `spj:audit-diff --fail-on-regression` PASS.

Perbaikan anomaly harus melalui workflow/source code, bukan SQL manual.

---

## P0-02 — Generator dokumen release-hardening

**Status: RVR/OPEN — perlu template/output nyata.**

- [ ] Word/Excel/PDF dapat dihasilkan;
- [ ] preview/download bebas side effect numbering;
- [ ] tidak ada placeholder unresolved;
- [ ] identitas sekolah/vendor/penerima/pajak/nomor benar;
- [ ] output Paket multi-template benar;
- [ ] error template manusiawi;
- [ ] output dapat dibuka secara nyata.

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
- [x] Generic ARKAS Importer tetap administrator-only.

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
- [x] forged profile lintas sekolah pada preview/sync ditolak;
- [x] save mapping hanya menulis tenant aktif;
- [x] stale fiscal-year context lintas tenant ditolak.

---

## P0-07 — APP DATA / backup / reset / restore nyata

**Status: RVR — memerlukan runtime tenant nyata.**

- [ ] database utama tidak ikut terhapus;
- [ ] tenant file benar;
- [ ] WAL/SHM tidak meninggalkan state rusak;
- [ ] `sqlite_sequence` kembali bersih setelah reset;
- [ ] backup dapat direstore;
- [ ] restore mempertahankan data yang dibackup;
- [ ] switch sekolah setelah maintenance tetap aman.

---

## P0-08 — Generic ARKAS Importer

**Status: IMPLEMENTED / SOURCE-KEY PASS / TENANT BOUNDARY PASS / SYNC-MODE PASS / HARDENING OPEN.**

Fondasi:

```text
Bridge
-> ArkasStagingService
-> ArkasImportProfile / arkas_import_rows
-> reconciliation preview
-> ArkasDomainAdapter
-> target domain / raw snapshot
```

Correctness yang sudah ditutup:

- [x] shared `ArkasSourceKeyResolver`;
- [x] configured key case-insensitive + canonical fallback;
- [x] non-empty Generic Import runtime path;
- [x] administrator + active-school + active-year boundary;
- [x] GET/save mapping/preview/sync cross-tenant regression;
- [x] **Upsert deterministic:** update stable key, insert key baru, preserve row yang absen dari snapshot berikutnya;
- [x] **Incremental deterministic:** hanya timestamp `> last_synced_at` yang diproses;
- [x] **Full Refresh scoped:** active profile/year staging diganti, active fiscal-year domain diganti, fiscal year/profile lain tetap utuh;
- [x] `tests/Feature/ArkasGenericImportSyncModeTest.php` masuk `SPJ Critical`;
- [x] CI critical PASS `134 tests / 896 assertions` pada `50794b4`.

Regression/checklist berikutnya:

- [ ] preview full reconciliation dibuktikan tidak menulis domain;
- [ ] raw profile mempunyai stable-key policy eksplisit;
- [ ] source kosong aman dan hasil mode jelas;
- [ ] schema drift memblokir jika source key/mandatory mapping hilang;
- [ ] background queue mengaktifkan tenant yang benar sebelum query model tenant.

Hardening setelah regression behavior:

- [ ] tenant-scoped staging/import lock;
- [ ] `created_at` existing row mempertahankan first-created semantics;
- [ ] histori import membedakan read/new/changed/unchanged/removed;
- [ ] Bridge-side delta fetch untuk incremental sebagai optimasi setelah correctness selesai.

**Exit criteria P0-08:** source-key, tenant isolation, dan tiga mode sinkronisasi utama sudah PASS. Status `READY FOR OPERATOR TEST` baru diberikan setelah preview/raw/source-empty/schema-drift/queue mempunyai kontrak regression release-critical yang memadai.

---

# P1 — Feature Completeness & Operational Quality

## P1-01 — JASA_LAINNYA multi-penerima E2E

- [ ] gross/tax/net tiap penerima benar;
- [ ] kuitansi/dokumen per penerima bila diperlukan;
- [ ] preview/download/final multi-penerima;
- [ ] agregat gross/tax/net tetap reconcile.

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
1. P0-08 preview read-only + raw stable-key policy
2. P0-08 source kosong + schema drift regression
3. P0-08 queue/background tenant regression
4. P0-08 tenant-scoped lock / created_at / import metrics
5. P0-01 real-data E2E enam kategori
6. P0-02 generator dokumen nyata
7. P0-07 APP DATA nyata
8. P1 blocker yang ditemukan dari kandidat nyata
9. P2 cleanup / performance / GUI polish
```

Tidak menambah fitur baru sebelum regression correctness P0-08 yang tersisa selesai, kecuali perubahan tersebut diperlukan untuk menutup blocker release.

---

## Definition of Done release candidate

Release candidate belum selesai sampai:

- seluruh P0 mendapat runtime checkpoint PASS atau keputusan RVR/out-of-scope eksplisit;
- P0-08 mempertahankan source-key + tenant + sync-mode PASS dan menutup preview/raw/source-empty/schema-drift/queue behavior release-critical;
- keenam kategori lulus E2E nyata sampai FINAL + preview/download;
- generator dokumen nyata tervalidasi;
- numbering/lifecycle/revision aman;
- safe sync tidak merusak overlay/final document;
- authorization sensitif diuji;
- tenant operation pada `SPJ_DATA_PATH` diuji;
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
