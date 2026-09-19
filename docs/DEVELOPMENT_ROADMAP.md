# SPJ BOSP Web — Rencana Pengembangan

Terakhir diperbarui: **2026-09-19**

Roadmap ini memuat urutan pekerjaan aktif pada branch `arkas-raw-mirror`. Status/evidence rinci berada di `CURRENT_PROGRESS.md`; code gate canonical berada di `P0_VERIFICATION_KIT.md`; keputusan bisnis permanen berada di `SPJ_DESIGN_DECISIONS.md`.

Prinsip kerja aktif:

```text
integration gate hijau -> operator flow -> temukan bug nyata -> perbaiki -> regression bila perlu
```

Jangan menambah smoke/regression hanya untuk memperbesar coverage setelah contract inti cukup dibuktikan. Exception: mutation boundary baru wajib mempunyai negative authorization/tenant regression bila permission tidak dapat dibuktikan tanpa test tersebut.

---

# P0 — Core Release Safety

## P0-00 — Current HEAD integration + dependency platform + Livewire authorization hardening

**Status: COMPLETE / CI #486 historical GREEN / current boundary regression PASS.**

Phase 1 mengaudit seluruh 25 component `app/Livewire/`. Phase 2 menutup mutation authorization boundary. Integration repair #478–#480 mengembalikan functional baseline ke hijau, kemudian Laravel 13/TALL migration dan dependency-platform repair #483–#486 menghasilkan canonical green gate baru pada PHP 8.3.

Phase 2 commits:

```text
3c7be408f5a93795a597878b79f975373df24412
fix: harden Livewire mutation authorization

701c73644b7dcf9d8aa710a842f28b2dad9a62d5
test: gate Livewire mutation authorization as critical
```

Historical integration repair:

```text
b61cdc621539cb6fc62dd17efc22da16a9c2a14c
test: close SPJ critical integration regressions

887d0219142d634e6a85b6672d3bffb02b5b1584
test: align description UI contract with service delegation
```

Dependency-platform repair:

```text
7b5615c4b98222f145a3ba0e18b409abb2b1e20d
fix: constrain dependency resolution to PHP 8.3

d3c786d841d431c4d78cf2441f9a1e358115afa6
fix: keep dependency lock compatible with PHP 8.3

ba8fa0b2ea307406a7c7be2cb3dc6fa6e7bce7c4
ci: enforce deterministic PHP 8.3 dependency gate
```

Checklist P0-00:

- [x] audit seluruh mutation/read-only Livewire boundary;
- [x] `UserManagement` ADMIN-only;
- [x] `SchoolMaster::createSchool` ADMIN-only;
- [x] `DatabaseMaintenance::run` ADMIN-only;
- [x] `DatabaseResetForm::resetDatabase` ADMIN + active-school + confirmation;
- [x] `DatabaseSchoolList::{activate,migrate}` ADMIN-only;
- [x] `DocumentStorageSettings::save` OPERATOR/ADMIN sebelum reuse;
- [x] negative regression OPERATOR/VIEWER;
- [x] lifecycle SPJ, numbering, safe sync, dan tenant ownership tidak diubah;
- [x] tutup dua regression SPJ Critical #478;
- [x] tutup stale full-feature source-contract assertion yang baru terlihat di #479;
- [x] migrasi Laravel 13 + pure TALL tetap melewati full deterministic gate;
- [x] root Composer platform floor dikunci ke PHP 8.3;
- [x] `composer.lock` kompatibel dengan PHP 8.3;
- [x] Composer validate + locked platform check PASS;
- [x] deterministic `composer install` PASS;
- [x] Repository Pint PASS pada gate #486;
- [x] SPJ Critical PASS;
- [x] Full Unit PASS;
- [x] Full Feature PASS;
- [x] promote green code gate baru.
- [x] `TransactionDetailWorkspace` fresh/legacy lookup scoped oleh fiscal year + fund source;
- [x] `saveDescriptions()` dan `resolveReconciliation()` memiliki action-level OPERATOR/ADMIN guard;
- [x] regression cross-year fresh lookup dan negative VIEWER mutation;
- [x] compatibility verification 2025 dan fresh-data verification 2026 pada tenant nyata;
- [x] audit read-only seluruh kuartal scope 2025/2026 tanpa critical atau financial mismatch.
- [x] RKAS fallback fresh di-scope oleh fiscal year + fund source;
- [x] cross-fund source-key dan cross-context RKAS collision regression;
- [x] positive ADMIN/OPERATOR dan negative VIEWER untuk detail mutation/reconciliation;
- [x] NUMBERED carve-out dan FINAL lock regression pada Livewire detail;
- [x] detail mutation berhasil mencatat operational audit setelah perubahan valid.

Evidence CI #486:

```text
HEAD                  : ba8fa0b2ea307406a7c7be2cb3dc6fa6e7bce7c4
RUN                   : #486 / 34853857969 / SUCCESS
COMPOSER VALIDATE     : PASS
LOCKED PLATFORM CHECK : PASS / PHP 8.3
COMPOSER INSTALL      : PASS
REPOSITORY PINT       : PASS
FRONTEND BUILD        : PASS
BLADE COMPILE         : PASS
SPJ CRITICAL          : PASS
FULL UNIT             : PASS
FULL FEATURE          : PASS
```

P0-00 code/dependency integration gate bukan lagi blocker. Browser/runtime tetap RVR karena deterministic CI tidak menggantikan operator/browser evidence.

Panduan detail: `LIVEWIRE_MIGRATION_PLAN.md` dan `P0_VERIFICATION_KIT.md`.

---

## V2-D — Canonical read-path migration

**Status: D1 FUNCTIONAL PASS / D2 RUNTIME PASS / D3 RUNTIME PASS / REPORT-TAX-PERIOD RUNTIME PASS / PRODUCTION CUTOVER BLOCKED.**

- [x] V2-C3 canonical migration semantic gate pada fresh isolated clone;
- [x] D1 shadow parity fresh ↔ V2 dengan fail-closed overlay drift regression;
- [x] implement read-only `SpjV2CanonicalReadService` tanpa mengalihkan GUI;
- [x] dukung membership hash canonical dan legacy `source_key` untuk mapping DETERMINISTIC;
- [x] jalankan `V2DCanonicalReadAdapterTest` pada clone lokal: 3 test / 225 assertions PASS;
- [x] implement `SpjV2PackageDocumentParityService` + fail-closed wrong-link regression + synthetic FINAL continuity;
- [x] jalankan `V2DPackageDocumentParityTest` pada isolated clone: 3 test / 42 assertions PASS;
- [x] implement canonical tax breakdown + raw RKAS activity-code resolution;
- [x] implement `SpjV2WorkflowParityService` + report/tax/period fail-closed regression;
- [x] jalankan focused V2-C → V2-D suite pada isolated clone: 16 test / 378 assertions PASS;
- [x] implement fail-safe read-path selector default `legacy`, tanpa hard-code `source_id`;
- [x] jalankan `V2DReadPathSelectorTest`: 7 test / 22 assertions PASS; Pint + diff-check bersih;
- [x] implement consumer read-only pertama: financial summary tab Laporan di belakang selector;
- [x] jalankan focused D4 consumer gate: 16 test / 354 assertions PASS; Pint + diff-check bersih;
- [x] tutup authorization + active-context runtime evidence consumer read-only pertama;
- [x] rollback strategy documented + tested pada selector/consumer fail-safe;
- [x] audit akar live legacy `ActiveSpjContext` vs canonical V2 mismatch;
- [x] implement read-only effective-context compatibility audit/resolver berbasis provenance;
- [x] jalankan `V2DEffectiveContextCompatibilityTest` + review exact 67-Paket/context inventory: 67/67 stale legacy FY, 0 unsafe, 0 fund-source mismatch;
- [x] implement compatibility read membership resolver untuk Paket/list berbasis effective-context/provenance tanpa mutation legacy;
- [x] jalankan Step 4 membership gate: 19 test / 216 assertions PASS; Pint + diff-check bersih;
- [x] implement read-only document action compatibility untuk Preview/Download dengan effective-year in-memory normalization;
- [x] jalankan Step 5 document read-context gate: 27 test / 353 assertions PASS; Pint + diff-check bersih;
- [x] implement dedicated Paket workspace read-only compatibility terpisah dari mutation authorization;
- [x] jalankan Step 6 read-only workspace gate: 25 test / 351 assertions PASS; Pint + diff-check bersih;
- [x] implement Paket list membership + Paket summary metrics cutover berbasis effective context;
- [x] jalankan Step 7 Paket-list gate: 29 test / 583 assertions PASS; Pint + diff-check bersih;
- [x] implement atomic Laporan Paket-table + financial-summary cutover berbasis effective membership + canonical parity;
- [x] jalankan Step 8 atomic report gate: 42 test / 711 assertions PASS; Pint + diff-check bersih;
- [x] implement effective-context read boundary untuk Honor Pegawai + Jasa Lainnya select/compose/export;
- [x] jalankan Step 9 Honor/Jasa effective-context gate: 20 test / 241 assertions PASS; Pint + diff-check bersih;
- [x] implement fail-closed effective-context cutover untuk Pajak + safe source-key detail navigation;
- [x] jalankan Step 10 Pajak gate: 33 test / 199 assertions PASS; Pint + diff-check bersih;
- [x] audit Monitoring post-Step-10: read cutover DEFERRED karena tab berbagi boundary dengan Bulk Final, numbering, Checklist/Persiapan, dan fiscal-period mutation;
- [x] implement Step 11A `SpjV2MutationContextService` + Checklist/READY effective-context authorization, fail-closed pada reconciliation/source drift;
- [x] jalankan Step 11A gate: focused 8 test / 90 assertions PASS; full 42 test / 556 assertions PASS; Pint + build + diff-check bersih;
- [x] implement Step 11B package/operator-overlay write boundary + dedicated DRAFT/READY compatibility editor tanpa fiscal-year rewrite;
- [ ] jalankan `V2DPackageOverlayWriteCutoverTest` + Step 11A/read-context + package manual/category/transaction-boundary/NUMBERED regressions + Pint/view-cache/build/diff;
- [ ] setelah Step 11B PASS, audit lifecycle-specific gate berikutnya; numbering, settlement, Monitoring, bulk-final, dan period-close tetap legacy;
- [ ] definisikan overlay write-through/compatibility sebelum consumer mutation-heavy membaca V2 overlay;
- [ ] controlled production read-path cutover + rollback evidence.

Jangan memindahkan controller/Livewire/write-path ke V2 sebelum D3 relation parity, report/tax/period parity, dan rollback gate lulus.

---

## P0-01 — Six-category E2E

**Status: FUNCTIONAL PASS / REAL-DATA BASELINE VERIFIED / GENERATED-DOCUMENT QA ACTIVE / INSTALLED-RUNTIME DEFERRED.**

Sudah dibuktikan:

- [x] six-category functional lifecycle;
- [x] read-only real-data audit 2026/TW2 pada 66 READY package;
- [x] fund-source partition check pada scope yang diuji;
- [x] real-data numbering preflight read-only;
- [x] isolated first numbering;
- [x] individual cancel + reserved sequence;
- [x] isolated tail rollback;
- [x] source transaction/item immutable pada baseline;
- [x] current code gate hijau.

Pekerjaan aktif:

- [ ] generate output nyata untuk kategori yang tersedia;
- [ ] koreksi hanya bug/operator overlay yang mempunyai evidence;
- [ ] jangan fabrikasi SPPD 2026 karena data nyata tidak tersedia.

---

## P0-02 — Generator dokumen + template

**Status: FUNCTIONAL CODE GATE PASS / REAL-DATA OUTPUT QA ACTIVE / OFFICIAL-TEMPLATE VISUAL RVR.**

Functional coverage yang sudah hijau:

- [x] Download Template XLSX individu true single-sheet;
- [x] source/master tersimpan tetap utuh;
- [x] Cek Placeholder read-only memakai resolver generator canonical;
- [x] master template terbaru dirakit dari XLSX canonical aktif;
- [x] update XLSX individu mengganti source document type berikutnya tanpa mutasi master historis;
- [x] master parsial ditolak;
- [x] canonical XLSX HTML preview memilih worksheet yang benar;
- [x] current canonical HEAD memperoleh green code gate #486;
- [x] validator/template load terbaru tercakup full regression;
- [x] PDF/report writer path tercakup full Unit/Feature gate.

Masih RVR:

- [ ] buka satu individual template nyata di Microsoft Excel/LibreOffice;
- [ ] buka `MASTER-TEMPLATE-SPJ-TERBARU.xlsx`;
- [ ] periksa drawing, formula/reference, defined name, print area, page break, header/footer, repair prompt;
- [ ] generate dan inspeksi XLSX/PDF nyata per kategori tersedia;
- [ ] verifikasi field identitas, bukti, tanggal, uraian, penerima/vendor, nominal, pajak, dan nomor dokumen turunan.

Panduan canonical: `TEMPLATE_MASTER_WORKFLOW.md` dan `DOCUMENT_TEMPLATE_PLACEHOLDERS.md`.

---

## P0-03 — Numbering + registry + lifecycle

**Status: FUNCTIONAL PASS / REGISTRY CANONICAL.**

Source of truth executable:

```text
app/Services/SpjNumberingDocumentRegistry.php
```

Sudah dibuktikan:

- [x] first numbering;
- [x] individual cancel mempertahankan `CANCELLED` permanen;
- [x] nomor berikutnya memakai sequence berikutnya;
- [x] tail rollback melepaskan tail dan membangun ulang checkpoint;
- [x] released sequence dapat dipakai kembali setelah valid tail rollback;
- [x] fund-source scoped sequence;
- [x] quarter rollback dependency regression;
- [x] `{TW}` tidak memaksakan prefix literal `TW.`;
- [x] registry dipakai consumer numbering utama;
- [x] NUMBERED tetap hanya mengizinkan `payment_description` + `item_description` correction carve-out;
- [x] FINAL tetap locked;
- [x] regression stale #478 ditutup tanpa mengubah contract.

Tidak menambah smoke test numbering tanpa bug/operator requirement baru.

---

## P0-04 — Authorization

**Status: HTTP/ROUTE PASS / LIVEWIRE MUTATION HARDENING COMPLETE / DETAIL BOUNDARY REGRESSION PASS / CODE GATE HISTORICAL GREEN / BROWSER RVR.**

Definition of Done Phase 2:

- [x] action-level authorization seluruh mutation sensitif Phase 1;
- [x] ADMIN/OPERATOR/VIEWER regression sesuai matrix permission;
- [x] tenant/context logic existing tidak dipindahkan ke UI;
- [x] no privilege widening pada mutation Livewire yang diuji;
- [x] overall repository code gate hijau #486.

Rule untuk migrasi berikutnya: route visibility/middleware GET tidak cukup sebagai bukti; mutation Livewire harus authorize pada request action melalui action guard, policy, atau persistent mechanism yang benar-benar berlaku.

---

## P0-05 — Safe sync + reconciliation

**Status: FUNCTIONAL PASS / REAL-DATA RECONCILIATION ACTIVE.**

Kerjakan hanya ketika ditemukan mismatch source/overlay nyata:

- [ ] source missing/returning identity;
- [ ] overlay preservation;
- [ ] NUMBERED/FINAL protection pada kasus nyata;
- [ ] reconciliation flag/operator resolution.

---

## P0-06 — Tenant/context isolation

**Status: FUNCTIONAL PASS / CONTEXT ISOLATION REGRESSION PASS.**

Boundary canonical:

```text
School + Fiscal Year + Fund Source
```

Phase 2 Livewire menjaga boundary ini. Fresh RKAS fallback juga tidak memakai
`source_rapbs_id` global; query mengikat tahun dan sumber dana transaksi. Tidak ada
test tambahan aktif tanpa bug/boundary baru.

---

## P0-07 — APP DATA / backup / reset / restore

**Status: FUNCTIONAL PASS / LIVEWIRE RESET ROLE HARDENING COMPLETE / INSTALLED-RUNTIME DEFERRED.**

`DatabaseResetForm` memerlukan ADMIN, active-school match, dan exact confirmation sebelum reset service dipanggil.

---

## P0-08 — Generic ARKAS Importer

**Status: MULTI-YEAR CATCH-UP IMPLEMENTED / REAL-DATA CONTRACT VERIFIED / OVERLAY-PACKAGE CONTINUITY REGRESSION PASS / SAFE-SYNC ATOMICITY NEXT.**

Koreksi raw-mirror yang didorong audit database nyata:

- [x] kunci regression contract untuk PK row ARKAS, grouped transaction, dan fund-source isolation;
- [x] raw mirror memakai primary key asli tabel, termasuk composite PK;
- [x] fresh projection membatasi approved active budget dan mengelompokkan BELANJA per `NO_BUKTI`;
- [x] jalankan focused Laravel regression pada head koreksi;
- [x] compatibility grouped `source_key` ke legacy workspace/overlay;
- [x] gross/tax/net grouped transaction mengikuti seluruh item dan PBT source;
- [x] statistik count/gross/tax/net halaman transaksi memakai dataset fresh + filter yang sama.
- [x] langkah 6B: setelah raw mirror, projection catch-up mengiterasi semua Fiscal Year + Fund Source valid pada tenant;
- [x] regression multi-year/fund-source untuk 2025+2026, isolation, dan idempotensi;
- [x] migration/integrity verification tenant: fresh migration applied, SQLite integrity `ok`, foreign-key violations 0;
- [x] audit read-only 2025 seluruh scope bertransaksi dan 2026 seluruh kuartal: critical 0, financial mismatches 0;
- [x] overlay continuity: payment/item/operator fields dan grouped identity tetap tersambung setelah sync/projection;
- [x] package continuity DRAFT/NUMBERED/FINAL, nomor, timestamp, snapshot, source disappear/return;
- [x] item mapping tetap berdasarkan `ID_KAS_UMUM` saat urutan raw source berubah;
- [x] repeated projection tidak menambah fresh transaction/item atau Paket legacy;
- [x] membership-change regression mengungkap fallback unik berbasis `NO_BUKTI` dan mempertahankan transaction/package lama;
- [x] safe-sync atomic per raw source table; failure mempertahankan snapshot lama dan menghentikan projection lanjutan;
- [x] approved budget hilang/kembali memproses fresh `SOURCE_MISSING`/`ACTIVE` tanpa delete atau duplicate;
- [x] membership change konservatif: old legacy transaction/package dipertahankan, event `SOURCE_ITEM_CHANGED` dicatat, dan event identik tidak diulang;
- [x] NUMBERED/FINAL tidak auto-remap atau mengubah nomor/snapshot pada membership change;
- [ ] desain safe-sync/performance lanjutan untuk kasus ambiguous membership dan Bridge paging (Langkah 10);

### V2-A2 — Tenant discovery dan source identity preflight

Status: **READ-ONLY PREFLIGHT COMPLETE / ISOLATED REHEARSAL COMPLETE**.

- [x] previous-project external/orphan fixture `10208183` diinventarisasi;
- [x] mismatch central NPSN `10260756` vs path `10260786` diklasifikasikan sebagai `DATABASE_REGISTRY_MISMATCH`;
- [x] 291 legacy transaction V2-A dipetakan ke full raw `kas_umum`;
- [x] 56 raw mirror table identities diklasifikasikan;
- [x] stable identity registry contract dan additive V2-B schema proposal dikunci;
- [ ] optional audit/source recovery untuk fixture `10208183` (bukan blocker tenant current-project);
- [ ] execute V2-B migration pada isolated/test database;
- [ ] promote V2-B ke real tenants;

Evidence canonical: `docs/V2_A2_TENANT_DISCOVERY_SOURCE_IDENTITY_PREFLIGHT.md`.

### V2-C — Full legacy migration rehearsal

Status: **ISOLATED CLONE FUNCTIONAL PASS / PRODUCTION MIGRATION BLOCKED**.

- [x] Tenant A 291 legacy transaction dan 699 item diproses pada fresh clone;
- [x] V2-C2 menghasilkan 187 canonical transaction, 431 unique source links,
  dan 291 legacy provenance mappings (104 many-to-one duplicate provenance);
- [x] EXACT 269 dan DETERMINISTIC 22 tanpa rewrite legacy `source_key`;
- [x] package/document NUMBERED continuity, overlay continuity, idempotency,
  integrity, FK, orphan, dan synthetic FINAL regression lulus;
- [x] semantic context-isolation gate PASS pada fresh V2-C3 clone;
- [x] external/orphan fixture 46 transaction diaudit sebagai `SOURCE_MISSING` tanpa fabrikasi;
- [x] command/service/report V2-C tersedia;
- [x] provenance classification eksplisit: ACTIVE_CANONICAL 187 dan
  LEGACY_DUPLICATE 104; metric many-to-one 104 dilaporkan terpisah;
- [x] transaction overlay conflict gate dan item overlay conflict gate fail-closed;
- [x] fresh execute #1/#2 idempotency mencakup counts, errors, classification,
  conflict count, dan protected manifest;
- [ ] source evidence/ownership fixture `10208183` (opsional, bukan gate current-project);
- [ ] production migration dan legacy retirement;
- [x] V2-D1 shadow read parity rehearsal and fail-closed drift regression.

Evidence: `docs/V2_C_TWO_TENANT_MIGRATION_REHEARSAL.md`.

V2-C3 semantic gate PASS pada fresh Tenant A clone. V2-D1 shadow parity juga PASS
pada isolated rehearsal; canonical read adapter, downstream workflow parity, dan
production read-path cutover tetap belum dilakukan.

Importer mapping → preview → sync tidak menjadi target migrasi Livewire opportunistic. Jangan lanjut ke operator-flow promotion sebelum regression runtime dan compatibility boundary di atas ditutup.

---

# P1 — Real Data, Output, dan Operational Quality

**P1 sekarang menjadi fokus produk utama karena P0-00 code/dependency integration gate sudah hijau.**

## P1-01 — Generated-document real-data QA

**Prioritas tertinggi berikutnya.** Untuk setiap kategori nyata yang tersedia, pilih Paket representatif dan periksa hasil dokumen:

- [ ] BARANG;
- [ ] KONSUMSI;
- [ ] PEMELIHARAAN;
- [ ] JASA_LAINNYA;
- [ ] HONOR_PEGAWAI;
- [ ] SiPLah bila Paket nyata applicable.

SPPD 2026 tidak dipaksakan karena tidak ada real data.

Bug yang langsung diperbaiki bila ditemukan: data kosong/salah, tanggal/nomor tidak sinkron, placeholder gagal, penerima/vendor salah, total/pajak salah, nomor turunan salah, XLSX/PDF gagal dibuka, clipping/page-break/header-footer bermasalah.

## P1-02 — JASA_LAINNYA multi-penerima output

- [ ] gross/tax/net tiap penerima;
- [ ] aggregate reconciliation;
- [ ] dokumen per penerima bila applicable;
- [ ] preview/download/final dengan data nyata.

## P1-03 — PEMELIHARAAN bahan + upah output

- [ ] material dari transaksi bahan;
- [ ] pekerja/upah dari transaksi upah;
- [ ] linkage active context;
- [ ] RAB/SPK/kuitansi/A2 konsisten;
- [ ] source BKU tetap immutable.

## P1-04 — SiPLah generated-document E2E

Core procurement policy functional PASS. Tersisa:

- [ ] generated-document nyata;
- [ ] official-template output;
- [ ] browser reload/category/payment-method consistency pada operator flow.

## P1-05 — Browser QA desktop/laptop

**Status: SOURCE READINESS / RUNTIME RVR.** Checklist: `GUI_RUNTIME_QA.md`.

Viewport prioritas:

- [ ] 1366×768;
- [ ] 1440×900;
- [ ] 1920×1080.

Fokus: sidebar, Paket SPJ, SPA tab navigation, repeated `Livewire.navigate`, modal preview setelah body swap, dropdown, pagination, filter URL state, preview/download, overflow/clipping.

## P1-06 — Official-template visual/output QA

- [ ] individual template tidak meminta repair;
- [ ] master template terbaru tidak meminta repair;
- [ ] template resmi/aktual sekolah;
- [ ] visual fidelity Word/Excel/PDF;
- [ ] print area/page break/header-footer;
- [ ] hasil cetak/print preview target.

## P1-07 — Operational audit E2E

Audit flow nyata harus mampu menjelaskan actor, time, tenant context, entity, action, dan description untuk action sensitif. Mutation Livewire Database Manager yang dipertahankan harus tetap memakai `OperationalAuditService`.

## P1-08 — Employee identity + participant roster real-data

Functional identity baseline PASS. Tersisa bila muncul pada operator flow:

- [ ] duplicate/ambiguous identity nyata;
- [ ] auto-fill roster menyatu;
- [ ] participant manual UX;
- [ ] same-name/different-identifier review.

## P1-09 — Mobile/tablet minimum usability

**Status: SOURCE READINESS / RUNTIME RVR / NON-BLOCKER untuk target desktop-laptop.**

- [ ] 375×812;
- [ ] 768×1024;
- [ ] 1024×768.

Kerjakan setelah desktop operator flow stabil atau bila ada bug mobile yang menghambat penggunaan nyata.

---

# P2 — Polish & Maintainability

Setelah P1 operator/runtime flow stabil:

- [ ] pertahankan repository-wide Pint clean pada source change berikutnya;
- [ ] migrasikan consumer `<x-ui-icon>` lama secara bertahap;
- [ ] cleanup compatibility CSS/JS setelah consumer legacy hilang;
- [ ] field-level validation UX;
- [ ] authenticated page-render/browser performance profiling;
- [ ] cleanup generated `bin/obj` bila relevan;
- [ ] report foundation/polish;
- [ ] mobile polish lanjutan.

Kandidat Livewire read-only setelah operator/runtime priorities:

1. Rekonsiliasi read-only filter/search;
2. Template Dokumen filter katalog — upload/update tetap canonical flow;
3. Laporan Audit pagination.

Tetap ditunda tanpa kebutuhan/operator evidence khusus: ARKAS importer stateful, workspace detail Paket mutation-heavy, Dashboard filter migration, dan protected Siswa.

---

# Aturan pengerjaan

- jangan mengubah ARKAS/BKU source hanya agar test/audit lulus;
- jangan fabrikasi SPPD/vendor/penerima/template;
- audit real-data read-only sebelum mutation;
- mutation real-data hanya pada isolated copy;
- numbering mengikuti canonical order dan registry;
- Livewire mutation wajib mempunyai authorization boundary yang dapat dibuktikan;
- custom role middleware route tidak boleh dianggap otomatis persisten pada action Livewire;
- business rule tetap di use case/service/model, bukan component UI;
- docs status membedakan FUNCTIONAL PASS, REAL-DATA VERIFIED, RVR, dan DEFERRED;
- source-responsive PASS tidak sama dengan browser/mobile PASS;
- setelah contract utama PASS, jangan menambah test tanpa alasan nyata;
- bila ada bug: reproduce -> fix -> focused regression bila perlu -> operator re-check.
