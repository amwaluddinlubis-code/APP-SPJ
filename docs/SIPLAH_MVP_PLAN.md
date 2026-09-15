# SiPLah — Status Core dan Verifikasi Output

Status: **FUNCTIONAL CORE PASS / GENERATED-DOCUMENT E2E + OFFICIAL-TEMPLATE OUTPUT RVR**

Terakhir diperbarui: **2026-09-16**

> Nama file ini dipertahankan untuk kompatibilitas dokumentasi lama. Isi dokumen tidak lagi menggambarkan SiPLah sebagai MVP yang baru dibangun.

SiPLah adalah **procurement/payment channel**, bukan kategori SPJ. Core policy, persistence, requirement mapping, dan placeholder SiPLah sudah mempunyai regression deterministic. Pekerjaan aktif sekarang adalah generated-document E2E, official-template QA, real-data verification, dan browser flow.

## 1. Prinsip domain

Kategori SPJ tetap:

```text
BARANG
KONSUMSI
PEMELIHARAAN
JASA_LAINNYA
SPPD
HONOR_PEGAWAI
```

Contoh canonical:

```text
spj_category   = BARANG
payment_method = siplah
is_siplah      = true
```

Tidak ada kategori `SIPLAH`, lifecycle khusus SiPLah, atau numbering khusus SiPLah.

## 2. Core yang sudah FUNCTIONAL PASS

Regression aktif membuktikan:

- metadata SiPLah dapat disimpan tanpa mengubah kategori canonical;
- `payment_method=siplah` dan source flag dapat dikenali oleh procurement policy;
- Paket SiPLah tetap memakai lifecycle Paket SPJ normal;
- nomor pesanan marketplace `siplah_order_number` berbeda dari nomor Surat Pesanan internal;
- placeholder SiPLah memiliki mapping tersendiri;
- BARANG SiPLah tidak dipaksa memenuhi internal purchase-order requirement yang tidak applicable;
- incomplete metadata SiPLah tidak menambahkan READY blocker yang tidak mempunyai dasar aturan;
- policy dokumen marketplace mempunyai regression khusus;
- pemetaan template Paket berdasarkan `is_siplah` mempunyai regression khusus;
- preview/download tetap tidak boleh menerbitkan nomor secara diam-diam.

Bukti regression utama:

```text
tests/Feature/SiplahPurchaseMvpTest.php
tests/Feature/SiplahMarketplaceDocumentPolicyTest.php
tests/Feature/DocumentTemplateSiplahMappingTest.php
```

Ketiganya merupakan bagian dari source/test yang tercakup pada functional gate branch aktif.

## 3. Field dan ownership

Field utama yang digunakan antara lain:

```text
payment_method
is_siplah
siplah_order_number
vendor_name
vendor_owner
vendor_npwp
invoice_number
invoice_date
invoice_status
payment_reference
receipt_recipient_name
siplah_marketplace
siplah_transaction_id
siplah_merchant_address
siplah_dq_passed
```

Ownership tetap mengikuti kontrak aplikasi:

- ARKAS/BKU = source readonly;
- operator/manual field = overlay;
- Detail Transaksi tidak menjadi workspace input SiPLah;
- mutation kategori/payment/vendor/procurement dilakukan di Paket SPJ;
- safe sync tidak boleh menimpa overlay manual tanpa rule eksplisit.

## 4. Surat Pesanan internal vs marketplace

Untuk transaksi SiPLah:

- nomor marketplace menggunakan `siplah_order_number`;
- invoice/payment reference/vendor menggunakan field SiPLah yang relevan;
- nomor Surat Pesanan internal bukan nomor marketplace;
- internal purchase-order requirement yang tidak applicable tidak boleh menjadi blocker.

Pemetaan generated-document pada Paket SPJ tetap dimulai dari kategori canonical `BARANG`, lalu difilter oleh flag persisted `is_siplah` transaksi dan mapping `document_templates.is_siplah`:

- `document_templates.is_siplah = NULL` berarti template berlaku pada SiPLah dan Non-SiPLah;
- `document_templates.is_siplah = true` berarti template hanya berlaku pada Paket SiPLah;
- `document_templates.is_siplah = false` berarti template hanya berlaku pada Paket Non-SiPLah;
- template tetap harus aktif dan mapping kategorinya harus cocok;
- `SPJ_COVER`, `SPJ_CHECKLIST`, `KUITANSI_A2`, dan dokumen lain dapat tetap shared dengan nilai `NULL`;
- `SURAT_PESANAN`, `BAP`, dan `BAST` memiliki default `is_siplah=false`, sehingga tidak masuk Paket ketika transaksi mempunyai `is_siplah=true`;
- pemetaan Paket tidak menginfer nilai `is_siplah` dari `payment_method`; sinkronisasi/normalisasi field tetap menjadi tanggung jawab boundary yang memiliki data tersebut.

Untuk Non-SiPLah, Surat Pesanan internal, BA Pemeriksaan, dan BAST tetap mengikuti mapping kategori dan document requirement aplikasi bila applicable.

Import workbook master memberi default Non-SiPlah pada `SURAT_PESANAN`, `BAP`, dan `BAST` yang baru dibuat. Jika import mengganti template yang sudah ada, mapping `is_siplah` yang telah diatur operator dipertahankan.

## 5. Placeholder SiPLah

Placeholder aktif mencakup identifier seperti:

```text
SIPLAH_NOMOR_PESANAN
SIPLAH_PENYEDIA
SIPLAH_NOMOR_INVOICE
SIPLAH_TANGGAL_INVOICE
SIPLAH_REFERENSI_BAYAR
SIPLAH_MARKETPLACE
SIPLAH_TRANSACTION_ID
SIPLAH_ALAMAT_PENYEDIA
SIPLAH_NPWP_PENYEDIA
SIPLAH_STATUS_DQ
SIPLAH_STATUS_MAPPING
```

`NOMOR_PESANAN` internal dan `SIPLAH_NOMOR_PESANAN` marketplace tidak boleh dipertukarkan.

## 6. UI aktif

### Detail Transaksi

Hanya source/context + `item_description` editable. Tidak ada editor SiPLah di sini.

### Paket SPJ

Pada BARANG, kontrol konteks dapat menampilkan:

```text
Kategori SPJ | ○ SiPLah  ○ Non SiPLah
```

Kontrol tersebut bukan domain field baru. Kedua radio adalah satu group dan mutually-exclusive, hanya ditampilkan pada kategori BARANG, dan bersifat UI-only: hanya show/hide section pengadaan (SiPLah vs internal), nilainya tidak disimpan dan tidak pernah menulis `payment_method`. Status awal radio diturunkan dari `payment_method`/`is_siplah`. Bila source authoritative mengunci transaksi sebagai SiPLah, UI biasa tidak boleh membaliknya tanpa rule yang sah; Non SiPLah dapat disabled.

SiPLah dilarang di luar BARANG: KONSUMSI, PEMELIHARAAN, JASA_LAINNYA, SPPD, dan HONOR_PEGAWAI tidak mengenal channel SiPLah; `payment_method = siplah` pada kategori tersebut adalah data tidak valid.

### Pengaturan → Template Dokumen

Setiap template mempunyai pemetaan **Channel Paket SPJ** yang dapat diatur saat upload/ganti satu template maupun pada tabel **Template yang Tersedia**:

```text
Semua channel   -> document_templates.is_siplah = NULL
SiPlah saja     -> document_templates.is_siplah = true
Non-SiPlah saja -> document_templates.is_siplah = false
```

Pilihan ini berdampingan dengan mapping kategori SPJ dan status aktif. Selector Paket menggunakan kombinasi:

```text
is_active
+ applicable_categories
+ document_templates.is_siplah
+ transaction.is_siplah
```

Dengan demikian pemetaan SiPlah bukan lagi hard-code di selector dan dapat dikelola dari halaman pengaturan template tanpa membuat kategori `SIPLAH` baru.

## 7. Pekerjaan yang masih RVR / aktif

Pekerjaan tersisa bukan membangun ulang core SiPLah, tetapi membuktikan output dan runtime nyata:

- [ ] generated-document E2E dengan transaksi SiPLah nyata/representatif;
- [ ] official-template Word/Excel/PDF mengambil field SiPLah yang benar;
- [ ] print area/page break/header/footer/tabel dinamis pada template resmi;
- [ ] browser flow source → transaksi → Paket → output tanpa side effect numbering;
- [ ] reload/category switching/payment-method consistency, termasuk radio SiPLah/Non SiPLah yang tetap UI-only dan tidak menulis `payment_method`;
- [ ] verifikasi ownership field source vs operator pada data sekolah nyata;
- [ ] safe sync mempertahankan overlay SiPLah manual sesuai kontrak.

## 8. Di luar scope aktif

- API eksternal SiPLah;
- SSO SiPLah;
- scraping portal;
- marketplace sync langsung;
- kategori `SIPLAH`;
- numbering/lifecycle khusus SiPLah.

## 9. Definition of Done untuk penutupan P1 SiPLah

SiPLah dapat dianggap selesai untuk target release saat:

- core policy tetap regression green;
- real/representative SiPLah package menghasilkan dokumen yang benar;
- placeholder dan requirement official template tervalidasi;
- browser flow tidak menghasilkan mutation/numbering tak terduga;
- safe sync tidak merusak overlay;
- hasil output lolos visual/runtime verification pada viewer target.

Status release final tetap mengikuti `CURRENT_PROGRESS.md` dan `DEVELOPMENT_ROADMAP.md`.
