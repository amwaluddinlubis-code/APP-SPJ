# SiPLah MVP — Status dan Rencana Implementasi

Status: **PARTIAL / IN PROGRESS**

Terakhir diperbarui: **2026-09-07**

Branch target:

```text
gui-standardization
```

Dokumen ini tidak lagi menggambarkan SiPLah sebagai fitur yang belum disentuh. Dukungan dasar sudah ada; pekerjaan berikutnya adalah memastikan ownership data, dokumen, safe sync, dan end-to-end flow konsisten.

---

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

SiPLah adalah karakteristik/metode/channel pembelian atau pembayaran.

Contoh:

```text
payment_method = siplah
spj_category = BARANG
```

atau:

```text
payment_method = siplah
spj_category = KONSUMSI
```

---

## 2. Dukungan yang sudah tersedia

Codebase saat ini sudah memiliki dukungan berikut:

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

Template placeholder juga sudah menyediakan identifier SiPLah:

```text
SIPLAH_NOMOR_PESANAN
SIPLAH_PENYEDIA
SIPLAH_NOMOR_INVOICE
SIPLAH_TANGGAL_INVOICE
SIPLAH_REFERENSI_BAYAR
```

`SpjDocumentRequirementService` dan procurement policy sudah membedakan kebutuhan SiPLah dan Non-SiPLah.

---

## 3. Surat Pesanan internal dan transaksi SiPLah

Keputusan canonical:

> **Transaksi SiPLah tidak memakai Surat Pesanan internal aplikasi.**

Untuk transaksi dengan:

```text
payment_method = siplah
```

maka:

- tidak ada kewajiban isi Surat Pesanan internal;
- tidak ada kewajiban Nomor Surat Pesanan internal;
- Surat Pesanan internal tidak menjadi blocker DRAFT/READY/NUMBERED/FINAL;
- aplikasi tidak perlu menerbitkan nomor `order_number` untuk kebutuhan SiPLah;
- bukti pengadaan menggunakan data marketplace/source yang relevan seperti `siplah_order_number`, invoice, dan `payment_reference`.

Nomor marketplace SiPLah tetap disimpan terpisah:

```text
siplah_order_number = nomor pesanan/order marketplace SiPLah
```

`order_number` hanya digunakan untuk Surat Pesanan internal pada pengadaan **Non-SiPLah** bila aturan transaksi tersebut memang memerlukannya.

Untuk Non-SiPLah + kategori barang/konsumsi, Surat Pesanan internal tetap mengikuti rule content + numbering aplikasi.

---

## 4. Ownership field

### Source ARKAS/BKU

Field source tetap readonly dan boleh berubah saat sync sesuai safe sync.

### Operator/manual

Field operator harus dipertahankan dan tidak boleh ditimpa hanya karena source sync berubah.

Saat menambah dukungan SiPLah baru, jangan membuat field duplikat jika field generic existing sudah memiliki makna yang sama.

---

## 5. UI saat ini

### Detail Transaksi

Jika `payment_method === 'siplah'`, block data SiPLah dapat menampilkan/menyimpan penyedia, nomor pesanan marketplace, invoice, dan referensi pembayaran sesuai field existing.

Surat Pesanan internal tidak perlu ditampilkan sebagai kewajiban transaksi SiPLah.

### Paket SPJ

Package tetap mengikuti kategori SPJ normal. SiPLah tidak membuat tab/lifecycle/numbering baru.

Template/dokumen tetap berada di sub-tab Rincian bersama panel Dokumen & Template.

---

## 6. Dokumen

Dokumen ditentukan oleh kategori SPJ dan lifecycle package.

SiPLah boleh memengaruhi data yang dicetak, tetapi tidak otomatis membuat domain nomor baru dan tidak memakai domain nomor Surat Pesanan internal.

Aturan tetap:

```text
preview/download != numbering
```

---

## 7. Sinkronisasi

Wajib:

- source ARKAS/BKU tetap readonly;
- safe sync tidak menimpa operator field;
- source SiPLah yang berubah dapat memicu reconciliation;
- package/manual data tidak dihapus saat source berubah/hilang.

---

## 8. Gap yang masih harus diverifikasi

1. ownership setiap field SiPLah source vs operator;
2. apakah semua placeholder mengambil field canonical yang benar;
3. apakah preview/download Word/Excel/PDF konsisten;
4. apakah safe sync mempertahankan manual SiPLah fields;
5. apakah package Barang/Konsumsi SiPLah lolos requirement tanpa Surat Pesanan internal;
6. apakah browser flow source → transaction → package → document bebas side effect numbering.

---

## 9. Di luar scope MVP

Belum dikerjakan:

- API eksternal SiPLah;
- SSO SiPLah;
- scraping portal;
- sync marketplace langsung;
- kategori `SIPLAH`;
- numbering system khusus SiPLah;
- lifecycle khusus SiPLah.

---

## 10. Testing minimum

```text
1. payment_method=siplah dapat disimpan tanpa mengubah spj_category
2. manual SiPLah fields tidak ditimpa safe sync
3. package dibuat berdasarkan kategori SPJ normal
4. preview/download tidak menyebabkan numbering
5. placeholder SiPLah mengambil source yang benar
6. SiPLah tidak memiliki requirement Surat Pesanan internal
7. Non-SiPLah tetap mengikuti Surat Pesanan internal sesuai policy
```

---

## 11. Definition of Done MVP

MVP dianggap stabil jika:

- transaksi SiPLah dikenali;
- kategori SPJ tetap canonical;
- field minimum tersedia tanpa duplikasi tidak perlu;
- operator dapat melihat/mengisi field yang memang menjadi tanggung jawabnya;
- safe sync aman;
- requirement policy benar;
- tidak ada Surat Pesanan internal pada workflow SiPLah;
- dokumen menggunakan field SiPLah yang tepat;
- numbering/lifecycle existing tetap aman;
- focused tests dan browser flow lulus.

---

## 12. Next action

Bukan lagi “audit apakah ada dukungan SiPLah”. Dukungan sudah ada.

Next action yang benar:

> Verifikasi ownership field dan end-to-end output dokumen SiPLah, lalu jalankan focused tests untuk safe sync, requirement policy, placeholder, dan browser flow.
