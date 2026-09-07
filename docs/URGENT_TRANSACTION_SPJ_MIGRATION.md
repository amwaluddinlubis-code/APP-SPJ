# URGENT — Migrasi Detail Transaksi ↔ Paket SPJ

Terakhir diperbarui: **2026-09-08**

> **STATUS: URGENT**
>
> Migrasi ini adalah prioritas arsitektur/UX tertinggi sampai batas tanggung jawab **Detail Transaksi** dan **Paket SPJ** benar-benar tunggal, tidak ada input ganda, dan tidak ada write-path lama yang dapat mengubah data SPJ dari halaman transaksi.

Dokumen ini adalah rencana kerja khusus migrasi. Keputusan bisnis permanen tetap berada di `docs/SPJ_DESIGN_DECISIONS.md`; gap aktif diringkas di `docs/CURRENT_PROGRESS.md`.

---

## 1. Tujuan akhir

Prinsip final:

```text
Detail Transaksi = fakta transaksi/source + koreksi nama item SPJ
Paket SPJ        = seluruh data dokumen pertanggungjawaban
```

Operator tidak boleh lagi bolak-balik karena field yang sama tersedia di dua halaman.

### Detail Transaksi menjawab

> Apa yang terjadi di ARKAS/BKU dan apa rincian transaksi yang menjadi sumber dokumen?

### Paket SPJ menjawab

> Bagaimana transaksi tersebut dipertanggungjawabkan dalam paket dokumen SPJ?

---

## 2. Ownership data final

### 2.1 Detail Transaksi

Tetap berada di Detail Transaksi:

- nomor bukti/source key;
- tanggal transaksi;
- uraian source ARKAS/BKU;
- kegiatan dan rekening;
- penerima source;
- gross/bruto;
- pajak source;
- netto;
- rincian item source;
- `item_description` sebagai satu-satunya field item yang boleh dikoreksi operator;
- status source/reconciliation;
- status Paket SPJ;
- aksi `Siapkan Paket SPJ` / `Lihat Paket SPJ`.

Pada rincian item:

```text
description       = readonly, uraian source
item_description  = editable, nama/uraian item untuk dokumen SPJ
quantity          = readonly
unit              = readonly
unit_price        = readonly
amount            = readonly
```

`item_description` **harus tersimpan** sebelum Paket SPJ dapat dibuat/dibuka. Perubahan yang baru diketik tetapi belum disimpan juga harus memblokir navigasi ke Paket SPJ.

### 2.2 Pajak transaksi

Pajak tetap dibedakan dan dimiliki transaksi/source:

```text
PPN
PPh 21
PPh 22
PPh 23
PPh 4(2)
SSPD / Pajak Daerah
Total Pajak
Nilai Netto
```

Paket SPJ hanya membaca nilai tersebut sebagai referensi readonly. Paket SPJ tidak boleh menghitung ulang, menulis ulang, atau mengubah PPN/PPh/SSPD source.

### 2.3 Paket SPJ

Seluruh data dokumen berikut hanya diedit di Paket SPJ:

- `spj_category`;
- `payment_description`;
- `payment_method`;
- `payment_reference`;
- `receipt_recipient_name`;
- vendor/rekanan;
- data SiPLah/invoice;
- tanggal/dokumen pengadaan;
- data konsumsi dan peserta;
- data pemeliharaan dan pekerja;
- data SPPD/pelaksana perjalanan;
- data honor/penerima;
- data JASA_LAINNYA/penerima jasa;
- penomoran;
- preview/generate/download/finalisasi.

Rincian Paket SPJ hanya menampilkan `item_description`, quantity, unit, harga, dan nilai secara readonly dari transaksi.

---

## 3. Alur operator target

```text
Daftar Transaksi
      ↓
Detail Transaksi
      ├── lihat source ARKAS/BKU
      ├── lihat PPN / PPh / SSPD
      ├── koreksi item_description
      ├── Simpan Uraian Barang/Jasa
      └── Siapkan / Lihat Paket SPJ
                      ↓
                 Paket SPJ
                      ├── pilih kategori
                      ├── isi data umum dokumen
                      ├── isi data kategori
                      ├── validasi
                      ├── READY
                      ├── penomoran
                      ├── preview/download
                      └── FINAL
```

Tidak boleh ada form kategori SPJ di Detail Transaksi.

---

## 4. Implementasi yang sudah masuk source

Status implementasi saat dokumen ini dibuat:

### Detail Transaksi

- `resources/views/transactions/show.blade.php` sudah menjadi shell detail transaksi;
- builder dan partial kategori SPJ lama di bawah modul transaksi sudah dipensiunkan;
- `item_description` tetap editable di bagian `#rincian-transaksi`;
- tombol Paket SPJ melewati gateway `transactions.prepare-spj`;
- gateway memvalidasi seluruh `item_description` sudah tersimpan;
- UI mendeteksi perubahan uraian yang belum disimpan dan memblokir navigasi Paket SPJ;
- PPN, rincian PPh, SSPD, total pajak, dan netto ditampilkan terpisah.

### Paket SPJ

- draft dibuat/dibuka melalui `CreateSpjDraftUseCase`;
- penyimpanan isian Paket menggunakan `UpdateSpjPackageDetailsUseCase`;
- write-path Paket tidak menulis ulang field pajak source;
- field tarif pajak lama di UI ditandai readonly sebagai referensi BKU;
- validator mengarahkan masalah milik Paket ke workspace Paket SPJ dan masalah rincian transaksi ke Detail Transaksi;
- perubahan Combo Kategori SPJ disimpan via AJAX tanpa full page reload;
- bila persist kategori gagal, UI kembali ke kategori sebelumnya.

---

## 5. Sisa migrasi — wajib ditutup sebelum migrasi dinyatakan selesai

### U01 — pensiunkan compatibility write-path lama

Masih ada route legacy:

```text
POST /spj/{transactionId}/siapkan
route: spj.prepare
```

Route tersebut masih menuju `SpjController::prepare()` → `SpjPackageUseCase::prepare()`.

`SpjPackageUseCase::prepare()` adalah arsitektur lama karena menerima dan menyimpan form SPJ saat menyiapkan package. Setelah flow baru stabil, route/use-case ini harus:

1. dipastikan tidak lagi dipakai view/test/flow aktif;
2. diganti redirect/adapter aman bila masih dibutuhkan sementara;
3. kemudian dihapus dari route aktif dan use case lama dirampingkan/dipensiunkan.

### U02 — pensiunkan write-path SPJ lama di TransactionController

Route legacy berikut masih terdaftar:

```text
PUT /transaksi/{transactionId}/uraian-manual
route: transactions.manual-description.update
```

Method `TransactionController::updateManualDescription()` masih memvalidasi dan menulis banyak field SPJ. Ini bertentangan dengan ownership final jika masih dapat dipanggil.

Target:

- pertahankan hanya endpoint khusus `item_description` di Detail Transaksi;
- hentikan mutation kategori/payment/vendor/detail kategori dari `TransactionController`;
- hapus route/method compatibility setelah seluruh pemanggil aktif dipastikan tidak ada.

### U03 — partial Paket SPJ per kategori

Markup Paket SPJ masih terlalu besar dan perlu dipisah menjadi ownership view yang jelas:

```text
resources/views/spj/partials/package/
├── common.blade.php
├── tax-reference.blade.php
├── items-readonly.blade.php
└── categories/
    ├── barang.blade.php
    ├── konsumsi.blade.php
    ├── pemeliharaan.blade.php
    ├── sppd.blade.php
    ├── honor-pegawai.blade.php
    └── jasa-lainnya.blade.php
```

Semua partial kategori harus tetap berada di workspace SPJ, bukan kembali ke `transactions/partials/spj`.

### U04 — hilangkan asumsi server-render kategori yang memaksa reload

Pergantian kategori sudah AJAX, tetapi semua perilaku kategori harus diaudit agar tidak ada logic lain yang masih mengandalkan full page reload untuk:

- label kategori;
- template/dokumen applicable;
- validasi checklist;
- section SiPLah;
- section honor/pemeliharaan/SPPD/konsumsi;
- tombol READY/penomoran.

Jika komponen di luar form perlu refresh setelah kategori berubah, gunakan event `spj:category-changed` atau refresh parsial yang terkontrol; jangan kembali ke full page reload sebagai default.

### U05 — pajak readonly harus menjadi markup canonical

Readonly pajak saat ini masih dibantu normalizer JavaScript. Target akhir:

- markup Blade langsung menampilkan PPN/PPh/SSPD sebagai readonly display;
- tidak lagi mengirim `ppn_rate`, `pph*_rate`, `sspd_rate` dari Paket SPJ;
- hapus compatibility logic pajak lama setelah tidak diperlukan;
- regression test memastikan Paket SPJ tidak dapat mengubah `ppn`, `pph*`, `sspd`, `tax_total`, `net_amount`.

### U06 — verifikasi semua kategori tanpa input ganda

Wajib diuji satu per satu:

```text
BARANG
KONSUMSI
PEMELIHARAAN
JASA_LAINNYA
SPPD
HONOR_PEGAWAI
```

Untuk setiap kategori, pastikan:

- `item_description` hanya diedit di Detail Transaksi;
- data kategori hanya diedit di Paket SPJ;
- tidak ada field yang harus diisi ulang pada dua halaman;
- kategori berubah tanpa reload;
- save, validation, READY, numbering, preview/download tetap benar.

---

## 6. Urutan implementasi URGENT

```text
U01/U02  Tutup write-path lama
   ↓
U03      Partial Paket per kategori
   ↓
U04      Audit dynamic category tanpa reload
   ↓
U05      Pajak readonly native Blade
   ↓
U06      E2E enam kategori
   ↓
Migrasi dinyatakan PASS
```

Selama U01/U02 belum selesai, migrasi belum boleh disebut final meskipun UX utama sudah mengikuti arsitektur baru.

---

## 7. Guardrail

Selama migrasi:

- jangan memindahkan `item_description` ke Paket SPJ;
- jangan membuka edit quantity/unit/harga/amount di Detail Transaksi maupun Paket SPJ;
- jangan menggabungkan PPN/PPh/SSPD menjadi satu field input;
- jangan membuat pajak editable di Paket SPJ;
- jangan membuat kategori baru untuk SiPLah;
- jangan menghapus data source/overlay saat memindahkan ownership view;
- jangan mengubah lifecycle/numbering hanya demi refactor UI;
- jangan mengandalkan JavaScript sebagai satu-satunya enforcement untuk aturan backend penting.

---

## 8. Definition of Done migrasi

Migrasi Detail Transaksi ↔ Paket SPJ baru dinyatakan **PASS** jika seluruh kondisi berikut terpenuhi:

1. Detail Transaksi tidak mempunyai mutation SPJ selain `item_description` dan aksi membuka/menyiapkan package.
2. `item_description` wajib tersimpan sebelum Paket dapat dibuka/dibuat.
3. Paket SPJ adalah satu-satunya workspace mutation kategori/payment/vendor/data kategori.
4. PPN/PPh/SSPD tidak dapat diubah melalui Paket SPJ, termasuk request manual.
5. Route/use-case compatibility lama yang menulis SPJ dari transaksi sudah dipensiunkan.
6. Pergantian kategori tidak menyebabkan full page reload.
7. Keenam kategori lulus save → validation → READY → numbering → preview/download tanpa input ganda.
8. Regression tests dan runtime QA terkait migration lulus.

---

## 9. Verification minimum

```powershell
php vendor/bin/pint --dirty --format agent
npm run theme:qa
npm run build
php artisan view:cache --no-interaction
git diff --check
php artisan test --compact --filter=SpjPackage
php artisan test --compact --filter=Transaction
```

Tambahkan focused test untuk setiap compatibility path yang dipensiunkan sebelum menghapus route lama.
