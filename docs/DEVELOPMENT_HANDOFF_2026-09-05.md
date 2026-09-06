# SPJ BOSP Web — Development Handoff 2026-09-05

> **Status: HISTORICAL / SUPERSEDED**
>
> Dokumen ini dipertahankan sebagai jejak handoff tanggal 2026-09-05, tetapi **tidak lagi menjadi sumber kondisi project terkini**. Untuk pekerjaan setelah 2026-09-06, gunakan `docs/CURRENT_PROGRESS.md` sebagai snapshot aktif.

Terakhir ditinjau ulang: **2026-09-06**.

---

## 1. Mengapa dokumen ini disupersede

Sejak handoff 2026-09-05, branch `gui-standardization` telah mengalami perubahan penting pada domain SPJ dan UI Paket, sehingga checkpoint dan next action lama tidak lagi akurat.

Perubahan setelah handoff ini mencakup:

- pemisahan validasi Surat Pesanan internal menjadi content requirement vs number requirement;
- perbaikan/penambahan validasi kronologi tanggal pengadaan;
- field tanggal pengadaan dimasukkan ke update flow Paket → Isian Manual;
- auto-fill peserta `KONSUMSI` di Detail Transaksi diubah menjadi Dapodik-only;
- dark form controls dinormalisasi;
- theme Paket/Isian Manual diperbaiki;
- quarter card hover `/spj/penomoran` dibuat theme-aware;
- daftar Dokumen & Template dibuat compact;
- Dokumen & Template dipindahkan ke sub-tab Rincian;
- Rincian Transaksi dan Dokumen & Template dipisah menjadi dua panel dengan header theme-aware;
- `docs/CSS_USAGE_GUIDE.md` dibuat sebagai contract CSS aktif.

Karena itu, pernyataan lama seperti “Phase 4.3E belum dikerjakan” atau “next action utama adalah audit SiPLah sebelum perubahan source” **tidak lagi boleh digunakan sebagai instruksi kerja saat ini**.

---

## 2. Kondisi checkpoint aktif

Checkpoint aktif bukan lagi commit `7d02661` yang tercatat pada handoff lama.

Gunakan HEAD branch `gui-standardization` dan `CURRENT_PROGRESS.md` untuk menentukan kondisi aktual.

Dokumen ini tidak mencoba mengunci SHA baru karena perubahan aktif terus berjalan; SHA lokal/user harus diperiksa saat sesi dimulai.

---

## 3. SiPLah — koreksi status

Handoff lama menyebut SiPLah MVP sebagai pekerjaan yang belum diaudit. Kondisi sekarang lebih maju:

Sudah tersedia pada codebase:

```text
payment_method = siplah
siplah_order_number
vendor_name / vendor_owner / vendor_npwp
invoice_number / invoice_date / invoice_status
payment_reference
placeholder template SiPLah
procurement/document requirement policy SiPLah vs Non-SiPLah
```

Status yang benar sekarang adalah **partial/in progress**, bukan “belum mulai”.

SiPLah tetap **bukan kategori SPJ**.

---

## 4. GUI — koreksi status

Handoff lama menyatakan internal package tabs belum dikerjakan. Itu sudah tidak benar.

Sub-tab Paket yang aktif:

```text
Rincian
Isian Manual
Penomoran
```

Tab Rincian sekarang memiliki:

```text
Panel Rincian Transaksi
Panel Dokumen & Template
```

Dokumen & Template dipindahkan ke Rincian dan menggunakan compact list. Isian Manual/theme/spacing dan numbering hover juga sudah mendapat compatibility/theme fixes.

---

## 5. Domain rule terbaru yang wajib diketahui

### Surat Pesanan internal

- content/substansi blocking sebelum READY;
- nomor tidak blocking pada preparation;
- nomor wajib pada NUMBERED/FINAL;
- nomor diterbitkan aplikasi saat numbering.

### Kronologi tanggal

Canonical rule Paket:

```text
order_date <= transaction_date
order_date <= bap_date
bap_date <= bast_date
```

Known mismatch: Detail Transaksi masih memiliki upper bound tambahan BAP/BAST <= transaction date di controller.

### Konsumsi

`fillTeachers()` pada Detail Transaksi memakai Employee Dapodik-only.

---

## 6. Verification status

Jangan membawa klaim PASS lama sebagai bukti kondisi HEAD sekarang.

Setiap perubahan harus diverifikasi ulang sesuai scope:

```text
npm run build                     # frontend
php artisan test --compact ...    # backend focused test
php artisan view:cache            # bila relevan
```

Mobile visual QA tetap TODO/RVR sampai `MOBILE_VISUAL_QA_TODO.md` ditutup.

---

## 7. Next action yang berlaku sekarang

Gunakan urutan dari `DEVELOPMENT_ROADMAP.md`. Prioritas terdekat:

1. samakan purchase-date rules antara Detail Transaksi dan Paket;
2. regression test Surat Pesanan/numbering;
3. stabilkan generator/preview;
4. hardening lifecycle/locking/numbering/reconciliation;
5. end-to-end test semua kategori;
6. mobile QA dan release hardening.

---

## 8. Dokumen aktif yang harus dibaca

```text
README.md
docs/CURRENT_PROGRESS.md
docs/ARCHITECTURE_COMPLETE.md
docs/SPJ_DESIGN_DECISIONS.md
docs/USER_SCENARIOS.md
docs/GUI_STANDARDIZATION.md
docs/CSS_USAGE_GUIDE.md
docs/DEVELOPMENT_ROADMAP.md
docs/SIPLAH_MVP_PLAN.md
docs/MOBILE_VISUAL_QA_TODO.md
```

Dokumen ini hanya untuk history handoff 2026-09-05 dan **tidak boleh mengalahkan dokumen aktif di atas**.
