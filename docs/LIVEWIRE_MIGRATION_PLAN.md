# Rencana Migrasi Livewire (TALL) — Status, Audit Boundary, dan Urutan

Terakhir diverifikasi: **2026-09-14** pada branch `gui-standardization`, HEAD `701c73644b7dcf9d8aa710a842f28b2dad9a62d5`.

Dokumen ini adalah sumber teknis untuk status migrasi Livewire/TALL. Status release keseluruhan tetap berada di `CURRENT_PROGRESS.md`, sedangkan prioritas berada di `DEVELOPMENT_ROADMAP.md`.

> Catatan evidence: Phase 2 authorization hardening sudah dibuktikan oleh `LivewireMutationAuthorizationTest` PASS 2/2 di SPJ Critical CI #478. Overall workflow #478 tetap merah karena dua regression SPJ non-authorization; full Unit dan Feature suite tidak dijalankan setelah critical failure. Karena itu Phase 2 dapat dinyatakan selesai untuk scope authorization, tetapi current HEAD belum menjadi canonical FUNCTIONAL PASS repository.

## 1. Prinsip canonical migrasi

1. Laravel/use case/service tetap memiliki business rule, authorization, persistence, tenant boundary, numbering, sync, dan lifecycle.
2. Livewire memiliki reactive server-backed state, filter, pagination, dan action UI yang memang dipindahkan ke component.
3. Alpine hanya memiliki interaction client-side ringan; jangan membuat Alpine dan Livewire memiliki state yang sama.
4. Query domain harus tetap memakai query/use case/service canonical; jangan menduplikasi aturan domain di component.
5. State filter yang bookmarkable memakai `#[Url]`.
6. Mutation Livewire **wajib mempunyai authorization boundary yang tetap berlaku pada request Livewire**, bukan hanya mengandalkan route GET yang merender component.
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

Temuan Phase 1 tentang `HARDENING REQUIRED` telah ditutup pada Phase 2. Prinsip arsitektur tetap berlaku: route middleware yang melindungi halaman awal tidak dianggap otomatis menjadi authorization proof untuk request Livewire berikutnya.

## 3. Phase 1 — Mutation boundary audit

**Status: COMPLETE (SOURCE AUDIT), 2026-09-14.**

Inventaris `app/Livewire/` berisi **25 component**.

### 3.1 Mutation/context boundaries setelah Phase 2

| Component | Action | Permission/constraint | Status setelah Phase 2 |
|---|---|---|---|
| `UserManagement` | `createUser`, `updateUser`, `deleteUser` | ADMIN | **MUTATION GUARDED** — `isAdministrator()` dicek sebelum validation/query/mutation |
| `SchoolMaster` | `createSchool` | ADMIN | **MUTATION GUARDED** — role guard sebelum create/provision |
| `DatabaseMaintenance` | `run` | ADMIN | **MUTATION GUARDED** — role guard sebelum allow-list/service/audit |
| `DatabaseResetForm` | `resetDatabase` | ADMIN + active school + exact confirmation | **MUTATION GUARDED** — ketiga guard berlaku sebelum reset service |
| `DatabaseSchoolList` | `activate`, `migrate` | ADMIN | **MUTATION GUARDED** — role guard sebelum school lookup/service/audit |
| `DocumentStorageSettings` | `save` | OPERATOR/ADMIN | **MUTATION GUARDED / UNMOUNTED** — aman sebelum reuse; halaman settings aktif masih memakai canonical form/controller |
| `SchoolSelector` | `selectSchool` | ADMIN dapat memilih sekolah; non-admin hanya sekolah sendiri | **MUTATION GUARDED** — guard existing dipertahankan |
| `YearSelector` | `selectYear` | authenticated user setelah active school | **CONTEXT MUTATION ACCEPTED** |

### 3.2 Read-only / UI-state components

Komponen berikut tidak ditemukan melakukan persistence/domain mutation pada audit source. Public method-nya mengelola filter, pagination, sorting, tab, computed view state, atau read-only inspection:

- `DatabaseDiagnostics`
- `DatabaseManagerAlerts`
- `DatabaseManagerTabs`
- `DatabaseOverview`
- `DatabaseStatusSummary`
- `DatabaseTableExplorer`
- `EmployeeDirectory`
- `RkasBudgetFilter`
- `RkasBudgetTable`
- `RkasTable`
- `SpjMonitoringList`
- `SpjPackageList`
- `SpjPreparationFilter`
- `SpjReportFilter`
- `SyncedDataNavigation`
- `TaxFilter`
- `TransactionsTable`

Catatan khusus:

- `DatabaseTableExplorer::openTable()` hanya membaca schema/data melalui `SchoolDatabaseManager`.
- `RkasBudgetFilter` hanya memegang filter state dan menavigasi ke canonical GET URL; query option tetap read-only dan scoped oleh active fiscal year + fund source.
- `TransactionsTable` memakai `Transaction::activeContext()` dan hanya menghasilkan filter/stat/pagination/view helpers.
- `Spj*Filter/List` memakai use case canonical; detail Paket SPJ mutation-heavy tetap server-rendered dan tidak dipindahkan pada batch ini.

## 4. Boundary middleware dan rule authorization

`AppServiceProvider` menambahkan persistent middleware Livewire custom untuk active context sekolah/tahun. Role middleware route bukan bukti otomatis untuk request Livewire mutation.

Rule yang sekarang dibuktikan Phase 2:

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

**Status: COMPLETE untuk scope authorization / FOCUSED CRITICAL REGRESSION PASS / BROWSER RVR.**

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

Regression `tests/Feature/LivewireMutationAuthorizationTest.php` membuktikan pada CI #478:

1. OPERATOR ditolak dari create/update/delete user;
2. VIEWER ditolak dari create/update/delete user;
3. OPERATOR dan VIEWER ditolak dari create school;
4. OPERATOR dan VIEWER ditolak dari database maintenance;
5. OPERATOR dan VIEWER ditolak dari reset database;
6. OPERATOR dan VIEWER ditolak dari activate/migrate database school;
7. OPERATOR boleh menyimpan document storage path;
8. VIEWER ditolak dari document storage mutation;
9. target user/school data yang dipakai negative test tetap tidak termutasi.

Phase 2 tidak mengubah lifecycle SPJ, numbering, sync, tenant ownership, atau route contract.

## 6. Status per area migrasi

| Area | Implementasi source | Status integrasi saat ini |
|---|---|---|
| Transaksi | `TransactionsTable` filter/search/pagination | Implemented; read-only boundary audit PASS; overall HEAD gate masih merah |
| RKAS budget | `RkasBudgetFilter`, `RkasBudgetTable` (+ `RkasTable` legacy/read-only) | Implemented; read-only boundary audit PASS; runtime RVR |
| SPJ Persiapan/Paket/Laporan/Monitoring | `SpjPreparationFilter`, `SpjPackageList`, `SpjReportFilter`, `SpjMonitoringList`, SPA tab navigation | Implemented; filter/list read-only; workspace detail mutation tetap server-rendered; runtime RVR |
| Pajak | `TaxFilter` | Implemented; read-only boundary audit PASS; runtime RVR |
| Pegawai | `EmployeeDirectory` | Implemented; read-only boundary audit PASS; runtime RVR |
| User | `UserManagement` | Implemented; ADMIN action guard + negative regression PASS |
| Master Sekolah | `SchoolMaster` | Implemented; ADMIN action guard + negative regression PASS |
| Pilih Sekolah | `SchoolSelector` | Implemented; explicit school/role guard ada |
| Pilih Tahun | `YearSelector` | Implemented; accepted session-context mutation |
| Database Aktif | summary/tabs/explorer/list/maintenance/reset | Implemented; read-only panels okay; mutation actions ADMIN-hardened |
| Data Sinkronisasi | `SyncedDataNavigation` | Implemented; UI-state/read-only |
| Penyimpanan Dokumen | `DocumentStorageSettings` class tersedia | Unmounted pada halaman aktif; OPERATOR/ADMIN-hardened sebelum reuse |

## 7. Repository integration gate setelah Phase 2

CI #478 menjalankan Phase 2 regression dan membuktikannya PASS, tetapi overall SPJ Critical tetap gagal:

```text
LivewireMutationAuthorizationTest : PASS 2/2
SPJ Critical total               : 285 PASS / 2 FAIL / 2218 assertions
Full Unit                        : skipped
Full Feature                     : skipped
```

Dua blocker berikut harus ditutup sebelum migrasi Livewire diperluas:

1. `SpjNumberingRollbackTest` stale direct-controller call: test memanggil `TransactionController::updateSpjDescriptions()` dengan 2 argumen sementara signature canonical menerima 4 dependency. Rekomendasi: uji melalui route/container HTTP canonical agar test tidak couple ke signature internal controller.
2. `SpjWorkspaceMigrationTest` mengharapkan session `error` pada update `vendor_name` Paket NUMBERED, sedangkan current behavior tidak mengembalikan kontrak itu. Verifikasi lifecycle contract; restore protection bila behavior source salah, atau sinkronkan regression bila contract memang telah berubah dengan sengaja.

Latest successful full canonical gate tetap CI #469 sampai SPJ Critical + full Unit + full Feature kembali hijau.

## 8. Kandidat migrasi setelah stabilization gate hijau

Urutan ini **ditunda sementara** sampai repository gate hijau:

1. Rekonsiliasi — search/filter read-only bila manfaat operator jelas.
2. Template dokumen — filter katalog dapat dipertimbangkan; upload/update tetap mengikuti controller/service canonical kecuali dirancang ulang secara khusus.
3. Laporan audit pagination — optional/low risk.
4. ARKAS importer — tetap ditunda; workflow mapping → preview → sync terlalu sensitif untuk migrasi opportunistic.

Tetap OUT OF SCOPE tanpa instruksi khusus:

- `resources/views/students/index.blade.php` karena protected file;
- Dashboard sebagai target migrasi filter tanpa kebutuhan nyata;
- Workspace detail Paket SPJ mutation-heavy;
- perubahan domain Dapodik hanya demi konsistensi UI.

## 9. Definition of Done per batch Livewire

```text
[x] boundary read/write diklasifikasikan untuk batch Phase 1/2
[x] authorization mutation Phase 2 diverifikasi pada action Livewire
[x] School + Fiscal Year + Fund Source tetap terjaga oleh contract existing
[x] query/business rule tetap di service/use case/model canonical
[x] negative role regression Phase 2 tersedia dan PASS
[x] focused critical test aktual dijalankan untuk Phase 2
[x] frontend build / Blade compile CI #478 PASS
[ ] repository code gate hijau — tertahan dua regression SPJ non-authorization
[ ] browser/runtime — tetap RVR sampai benar-benar diuji
[x] documentation impact review Phase 2 selesai
```
