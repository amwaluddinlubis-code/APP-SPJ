# SPJ BOSP Web — Rencana Pengembangan

Terakhir diperbarui: **2026-09-10**

Roadmap ini memusatkan pekerjaan yang masih perlu diselesaikan dan checkpoint P0 yang sudah ditutup pada branch `gui-standardization`. Migrasi ownership Detail Transaksi ↔ Paket SPJ sudah PASS; detail historisnya ada di `docs/URGENT_TRANSACTION_SPJ_MIGRATION.md`.

Baseline correctness P0-08 saat ini adalah commit `b3aa081c1a16e51ccdf80466877d2398b2b0de3e`, yang mencakup source-key regression sebelumnya serta tenant activation/isolation + cross-school/cross-year regression Generic ARKAS Importer.

# P0 — Core Release Safety

P0 harus selesai sebelum aplikasi disebut aman menghasilkan SPJ pada data nyata. Pekerjaan correctness/tenant boundary selalu didahulukan daripada penambahan fitur atau polish GUI.

## P0 Verification Kit — functional CI PASS, real tenant RVR

Agar milestone P0 tidak mengulang fixture, daftar test, build command, dan audit dari awal, verification kit canonical tersedia. Detail penggunaan: `docs/P0_VERIFICATION_KIT.md`.

### Sudah masuk source / terverifikasi

- [x] `tests/Support/SpjScenarioFactory.php` sebagai payload factory enam kategori;
- [x] unit contract untuk scenario factory;
- [x] PHPUnit suite `SPJ Critical` sebagai regression release-safety canonical;
- [x] `php artisan spj:verify` sebagai satu entry point style → critical tests → frontend build → Blade compile → optional real-tenant audit;
- [x] `spj:audit-quarter --output=...` untuk menyimpan baseline audit JSON;
- [x] `spj:audit-diff before.json after.json` untuk membandingkan audit tanpa membaca ulang database;
- [x] `--fail-on-regression` sebagai gate diff;
- [x] `.github/workflows/spj-critical.yml` untuk static/build/critical-test CI pada branch `gui-standardization`;
- [x] source-key resolver Generic ARKAS Importer dan regression non-empty import sudah masuk `SPJ Critical`;
- [x] tenant activation/isolation + cross-school/cross-year Generic ARKAS Importer sudah masuk `SPJ Critical`;
- [x] CI pada `b3aa081` membuktikan frontend build, Blade compile, dan `SPJ Critical` PASS (`131 tests / 867 assertions`);
- [x] CI docs-only changes di-skip agar dokumentasi tidak memicu verification run yang tidak perlu.

### Masih RVR / TODO

- [ ] first local `php artisan spj:verify` lengkap;
- [ ] first `spj:verify --npsn=10208183 --quarter=1` pada database nyata;
- [ ] selesaikan regression P0-08 untuk Upsert / Incremental / Full Refresh;
- [ ] selesaikan regression raw stable-key, schema drift/source kosong, dan queue/background import;
- [ ] masukkan regression importer lanjutan yang release-critical ke gate yang sesuai;
- [ ] bersihkan repository-wide Pint debt. Pada checkpoint `b3aa081`, Pint masih WARN karena `single_quote` pada `tests/Feature/SyncProgressUiTest.php`; status style sementara advisory, bukan functional PASS penuh.

Tenant boundary Generic ARKAS Importer **bukan TODO aktif lagi**. Boundary tersebut sudah FUNCTIONAL PASS dan hanya dibuka kembali bila regression baru menunjukkan kebocoran context.

Gunakan `php artisan spj:verify --strict-style` bila Pint perlu dijadikan blocking gate.

---

## P0-01 — E2E enam kategori berbasis database nyata

**Status: RVR — source auditor + verification kit siap dan CI functional PASS; menunggu database SDN 10208183.**

Detail checkpoint: `docs/P0_01_SOURCE_AUDIT.md`.

### Sudah masuk source

- [x] audit static jalur produksi Detail → DRAFT → save → validation → numbering → preview/download → FINAL;
- [x] `SpjQuarterAuditService` read-only;
- [x] command `spj:audit-quarter`;
- [x] coverage matrix enam kategori;
- [x] anomaly check integrity/FK/source/item/finansial/package/category detail;
- [x] kandidat E2E per kategori;
- [x] regression test yang membuktikan tenant file/metadata tidak ditulis oleh command;
- [x] reusable six-category payload factory;
- [x] audit baseline/diff tooling.

### TODO saat laptop/database tersedia

- [ ] pull source terbaru;
- [ ] jalankan `php artisan spj:verify`;
- [ ] jalankan `php artisan spj:verify --npsn=10208183 --quarter=1`;
- [ ] simpan baseline dengan `spj:audit-quarter ... --output=storage/app/audits/10208183-tw1-before.json`;
- [ ] review seluruh CRITICAL/WARNING;
- [ ] konfirmasi coverage BARANG;
- [ ] konfirmasi coverage KONSUMSI;
- [ ] konfirmasi coverage PEMELIHARAAN;
- [ ] konfirmasi coverage JASA_LAINNYA;
- [ ] konfirmasi coverage SPPD;
- [ ] konfirmasi coverage HONOR_PEGAWAI;
- [ ] pilih satu kandidat nyata per kategori;
- [ ] jalankan kandidat melalui Detail → DRAFT → READY → NUMBERED → preview/download → FINAL;
- [ ] patch blocker pertama yang ditemukan per kategori;
- [ ] simpan audit sesudah patch dan jalankan `spj:audit-diff --fail-on-regression`;
- [ ] ulangi sampai 6/6 PASS;
- [ ] catat hasil runtime final di `CURRENT_PROGRESS.md`.

Tidak boleh memperbaiki anomaly dengan SQL manual. Perbaikan harus melalui workflow/source code agar dapat diregresikan.

---

## P0-02 — Generator dokumen release-hardening

**Status: RVR/OPEN — perlu template/output nyata.**

Setelah kandidat P0-01 tersedia, verifikasi seluruh template applicable untuk keenam kategori:

- [ ] Word/Excel/PDF dapat dihasilkan;
- [ ] preview/download bebas side effect numbering;
- [ ] tidak ada placeholder unresolved;
- [ ] identitas sekolah/vendor/penerima/pajak/nomor benar;
- [ ] output Paket multi-template benar;
- [ ] error template manusiawi;
- [ ] output dapat dibuka secara nyata.

P0-01 dan P0-02 boleh menemukan bug secara bersamaan, tetapi PASS generator dicatat terpisah dari PASS lifecycle.

---

## P0-03 — Numbering + lifecycle hardening

**Status: FUNCTIONAL PASS.**

Kontrak yang sudah ditutup:

```text
DRAFT → READY → NUMBERED → FINAL
```

- [x] double-submit/idempotensi numbering;
- [x] nomor aktif tidak ganda;
- [x] locking NUMBERED/FINAL;
- [x] cancellation dengan alasan;
- [x] reopen/unlock;
- [x] reissue/replacement;
- [x] histori nomor tidak hilang;
- [x] package FINAL konsisten dengan lifecycle dokumen;
- [x] preview/download tidak mengalokasikan nomor.

---

## P0-04 — Authorization backend

**Status: FUNCTIONAL PASS untuk jalur SPJ yang sudah diregresikan.**

ADMIN/OPERATOR/VIEWER sudah dibuktikan pada backend request/middleware untuk jalur SPJ utama:

- [x] transaction mutation;
- [x] Paket mutation;
- [x] numbering/finalization;
- [x] cancellation/reissue/reopen;
- [x] template/configuration sensitif;
- [x] reconciliation guard;
- [x] reset/backup/restore tenant guard.

VIEWER tetap read-only, OPERATOR menangani mutation operasional normal, dan aksi sensitif/lifecycle administratif dibatasi ke ADMIN.

Generic ARKAS Importer tetap administrator-only; tenant-context action-nya sudah diregresikan secara terpisah pada P0-08.

---

## P0-05 — Safe sync + reconciliation

**Status: FUNCTIONAL PASS untuk canonical transaction/source adapter.**

- [x] source unchanged tidak mengubah overlay;
- [x] source changed memicu reconciliation yang benar;
- [x] perubahan rincian source dicatat tanpa bergantung hanya pada agregat transaksi;
- [x] source missing tidak menghapus pekerjaan operator;
- [x] source returning menyambung kembali ke state/identity lama;
- [x] `item_description`, payment/vendor/category detail tetap aman;
- [x] NUMBERED/FINAL tidak berubah diam-diam karena sync;
- [x] before/after snapshot disimpan sebagai source event;
- [x] diff field-level dan action hint tersedia untuk operator pada Detail Transaksi.

Source contract utama:

```text
database/migrations/school/2026_09_08_151500_add_source_reconciliation_events.php
app/Services/SpjSourceReconciliationService.php
resources/views/transactions/partials/detail/source-reconciliation.blade.php
tests/Feature/SpjSafeSyncReconciliationHardeningTest.php
```

Generic staging/importer tambahan tetap harus ditutup secara terpisah melalui P0-08.

---

## P0-06 — Tenant/context isolation

**Status: FUNCTIONAL PASS untuk resource SPJ + Generic ARKAS Importer boundary.**

Boundary aktif:

```text
Sekolah + Tahun Anggaran + Sumber Dana
```

- [x] cross-school session/mutation OPERATOR ditolak;
- [x] cross-year package/transaction ditolak;
- [x] cross-fund-source package/transaction/document ditolak;
- [x] previous/next Package tidak keluar context;
- [x] forged `transaction_id/package_id/document_id` tidak menjadi IDOR;
- [x] Generic ARKAS Importer mengaktifkan `active-school` sebelum `active-year`;
- [x] Generic ARKAS Importer menolak forged profile lintas sekolah pada preview/sync;
- [x] save mapping Generic ARKAS Importer hanya menulis tenant aktif;
- [x] stale fiscal-year context lintas tenant ditolak sebelum controller mengakses tenant yang salah.

Regression Generic Importer ada di `tests/Feature/ArkasImporterTenantBoundaryTest.php`.

---

## P0-07 — APP DATA / backup / reset / restore nyata

**Status: RVR — memerlukan runtime tenant nyata.**

Validasi pada database sekolah nyata:

```text
provision
switch tenant
backup
reset total + sqlite_sequence
restore
WAL/SHM cleanup
```

TODO:

- [ ] database utama tidak ikut terhapus;
- [ ] tenant file benar;
- [ ] WAL/SHM tidak meninggalkan state rusak;
- [ ] `sqlite_sequence` kembali bersih setelah reset;
- [ ] backup dapat direstore;
- [ ] restore mempertahankan data yang dibackup;
- [ ] switch sekolah setelah maintenance tetap aman.

---

## P0-08 — Generic ARKAS Importer + tenant boundary

**Status: IMPLEMENTED / SOURCE-KEY PASS / TENANT BOUNDARY PASS / HARDENING OPEN.**

Fondasi yang sudah ada:

```text
Bridge
→ ArkasStagingService
→ ArkasImportProfile / arkas_import_rows
→ reconciliation preview
→ ArkasDomainAdapter
→ target domain / raw snapshot
```

Correctness yang sudah ditutup:

- [x] `ArkasSourceKeyResolver` menjadi resolver tunggal untuk Generic Import, Staging, dan Reconciliation;
- [x] configured key diprioritaskan dan lookup nama kolom case-insensitive;
- [x] fallback identity canonical ARKAS tersedia sebelum fallback hash payload;
- [x] regression import record non-kosong menutup undefined runtime path lama;
- [x] semua action `ArkasImporterController` wajib melalui `active-school` lalu `active-year`;
- [x] guard route tetap administrator-only;
- [x] GET importer mengaktifkan database tenant yang benar;
- [x] save mapping menulis hanya pada tenant aktif;
- [x] forged profile lintas sekolah pada preview/sync menghasilkan 404;
- [x] stale fiscal-year ID lintas tenant ditolak setelah koneksi sekolah aktif dipilih;
- [x] source-key + tenant-boundary regression sudah masuk suite `SPJ Critical`;
- [x] CI critical pada `b3aa081` PASS `131 tests / 867 assertions`.

Regression/checklist yang masih terbuka:

- [ ] preview full reconciliation dibuktikan tidak menulis domain;
- [ ] regression Upsert deterministic;
- [ ] regression Incremental deterministic;
- [ ] regression Full Refresh hanya membersihkan scope tenant/fiscal year/domain aktif;
- [ ] regression raw profile dan stable-key behavior;
- [ ] regression schema drift dan source kosong;
- [ ] regression queue/background import dengan tenant activation yang benar;
- [ ] masukkan regression lanjutan yang release-critical ke gate yang sesuai.

Hardening berikutnya:

- [ ] tenant-scoped staging/import lock;
- [ ] semantics `created_at` tidak berubah saat upsert existing row;
- [ ] histori import membedakan new/changed/unchanged/removed;
- [ ] kebijakan raw profile tanpa stable key;
- [ ] Bridge-side delta fetch sebagai optimasi setelah correctness selesai.

**Exit criteria P0-08:** tenant activation/isolation sudah PASS. Importer baru boleh disebut `READY FOR OPERATOR TEST` setelah regression mode sinkronisasi utama (Upsert / Incremental / Full Refresh) PASS dan behavior raw/source-empty/schema-drift/queue yang release-critical sudah mempunyai kontrak regression yang memadai.

---

# P1 — Feature Completeness & Operational Quality

P1 dikerjakan setelah core P0 sudah cukup stabil atau sebagai follow-up blocker kategori yang tidak mengubah release-safety boundary.

## P1-01 — JASA_LAINNYA multi-penerima end-to-end

Fondasi aktif sudah mencakup penerima jamak, quantity × hari × tarif, gross detail, tax/net per penerima, alokasi rounding-safe, dan blocker rekonsiliasi.

TODO:

- [ ] gross/tax/net tiap penerima tampil pada output yang membutuhkan;
- [ ] kuitansi/dokumen per penerima bila template mensyaratkan;
- [ ] preview/download/final multi-penerima;
- [ ] agregat tetap:

```text
Σ gross = transaction.gross_amount
Σ tax   = transaction.tax_total
Σ net   = transaction.net_amount
```

Jangan membuat kategori baru seperti `SEWA_LAPTOP` atau `SEWA_MOBIL`; gunakan subtype di bawah `JASA_LAINNYA`.

---

## P1-02 — PEMELIHARAAN bahan + upah full-document QA

- [ ] linkage bahan/upah memakai transaksi active context;
- [ ] material dokumen diambil dari transaksi bahan;
- [ ] pekerja/upah diambil dari transaksi upah;
- [ ] RAB/SPK/kuitansi/A2 konsisten;
- [ ] source BKU dua transaksi tidak ditimpa/digabung permanen;
- [ ] kandidat real P0-01 membuktikan hasil dokumen.

---

## P1-03 — SiPLah E2E

- [ ] source SiPLah tetap authoritative;
- [ ] radio SiPLah/Non SiPLah benar di browser desktop/laptop;
- [ ] vendor/marketplace order/invoice/payment reference tersimpan;
- [ ] Surat Pesanan internal tidak diwajibkan untuk SiPLah;
- [ ] placeholder/output SiPLah benar;
- [ ] preview/download bebas side effect.

---

## P1-04 — Browser QA Paket SPJ — desktop/laptop

- [ ] radio `SiPLah / Non SiPLah` mutually-exclusive;
- [ ] selector PEMELIHARAAN berada di baris kategori;
- [ ] Data Umum Dokumen: textarea kiri, field umum kanan;
- [ ] summary 5 kolom benar;
- [ ] tab ke-3 `Rincian Pajak`;
- [ ] nomor otomatis hanya informasi;
- [ ] tabel non-BARANG compact;
- [ ] pagination non-BARANG hanya satu;
- [ ] previous/next Package sesuai context;
- [ ] usability desktop/laptop stabil.

Mobile/responsive QA penuh bukan blocker release saat ini dan dipindahkan ke backlog future development.

---

## P1-05 — Audit trail operasional

Pastikan aktivitas sensitif dapat ditelusuri:

```text
BUAT_DRAFT
UBAH_KATEGORI
PERBARUI_ISIAN
READY
NUMBERING
CANCEL
REISSUE
FINAL
REOPEN
RECONCILE
RESET_DB
RESTORE_DB
```

Setiap audit minimal mempunyai actor, waktu, school/context, entity, action, dan keterangan yang cukup.

---

## P1-06 — Employee identity dan participant roster contract

Identity layer boleh menyatukan provenance ARKAS, Dapodik, dan data manual secara aman, tetapi keputusan bisnis canonical pada `SPJ_DESIGN_DECISIONS.md` tetap:

```text
Auto-fill peserta KONSUMSI = Employee.source_type DAPODIK
Participant manual          = diperbolehkan
```

TODO:

- [ ] pastikan jalur auto-fill UI tidak mengambil semua Employee aktif hanya karena identity layer sekarang lintas sumber;
- [ ] nama ter-normalisasi tidak boleh menjadi alasan tunggal untuk silent merge orang berbeda bila identifier kuat bertentangan/tersedia;
- [ ] pertahankan NIP/NUPTK participant pada penyimpanan/output;
- [ ] regression untuk dua orang dengan nama sama tetapi identifier berbeda.

Perubahan kontrak Dapodik-only hanya boleh dilakukan melalui keputusan desain eksplisit, bukan sekadar mengikuti implementasi sementara.

---

# P2 — Product Polish & Maintainability

## P2-01 — Field-level validation UX

- pesan error manusiawi;
- fokus/tab diarahkan ke lokasi masalah;
- backend tetap authoritative;
- tidak membuat business rule baru hanya di JavaScript.

## P2-02 — GUI/compatibility + style cleanup

- kurangi CSS compatibility layer setelah markup canonical stabil;
- kurangi JS DOM mover bila native Blade bisa memiliki struktur yang benar;
- pertahankan `x-ui.*`, `ui-*`, theme token;
- jangan hidupkan kembali legacy write-path;
- bersihkan repository-wide Pint style debt dan kembalikan `spj:verify --strict-style` menjadi PASS.

## P2-03 — Icon/action consistency

Standardisasi action baru ke `<x-ui.icon>` serta hover/focus/disabled/tooltip yang konsisten.

## P2-04 — Performance

Profil sebelum optimasi:

- N+1 package/transaction;
- tabel transaksi;
- dashboard;
- preview/document context;
- template generator;
- ukuran bundle CSS/JS setelah correctness P0 stabil.

## P2-05 — Repository hygiene Bridge

- jangan version-control generated `bridge/src/**/obj`;
- jangan jadikan generated `bridge/src/**/bin` sebagai source canonical;
- binary publish resmi dapat dipertahankan pada lokasi distribusi yang disengaja, misalnya `bridge/bin/win-x64`, bila aplikasi memang menggunakannya;
- build source Bridge harus dapat direproduksi tanpa bergantung pada generated cache yang dicommit.

## P2-06 — Report foundation

Mulai dari laporan internal yang source/meaning-nya sudah jelas:

```text
BKU / rekap transaksi
RKAS vs realisasi
status workflow SPJ
register penomoran
rekap pajak
audit/reconciliation
```

K7A, K7, K8, SPTJM, K7B, K7C dan format resmi lain baru boleh disebut compliant setelah template/aturan resmi yang dipakai project dikonfirmasi.

---

# Future Development — Mobile / Responsive

Mobile/responsive QA penuh dipertahankan di `docs/MOBILE_VISUAL_QA_TODO.md`, tetapi bukan release blocker untuk target operator laptop/desktop saat ini.

---

# P3 — Laporan resmi / ekspansi setelah core stabil

Implementasi bertahap laporan BOS resmi dan ekspansi non-core setelah P0 release safety serta P1 workflow utama stabil.

---

## Urutan kerja efektif dari checkpoint sekarang

```text
1. P0-08 regression Upsert / Incremental / Full Refresh
2. P0-08 regression raw stable-key / source kosong / schema drift / queue
3. P0-08 hardening tenant-scoped lock / created_at / import metrics
4. P0-01 real-data E2E enam kategori
5. P0-02 generator dokumen nyata
6. P0-07 APP DATA nyata
7. P1 blocker yang ditemukan pada kandidat nyata
8. P2 cleanup / performance / GUI polish
```

Tidak menambah fitur baru sebelum regression correctness utama P0-08 selesai kecuali perubahan tersebut diperlukan untuk menutup blocker release.

---

## Definition of Done release candidate

Release candidate belum selesai sampai:

- seluruh P0 mendapat runtime checkpoint PASS atau keputusan RVR/out-of-scope eksplisit;
- P0-08 Generic ARKAS Importer mempertahankan tenant isolation yang sudah PASS dan lolos mode sinkronisasi utama sebelum operator test;
- keenam kategori lulus E2E nyata sampai FINAL + preview/download;
- preview/download bebas side effect;
- numbering/lifecycle/revision aman;
- safe sync tidak merusak overlay/final document;
- authorization sensitif diuji;
- tenant operation pada `SPJ_DATA_PATH` diuji;
- critical build/tests berhasil;
- gap P1 yang benar-benar diperlukan untuk sekolah target ditutup.

Baca bersama:

```text
docs/P0_VERIFICATION_KIT.md
docs/P0_01_SOURCE_AUDIT.md
docs/CURRENT_PROGRESS.md
docs/SPJ_DESIGN_DECISIONS.md
docs/ARCHITECTURE_COMPLETE.md
docs/GUI_STANDARDIZATION.md
docs/URGENT_TRANSACTION_SPJ_MIGRATION.md
```
