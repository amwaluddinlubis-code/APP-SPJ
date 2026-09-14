# SPJ BOSP Web — Current Progress / Open Issues

Terakhir diperbarui: **2026-09-14**

Dokumen ini adalah sumber status release utama untuk branch `gui-standardization`. Detail gate historis dan command verification berada di `P0_VERIFICATION_KIT.md`; kontrak bisnis permanen berada di `SPJ_DESIGN_DECISIONS.md`.

Definisi status:

- **FUNCTIONAL PASS**: dibuktikan oleh source + deterministic test/CI yang benar-benar dijalankan;
- **REAL-DATA VERIFIED**: dibuktikan pada database sekolah nyata atau isolated copy tanpa fabrikasi data;
- **RVR**: masih memerlukan real-value/runtime/operator verification;
- **DEFERRED**: sengaja tidak menjadi fokus aktif saat ini, bukan berarti PASS.

---

## Checkpoint terbaru

### Latest successful canonical gate

```text
LATEST SUCCESSFUL CODE HEAD: fd01fc6681cf33642857fd3d0916764c4e140074
LATEST SUCCESSFUL CODE GATE: CI #469 / run 34695708139 / SUCCESS
WORKFLOW                   : SPJ Critical Verification
```

CI #469 tetap menjadi gate sukses terakhir yang membuktikan blocking frontend build, Blade compile, SPJ Critical, full Unit, dan full Feature suite untuk code head tersebut. Detail evidence canonical tetap berada di `P0_VERIFICATION_KIT.md` §1.

### Current branch HEAD attempt

```text
CURRENT HEAD AUDITED       : 701c73644b7dcf9d8aa710a842f28b2dad9a62d5
LATEST ATTEMPTED GATE      : CI #478 / run 34838430998 / FAILURE
REPOSITORY PINT            : ADVISORY / 5 pre-existing unrelated style issues
FRONTEND BUILD             : PASS pada run #478
BLADE COMPILE              : PASS pada run #478
LIVEWIRE AUTH REGRESSION   : PASS 2/2 di dalam SPJ Critical #478
SPJ CRITICAL               : 285 PASS / 2 FAIL / 2218 assertions
FULL UNIT                  : NOT RUN / skipped setelah critical failure
FULL FEATURE               : NOT RUN / skipped setelah critical failure
```

Dua failure SPJ Critical #478 adalah blocker integrasi yang sudah ada sebelum Phase 2 dan tidak berasal dari authorization hardening:

1. `SpjNumberingRollbackTest::test_item_description_can_change_when_numbered_but_not_when_final` masih memanggil `TransactionController::updateSpjDescriptions()` secara langsung dengan signature lama (2 argumen), sedangkan controller canonical sekarang menerima `Request`, `transactionId`, `ActiveSpjContext`, dan `SpjDescriptionService`.
2. `SpjWorkspaceMigrationTest::test_numbered_keeps_manual_paths_locked_but_allows_item_description_and_final_locks_everything` masih mengharapkan session `error` saat update `vendor_name` pada Paket NUMBERED, tetapi current workspace path tidak menghasilkan kontrak response tersebut.

Konsekuensi: **Phase 2 authorization hardening mempunyai focused critical regression PASS**, tetapi current HEAD belum boleh dipromosikan menjadi canonical FUNCTIONAL PASS sampai dua blocker integrasi di atas ditutup dan full Unit + Feature benar-benar berjalan hijau.

Commit docs-only tidak menggantikan code gate dan tidak boleh disebut functional verification baru.

---

## Status release saat ini

```text
FUNCTIONAL BASELINE : PASS pada successful gate fd01fc6681... / CI #469
CURRENT HEAD GATE   : RED / 2 SPJ Critical regressions pada CI #478
REAL-DATA CORE      : VERIFIED untuk audit/preflight + isolated numbering/cancel/tail rollback yang sudah terdokumentasi
GENERATED OUTPUT    : RVR / OPERATOR QA ACTIVE
TEMPLATE OFFICE QA : RVR
BROWSER/RUNTIME     : RVR ACTIVE
LIVEWIRE MIGRATION : PHASE 1 AUDIT + PHASE 2 AUTH HARDENING COMPLETE; REPO GATE STILL RED
FINAL RELEASE       : NOT YET
```

Aplikasi belum boleh disebut final release-ready. Current HEAD harus kembali memperoleh code gate hijau setelah dua regression integrasi ditutup; output/runtime QA tetap gate terpisah.

---

## Livewire / TALL migration — Phase 1 + Phase 2

### Phase 1 — mutation boundary audit

Status: **SOURCE AUDIT COMPLETE** (2026-09-14).

Audit seluruh `app/Livewire/` menemukan **25 component** dan mengklasifikasikan read-only/UI-state, context mutation, serta mutation sensitif. Detail matriks tetap berada di `LIVEWIRE_MIGRATION_PLAN.md`.

### Phase 2 — authorization hardening

Status: **IMPLEMENTED + FOCUSED CRITICAL REGRESSION PASS / BROWSER RUNTIME RVR** (2026-09-14).

Commit source:

```text
3c7be408f5a93795a597878b79f975373df24412
fix: harden Livewire mutation authorization
```

Commit test-gate:

```text
701c73644b7dcf9d8aa710a842f28b2dad9a62d5
test: gate Livewire mutation authorization as critical
```

Boundary yang sudah ditutup:

- `UserManagement::{createUser,updateUser,deleteUser}` → ADMIN-only sebelum validation/query/mutation;
- `SchoolMaster::createSchool` → ADMIN-only sebelum provisioning;
- `DatabaseMaintenance::run` → ADMIN-only sebelum action allow-list/service call;
- `DatabaseResetForm::resetDatabase` → ADMIN-only selain active-school match + exact confirmation yang sudah ada;
- `DatabaseSchoolList::{activate,migrate}` → ADMIN-only sebelum school lookup/service/audit;
- `DocumentStorageSettings::save` → OPERATOR/ADMIN sebelum validation/persistence. Component tetap belum mounted pada halaman settings aktif, tetapi aman sebelum reuse;
- `SchoolSelector::selectSchool` tetap memakai guard eksplisit `admin || own school`;
- `YearSelector::selectYear` tetap context mutation sesuai flow.

Regression baru `LivewireMutationAuthorizationTest` berada di testsuite `SPJ Critical`. CI #478 membuktikan:

```text
PASS operator + viewer ditolak dari seluruh mutation ADMIN Livewire yang diaudit
PASS operator dapat menyimpan document storage path
PASS viewer ditolak dari document storage mutation
```

Phase 2 tidak mengubah lifecycle SPJ, numbering, sync, business rule, atau tenant ownership. Authorization menggunakan kontrak model existing `isAdministrator()` / `isOperatorOrAdministrator()` sehingga tidak membuat role matrix baru.

Persistent middleware Livewire custom aplikasi tetap hanya active-school/active-year; karena itu rule arsitektur tetap: **mutation Livewire sensitif harus re-assert authorization di action/policy/persistent mechanism yang benar-benar berlaku pada request Livewire, bukan mengandalkan route GET induk**.

---

## Status area Livewire yang sudah dimigrasikan

- Transaksi: filter/search/pagination `TransactionsTable` — read-only boundary.
- RKAS budget: `RkasBudgetFilter`, `RkasBudgetTable` — read-only boundary; `RkasTable` tetap read-only/legacy component.
- SPJ Persiapan/Paket/Laporan/Monitoring: Livewire filters/lists + SPA tab navigation; workspace detail Paket mutation-heavy tetap server-rendered.
- Pajak: `TaxFilter` read-only filter/summary.
- Pegawai: `EmployeeDirectory` read-only filter/pagination.
- Data Sinkronisasi: `SyncedDataNavigation` UI-state/read-only.
- Database Aktif: summary/tab/explorer read-only panels + mutation actions sudah role-hardened.
- Pengaturan user/master sekolah: mutation sudah ADMIN-hardened dan negative role regression PASS.
- Penyimpanan Dokumen: class Livewire dormant sudah OPERATOR/ADMIN-hardened sebelum reuse; halaman settings aktif masih memakai canonical form/controller path.

Browser/operator behavior tetap RVR sampai `GUI_RUNTIME_QA.md` dijalankan pada runtime aktual.

---

## GUI standardization

```text
GUI STANDARDIZATION CORE : ESTABLISHED
SOURCE-LEVEL CLEANUP      : PASS untuk milestone source yang sudah digate pada baseline sebelumnya
BROWSER DESKTOP/LAPTOP    : RVR ACTIVE
MOBILE/TABLET RUNTIME     : RVR / NON-BLOCKER untuk target desktop-laptop
```

Shared `x-ui` primitives, semantic theme tokens, icon registry, density/typography, responsive source guards, pagination theme, early theme init, dan top progress integration tersedia di source. Klaim browser/mobile PASS tetap dilarang sampai `GUI_RUNTIME_QA.md` dijalankan pada runtime aktual.

SPA tab SPJ memakai `Livewire.navigate` dengan full-reload fallback; modal template preview menggunakan delegated listener agar tetap bekerja setelah body swap. Source regression tersedia, tetapi browser repeated-navigation/modal verification masih RVR.

---

## P0-01 — Six-category SPJ end-to-end

```text
FUNCTIONAL SIX-CATEGORY BASELINE : PASS pada successful gate sebelumnya
REAL-DATA BASELINE/AUDIT        : VERIFIED untuk scope yang tersedia
GENERATED-DOCUMENT REAL DATA    : ACTIVE / RVR
INSTALLED-RUNTIME               : DEFERRED
```

Kategori canonical tetap:

```text
BARANG
KONSUMSI
PEMELIHARAAN
JASA_LAINNYA
SPPD
HONOR_PEGAWAI
```

Baseline real-data 2026 yang sudah terdokumentasi:

```text
transactions              : 170
transaction_items         : 407
spj_packages              : 66
spj_documents             : 0
document_number_sequences : 0
document_number_formats   : 0
```

Distribusi Paket 2026 yang tersedia: BARANG 41, HONOR_PEGAWAI 12, JASA_LAINNYA 9, KONSUMSI 2, PEMELIHARAAN 2, SPPD 0. SPPD nyata tersedia pada data 2025; jangan fabrikasi SPPD 2026 untuk coverage.

---

## P0-02 — Document generator / template

Successful baseline sebelum current red gate membuktikan:

- individual template XLSX true single-sheet;
- canonical XLSX HTML preview;
- placeholder inspector;
- master template recomposition;
- preview/download tidak menerbitkan nomor;
- source/master tersimpan tidak dimutasi saat individual download;
- master parsial ditolak;
- DOCX tetap individual.

Source HEAD terbaru menambahkan optimasi validator XLSX agar daftar sheet dibaca lebih dahulu dan hanya sheet canonical yang dimuat `readDataOnly` untuk template individual; package importer juga memakai read-only load. Commit pengembang melaporkan penurunan waktu halaman template dari sekitar 33 detik menjadi sekitar 0,7 detik pada environment pengembang, tetapi angka ini **belum dipromosikan menjadi canonical runtime PASS** karena current HEAD gate masih merah dan browser/runtime independent verification belum dilakukan.

Source HEAD juga menambahkan `SpjSpreadsheetPdfWriter` dan persistence report path. Full Unit/Feature suite untuk HEAD belum berjalan karena SPJ Critical masih berhenti pada dua regression integrasi.

Panduan lifecycle tetap: `TEMPLATE_MASTER_WORKFLOW.md` dan `DOCUMENT_TEMPLATE_PLACEHOLDERS.md`.

---

## P0-03 — Numbering + registry + lifecycle

Canonical source of truth tetap:

```text
app/Services/SpjNumberingDocumentRegistry.php
```

Successful baseline sebelumnya sudah membuktikan first numbering, cancel/reserved sequence, tail rollback, fund-source scoped sequence, quarter rollback regression, dan registry-based consumers. Current Livewire migration tidak mengubah kontrak numbering tersebut.

`SpjDocumentTypeRegistry` tetap registry template/placeholder/output dan bukan source sequence numbering.

---

## P0-04 — Authorization

Status:

```text
HTTP/ROUTE AUTH BASELINE       : PASS pada successful gate sebelumnya
LIVEWIRE MUTATION BOUNDARY     : HARDENED pada Phase 2
NEGATIVE ROLE REGRESSION       : PASS di SPJ Critical #478
RUNTIME/BROWSER VERIFICATION   : RVR
OVERALL CURRENT HEAD GATE      : RED karena 2 regression SPJ non-authorization
```

Rule aktif: mutation user/role, school provisioning, database maintenance/reset/activation, dan global document-storage setting harus mengulang authorization pada boundary Livewire yang dieksekusi. Route GET visibility bukan authorization proof untuk request Livewire berikutnya.

---

## P0-05 — Safe sync + reconciliation

Baseline contract tetap:

- ARKAS/BKU source readonly;
- operator SPJ overlay tidak dihapus oleh sync;
- source missing/returning mempertahankan identity;
- NUMBERED/FINAL tidak dimutasi diam-diam;
- tenant boundary `School + Fiscal Year + Fund Source`.

Current Livewire filter/navigation/hardening tidak mengubah contract tersebut. Real-data reconciliation tetap operator-flow driven.

---

## P0-06 — Tenant/context isolation

Canonical boundary:

```text
School + Fiscal Year + Fund Source
```

Read-only Livewire filter components tetap menggunakan active context atau query/service canonical. Phase 2 hanya menambah role enforcement dan tidak memindahkan scope logic ke Blade/Alpine.

---

## P0-07 — APP DATA / backup / reset / restore

Baseline service/functionality tetap tersedia. `DatabaseResetForm` Livewire sekarang memerlukan **ADMIN + active-school match + exact confirmation** sebelum reset service dipanggil.

Installed Windows runtime tetap DEFERRED.

---

## P0-08 — Generic ARKAS Importer

Status baseline: **FUNCTIONAL HARDENING PASS / OPERATOR DATA TEST ACTIVE** pada gate sebelumnya. Importer stateful tetap tidak menjadi target migrasi Livewire opportunistic.

---

## Prioritas kerja aktif

Phase 2 authorization sudah selesai. Urutan berikutnya:

1. **P0-00 integration repair**: tutup dua failure SPJ Critical #478 tanpa mengubah contract yang sudah benar;
2. ubah `SpjNumberingRollbackTest` agar tidak memanggil controller dengan signature internal yang sudah usang—lebih baik uji melalui route/container HTTP canonical;
3. tentukan kontrak benar untuk update Paket NUMBERED pada `SpjWorkspaceMigrationTest`: bila mutation substansi memang harus terkunci, restore response/error canonical; bila behavior sengaja berubah, sinkronkan test + dokumentasi contract;
4. rerun SPJ Critical sampai hijau, lalu pastikan full Unit + Feature benar-benar berjalan PASS;
5. setelah integration gate hijau, kembali ke generated-document real-data/operator QA sebagai prioritas produk;
6. browser/operator QA desktop-laptop berdasarkan `GUI_RUNTIME_QA.md`;
7. official-template/Excel/LibreOffice/PDF visual-output QA;
8. baru pertimbangkan migrasi Livewire read-only berikutnya seperti Rekonsiliasi.

Jangan menambah mutation-heavy Livewire baru sebelum poin 1–4 selesai.

---

## Open verification / release blockers

- current branch HEAD gate merah karena dua SPJ Critical regression non-authorization;
- full Unit + Feature suite belum dijalankan untuk HEAD `701c736...` karena critical failure;
- generated-document real-data per kategori masih RVR/active;
- individual template/master template Office visual QA masih RVR;
- preview HTML template nyata pada browser aktual masih RVR;
- browser/operator desktop-laptop QA masih RVR;
- official-template print/layout/output QA masih RVR;
- installed-runtime checks masih DEFERRED;
- mobile/tablet runtime QA tetap RVR/non-blocker untuk target desktop-laptop.

Livewire mutation authorization **bukan lagi open blocker source/regression** setelah Phase 2, tetapi browser/runtime verification tetap RVR.

---

## Aturan evidence dan pengembangan

1. Jangan mengubah source data agar test/audit PASS.
2. Jangan memakai deterministic fixture sebagai bukti real-data verified.
3. Jangan memakai screenshot/UI appearance sebagai pengganti backend regression.
4. Jangan menyatakan CI baru untuk commit docs-only.
5. Setiap source change setelah successful code gate terakhir membutuhkan gate hijau baru sebelum menjadi canonical functional HEAD.
6. Mutation Livewire sensitif harus mempunyai authorization boundary yang benar-benar dieksekusi pada action request.
7. GUI source cleanup tidak sama dengan browser visual PASS.
8. Bila business rule berubah, sinkronkan `SPJ_DESIGN_DECISIONS.md` dan feature guide terkait.
9. Metadata numbering baru/berubah dimulai dari `SpjNumberingDocumentRegistry`.
10. Setelah contract inti stabil, gunakan pendekatan `operator flow -> temukan bug nyata -> perbaiki -> regression bila perlu`.
