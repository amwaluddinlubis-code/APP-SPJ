# Penanda Template Dokumen SPJ

Terakhir diverifikasi: **2026-09-12** terhadap `app/Services/SpjTemplateService.php`, `app/Services/ArkasActivityHierarchyResolver.php`, dan `bridge/src/ARKASBridge/Program.cs` pada branch `gui-standardization`.

Dokumen ini adalah referensi placeholder canonical untuk template dokumen SPJ Word (`.docx`) dan Excel (`.xlsx`). Format placeholder memakai kurung kurawal ganda, misalnya:

```text
{{NOMOR_SPJ}}
```

Daftar pada **Pengaturan → Template Dokumen** harus memakai katalog yang sama dengan `SpjTemplateService::placeholderGroups()` agar UI, template master, dan generator tidak berbeda.

Dokumen ini menjelaskan contract placeholder, bukan status release generator. Status functional/RVR generator tetap mengikuti `CURRENT_PROGRESS.md`.

---

## 1. Aturan umum placeholder

- Placeholder scalar canonical diganti oleh `SpjTemplateService`.
- Scalar kosong dirender menggunakan nilai kosong terstandar aplikasi (`-`), bukan dibiarkan sebagai marker mentah.
- `KOP_SURAT` adalah kasus khusus: pada Excel ia diproses sebagai gambar oleh generator, bukan sebagai teks biasa.
- Placeholder repeating row `ITEM_*` dan `UPAH_*` diproses melalui jalur dinamis tersendiri.
- Preview/download tidak boleh menerbitkan nomor hanya karena placeholder nomor belum mempunyai nilai.
- Unresolved placeholder guard harus menolak artifact akhir yang masih menyisakan marker placeholder yang seharusnya sudah di-resolve.
- Source ARKAS/BKU tidak boleh dimutasi hanya agar placeholder mempunyai isi.

---

## 2. Dokumen dan periode

Placeholder canonical:

```text
NOMOR_SPJ
NOMOR_DOKUMEN
NO_BUKTI
NOMOR_BUKTI
TANGGAL_TRANSAKSI
TANGGAL_DOKUMEN
TAHUN_ANGGARAN
SUMBER_DANA
SUMBER_DANA_PERIODE
TRIWULAN
SEMESTER
JENIS_SPJ
```

Catatan:

- `NOMOR_DOKUMEN` saat ini alias ke `NOMOR_SPJ`.
- `NOMOR_BUKTI` alias ke `NO_BUKTI`.
- `TANGGAL_DOKUMEN` menggunakan tanggal transaksi pada mapping scalar saat ini.
- `SUMBER_DANA_PERIODE` dirangkai dari sumber dana, tahun anggaran, dan triwulan.

---

## 3. Sekolah dan pejabat

Placeholder canonical:

```text
NAMA_SEKOLAH
NAMA_SATUAN_PENDIDIKAN
NPSN
ALAMAT_SEKOLAH
DESA
KECAMATAN
KABUPATEN_KOTA
PROVINSI
KOP_SURAT
NAMA_KEPALA_SEKOLAH
NIP_KEPALA_SEKOLAH
NAMA_BENDAHARA_BOSP
NIP_BENDAHARA_BOSP
```

`NAMA_SATUAN_PENDIDIKAN` adalah alias dari `NAMA_SEKOLAH`.

`KOP_SURAT` tidak diisi sebagai teks biasa pada Excel; generator dapat memasukkan gambar letterhead melalui jalur khusus.

Alias lama seperti `NAMA_BENDAHARA` / `NIP_BENDAHARA` tidak boleh diasumsikan tersedia untuk template baru kecuali service memang menambahkannya kembali secara eksplisit. Template baru harus memakai nama canonical di atas.

---

## 4. Penerima dan penyedia

Placeholder canonical:

```text
NAMA_PENERIMA
NAMA_PENERIMA_BKU
NAMA_PENERIMA_KUITANSI
PENERIMA_PENYEDIA
NAMA_PENYEDIA
ALAMAT_PENYEDIA
NPWP_PENYEDIA
TELEPON_PENYEDIA
NAMA_PENANDATANGAN
JABATAN_PENANDATANGAN
SUDAH_TERIMA_DARI
```

Ownership penting:

- `NAMA_PENERIMA_BKU` berasal dari source BKU/ARKAS.
- `NAMA_PENERIMA_KUITANSI` memakai effective receipt recipient / `receipt_recipient_name` operator.
- `NAMA_PENERIMA` saat ini mengikuti effective receipt recipient.
- `PENERIMA_PENYEDIA` dan `NAMA_PENYEDIA` mengikuti vendor/effective recipient sesuai mapping service.
- `ALAMAT_PENYEDIA` saat ini terutama dapat berasal dari metadata merchant SiPLah bila tersedia.
- `TELEPON_PENYEDIA` belum mempunyai source schema aktif dan dapat dirender `-`.

Jangan menukar source recipient dan receipt recipient hanya demi menyesuaikan template.

---

## 5. Transaksi dan pembayaran

Placeholder canonical:

```text
KODE_PROGRAM
NAMA_PROGRAM
KODE_SUB_PROGRAM
NAMA_SUB_PROGRAM
KODE_KEGIATAN
NAMA_KEGIATAN
KODE_REKENING
NAMA_REKENING
URAIAN_TRANSAKSI
UNTUK_PEMBAYARAN
CARA_BAYAR
REFERENSI_BAYAR
CARA_BAYAR_REFERENSI
```

### Hirarki Program → Sub Program → Kegiatan

Contract sumber data canonical:

```text
ref_kode ARKAS
  -> ARKASBridge RKAS v3
  -> arkas_rkas_items.payload
  -> ArkasActivityHierarchyResolver
  -> SpjTemplateService
  -> DOCX/XLSX/preview/PDF
```

Makna placeholder:

- `KODE_PROGRAM` — kode parent level Program dari hirarki kode kegiatan ARKAS.
- `NAMA_PROGRAM` — `uraian_kode` parent Program dari `ref_kode` ARKAS.
- `KODE_SUB_PROGRAM` — kode parent level Sub Program dari hirarki kode kegiatan ARKAS.
- `NAMA_SUB_PROGRAM` — `uraian_kode` parent Sub Program dari `ref_kode` ARKAS.
- `KODE_KEGIATAN` — snapshot `activity_code` pada transaksi yang berasal dari RKAS.
- `NAMA_KEGIATAN` — snapshot `activity_name` pada transaksi yang berasal dari RKAS.

Aturan kebenaran data:

- Bridge tidak menebak nama Program/Sub Program. Nama hanya berasal dari row parent `ref_kode` untuk tahun anggaran yang sama.
- Kode Program/Sub Program dapat diturunkan secara deterministik dari `KODE_KEGIATAN` sebagai fallback untuk data hasil sinkronisasi lama.
- Nama Program/Sub Program pada data lama yang belum memiliki field RKAS v3 **tidak boleh ditebak**; scalar kosong akan dirender `-`.
- Agar `NAMA_PROGRAM` dan `NAMA_SUB_PROGRAM` terisi pada database sekolah yang telah disinkronkan sebelum contract RKAS v3, build ARKAS Bridge terbaru lalu lakukan sinkronisasi ulang RKAS/BKU untuk tahun anggaran tersebut.
- `KODE_KEGIATAN` dan `NAMA_KEGIATAN` tetap memakai snapshot transaksi. Penambahan hirarki tidak mengubah ownership kegiatan yang sudah berjalan.
- Parser `ArkasPipePayload` membaca header `FIELDS` secara dinamis, sehingga field RKAS v3 tersimpan di payload tanpa migrasi schema transaksi baru.

Contract transaksi lain:

- `URAIAN_TRANSAKSI` berasal dari uraian source transaksi.
- `UNTUK_PEMBAYARAN` memakai `payment_description` operator jika tersedia, lalu fallback ke uraian transaksi.
- `manual_description` bukan field canonical dan tidak boleh dihidupkan kembali.
- `CARA_BAYAR_REFERENSI` adalah kombinasi label metode pembayaran dan referensi pembayaran.

---

## 6. Pesanan dan pekerjaan

Placeholder canonical:

```text
NOMOR_PESANAN
TANGGAL_PESANAN
NOMOR_INVOICE
TANGGAL_INVOICE
STATUS_INVOICE
NOMOR_SPK
TANGGAL_SPK
NOMOR_RAB
TANGGAL_RAB
URAIAN_PEKERJAAN
LOKASI_PEKERJAAN
TANGGAL_MULAI
TANGGAL_SELESAI
TANGGAL_TANDA_TANGAN
TANGGAL_PENYERAHAN
TEMPAT_PENYERAHAN
```

### `NOMOR_PESANAN`

`NOMOR_PESANAN` adalah **Nomor Surat Pesanan SPJ internal**.

Nomor ini:

- dapat kosong pada `DRAFT` / `READY`;
- bukan blocker sebelum numbering;
- menjadi wajib setelah `NUMBERED` / `FINAL` bila internal purchase order applicable;
- tidak sama dengan nomor order marketplace SiPlah.

### `NOMOR_RAB`

`NOMOR_RAB` adalah nomor RAB pada konteks pekerjaan/pemeliharaan bila tersedia. `TANGGAL_RAB` adalah tanggal RAB dan saat ini mempunyai fallback ke tanggal transaksi bila tanggal RAB belum tersedia.

### Chronology pengadaan

Rule domain canonical tetap:

```text
TANGGAL_PESANAN <= TANGGAL_TRANSAKSI
TANGGAL_PESANAN <= tanggal BAP
BAP <= BAST
```

Template tidak boleh mengubah chronology rule backend.

---

## 7. Pembelian SiPLah

SiPLah adalah procurement/payment channel, bukan kategori SPJ.

Placeholder canonical SiPLah saat ini:

```text
SIPLAH_MARKETPLACE
SIPLAH_TRANSACTION_ID
SIPLAH_NOMOR_PESANAN
SIPLAH_PENYEDIA
SIPLAH_ALAMAT_PENYEDIA
SIPLAH_NPWP_PENYEDIA
SIPLAH_NOMOR_INVOICE
SIPLAH_TANGGAL_INVOICE
SIPLAH_TANGGAL_PEMBAYARAN
SIPLAH_REFERENSI_BAYAR
SIPLAH_STATUS_DQ
SIPLAH_STATUS_MAPPING
SIPLAH_RINCIAN_ITEM
```

Perbedaan wajib:

```text
SIPLAH_NOMOR_PESANAN = nomor marketplace/order SiPLah
NOMOR_PESANAN         = nomor Surat Pesanan SPJ internal
```

Keduanya tidak boleh saling menggantikan.

Makna ringkas:

- `SIPLAH_MARKETPLACE` — nama marketplace bila tersedia.
- `SIPLAH_TRANSACTION_ID` — identifier transaksi marketplace.
- `SIPLAH_NOMOR_PESANAN` — nomor order marketplace.
- `SIPLAH_PENYEDIA` — merchant/penyedia SiPLah.
- `SIPLAH_ALAMAT_PENYEDIA` — alamat merchant.
- `SIPLAH_NPWP_PENYEDIA` — NPWP merchant/vendor.
- `SIPLAH_NOMOR_INVOICE` — nomor invoice transaksi.
- `SIPLAH_TANGGAL_INVOICE` — tanggal invoice.
- `SIPLAH_TANGGAL_PEMBAYARAN` — tanggal/waktu pembayaran bila tersedia.
- `SIPLAH_REFERENSI_BAYAR` — referensi pembayaran.
- `SIPLAH_STATUS_DQ` — status data-quality SiPLah dalam label aplikasi.
- `SIPLAH_STATUS_MAPPING` — status pemetaan anggaran/item.
- `SIPLAH_RINCIAN_ITEM` — ringkasan item SiPLah multibaris.

Untuk BARANG SiPLah, template tidak boleh menganggap Surat Pesanan internal selalu applicable. Applicability tetap ditentukan procurement/document requirement policy aplikasi.

---

## 8. Nilai dan pajak

Placeholder canonical:

```text
NILAI_BRUTO
NILAI_PEKERJAAN
NILAI_PEKERJAAN_TERBILANG
PPN
PPH21
PPH22
PPH23
PPH4
SSPD
TOTAL_PAJAK
POTONGAN_PAJAK
NILAI_DIBAYARKAN
TERBILANG_NETO
```

Contract:

- tax/gross/net berasal dari source transaction.
- generator tidak boleh menulis ulang source tax.
- `POTONGAN_PAJAK` saat ini alias ke `TOTAL_PAJAK`.
- `NILAI_PEKERJAAN` saat ini mengikuti `NILAI_BRUTO`.
- `TERBILANG_NETO` dan `NILAI_PEKERJAAN_TERBILANG` menghasilkan teks terbilang bahasa Indonesia.
- formatting rupiah dilakukan oleh service generator.

---

## 9. Ringkasan multibaris

Placeholder canonical:

```text
RINCIAN_BELANJA
RINCIAN_UPAH
RINCIAN_JASA
```

- `RINCIAN_BELANJA` — ringkasan item transaksi.
- `RINCIAN_UPAH` — ringkasan pekerja/upah.
- `RINCIAN_JASA` — ringkasan penerima jasa, termasuk quantity/unit/duration/value yang tersedia.

Placeholder ini menghasilkan teks multibaris. Untuk tabel dokumen yang membutuhkan struktur baris/kolom nyata, gunakan repeating-row placeholder yang didukung.

---

## 10. Repeating row barang

Placeholder:

```text
ITEM_NO
ITEM_URAIAN
ITEM_VOLUME
ITEM_SATUAN
ITEM_HARGA_SATUAN
ITEM_JUMLAH
ITEM_KODE_REKENING
ITEM_NAMA_REKENING
```

Baris template yang memuat `ITEM_NO` menjadi template row yang digandakan mengikuti jumlah item.

`ITEM_URAIAN` harus memakai effective item description sesuai mapping generator, bukan memaksa perubahan pada source description.

---

## 11. Repeating row upah/honor

Placeholder:

```text
UPAH_NO
UPAH_NAMA
UPAH_PEKERJAAN
UPAH_HARI
UPAH_TARIF_HARI
UPAH_JUMLAH
UPAH_PENERIMA_KUITANSI
```

Baris template yang memuat `UPAH_NO` menjadi template row yang digandakan mengikuti pekerja/penerima yang didukung generator.

Repeating row ini tidak otomatis berarti seluruh multi-recipient JASA_LAINNYA menggunakan schema `UPAH_*`. Untuk JASA gunakan placeholder/context yang memang disediakan generator atau template khusus yang sudah diverifikasi.

---

## 12. Scalar kosong dan unresolved marker

Runtime generator menormalisasi scalar kosong menjadi:

```text
-
```

Pengecualian utama:

```text
KOP_SURAT
```

karena `KOP_SURAT` dapat ditangani sebagai gambar.

Implikasi:

- template resmi tidak boleh mengandalkan marker mentah tetap terlihat untuk menandai field kosong;
- field yang memang belum mempunyai source akan tampil `-` setelah rendering;
- unresolved placeholder yang seharusnya dikenal generator harus dianggap error, bukan output final yang valid.

---

## 13. DOCX, XLSX, preview, dan PDF

Generator saat ini mendukung pengisian template:

```text
DOCX
XLSX
```

Untuk XLSX, generator juga mendukung preview HTML dan export PDF melalui pipeline spreadsheet.

Contract preview XLSX:

- source preview adalah **workbook Excel aktif yang sama** yang di-load dan diisi oleh `SpjTemplateService`; tidak ada template HTML terpisah sebagai sumber dokumen;
- untuk source multi-sheet, worksheet yang dirender ditentukan dari `document_type` → `SpjDocumentTypeRegistry` → nama sheet canonical;
- preview tidak boleh menganggap worksheet index `0` / sheet pertama sebagai dokumen yang benar;
- bila sheet canonical tidak ditemukan, fallback hanya diperbolehkan jika workbook mempunyai tepat satu worksheet non-teknis;
- sheet teknis registry seperti `PLACEHOLDER_MAP` tidak boleh dipakai sebagai fallback preview;
- source multi-sheet yang ambigu harus gagal daripada menampilkan worksheet yang salah;
- package preview menerapkan resolver worksheet canonical yang sama pada setiap template XLSX.

Catatan penting:

- unduh PDF langsung saat ini berasal dari template Excel/XLSX;
- jangan mengasumsikan template DOCX otomatis mempunyai jalur PDF yang sama;
- package XLSX/PDF dapat menggabungkan beberapa template sesuai document package flow;
- HTML preview adalah representasi workbook melalui renderer PhpSpreadsheet, bukan bukti pixel-perfect terhadap Microsoft Excel;
- visual fidelity, print area, page break, header/footer, ukuran kertas, dan hasil buka di Microsoft Office/PDF viewer tetap harus diverifikasi dengan template resmi/aktual.

---

## 14. Template lifecycle dan upload

Contract lifecycle template:

- upload invalid tidak boleh mengganti template aktif;
- replacement file + database harus atomic dari perspektif aplikasi;
- file lama baru boleh dihapus setelah replacement database berhasil;
- template lifecycle memakai disk `local` yang sama dengan generator;
- upload package dan single template mempunyai mode/error bag terpisah;
- limit upload efektif tetap dipengaruhi PHP `upload_max_filesize` dan `post_max_size`.

Detail status functional upload template berada di `CURRENT_PROGRESS.md`.

---

## 15. Ownership data untuk template

Template adalah consumer dari context dokumen; template tidak menjadi alasan untuk mengubah ownership data.

Contoh:

```text
KODE/NAMA_PROGRAM         -> parent ref_kode ARKAS melalui payload RKAS tersinkron
KODE/NAMA_SUB_PROGRAM     -> parent ref_kode ARKAS melalui payload RKAS tersinkron
KODE/NAMA_KEGIATAN        -> snapshot kegiatan pada transaction
NAMA_PENERIMA_BKU         -> source BKU/ARKAS
NAMA_PENERIMA_KUITANSI    -> overlay/effective receipt recipient
PPN/PPH/SSPD              -> source transaction
UNTUK_PEMBAYARAN          -> operator payment description dengan fallback source
ITEM_URAIAN               -> effective item description
SIPLAH_*                  -> canonical SiPLah context bila tersedia
```

Jika template membutuhkan data yang belum tersedia, solusi yang benar adalah memperjelas contract/source atau menambah capability generator secara eksplisit — bukan mengisi source/operator data secara fiktif.

---

## 16. Lokasi dokumen pada UI Paket

Pada Paket SPJ, daftar **Dokumen & Template** berada di sub-tab **Rincian** sebagai panel terpisah dari Rincian Transaksi.

Perubahan posisi UI tidak mengubah:

- ownership data;
- placeholder catalog;
- document requirement policy;
- numbering;
- lifecycle generator.

---

## 17. Checklist template resmi

Sebelum template dianggap siap dipakai pada release:

1. gunakan placeholder canonical dari katalog runtime;
2. tidak ada marker unresolved setelah generate;
3. nomor internal dan nomor marketplace SiPLah tidak tertukar;
4. source recipient dan receipt recipient tidak tertukar;
5. repeating row tidak merusak format tabel;
6. nilai/tanggal memakai formatter yang benar;
7. preview/download tidak menerbitkan nomor;
8. preview XLSX merender worksheet canonical milik `document_type`, bukan sekadar sheet pertama workbook;
9. DOCX/XLSX hasil generate dapat dibuka;
10. XLSX → PDF dapat dirender bila jalur tersebut digunakan;
11. print area, page break, header/footer, orientation, paper size, dan visual fidelity diverifikasi terhadap template resmi;
12. output memakai real/canonical context, bukan data fiktif untuk memaksa coverage;
13. bila template memakai `NAMA_PROGRAM`/`NAMA_SUB_PROGRAM`, pastikan database sekolah sudah disinkronkan ulang dengan ARKAS Bridge RKAS v3.
