# Rencana Migrasi Livewire (TALL) — Status, Audit Boundary, dan Urutan

Terakhir diverifikasi: **2026-09-14** pada branch `gui-standardization`, canonical code gate HEAD `887d0219142d634e6a85b6672d3bffb02b5b1584`, dengan pembaruan stack Laravel 13 + pure TALL terverifikasi lokal pada `a4dd395`.

Dokumen ini adalah sumber teknis untuk status migrasi Livewire/TALL. Status release keseluruhan berada di `CURRENT_PROGRESS.md`; prioritas berada di `DEVELOPMENT_ROADMAP.md`; evidence gate berada di `P0_VERIFICATION_KIT.md`.

> Evidence saat ini: Phase 2 authorization hardening sudah selesai dan tetap PASS pada green repository gate CI #480. SPJ Critical, full Unit, dan full Feature semuanya hijau. Stack naik ke Laravel 13 + pure TALL (Filament/Sail mati dicopot pada `a4dd395`, terverifikasi lokal: 288/60/412 PASS, build + view:cache PASS). Browser/operator runtime tetap RVR. CI gate canonical tetap #480 sampai workflow dijalankan ulang.

## 1. Prinsip canonical migrasi

1. Laravel/use case/service tetap memiliki business rule, authorization, persistence, tenant boundary, numbering, sync, dan lifecycle.
2. Livewire memiliki reactive server-backed state, filter, pagination, dan action UI yang memang dipindahkan ke component.
3. Alpine hanya memiliki interaction client-side ringan; jangan membuat Alpine dan Livewire memiliki state yang sama.
4. Query domain harus tetap memakai query/use case/service canonical; jangan menduplikasi aturan domain di component.
5. State filter bookmarkable memakai `#[Url]`.
6. Mutation Livewire **wajib mempunyai authorization boundary yang berlaku pada request Livewire**, bukan hanya mengandalkan route GET yang merender component.
7. Tenant canonical tetap `School + Fiscal Year + Fund Source`; migrasi UI tidak boleh melonggarkan scope tersebut.
8. Browser/runtime PASS tetap RVR sampai diuji pada browser aktual.

## 2. Klasifikasi audit

```text
READ-ONLY / UI-STATE       = tidak menulis persistence/domain; hanya query, filter, pagination, tab, atau detail baca.
CONTEXT MUTATION ACCEPTED  = mengubah session context sesuai flow yang memang tersedia untuk user tersebut.
MUTATION GUARDED           = mutation mempunyai authorization/context guard eksplisit yang relevan di component.
UNMOUNTED                  = class ada, tetapi tidak ditemukan dipasang pada halaman aktif yang diaudit.
RVR                        = masih memerlukan runtime/operator/browser verification.
```

Temuan Phase 1 tentang `HARDENING REQUIRED` telah ditutup pada Phase 2. Route middleware yang melindungi halaman awal tidak dianggap otomatis menjadi authorization proof untuk request Livewire berikutnya.

## 3. Phase 1 — Mutation boundary audit

**Status: COMPLETE (SOURCE AUDIT), 2026-09-14.**

Inventaris `app/Livewire/` pada audit berisi **25 component**. Setelah pencopotan Filament mati pada `a4dd395` (`RkasTable` orphan + `RkasBudgetTable` yang hanya dipakai view orphan), inventaris aktif tinggal **23 component**.

### 3.1 Mutation/context boundaries setelah Phase 2

| Component | Action | Permission/constraint | Status |
|---|---|---|---|
| `UserManagement` | `createUser`, `updateUser`, `deleteUser` | ADMIN | **MUTATION GUARDED** — role check sebelum validation/query/mutation |
| `SchoolMaster` | `createSchool` | ADMIN | **MUTATION GUARDED** — role guard sebelum create/provision |
| `DatabaseMaintenance` | `run` | ADMIN | **MUTATION GUARDED** — role guard sebelum allow-list/service/audit |
| `DatabaseResetForm` | `resetDatabase` | ADMIN + active school + exact confirmation | **MUTATION GUARDED** |
| `DatabaseSchoolList` | `activate`, `migrate` | ADMIN | **MUTATION GUARDED** — role guard sebelum school lookup/service/audit |
| `DocumentStorageSettings` | `save` | OPERATOR/ADMIN | **MUTATION GUARDED / UNMOUNTED** — aman sebelum reuse |
| `SchoolSelector` | `selectSchool` | ADMIN dapat memilih sekolah; non-admin hanya sekolah sendiri | **MUTATION GUARDED** |
| `YearSelector` | `selectYear` | authenticated user setelah active school | **CONTEXT MUTATION ACCEPTED** |

### 3.2 Read-only / UI-state components

Komponen berikut tidak ditemukan melakukan persistence/domain mutation pada audit source:

- `DatabaseDiagnostics`
- `DatabaseManagerAlerts`
- `DatabaseManagerTabs`
- `DatabaseOverview`
- `DatabaseStatusSummary`
- `DatabaseTableExplorer`
- `EmployeeDirectory`
- `RkasBudgetFilter`
- `SpjMonitoringList`
- `SpjPackageList`
- `SpjPreparationFilter`
- `SpjReportFilter`
- `SyncedDataNavigation`
- `TaxFilter`
- `TransactionsTable`

Catatan:

- `DatabaseTableExplorer::openTable()` hanya membaca schema/data melalui `SchoolDatabaseManager`.
- `RkasBudgetFilter` memegang filter state dan navigasi ke canonical GET URL; query option tetap scoped.
- `TransactionsTable` memakai active context dan hanya menghasilkan filter/stat/pagination/view helpers.
- `Spj*Filter/List` memakai use case canonical; detail Paket SPJ mutation-heavy tetap server-rendered.

## 4. Boundary middleware dan rule authorization

`AppServiceProvider` menambahkan persistent middleware Livewire custom untuk active-school/active-year. Role middleware route bukan bukti otomatis untuk request Livewire mutation.

Rule yang dibuktikan Phase 2:

```text
ADMIN mutation:
UserManagement
SchoolMaster::createSchool
DatabaseMaintenance::run
DatabaseResetForm::resetDatabase
DatabaseSchoolList::{activate,migrate}

OPERATOR/ADMIN mutation:
DocumentStorageSettings::save
```

Implementasi memakai helper role canonical dari `App\Models\User`:

```text
isAdministrator()
isOperatorOrAdministrator()
```

Business rule tidak diduplikasi di component; guard hanya enforcement permission sebelum service/use case/domain path yang sudah ada.

## 5. Phase 2 — Authorization hardening

**Status: COMPLETE / REGRESSION PASS / FULL CODE GATE PASS / BROWSER RVR.**

Source commit:

```text
3c7be408f5a93795a597878b79f975373df24412
fix: harden Livewire mutation authorization
```

Critical-suite integration commit:

```text
701c73644b7dcf9d8aa710a842f28b2dad9a62d5
test: gate Livewire mutation authorization as critical
```

`tests/Feature/LivewireMutationAuthorizationTest.php` membuktikan:

1. OPERATOR ditolak dari create/update/delete user;
2. VIEWER ditolak dari create/update/delete user;
3. OPERATOR dan VIEWER ditolak dari create school;
4. OPERATOR dan VIEWER ditolak dari database maintenance;
5. OPERATOR dan VIEWER ditolak dari reset database;
6. OPERATOR dan VIEWER ditolak dari activate/migrate database school;
7. OPERATOR boleh menyimpan document storage path;
8. VIEWER ditolak dari document storage mutation;
9. target data pada negative test tetap tidak termutasi.

Regression ini pertama dibuktikan PASS pada CI #478 dan tetap PASS sebagai bagian dari SPJ Critical green gate #480.

Phase 2 tidak mengubah lifecycle SPJ, numbering, sync, tenant ownership, atau route contract.

## 6. Status per area migrasi

| Area | Implementasi source | Status integrasi |
|---|---|---|
| Transaksi | `TransactionsTable` filter/search/pagination | Implemented; read-only boundary audit PASS; code gate #480 PASS; runtime RVR |
| RKAS budget | `RkasBudgetFilter` + native hierarchy (`rkas-budget.index`; tabel Filament mati dihapus pada `a4dd395`) | Implemented; read-only boundary audit PASS; code gate PASS; runtime RVR |
| SPJ Persiapan/Paket/Laporan/Monitoring | `SpjPreparationFilter`, `SpjPackageList`, `SpjReportFilter`, `SpjMonitoringList`, SPA tab navigation | Implemented; filter/list read-only; detail Paket mutation tetap server-rendered; code gate PASS; runtime RVR |
| Pajak | `TaxFilter` | Implemented; read-only boundary audit PASS; runtime RVR |
| Pegawai | `EmployeeDirectory` | Implemented; read-only boundary audit PASS; runtime RVR |
| User | `UserManagement` | Implemented; ADMIN action guard + negative regression PASS |
| Master Sekolah | `SchoolMaster` | Implemented; ADMIN action guard + negative regression PASS |
| Pilih Sekolah | `SchoolSelector` | Implemented; explicit school/role guard |
| Pilih Tahun | `YearSelector` | Implemented; accepted session-context mutation |
| Database Aktif | summary/tabs/explorer/list/maintenance/reset | Implemented; read-only panels + ADMIN-hardened mutations; code gate PASS |
| Data Sinkronisasi | `SyncedDataNavigation` | Implemented; UI-state/read-only |
| Penyimpanan Dokumen | `DocumentStorageSettings` | Unmounted pada halaman aktif; OPERATOR/ADMIN-hardened sebelum reuse |

## 7. Repository integration gate setelah Phase 2

**Status: COMPLETE / GREEN.**

Phase 2 awalnya PASS pada CI #478, tetapi workflow tersebut membuka dua stale regression non-authorization. Keduanya ditutup pada:

```text
b61cdc621539cb6fc62dd17efc22da16a9c2a14c
test: close SPJ critical integration regressions
```

Perubahan itu tidak mengubah app source/business rule:

- `SpjNumberingRollbackTest` beralih dari direct controller invocation ke route HTTP canonical;
- `SpjWorkspaceMigrationTest` diselaraskan dengan contract NUMBERED bahwa manual field substansi diabaikan/tidak disimpan, sementara description carve-out tetap berlaku.

CI #479 membuktikan SPJ Critical + Unit hijau lalu membuka stale source-string assertion di `TransactionNumberedItemDescriptionUiTest`. Assertion itu diselaraskan dengan controller canonical yang mendelegasikan `payment_description` ke `SpjDescriptionService` pada:

```text
887d0219142d634e6a85b6672d3bffb02b5b1584
test: align description UI contract with service delegation
```

Canonical gate sekarang:

```text
CI #480 / run 34839580942 / SUCCESS
HEAD           : 887d0219142d634e6a85b6672d3bffb02b5b1584
Frontend build : PASS
Blade compile  : PASS
SPJ Critical   : 287 PASS / 2236 assertions
Full Unit      : 60 PASS / 203 assertions
Full Feature   : 411 PASS / 2972 assertions
Pint           : ADVISORY / 5 pre-existing style issues
```

P0 integration gate tidak lagi menghalangi pekerjaan operator/runtime berikutnya.

Perubahan stack setelah gate #480 (terverifikasi lokal, CI belum di-run ulang): Laravel 12 → 13 (`3582cef`), pencopotan Filament/Sail mati menuju pure TALL (`a4dd395`). Angka gate di atas tetap authoritative untuk #480; verifikasi lokal L13 tercatat di `CURRENT_PROGRESS.md`.

## 8. Kandidat migrasi setelah stabilization gate

Integration gate sudah hijau, tetapi migrasi Livewire baru bukan prioritas otomatis. P1 generated-document, browser/operator, dan Office/PDF QA lebih bernilai saat ini.

Jika operator/runtime flow sudah stabil dan manfaatnya jelas, urutan kandidat read-only:

1. Rekonsiliasi — search/filter read-only.
2. Template Dokumen — filter katalog; upload/update tetap canonical controller/service flow kecuali didesain ulang secara khusus.
3. Laporan Audit — pagination/read-only filter.

Tetap OUT OF SCOPE tanpa instruksi/kebutuhan khusus:

- ARKAS importer stateful;
- workspace detail Paket SPJ mutation-heavy;
- protected `resources/views/students/index.blade.php`;
- Dashboard sebagai target migrasi filter tanpa kebutuhan nyata;
- perubahan domain Dapodik hanya demi konsistensi UI.

## 9. Definition of Done per batch Livewire

```text
[x] boundary read/write diklasifikasikan untuk Phase 1/2
[x] authorization mutation Phase 2 diverifikasi pada action Livewire
[x] School + Fiscal Year + Fund Source tetap dijaga contract existing
[x] query/business rule tetap di service/use case/model canonical
[x] negative role regression Phase 2 tersedia dan PASS
[x] focused critical test dijalankan
[x] frontend build / Blade compile PASS pada canonical gate #480
[x] repository code gate hijau
[x] documentation impact review Phase 2 + integration repair selesai
[ ] browser/runtime — tetap RVR sampai benar-benar diuji
```