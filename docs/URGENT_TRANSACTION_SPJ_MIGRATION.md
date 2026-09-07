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

**Status: PASS**

Route legacy `spj.prepare` dan `SpjPackageUseCase::prepare()` sudah tidak ada.

- `SpjDocumentController` (dead controller, tidak ada route) sudah dihapus.
- Stale JS selectors yang merujuk `form[action*="/spj/"][action*="/siapkan"]` sudah dihapus.
- Gateway tunggal pembuatan/open DRAFT adalah `transactions.prepare-spj` → `SpjPreparationController` → `CreateSpjDraftUseCase`.
- Test `test_legacy_routes_and_use_case_are_retired` memastikan route lama tidak ada.
- Test `test_legacy_spj_prepare_route_does_not_exist` memverifikasi POST ke path lama return 404.

### U02 — pensiunkan write-path SPJ lama di TransactionController

**Status: PASS**

Route `transactions.manual-description.update` dan method `updateManualDescription()` tidak ada di codebase.

- `TransactionController::updateSpjDescriptions()` hanya menulis `item_description`.
- Endpoint `transactions.spj-descriptions.update` adalah satu-satunya write-path item_description.
- TransactionController tidak mengubah spj_category, payment_description, payment_method, vendor, atau field SPJ lainnya.
- Test `test_transaction_controller_only_writes_item_description` memverifikasi isolasi write-path.

### U03 — partial Paket SPJ per kategori

**Status: PASS**

Struktur partial sudah terpisah dengan benar:

```text
resources/views/spj/partials/package/
├── common.blade.php
├── tax-reference.blade.php
├── items-readonly.blade.php
├── numbering.blade.php
├── validation.blade.php
├── documents.blade.php
├── transaction-summary.blade.php
├── row-editor.blade.php
└── categories/
    ├── barang.blade.php
    ├── konsumsi.blade.php
    ├── pemeliharaan.blade.php
    ├── sppd.blade.php
    ├── honor-pegawai.blade.php
    └── jasa-lainnya.blade.php
```

Semua partial kategori memiliki `data-spj-section` attribute dan di-render di DOM untuk switching tanpa reload.

### U04 — hilangkan asumsi server-render kategori yang memaksa reload

**Status: PASS**

- Duplikat `<div x-show="tab === 'paket'">` wrapper sudah diperbaiki.
- Semua 6 kategori canonical di-render di DOM dengan `data-spj-section` dan visibility via JS.
- `spj-package-manual-category.js` menghandle AJAX category switch tanpa reload.
- Tidak ada `form.submit()` pada category switch.
- Event `spj:category-changed` tersedia untuk komponen yang perlu refresh parsial.

### U05 — pajak readonly harus menjadi markup canonical

**Status: PASS**

- `tax-reference.blade.php` sudah native Blade readonly — tidak ada `<input>` field.
- `spj-package-transaction-boundary.js` sudah dibersihkan: `markTaxReference()` (JS runtime readonly) dihapus.
- Field `ppn_rate`, `pph*_rate`, `sspd_rate` tidak dikirim dari form Paket SPJ.
- Backend `UpdateSpjPackageDetailsUseCase` tidak menulis pajak transaksi.
- Test `test_tax_reference_partial_renders_readonly_display` memverifikasi tidak ada `<input>` di partial.
- Test `test_updating_spj_package_does_not_change_transaction_tax_values` memverifikasi backend immutability.

### U06 — verifikasi semua kategori tanpa input ganda

**Status: PASS**

Keenam kategori canonical terverifikasi:

- `test_each_category_can_be_selected_and_saved_in_draft` — kategori bisa dipilih dan disimpan sebagai DRAFT.
- `test_each_category_can_be_selected_saved_and_rendered_in_the_package` — kategori bisa dipilih, disimpan, dan dirender.
- `test_category_switch_is_ajax_persists_and_returns_json` — switch kategori via AJAX.
- `test_category_partials_use_data_spj_section_attribute` — semua partial memiliki `data-spj-section`.
- `test_package_update_cannot_change_tax_values` — pajak tidak bisa diubah dari Paket.
- `test_package_update_cannot_change_item_descriptions` — item_description tidak bisa diubah dari Paket.
- `test_numbered_package_blocks_description_update` — NUMBERED/FINAL terkunci.

---

## 6. Urutan implementasi URGENT

```text
U01/U02  Tutup write-path lama              ✅ PASS
   ↓
U03      Partial Paket per kategori         ✅ PASS
   ↓
U04      Audit dynamic category tanpa reload ✅ PASS
   ↓
U05      Pajak readonly native Blade        ✅ PASS
   ↓
U06      E2E enam kategori                  ✅ PASS
   ↓
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
