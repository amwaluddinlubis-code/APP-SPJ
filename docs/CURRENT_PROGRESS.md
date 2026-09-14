# SPJ BOSP Web — Current Progress / Open Issues

Terakhir diperbarui: **2026-09-14**

Dokumen ini adalah sumber status release utama untuk branch `gui-standardization`. Detail gate/command verification berada di `P0_VERIFICATION_KIT.md`; prioritas berada di `DEVELOPMENT_ROADMAP.md`; kontrak bisnis permanen berada di `SPJ_DESIGN_DECISIONS.md`.

Definisi status:

- **FUNCTIONAL PASS**: dibuktikan oleh source + deterministic test/CI yang benar-benar dijalankan;
- **REAL-DATA VERIFIED**: dibuktikan pada database sekolah nyata atau isolated copy tanpa fabrikasi data;
- **RVR**: masih memerlukan real-value/runtime/operator verification;
- **DEFERRED**: sengaja tidak menjadi fokus aktif saat ini, bukan berarti PASS.

---

## Checkpoint terbaru

### Latest successful canonical code gate

```text
LATEST SUCCESSFUL CODE HEAD: 887d0219142d634e6a85b6672d3bffb02b5b1584
LATEST SUCCESSFUL CODE GATE: CI #480 / run 34839580942 / SUCCESS
WORKFLOW                   : SPJ Critical Verification
REPOSITORY PINT            : ADVISORY / 5 pre-existing style issues
FRONTEND BUILD             : PASS
BLADE COMPILE              : PASS
SPJ CRITICAL               : 287 PASS / 2236 assertions
FULL UNIT                  : 60 PASS / 203 assertions
FULL FEATURE               : 411 PASS / 2972 assertions
```

CI #480 adalah gate sukses terbaru yang membuktikan blocking frontend build, Blade compile, SPJ Critical, full Unit, dan full Feature untuk code head `887d0219...`. Detail authoritative berada di `P0_VERIFICATION_KIT.md` §1.

Repository Pint belum clean: lima issue lama tetap advisory. Jangan mengubah status itu menjadi PASS/clean sampai benar-benar diperbaiki.

### Integration repair #478 → #480

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

Tidak ada business rule, lifecycle, numbering, sync, tenant ownership, atau authorization contract yang dilonggarkan untuk mendapatkan gate hijau.

Commit dokumentasi setelah gate #480 tidak menggantikan code gate `887d0219...`.

### Laravel 13 upgrade — local verification 2026-09-14

`composer.json` dinaikkan: `php ^8.3`, `laravel/framework ^13.0`, `laravel/tinker ^3.0`, `phpunit/phpunit ^12.0`, `branch-alias 13.x-dev`. Hasil resolve: framework `v13.31.0`, Livewire `v3.8.8`, Filament `v4.13.1`, Boost `v2.8.1`, Pint `v1.32.1`, Symfony 7 → 8, Guzzle 7 → 8. Aset JS Filament ter-publish ulang via `filament:upgrade`. `composer update` memakai `--ignore-platform-req=ext-intl` karena PHP lokal (herd-lite 8.4.0) tidak menyertakan `intl` — kondisi yang sama dengan lock sebelumnya.

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

Selisih +1 test vs gate #480 berasal dari commit `5fa98ed` (satu head di depan gate), bukan dari upgrade framework. Tidak ada business rule, lifecycle, numbering, sync, tenant ownership, atau authorization contract yang diubah. CI gate canonical tetap #480 sampai workflow CI dijalankan ulang pada head baru.

---

## Status release saat ini

```text
FUNCTIONAL BASELINE : PASS pada 887d0219... / CI #480
CURRENT CODE GATE   : GREEN / CI #480
REAL-DATA CORE      : VERIFIED untuk audit/preflight + isolated numbering/cancel/tail rollback yang terdokumentasi
GENERATED OUTPUT    : RVR / OPERATOR QA ACTIVE
TEMPLATE OFFICE QA : RVR
BROWSER/RUNTIME     : RVR ACTIVE
LIVEWIRE MIGRATION : PHASE 1 AUDIT + PHASE 2 AUTH HARDENING COMPLETE / CODE GATE PASS
FINAL RELEASE       : NOT YET
```

P0 integration gate sudah kembali hijau. Aplikasi belum boleh disebut final release-ready karena generated-document real-data QA, browser/operator QA, Office/PDF visual fidelity, dan installed-runtime verification masih terpisah dari deterministic CI.

---

## Livewire / TALL migration — Phase 1 + Phase 2

### Phase 1 — mutation boundary audit

Status: **SOURCE AUDIT COMPLETE**.

Audit seluruh `app/Livewire/` menemukan 25 component dan mengklasifikasikan read-only/UI-state, context mutation, serta mutation sensitif. Detail matriks berada di `LIVEWIRE_MIGRATION_PLAN.md`.

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

`LivewireMutationAuthorizationTest` berada di SPJ Critical dan tetap PASS pada green gate #480. Rule arsitektur tetap: mutation Livewire sensitif harus authorize pada request action/policy/persistent mechanism yang benar-benar berlaku, bukan hanya mengandalkan route GET halaman awal.

Status area yang dimigrasikan:

- Transaksi: `TransactionsTable` read-only filter/search/pagination;
- RKAS: `RkasBudgetFilter`, `RkasBudgetTable`, `RkasTable` read-only;
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
SOURCE-LEVEL REGRESSION  : COVERED oleh green gate #480 untuk current code head
BROWSER DESKTOP/LAPTOP   : RVR ACTIVE
MOBILE/TABLET RUNTIME    : RVR / NON-BLOCKER untuk target desktop-laptop
```

Shared `x-ui` primitives, semantic theme tokens, icon registry, density/typography, responsive source guards, pagination theme, early theme init, dan top progress integration tersedia. Source-level PASS tidak sama dengan browser visual PASS.

SPA tab SPJ memakai `Livewire.navigate` dengan full-reload fallback; modal template preview memakai delegated listener agar bertahan setelah body swap. Repeated-navigation/modal runtime tetap perlu browser QA.

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

Gate #480 mempertahankan regression first numbering, cancel/reserved sequence, tail rollback, quarter dependency, fund-source scope, NUMBERED description carve-out, FINAL lock, dan registry-based consumers.

`SpjDocumentTypeRegistry` tetap registry template/placeholder/output dan bukan source sequence numbering.

---

## P0-04 — Authorization

```text
HTTP/ROUTE AUTH BASELINE       : PASS
LIVEWIRE MUTATION BOUNDARY     : HARDENED
NEGATIVE ROLE REGRESSION       : PASS di current SPJ Critical gate
OVERALL CURRENT CODE GATE      : GREEN / CI #480
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

**Status: FUNCTIONAL HARDENING PASS / OPERATOR DATA TEST ACTIVE.**

Importer stateful tidak menjadi target migrasi Livewire opportunistic.

---

## Prioritas kerja aktif

P0 integration repair dan Phase 2 authorization sudah selesai. Prioritas berikutnya:

1. **Generated-document real-data/operator QA** untuk Paket nyata yang tersedia;
2. **browser/operator QA desktop-laptop** berdasarkan `GUI_RUNTIME_QA.md`, khususnya repeated `Livewire.navigate`, SPA tab SPJ, modal preview, pagination, dropdown, dan filter URL state;
3. **Office/PDF visual-output QA** untuk individual template, master terbaru, XLSX/PDF hasil generate, print area/page break/header/footer;
4. lanjutkan JASA_LAINNYA multi-penerima dan PEMELIHARAAN bahan+upah pada output nyata bila ditemukan mismatch;
5. setelah operator/runtime flow stabil, baru pertimbangkan kandidat migrasi Livewire read-only berikutnya seperti Rekonsiliasi;
6. lima Pint advisory lama dapat ditutup pada maintenance window terpisah karena bukan blocker workflow saat ini.

---

## Open verification / release blockers

Code integration gate **bukan lagi blocker**. Blocker/verifikasi tersisa:

- generated-document real-data per kategori masih RVR/active;
- individual template/master template Office visual QA masih RVR;
- preview HTML/template nyata pada browser aktual masih RVR;
- browser/operator desktop-laptop QA masih RVR;
- official-template print/layout/output QA masih RVR;
- installed-runtime checks masih DEFERRED;
- mobile/tablet runtime QA tetap RVR/non-blocker untuk target desktop-laptop;
- repository-wide Pint mempunyai 5 issue lama, tetapi saat ini bersifat advisory, bukan blocking gate.

---

## Aturan evidence dan pengembangan

1. Jangan mengubah source data agar test/audit PASS.
2. Jangan memakai deterministic fixture sebagai bukti real-data verified.
3. Jangan memakai screenshot/UI appearance sebagai pengganti backend regression.
4. Jangan menyatakan CI baru untuk commit docs-only.
5. Setiap source change setelah gate `887d0219...` membutuhkan gate hijau baru sebelum menjadi canonical functional HEAD.
6. Mutation Livewire sensitif harus mempunyai authorization boundary pada action request.
7. GUI source PASS tidak sama dengan browser visual PASS.
8. Bila business rule berubah, sinkronkan `SPJ_DESIGN_DECISIONS.md` dan feature guide terkait.
9. Metadata numbering baru/berubah dimulai dari `SpjNumberingDocumentRegistry`.
10. Setelah contract inti stabil, gunakan `operator flow -> temukan bug nyata -> perbaiki -> focused regression bila perlu`.