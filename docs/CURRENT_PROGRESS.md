# SPJ BOSP Web — Current Progress / Open Issues

Terakhir diperbarui: **2026-09-07**

Dokumen ini hanya memuat kondisi yang **belum dapat dinyatakan PASS** pada branch `gui-standardization`. Item yang sudah selesai dan telah diverifikasi tidak dipelihara sebagai daftar progres di sini.

---

## 1. Kontrak aktif

- ARKAS/BKU adalah source readonly; data operator SPJ adalah overlay terpisah.
- Kategori canonical: `BARANG`, `KONSUMSI`, `PEMELIHARAAN`, `JASA_LAINNYA`, `SPPD`, `HONOR_PEGAWAI`.
- SiPLah bukan kategori; gunakan `payment_method = siplah`.
- Transaksi SiPLah tidak memakai Surat Pesanan internal aplikasi; gunakan nomor/reference marketplace, invoice, payment reference, dan metadata penyedia sesuai kebutuhan.
- Workflow Transaksi/Persiapan/Dashboard memakai `SpjWorkflowFilterService` sebagai kontrak status operator.
- Root data eksternal dapat diatur dengan `SPJ_DATA_PATH`; bila tidak diisi aplikasi kembali ke `storage/app`.
- Database sekolah: `{SPJ_DATA_PATH}/school-databases/{NPSN}/spj.sqlite`.
- Dummy: `{SPJ_DATA_PATH}/school-databases/_unselected.sqlite`.
- Backup baru: `{SPJ_DATA_PATH}/backups/{NPSN}/...`.

---

## 2. RVR — source sudah diperbaiki, menunggu verifikasi lokal

### R01 — Kronologi tanggal pengadaan

`TransactionController` sudah disamakan dengan rule Paket SPJ:

```text
order_date <= transaction_date
order_date <= bap_date
bap_date <= bast_date
```

Pembatas lama `bap_date <= transaction_date` dan `bast_date <= transaction_date` sudah dihapus. Focused regression test tersedia di `TransactionPurchaseDateValidationTest`.

### R02 — Dashboard memakai workflow canonical

`ProductivityDashboardController` tidak lagi memakai `->has('items')` untuk menentukan pekerjaan operator. Bucket `unprepared`, `draft`, `ready`, dan `attention` sekarang memakai `SpjWorkflowFilterService` yang sama dengan Transaksi/Persiapan.

### R03 — Persiapan tidak lagi menampilkan istilah `needs_details`

Compatibility markup lama di view besar masih dipertahankan untuk menghindari rewrite berisiko, tetapi runtime UI sekarang menormalisasi:

```text
needs_details / Rincian belum ada -> attention / Perlu Perhatian
ready / Siap dibuat               -> ready / Siap Dinomori
```

Backend tetap menerima alias lama hanya untuk URL/bookmark historis.

### R04 — PEMELIHARAAN: dokumen membaca transaksi bahan + upah terkait

`SpjMaintenanceDocumentContextService` sudah ditambahkan. Pada preview/download dokumen kategori `PEMELIHARAAN`:

- rincian barang/material berasal dari transaksi bahan/barang terkait;
- rincian pekerja/upah berasal dari transaksi upah terkait;
- current transaction tetap menjadi identitas Paket SPJ;
- context hanya in-memory dan tidak menulis ulang source BKU.

Focused test tersedia di `MaintenanceDocumentContextTest`.

### R05 — APP DATA eksternal

Konfigurasi tidak lagi mengunci path mesin developer. `SPJ_DATA_PATH` bersifat opsional dengan fallback `storage/app`. Pada deployment Windows yang sedang dipakai:

```env
SPJ_DATA_PATH=D:/lrvProject/spj-bosp-data
```

Masih perlu runtime check provision/migrate/reset/backup/restore pada database nyata sekolah.

### R06 — Rekonsiliasi gross/tax/net JASA_LAINNYA

Migration baru menambah `tax_amount` dan `net_amount` pada setiap service recipient. Sinkronisasi mengalokasikan tax/net dari source secara proporsional terhadap gross dengan koreksi rounding pada baris terakhir. `SpjPackageValidationService` sekarang memblokir ketidaksesuaian:

```text
Σ gross detail != transaction.gross_amount
Σ tax detail   != transaction.tax_total
Σ net detail   != transaction.net_amount
net per line   != gross - tax
```

Focused test tersedia di `ServiceRecipientReconciliationTest`.

---

## 3. FAIL / belum tuntas yang masih aktif

### F01 — JASA_LAINNYA multi-penerima belum sepenuhnya end-to-end

Yang masih belum selesai:

- tampilkan gross/tax/net secara eksplisit pada output template per penerima;
- dokumen/kuitansi per penerima bila template membutuhkannya;
- end-to-end test sampai preview/download/final.

### F02 — Generator dokumen belum release-hardened untuk seluruh kategori

Foundation Word/Excel/PDF, unresolved-placeholder guard, preview, download per template, dan package export sudah ada. Yang masih perlu dibuktikan sebagai satu checkpoint release:

- seluruh template aktif per kategori menghasilkan output valid;
- preview tidak mempunyai side effect;
- placeholder identitas/pajak/nomor konsisten;
- error template terbaca operator;
- output paket multi-template benar.

### F03 — Lifecycle / authorization / reconciliation belum release-hardened terpadu

Masih perlu suite terpadu untuk cancellation/reissue/reopen, backend locking NUMBERED/FINAL, mutation ADMIN/OPERATOR/VIEWER, snapshot/diff reconciliation, dan perlindungan final document terhadap sync.

### F04 — End-to-end keenam kategori belum ditutup

Belum ada satu checkpoint yang membuktikan seluruh kategori berjalan:

```text
source -> DRAFT -> READY -> NUMBERED -> FINAL -> preview/download
```

tanpa edit database manual.

### F05 — Mobile visual QA masih terbuka

`docs/MOBILE_VISUAL_QA_TODO.md` belum ditutup. Status tetap RVR/FAIL sampai visual minimum diverifikasi.

### F06 — Pusat Laporan dan laporan BOS resmi masih roadmap

Pusat Laporan, K7/K7A/K8/SPTJM/K7B/K7C, laporan pajak lengkap, laporan kategori, serta monitoring/audit terpadu belum dianggap fitur release. Format resmi harus dikonfirmasi sebelum klaim compliance.

---

## 4. Verification queue setelah pull

Jalankan minimal:

```powershell
php vendor/bin/pint --dirty --format agent

php artisan test --compact tests/Feature/TransactionPurchaseDateValidationTest.php
php artisan test --compact tests/Feature/TransactionsWorkflowFilterTest.php tests/Feature/SpjPreparationFilterTest.php
php artisan test --compact tests/Feature/MaintenanceTransactionLinkTest.php tests/Feature/MaintenanceDocumentContextTest.php
php artisan test --compact tests/Feature/ServiceRecipientReconciliationTest.php

npm run theme:qa
npm run build
php artisan view:cache --no-interaction
git diff --check
```

Karena ada migration tenant baru, database sekolah aktif juga harus dimigrasikan melalui mekanisme aktivasi tenant yang benar sebelum menguji JASA_LAINNYA.

---

## 5. Aturan status dokumentasi

- **FAIL** — masih ada gap implementasi/domain yang nyata.
- **RVR** — source sudah diperbaiki atau cakupan test sudah tersedia tetapi belum diverifikasi pada working copy/runtime terbaru.
- **PLANNED** — belum diimplementasikan.

Setelah user melaporkan hasil **PASS**, item RVR terkait dihapus dari dokumen ini, bukan dipindahkan ke daftar PASS panjang.

Baca bersama:

```text
README.md
docs/SPJ_DESIGN_DECISIONS.md
docs/DEVELOPMENT_ROADMAP.md
docs/GUI_STANDARDIZATION.md
docs/CSS_USAGE_GUIDE.md
```
