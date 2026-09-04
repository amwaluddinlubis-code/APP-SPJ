# SiPLah MVP — Rencana Implementasi

Status: PLANNED

Branch target:

```text
gui-standardization
```

Dokumen ini mendefinisikan batas MVP pembelian SiPLah agar implementasi tidak melebar menjadi redesign domain atau integrasi eksternal sebelum kebutuhan dasar operator stabil.

## 1. Prinsip domain

SiPLah **bukan kategori SPJ**.

Kategori SPJ tetap:

```text
BARANG
KONSUMSI
PEMELIHARAAN
SPPD
HONOR_PEGAWAI
JASA_LAINNYA
```

SiPLah diperlakukan sebagai karakteristik/metode pembelian atau pembayaran transaksi.

Contoh konsep canonical:

```text
payment_method = siplah
spj_category = BARANG
```

atau:

```text
payment_method = siplah
spj_category = KONSUMSI
```

Kategori dokumen tetap mengikuti sifat belanja, bukan kanal pembeliannya.

## 2. Tujuan MVP

Operator dapat menandai dan mengelola transaksi pembelian SiPLah tanpa membuat kategori SPJ baru dan tanpa menggandakan data yang sudah tersedia dari ARKAS/BKU.

MVP harus memungkinkan aplikasi:

- mengenali transaksi SiPLah;
- menampilkan identitas pembelian SiPLah yang relevan;
- mempertahankan kategori SPJ normal;
- memakai data rekanan/pesanan/referensi pembayaran existing bila tersedia;
- membawa informasi tersebut ke workspace SPJ dan dokumen yang relevan;
- menjaga seluruh lifecycle penomoran/finalisasi tetap sama.

## 3. Audit wajib sebelum migration

Sebelum membuat migration baru, audit minimal:

### Transaction/model

Cari dukungan existing untuk:

```text
payment_method
payment_reference
vendor_name
vendor_owner
vendor_npwp
order_number
order_date
bap_number
bap_date
bast_number
bast_date
receipt_recipient_name
recipient_name
no_bukti
```

### Source ARKAS/BKU

Tentukan field mana yang berasal dari source dan harus readonly.

### Operator/manual

Tentukan field mana yang boleh diedit operator dan harus dipertahankan saat sinkronisasi.

### Package/document

Audit penggunaan field tersebut pada:

```text
SpjPackageUseCase
SpjDocumentUseCase
template placeholders
PDF/Word/Excel generator
SPJ workspace
transaction detail workspace
```

## 4. Field tambahan — hanya bila benar-benar diperlukan

Jangan otomatis menambah semua field berikut. Tambahkan hanya jika audit membuktikan belum ada padanan existing.

Kandidat kebutuhan SiPLah:

```text
siplah_order_number
siplah_invoice_number
siplah_transaction_date
siplah_provider_name
siplah_payment_reference
```

Preferensi desain:

- pakai field generic existing jika maknanya sama;
- jangan membuat duplikat `vendor_name` menjadi `siplah_vendor_name` tanpa kebutuhan nyata;
- jangan membuat duplikat `order_number` bila nomor pesanan SiPLah dapat menggunakan field tersebut;
- source field dan manual/operator field harus jelas ownership-nya.

## 5. UI MVP

### Transaction detail

Jika `payment_method === 'siplah'`, tampilkan blok/field SiPLah yang relevan.

Gunakan primitive `x-ui.*` existing.

Jangan membuat Page Header atau design system baru.

### SPJ package

Tampilkan penanda bahwa pembelian melalui SiPLah tanpa mengganti kategori SPJ.

Contoh informasi yang dapat ditampilkan bila tersedia:

```text
Metode Pembelian: SiPLah
Penyedia
Nomor Pesanan
Nomor Invoice
Referensi Pembayaran
Tanggal Pembelian
```

## 6. Dokumen

Dokumen tetap ditentukan oleh kategori SPJ dan lifecycle package.

SiPLah dapat memengaruhi data yang dicetak, tetapi tidak membuat domain nomor baru secara otomatis.

Jangan mengubah prinsip:

```text
preview/download != numbering
```

Jangan membuat nomor dokumen baru hanya karena transaksi adalah SiPLah.

## 7. Sinkronisasi

Aturan wajib:

- data ARKAS/BKU tetap readonly source;
- sinkronisasi tidak boleh menimpa field operator;
- jika source SiPLah berubah, gunakan mekanisme rekonsiliasi existing bila relevan;
- jangan menghapus paket/manual operator ketika source berubah/hilang.

## 8. Di luar scope MVP

Tidak dikerjakan pada tahap awal:

- integrasi API SiPLah eksternal;
- login/SSO SiPLah;
- scraping portal SiPLah;
- sinkronisasi langsung marketplace;
- pembuatan kategori `SIPLAH`;
- redesign numbering;
- redesign document lifecycle;
- refactor besar Phase 4 UI bersamaan dengan implementasi SiPLah.

## 9. Testing strategy

Gunakan focused tests.

Minimum yang disarankan setelah implementasi:

```text
1. payment_method=siplah dapat disimpan tanpa mengubah spj_category
2. data SiPLah operator tidak ditimpa safe sync
3. transaksi SiPLah tetap dapat menyiapkan package berdasarkan kategori SPJ
4. preview tidak menyebabkan numbering
5. field SiPLah yang dicetak mengambil sumber data yang benar
```

Jangan menjalankan full suite pada setiap perubahan kecil.

Sebelum release candidate, test kritis tetap harus dijalankan lebih luas.

## 10. Definition of Done MVP

SiPLah MVP dianggap selesai jika:

- transaksi dapat dikenali sebagai SiPLah;
- kategori SPJ tetap normal dan valid;
- field minimum yang diperlukan tersedia tanpa duplikasi tidak perlu;
- operator dapat melihat/mengisi data SiPLah yang menjadi tanggung jawabnya;
- data source tidak tertimpa;
- package SPJ tetap menggunakan lifecycle existing;
- dokumen relevan dapat memakai data SiPLah;
- focused tests lulus;
- browser flow transaksi → package dapat diverifikasi tanpa mutation penomoran yang tidak disengaja.

## 11. Langkah pertama

Jangan langsung coding migration.

Langkah pertama:

> Audit codebase untuk seluruh dukungan SiPLah yang sudah ada, petakan field ownership dan penggunaan dokumen, lalu buat daftar gap minimum sebelum implementasi.
