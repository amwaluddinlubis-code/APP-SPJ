# SPJ BOSP Web — Rencana Pengembangan

Terakhir diperbarui: **2026-09-08**

Roadmap ini hanya memuat pekerjaan yang masih belum selesai. Migrasi ownership Detail Transaksi ↔ Paket SPJ sudah PASS dan tidak lagi menjadi milestone aktif; detail historisnya ada di `docs/URGENT_TRANSACTION_SPJ_MIGRATION.md`.

# P0 — Core Release Safety

P0 harus selesai sebelum aplikasi disebut aman menghasilkan SPJ pada data nyata.

## P0 Verification Kit — functional CI PASS, real tenant RVR

Agar milestone P0 tidak mengulang fixture, daftar test, build command, dan audit dari awal, verification kit canonical sekarang tersedia. Detail penggunaan: `docs/P0_VERIFICATION_KIT.md`.

### Sudah masuk source / terverifikasi

- [x] `tests/Support/SpjScenarioFactory.php` sebagai payload factory enam kategori;
- [x] unit contract untuk scenario factory;
- [x] PHPUnit suite `SPJ Critical` sebagai daftar regression release-safety canonical;
- [x] `php artisan spj:verify` sebagai satu entry point style → critical tests → frontend build → Blade compile → optional real-tenant audit;
- [x] `spj:audit-quarter --output=...` untuk menyimpan baseline audit JSON;
- [x] `spj:audit-diff before.json after.json` untuk membandingkan audit tanpa membaca ulang database;
- [x] `--fail-on-regression` sebagai gate diff;
- [x] `.github/workflows/spj-critical.yml` untuk static/build/critical-test CI pada branch `gui-standardization`;
- [x] first functional green GitHub Actions run: frontend build PASS, Blade compile PASS, `SPJ Critical` PASS **81 tests / 561 assertions**;
- [x] CI docs-only changes di-skip agar dokumentasi tidak memicu verification run yang tidak perlu.

### Masih RVR / TODO

- [ ] first local `php artisan spj:verify` lengkap;
- [ ] first `spj:verify --npsn=10208183 --quarter=1` pada database nyata;
- [ ] bersihkan repository-wide Pint debt: saat checkpoint CI pertama terdapat 12 style issues; status sementara WARN/advisory, bukan functional blocker.

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

Tutup kontrak:

```text
DRAFT → READY → NUMBERED → FINAL
```

beserta:

- [ ] double-submit/idempotensi numbering;
- [ ] nomor aktif tidak ganda;
- [ ] locking NUMBERED/FINAL;
- [ ] cancellation dengan alasan;
- [ ] reopen/unlock;
- [ ] reissue/replacement;
- [ ] histori nomor tidak hilang;
- [ ] package FINAL konsisten dengan lifecycle dokumen;
- [ ] preview/download tidak mengalokasikan nomor.

---

## P0-04 — Authorization backend

Buktikan ADMIN/OPERATOR/VIEWER pada request backend, bukan hanya visibility UI:

- [ ] transaction mutation;
- [ ] Paket mutation;
- [ ] numbering/finalization;
- [ ] cancellation/reissue/reopen;
- [ ] template/configuration;
- [ ] reconciliation;
- [ ] reset/backup/restore tenant.

Forged POST/PUT/DELETE dan direct URL harus ditolak sesuai role.

---

## P0-05 — Safe sync + reconciliation

- [ ] source unchanged tidak mengubah overlay;
- [ ] source changed memicu reconciliation yang benar;
- [ ] source missing tidak menghapus pekerjaan operator;
- [ ] source returning menyambung kembali ke state lama;
- [ ] `item_description`, payment/vendor/category detail tetap aman;
- [ ] NUMBERED/FINAL tidak berubah diam-diam karena sync;
- [ ] snapshot/diff cukup untuk operator menentukan tindakan.

---

## P0-06 — Tenant/context isolation

Boundary wajib:

```text
Sekolah + Tahun Anggaran + Sumber Dana
```

TODO:

- [ ] cross-school read ditolak;
- [ ] cross-school mutation ditolak;
- [ ] cross-year package/transaction ditolak;
- [ ] cross-fund-source package/transaction ditolak;
- [ ] previous/next Package tidak keluar context;
- [ ] forged `transaction_id/package_id` tidak menjadi IDOR.

---

## P0-07 — APP DATA / backup / reset / restore nyata

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
- [ ] radio SiPLah/Non SiPLah benar di browser;
- [ ] vendor/marketplace order/invoice/payment reference tersimpan;
- [ ] Surat Pesanan internal tidak diwajibkan untuk SiPLah;
- [ ] placeholder/output SiPLah benar;
- [ ] preview/download bebas side effect.

---

## P1-04 — Browser QA Paket SPJ

- [ ] radio `SiPLah / Non SiPLah` mutually-exclusive;
- [ ] selector PEMELIHARAAN berada di baris kategori;
- [ ] Data Umum Dokumen: textarea kiri, field umum kanan;
- [ ] summary 5 kolom benar;
- [ ] tab ke-3 `Rincian Pajak`;
- [ ] nomor otomatis hanya informasi;
- [ ] tabel non-BARANG compact;
- [ ] pagination non-BARANG hanya satu;
- [ ] previous/next Package sesuai context;
- [ ] minimum responsive desktop/tablet/mobile usable.

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

# P2 — Product Polish & Maintainability

## P2-01 — Mobile/responsive QA penuh

Tutup `docs/MOBILE_VISUAL_QA_TODO.md` pada breakpoint mobile/tablet/desktop termasuk table scroll, modal, tab, dropdown, sticky action, toast, dan form panjang.

## P2-02 — Field-level validation UX

- pesan error manusiawi;
- fokus/tab diarahkan ke lokasi masalah;
- backend tetap authoritative;
- tidak membuat business rule baru hanya di JavaScript.

## P2-03 — GUI/compatibility + style cleanup

- kurangi CSS compatibility layer setelah markup canonical stabil;
- kurangi JS DOM mover bila native Blade bisa memiliki struktur yang benar;
- pertahankan `x-ui.*`, `ui-*`, theme token;
- jangan hidupkan kembali legacy write-path;
- bersihkan repository-wide Pint style debt dan kembalikan `spj:verify --strict-style` menjadi PASS.

## P2-04 — Icon/action consistency

Standardisasi action baru ke `<x-ui.icon>` serta hover/focus/disabled/tooltip yang konsisten.

## P2-05 — Performance

Profil sebelum optimasi:

- N+1 package/transaction;
- tabel transaksi;
- dashboard;
- preview/document context;
- template generator.

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

# P3 — Laporan resmi / ekspansi setelah core stabil

Implementasi bertahap laporan BOS resmi dan ekspansi non-core setelah P0 release safety serta P1 workflow utama stabil.

---

## Definition of Done release candidate

Release candidate belum selesai sampai:

- seluruh P0 mendapat runtime checkpoint PASS atau keputusan out-of-scope eksplisit;
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
