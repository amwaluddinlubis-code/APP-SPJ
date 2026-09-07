# SPJ BOSP Web — Rencana Pengembangan

Terakhir diperbarui: **2026-09-07**

Roadmap ini hanya memuat pekerjaan yang masih belum selesai setelah perbaikan workflow, kronologi pengadaan, linkage pemeliharaan, dan APP DATA. Item yang sudah PASS tidak dipelihara sebagai milestone aktif.

---

## P0 — tutup verification queue terbaru

Sebelum menambah fitur besar, verifikasi source terbaru untuk:

```text
kronologi tanggal pengadaan
workflow Dashboard/Transaksi/Persiapan
normalisasi state Persiapan
PEMELIHARAAN bahan + upah pada preview/download
APP DATA eksternal
SiPLah MVP
```

Daftar perintah canonical berada di `docs/CURRENT_PROGRESS.md`.

---

## P1 — JASA_LAINNYA multi-penerima end-to-end

Fondasi aktif sudah mencakup penerima jamak, quantity × hari × tarif, gross detail, tax/net per penerima, dan alokasi rounding-safe.

Yang harus diselesaikan:

1. tampilkan gross/tax/net setiap penerima pada output template;
2. validasi row legacy yang belum memiliki hasil rekonsiliasi tax/net;
3. dukung kuitansi/dokumen per penerima bila template mensyaratkan;
4. tambah test preview/download dan lifecycle sampai FINAL.

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
source -> Detail Transaksi -> DRAFT -> READY -> NUMBERED -> FINAL -> preview/download
```

tanpa edit database manual.

Untuk `PEMELIHARAAN`, RAB/dokumen harus membaca material dari transaksi bahan dan pekerja/upah dari transaksi upah yang ditautkan.

---

## P2 — UX dan QA

- tutup `docs/MOBILE_VISUAL_QA_TODO.md`;
- tambah field-level validation UX pada area yang masih generik;
- kurangi runtime compatibility layer hanya ketika refactor markup aman;
- pertahankan theme token dan primitive `x-ui.*` / `ui-*`.

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
docs/CURRENT_PROGRESS.md
docs/SPJ_DESIGN_DECISIONS.md
docs/USER_SCENARIOS.md
docs/GUI_STANDARDIZATION.md
docs/CSS_USAGE_GUIDE.md
```
