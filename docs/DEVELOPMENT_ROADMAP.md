# SPJ BOSP Web — Rencana Pengembangan

Terakhir diperbarui: **2026-09-07**

Roadmap ini hanya memuat pekerjaan yang belum selesai. Item yang sudah PASS tidak lagi dipelihara sebagai milestone aktif agar fokus tetap pada gap menuju release.

---

## P0 — konsistensi workflow kritis

### 1. Samakan validasi tanggal pengadaan

Canonical:

```text
Tanggal Pesanan <= Tanggal Transaksi
Tanggal Pesanan <= Tanggal BAP
Tanggal BAP <= Tanggal BAST
```

Hilangkan pembatas tambahan BAP/BAST <= tanggal transaksi dari entry point yang tidak sesuai dan tambahkan regression test.

### 2. Samakan sumber workflow Dashboard/Transaksi/Persiapan

Dashboard Produktivitas masih memakai `->has('items')`. Workflow canonical tidak boleh menggunakan keberadaan `transaction_items` sebagai indikator pekerjaan operator.

Target: gunakan `SpjWorkflowFilterService` atau kontrak equivalent di semua entry point.

### 3. Hapus compatibility state `needs_details`

Bersihkan label/link legacy Persiapan tanpa rewrite besar view `spj/index.blade.php`.

### 4. PEMELIHARAAN end-to-end bahan + upah

UI linkage sudah ada. Berikutnya:

- transaksi upah memilih transaksi bahan/barang;
- transaksi bahan/barang memilih transaksi upah;
- generator RAB membaca kedua sisi;
- definisikan aturan agregasi item dan nilai;
- blokir hasil yang tidak dapat direkonsiliasi;
- tambah focused test generator/dokumen.

---

## P1 — kategori dan generator

### 5. JASA_LAINNYA multi-penerima end-to-end

Sudah ada model/relation, request validation, sinkronisasi, dan UI dasar. Yang masih wajib:

```text
Σ gross detail = transaction.gross_amount
Σ tax detail   = transaction.tax_total
Σ net detail   = transaction.net_amount
```

Tambahkan pajak per penerima/service line, blocker READY, dokumen per penerima, generator/template, dan focused test.

### 6. SiPLah end-to-end

Verifikasi source ownership, safe sync, Paket SPJ, numbering, template, preview/download, dan regression test. Jangan membuat kategori `SIPLAH`.

### 7. Generator PDF/Word/Excel

Hardening:

- preview bebas side effect;
- template error mudah dipahami;
- placeholder konsisten;
- output per kategori benar;
- batch package tidak mengubah lifecycle.

### 8. End-to-end semua kategori

Wajib mencakup:

```text
BARANG
KONSUMSI
PEMELIHARAAN
JASA_LAINNYA
SPPD
HONOR_PEGAWAI
```

Target akhir tiap kategori: source → DRAFT → READY → NUMBERED → FINAL tanpa edit database manual.

---

## P1 — integrity, lifecycle, authorization

### 9. Lifecycle/locking/revision

Hardening DRAFT → READY → NUMBERED → FINAL beserta cancellation/reissue/reopen yang audited dan backend-authoritative.

### 10. Reconciliation snapshot/diff

Tambahkan before/after snapshot, field-level diff yang terbaca operator, dan keputusan eksplisit untuk perubahan source setelah pekerjaan SPJ dibuat.

### 11. Authorization per role

ADMIN / OPERATOR / VIEWER harus memiliki batas mutation yang diuji untuk reset, restore, numbering, cancellation, reopen, finalization, dan konfigurasi.

### 12. Tenant operations pada APP DATA eksternal

Root data aktif menggunakan:

```env
SPJ_DATA_PATH=D:/lrvProject/spj-bosp-data
```

Database sekolah canonical:

```text
{SPJ_DATA_PATH}/school-databases/{NPSN}/spj.sqlite
```

Kode database sekolah/dummy dan backup sudah memakai root tersebut. Yang masih wajib adalah runtime/focused verification provision, migrate, reset, backup, restore, serta audit path export agar tidak ada path lama `storage/app` yang tersisa pada alur operasional.

---

## P2 — UX dan QA

### 13. Field-level validation UX

Pesan dekat field, bahasa operator, dan link tindakan bila relevan.

### 14. Mobile visual QA

Tutup `docs/MOBILE_VISUAL_QA_TODO.md`. Selama masih terbuka, status tetap RVR.

### 15. Cleanup compatibility UI

Kurangi DOM-placement/runtime guards dan legacy palette hanya saat area terkait disentuh dan regression risk rendah.

---

## P3 — Pusat Laporan

Implementasikan bertahap setelah workflow/domain stabil:

```text
Laporan Keuangan
Laporan SPJ
Laporan BOS
Laporan Pajak
Laporan per Kategori
Monitoring & Audit
```

Prioritas awal:

- BKU/rekap transaksi;
- RKAS vs realisasi;
- status workflow SPJ;
- register penomoran;
- rekap pajak;
- audit/reconciliation.

Laporan resmi K7A, K7, K8, SPTJM, K7B, K7C dan format lain baru diklaim compliant setelah template/aturan resmi yang dipakai project dikonfirmasi.

---

## Definition of Done release candidate

Release candidate belum boleh dinyatakan selesai sampai:

- seluruh FAIL di `CURRENT_PROGRESS.md` selesai atau secara eksplisit dipindah out-of-scope;
- semua kategori canonical lulus end-to-end;
- purchase-date rule satu sumber;
- preview/download bebas side effect;
- numbering/lifecycle/revision aman;
- safe sync tidak merusak overlay/final document;
- authorization sensitif diuji;
- tenant operation pada `SPJ_DATA_PATH` diuji;
- mobile QA ditutup;
- build + focused/critical tests berhasil.

Baca bersama:

```text
docs/CURRENT_PROGRESS.md
docs/SPJ_DESIGN_DECISIONS.md
docs/USER_SCENARIOS.md
docs/GUI_STANDARDIZATION.md
docs/CSS_USAGE_GUIDE.md
```
