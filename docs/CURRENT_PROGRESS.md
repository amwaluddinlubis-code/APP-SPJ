# SPJ BOSP Web — Current Progress / Open Issues

Terakhir diperbarui: **2026-09-08**

Dokumen ini hanya memuat kondisi yang **belum dapat dinyatakan PASS** pada branch `gui-standardization`. Item yang sudah selesai dan telah diverifikasi tidak dipelihara sebagai daftar progres di sini.

---

## URGENT — Migrasi Detail Transaksi ↔ Paket SPJ

**Prioritas: URGENT**

Rencana lengkap ada di:

```text
docs/URGENT_TRANSACTION_SPJ_MIGRATION.md
```

Target arsitektur final:

```text
Detail Transaksi = source transaksi + item_description
Paket SPJ        = seluruh data dokumen pertanggungjawaban
```

Keputusan aktif:

- `item_description` tetap hanya diedit di Detail Transaksi;
- quantity, unit, unit price, dan amount selalu readonly;
- `item_description` harus benar-benar tersimpan sebelum Paket SPJ dapat dibuat/dibuka;
- PPN, PPh 21/22/23/4(2), SSPD, total pajak, dan netto tetap milik transaksi/source;
- Paket SPJ hanya membaca pajak sebagai referensi readonly;
- kategori, uraian dokumen, metode/referensi pembayaran, penerima kuitansi, vendor, invoice, data kategori, numbering, preview/download/finalisasi hanya dikelola di Paket SPJ;
- pergantian kategori Paket SPJ tidak boleh melakukan full page reload.

### Status implementasi migrasi saat ini

Source utama sudah bergerak ke arsitektur baru:

- Detail Transaksi tidak lagi merender builder/form kategori SPJ;
- `item_description` tetap editable di `#rincian-transaksi`;
- gateway `transactions.prepare-spj` memvalidasi uraian item sudah tersimpan sebelum membuka/membuat Paket;
- draft/open package memakai `CreateSpjDraftUseCase`;
- penyimpanan Paket memakai `UpdateSpjPackageDetailsUseCase` dan tidak menulis ulang pajak source;
- validator mengarahkan masalah ke workspace pemiliknya;
- Combo Kategori SPJ sekarang persist via AJAX tanpa full reload;
- `SpjDocumentController` (dead controller) sudah dihapus;
- `SpjReportController` (dead controller) sudah dihapus;
- Stale JS selectors (`transaction-detail-ui.js`, `transaction-detail-common-fields-layout.js`, `transaction-detail-category-layout.js`, `maintenance-transaction-links.js`) sudah dihapus;
- Duplikat div wrapper di `spj/index.blade.php` sudah diperbaiki;
- Tax reference sudah native Blade readonly tanpa JS compatibility;
- 20 regression tests baru meliputi seluruh kontrak migrasi.

### Gap URGENT yang masih aktif

Semua item URGENT sudah diselesaikan:

- **U01** — PASS: route legacy `spj.prepare` tidak ada, `SpjPackageUseCase` tidak ada, dead controllers dihapus.
- **U02** — PASS: `transactions.manual-description.update` tidak ada, `TransactionController` hanya menulis `item_description`.
- **U03** — PASS: partial Paket SPJ sudah terpisah per kategori dengan `data-spj-section`.
- **U04** — PASS: semua kategori di-render di DOM, switching tanpa reload, tidak ada `form.submit()`.
- **U05** — PASS: pajak adalah native Blade readonly, JS compatibility dibersihkan.
- **U06** — PASS: 20 regression tests meliputi gateway, ownership boundary, tax immutability, category switch, dan keenam kategori.

Migrasi **sudah dapat dinyatakan PASS** untuk komponen ownership boundary.

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

## 2. RVR — source sudah diperbaiki, menunggu verifikasi runtime

### R01 — APP DATA eksternal

Konfigurasi tidak lagi mengunci path mesin developer. `SPJ_DATA_PATH` bersifat opsional dengan fallback `storage/app`. Pada deployment Windows yang sedang dipakai:

```env
SPJ_DATA_PATH=D:/lrvProject/spj-bosp-data
```

Masih perlu runtime check provision/reset/backup/restore pada database nyata sekolah sebelum dianggap release-ready.

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
source -> Detail Transaksi -> DRAFT -> READY -> NUMBERED -> FINAL -> preview/download
```

tanpa edit database manual dan tanpa pengisian data yang sama di Detail Transaksi serta Paket SPJ.

### F05 — Mobile visual QA masih terbuka

`docs/MOBILE_VISUAL_QA_TODO.md` belum ditutup. Status tetap RVR/FAIL sampai visual minimum diverifikasi.

### F06 — Pusat Laporan dan laporan BOS resmi masih roadmap

Pusat Laporan, K7/K7A/K8/SPTJM/K7B/K7C, laporan pajak lengkap, laporan kategori, serta monitoring/audit terpadu belum dianggap fitur release. Format resmi harus dikonfirmasi sebelum klaim compliance.

---

## 4. Verification queue setelah pull

Checkpoint migrasi URGENT sudah tercapai:

```powershell
php vendor/bin/pint --dirty --format agent          # PASS
npm run build                                         # PASS
php artisan view:cache --no-interaction               # PASS
php artisan test --compact --filter=Spj               # PASS (93 tests, 611 assertions)
php artisan test --compact --filter=Transaction       # PASS
```

APP DATA masih memerlukan runtime check provision/reset/backup/restore pada database sekolah nyata.

---

## 5. Aturan status dokumentasi

- **URGENT** — prioritas program yang harus didahulukan; bukan pengganti PASS/FAIL/RVR/PLANNED.
- **FAIL** — masih ada gap implementasi/domain yang nyata.
- **RVR** — source sudah diperbaiki atau cakupan test sudah tersedia tetapi belum diverifikasi pada working copy/runtime terbaru.
- **PLANNED** — belum diimplementasikan.

Setelah user melaporkan hasil **PASS**, item RVR terkait dihapus dari dokumen ini, bukan dipindahkan ke daftar PASS panjang.

Baca bersama:

```text
README.md
docs/URGENT_TRANSACTION_SPJ_MIGRATION.md
docs/SPJ_DESIGN_DECISIONS.md
docs/DEVELOPMENT_ROADMAP.md
docs/GUI_STANDARDIZATION.md
docs/CSS_USAGE_GUIDE.md
```
