# SiPLah MVP — Status dan Rencana Implementasi

Status: **PARTIAL / IN PROGRESS**

Terakhir diperbarui: **2026-09-08**

Branch target: `gui-standardization`.

SiPLah bukan kategori SPJ. Dukungan dasar sudah ada; pekerjaan tersisa adalah end-to-end output dokumen, safe sync, dan browser flow.

## 1. Prinsip domain

Kategori SPJ tetap:

```text
BARANG
KONSUMSI
PEMELIHARAAN
SPPD
HONOR_PEGAWAI
JASA_LAINNYA
```

SiPLah adalah mode/channel pembelian atau pembayaran.

Contoh canonical UI saat ini:

```text
spj_category = BARANG
payment_method = siplah
```

## 2. Dukungan yang tersedia

```text
payment_method = siplah
siplah_order_number
vendor_name
vendor_owner
vendor_npwp
invoice_number
invoice_date
invoice_status
payment_reference
receipt_recipient_name
transaction items
```

Placeholder SiPLah mencakup identifier seperti:

```text
SIPLAH_NOMOR_PESANAN
SIPLAH_PENYEDIA
SIPLAH_NOMOR_INVOICE
SIPLAH_TANGGAL_INVOICE
SIPLAH_REFERENSI_BAYAR
```

Requirement service/procurement policy membedakan SiPLah dan Non-SiPLah.

## 3. Surat Pesanan internal vs SiPLah

Transaksi SiPLah tidak memakai Surat Pesanan internal aplikasi sebagai kewajiban workflow SiPLah.

Untuk `payment_method = siplah`:

- nomor marketplace memakai `siplah_order_number`;
- invoice/reference/vendor mengikuti field SiPLah yang relevan;
- nomor Surat Pesanan internal aplikasi bukan nomor marketplace;
- preview/download tetap tidak boleh mengalokasikan nomor diam-diam.

Untuk Non-SiPLah, Surat Pesanan internal tetap mengikuti policy content + numbering aplikasi bila applicable.

## 4. Ownership field

### Source ARKAS/BKU

Readonly dan mengikuti safe sync.

### Operator/manual

Field vendor/invoice/reference yang menjadi tanggung jawab operator dipertahankan sebagai overlay dan tidak boleh ditimpa source sync tanpa rule eksplisit.

Field SiPLah tidak boleh diedit dari Detail Transaksi. Mutation dokumen dilakukan di Paket SPJ.

## 5. UI saat ini

### Detail Transaksi

Tidak menjadi tempat input SiPLah. Detail Transaksi hanya menampilkan source/context dan `item_description` editable.

### Paket SPJ — BARANG

Baris kategori:

```text
Kategori SPJ | ○ SiPLah  ○ Non SiPLah
```

Kedua radio adalah satu group dan mutually-exclusive. Pemilihan disinkronkan dengan `payment_method`.

Jika source menandai transaksi SiPLah secara authoritative, Non SiPLah dapat disabled.

Radio SiPLah/Non SiPLah eksplisit saat ini hanya ditampilkan pada kategori BARANG sesuai desain workspace terbaru.

### Data Umum

`payment_method` tetap field canonical backend. Radio context bukan field domain baru; ia hanya kontrol UI yang menyinkronkan field canonical.

## 6. Dokumen dan numbering

Dokumen tetap ditentukan oleh kategori SPJ dan lifecycle package.

SiPLah tidak membuat tab/lifecycle/numbering baru.

Nomor internal otomatis tidak menjadi input manual pada Isian Manual; informasi nomor ditampilkan dalam strip readonly di bawah Kategori SPJ.

Aturan tetap:

```text
preview/download != numbering
```

## 7. Sinkronisasi

Wajib:

- source ARKAS/BKU readonly;
- safe sync tidak menimpa operator field;
- perubahan source SiPLah dapat memicu reconciliation;
- package/manual data tidak dihapus saat source berubah/hilang.

## 8. Gap yang masih harus diverifikasi

1. ownership setiap field SiPLah source vs operator pada dataset nyata;
2. semua placeholder mengambil field canonical yang benar;
3. preview/download Word/Excel/PDF konsisten;
4. safe sync mempertahankan manual SiPLah fields;
5. package BARANG SiPLah lolos requirement tanpa Surat Pesanan internal;
6. browser flow source → transaction → package → document bebas side effect numbering;
7. radio SiPLah/Non SiPLah dan field `payment_method` selalu sinkron setelah category switch/reload.

## 9. Di luar scope MVP

- API eksternal SiPLah;
- SSO SiPLah;
- scraping portal;
- sync marketplace langsung;
- kategori `SIPLAH`;
- numbering system khusus SiPLah;
- lifecycle khusus SiPLah.

## 10. Testing minimum

```text
1. payment_method=siplah dapat disimpan tanpa mengubah spj_category
2. radio SiPLah/Non SiPLah mutually-exclusive
3. source SiPLah authoritative tidak dapat dibalik oleh UI biasa jika dikunci
4. manual SiPLah fields tidak ditimpa safe sync
5. package dibuat berdasarkan kategori SPJ normal
6. preview/download tidak menyebabkan numbering
7. placeholder SiPLah mengambil field yang benar
8. SiPLah tidak memiliki requirement Surat Pesanan internal yang salah
9. Non-SiPLah tetap mengikuti policy Surat Pesanan internal
```

## 11. Definition of Done MVP

MVP dianggap stabil jika:

- transaksi SiPLah dikenali;
- kategori SPJ tetap canonical;
- field minimum tersedia tanpa duplikasi;
- operator hanya mengisi field pada Paket SPJ;
- radio UI sinkron dengan `payment_method`;
- safe sync aman;
- requirement policy benar;
- dokumen menggunakan field SiPLah yang tepat;
- numbering/lifecycle existing tetap aman;
- focused tests dan browser flow lulus.

## 12. Next action

Verifikasi end-to-end output dokumen SiPLah dan safe sync, lalu tutup browser QA untuk radio/category switching pada workspace Paket terbaru.
