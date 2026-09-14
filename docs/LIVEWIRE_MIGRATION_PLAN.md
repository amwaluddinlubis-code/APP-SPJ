# Rencana Migrasi Livewire (TALL) — Status, Audit Boundary, dan Urutan

Terakhir diverifikasi: **2026-09-14** pada branch `gui-standardization`, HEAD source-audit `2e0f65cbd5c6e0fd8a8495f2d156805fd468ee35`.

Dokumen ini adalah sumber teknis untuk status migrasi Livewire/TALL. Status release keseluruhan tetap berada di `CURRENT_PROGRESS.md`, sedangkan prioritas berada di `DEVELOPMENT_ROADMAP.md`.

> Catatan evidence: HEAD `2e0f65c...` belum mempunyai code gate hijau. CI `SPJ Critical Verification` #476 gagal pada langkah **SPJ critical tests**; frontend build dan Blade compile lulus, tetapi full Unit dan Feature suite tidak dijalankan pada run tersebut. Karena itu status di dokumen ini membedakan implementasi source dari functional gate.

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
HARDENING REQUIRED         = mutation sensitif belum mempunyai role/action guard eksplisit yang memadai di boundary Livewire.
UNMOUNTED                  = class ada, tetapi tidak ditemukan dipasang pada halaman aktif yang diaudit.
RVR                        = masih memerlukan runtime/operator/browser verification.
```

`HARDENING REQUIRED` adalah temuan source architecture. Ini **bukan klaim exploit runtime**. Livewire memakai signed snapshot/checksum; eksploitabilitas aktual tidak dinyatakan tanpa runtime evidence. Namun repository tidak boleh menganggap route middleware saja sebagai authorization action setelah mutation dipindahkan ke component.

## 3. Phase 1 — Mutation boundary audit

**Status: COMPLETE (SOURCE AUDIT), 2026-09-14. Tidak ada behavior aplikasi yang diubah pada phase ini.**

Inventaris `app/Livewire/` pada HEAD berisi **25 component**.

### 3.1 Mutation/context boundaries

| Component | Action | Dampak | Permission/constraint yang diharapkan | Kondisi source saat audit | Status |
|---|---|---|---|---|---|
| `UserManagement` | `createUser`, `updateUser`, `deleteUser` | create/update/delete akun dan role | ADMIN | Proteksi self-demotion + last-admin ada, tetapi tidak ada guard bahwa actor adalah administrator di action | **HARDENING REQUIRED** |
| `SchoolMaster` | `createSchool` | membuat sekolah + provision database | ADMIN | Component hanya dirender dalam blok admin pada halaman settings, tetapi action tidak mempunyai guard administrator sendiri | **HARDENING REQUIRED** |
| `DatabaseMaintenance` | `run` | checkpoint/migrate/vacuum/provision database sekolah | ADMIN | allow-list action + audit ada; tidak ada role guard di action | **HARDENING REQUIRED** |
| `DatabaseResetForm` | `resetDatabase` | reset total database sekolah | ADMIN + active school + confirmation | Active-school match + exact confirmation ada; tidak ada role guard administrator di action | **HARDENING REQUIRED** |
| `DatabaseSchoolList` | `activate`, `migrate` | ganti active school/database, migrasi database | ADMIN | service + operational audit dipakai; tidak ada role guard administrator di action | **HARDENING REQUIRED** |
| `DocumentStorageSettings` | `save` | menulis global document storage path | OPERATOR/ADMIN bila component dipakai | Validasi path ada; tidak ada role guard. Component tidak ditemukan dipasang pada halaman settings aktif; halaman saat ini memakai form controller biasa | **UNMOUNTED / HARDEN BEFORE REUSE** |
| `SchoolSelector` | `selectSchool` | ensure migration + ubah `active_school_id` | ADMIN dapat memilih sekolah; non-admin hanya sekolah sendiri | Guard eksplisit `admin || user.school_id === school.id` ada; fiscal/fund context direset | **MUTATION GUARDED** |
| `YearSelector` | `selectYear` | ubah active fiscal year + fund source session | authenticated user setelah active school | Memilih record dari connection sekolah aktif dan mensyaratkan `fund_source_id`; ini context mutation, bukan domain persistence | **CONTEXT MUTATION ACCEPTED** |

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

## 4. Temuan boundary middleware

`AppServiceProvider` saat audit menambahkan persistent middleware Livewire khusus:

```text
EnsureActiveSchool
EnsureActiveFiscalYear
```

Custom role/context middleware berikut **tidak** terdaftar pada daftar persistent middleware aplikasi tersebut:

```text
EnsureAdministrator
EnsureOperatorOrAdministrator
EnsureSpjActiveContext
```

Route GET yang merender halaman admin/operator tetap penting, tetapi action Livewire sensitif harus mempunyai authorization yang dapat dibuktikan pada request action itu sendiri atau melalui mekanisme persistent middleware/policy yang setara. Phase 2 harus menyelesaikan ini tanpa memindahkan business rule ke UI.

## 5. Status per area migrasi

| Area | Implementasi source | Status integrasi saat ini |
|---|---|---|
| Transaksi | `TransactionsTable` filter/search/pagination | Implemented; read-only boundary audit PASS; HEAD code gate masih merah |
| RKAS budget | `RkasBudgetFilter`, `RkasBudgetTable` (+ `RkasTable` legacy/read-only) | Implemented; read-only boundary audit PASS; runtime RVR |
| SPJ Persiapan/Paket/Laporan/Monitoring | `SpjPreparationFilter`, `SpjPackageList`, `SpjReportFilter`, `SpjMonitoringList`, SPA tab navigation | Implemented; filter/list read-only; workspace detail mutation tetap server-rendered; runtime RVR |
| Pajak | `TaxFilter` | Implemented; read-only boundary audit PASS; runtime RVR |
| Pegawai | `EmployeeDirectory` | Implemented; read-only boundary audit PASS; runtime RVR |
| User | `UserManagement` | Implemented, tetapi **authorization hardening required** |
| Master Sekolah | `SchoolMaster` | Implemented, tetapi **authorization hardening required** |
| Pilih Sekolah | `SchoolSelector` | Implemented; explicit school/role guard ada |
| Pilih Tahun | `YearSelector` | Implemented; accepted session-context mutation |
| Database Aktif | summary/tabs/explorer/list/maintenance/reset | Implemented; read-only panels okay, **mutation actions hardening required** |
| Data Sinkronisasi | `SyncedDataNavigation` | Implemented; UI-state/read-only |
| Penyimpanan Dokumen | `DocumentStorageSettings` class tersedia | **Unmouted pada halaman aktif; harden sebelum dipakai kembali** |

Status `WIP UNCOMMITTED` dari versi dokumen sebelumnya sudah usang dan dicabut: komponen Database Manager/Data Sinkronisasi yang diaudit sudah berada di HEAD committed.

## 6. Phase 2 — Authorization hardening (NEXT)

Jangan menambah area migrasi Livewire baru sebelum boundary berikut ditutup:

1. tambahkan authorization action-level/policy yang sesuai untuk `UserManagement`;
2. harden `SchoolMaster::createSchool()` sebagai ADMIN-only;
3. harden `DatabaseMaintenance::run()` sebagai ADMIN-only;
4. harden `DatabaseResetForm::resetDatabase()` sebagai ADMIN-only selain active-school + confirmation guard yang sudah ada;
5. harden `DatabaseSchoolList::activate()` dan `migrate()` sebagai ADMIN-only;
6. tentukan nasib `DocumentStorageSettings`: hapus hanya bila ada persetujuan eksplisit, atau beri operator/admin guard sebelum dipakai kembali;
7. tambahkan negative regression untuk actor yang tidak berhak pada mutation boundary tersebut;
8. jangan mengubah lifecycle SPJ, numbering, sync, atau tenant ownership sebagai bagian hardening ini.

Setelah Phase 2, jalankan focused regression yang relevan lalu code gate repository. Jangan mempromosikan status ke FUNCTIONAL PASS sampai gate aktual hijau.

## 7. Kandidat migrasi setelah stabilization gate hijau

Urutan ini **ditunda sementara** sampai Phase 2 + gate CI ditutup:

1. Rekonsiliasi — search/filter read-only bila manfaat operator jelas.
2. Template dokumen — filter katalog dapat dipertimbangkan; upload/update tetap mengikuti controller/service canonical kecuali dirancang ulang secara khusus.
3. Laporan audit pagination — optional/low risk.
4. ARKAS importer — tetap ditunda; workflow mapping → preview → sync terlalu sensitif untuk migrasi opportunistic.

Tetap OUT OF SCOPE tanpa instruksi khusus:

- `resources/views/students/index.blade.php` karena protected file;
- Dashboard sebagai target migrasi filter tanpa kebutuhan nyata;
- Workspace detail Paket SPJ mutation-heavy;
- perubahan domain Dapodik hanya demi konsistensi UI.

## 8. Definition of Done per batch Livewire

```text
[ ] boundary read/write diklasifikasikan
[ ] authorization mutation diverifikasi pada action Livewire
[ ] School + Fiscal Year + Fund Source tetap terjaga
[ ] query/business rule tetap di service/use case/model canonical
[ ] negative role/tenant regression tersedia bila mutation sensitif
[ ] focused test aktual dijalankan
[ ] frontend build / Blade compile dijalankan bila UI berubah
[ ] code gate repository hijau sebelum klaim FUNCTIONAL PASS
[ ] documentation impact review selesai
[ ] browser/runtime tetap RVR sampai benar-benar diuji
```
