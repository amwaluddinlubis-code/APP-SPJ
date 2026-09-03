# Arsitektur Lengkap SPJ BOSP Web

Dokumen ini memberikan gambaran menyeluruh tentang arsitektur, alur data, dan komponen teknis SPJ BOSP Web.

## 1. Ringkasan Eksekutif

**SPJ BOSP Web** adalah sistem manajemen Surat Pertanggungjawaban (SPJ) berbasis Laravel 12 yang dirancang untuk:
- Mengelola transaksi keuangan sekolah (BKU) dari sistem ARKAS
- Menyiapkan paket dokumen SPJ per transaksi dengan kategori yang beragam
- Menyediakan dashboard terintegrasi untuk monitoring penyerapan anggaran
- Mendukung multi-sekolah dengan isolasi data per fiscal year

**Tech Stack**:
- **Backend**: Laravel 12, PHP 8.2
- **Frontend**: Alpine.js, Tailwind CSS 4, Livewire 3
- **Database**: SQLite per sekolah (fase awal), multi-koneksi (main + school)
- **PDF/Export**: DomPDF, PHPWord, PHPSpreadsheet
- **Admin Panel**: Filament 4

## 2. Struktur Database

### 2.1 Koneksi Database Ganda

Aplikasi menggunakan dua koneksi database:

```
┌─────────────────────────────────────────────────────────┐
│           DATABASE UTAMA (main)                         │
│                                                         │
│  ✓ users                    (login & user management)   │
│  ✓ schools                  (profil sekolah)            │
│  ✓ school_databases         (path DB per sekolah)       │
│  ✓ school_backups           (backup history)            │
│  ✓ arkas_sources            (config integrasi ARKAS)    │
│  ✓ cache, jobs, migrations  (sistem Laravel)           │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│      DATABASE SEKOLAH (school) - Per Sekolah            │
│                                                         │
│  ✓ fiscal_years             (tahun anggaran)           │
│  ✓ fund_sources             (sumber dana)              │
│  ✓ transactions             (BKU + SPJ metadata)       │
│  ✓ transaction_items        (rincian item BKU)         │
│  ✓ spj_packages             (dokumen SPJ metadata)     │
│  ✓ spj_goods                (detail barang/modal)      │
│  ✓ spj_workers              (detail pekerja)           │
│  ✓ spj_participants         (detail peserta konsumsi)  │
│  ✓ spj_travels              (detail perjalanan dinas)  │
│  ✓ document_templates       (template dokumen)         │
│  ✓ arkas_rkas_items         (data RKAS hasil sync)     │
│  ✓ arkas_bku_rows           (data BKU hasil sync)      │
│  ✓ operational_audit_logs   (audit trail)              │
└─────────────────────────────────────────────────────────┘
```

### 2.2 Model & Relasi Utama

```
User (DB: main)
├── school_id → School
└── role: ADMIN, OPERATOR

School (DB: main)
├── databaseRecord: SchoolDatabase
├── backups: SchoolBackup[]
├── arkasSource: ArkasSource
└── has_many: FiscalYear

FiscalYear (DB: school)
├── belongs_to: School
├── has_many: Transaction
├── belongs_to: FundSource
└── has_many: DocumentTemplate

Transaction (DB: school)
├── belongs_to: FiscalYear
├── has_many: TransactionItem (raw import data)
├── has_many: SpjGoods (category-specific data)
├── has_many: SpjWorker (pekerja/kontraktor)
├── has_many: SpjParticipant (peserta konsumsi)
├── has_many: SpjTravel (data SPPD)
└── has_one: SpjPackage (dokumen SPJ)

SpjPackage (DB: school)
└── belongs_to: Transaction
```

### 2.3 Tabel Utama & Struktur

#### transactions
Menyimpan data transaksi BKU dari ARKAS + metadata SPJ.

**Fields Financial (dari ARKAS BKU)**:
- `id`, `fiscal_year_id`, `fund_source_id`
- `id_kas_umum`, `no_bukti`, `transaction_date`
- `description`, `payment_description`, `payment_method`, `payment_reference`
- `activity_code`, `activity_name`, `account_code`, `account_name`
- `recipient_name`, `vendor_name`, `vendor_owner`, `vendor_npwp`
- `signatory_name`, `signatory_role`
- `gross_amount`, `ppn`, `pph21`, `pph22`, `pph23`, `pph4`, `sspd`, `tax_total`, `net_amount`
- `is_siplah`, `status`
- `manual_description` (untuk catatan manual operator)

**Fields SPJ Metadata**:
- `spj_category` (BARANG, KONSUMSI, PEMELIHARAAN, SPPD, HONOR_PEGAWAI, JASA_LAINNYA, BELANJA_MODAL)
- `spj_recipient_name` (override nama penerima untuk SPJ)

**Deprecated (masih ada untuk backward compatibility)**:
- `work_description`, `work_location`, `work_started_at`, `work_completed_at`, `spk_number`, `spk_date`
- `event_name`, `event_location`, `participant_count`
- `order_number`, `order_date`, `bap_number`, `bap_date`, `bast_number`, `bast_date`
- `invoice_number`, `invoice_date`, `invoice_status`

#### transaction_items
Archive rincian item dari ARKAS. Tidak dimodifikasi di SPJ, hanya untuk referensi.

**Fields**:
- `id`, `transaction_id`
- `source_item_id`, `description`, `item_description`
- `quantity`, `unit`, `unit_price`, `amount`
- Catatan: `account_code` dan `account_name` sudah dihapus (migrasi 2026_08_30_053000)

#### spj_packages
Metadata dokumen SPJ yang dihasilkan.

**Fields**:
- `id`, `transaction_id` (unique)
- `document_number` (nomor urut SPJ per periode)
- `quarter_code`, `semester_code`, `phase_code` (periode penomoran)
- `status` (DRAFT, DISIAPKAN, DICETAK, DIARSIPKAN)
- `numbered_at`, `generated_at` (timestamps)

#### spj_goods
Detail barang/modal/konsumsi per transaksi.

**Fields**:
- `id`, `transaction_id`
- `name`, `description`, `quantity`, `unit`, `unit_price`, `amount`
- `account_code`, `account_name`
- `order_number`, `order_date` (PO reference)
- `bap_number`, `bap_date` (pemeriksaan barang)
- `bast_number`, `bast_date` (penyerahan barang)
- `notes`

#### spj_workers
Detail pekerja/kontraktor untuk kategori PEMELIHARAAN, UPAH.

**Fields**:
- `id`, `transaction_id`
- `name`, `job_description`
- `work_location`, `work_started_at`, `work_completed_at`
- `work_days`, `daily_rate`, `amount` (calculated/input)
- `spk_number`, `spk_date`, `rab_number`, `rab_date`
- `is_receipt_recipient` (apakah menjadi penerima kuitansi)
- `notes`

#### spj_participants
Detail peserta untuk kategori KONSUMSI.

**Fields**:
- `id`, `transaction_id`
- `name`, `position`, `portions` (porsi makanan/hari)

#### spj_travels
Detail perjalanan dinas untuk kategori SPPD.

**Fields**:
- `id`, `transaction_id`
- `traveler_name`, `destination`, `purpose`
- `departure_date`, `return_date`
- `transport_mode`, `participant_count`, `amount`
- `notes`

## 3. Alur Data & Sinkronisasi

### 3.1 Sinkronisasi ARKAS (ArkasSynchronizationService)

```
Admin Input: Database ARKAS + Bridge Path + Password
         ↓
   [ArkasBridgeClient::execute]
   Jalankan executable ARKASBridge
         ↓
   Retrieve: identity, rkas, bku per tahun
         ↓
   [Transaction dalam DB::connection('school')]
         ├─→ Simpan ke arkas_rkas_items (RKAS)
         ├─→ Simpan ke arkas_bku_rows (raw BKU)
         └─→ Buat transaction + transaction_items per NO_BUKTI
         ↓
   Update last_synced_at di arkas_sources
         ↓
   Log sync_run (start, finish, status)
```

### 3.2 Persiapan SPJ (TransactionController + SpjController)

```
1. Pilih Transaksi
         ↓
2. Update manual_description (kategori SPJ, uraian, pajak override)
         ↓
3. Load detail sesuai kategori:
   - BARANG: load/buat SpjGoods
   - KONSUMSI: load/buat SpjParticipant
   - PEMELIHARAAN: load/buat SpjWorker
   - SPPD: load/buat SpjTravel
   - HONOR_PEGAWAI: load/buat SpjWorker
         ↓
4. Jika semua kategori siap → Prepare SpjPackage
         ↓
5. Validasi (SpjPackageValidationService)
   - Cek field wajib (recipient, activity, account, payment method)
   - Cek detail per kategori (goods, workers, participants, travels)
         ↓
6. Assign nomor SPJ (document_number)
         ↓
7. Generate PDF atau export ke Template (Word/Excel)
```

## 4. Modul-Modul Sistem

### Modul 1: Setup & Login
- **Controllers**: InitialSetupController, LoginController
- **Fungsi**: Setup sekolah pertama, registrasi admin, login user
- **Routes**: `/setup`, `/masuk`, `/keluar`

### Modul 2: Pemilihan Sekolah & Tahun
- **Controllers**: SchoolSelectionController, YearSelectionController
- **Middleware**: `active-school`, `active-year`
- **Fungsi**: Switch konteks kerja per sekolah dan tahun anggaran
- **Routes**: `/pilih-sekolah`, `/pilih-tahun`, `/pilih-tahun/sinkronisasi`

### Modul 3: Pengaturan Sekolah & ARKAS
- **Controllers**: SchoolConfigurationController, ArkasSourceController
- **Middleware**: `administrator`
- **Fungsi**: Konfigurasi profil sekolah, sumber dana, ARKAS integration
- **Routes**: 
  - `/pengaturan/sekolah` (profile, fund sources)
  - `/pengaturan/arkas` (database path, bridge path, password)

### Modul 4: Manajemen Database Sekolah
- **Controllers**: DatabaseManagerController
- **Services**: SchoolDatabaseManager
- **Middleware**: `administrator`
- **Fungsi**: Activate, migrate, checkpoint, vacuum, integrity check, provision
- **Routes**: `/pengaturan/database-aktif/{schoolId}/{action}`

### Modul 5: Backup & Pemulihan
- **Controllers**: SchoolBackupController
- **Models**: SchoolBackup
- **Middleware**: `administrator`
- **Fungsi**: Buat backup manual, restore dari backup sebelumnya
- **Routes**: `/pengaturan/backup` (list, create, restore)

### Modul 6: Dashboard & RKAS
- **Controllers**: DashboardController, RkasBudgetController
- **Livewire**: RkasTable, RkasBudgetTable
- **Fungsi**: Dashboard ringkasan, perbandingan pagu vs realisasi
- **Routes**: `/` (dashboard), `/penganggaran-rkas` (RKAS detail)

### Modul 7: Transaksi BKU
- **Controllers**: TransactionController, TaxController
- **Models**: Transaction, TransactionItem, SpjParticipant, SpjWorker, SpjGoods
- **Middleware**: `active-school`, `active-year`
- **Fungsi**: Tampilkan transaksi, update kategori & manual description, detail transaksi
- **Routes**: 
  - `/transaksi` (list)
  - `/transaksi/{transactionId}` (detail)
  - `/transaksi/{transactionId}/uraian-manual` (update)
  - `/pajak` (summary pajak)

### Modul 8: SPJ & Paket Dokumen
- **Controllers**: SpjController, SpjDocumentController
- **Services**: SpjPackageValidationService, SpjPdfService, SpjTemplateService, SpjTransactionDetailsService
- **Models**: SpjPackage
- **Middleware**: `active-school`, `active-year`
- **Fungsi**: Persiapan paket SPJ, validasi, penomoran, preview, download PDF/Template
- **Routes**:
  - `/spj` (list tab: persiapan, paket, laporan, monitoring)
  - `/spj/{transactionId}/siapkan` (prepare)
  - `/spj/paket/{packageId}` (update detail)
  - `/spj/paket/{packageId}/nomor` (assign number)
  - `/spj/paket/{packageId}/unduh` (download PDF)
  - `/spj/paket/{packageId}/template/{templateId}/pratinjau` (preview template)
  - `/spj/paket/{packageId}/template/{templateId}/unduh` (download from template)
  - `/spj/unduh/{format}` (export all - PDF/Excel)

### Modul 9: Data Sinkronisasi
- **Controllers**: SyncedDataController
- **Middleware**: `active-school`, `active-year`
- **Fungsi**: Monitor hasil sinkronisasi ARKAS (RKAS, BKU, referensi)
- **Routes**: `/data-sinkron`, `/data-sinkron/{type}`

### Modul 10: Template Dokumen
- **Controllers**: DocumentTemplateController
- **Models**: DocumentTemplate
- **Middleware**: `administrator`
- **Fungsi**: Upload & manage template Word/Excel per kategori SPJ
- **Routes**: `/pengaturan/template-dokumen` (list, upload, mapping, delete)

### Modul 11: Audit & Laporan
- **Controllers**: AuditReportController
- **Services**: AuditReportService, OperationalAuditService
- **Middleware**: `active-school`, `active-year`
- **Fungsi**: Log audit trail, generate laporan audit PDF/Excel
- **Routes**: `/laporan-audit`, `/laporan-audit/unduh/{format}`

### Modul 12: ArKAS Sync
- **Controllers**: ArkasSyncController
- **Services**: ArkasSynchronizationService, ArkasBridgeClient
- **Middleware**: `administrator`, throttle
- **Fungsi**: Trigger sinkronisasi data dari ARKAS
- **Routes**: `/sinkronisasi/arkas` (POST)

## 5. Key Services

### ArkasSynchronizationService
Mengelola sinkronisasi penuh dari ARKAS:
- `synchronize(School, FiscalYear, ArkasSource)`: Main method
- `saveRkas()`: Simpan RKAS dari ARKAS
- `saveBkuAndTransactions()`: Simpan BKU & buat transaction records

### ArkasBridgeClient
Adapter untuk ARKASBridge executable:
- `execute(ArkasSource, command, year?)`: Run bridge command
- `resolveBridgeExecutable()`: Resolve path berdasarkan OS

### SchoolDatabaseManager
Manajemen lifecycle database sekolah:
- `provision(School)`: Buat & migrate DB baru
- `activate(School)`: Set koneksi 'school' ke DB tertentu
- `ensureMigrated(School)`: Pastikan semua migrations sudah jalan

### SpjPackageValidationService
Validasi kelengkapan paket SPJ sebelum cetak:
- `validate(SpjPackage)`: Return array masalah yang ditemukan

### SpjPdfService
Generate PDF dokumen SPJ:
- `download(SpjPackage, School)`: Load view & stream PDF

### SpjTemplateService
Process template Word/Excel untuk SPJ:
- `fill()`: Replace placeholder dengan data transaksi
- `repeat()`: Duplicate baris untuk detail items

### OperationalAuditService
Log aktivitas operasional penting:
- `record(yearId, entityType, entityId, action, description)`

### SpjTransactionDetailsService
Format & prepare data transaksi untuk display/export.

## 6. Middleware & Kontrol Akses

| Middleware | Fungsi |
|-----------|--------|
| `auth` | Ensure user logged in |
| `guest` | Ensure user NOT logged in (setup/login page) |
| `active-school` | Ensure active_school_id di session + activate DB |
| `active-year` | Ensure active_fiscal_year_id di session |
| `administrator` | Ensure user->role === 'ADMIN' |
| `throttle:N,M` | Rate limiting (N requests per M minutes) |

## 7. Kategori SPJ & Field Mapping

| Kategori | Field Wajib | Related Models |
|----------|-----------|----------------|
| BARANG | description, unit_price, quantity | SpjGoods, TransactionItem |
| BELANJA_MODAL | (sama seperti BARANG) | SpjGoods |
| KONSUMSI | name, portions | SpjParticipant, SpjGoods |
| PEMELIHARAAN | job_description, work_days, daily_rate | SpjWorker |
| UPAH | (sama seperti PEMELIHARAAN) | SpjWorker |
| HONOR_PEGAWAI | (sama seperti PEMELIHARAAN) | SpjWorker |
| SPPD | traveler_name, destination, departure_date | SpjTravel |
| JASA_LAINNYA | description, amount | TransactionItem |

## 8. Status & Workflow

### Transaction Status
- `DRAFT` → baru sinkron / editing
- `SIAP` → siap untuk SPJ
- `BERLAKU` → telah dicetak

### SPJ Package Status
- `DRAFT` → baru dibuat, belum nomor
- `DISIAPKAN` → sudah validasi, siap cetak
- `DICETAK` → sudah generate PDF
- `DIARSIPKAN` → archived

### Sync Run Status
- `RUNNING` → sedang sinkron
- `SUCCESS` → berhasil
- `FAILED` → gagal

## 9. Fitur Keamanan & Compliance

✅ **Validation**:
- Input validation pada setiap form
- CSRF protection (Laravel default)
- SQL injection protection (Eloquent)

✅ **Authorization**:
- Role-based access (ADMIN vs OPERATOR)
- School isolation (user hanya bisa akses sekolahnya)
- Fiscal year scoping (query selalu filter by active year)

✅ **Audit Trail**:
- Semua aktivitas penting dicatat di operational_audit_logs
- User tracking (who, what, when)
- Timestamp tracking pada setiap change

✅ **Data Protection**:
- Database password di ARKAS source di-encrypt
- Backup sebelum restore
- Soft deletes support (jika diperlukan)

## 10. Performance & Optimization

✅ **Database**:
- Indexes pada: `transaction_date`, `no_bukti`, `status`, `fiscal_year_id`
- Foreign keys dengan cascade delete
- Unique constraints untuk prevent duplicates

✅ **Caching**:
- Session-based active context (school, year)
- Eager loading relasi (with/withCount)
- Pagination pada list views

✅ **Query Optimization**:
- Avoid N+1 dengan eager loading
- Use selective select() untuk columns
- Filter query di controller sebelum paginate

## 11. Fase Pengembangan & Roadmap

### ✅ Fase 1: Fondasi (Current)
- Setup initial
- Login & role management
- Multi-school & multi-fiscal year
- ARKAS sinkronisasi
- Dashboard & monitoring
- Basic SPJ preparation

### 🔄 Fase 2: Dokumen & Export (Next)
- Template Word/Excel dengan placeholder
- PDF generation & customization
- Batch export
- Preview sebelum cetak

### 📋 Fase 3: Automation & Integration (Future)
- Scheduled sync
- Email notifications
- API untuk integrasi sistem lain
- Mobile app support

### 🔒 Fase 4: Compliance & Reporting (Future)
- Advanced audit reports
- Compliance checklists
- Integration dengan sistem inspeksi

## 12. Troubleshooting & Common Issues

### Issue: "Transaksi tidak ditemukan pada tahun aktif"
**Cause**: Active fiscal year di session tidak sesuai dengan transaction
**Solution**: Pastikan year yang dipilih sebelum akses transaksi

### Issue: "Database sekolah tidak dapat dibuat"
**Cause**: Permission issue pada storage/app/school-databases/
**Solution**: Ensure direktori writable, check file permissions

### Issue: "ARKASBridge gagal dijalankan"
**Cause**: Bridge executable tidak cocok dengan OS atau database format
**Solution**: Verify bridge path, check database compatibility, see ArkasBridgeClient

### Issue: "SPJ Validation Failed"
**Cause**: Field wajib belum lengkap
**Solution**: Check SpjPackageValidationService untuk list field yang wajib

## 13. Referensi File Penting

```
app/
├── Http/
│   ├── Controllers/         → 20+ controller untuk setiap modul
│   └── Middleware/          → Access control & context activation
├── Models/                  → 17 models (Transaction, SpjGoods, dll)
├── Services/                → 13 services (Sync, PDF, Validation, dll)
└── Livewire/                → RkasTable, RkasBudgetTable

database/
├── migrations/              → Main database migrations
└── migrations/school/       → School database migrations (21 files)

routes/
└── web.php                  → 40+ routes dengan middleware grouping

resources/
├── views/
│   ├── spj/                 → SPJ preparation views
│   ├── transactions/        → Transaction list & detail
│   ├── audit-reports/       → Laporan audit
│   ├── dashboard.blade.php  → Dashboard utama
│   └── (lainnya)
└── js/                      → Alpine.js + Tailwind

docs/
├── DOKUMENTASI_MODUL.md                    → Module overview (original)
├── REFACTORING_SPJ_ARCHITECTURE.md         → Architecture refactoring
├── REFACTORING_QUICK_REFERENCE.md          → Quick reference
├── REFACTORING_IMPLEMENTATION_LOG.md       → Implementation details
├── PANDUAN_OPERATOR_RINGKAS.md             → Operator guide
├── ARCHITECTURE_COMPLETE.md                → This file (comprehensive)
└── (dokumentasi lainnya)
```

---

**Document Version**: 1.0  
**Last Updated**: 2026-08-31  
**Created by**: Copilot Code Assistant  
**Status**: Complete & Comprehensive Architecture Documentation
