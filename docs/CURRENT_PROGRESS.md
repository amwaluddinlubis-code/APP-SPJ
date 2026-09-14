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
CURRENT HEAD AUDITED       : 2e0f65cbd5c6e0fd8a8495f2d156805fd468ee35
LATEST ATTEMPTED GATE      : CI #476 / run 34830288269 / FAILURE
FRONTEND BUILD             : PASS pada run #476
BLADE COMPILE              : PASS pada run #476
SPJ CRITICAL               : FAILURE pada run #476
FULL UNIT                  : NOT RUN / skipped setelah critical failure
FULL FEATURE               : NOT RUN / skipped setelah critical failure
```

Konsekuensi: perubahan source setelah `fd01fc6681...` **belum boleh dipromosikan menjadi canonical FUNCTIONAL PASS** hanya berdasarkan source review atau focused test lokal yang pernah dijalankan. Run #472, #473, #474, #475, dan #476 berada dalam rangkaian gate merah; karena itu kegagalan tidak boleh diasumsikan berasal hanya dari commit HEAD terakhir tanpa reproduksi test yang tepat.

Commit docs-only tidak menggantikan code gate dan tidak boleh disebut functional verification baru.

---

## Status release saat ini

```text
FUNCTIONAL BASELINE : PASS pada successful gate fd01fc6681... / CI #469
CURRENT HEAD GATE   : RED / SPJ Critical failure pada CI #476
REAL-DATA CORE      : VERIFIED untuk audit/preflight + isolated numbering/cancel/tail rollback yang sudah terdokumentasi
GENERATED OUTPUT    : RVR / OPERATOR QA ACTIVE
TEMPLATE OFFICE QA : RVR
BROWSER/RUNTIME     : RVR ACTIVE
LIVEWIRE MIGRATION : SOURCE IMPLEMENTED / MUTATION AUTHORIZATION HARDENING OPEN
FINAL RELEASE       : NOT YET
```

Aplikasi belum boleh disebut final release-ready. Selain output/runtime QA yang memang belum selesai, current HEAD harus kembali memperoleh code gate hijau setelah integration/hardening issue ditutup.

---

## Livewire / TALL migration — Phase 1 boundary audit

Status: **SOURCE AUDIT COMPLETE / HARDENING REQUIRED / BROWSER RUNTIME RVR** (2026-09-14).

Audit seluruh `app/Livewire/` pada HEAD menemukan **25 component**:

```text
17  READ-ONLY / UI-STATE
 1  MUTATION GUARDED (SchoolSelector)
 1  CONTEXT MUTATION ACCEPTED (YearSelector)
 5  ACTIVE MUTATION BOUNDARIES NEED ROLE HARDENING
 1  UNMOUNTED MUTATION COMPONENT NEEDS HARDENING BEFORE REUSE
```

Active mutation boundaries yang perlu Phase 2:

- `UserManagement::{createUser,updateUser,deleteUser}` — mutation user/role, expected ADMIN;
- `SchoolMaster::createSchool` — create school + database provision, expected ADMIN;
- `DatabaseMaintenance::run` — checkpoint/migrate/vacuum/provision, expected ADMIN;
- `DatabaseResetForm::resetDatabase` — destructive reset; active-school + exact confirmation sudah ada, role ADMIN belum digate di action;
- `DatabaseSchoolList::{activate,migrate}` — database/context maintenance, expected ADMIN.

`DocumentStorageSettings::save` menulis global path dan belum mempunyai operator/admin guard; component ini tidak ditemukan dipasang pada halaman settings aktif saat audit sehingga diklasifikasikan **UNMOUNTED / harden before reuse**, bukan active exploit claim.

`SchoolSelector::selectSchool` sudah mempunyai guard eksplisit: administrator dapat memilih sekolah, non-admin hanya sekolah miliknya. `YearSelector::selectYear` hanya mengubah fiscal-year/fund-source session pada database sekolah aktif dan diklasifikasikan context mutation yang sesuai flow.

Persistent middleware Livewire custom yang terdaftar aplikasi saat audit hanya:

```text
EnsureActiveSchool
EnsureActiveFiscalYear
```

`EnsureAdministrator`, `EnsureOperatorOrAdministrator`, dan `EnsureSpjActiveContext` tidak berada pada daftar persistent middleware custom tersebut. Karena itu action mutation sensitif tidak boleh dianggap independently authorized hanya karena route GET induknya memakai role middleware.

Audit ini adalah temuan source architecture, **bukan klaim exploit runtime**. Livewire signed snapshot/checksum dan browser behavior tetap memerlukan runtime evidence.

Panduan lengkap dan matriks 25 component: `LIVEWIRE_MIGRATION_PLAN.md`.

### Status area Livewire yang sudah dimigrasikan

- Transaksi: filter/search/pagination `TransactionsTable` — read-only boundary.
- RKAS budget: `RkasBudgetFilter`, `RkasBudgetTable` — read-only boundary; `RkasTable` tetap read-only/legacy component.
- SPJ Persiapan/Paket/Laporan/Monitoring: Livewire filters/lists + SPA tab navigation; workspace detail paket mutation-heavy tetap server-rendered.
- Pajak: `TaxFilter` read-only filter/summary.
- Pegawai: `EmployeeDirectory` read-only filter/pagination.
- Data Sinkronisasi: `SyncedDataNavigation` UI-state/read-only.
- Database Aktif: summary/tab/explorer read-only panels sudah Livewire; mutation actions memerlukan hardening seperti daftar di atas.
- Pengaturan user/master sekolah: source migration ada, tetapi status FUNCTIONAL PASS sebelumnya dicabut sampai authorization boundary + code gate hijau.

Status lama `WIP UNCOMMITTED` untuk Database Manager/Data Sinkronisasi sudah usang: component tersebut sekarang sudah committed pada current branch.

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

Source HEAD terbaru menambahkan optimasi validator XLSX agar daftar sheet dibaca lebih dahulu dan hanya sheet canonical yang dimuat `readDataOnly` untuk template individual; package importer juga memakai read-only load. Commit melaporkan penurunan waktu halaman template dari sekitar 33 detik menjadi sekitar 0,7 detik pada environment pengembang, tetapi angka ini **belum dipromosikan menjadi canonical runtime PASS** karena current HEAD code gate #476 merah dan browser/runtime independent verification belum dilakukan.

Source HEAD juga menambahkan `SpjSpreadsheetPdfWriter` dan persistence report path. Full Unit/Feature suite untuk HEAD belum berjalan pada CI #476, sehingga output-sensitive change tersebut masih memerlukan green gate + Office/PDF runtime QA.

Panduan lifecycle tetap: `TEMPLATE_MASTER_WORKFLOW.md` dan `DOCUMENT_TEMPLATE_PLACEHOLDERS.md`.

---

## P0-03 — Numbering + registry + lifecycle

Canonical source of truth tetap:

```text
app/Services/SpjNumberingDocumentRegistry.php
```

Successful baseline sebelumnya sudah membuktikan first numbering, cancel/reserved sequence, tail rollback, fund-source scoped sequence, quarter rollback regression, dan registry-based consumers. Current Livewire migration tidak boleh mengubah kontrak numbering tersebut.

`SpjDocumentTypeRegistry` tetap registry template/placeholder/output dan bukan source sequence numbering.

---

## P0-04 — Authorization

Baseline HTTP authorization sebelumnya FUNCTIONAL PASS. **Livewire mutation authorization hardening sekarang menjadi open integration issue** karena sejumlah mutation action baru tidak mempunyai role guard action-level dan custom role middleware tidak terdaftar sebagai persistent Livewire middleware.

Status saat ini:

```text
HTTP/ROUTE AUTH BASELINE       : PASS pada gate sebelumnya
LIVEWIRE MUTATION BOUNDARY     : HARDENING REQUIRED
NEGATIVE ROLE REGRESSION       : REQUIRED untuk mutation yang dipindahkan
RUNTIME EXPLOITABILITY CLAIM   : NOT ASSERTED / RVR
```

Jangan menurunkan temuan ini menjadi sekadar cosmetic issue; mutation user, school provisioning, database maintenance, dan reset adalah action sensitif.

---

## P0-05 — Safe sync + reconciliation

Baseline contract tetap:

- ARKAS/BKU source readonly;
- operator SPJ overlay tidak dihapus oleh sync;
- source missing/returning mempertahankan identity;
- NUMBERED/FINAL tidak dimutasi diam-diam;
- tenant boundary `School + Fiscal Year + Fund Source`.

Current Livewire filter/navigation work tidak boleh mengubah contract tersebut. Real-data reconciliation tetap operator-flow driven.

---

## P0-06 — Tenant/context isolation

Canonical boundary:

```text
School + Fiscal Year + Fund Source
```

Read-only Livewire filter components yang diaudit tetap menggunakan active context atau query/service canonical. Phase 2 authorization hardening harus menjaga boundary ini dan tidak memindahkan scope logic ke Blade/Alpine.

---

## P0-07 — APP DATA / backup / reset / restore

Baseline service/functionality tetap tersedia. `DatabaseResetForm` Livewire sekarang memiliki active-school match + exact confirmation guard, tetapi role ADMIN action guard perlu ditambahkan sebelum status migration tersebut dianggap fully hardened.

Installed Windows runtime tetap DEFERRED.

---

## P0-08 — Generic ARKAS Importer

Status baseline: **FUNCTIONAL HARDENING PASS / OPERATOR DATA TEST ACTIVE** pada gate sebelumnya. Importer stateful tetap tidak menjadi target migrasi Livewire opportunistic.

---

## Prioritas kerja aktif

Urutan langsung setelah audit ini:

1. **Livewire authorization hardening Phase 2** untuk mutation boundaries yang teridentifikasi;
2. focused negative role/tenant regression untuk mutation tersebut;
3. identifikasi dan tutup failure `SPJ Critical` pada current HEAD;
4. jalankan kembali blocking gate sampai SPJ Critical + Unit + Feature benar-benar hijau;
5. setelah integration gate hijau, kembali ke operator-flow/generated-document real-data QA sebagai prioritas produk utama;
6. browser/operator QA desktop-laptop berdasarkan `GUI_RUNTIME_QA.md`;
7. official-template/Excel/LibreOffice/PDF visual-output QA;
8. mobile/tablet tetap RVR/non-blocker untuk target desktop-laptop.

Jangan menambah area migrasi Livewire baru sebelum poin 1–4 selesai.

---

## Open verification / release blockers

- current branch HEAD code gate merah pada SPJ Critical;
- Livewire mutation authorization hardening belum selesai;
- full Unit + Feature suite belum dijalankan untuk HEAD `2e0f65c...` karena CI #476 berhenti lebih awal;
- generated-document real-data per kategori masih RVR/active;
- individual template/master template Office visual QA masih RVR;
- preview HTML template nyata pada browser aktual masih RVR;
- browser/operator desktop-laptop QA masih RVR;
- official-template print/layout/output QA masih RVR;
- installed-runtime checks masih DEFERRED;
- mobile/tablet runtime QA tetap RVR/non-blocker untuk target desktop-laptop.

---

## Aturan evidence dan pengembangan

1. Jangan mengubah source data agar test/audit PASS.
2. Jangan memakai deterministic fixture sebagai bukti real-data verified.
3. Jangan memakai screenshot/UI appearance sebagai pengganti backend regression.
4. Jangan menyatakan CI baru untuk commit docs-only.
5. Setiap source change setelah successful code gate terakhir membutuhkan gate hijau baru sebelum menjadi canonical functional HEAD.
6. Source implementation tidak sama dengan authorization-hardened implementation.
7. GUI source cleanup tidak sama dengan browser visual PASS.
8. Bila business rule berubah, sinkronkan `SPJ_DESIGN_DECISIONS.md` dan feature guide terkait.
9. Metadata numbering baru/berubah dimulai dari `SpjNumberingDocumentRegistry`.
10. Setelah contract inti stabil, gunakan pendekatan `operator flow -> temukan bug nyata -> perbaiki -> regression bila perlu`.
