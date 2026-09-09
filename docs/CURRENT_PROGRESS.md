# SPJ BOSP Web — Current Progress / Open Issues

Terakhir diperbarui: **2026-09-10**

Dokumen ini memuat kondisi yang masih relevan untuk release pada branch `gui-standardization`. Item yang sudah ditutup diringkas sebagai baseline, bukan dipelihara sebagai backlog aktif.

Checkpoint kode yang menjadi acuan review saat ini:

```text
branch : gui-standardization
commit : b3aa081c1a16e51ccdf80466877d2398b2b0de3e
subject: test: verify ARKAS GET tenant isolation at connection boundary
```

### P0-08 — Importer ARKAS kanonik: IMPLEMENTED / SOURCE-KEY PASS / TENANT BOUNDARY PASS / HARDENING OPEN

Modul importer sudah mempunyai fondasi alur tunggal:

```text
Bridge -> staging -> mapping -> reconciliation -> domain adapter
```

Fitur yang sudah tersedia di source:

- preset mapping dan mapping awal otomatis berdasarkan nama kolom;
- validasi kunci, peran kolom, dan mode incremental;
- preview rekonsiliasi penuh dengan status Baru/Berubah/Tetap/Hilang;
- histori 10 import terakhir;
- mode Incremental, Upsert, dan Full refresh;
- schema drift warning berdasarkan snapshot daftar kolom;
- parent-child staging melalui `parent_source_key` dan `relation_type`;
- background import melalui queue `operations` bila `ARKAS_SYNC_ASYNC=true`;
- coordinator runtime `ArkasCanonicalSyncService` untuk dashboard, pemilihan tahun, dan job sinkronisasi utama;
- source-key resolver bersama `ArkasSourceKeyResolver` yang dipakai Generic Import, Staging, Reconciliation, dan preview importer.

Dua blocker correctness utama hasil review awal sekarang **sudah ditutup**:

1. **Source-key runtime PASS.** `ArkasGenericImportService` tidak lagi memanggil method `sourceKey()` yang tidak tersedia. Resolver bersama memprioritaskan configured key secara case-insensitive, memakai fallback identity ARKAS canonical, lalu hash payload deterministic sebagai fallback terakhir.
2. **Tenant boundary PASS.** Seluruh action `ArkasImporterController` wajib melewati `active-school` lalu `active-year` sebelum model connection `school` dibaca/ditulis, sementara guard `administrator` tetap dipertahankan oleh route group.

Regression yang sudah menjadi bagian suite `SPJ Critical`:

```text
tests/Unit/ArkasSourceKeyResolverTest.php
tests/Feature/ArkasGenericImportSourceKeyTest.php
tests/Feature/ArkasImporterTenantBoundaryTest.php
```

Regression tenant menggunakan dua file SQLite tenant terpisah dan membuktikan:

- semua route importer membawa `administrator`, `active-school`, dan `active-year`;
- `active-school` dijalankan sebelum `active-year`;
- GET importer mengaktifkan database sekolah yang benar;
- profile tenant lain tidak terlihat pada connection aktif;
- forged profile ID tenant lain pada preview/sync menghasilkan 404;
- save mapping hanya menulis pada database tenant aktif;
- fiscal-year ID yang hanya valid di tenant lain ditolak setelah koneksi dipindahkan ke sekolah aktif.

CI `SPJ Critical Verification` pada `b3aa081` membuktikan **131 tests / 867 assertions PASS**. Dengan demikian source-key dan tenant activation/isolation Generic Importer adalah **FUNCTIONAL PASS**.

P0-08 belum disebut release-ready karena regression/hardening berikut masih terbuka:

- preview full reconciliation harus dibuktikan tidak menulis domain;
- Upsert deterministic;
- Incremental deterministic;
- Full refresh hanya membersihkan scope tenant/fiscal year/domain aktif;
- schema drift blocking behavior;
- source kosong;
- queue/background execution dengan tenant activation yang benar;
- raw profile tanpa stable key agar tidak menyisakan versi record lama secara diam-diam;
- lock staging/import harus tenant-scoped;
- `created_at` existing row tidak boleh di-reset bila dimaksudkan sebagai waktu pertama dibuat;
- histori import sebaiknya membedakan read/new/changed/unchanged/removed.

Panduan operator/teknis: `docs/ARKAS_IMPORTER.md`.

Catatan: mode Incremental saat ini menyaring hasil snapshot Bridge di aplikasi. Delta fetch langsung dari database ARKAS belum tersedia pada Bridge dan tetap menjadi optimasi berikutnya, bukan blocker correctness utama selama hasil sinkronisasi deterministic.

## Baseline yang sudah ditutup

Ownership migration **Detail Transaksi ↔ Paket SPJ** sudah PASS:

```text
Detail Transaksi = source transaksi + item_description
Paket SPJ        = seluruh data dokumen pertanggungjawaban
```

Write-path legacy sudah dipensiunkan, pajak immutable dari Paket, category switch berjalan tanpa reload, dan Paket menjadi satu-satunya workspace mutation data dokumen SPJ.

### P0-03 — Numbering + lifecycle hardening: FUNCTIONAL PASS

Kontrak yang sudah diregresikan:

- numbering identik bersifat idempotent;
- sequence tidak meloncat akibat submit ulang;
- NUMBERED/FINAL terkunci dari edit normal;
- cancellation wajib mempunyai alasan;
- nomor dokumen yang dibatalkan tetap tersimpan sebagai histori;
- CANCELLED dapat dibuka kembali ke DRAFT tanpa menghapus histori dokumen batal;
- FINAL tidak dapat dibuka langsung melalui unlock normal;
- cancelled document dapat diterbitkan ulang melalui workflow yang tersedia;
- preview/download tidak memanggil number allocator.

Test utama: `tests/Feature/SpjLifecycleHardeningTest.php` dan `tests/Feature/DocumentNumberingWorkflowTest.php`.

### P0-04 — Authorization backend: FUNCTIONAL PASS

Boundary role aktif:

```text
VIEWER        = read-only
OPERATOR      = mutation operasional normal
ADMINISTRATOR = mutation sensitif / maintenance / lifecycle administratif
```

Mutation transaksi/Paket, prepare draft, READY, numbering individual, finalization, payment/receipt, ARKAS sync, dan data operasional dilindungi `operator-or-administrator` sesuai jalur runtime masing-masing.

Aksi sensitif tetap administrator-only, termasuk:

- cancellation/replacement dokumen bernomor;
- numbering triwulan;
- close/reopen triwulan;
- unlock Paket;
- template mutation;
- reset database;
- backup/restore;
- konfigurasi administratif.

Behavior middleware juga diuji: VIEWER ditolak 403 pada mutation guard, OPERATOR/ADMIN lolos pada mutation normal, dan hanya ADMIN yang lolos administrator guard.

Generic ARKAS Importer tetap administrator-only dan sekarang juga diregresikan untuk active-school + active-year sebelum tenant access.

### P0-05 — Safe sync + reconciliation: FUNCTIONAL PASS

Kontrak safe-sync sekarang diregresikan secara terpadu:

- source yang tidak berubah tidak membuat reconciliation baru dan tidak mengubah overlay;
- perubahan source transaction membuat event `SOURCE_CHANGED` dan menandai `requires_reconciliation` bila Paket sudah ada;
- perubahan rincian source yang tidak selalu terlihat dari agregat transaksi dipantau sebagai `SOURCE_ITEM_CHANGED`;
- source yang hilang ditandai `SOURCE_MISSING` tanpa menghapus transaksi, item manual, maupun Paket;
- source yang kembali menghasilkan `SOURCE_RETURNED` dan menyambung ke transaction/package identity yang sama;
- `item_description`, payment description, penerima kuitansi, vendor manual, kategori SPJ, dan detail Paket tetap aman dari overwrite sync;
- NUMBERED/FINAL tidak diubah diam-diam: status, nomor, dan snapshot Paket/dokumen tetap utuh saat source berubah;
- histori before/after source disimpan di `transaction_source_events`;
- `SpjSourceReconciliationService` membentuk diff field-level serta action hint sesuai lifecycle;
- Detail Transaksi menampilkan panel **Rekonsiliasi Sumber ARKAS/BKU** ketika ada source event atau kondisi yang membutuhkan perhatian.

Source utama:

```text
database/migrations/school/2026_09_08_151500_add_source_reconciliation_events.php
app/Services/SpjSourceReconciliationService.php
resources/views/transactions/partials/detail/source-reconciliation.blade.php
tests/Feature/SpjSafeSyncReconciliationHardeningTest.php
```

`SafeArkasSynchronizationTest` tetap menjadi regression dasar adapter transaksi, sedangkan `SpjSafeSyncReconciliationHardeningTest` menjadi release-safety contract untuk overlay, missing/returning, item-level source changes, FINAL locking, snapshot/diff, dan panel operator. Generic Importer mempunyai regression tambahan sendiri pada P0-08.

### P0-06 — Tenant/context isolation: FUNCTIONAL PASS untuk resource SPJ + Generic Importer boundary

Boundary yang dikunci:

```text
Sekolah + Tahun Anggaran + Sumber Dana
```

`spj-active-context` menolak forged `transactionId`, `packageId`, dan `documentId` jika resource berada di tahun atau sumber dana lain. Cross-school session untuk OPERATOR juga ditolak sebelum tenant database yang salah diaktifkan. ADMIN tetap dapat mengelola sekolah yang dipilih sesuai role-nya.

Previous/next Paket juga telah diregresikan agar tidak keluar dari sumber dana aktif.

Generic Importer sekarang mempunyai boundary tersendiri: `administrator → active-school → active-year → connection school`. Cross-school profile access, cross-year stale context, dan mapping write isolation sudah diregresikan.

Test utama:

```text
tests/Feature/SpjAuthorizationContextHardeningTest.php
tests/Feature/SpjRoleAuthorizationMiddlewareTest.php
tests/Feature/SpjSchoolIsolationTest.php
tests/Feature/SpjPackageNavigationContextTest.php
tests/Feature/ArkasImporterTenantBoundaryTest.php
```

### CI checkpoint terbaru

GitHub Actions `SPJ Critical Verification` pada commit `b3aa081c1a16e51ccdf80466877d2398b2b0de3e` membuktikan:

```text
frontend build         PASS
Blade view cache       PASS
SPJ Critical PHPUnit   PASS — 131 tests / 867 assertions
repository Pint        WARN — 1 pre-existing style issue
```

Source-key dan tenant-boundary regression Generic ARKAS Importer PASS di suite critical. Pint masih menemukan `single_quote` pada `tests/Feature/SyncProgressUiTest.php`; workflow dapat berstatus success karena repository-wide Pint masih advisory/`continue-on-error`.

Dengan demikian blocker runtime source-key dan tenant boundary **FUNCTIONAL PASS**. Generic Importer tetap mempunyai regression/hardening lanjutan sebelum dinilai release-ready.

---

## 1. P0-01 — E2E enam kategori berbasis database nyata

**Status: RVR — menunggu laptop/database SDN 10208183.**

Tool yang sudah tersedia:

```text
spj:audit-quarter
spj:audit-diff
spj:verify
SpjScenarioFactory
SPJ Critical suite
GitHub CI
```

Saat database nyata tersedia:

```powershell
php artisan spj:verify --npsn=10208183 --quarter=1
php artisan spj:audit-quarter 10208183 --quarter=1 --output=storage/app/audits/10208183-tw1-before.json
```

Lalu:

1. review CRITICAL/WARNING;
2. konfirmasi coverage BARANG, KONSUMSI, PEMELIHARAAN, JASA_LAINNYA, SPPD, HONOR_PEGAWAI;
3. pilih satu kandidat nyata per kategori;
4. jalankan Detail → DRAFT → READY → NUMBERED → preview/download → FINAL;
5. patch blocker melalui source/workflow, bukan SQL manual;
6. simpan audit sesudah patch dan bandingkan dengan `spj:audit-diff --fail-on-regression`;
7. ulangi sampai 6/6 PASS.

---

## 2. P0-02 — Generator dokumen release-hardening

**Status: RVR/OPEN.**

Foundation Word/Excel/PDF, unresolved-placeholder guard, preview, download per template, dan package export tersedia. Masih perlu pembuktian menggunakan template/output nyata untuk enam kategori:

- seluruh template applicable menghasilkan output valid;
- tidak ada placeholder unresolved;
- identitas sekolah/vendor/penerima/pajak/nomor benar;
- preview/download bebas side effect;
- output multi-template benar;
- file nyata dapat dibuka.

---

## 3. P0-07 — APP DATA / backup / reset / restore nyata

**Status: RVR — memerlukan runtime tenant nyata.**

Perlu diuji pada database sekolah nyata:

```text
provision
switch tenant
backup
reset total + sqlite_sequence
restore
WAL/SHM cleanup
```

Database utama tidak boleh ikut berubah/rusak dan restore harus mengembalikan data yang benar.

---

## 4. P1 aktif

Masih terbuka:

- JASA_LAINNYA multi-penerima sampai output dokumen nyata;
- PEMELIHARAAN bahan + upah full-document QA;
- SiPLah end-to-end output;
- Browser QA Paket SPJ **desktop/laptop**;
- audit trail operasional end-to-end;
- alignment Employee/participant roster: keputusan bisnis canonical untuk auto-fill `KONSUMSI` tetap `Employee.source_type = DAPODIK`, sementara identity layer baru dapat menyimpan provenance ARKAS/Dapodik/manual. Jalur roster UI harus dibuktikan tidak memperluas auto-fill melampaui kontrak tersebut tanpa keputusan desain baru.

Browser QA release saat ini hanya menargetkan perangkat utama operator: **laptop/desktop**. Checklist visual penting mencakup radio SiPLah, selector PEMELIHARAAN, layout Data Umum, satu pagination non-BARANG, tab Rincian Pajak, previous/next Paket, dan usability desktop.

---

## 5. Mobile — future development, bukan release scope saat ini

Mobile/responsive QA penuh sengaja **dikeluarkan dari P0–P2 aktif** karena pengguna utama aplikasi memakai laptop. `docs/MOBILE_VISUAL_QA_TODO.md` dipertahankan sebagai backlog pengembangan masa depan dan tidak menjadi blocker release saat ini.

---

## 6. P2 aktif

- field-level validation UX;
- GUI/compatibility cleanup dan pengurangan JS DOM mover;
- repository Pint/style cleanup;
- icon/action consistency;
- performance profiling dan pengurangan bundle frontend setelah correctness stabil;
- repository hygiene Bridge: generated `bridge/src/**/bin` dan `bridge/src/**/obj` tidak diperlakukan sebagai source canonical; binary publish resmi boleh dipertahankan terpisah bila memang bagian distribusi;
- report foundation.

---

## 7. Kontrak aktif yang tidak boleh diregresikan

- ARKAS/BKU = source readonly; data operator SPJ = overlay.
- Sync source tidak menghapus overlay; perubahan source dicatat sebagai reconciliation event.
- Source missing/returning tidak membuat transaction/package identity baru.
- NUMBERED/FINAL tidak dimutasi diam-diam oleh sync; perubahan source harus ditinjau melalui reconciliation/revision workflow.
- Kategori canonical: `BARANG`, `KONSUMSI`, `PEMELIHARAAN`, `JASA_LAINNYA`, `SPPD`, `HONOR_PEGAWAI`.
- SiPLah bukan kategori; gunakan `payment_method = siplah` dan source SiPLah tetap authoritative.
- Detail Transaksi hanya menulis `item_description`.
- Paket SPJ adalah workspace mutation dokumen.
- Pajak source tidak dapat ditulis dari Paket.
- Nomor otomatis bukan input manual operator.
- NUMBERED/FINAL terkunci.
- Preview/download tidak mengalokasikan nomor.
- Resource SPJ harus berada dalam School + Fiscal Year + Fund Source aktif.
- Generic ARKAS Importer yang membaca model tenant wajib melewati `administrator → active-school → active-year` sebelum query/write connection `school`.
- Auto-fill peserta `KONSUMSI` tetap mengikuti keputusan desain Dapodik-only sampai ada keputusan domain baru.
- Database sekolah: `{SPJ_DATA_PATH}/school-databases/{NPSN}/spj.sqlite`.

Command canonical:

```powershell
php artisan spj:verify
```

Saat SDN 10208183 tersedia:

```powershell
php artisan spj:verify --npsn=10208183 --quarter=1
```
