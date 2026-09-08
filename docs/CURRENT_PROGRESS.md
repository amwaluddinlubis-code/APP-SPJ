# SPJ BOSP Web — Current Progress / Open Issues

Terakhir diperbarui: **2026-09-08**

Dokumen ini hanya memuat kondisi yang **belum release-ready** pada branch `gui-standardization`. Item yang sudah selesai tidak dipelihara sebagai daftar PASS panjang.

## Baseline yang sudah ditutup

Ownership migration **Detail Transaksi ↔ Paket SPJ** sudah PASS:

```text
Detail Transaksi = source transaksi + item_description
Paket SPJ        = seluruh data dokumen pertanggungjawaban
```

Write-path legacy sudah dipensiunkan, pajak immutable dari Paket, category switch berjalan tanpa reload, dan focused regression suite SPJ sebelumnya dilaporkan user **ALL PASS** pada 2026-09-08.

Refinement UI yang juga sudah masuk source:

- Detail Transaksi disederhanakan: Informasi Referensi ARKAS/BKU + Total Pajak, lalu Rincian Barang/Jasa, lalu Status Paket SPJ;
- daftar transaksi memakai satu tombol `Aksi` yang membuka modal navigasi Detail/Paket;
- topbar `Livewire + Filament` diganti menu Profil User;
- toolbar Paket memiliki `Semua Paket`, `Paket Sebelumnya`, `Paket Setelahnya` dalam konteks tahun+sumber dana aktif;
- summary Paket menjadi `Periode | Penerima | Bruto | Pajak | Nilai Dibayarkan`;
- nilai uang UI memakai accounting Indonesia tanpa `Rp`/desimal (`1.000`);
- tab Paket menjadi `Rincian | Isian Manual | Rincian Pajak | Penomoran`;
- Kategori SPJ + kontrol konteks sejajar; BARANG memakai radio `SiPLah / Non SiPLah` mutually-exclusive;
- PEMELIHARAAN menampilkan selector pasangan transaksi di baris kategori;
- Data Umum Dokumen memakai textarea 5 baris di kiri dan seluruh input umum lain di kanan;
- nomor otomatis tidak lagi menjadi input operator dan ditampilkan sebagai informasi horizontal;
- tabel kategori non-BARANG compact, integer/accounting sesuai tipe data, satu `Penerima Utama`, dan hanya satu pagination lokal;
- Vite canonical kembali ke `resources/css/app.css` + `resources/js/app.js`; stale standalone asset entry dihapus.

Perubahan visual terakhir setelah checkpoint test ALL PASS tetap perlu browser QA setelah pull/build. Jangan menganggap browser QA sama dengan PHPUnit.

---

## 1. P0-01 — E2E enam kategori berbasis database nyata

**Status: RVR — audit source dan tool audit sudah tersedia; menunggu laptop/database SDN 10208183.**

Dataset target pertama adalah database tenant SDN **10208183** yang sudah berisi pekerjaan SPJ satu triwulan.

Urutan P0-01 yang dikunci:

```text
P0-01A  Audit database nyata secara read-only
P0-01B  Petakan coverage + pilih kandidat enam kategori
P0-01C  Jalankan E2E kandidat nyata
P0-01D  Catat/fix blocker
P0-01E  Ulangi sampai 6/6 PASS
```

Source baru:

```text
app/Console/Commands/AuditSpjQuarter.php
app/Services/SpjQuarterAuditService.php
tests/Feature/SpjQuarterAuditCommandTest.php
docs/P0_01_SOURCE_AUDIT.md
```

Command canonical:

```powershell
php artisan spj:audit-quarter 10208183 --quarter=1
```

Auditor sengaja tidak memanggil `SchoolDatabaseManager::activate/provision/ensureMigrated`, tidak membuat database bila file hilang, memaksa `PRAGMA query_only=ON`, dan hanya melakukan pemeriksaan read-only.

Cakupan auditor:

- SQLite integrity + foreign-key check;
- schema/migration snapshot;
- transaksi/item/item_description/source status/reconciliation;
- gross/tax/net dan indikator komponen pajak;
- lifecycle Paket;
- coverage keenam kategori;
- detail BARANG/KONSUMSI/PEMELIHARAAN/SPPD/HONOR/JASA_LAINNYA;
- duplicate active document number;
- rekomendasi satu kandidat E2E per kategori.

Regression test read-only sudah **ditambahkan ke source**, tetapi **belum dijalankan dalam sesi ini**. Test tersebut membandingkan hash file tenant sebelum/sesudah command dan memastikan metadata `school_databases.updated_at` tidak berubah.

Audit source P0-01 menyimpulkan jalur produksi untuk create/open DRAFT, save detail keenam kategori, validation/requirements, numbering, preview/download, dan happy-path FINAL tersedia. Status tetap RVR karena dataset nyata, template nyata, dan runtime enam kategori belum dapat dijalankan saat laptop off. Detail audit ada di `docs/P0_01_SOURCE_AUDIT.md`.

### TODO P0-01 berikutnya

Saat laptop/database tersedia:

```powershell
php artisan test --compact --filter=SpjQuarterAuditCommandTest
php artisan spj:audit-quarter 10208183 --quarter=1
```

Lalu:

1. review semua CRITICAL/WARNING;
2. pastikan enam kategori mempunyai coverage nyata;
3. pilih satu kandidat terbaik per kategori dari output auditor;
4. jalankan Detail → DRAFT → READY → NUMBERED → preview/download → FINAL;
5. jangan memperbaiki data dengan SQL manual;
6. catat titik gagal pertama per kategori dan patch source/test;
7. ulangi sampai 6/6 PASS.

---

## 2. Kontrak aktif

- ARKAS/BKU adalah source readonly; data operator SPJ adalah overlay terpisah.
- Kategori canonical: `BARANG`, `KONSUMSI`, `PEMELIHARAAN`, `JASA_LAINNYA`, `SPPD`, `HONOR_PEGAWAI`.
- SiPLah bukan kategori; gunakan `payment_method = siplah`.
- Detail Transaksi hanya boleh menulis `item_description`.
- Paket SPJ adalah satu-satunya workspace mutation kategori/payment/vendor/data kategori.
- Pajak source tidak boleh diubah atau dihitung ulang dari Paket SPJ.
- Nomor otomatis bukan input manual operator.
- `NUMBERED`/`FINAL` terkunci dari edit normal.
- Preview/download tidak boleh mengalokasikan nomor diam-diam.
- Workflow Transaksi/Persiapan/Dashboard memakai `SpjWorkflowFilterService` sebagai kontrak status operator.
- Root data eksternal dapat diatur dengan `SPJ_DATA_PATH`; fallback `storage/app`.
- Database sekolah: `{SPJ_DATA_PATH}/school-databases/{NPSN}/spj.sqlite`.
- Dummy: `{SPJ_DATA_PATH}/school-databases/_unselected.sqlite`.
- Backup: `{SPJ_DATA_PATH}/backups/{NPSN}/...`.

---

## 3. RVR — source tersedia, menunggu verifikasi runtime

### R01 — APP DATA eksternal

Konfigurasi path sudah portable, tetapi masih perlu runtime check pada database sekolah nyata untuk:

```text
provision
reset total tenant + sqlite_sequence
backup
restore
WAL/SHM cleanup
switch sekolah
```

Sebelum checkpoint tersebut PASS, APP DATA belum release-ready.

### R02 — Browser QA refinement Paket SPJ terbaru

Perubahan markup/JS terakhir perlu diverifikasi di browser setelah `npm run build`:

- radio SiPLah/Non SiPLah hanya satu yang aktif;
- selector PEMELIHARAAN benar-benar berada di baris kategori;
- Data Umum Dokumen tidak turun ke bawah textarea pada desktop;
- tabel non-BARANG hanya memiliki satu pagination;
- tab Rincian Pajak menjadi tab ke-3;
- previous/next Package dan warning state tampil benar;
- responsive/mobile tidak pecah.

Ini adalah QA visual/runtime, bukan gap ownership backend.

---

## 4. FAIL / belum tuntas yang masih aktif

### F01 — JASA_LAINNYA multi-penerima belum sepenuhnya end-to-end

Yang masih belum selesai:

- gross/tax/net tiap penerima pada output template;
- kuitansi/dokumen per penerima bila template membutuhkan;
- end-to-end preview/download/final.

### F02 — Generator dokumen belum release-hardened untuk seluruh kategori

Foundation Word/Excel/PDF, unresolved-placeholder guard, preview, download per template, dan package export sudah ada. Masih perlu satu checkpoint yang membuktikan:

- seluruh template aktif per kategori menghasilkan output valid;
- preview tidak mempunyai side effect;
- placeholder identitas/pajak/nomor konsisten;
- error template terbaca operator;
- output paket multi-template benar.

### F03 — Lifecycle / authorization / reconciliation belum release-hardened terpadu

Masih perlu suite terpadu untuk cancellation/reissue/reopen, locking NUMBERED/FINAL, role ADMIN/OPERATOR/VIEWER, snapshot/diff reconciliation, dan perlindungan final document terhadap sync.

### F04 — P0-01 E2E keenam kategori belum ditutup

Tool audit dan audit source sudah tersedia, tetapi belum ada checkpoint database nyata yang membuktikan seluruh kategori berjalan penuh:

```text
source
→ Detail Transaksi
→ DRAFT
→ READY
→ NUMBERED
→ preview/download
→ FINAL
```

Focused tests kategori yang PASS tidak sama dengan full document/lifecycle E2E.

### F05 — Mobile visual QA masih terbuka

`docs/MOBILE_VISUAL_QA_TODO.md` belum ditutup.

### F06 — Pusat Laporan dan laporan BOS resmi masih roadmap

Pusat Laporan, K7/K7A/K8/SPTJM/K7B/K7C, laporan pajak lengkap, laporan kategori, serta monitoring/audit terpadu belum dianggap fitur release. Format resmi harus dikonfirmasi sebelum klaim compliance.

---

## 5. Verification queue

Checkpoint focused SPJ sebelumnya dilaporkan **ALL PASS** oleh user. Perubahan auditor terbaru belum mendapat runtime checkpoint.

Prioritas pertama setelah pull:

```powershell
php vendor/bin/pint --dirty --format agent
php artisan test --compact --filter=SpjQuarterAuditCommandTest
php artisan spj:audit-quarter 10208183 --quarter=1
```

Untuk UI terbaru tetap jalankan bila relevan:

```powershell
npm run build
php artisan view:cache --no-interaction
php artisan optimize:clear
php artisan test --compact --filter="SpjWorkspaceMigrationTest|SpjOwnershipMigrationTest|SpjMaintenanceLinkWorkspaceTest"
git diff --check
```

Browser QA tetap diperlukan untuk item R02.

---

## 6. Aturan status dokumentasi

- **FAIL** — masih ada gap implementasi/domain nyata.
- **RVR** — source sudah tersedia tetapi runtime/browser/dataset terbaru belum diverifikasi.
- **PLANNED** — belum diimplementasikan.
- **PASS** tidak disimpan sebagai backlog aktif; keputusan permanennya dipindahkan ke dokumen desain/arsitektur.

Baca bersama:

```text
README.md
docs/P0_01_SOURCE_AUDIT.md
docs/SPJ_DESIGN_DECISIONS.md
docs/ARCHITECTURE_COMPLETE.md
docs/DEVELOPMENT_ROADMAP.md
docs/GUI_STANDARDIZATION.md
docs/URGENT_TRANSACTION_SPJ_MIGRATION.md
```
