# SPJ BOSP Web — Rencana Pengembangan

Terakhir diperbarui: **2026-09-08**

Roadmap ini hanya memuat pekerjaan yang masih belum selesai. Item yang sudah PASS tidak dipelihara sebagai milestone aktif.

---

## P0 URGENT — migrasi Detail Transaksi ↔ Paket SPJ

**Ini prioritas pertama sebelum pekerjaan P1/P2/P3.**

Rencana lengkap:

```text
docs/URGENT_TRANSACTION_SPJ_MIGRATION.md
```

Target final:

```text
Detail Transaksi = source transaksi + item_description
Paket SPJ        = seluruh data dokumen pertanggungjawaban
```

Urutan kerja:

1. pensiunkan route/use-case prepare legacy yang masih menerima payload SPJ dari transaksi;
2. pensiunkan `TransactionController::updateManualDescription()` sebagai write-path SPJ lama setelah seluruh pemanggil aktif dipastikan tidak ada;
3. pertahankan endpoint Detail Transaksi hanya untuk `item_description` dan data transaksi yang memang dimiliki Detail Transaksi;
4. pecah Paket SPJ menjadi partial per kategori di bawah `resources/views/spj/...`;
5. audit semua perubahan kategori agar tidak ada full page reload atau asumsi server-render yang stale;
6. ubah pajak readonly menjadi markup Blade canonical, bukan compatibility normalizer;
7. verifikasi BARANG, KONSUMSI, PEMELIHARAAN, JASA_LAINNYA, SPPD, HONOR_PEGAWAI end-to-end tanpa input ganda.

Definition of Done P0:

```text
Detail Transaksi
  - item_description editable & wajib tersimpan
  - quantity/unit/harga/nilai readonly
  - PPN/PPh/SSPD readonly source

Paket SPJ
  - satu-satunya workspace kategori/payment/vendor/data kategori
  - pajak source tidak dapat diubah
  - category switch tanpa reload
  - save -> validation -> READY -> numbering -> preview/download berjalan
```

Selama compatibility path lama masih dapat menulis data SPJ dari transaksi, P0 belum PASS.

---

## P0 — tutup verification queue terbaru

Setelah perubahan P0 URGENT, verifikasi source terbaru untuk:

```text
ownership Detail Transaksi vs Paket SPJ
validasi item_description sebelum buka Paket
category switch AJAX tanpa reload
pajak Paket readonly dan immutable
kronologi tanggal pengadaan
workflow Dashboard/Transaksi/Persiapan
PEMELIHARAAN bahan + upah pada preview/download
APP DATA eksternal
SiPLah MVP
rekonsiliasi gross/tax/net JASA_LAINNYA
```

Daftar perintah canonical berada di `docs/CURRENT_PROGRESS.md`.

---

## P1 — JASA_LAINNYA multi-penerima end-to-end

Fondasi aktif sudah mencakup penerima jamak, quantity × hari × tarif, gross detail, tax/net per penerima, alokasi rounding-safe, dan blocker rekonsiliasi gross/tax/net.

Yang harus diselesaikan:

1. tampilkan gross/tax/net setiap penerima pada output template;
2. dukung kuitansi/dokumen per penerima bila template mensyaratkan;
3. tambah test preview/download dan lifecycle sampai FINAL.

Rule agregat tetap:

```text
Σ gross = transaction.gross_amount
Σ tax   = transaction.tax_total
Σ net   = transaction.net_amount
```

Jangan membuat kategori baru seperti `SEWA_LAPTOP` atau `SEWA_MOBIL`; gunakan subtype di bawah `JASA_LAINNYA`.

---

## P1 — generator dan lifecycle release hardening

### Generator dokumen

Verifikasi seluruh template aktif untuk keenam kategori:

- Word/Excel/PDF;
- package preview/export;
- unresolved-placeholder guard;
- identitas sekolah/vendor/penerima/pajak/nomor;
- preview/download bebas side effect;
- error template terbaca operator.

### Lifecycle / authorization / reconciliation

Tutup suite terpadu untuk:

```text
DRAFT -> READY -> NUMBERED -> FINAL
```

beserta cancellation/reissue/reopen, backend locking, role ADMIN/OPERATOR/VIEWER, reconciliation snapshot/diff, dan perlindungan final document saat sync.

---

## P1 — end-to-end semua kategori

Wajib membuktikan:

```text
BARANG
KONSUMSI
PEMELIHARAAN
JASA_LAINNYA
SPPD
HONOR_PEGAWAI
```

melalui alur:

```text
source
-> Detail Transaksi
-> simpan item_description
-> create/open DRAFT
-> lengkapi Paket SPJ
-> READY
-> NUMBERED
-> FINAL
-> preview/download
```

tanpa edit database manual dan tanpa input field SPJ yang sama pada dua halaman.

Untuk `PEMELIHARAAN`, RAB/dokumen harus membaca material dari transaksi bahan dan pekerja/upah dari transaksi upah yang ditautkan.

---

## P2 — UX dan QA

- tutup `docs/MOBILE_VISUAL_QA_TODO.md`;
- tambah field-level validation UX pada area yang masih generik;
- kurangi runtime compatibility layer hanya ketika refactor markup aman;
- pertahankan theme token dan primitive `x-ui.*` / `ui-*`;
- pertahankan pergantian kategori Paket SPJ tanpa full page reload.

---

## P3 — Pusat Laporan

Implementasi bertahap setelah workflow dan generator stabil:

```text
Laporan Keuangan
Laporan SPJ
Laporan BOS
Laporan Pajak
Laporan per Kategori
Monitoring & Audit
```

Prioritas awal:

- BKU / rekap transaksi;
- RKAS vs realisasi;
- status workflow SPJ;
- register penomoran;
- rekap pajak;
- audit/reconciliation.

K7A, K7, K8, SPTJM, K7B, K7C dan format resmi lain baru boleh disebut compliant setelah template/aturan resmi yang dipakai project dikonfirmasi.

---

## Definition of Done release candidate

Release candidate belum selesai sampai:

- migrasi URGENT Detail Transaksi ↔ Paket SPJ dinyatakan PASS;
- seluruh item FAIL di `CURRENT_PROGRESS.md` selesai atau dinyatakan out-of-scope secara eksplisit;
- seluruh RVR penting mendapat hasil runtime PASS;
- keenam kategori lulus end-to-end;
- preview/download bebas side effect;
- numbering/lifecycle/revision aman;
- safe sync tidak merusak overlay/final document;
- authorization sensitif diuji;
- tenant operation pada `SPJ_DATA_PATH` diuji;
- mobile QA ditutup;
- build dan critical tests berhasil.

Baca bersama:

```text
docs/URGENT_TRANSACTION_SPJ_MIGRATION.md
docs/CURRENT_PROGRESS.md
docs/SPJ_DESIGN_DECISIONS.md
docs/USER_SCENARIOS.md
docs/GUI_STANDARDIZATION.md
docs/CSS_USAGE_GUIDE.md
```
