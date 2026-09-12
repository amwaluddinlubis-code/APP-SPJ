# API / Route Reference

Digenerate dari `php artisan route:list --json` pada **2026-09-12**. Total **126 route**, seluruhnya di `routes/web.php` (+ `routes/console.php` untuk schedule). **Tidak ada `routes/api.php`** — aplikasi ini murni session-authenticated web routes.

Regenerasi: `php artisan route:list --path=<prefix> -v --no-interaction`.

Alias middleware (terdaftar di `bootstrap/app.php`): `auth`, `guest`, `active-school`,
`active-year`, `spj-active-context`, `operator-or-administrator`, `administrator`, `throttle`.
`web` dihilangkan dari tabel. Peran: VIEWER read-only; OPERATOR workflow operasional;
ADMINISTRATOR lifecycle/maintenance/sensitive action.

## `/` — Dashboard (1)

| Method | URI | Name | Action | Middleware |
|---|---|---|---|---|
| GET\|HEAD | `/` | dashboard | `ProductivityDashboardController` | auth, active-school, active-year, spj-active-context |

## Auth & setup

| Method | URI | Name | Action | Middleware |
|---|---|---|---|---|
| GET\|HEAD | `/masuk` | login | `LoginController@create` | guest |
| POST | `/masuk` | login.store | `LoginController@store` | guest, throttle |
| POST | `/keluar` | logout | `LoginController@destroy` | auth |
| GET\|HEAD | `/setup` | setup | `InitialSetupController@create` |  |
| POST | `/setup` | setup.store | `InitialSetupController@store` | throttle |
| GET\|HEAD | `/pilih-sekolah` | schools.select | `SchoolSelectionController@create` | auth |
| POST | `/pilih-sekolah` | schools.activate | `SchoolSelectionController@store` | auth |
| GET\|HEAD | `/pilih-tahun` | years.select | `YearSelectionController@create` | auth, active-school |
| POST | `/pilih-tahun` | years.activate | `YearSelectionController@store` | auth, active-school |
| POST | `/pilih-tahun/sinkronisasi` | years.synchronize | `YearSelectionController@synchronize` | auth, active-school, operator-or-administrator, throttle |
| GET\|HEAD | `/up` |  | `Closure` |  |

## `/transaksi` (7)

| Method | URI | Name | Action | Middleware |
|---|---|---|---|---|
| GET\|HEAD | `/transaksi` | transactions.index | `TransactionController@index` | auth, active-school, active-year, spj-active-context |
| GET\|HEAD | `/transaksi/{transactionId}` | transactions.show | `TransactionController@show` | auth, active-school, active-year, spj-active-context |
| PUT | `/transaksi/{transactionId}/pemeliharaan/transaksi-terkait` | transactions.maintenance-links.update | `MaintenanceTransactionLinkController@update` | auth, active-school, active-year, spj-active-context, operator-or-administrator |
| GET\|HEAD | `/transaksi/{transactionId}/pemeliharaan/transaksi-terkait` | transactions.maintenance-links.show | `MaintenanceTransactionLinkController@show` | auth, active-school, active-year, spj-active-context |
| POST | `/transaksi/{transactionId}/rekonsiliasi-sumber/selesaikan` | transactions.source-reconciliation.resolve | `SourceReconciliationController@resolve` | auth, active-school, active-year, spj-active-context, operator-or-administrator |
| GET\|HEAD | `/transaksi/{transactionId}/siapkan-spj` | transactions.prepare-spj | `SpjPreparationController` | auth, active-school, active-year, spj-active-context, operator-or-administrator |
| PUT | `/transaksi/{transactionId}/uraian-spj` | transactions.spj-descriptions.update | `TransactionController@updateSpjDescriptions` | auth, active-school, active-year, spj-active-context, operator-or-administrator |

## `/spj` (27, termasuk `/spj` root)

| Method | URI | Name | Action | Middleware |
|---|---|---|---|---|
| GET\|HEAD | `/spj` | spj.index | `SpjController@index` | auth, active-school, active-year, spj-active-context |
| POST | `/spj/dokumen/{documentId}/batal` | spj.documents.cancel | `SpjController@cancelDocument` | auth, active-school, active-year, spj-active-context, operator-or-administrator, administrator |
| POST | `/spj/dokumen/{documentId}/final` | spj.documents.finalize | `SpjController@finalizeDocument` | auth, active-school, active-year, spj-active-context, operator-or-administrator |
| POST | `/spj/dokumen/{documentId}/ganti` | spj.documents.replace | `SpjController@replaceDocument` | auth, active-school, active-year, spj-active-context, operator-or-administrator, administrator |
| GET\|HEAD | `/spj/laporan/honor/{format}` | spj.honor-payments.export | `SpjController@exportHonorPayments` | auth, active-school, active-year, spj-active-context |
| PUT | `/spj/paket/{packageId}` | spj.update | `SpjController@updateDetails` | auth, active-school, active-year, spj-active-context, operator-or-administrator |
| POST | `/spj/paket/{packageId}/buka-kunci` | spj.unlock | `SpjController@unlockPackage` | auth, active-school, active-year, spj-active-context, administrator |
| GET\|HEAD | `/spj/paket/{packageId}/checklist` | spj.checklist | `SpjPackageChecklistController` | auth, active-school, active-year, spj-active-context |
| POST | `/spj/paket/{packageId}/dokumen/{documentType}/nomor` | spj.documents.assign-number | `SpjController@assignDocumentNumber` | auth, active-school, active-year, spj-active-context, operator-or-administrator |
| POST | `/spj/paket/{packageId}/nomor` | spj.assign-number | `SpjController@assignNumber` | auth, active-school, active-year, spj-active-context, operator-or-administrator |
| GET\|HEAD | `/spj/paket/{packageId}/pratinjau` | spj.preview-package | `SpjController@previewPackage` | auth, active-school, active-year, spj-active-context |
| POST | `/spj/paket/{packageId}/siap` | spj.ready | `SpjController@markReady` | auth, active-school, active-year, spj-active-context, operator-or-administrator |
| GET\|HEAD | `/spj/paket/{packageId}/template/{templateId}/pratinjau` | spj.preview-template | `SpjController@previewTemplate` | auth, active-school, active-year, spj-active-context |
| POST | `/spj/paket/{packageId}/template/{templateId}/unduh` | spj.download-template | `SpjController@downloadTemplate` | auth, active-school, active-year, spj-active-context |
| POST | `/spj/paket/{packageId}/template/{templateId}/unduh-pdf` | spj.download-template-pdf | `SpjController@downloadTemplatePdf` | auth, active-school, active-year, spj-active-context |
| POST | `/spj/paket/{packageId}/unduh` | spj.download | `SpjController@download` | auth, active-school, active-year, spj-active-context |
| POST | `/spj/paket/{packageId}/unduh-excel` | spj.download-package-excel | `SpjController@downloadPackageExcel` | auth, active-school, active-year, spj-active-context |
| GET\|HEAD | `/spj/penomoran` | spj.numbering-workflow | `SpjNumberingWorkflowController@index` | auth, active-school, active-year, spj-active-context |
| POST | `/spj/penomoran-triwulan` | spj.quarter-numbering | `SpjController@assignQuarterNumbers` | auth, active-school, active-year, spj-active-context, administrator |
| POST | `/spj/penomoran-triwulan/batal` | spj.quarter-numbering.cancel | `SpjController@cancelQuarterNumbering` | auth, active-school, active-year, spj-active-context, administrator |
| GET\|HEAD | `/spj/penomoran/koreksi` | spj.numbering-correction | `SpjNumberingCorrectionController` | auth, active-school, active-year, spj-active-context, administrator |
| POST | `/spj/penomoran/rollback` | spj.numbering.rollback | `SpjController@rollbackNumbering` | auth, active-school, active-year, spj-active-context, administrator |
| POST | `/spj/transaksi/{transactionId}/pembayaran` | spj.payments.store | `SpjController@storePayment` | auth, active-school, active-year, spj-active-context, operator-or-administrator |
| POST | `/spj/transaksi/{transactionId}/penerimaan` | spj.receipts.store | `SpjController@storeGoodsReceipt` | auth, active-school, active-year, spj-active-context, operator-or-administrator |
| POST | `/spj/triwulan/{periodId}/buka` | spj.quarter-reopen | `SpjController@reopenQuarter` | auth, active-school, active-year, spj-active-context, administrator |
| POST | `/spj/tutup-triwulan` | spj.quarter-close | `SpjController@closeQuarter` | auth, active-school, active-year, spj-active-context, administrator |
| GET\|HEAD | `/spj/unduh/{format}` | spj.export | `SpjController@export` | auth, active-school, active-year, spj-active-context |

Catatan: `spj.unlock` sengaja dinonaktifkan di UseCase (selalu error, mengarahkan ke Koreksi Penomoran).

## `/penganggaran-rkas` (3)

| Method | URI | Name | Action | Middleware |
|---|---|---|---|---|
| GET\|HEAD | `/penganggaran-rkas` | rkas-budget.index | `RkasBudgetController` | auth, active-school, active-year, spj-active-context |
| GET\|HEAD | `/penganggaran-rkas/saran` | rkas-planning.index | `RkasPlanningSuggestionController` | auth, active-school, active-year, spj-active-context |
| GET\|HEAD | `/penganggaran-rkas/saran/unduh/{modul}` | rkas-planning.export | `RkasPlanningSuggestionController@export` | auth, active-school, active-year, spj-active-context |

## `/sinkronisasi` + `/pilih-tahun/sinkronisasi`

| Method | URI | Name | Action | Middleware |
|---|---|---|---|---|
| POST | `/sinkronisasi/arkas` | arkas.sync | `ArkasSyncController` | auth, active-school, active-year, spj-active-context, operator-or-administrator, throttle |

## `/pengaturan` (43, administrator)

Seluruhnya `auth` + `administrator`; yang menyentuh tenant menambah `active-school` (+ `active-year`, sebagian `spj-active-context`).

| Method | URI | Name | Tambahan middleware |
|---|---|---|---|
| GET\|HEAD | `/pengaturan/arkas` | arkas.settings | — |
| POST | `/pengaturan/arkas` | arkas.settings.store | — |
| GET\|HEAD | `/pengaturan/arkas/importer` | arkas.importer | active-school, active-year |
| POST | `/pengaturan/arkas/importer/mapping` | arkas.importer.mapping.store | active-school, active-year |
| POST | `/pengaturan/arkas/importer/{profileId}/preview` | arkas.importer.preview | active-school, active-year |
| POST | `/pengaturan/arkas/importer/{profileId}/sync` | arkas.importer.sync | active-school, active-year |
| GET\|HEAD | `/pengaturan/backup` | school-backups.index | — |
| POST | `/pengaturan/backup` | school-backups.store | — |
| POST | `/pengaturan/backup/{backupId}/pulihkan` | school-backups.restore | — |
| GET\|HEAD | `/pengaturan/dapodik` (+PUT, +`POST sinkron/tes`) | dapodik.* | active-school, active-year, spj-active-context |
| GET\|HEAD | `/pengaturan/database-aktif` (+`tabel/{table}`, +5 POST `{schoolId}/*`, +reset-form) | database-manager.* | — |
| GET\|HEAD | `/pengaturan/format-penomoran` | document-number-formats.index | active-school, active-year, spj-active-context, operator-or-administrator |
| PUT | `/pengaturan/format-penomoran/{documentType}` | document-number-formats.update | active-school, active-year, spj-active-context, operator-or-administrator |
| GET\|HEAD | `/pengaturan/impersonate` (+POST `{userId}`, +POST `selesai`) | impersonation.* | — (`stop` auth saja) |
| GET\|HEAD | `/pengaturan/sekolah` (+POST, +`kop-surat`, +PUT `profil`, +POST `tahun`) | schools.* / years.store | — |
| GET\|HEAD | `/pengaturan/template-dokumen` (+POST, +`contoh/{format}`, +DELETE, +PUT `pemetaan`, +GET `unduh`) | document-templates.* | active-school, active-year, spj-active-context |
| GET\|HEAD | `/pengaturan/user` (+POST, +PUT, +DELETE) | users.* | — |

## Operasional lain

| Method | URI | Name | Middleware |
|---|---|---|---|
| GET\|HEAD | `/pegawai` (+CRUD, kecuali index/show: operator-or-administrator) | employees.* | auth, active-school, active-year, spj-active-context |
| GET\|HEAD | `/siswa` (+CRUD, kecuali index/show: operator-or-administrator) | students.* | auth, active-school, active-year, spj-active-context |
| GET\|HEAD | `/pajak` | taxes.index | auth, active-school, active-year, spj-active-context |
| GET\|HEAD | `/data-sinkron`, `/data-sinkron/{type}` | synced-data.* | auth, active-school, active-year, spj-active-context |
| GET\|HEAD | `/laporan-audit`, `/laporan-audit/unduh/{format}` | audit-reports.* | auth, active-school, active-year, spj-active-context |
| GET\|HEAD | `/rekonsiliasi` | reconciliation.index | auth, active-school, active-year, spj-active-context |
| GET\|HEAD | `/asisten`, `/asisten/status/{token}`, POST `/asisten/tanya` | asisten.* | auth (+throttle untuk ask) |

## Vendor (bukan kontrak aplikasi)

`/livewire/*` (Livewire assets/update/upload), `/filament/*` (export/import download),
`/storage/*` (Closure), `/up` (health), `POST /_boost/browser-logs` (dev tooling).
