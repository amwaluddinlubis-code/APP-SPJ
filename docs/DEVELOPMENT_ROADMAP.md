# SPJ BOSP Web — Rencana Pengembangan

Terakhir diperbarui: **2026-09-08**

Roadmap ini hanya memuat pekerjaan yang masih belum selesai. Migrasi ownership Detail Transaksi ↔ Paket SPJ sudah PASS dan tidak lagi menjadi milestone aktif; detail historisnya ada di `docs/URGENT_TRANSACTION_SPJ_MIGRATION.md`.

## P1 — browser QA refinement Paket SPJ terbaru

Tutup QA visual/runtime untuk perubahan terakhir:

- radio `SiPLah / Non SiPLah` mutually-exclusive;
- selector PEMELIHARAAN di baris Kategori SPJ;
- Data Umum Dokumen: textarea 5 baris kiri, seluruh field umum kanan;
- summary 5 kolom `Periode | Penerima | Bruto | Pajak | Nilai Dibayarkan`;
- tab ke-3 `Rincian Pajak`;
- informasi nomor otomatis horizontal, bukan input;
- tabel kategori non-BARANG compact dan hanya satu pagination;
- previous/next Package sesuai konteks tahun+sumber dana;
- mobile/responsive minimum.

Perubahan ini tidak boleh membuka kembali write-path legacy atau mengubah ownership backend.

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

Untuk `PEMELIHARAAN`, RAB/dokumen harus membaca material dari transaksi bahan dan pekerja/upah dari transaksi upah yang ditautkan tanpa menimpa source BKU.

---

## P1 — APP DATA runtime hardening

Validasi pada database sekolah nyata:

```text
provision
switch tenant
reset total + sqlite_sequence
backup
restore
WAL/SHM cleanup
```

Database utama tidak boleh ikut terhapus pada reset tenant.

---

## P2 — UX dan QA

- tutup `docs/MOBILE_VISUAL_QA_TODO.md`;
- tambah field-level validation UX pada area yang masih generik;
- kurangi compatibility layer hanya jika markup canonical sudah stabil;
- pertahankan theme token dan primitive `x-ui.*` / `ui-*`;
- pertahankan category switch Paket tanpa full page reload;
- standardisasi icon action baru ke `x-ui.icon` ketika markup native dirapikan.

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

- seluruh item FAIL penting di `CURRENT_PROGRESS.md` selesai atau out-of-scope secara eksplisit;
- seluruh RVR penting mendapat hasil runtime PASS;
- keenam kategori lulus end-to-end sampai FINAL + preview/download;
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
docs/ARCHITECTURE_COMPLETE.md
docs/GUI_STANDARDIZATION.md
docs/URGENT_TRANSACTION_SPJ_MIGRATION.md
```
