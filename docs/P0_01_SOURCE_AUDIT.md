# P0-01 — Source Audit & Real-Database E2E Readiness

Terakhir diperbarui: **2026-09-08**

Status: **RVR — source path tersedia; menunggu audit database nyata SDN 10208183 dan E2E enam kategori.**

Dokumen ini adalah checkpoint kerja P0-01. Ia tidak menggantikan `CURRENT_PROGRESS.md` atau keputusan bisnis di `SPJ_DESIGN_DECISIONS.md`.

## 1. Tujuan P0-01

Membuktikan enam kategori canonical dapat berjalan dari source nyata sampai dokumen final:

```text
ARKAS/BKU
→ Detail Transaksi
→ item_description tersimpan
→ create/open DRAFT
→ Isian Manual Paket
→ validation
→ READY
→ NUMBERED
→ preview/download
→ FINAL
```

Kategori:

```text
BARANG
KONSUMSI
PEMELIHARAAN
JASA_LAINNYA
SPPD
HONOR_PEGAWAI
```

Focused test yang hanya membuktikan save/render kategori **bukan** pengganti P0-01 E2E.

## 2. Urutan checkpoint

```text
P0-01A  Audit database nyata secara read-only
P0-01B  Petakan coverage dan pilih kandidat 6 kategori
P0-01C  Jalankan E2E kandidat nyata
P0-01D  Catat/fix blocker yang ditemukan
P0-01E  Ulangi sampai 6/6 PASS
```

Dataset target pertama adalah database tenant **SDN 10208183**, satu triwulan yang sudah diisi operator.

## 3. Auditor read-only

Command canonical:

```powershell
php artisan spj:audit-quarter 10208183 --quarter=1
```

Opsional:

```powershell
php artisan spj:audit-quarter 10208183 --quarter=1 --year=2026
php artisan spj:audit-quarter 10208183 --quarter=1 --fund-source=BOSP
php artisan spj:audit-quarter 10208183 --quarter=1 --json
```

Jika `--year` tidak diberikan, auditor memilih tahun terbaru yang mempunyai transaksi dan menampilkan tahun yang dipilih.

### Guardrail read-only

`spj:audit-quarter` sengaja **tidak** memakai `SchoolDatabaseManager::activate()`, `provision()`, `ensureMigrated()`, atau method maintenance lain karena method tersebut dapat membuat/migrasi/mengubah metadata database.

Auditor:

1. hanya mencari sekolah dan record/path database yang sudah ada;
2. menolak bila file tenant tidak ditemukan — file tidak dibuat;
3. membuka SQLite yang sudah ada;
4. memaksa `PRAGMA query_only = ON`;
5. hanya menjalankan `SELECT` dan PRAGMA pemeriksaan;
6. tidak melakukan perbaikan otomatis.

Regression test `SpjQuarterAuditCommandTest` membandingkan hash file tenant sebelum/sesudah command dan memastikan metadata `school_databases.updated_at` tidak berubah.

## 4. Pemeriksaan database yang tersedia

Auditor memeriksa:

### SQLite/schema

- `PRAGMA integrity_check`;
- `PRAGMA foreign_key_check`;
- keberadaan tabel core `transactions`, `transaction_items`, `spj_packages`, `fiscal_years`;
- migration count/latest bila tabel `migrations` tersedia;
- tabel detail keenam kategori.

### Source dan item

- jumlah transaksi pada tahun/triwulan/sumber dana;
- source status non-ACTIVE;
- `requires_reconciliation`;
- transaksi tanpa item;
- `item_description` kosong;
- total item vs bruto sebagai indikator mapping yang perlu ditinjau.

### Finansial

- `gross_amount - tax_total = net_amount` dengan toleransi rounding 0,02;
- jumlah komponen PPN/PPh/SSPD vs `tax_total` sebagai informasi review source.

Perbedaan komponen pajak tidak otomatis dianggap korup karena `tax_total` dapat berasal dari source yang lebih authoritative; auditor menandainya sebagai **INFO** untuk review.

### Paket/lifecycle

- coverage `NONE/DRAFT/READY/NUMBERED/FINAL/CANCELLED` per kategori;
- DRAFT/READY yang sudah memiliki `numbered_at`;
- NUMBERED tanpa nomor/timestamp;
- FINAL tanpa nomor/numbered/finalized timestamp;
- CANCELLED tanpa timestamp;
- status package non-canonical;
- orphan package;
- duplicate active document number bila `spj_documents` tersedia.

### Detail kategori

- BARANG → `spj_goods`;
- KONSUMSI → `spj_participants`;
- PEMELIHARAAN → `spj_work_orders` + `spj_workers`;
- SPPD → `spj_travels`;
- HONOR_PEGAWAI → `spj_honors`;
- JASA_LAINNYA → `spj_service_recipients`, termasuk rekonsiliasi agregat gross/tax/net.

### Kandidat E2E

Untuk setiap kategori auditor memilih kandidat terbaik berdasarkan source aktif, tidak membutuhkan reconciliation, memiliki item dan `item_description`, bruto positif, dan persamaan gross-tax-net yang konsisten.

Kandidat adalah **rekomendasi audit**, bukan mutation otomatis. Command tidak mengubah status Paket.

## 5. Audit source P0-01

### 5.1 Gateway Detail Transaksi → DRAFT — SOURCE READY

`CreateSpjDraftUseCase` sudah menjadi gateway create/open Paket yang:

- membatasi tahun anggaran + sumber dana aktif;
- mensyaratkan minimal satu item;
- mensyaratkan seluruh `item_description` sudah tersimpan;
- idempotent terhadap Paket yang sudah ada;
- membuat DRAFT melalui `firstOrCreate`.

Runtime pada dataset SDN 10208183 tetap **RVR** sampai database dapat dijalankan.

### 5.2 Mutation Isian Manual keenam kategori — SOURCE READY

`UpdateSpjPackageDetailsUseCase` tetap menjadi write-path Paket dan mendelegasikan detail ke `SpjTransactionDetailsService`.

Mapping source tersedia:

```text
BARANG          → synchronizeGoods
KONSUMSI        → synchronizeGoods + synchronizeParticipants
PEMELIHARAAN    → synchronizeWorkOrder
SPPD            → synchronizeTravel
HONOR_PEGAWAI   → synchronizeHonors
JASA_LAINNYA    → synchronizeServiceRecipients
```

Nomor otomatis hanya dipertahankan bila caller lama mengirimnya; form Package baru tidak harus mengirim nomor untuk menjaga nomor yang sudah diterbitkan.

### 5.3 Penerima Utama — SOURCE READY

Flow manual mempunyai `primary_recipient_group` / `primary_recipient_index` dan fallback ke baris pertama yang valid.

Penerima utama akhirnya disinkronkan ke `transactions.receipt_recipient_name`; worker/service recipient yang memiliki flag khusus juga dipertahankan sesuai relation-nya.

### 5.4 Validation/READY — SOURCE READY, RUNTIME RVR

`SpjPackageValidationService` melakukan boundary validation transaksi/package, item, bruto, payment description/penerima, kategori, procurement chronology, JASA_LAINNYA reconciliation, dan requirement dokumen.

`SpjDocumentRequirementService` memiliki requirement category-aware untuk seluruh enam kategori dan membedakan SiPLah vs Non-SiPLah.

Yang belum dapat dibuktikan tanpa database/template nyata adalah apakah setiap Paket triwulan yang sudah diisi memenuhi semua requirement yang applicable.

### 5.5 Numbering — SOURCE READY, E2E RVR

`SpjNumberingUseCase` menyediakan:

- numbering package/document;
- chronology/source-order guard;
- cancel/reissue/reopen;
- quarter numbering;
- mapping tanggal event dokumen category-specific.

P0-01 belum menyatakan numbering PASS sampai kandidat nyata SDN 10208183 berhasil melewati READY → NUMBERED.

### 5.6 Preview/download — SOURCE READY, OUTPUT RVR

`SpjDocumentUseCase` mempunyai preview/download/package export dan guard nomor pada package terkunci. Preview tidak dipakai sebagai jalur penerbitan nomor.

Validitas isi Word/Excel/PDF/template tetap masuk checkpoint generator P0 berikutnya dan dibuktikan bersamaan ketika kandidat P0-01 dijalankan.

### 5.7 FINAL — SOURCE PATH ADA, E2E RVR

`SpjDocumentLifecycleService::finalize()` hanya menerima dokumen NUMBERED, menyimpan snapshot, kemudian membuat Paket FINAL ketika semua dokumen aktif yang diperhitungkan telah FINAL.

Basic FINAL path tersedia. Cancellation/reissue/reopen dan kelengkapan snapshot lintas kasus khusus tetap harus di-hardening pada P0 lifecycle; P0-01 hanya akan membuktikan happy-path enam kategori terlebih dahulu.

## 6. Matriks readiness source

| Kategori | Save detail | Validation/requirements | Numbering path | Preview/download path | FINAL path | Status P0-01 |
| --- | --- | --- | --- | --- | --- | --- |
| BARANG | tersedia | tersedia | tersedia | tersedia | tersedia | RVR real DB |
| KONSUMSI | tersedia | tersedia | tersedia | tersedia | tersedia | RVR real DB |
| PEMELIHARAAN | tersedia | tersedia | tersedia | tersedia | tersedia | RVR real DB + linkage |
| JASA_LAINNYA | tersedia | tersedia + gross/tax/net | tersedia | tersedia | tersedia | RVR real DB/output |
| SPPD | tersedia | tersedia | tersedia | tersedia | tersedia | RVR real DB |
| HONOR_PEGAWAI | tersedia | tersedia | tersedia | tersedia | tersedia | RVR real DB |

Tidak ditemukan write-path category yang secara source langsung membuat salah satu dari enam kategori mustahil mencapai DRAFT/READY/NUMBERED/FINAL. **Ini bukan berarti P0-01 PASS**; dataset nyata dan output dokumen belum dijalankan.

## 7. TODO ketika laptop/database tersedia

Jalankan terlebih dahulu:

```powershell
php artisan test --compact --filter=SpjQuarterAuditCommandTest
php artisan spj:audit-quarter 10208183 --quarter=1
```

Jika tahun/sumber dana perlu dibatasi:

```powershell
php artisan spj:audit-quarter 10208183 --quarter=1 --year=<TAHUN> --fund-source=<SUMBER_DANA>
```

Kemudian:

1. simpan output auditor;
2. review seluruh CRITICAL/WARNING;
3. pastikan coverage keenam kategori;
4. pilih satu kandidat nyata per kategori;
5. jangan memperbaiki data dengan SQL manual;
6. jalankan kandidat melalui workflow aplikasi;
7. catat titik pertama yang gagal untuk tiap kategori;
8. patch source/test;
9. ulangi sampai enam kategori PASS.

## 8. Definition of Done P0-01

P0-01 baru boleh dinyatakan PASS jika:

- database nyata melewati integrity/FK audit yang dapat diterima;
- ada kandidat nyata untuk enam kategori, atau kekosongan kategori dicatat dan disediakan fixture nyata pengganti yang disetujui;
- enam kategori melewati Detail → DRAFT → READY → NUMBERED → preview/download → FINAL;
- tidak ada edit database manual;
- ownership Detail Transaksi/Paket tidak dilanggar;
- nilai source tax/item tidak berubah karena workflow Paket;
- dokumen yang dihasilkan dapat dibuka dan memakai data kandidat yang benar;
- hasil test + runtime dicatat sebagai checkpoint PASS.
