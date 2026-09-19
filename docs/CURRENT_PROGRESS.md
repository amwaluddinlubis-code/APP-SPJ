# SPJ BOSP Web — Current Progress / Open Issues

Terakhir diperbarui: **2026-09-19**

Dokumen ini adalah sumber status release utama untuk branch `arkas-raw-mirror`. Detail gate/command verification berada di `P0_VERIFICATION_KIT.md`; prioritas berada di `DEVELOPMENT_ROADMAP.md`; kontrak bisnis permanen berada di `SPJ_DESIGN_DECISIONS.md`.

Definisi status:

- **FUNCTIONAL PASS**: dibuktikan oleh source + deterministic test/CI yang benar-benar dijalankan;
- **REAL-DATA VERIFIED**: dibuktikan pada database sekolah nyata atau isolated copy tanpa fabrikasi data;
- **RVR**: masih memerlukan real-value/runtime/operator verification;
- **DEFERRED**: sengaja tidak menjadi fokus aktif saat ini, bukan berarti PASS.

---

## Checkpoint terbaru

### Latest successful canonical code gate

```text
LATEST SUCCESSFUL CODE HEAD: ba8fa0b2ea307406a7c7be2cb3dc6fa6e7bce7c4
LATEST SUCCESSFUL CODE GATE: CI #486 / run 34853857969 / SUCCESS
WORKFLOW                   : SPJ Critical Verification
COMPOSER VALIDATE          : PASS
LOCKED PLATFORM CHECK      : PASS pada PHP 8.3
COMPOSER INSTALL           : PASS dari committed lock
REPOSITORY PINT            : PASS pada gate #486
FRONTEND BUILD             : PASS
BLADE COMPILE              : PASS
SPJ CRITICAL               : PASS
FULL UNIT                  : PASS
FULL FEATURE               : PASS
```

CI #486 adalah gate sukses canonical terbaru untuk code head `ba8fa0b...`. Gate ini membuktikan dependency lock dapat di-install secara deterministik pada PHP 8.3, lalu frontend build, Blade compile, SPJ Critical, full Unit, dan full Feature semuanya selesai sukses.

Commit dokumentasi setelah `ba8fa0b...` tidak menggantikan code gate tersebut selama tidak mengubah source/runtime yang digate.

### P0 dependency-platform repair — CI #483 → #486

CI #483 pada head TALL migration `a4dd3954...` gagal sebelum test pada langkah `composer install`. Log membuktikan `composer.lock` mengunci sejumlah Symfony 8.x yang membutuhkan PHP `>=8.4`, sedangkan project mendeklarasikan PHP `^8.3` dan workflow canonical berjalan pada PHP 8.3.

Perbaikan dilakukan tanpa menaikkan minimum PHP project dan tanpa mengubah business rule:

1. `composer.json` menambahkan Composer platform floor `config.platform.php = 8.3.0` agar dependency resolution dari workstation PHP 8.4+ tetap kompatibel dengan minimum runtime project.
2. CI repair #485 me-resolve dependency Symfony pada PHP 8.3, memverifikasi `composer install`, build, Blade, SPJ Critical, Unit, dan Feature, lalu hanya setelah seluruh gate PASS menyimpan `composer.lock` hasil repair.
3. Workflow kemudian dikembalikan ke mode read-only/deterministik: tidak ada `composer update` di gate normal.
4. Gate normal menambahkan `composer validate --strict` dan `composer check-platform-reqs --lock` sebelum `composer install` agar drift platform lock terdeteksi lebih awal.
5. CI #486 pada `ba8fa0b...` membuktikan gate normal tersebut SUCCESS.

Commit terkait:

```text
7b5615c4b98222f145a3ba0e18b409abb2b1e20d
fix: constrain dependency resolution to PHP 8.3

d3c786d841d431c4d78cf2441f9a1e358115afa6
fix: keep dependency lock compatible with PHP 8.3

ba8fa0b2ea307406a7c7be2cb3dc6fa6e7bce7c4
ci: enforce deterministic PHP 8.3 dependency gate
```

### Integration repair #478 → #480 — historical baseline

Dua failure SPJ Critical #478 sudah ditutup pada commit:

```text
b61cdc621539cb6fc62dd17efc22da16a9c2a14c
test: close SPJ critical integration regressions
```

Perbaikannya hanya menyentuh regression test:

1. `SpjNumberingRollbackTest` tidak lagi memanggil method controller dengan signature internal lama; lifecycle NUMBERED/FINAL diuji melalui route HTTP canonical.
2. `SpjWorkspaceMigrationTest` diselaraskan dengan contract NUMBERED canonical: field substansi seperti `vendor_name` yang dikirim melalui manual package update tidak disimpan, category switch tetap ditolak, sedangkan `payment_description`/`item_description` tetap carve-out yang diizinkan.

CI #479 membuktikan dua failure tersebut selesai: SPJ Critical dan Unit PASS. Full Feature kemudian membuka satu stale source-contract assertion pada `TransactionNumberedItemDescriptionUiTest`, yang masih mencari implementasi inline lama meskipun controller sekarang mendelegasikan normalisasi payment description ke `SpjDescriptionService`. Assertion tersebut diselaraskan pada:

```text
887d0219142d634e6a85b6672d3bffb02b5b1584
test: align description UI contract with service delegation
```

CI #480 adalah historical green baseline sebelum Laravel 13/TALL migration. Ia telah disupersede sebagai current canonical code gate oleh CI #486.

### Laravel 13 upgrade — local verification 2026-09-14

`composer.json` dinaikkan: `php ^8.3`, `laravel/framework ^13.0`, `laravel/tinker ^3.0`, `phpunit/phpunit ^12.0`, `branch-alias 13.x-dev`. Pada tahap upgrade awal, resolve lokal PHP 8.4 sempat memilih dependency Symfony 8 dan Filament masih ada sebelum TALL cleanup berikutnya.

Verifikasi lokal yang benar-benar dijalankan pada head upgrade (PHP 8.4.0):

```text
SPJ Critical : 288 PASS / 2244 assertions
FULL UNIT    : 60 PASS / 206 assertions
FULL FEATURE : 412 PASS / 2980 assertions
npm run build: PASS (vite v6.4.3, ~3s)
view:cache   : PASS
pint --dirty : passed
git diff --check: OK
```

Selisih +1 test vs gate #480 berasal dari commit `5fa98ed` (satu head di depan gate), bukan dari upgrade framework. Tidak ada business rule, lifecycle, numbering, sync, tenant ownership, atau authorization contract yang diubah. Remote deterministic compatibility Laravel 13/TALL pada PHP 8.3 sekarang dibuktikan oleh CI #486.

### TALL migration — Filament + Sail removal 2026-09-14

Audit membuktikan seluruh surface Filament adalah dead code: `RkasTable` orphan tanpa konsumen, `RkasBudgetTable` hanya dipakai view `rkas-budget/filament.blade.php` yang juga orphan (controller aktif me-render `rkas-budget.index` yang native), dan tidak ada test yang menyentuh class/view Filament. `laravel/sail` tidak dipakai (tanpa compose file/CI, dev memakai herd-lite + `artisan serve`).

Dihapus: `filament/*` + `laravel/sail` dari `composer.json` (termasuk script `filament:upgrade`), 2 komponen + 3 view orphan, directive `@filamentStyles/@filamentScripts`, 5 CSS `@import` Filament, selector `.fi-*` basi, dan aset `public/js/filament`. Tidak ada paket baru — stack aplikasi memakai Laravel + Livewire + Alpine + Tailwind; workspace RKAS kini dimount melalui `RkasBudgetWorkspace` dengan controller sebagai adapter data read-only.

Verifikasi lokal pasca-removal (PHP 8.4.0): SPJ Critical 288 PASS / 2244 assertions, Unit 60/206, Feature 412/2980 (identik dengan baseline L13), `npm run build` PASS (CSS 932KB → 425KB), `view:cache` PASS, `pint --dirty` passed. Remote deterministic gate pasca-removal sekarang PASS pada CI #486.

---

## Status release saat ini

```text
FUNCTIONAL BASELINE : PASS pada ba8fa0b... / CI #486
CURRENT CODE GATE   : GREEN / CI #486
REAL-DATA CORE      : VERIFIED untuk audit/preflight + isolated numbering/cancel/tail rollback yang terdokumentasi
GENERATED OUTPUT    : RVR / OPERATOR QA ACTIVE
TEMPLATE OFFICE QA : RVR
BROWSER/RUNTIME     : RVR ACTIVE
LIVEWIRE MIGRATION : PHASE 1 AUDIT + PHASE 2 AUTH HARDENING COMPLETE / CODE GATE PASS
FINAL RELEASE       : NOT YET
```

### Boundary Livewire dan compatibility verification — 2026-09-19

`TransactionDetailWorkspace` sekarang mengambil legacy maupun fresh transaction
melalui `ActiveSpjContext` (tahun + sumber dana), termasuk fresh-only lookup;
`TransactionController::show` memakai scope yang sama. `saveDescriptions()` dan
`resolveReconciliation()` mengulang guard OPERATOR/ADMIN pada action Livewire,
menolak fresh-only mutation untuk overlay yang belum ada, dan reconciliation
menolak transaction synthetic/non-persisted. Regression lokal:
`TransactionDetailWorkspaceAuthorizationTest` **2 test / 5 assertions PASS**;
regression terkait authorization, reconciliation, fresh compatibility, dan UI
**17 test / 119 assertions PASS** (PHPUnit melaporkan deprecated metadata).

Evidence database tenant yang dipakai untuk verifikasi:

```text
tenant       : 10260756 / SMP Negeri 2 Ranto Baek
database     : storage/app/school-databases/10260786/spj.sqlite
branch       : arkas-raw-mirror
raw mirror   : 56 tables (38 ACTIVE, 18 EMPTY), 91,070 rows
fresh status : 329 ACTIVE, 12 DELETED, 97 SOURCE_MISSING
migration    : 54 applied; latest 2026_09_17_155741_create_fresh_spj_tables
integrity    : PRAGMA integrity_check = ok; foreign_key_check = 0
```

Perbandingan projection fresh dengan APP-SPJ lama dilakukan pada fiscal year
dan fund source yang sama:

```text
2025 / fund 1  : fresh 104 tx / 268 item / Rp256.410.000 gross /
                 Rp12.819.364 tax / Rp243.590.636 net
                 legacy 104 tx / 268 item / nominal identik
2025 / fund 12 : fresh 17 tx / 24 item / Rp35.000.000 gross /
                 Rp0 tax / Rp35.000.000 net
                 legacy 17 tx / 24 item / nominal identik
2026 / fund 1  : fresh 66 tx / 139 item / Rp138.195.000 gross /
                 Rp7.677.946 tax / Rp130.517.054 net
                 legacy APP-SPJ: 0 tx / 0 item — tidak ada baseline legacy 2026
```

Audit `spj:audit-quarter` read-only pada seluruh kuartal 2025 (fund 1 dan
fund 12 yang memiliki transaksi) dan 2026 (fund 1) menghasilkan integrity
**PASS**, foreign-key violations **0**, financial mismatches **0**, critical
anomalies **0**. Warning yang tersisa adalah data lama yang belum lengkap:
2025 fund 1 **75 warning**, 2025 fund 12 **16 warning**, dan 2026 **0 warning**;
terutama blank `item_description`, bukan mismatch projection/nominal.

### Context isolation completion — 2026-09-19

`SpjFreshTransaction::rkasValue()` sekarang mengikat lookup period dan RKAS ke
`fiscal_year_id` serta `fund_source_id` milik transaksi fresh. Regression
`TransactionDetailWorkspaceAuthorizationTest` menjadi **8 test / 29 assertions
PASS**, mencakup source key sama lintas fund source, collision RKAS
`source_rapbs_id`, positive ADMIN/OPERATOR, VIEWER 403, reconciliation valid,
NUMBERED carve-out, FINAL lock, dan operational audit log.

Regression suite terkait yang dijalankan setelah perubahan: **40 test / 227
assertions PASS** (40 deprecated notices). Mutation description dan resolution
sekarang mencatat `OperationalAuditService` setelah write/resolve berhasil;
penolakan authorization, FINAL, invalid reconciliation, atau fresh-only overlay
tidak membuat audit mutation palsu.

### Overlay & package continuity verification — 2026-09-19

Regression continuity menutup jalur `source_key` grouped → legacy overlay →
item operator → Paket SPJ. Suite continuity baru menghasilkan **21 test / 137
assertions PASS** (21 deprecated notices), sedangkan suite gabungan projection,
compatibility, workspace, reconciliation, ownership, dan sync menghasilkan
**67 test / 546 assertions PASS** (67 deprecated notices).

Yang dikunci oleh regression:

- `SHA256(sorted(ID_KAS_UMUM))` tetap menjadi identity transaksi dan
  `ID_KAS_UMUM` tetap menjadi identity item;
- `payment_description`, `spj_category`, `receipt_recipient_name`,
  `payment_method`, dan `item_description` tidak ditimpa sync;
- reorder raw item mempertahankan uraian berdasarkan `source_item_id`, bukan
  ordinal;
- Paket DRAFT, NUMBERED, dan FINAL mempertahankan ID, status, nomor, timestamp,
  serta snapshot; projection/sync tidak membuat Paket baru;
- source disappear/return mempertahankan transaksi, item, overlay, dan Paket;
- repeated projection tetap idempotent, dan membership change
  `KAS-001/KAS-002` → `KAS-001/KAS-002/KAS-003` pada `NO_BUKTI` unik tidak
  memindahkan Paket: legacy transaction lama dipertahankan sebagai
  `SOURCE_MISSING` dengan reconciliation event, sedangkan source baru tetap
  menjadi representasi fresh.

Read-only tenant audit pada `10260756` / database `10260786`:

```text
2025 fund 1  : legacy 104 tx / 268 item; fresh ACTIVE 104 tx / 268 item
2025 fund 12 : legacy 17 tx / 24 item; fresh ACTIVE 17 tx / 24 item
2026 fund 1  : legacy 0 tx / 0 item; fresh ACTIVE 66 tx / 139 item
fresh 2026 history: 12 DELETED, 97 SOURCE_MISSING
packages/documents pada tiga context: 0 / 0
integrity: PASS; foreign-key violations: 0; financial mismatches: 0
```

`spj:audit-quarter` dijalankan read-only untuk seluruh kuartal 2025/2026 pada
fund 1 dan fund 12. Tidak ditemukan critical anomaly atau financial mismatch;
warning yang ada berupa kelengkapan data lama, terutama blank
`item_description`. Membership change aman pada fixture `NO_BUKTI` unik, tetapi
fallback compatibility berbasis `NO_BUKTI` tidak lagi dipakai untuk mengganti
canonical identity. Implementasi kini mempertahankan transaction lama dan
menolak pemindahan Paket NUMBERED/FINAL.

### Safe-sync atomicity & membership reconciliation — 2026-09-19

Refresh raw mirror sekarang mempunyai transaction per source table yang mencakup
update metadata dan penggantian row. Jika fetch/encode/insert gagal, snapshot
lama dan metadata `ACTIVE` tetap utuh; projection tidak dipanggil karena job dan
command hanya melanjutkan setelah `synchronize()` berhasil.

Projection tidak lagi early-return tanpa menandai state ketika approved budget
context kosong. Existing fresh transaction menjadi `SOURCE_MISSING` dengan
`source_missing_since` idempotent, lalu kembali `ACTIVE` dan timestamp dikosongkan
ketika budget/source kembali. Tidak ada delete overlay/package pada jalur ini.

Membership change menggunakan policy konservatif: canonical source key baru tidak
meng-update legacy transaction lama. Transaction lama tetap menyimpan overlay dan
Paket, ditandai perlu rekonsiliasi, dan menerima event `SOURCE_ITEM_CHANGED`
dengan daftar item sebelum/sesudah. Sync berulang tidak menggandakan event yang
sama. Source baru tersedia melalui fresh projection; auto-remap Paket atau nomor
NUMBERED/FINAL tidak dilakukan.

Evidence 2026-09-19: focused Langkah 9 suite lulus 15 test / 132 assertions
(15 deprecated); related synchronization/workspace gate lulus 75 test / 640
assertions (75 deprecated); canonical `spj:verify --strict-style --skip-build`
lulus 16 test / 2.558 assertions (308 deprecated). Pint lulus dan `git diff
--check` bersih. Audit tenant read-only NPSN 10260756 memakai `query_only=ON`:
integritas PASS, foreign-key violations 0, 2025 fund 1 memiliki 104
transaksi legacy/fresh dan 268 item legacy/fresh, 2025 fund 12 17/17
transaksi dan 24/24 item; 2026 fund 1 belum memiliki baseline legacy dan
memiliki 175 fresh historis, 66 ACTIVE. Paket tenant yang diaudit 0, sehingga
status package lifecycle real-data tetap RVR.

P0 code/dependency integration gate sudah hijau pada current canonical code head. Aplikasi belum boleh disebut final release-ready karena generated-document real-data QA, browser/operator QA, Office/PDF visual fidelity, dan installed-runtime verification masih terpisah dari deterministic CI.

### Database Architecture V2-A2 — Tenant discovery & source identity preflight

Status: **READ-ONLY PREFLIGHT COMPLETE / V2-B REAL-TENANT EXECUTION BLOCKED**.

Database `10208183` ditemukan pada `D:\lrvProject\spj-bosp-data\school-databases`,
tetapi merupakan database legacy dari project sebelumnya, bukan tenant kedua
wajib pada project sekarang. Tenant V2-A/current-project `10260756` memiliki path runtime
registry `...\10260786\spj.sqlite`; mismatch tersebut diklasifikasikan sebagai
`DATABASE_REGISTRY_MISMATCH`, bukan diperbaiki pada tahap ini.

Read-only mapping V2-A: 291 transactions → 269 `EXACT`, 22 `DETERMINISTIC`,
0 partial/missing/ambiguous/legacy-only; seluruh 699 item source identity
ditemukan. 66 Paket `NUMBERED` termasuk 22 deterministic mappings dan 115
dokumen `NUMBERED`; tidak ada Paket `FINAL` pada tenant yang diaudit.

Raw mirror 56 tabel: 38 single-PK, 13 composite-PK, 1 deterministic fallback,
4 unstable fallback. Registry identity contract dan additive V2-B schema sudah
ditulis pada `docs/V2_A2_TENANT_DISCOVERY_SOURCE_IDENTITY_PREFLIGHT.md`.
Tidak ada original tenant yang dimutasi. Database `10208183` dipertahankan
sebagai external/orphan negative fixture; ia bukan blocker untuk tenant
current-project. V2-B/V2-C rehearsal tetap hanya boleh berjalan pada clone
isolated.

---

## Livewire / TALL migration — Phase 1 + Phase 2

### Phase 1 — mutation boundary audit

Status: **SOURCE AUDIT COMPLETE**.

Audit seluruh `app/Livewire/` menemukan 27 component dan mengklasifikasikan read-only/UI-state, context mutation, serta mutation sensitif. Detail matriks berada di `LIVEWIRE_MIGRATION_PLAN.md`.

### Phase 2 — authorization hardening

Status: **IMPLEMENTED + REGRESSION PASS + FULL CODE GATE PASS / BROWSER RUNTIME RVR**.

Source hardening:

```text
3c7be408f5a93795a597878b79f975373df24412
fix: harden Livewire mutation authorization
```

Critical-suite integration:

```text
701c73644b7dcf9d8aa710a842f28b2dad9a62d5
test: gate Livewire mutation authorization as critical
```

Boundary yang sudah ditutup:

- `UserManagement::{createUser,updateUser,deleteUser}` → ADMIN-only;
- `SchoolMaster::createSchool` → ADMIN-only;
- `DatabaseMaintenance::run` → ADMIN-only;
- `DatabaseResetForm::resetDatabase` → ADMIN + active-school match + exact confirmation;
- `DatabaseSchoolList::{activate,migrate}` → ADMIN-only;
- `DocumentStorageSettings::save` → OPERATOR/ADMIN sebelum reuse;
- `SchoolSelector::selectSchool` mempertahankan guard admin/own-school;
- `YearSelector::selectYear` tetap accepted context mutation.

`LivewireMutationAuthorizationTest` berada di SPJ Critical dan tetap tercakup oleh current green SPJ Critical gate #486. Rule arsitektur tetap: mutation Livewire sensitif harus authorize pada request action/policy/persistent mechanism yang benar-benar berlaku, bukan hanya mengandalkan route GET halaman awal.

Status area yang dimigrasikan:

- Transaksi: `TransactionsTable` untuk daftar dan `TransactionDetailWorkspace` untuk detail, filter/read/write state Livewire dengan mutasi tetap melalui service domain;
- RKAS: `RkasBudgetWorkspace` + `RkasBudgetFilter` read-only; tabel hierarki dan ringkasan berada di dalam workspace Livewire;
- SPJ Persiapan/Paket/Laporan/Monitoring: Livewire filters/lists + SPA tab navigation; detail Paket mutation-heavy tetap server-rendered;
- Pajak: `TaxFilter` read-only;
- Pegawai: `EmployeeDirectory` read-only;
- Data Sinkronisasi: `SyncedDataNavigation` UI-state/read-only;
- Database Aktif: read-only panels + role-hardened mutation components;
- User/Master Sekolah: role-hardened mutation components;
- Penyimpanan Dokumen: dormant component sudah OPERATOR/ADMIN-hardened sebelum reuse.

Browser/operator behavior tetap RVR sampai `GUI_RUNTIME_QA.md` dijalankan pada runtime aktual.

---

## GUI standardization

```text
GUI STANDARDIZATION CORE : ESTABLISHED
SOURCE-LEVEL REGRESSION  : COVERED oleh green gate #486 untuk current canonical code head
BROWSER DESKTOP/LAPTOP   : RVR ACTIVE
MOBILE/TABLET RUNTIME    : RVR / NON-BLOCKER untuk target desktop-laptop
```

Shared `x-ui` primitives, semantic theme tokens, icon registry, density/typography, responsive source guards, pagination theme, early theme init, dan top progress integration tersedia. Source-level PASS tidak sama dengan browser visual PASS.

SPA tab SPJ memakai `Livewire.navigate` dengan full-reload fallback; modal template preview memakai delegated listener agar bertahan setelah body swap. Repeated-navigation/modal runtime tetap perlu browser QA.

Pagination pada halaman `/penganggaran-rkas/saran` sudah PASS: Modul 1 dan Modul 2 memakai kontrol angka segmented yang terpisah, dengan konfirmasi ujicoba operator.

---

## P0-01 — Six-category SPJ end-to-end

```text
FUNCTIONAL SIX-CATEGORY : PASS pada current green gate
REAL-DATA BASELINE      : VERIFIED untuk scope yang tersedia
GENERATED-DOCUMENT DATA : ACTIVE / RVR
INSTALLED-RUNTIME       : DEFERRED
```

Kategori canonical:

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

Distribusi Paket 2026: BARANG 41, HONOR_PEGAWAI 12, JASA_LAINNYA 9, KONSUMSI 2, PEMELIHARAAN 2, SPPD 0. SPPD nyata tersedia pada data 2025; jangan fabrikasi SPPD 2026 untuk coverage.

---

## P0-02 — Document generator / template

**Status: FUNCTIONAL CODE GATE PASS / REAL-DATA OUTPUT QA ACTIVE / OFFICIAL-TEMPLATE VISUAL RVR.**

Current green gate mencakup source/regression untuk:

- individual template XLSX true single-sheet;
- canonical XLSX HTML preview;
- placeholder inspector;
- master template recomposition;
- preview/download tidak menerbitkan nomor;
- source/master tersimpan tidak dimutasi saat individual download;
- master parsial ditolak;
- DOCX individual;
- validator/template load path terbaru;
- generated document validation;
- `SpjSpreadsheetPdfWriter`/report-related test coverage yang berada dalam Unit/Feature suite.

Optimasi validator XLSX membaca daftar sheet lebih dahulu dan memuat hanya sheet canonical `readDataOnly` untuk individual validation. Angka developer sekitar 33 detik → 0,7 detik **bukan canonical browser/performance evidence** dan tetap memerlukan runtime profiling bila ingin dipromosikan sebagai performance claim.

Sisa utama: buka output/template nyata di Microsoft Excel/LibreOffice/PDF viewer dan verifikasi repair prompt, drawing, formula/reference, defined names, print area, page break, header/footer, serta hasil cetak.

---

## P0-03 — Numbering + registry + lifecycle

**Status: FUNCTIONAL PASS / REGISTRY CANONICAL.**

Source of truth:

```text
app/Services/SpjNumberingDocumentRegistry.php
```

Gate #486 mempertahankan regression suite yang mencakup first numbering, cancel/reserved sequence, tail rollback, quarter dependency, fund-source scope, NUMBERED description carve-out, FINAL lock, dan registry-based consumers.

`SpjDocumentTypeRegistry` tetap registry template/placeholder/output dan bukan source sequence numbering.

---

## P0-04 — Authorization

```text
HTTP/ROUTE AUTH BASELINE       : PASS
LIVEWIRE MUTATION BOUNDARY     : HARDENED
NEGATIVE ROLE REGRESSION       : PASS di current SPJ Critical gate
OVERALL CURRENT CODE GATE      : GREEN / CI #486
RUNTIME/BROWSER VERIFICATION   : RVR
```

Mutation user/role, school provisioning, database maintenance/reset/activation, dan document-storage setting harus tetap mengulang authorization pada boundary Livewire yang dieksekusi.

---

## P0-05 — Safe sync + reconciliation

**Status: FUNCTIONAL PASS / REAL-DATA RECONCILIATION ACTIVE.**

Contract tetap:

- ARKAS/BKU source readonly;
- operator SPJ overlay tidak dihapus oleh sync;
- source missing/returning mempertahankan identity;
- NUMBERED/FINAL tidak dimutasi diam-diam;
- tenant boundary `School + Fiscal Year + Fund Source`.

---

## P0-06 — Tenant/context isolation

**Status: FUNCTIONAL PASS.**

Boundary canonical:

```text
School + Fiscal Year + Fund Source
```

Livewire Phase 2 hanya menambah role enforcement dan tidak memindahkan scope logic ke Blade/Alpine.

---

## P0-07 — APP DATA / backup / reset / restore

**Status: FUNCTIONAL PASS / LIVEWIRE RESET ROLE HARDENING COMPLETE / INSTALLED-RUNTIME DEFERRED.**

`DatabaseResetForm` memerlukan ADMIN, active-school match, dan exact confirmation sebelum reset service dipanggil.

---

## P0-08 — Generic ARKAS Importer

Raw mirror generik mulai diimplementasikan pada branch `arkas-raw-mirror`:

- [x] metadata schema/tabel dan snapshot payload raw terpisah dari domain SPJ;
- [x] penandaan tabel `STALE` tanpa penghapusan snapshot;
- [x] command sinkronisasi seluruh tabel;
- [x] tombol GUI administrator yang mengantrikan mirror dengan konteks sekolah–tahun–sumber dana;
- [x] raw mirror memakai primary key tabel ARKAS sebagai identity row; composite primary key mempertahankan seluruh komponen sesuai ordinal PK;
- [x] projection `kas_umum` membentuk satu transaksi per `NO_BUKTI` dengan item per `ID_KAS_UMUM`;
- [x] `source_key` transaksi fresh mengikuti kontrak legacy `SHA256(sorted(ID_KAS_UMUM))`, sedangkan item memakai `ID_KAS_UMUM`;
- [x] projection transaksi fresh menandai source yang hilang tanpa menghapus overlay operator atau Paket SPJ dan mengosongkan `source_missing_since` ketika source kembali;
- [x] projection transaksi fresh membatasi `kas_umum` melalui snapshot `anggaran` aktif pada tahun dan sumber dana yang sama serta hanya memproyeksikan row BELANJA;
- [x] compatibility grouped `source_key` ke transaksi/overlay lama ditutup melalui `Transaction::forSourceIdentifier()`, sehingga overlay dan Paket lama tetap memakai identity transaksi yang sama;
- [x] bruto fresh menjumlahkan seluruh item grouped; pajak hanya mengambil Pajak Belanja Terima (`id_ref_bku` 10/30) untuk seluruh parent item pada source yang sama; netto = bruto - pajak;
- [x] regression source ditambahkan pada `SpjFreshTransactionCompatibilityTest` untuk overlay/Paket grouped source key serta gross/tax/net multi-item;
- [x] statistik count/gross/tax/net halaman transaksi memakai `filteredQuery()` yang sama dengan tabel, sehingga fund source, periode, pencarian, dan workflow status memakai dataset fresh yang sama;
- [x] regression `TransactionsWorkflowFilterTest` mengunci summary terhadap fund-source isolation, triwulan, status, dan pencarian;
- [x] langkah 6B projection catch-up setelah raw mirror memproyeksikan seluruh pasangan Fiscal Year + Fund Source valid pada tenant dari job GUI dan command, dengan isolation dan idempotensi;
- [x] regression multi-year/fund-source mengunci projection 2025+2026, fund-source isolation, dan idempotensi;
- [x] focused Laravel regression untuk projection/compatibility/statistik berjalan pada head ini: 7 test terlapor, 35 assertions, tanpa failure; PHPUnit hanya melaporkan deprecated metadata dan cache hasil tidak writable;
- [x] GUI Penganggaran RKAS membaca payload raw mirror bila proyeksi legacy belum tersedia;
- [x] modul Referensi ARKAS read-only menampilkan Program, Subprogram, Kegiatan, dan Rekening dari snapshot raw pada konteks aktif;
- [x] tab Acuan Barang menampilkan master aktif `ref_acuan_barang` yang di-inner-join ke `ref_rekening` hanya setelah pencarian, dengan status `Belum Ada Rekening` bila pasangan tidak tersedia;
- [x] selector konteks topbar menampilkan pasangan sumber dana dan tahun anggaran;
- [x] error 500 memiliki halaman fallback dark yang mandiri dan pencarian referensi aman terhadap field array;
- [ ] build dan real-data verification ARKASBridge paging pada database ARKAS nyata.

Fondasi schema fresh SPJ juga sudah ditambahkan secara terpisah melalui namespace
`spj_fresh_*`; projection awal transaksi/item, integrasi overlay operator, dan
pembentukan paket fresh sudah tersedia untuk tenant yang telah diproyeksikan.

Migrasi overlay operator reusable kini tersedia melalui `spj:migrate-overlay`.
Perintah memiliki mode dry-run, membuat backup tenant sebelum execute, menyimpan
report unmatched/ambiguous, dan tidak menulis database ARKAS/raw mirror.

Audit read-only terhadap database ARKAS asli dan APP-SPJ lama mengonfirmasi kontrak:
2025 Reguler memiliki 104 transaksi dari 268 item BELANJA, 2025 fund 12 memiliki
17 transaksi dari 24 item, sedangkan 2026 Reguler memiliki 66 transaksi dari 139
item BELANJA. Pada `kas_umum`, `ID_KAS_UMUM` unik per row dan
global fallback lama menghasilkan collision, sehingga koreksi identity menggunakan
primary key tabel diterapkan sebelum projection grouped.

Audit amount 2026 menghasilkan 66 transaksi / 139 item BELANJA dengan bruto
Rp138.195.000, pajak Rp7.677.946, dan netto Rp130.517.054. Angka 2026 fresh tidak
boleh disebut cocok dengan APP-SPJ lama karena tabel legacy tenant tidak memiliki
transaksi 2026; pembanding nominal identik yang benar-benar tersedia adalah 2025
fund 1 dan fund 12. Formula pajak menggunakan PBT saja dan tidak menghitung
PBS/setoran dua kali.

**Status: MULTI-YEAR CATCH-UP IMPLEMENTED / SOURCE CORRECTION + GROUPED COMPATIBILITY IMPLEMENTED / REAL-DATA CONTRACT VERIFIED / FOCUSED REGRESSION VERIFIED.**

Importer stateful tidak menjadi target migrasi Livewire opportunistic.

## V2-B isolated schema implementation — 2026-09-19

Additive V2-B schema PASS pada clone isolated/test saja. Guard, stable source identity
registry lifecycle, overlay tables, legacy bridge, composite identity ordering, and
immutability regression tersedia di `docs/V2_B_ISOLATED_SCHEMA_IMPLEMENTATION.md`.
Real tenant migration, cleanup legacy, central registry mutation, dan source ARKAS mutation
tetap BLOCKED sampai V2-A2 tenant discovery/source identity review selesai.

## V2-C full legacy migration rehearsal — 2026-09-19

V2-C3 **FINAL SEMANTIC GATE PASS ON A FRESH ISOLATED CLONE / PRODUCTION CUTOVER BLOCKED**.
Tenant A fresh clone memproses seluruh 291 legacy transaction menjadi 187 canonical
V2 transactions, 431 unique source links, 187 transaction overlays, 245 item
overlays, 291 provenance maps, dan 67 package V2 links. Source-resolution tetap
EXACT 269 dan DETERMINISTIC 22; 104 legacy rows menjadi provenance many-to-one,
bukan canonical transaction tambahan. Seluruh 22 deterministic mempertahankan
legacy `source_key`; 66 NUMBERED Paket dan 115 NUMBERED dokumen tetap identik.
Execute kedua mempertahankan seluruh canonical count dan lulus verify tanpa
menambah source link, overlay, item overlay, atau provenance map.

External/orphan fixture clone hanya menjalani source-unavailable dry-run karena
tidak memiliki raw mirror/source evidence yang cukup: 46 transaction menjadi
`SOURCE_MISSING`, tanpa mapping tebakan dan tanpa V2 mutation.

Evidence lengkap: `V2_C_TWO_TENANT_MIGRATION_REHEARSAL.md` dan report JSON lokal di
`storage/app/v2-c-rehearsal/reports/`. Original tenant, central registry, dan ARKAS
source tidak dimutasi. V2-C3 memisahkan source-resolution dari canonical
transaction identity: 187 canonical V2 transaction dan 104 legacy provenance
duplicate. Semantic verify menghitung gate dari evidence aktual; integrity, FK,
orphan, source adapter, context isolation, canonical identity, dan item-overlay
reconciliation semuanya PASS pada fresh clone. Provenance classification eksplisit
adalah ACTIVE_CANONICAL 187 dan LEGACY_DUPLICATE 104; metric many-to-one 104 tetap
terpisah. Transaction/item overlay conflict masing-masing 0.

## V2-D1 shadow read parity — 2026-09-19

Focused `V2DReadParityTest` PASS dengan 2 test, 20 assertions, dan 2 deprecations.
Parity membatasi fresh projection ke konteks yang sudah direpresentasikan V2,
membandingkan overlay operator dari legacy read model melalui provenance map, dan
tetap fail-closed saat overlay V2 diubah secara sintetis. Fixture sumber ARKAS
ditemukan dari path relatif proyek `../../backupdata/datasmp.db`; tidak ada env
manual yang diperlukan. Pint dan `git diff --check` juga PASS. Evidence ini hanya
functional local rehearsal; canonical read adapter dan production read-path
cutover belum dilakukan.


## V2-D2 canonical read adapter — 2026-09-19

`SpjV2CanonicalReadService` menyediakan jalur baca canonical V2 read-only tanpa
memindahkan controller/Livewire production. Boundary query adalah fiscal year +
fund source + source id + `ACTIVE_CANONICAL`; fakta ARKAS berasal dari source
identity/raw mirror, sedangkan operator-owned transaction/item fields hanya dari
overlay V2. Lookup mempertahankan `legacy_source_key` melalui provenance bridge
agar mapping DETERMINISTIC tidak kehilangan kompatibilitas identifier.

Runtime evidence D2 sudah tersedia: `php artisan test --compact
tests/Feature/V2DCanonicalReadAdapterTest.php` PASS dengan **3 test / 225
assertions** dan 3 deprecations. `vendor/bin/pint --dirty --format agent` PASS
dan `git diff --check` bersih. Regression membuktikan 187 canonical transaction,
context partition, source-link/item cardinality, aggregate gross/tax/net,
deterministic legacy-source-key → canonical transaction, dan ownership overlay.
Gate runtime D2 ditutup; production read-path tetap belum dialihkan.

## V2-D3 Paket/document relation parity — 2026-09-19

`SpjV2PackageDocumentParityService` dan `V2DPackageDocumentParityTest` sudah
mendapat runtime evidence lokal. Focused test PASS dengan **3 test / 42 assertions**
dan 3 deprecations; `vendor/bin/pint --dirty --format agent` PASS dan
`git diff --check` bersih.

Gate D3 membuktikan jalur legacy Paket
`transaction_id -> legacy_transaction_v2_map -> spj_transactions` konsisten
dengan `spj_packages.spj_transaction_id`, termasuk provenance
`LEGACY_DUPLICATE -> ACTIVE_CANONICAL` yang valid. Synthetic wrong-but-valid V2
link tetap fail-closed, synthetic FINAL package/document tetap immutable, dan
service parity tidak memutasi lifecycle. D3 ditutup sebagai **RUNTIME PASS**.
Production `SpjPackage::transaction()` dan write/lifecycle flow tetap legacy.

## V2-D downstream report/tax/period parity — 2026-09-19

Source gate berikutnya sudah ditambahkan. `SpjV2CanonicalReadService` kini
mengekspos breakdown pajak PPN/PPh/SSPD dari PBT raw `kas_umum` dan menelusuri
kode kegiatan langsung dari raw RKAS melalui
`rapbs_periode -> rapbs -> ref_kode`, tanpa membaca fakta tersebut dari legacy
transaction projection.

`SpjV2WorkflowParityService` + `V2DWorkflowParityTest` membandingkan current
legacy consumer contract dengan canonical V2 untuk transaction source fields,
gross/tax/net, tax components, report successful/cancelled/pending, seluruh
filter bulan/triwulan/semester, quarter closure-readiness inputs, serta realization
per kegiatan/rekening. Regression synthetic mengubah tax component tanpa mengubah
total pajak dan memindahkan tanggal source lintas quarter; keduanya wajib
fail-closed.

Runtime evidence tahap ini sekarang **PASS**. Pada 2026-09-19 focused suite
`V2CLegacyMigrationTest`, `V2DReadParityTest`, `V2DCanonicalReadAdapterTest`,
`V2DPackageDocumentParityTest`, dan `V2DWorkflowParityTest` lulus dengan **16 test /
378 assertions / 16 deprecations**, tanpa failure. Ini membuktikan ulang V2-C
migration/idempotency, D1 shadow parity, D2 canonical adapter, D3 package/document
bridge, serta downstream report/tax/period parity pada isolated clone. Production
read-path tetap BLOCKED sampai authorization/active-context cutover regression,
rollback strategy, dan source-quality/CI gate D4 selesai. Audit D4 juga memastikan
consumer mutation-heavy belum boleh membaca V2 overlay secara langsung selama
mutation operator masih menulis legacy tables saja; cutover awal harus read-only
atau memakai compatibility overlay/write-through eksplisit. Resolver production
juga tidak boleh mengasumsikan `source_id=1` karena source identity bukan bagian
dari active session context.

Source D4 step 1 sekarang sudah tersedia: `SpjV2CanonicalSourceResolver` +
`SpjReadPathSelector`, dengan config `SPJ_V2_READ_PATH=legacy` sebagai default.
Request V2 hanya eligible bila satu source canonical unik ditemukan; invalid config,
schema V2 belum ada, source kosong, atau source ambigu kembali ke legacy. Belum ada
production consumer yang dipindahkan. Runtime regression selector sekarang PASS:
`V2DReadPathSelectorTest` **7 test / 22 assertions / 7 deprecations**, Pint PASS,
dan `git diff --check` bersih.

D4 step 2 source sekarang tersedia untuk consumer read-only pertama: financial
summary pada tab Laporan SPJ. Hanya angka count/cancelled/bruto/pajak/neto dan
komponen pajak yang dapat membaca canonical V2; Paket list, pending, export,
monitoring, activity/account labels, serta seluruh mutation tetap legacy.
Regression `V2DReportSummaryCutoverTest` sekarang mengunci dua lapisan:
selector/source eligibility dan **live consumer parity** terhadap summary production
legacy pada `ActiveSpjContext`. Run awal menemukan fixture nyata dengan canonical
numbered count 66 tetapi live legacy count 0 pada context yang sama; ini bukan
alasan mengubah angka legacy, melainkan bukti bahwa provenance/workflow parity belum
cukup untuk production cutover. Consumer sekarang fail-closed ke legacy pada
context mismatch atau financial drift. Positive V2 path hanya diuji pada isolated
clone setelah legacy package-context disejajarkan eksplisit. Focused runtime gate
sekarang PASS: **16 test / 354 assertions / 16 deprecations**, Pint PASS, dan
`git diff --check` bersih. D4 step 2 therefore FUNCTIONAL/RUNTIME PASS sebagai
fail-safe consumer implementation, tetapi **effective production cutover masih
BLOCKED** sampai akar live-context mismatch nyata diselesaikan tanpa rewrite
source/protected lifecycle.

Audit source mengonfirmasi akar mismatch: V2-C menentukan
`effective_fiscal_year_id` dari tahun `transaction_date` + fund source, sedangkan
production `Transaction::forSpjContext()` masih memakai
`transactions.fiscal_year_id` legacy. `classifyCanonicalContext()` memang
menerima ACTIVE_CANONICAL ketika context efektif valid walaupun legacy fiscal-year
id stale, dan `migrateOne()` hanya menulis context efektif ke V2/provenance,
bukan mengubah transaksi legacy. Jadi mismatch 66 canonical numbered vs 0 live
legacy adalah boundary transisi yang nyata, bukan bug selector D4.

D4 step 3 source sekarang tersedia melalui
`SpjV2EffectiveContextCompatibilityService`. Service ini read-only dan tidak
mengubah `transactions.fiscal_year_id`, Paket, dokumen, numbering, snapshot,
atau V2 rows. `audit()` menginventarisasi provenance + Paket menjadi
`ALIGNED`, `STALE_LEGACY_FISCAL_YEAR`, atau `UNSAFE`; `resolve()`
menerjemahkan satu effective context eksplisit
`fiscal_year_id + fund_source_id + source_id` kembali ke legacy provenance dan
Paket secara deterministic. Missing provenance, cross-context/fund mismatch,
wrong Paket bridge, multiple Paket per canonical transaction, atau schema V2
tidak lengkap semuanya fail-closed. `LEGACY_DUPLICATE` hanya eligible bila
tetap menunjuk ACTIVE_CANONICAL yang sama.

Regression `V2DEffectiveContextCompatibilityTest` sekarang memiliki runtime
evidence. Focused gate PASS dengan **16 test / 174 assertions / 16 deprecations**,
Pint PASS, dan `git diff --check` bersih.

Exact inventory isolated fixture:

```text
status                       : COMPATIBLE_STALE_CONTEXT
Paket total                  : 67
Paket NUMBERED               : 66
Paket FINAL                  : 0
Paket aligned                : 0
Paket stale legacy FY        : 67
Paket unsafe                 : 0
Paket duplicate provenance   : 1
provenance aligned context   : 121
provenance stale legacy FY   : 170
provenance fund mismatch     : 0
provenance LEGACY_DUPLICATE  : 104
```

D4 step 3 ditutup sebagai **RUNTIME PASS**. Temuan terpenting: seluruh 67 Paket
existing pada fixture bersifat stale hanya pada legacy `fiscal_year_id`, bukan
unsafe dan bukan cross-fund. Karena itu Step 4 harus membuat compatibility read
membership berbasis effective-context/provenance; jangan memperbaikinya dengan
mass rewrite `transactions.fiscal_year_id`.

D4 step 4 sekarang **RUNTIME PASS**. Focused gate
`V2DReadPathSelectorTest`, `V2DPackageDocumentParityTest`,
`V2DReportSummaryCutoverTest`, `V2DEffectiveContextCompatibilityTest`, dan
`V2DPackageReadMembershipTest` PASS dengan **19 test / 216 assertions / 19
deprecations**; Pint PASS dan `git diff --check` bersih.
`SpjV2PackageReadMembershipService` terbukti mengembalikan exact effective-context
Paket membership, 66 NUMBERED, fund-source isolation, immediate rollback config,
wrong-bridge fail-closed, dan tanpa mutation protected state.

Production Paket list/report table belum dialihkan karena row tersebut menyediakan
Buka Paket/Preview/Download, sementara action/detail guard sebelumnya masih
memvalidasi legacy transaction context.

D4 step 5 **RUNTIME PASS**. Focused gate
`V2DReadPathSelectorTest`, `V2DPackageDocumentParityTest`,
`V2DEffectiveContextCompatibilityTest`, `V2DPackageReadMembershipTest`,
`V2DPackageReadContextTest`, `SpjDocumentGeneratorHardeningTest`, dan
`SpjPreviewExcelParityTest` PASS dengan **27 test / 353 assertions / 27
deprecations**; Pint PASS dan `git diff --check` bersih. Delapan jalur
Preview/Download read-only sekarang terbukti memakai effective-context secara
fail-closed tanpa persistence mutation.

D4 step 6 **RUNTIME PASS**. Focused gate
`V2DPackageReadMembershipTest`, `V2DPackageReadContextTest`,
`V2DPackageWorkspaceReadOnlyTest`, `SpjPackageNavigationContextTest`,
`SpjMainTabsRenderingTest`, `SpjDocumentGeneratorHardeningTest`, dan
`SpjPreviewExcelParityTest` PASS dengan **25 test / 351 assertions / 25
deprecations**; Pint PASS dan `git diff --check` bersih. Stale Paket sekarang
dapat dibuka hanya pada dedicated read-only workspace tanpa memperluas mutation
authorization.

D4 step 7 **RUNTIME PASS**. Focused gate
`V2DPackageReadMembershipTest`, `V2DPackageReadContextTest`,
`V2DPackageWorkspaceReadOnlyTest`, `V2DPackageListCutoverTest`,
`SpjPackageNavigationContextTest`, `SpjMainTabsRenderingTest`,
`SpjDocumentGeneratorHardeningTest`, dan `SpjPreviewExcelParityTest` PASS
dengan **29 test / 583 assertions / 29 deprecations**; Pint PASS dan
`git diff --check` bersih. Daftar Paket + package-only summary metrics sekarang
memiliki runtime evidence untuk effective-context membership + config rollback.

D4 step 8 source sekarang melakukan atomic cutover pada tabel Paket Laporan +
financial summary. Effective Paket IDs dipakai untuk menghitung live consumer
summary, lalu canonical V2 summary wajib exact match sebelum **keduanya** switch.
Jika raw-source drift atau bridge tidak aman, tabel dan summary bersama-sama
fallback legacy. Filter bulan/triwulan/semester ikut effective membership; row
ditandai `Baca saja`. Pending transaction, activities/accounts, monitoring,
export, settlement, numbering, lifecycle, dan mutation lain tetap legacy.
`V2DReportSummaryCutoverTest` dipromosikan dari stale-context fallback menjadi
effective-context compatibility, dan regression baru
`V2DReportPackageListCutoverTest` ditambahkan.

Percobaan runtime pertama Step 8 gagal pada wiring DI, bukan pada assertion parity:
**8 failure / 627 assertions / 34 deprecations** karena
`ExtendedSpjReportUseCase` masih memanggil parent constructor dengan 2
dependency setelah `SpjReportUseCase` membutuhkan
`SpjV2PackageReadMembershipService` sebagai dependency ketiga. Commit
`956ae6c2` memperbaiki constructor subclass dan meneruskan dependency tersebut.
Clean rerun setelah fix DI PASS dengan **42 test / 711 assertions / 42
deprecations**; Pint PASS dan `git diff --check` bersih. D4 step 8 sekarang
**RUNTIME PASS**.

D4 step 9 source sekarang mengalihkan jalur read-only **Honor Pegawai** dan
**Jasa Lainnya** pada toolbar Laporan ke effective legacy-transaction membership
ketika selector V2 RESOLVED. `ExtendedSpjReportUseCase` tetap membaca seluruh
operator-owned detail Honor/Jasa dari tabel legacy; hanya scope transaksi yang
berasal dari provenance/effective context. Select, compose, dan export memakai
boundary yang sama; forged/outside transaction ID tetap ditolak dan config
`legacy` langsung mengembalikan behavior lama. Regression
`V2DExtendedReportContextCutoverTest` memakai synthetic operator overlays pada
clone yang dipaku ke stale effective-context nyata. Focused runtime gate Step 9 PASS dengan **20 test / 241 assertions / 20 deprecations**; Pint PASS dan `git diff --check` bersih. D4 step 9 sekarang **RUNTIME PASS**.

D4 step 10 source sekarang mengalihkan consumer read-only **Pajak** ke
effective-context hanya setelah `SpjV2TaxReadContextService` membuktikan
representative legacy per canonical transaction dan seluruh field yang dipakai
UI—termasuk komponen pajak—masih exact-match terhadap raw canonical. Duplicate
provenance tidak didouble-count. Bila raw-tax/source-key/identity drift, seluruh
Pajak consumer fallback legacy. Row V2 diberi label `Baca saja` dan Detail
Transaksi diarahkan lewat `source_key` ke fresh effective-context read model,
bukan legacy ID stale. Regression `V2DTaxReadContextCutoverTest` ditambahkan;
`TaxFilterLivewireTest` dan `TransactionDetailWorkspaceAuthorizationTest` tetap
menjadi compatibility/action-boundary regression. Focused runtime gate Step 10 PASS dengan **33 test / 199 assertions /
33 deprecations**; Pint PASS dan `git diff --check` bersih. D4 step 10 sekarang
**RUNTIME PASS**.

Audit consumer berikutnya menetapkan tab **Monitoring** tetap legacy-authoritative
untuk saat ini. Walaupun antrean pending read-only secara visual, tab yang sama
memuat Bulk Final, Penomoran Triwulan, Tutup/Buka Periode, dan row action menuju
Checklist/Persiapan. Cutover membership tanpa write-path/effective-context
authorization akan menciptakan split read/write context. Karena itu Monitoring
ditandai **DEFERRED/BLOCKED FOR READ CUTOVER**, bukan dipaksa masuk V2.

D4 step 11A source sekarang menambahkan `SpjV2MutationContextService` sebagai
boundary write-path transisi pertama. Scope sengaja hanya Checklist dan
`DRAFT -> READY`: stale legacy fiscal-year hanya boleh dimutasi bila selector V2,
effective package membership, exact provenance/package bridge, fund source,
source-reconciliation state, dan live canonical source facts semuanya aman.
Fiscal year legacy hanya dinormalisasi **in memory** untuk validasi dan tidak
pernah dipersist. `SpjPackageLifecycleUseCase` memakai boundary ini sebelum
READY; audit `PAKET_READY` memakai effective fiscal year. Compatibility Paket
hanya membuka Checklist, sedangkan Isian Manual, numbering, FINAL, settlement,
cancel/replace, bulk final, dan period mutation tetap tertutup. Regression
`V2DMutationContextReadyTest` ditambahkan dengan config rollback, wrong bridge,
raw-source drift, immutability, audit-year, dan legacy-aligned compatibility.
Percobaan runtime pertama Step 11A belum PASS: **2 failed / 534 assertions /
40 deprecations**. Kedua failure berasal dari bug boundary persistence yang sama:
metadata transient `mutation_context_*` dipasang sebagai Eloquent attribute,
sehingga saat status Paket disimpan ke READY Eloquent mencoba menulis kolom yang
tidak ada (`mutation_context_path`, dst.). Fix sudah mengubah metadata tersebut
menjadi relation in-memory `v2MutationContext`, dan regression kini memastikan
metadata itu tidak pernah muncul sebagai SQL attribute/dirty field. Clean rerun sudah hijau: focused `V2DMutationContextReadyTest`
PASS **8 test / 90 assertions / 8 deprecations**; full Step 11A gate PASS **42
test / 556 assertions / 42 deprecations**. Pint PASS, `npm run build` PASS,
dan `git diff --check` bersih. D4 Step 11A sekarang **RUNTIME PASS**.

D4 Step 11B source sekarang membuka write boundary berikutnya secara terbatas
untuk **operator overlay Paket DRAFT/READY**. `authorizePackageWrite()` memakai
effective-context/provenance/source-parity yang sama tetapi sengaja tidak
menormalisasi `transactions.fiscal_year_id`, bahkan in-memory, karena
`UpdateSpjPackageDetailsUseCase` dan child synchronizer benar-benar menyimpan
legacy Transaction/relations. `SpjPackageCategoryUseCase` juga memakai boundary
ini; perubahan kategori READY tetap turun ke DRAFT untuk revalidasi. Audit
`PERBARUI_ISIAN`/ `UBAH_KATEGORI` memakai effective fiscal year.

Workspace stale-context tidak dibuka penuh. View baru
`spj.package-compat-edit` hanya merender Isian Manual canonical untuk DRAFT/READY;
Penomoran, FINAL, settlement, bulk-final, dan maintenance material/labor linkage
tetap di luar gate. NUMBERED effective-context juga tetap ditolak untuk overlay
write.

Regression `V2DPackageOverlayWriteCutoverTest` sudah diperkeras pada
`3aa331a` dan `eb96214`: selain positive DRAFT/READY dan config rollback,
gate sekarang secara eksplisit menolak wrong Paket/V2 bridge, unresolved
reconciliation, `SOURCE_MISSING`, synthetic raw-source financial drift,
overlay write pada NUMBERED, serta category mutation pada NUMBERED; seluruh
negative path juga mensyaratkan tidak ada audit mutation palsu.

Runtime evidence branch ini sekarang PASS: `V2DPackageOverlayWriteCutoverTest`
lulus **9 test / 109 assertions / 9 deprecations**; regression gabungan
Step 11A/read-context/package manual/category/transaction-boundary/
authorization/NUMBERED lulus **59 test / 644 assertions / 59 deprecations**.
Pint, `php artisan view:cache`, `npm run build`, dan `git diff --check` juga
lulus. Step 11B tetap terbatas pada Paket DRAFT/READY; numbering, FINAL,
settlement, bulk-final, dan period-close tidak dibuka.

---

### V2-D Step 11C — NUMBERED narrative correction compatibility gate

Audit lifecycle-specific setelah Step 11B menemukan gap pada Paket `NUMBERED`
stale-context: jalur legacy mengizinkan koreksi `payment_description`, tetapi
jalur effective-context masih menolak seluruh koreksi. Perubahan minimal kini
mengizinkan hanya koreksi narasi tersebut setelah exact Paket/provenance bridge,
fund source, reconciliation, dan live source-fact parity lulus melalui boundary
Step 11B.

Regression focused lulus **3 test / 32 assertions**; related lifecycle,
category, dan READY mutation lulus **16 test / 129 assertions**. Pint, Blade
`view:cache`, `npm run build`, dan `git diff --check` juga lulus. Status Paket,
nomor, dokumen, kategori, pembayaran, dan fiscal-year legacy tetap tidak
berubah; FINAL, numbering, settlement, bulk-final, dan period-close tidak
dibuka.

Follow-up Step 11C kini menutup boundary Detail Transaksi effective-context.
Resolver fail-closed memeriksa membership Paket, provenance/package bridge,
fund source, reconciliation/source status, source facts, dan seluruh item
source facts sebelum stale legacy transaction dibuka. Livewire hanya menulis
overlay legacy `payment_description`/`item_description`, mencatat audit pada
effective fiscal year, dan tidak mengubah status/nomor/fiscal-year legacy.
Focused + related regression lulus **25 test / 193 assertions / 25
deprecations**; Pint, `view:cache`, `npm run build`, dan `git diff --check`
lulus. FINAL, numbering, settlement, bulk-final, dan period-close tetap
tertutup.

Audit gate berikutnya, **V2-D numbering lifecycle: BLOCKED / DEFERRED**.
Regression numbering/lifecycle legacy lulus **64 test / 528 assertions / 64
deprecations**, tetapi belum membuktikan effective-context numbering. Single
numbering, numbering gate/order, quarter selection, sequence, dan audit masih
berbasis `transactions.fiscal_year_id` legacy; exact effective
provenance/package bridge, item/source parity, package/document completeness,
effective period constraints, collision isolation, dan effective-fiscal-year
audit belum menjadi authorization boundary. Quarter batch juga belum atomic
end-to-end saat iterasi gagal. Regression tambahan membuktikan stale
effective-context single numbering tetap tertutup (**1 test / 9 assertions**).
Tidak ada boundary numbering yang dibuka dan tidak ada mass rewrite fiscal
year legacy.

## Prioritas kerja aktif

P0 integration/dependency repair dan Phase 2 authorization sudah selesai. Prioritas aktif pada branch migrasi ini:

1. definisikan overlay write-through/compatibility sebelum consumer mutation-heavy membaca V2 overlay;
3. **Generated-document real-data/operator QA** untuk Paket nyata yang tersedia;
4. **browser/operator QA desktop-laptop** berdasarkan `GUI_RUNTIME_QA.md`, khususnya repeated `Livewire.navigate`, SPA tab SPJ, modal preview, pagination, dropdown, dan filter URL state;
5. **Office/PDF visual-output QA** untuk individual template, master terbaru, XLSX/PDF hasil generate, print area/page break/header/footer;
6. lanjutkan JASA_LAINNYA multi-penerima dan PEMELIHARAAN bahan+upah pada output nyata bila ditemukan mismatch;
7. setelah operator/runtime flow stabil, baru pertimbangkan kandidat migrasi Livewire read-only berikutnya seperti Rekonsiliasi.

---

## Open verification / release blockers

Code/dependency integration gate **bukan lagi blocker**. Blocker/verifikasi tersisa:

- generated-document real-data per kategori masih RVR/active;
- individual template/master template Office visual QA masih RVR;
- preview HTML/template nyata pada browser aktual masih RVR;
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
5. Setiap source/runtime change setelah gate `ba8fa0b...` membutuhkan gate hijau baru sebelum menjadi canonical functional HEAD.
6. Mutation Livewire sensitif harus mempunyai authorization boundary pada action request.
7. GUI source PASS tidak sama dengan browser visual PASS.
8. Bila business rule berubah, sinkronkan `SPJ_DESIGN_DECISIONS.md` dan feature guide terkait.
9. Metadata numbering baru/berubah dimulai dari `SpjNumberingDocumentRegistry`.
10. Setelah contract inti stabil, gunakan `operator flow -> temukan bug nyata -> perbaiki -> focused regression bila perlu`.

---

## Dashboard operator reference alignment 2026-09-15 (uncommitted)

`ProductivityDashboardDataService` tetap menjadi pemilik data, sedangkan `livewire/dashboard-workspace` kini memakai struktur dan hierarki visual dari `dashboard-productivity.blade.php` sebagai dashboard aktif:

- Progres memakai basis tunggal (transaksi kerja memiliki rincian); bug double-counting transaksi+paket ditutup.
- `without_package` selaras dengan antrean (keduanya mensyaratkan rincian).
- `attentionCount` dihitung distinct, bukan penjumlahan.
- ±35 query per buka dipadatkan menjadi segelintir agregat (budget ≤12 query, dikunci test).
- Validasi paket per baris antrean dihapus dari dashboard (tetap jalan di halaman checklist).
- Dashboard aktif menampilkan empat ringkasan produktivitas, panel prioritas, progres keseluruhan, pekerjaan lanjutan, transaksi berikutnya, antrean kerja, prioritas penomoran, dan kondisi sistem.
- Langkah berikut antrean memakai badge status (`SOURCE_MISSING`/`RECONCILIATION`/`BELUM_LENGKAP`); kartu pipeline menampilkan tombol aksi; progress bar memakai `role="progressbar"`; header menampilkan konteks sekolah·TA·sumber dana.
- Field mati dihapus dari kontrak data (`tone`, `completion_checks`, `queue_state`, `latestOperation`, `action` pipeline kini dirender).

Status: **FUNCTIONAL PASS** berdasarkan persetujuan pengguna dan evidence berikut: `tests/Feature/DashboardMetricsTest.php` 5 passed (21 assertions; 5 deprecated), Pint passed, `view:cache` sukses, `npm run build` sukses, dan `git diff --check` bersih. `resources/views/dashboard.blade.php` proteksi tidak diubah. Browser/operator visual QA dan timing real-data tetap RVR.

### Effective numbering authorization boundary — PASS; issuance tetap BLOCKED

`SpjV2NumberingAuthorizationService` kini menyediakan preflight read-only yang
fail-closed untuk selector V2, effective package membership, exact provenance
bridge, fund/source reconciliation, canonical transaction dan item parity,
registry document relation, lifecycle READY, serta effective period proof.
Focused runtime regression lulus **2 test / 72 assertions / 2 deprecations**.
Resolver canonical kini membuktikan effective fiscal year dari
`spj_transactions.fiscal_year_id` canonical + `FiscalYear.year`, serta quarter
dari canonical transaction date dengan boundary Q1-Q4. Authorization failure
tidak mengubah nomor, status, fiscal year legacy, dokumen, atau audit. Effective
numbering authorization boundary = **PASS** dan effective year/quarter
resolution = **PASS**; effective-context numbering issuance tetap **BLOCKED /
DEFERRED** sampai sequence/collision, completeness, atomic rollback, dan
effective audit trail ditutup.

Sequence gate terbaru: effective sequence/collision reservation/completion =
**PASS**. Focused sequence evidence **3 tests / 96 assertions / 3
deprecations**; related V2-D + legacy numbering/lifecycle evidence **68 tests /
633 assertions / 68 deprecations**. Scope tetap `effective fiscal year + fund
source + document type + period_key`; reservation atomic/idempotent dan tidak
menerbitkan document number. Actual effective-context issuance tetap
**BLOCKED / DEFERRED** sampai atomic end-to-end issuance, completeness/post-
condition, dan effective audit trail.
