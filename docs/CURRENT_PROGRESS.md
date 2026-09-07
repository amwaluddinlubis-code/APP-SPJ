# SPJ BOSP Web — Current Progress / Open Issues

Terakhir diperbarui: **2026-09-07**

Dokumen ini adalah register kondisi aktif branch `gui-standardization`. Item yang sudah selesai/PASS tidak lagi dipelihara sebagai daftar progres di sini; fondasi yang sudah stabil tetap dijelaskan di README, arsitektur, dan keputusan desain. Dokumen ini fokus pada **FAIL / belum tuntas / RVR** agar tidak menyesatkan.

---

## 1. Kondisi aktif

- Laravel 12 / PHP 8.2, Livewire + Alpine + Tailwind.
- Multi-database: database utama + SQLite tenant/sekolah.
- ARKAS/BKU adalah source readonly; data operator SPJ adalah overlay terpisah.
- `SpjController` tetap tipis dan orkestrasi utama berada di use case SPJ.
- Kategori canonical: `BARANG`, `KONSUMSI`, `PEMELIHARAAN`, `JASA_LAINNYA`, `SPPD`, `HONOR_PEGAWAI`.
- SiPLah bukan kategori; gunakan `payment_method = siplah`.
- Root data aplikasi memakai `SPJ_DATA_PATH`. Target development Windows saat ini: `D:/lrvProject/spj-bosp-data`.
- Database sekolah berada di `{SPJ_DATA_PATH}/school-databases/{NPSN}/spj.sqlite`; dummy berada di `{SPJ_DATA_PATH}/school-databases/_unselected.sqlite`.
- Backup sekolah diarahkan ke `{SPJ_DATA_PATH}/backups/{NPSN}`.

---

## 2. FAIL / belum tuntas yang masih aktif

### F01 — Validasi tanggal pengadaan belum konsisten

**Status: FAIL**

Rule canonical Paket SPJ:

```text
order_date <= transaction_date
order_date <= bap_date
bap_date <= bast_date
```

`SpjPackageUseCase` mengikuti rule tersebut, tetapi `TransactionController::updateManualDescription()` masih memaksa `bap_date <= transaction_date` dan `bast_date <= transaction_date`. Kedua entry point belum setara.

Target: satu rule backend + focused regression test.

### F02 — Dashboard Produktivitas masih memakai keberadaan item sebagai syarat workflow

**Status: FAIL / inkonsisten konsep**

`ProductivityDashboardController` masih membangun `$cleanTransactions` dengan `->has('items')`. Ini bertentangan dengan kontrak workflow Transaksi/Persiapan bahwa `transaction_items` berasal dari source dan bukan indikator apakah operator sudah mulai mengerjakan SPJ.

Target: dashboard memakai kontrak `SpjWorkflowFilterService` atau rule equivalent tanpa menjadikan item sebagai status pekerjaan.

### F03 — Markup Persiapan masih menyimpan state legacy `needs_details`

**Status: FAIL / technical debt UI**

Backend sudah memakai state canonical, tetapi view Persiapan legacy masih memiliki label/link `needs_details`/“Rincian belum ada” sebagai compatibility path.

Target: hapus state visual legacy tanpa rewrite besar `resources/views/spj/index.blade.php`.

### F04 — PEMELIHARAAN: link bahan/upah belum masuk generator RAB

**Status: FAIL / fitur belum end-to-end**

Detail Transaksi sudah memiliki linkage transaksi terkait pemeliharaan dengan field:

```text
maintenance_material_transaction_id
maintenance_labor_transaction_id
```

UI hanya meminta **transaksi lawan** dari transaksi yang sedang dibuka: transaksi upah memilih transaksi bahan/barang; transaksi bahan/barang memilih transaksi upah. Label kandidat memakai `NOMOR BUKTI - PAYMENT DESCRIPTION`.

Yang belum selesai: generator RAB/dokumen pemeliharaan belum menggunakan transaksi terkait tersebut sebagai sumber otomatis bahan + upah. Aturan rekonsiliasi nilai dan cara membawa item ke RAB juga belum difinalkan.

### F05 — JASA_LAINNYA multi-penerima baru partial

**Status: FAIL / partial implementation**

Sudah aktif: relation `serviceRecipients`, validasi request, sinkronisasi detail penerima, UI baris penerima, dan kalkulasi dasar `quantity × rental_days × daily_rate`.

Belum tuntas:

- rekonsiliasi wajib gross/tax/net ke transaksi source;
- pajak per penerima/service line;
- blocker READY bila total tidak sesuai;
- dokumen per penerima;
- generator/template output multi-penerima;
- focused end-to-end test.

Bug kategori tersembunyi pada browser telah diperbaiki dengan men-disable control kategori nonaktif; itu tidak lagi dimasukkan sebagai FAIL aktif.

### F06 — SiPLah belum terverifikasi end-to-end

**Status: FAIL / partial**

Field/policy dasar tersedia, tetapi belum ada bukti lengkap source → Detail Transaksi → Paket → numbering → preview/download untuk skenario SiPLah. Safe-sync ownership dan output template masih perlu regression test.

### F07 — Generator dokumen belum release-hardened

**Status: FAIL / RVR**

Preview/download foundation tersedia, tetapi stabilitas PDF/Word/Excel per kategori, konsistensi placeholder, error handling template, dan side-effect-free preview belum dibuktikan sebagai release checkpoint menyeluruh.

### F08 — Lifecycle, authorization, reconciliation belum release-hardened

**Status: FAIL / belum selesai**

Masih perlu verifikasi dan test menyeluruh untuk:

- cancellation/reissue/reopen;
- locking backend NUMBERED/FINAL;
- authorization ADMIN/OPERATOR/VIEWER pada mutation sensitif;
- snapshot/diff rekonsiliasi ARKAS;
- final document tidak berubah karena sync.

### F09 — End-to-end semua kategori belum lengkap

**Status: FAIL / RVR**

Belum ada checkpoint terpadu yang membuktikan keenam kategori canonical berjalan source → FINAL dengan dokumen yang benar.

### F10 — Mobile visual QA belum ditutup

**Status: FAIL / RVR**

`docs/MOBILE_VISUAL_QA_TODO.md` masih terbuka. Jangan menyebut aplikasi mobile-complete sebelum checklist tersebut ditutup.

### F11 — Pusat Laporan dan laporan BOS belum tersedia sebagai fitur lengkap

**Status: FAIL / planned**

Pusat Laporan, K7/K7A/K8/SPTJM/K7B/K7C, laporan pajak, kategori, monitoring, dan audit masih roadmap. Format resmi harus dikonfirmasi sebelum klaim compliance.

### F12 — Operasi tenant perlu runtime verification pada APP DATA eksternal

**Status: RVR**

Konfigurasi database sekolah, dummy, dan backup sudah menggunakan `SPJ_DATA_PATH`. Yang masih perlu dibuktikan melalui runtime/focused test adalah provision, migrate, reset, backup, restore, serta seluruh path export yang relevan terhadap struktur eksternal:

```text
{SPJ_DATA_PATH}/
├── school-databases/
├── backups/
└── exports/
```

Jangan menyebut operasi tenant eksternal release-ready sebelum verifikasi tersebut selesai.

---

## 3. Urutan penyelesaian FAIL

```text
1. F01 validasi tanggal pengadaan
2. F02 dashboard workflow source
3. F03 cleanup state legacy Persiapan
4. F04 PEMELIHARAAN → RAB bahan + upah
5. F05 JASA_LAINNYA multi-penerima end-to-end
6. F06 SiPLah end-to-end
7. F07 generator dokumen
8. F08 lifecycle/authorization/reconciliation
9. F09 end-to-end seluruh kategori
10. F12 tenant operations pada SPJ_DATA_PATH
11. F10 mobile QA
12. F11 Pusat Laporan / laporan BOS
```

---

## 4. Aturan status dokumentasi

Gunakan hanya:

- **FAIL** — perilaku diketahui belum benar/inkonsisten atau fitur yang diminta belum end-to-end.
- **RVR** — perlu runtime/visual verification; belum boleh disebut PASS.
- **PLANNED** — belum diimplementasikan.

Item yang sudah PASS tidak dipertahankan dalam register ini. Jika sebuah FAIL selesai dan telah diverifikasi, hapus dari daftar ini dan sinkronkan README/roadmap/desain bila relevan.

Baca bersama:

```text
README.md
docs/SPJ_DESIGN_DECISIONS.md
docs/DEVELOPMENT_ROADMAP.md
docs/GUI_STANDARDIZATION.md
docs/CSS_USAGE_GUIDE.md
```
