# SPJ BOSP Web — Keputusan Desain & Aturan Bisnis

Terakhir diperbarui: **2026-09-07**

Dokumen ini adalah sumber keputusan bisnis permanen. Status implementasi/gap aktif ada di `docs/CURRENT_PROGRESS.md`.

---

## 1. Prinsip utama

1. ARKAS/BKU adalah source readonly, bukan data manual operator.
2. Data operator SPJ disimpan terpisah dan dipertahankan saat sync ulang.
3. Sekolah, tahun anggaran, dan sumber dana aktif adalah boundary operasi tenant.
4. Preview/download tidak boleh menerbitkan nomor diam-diam.
5. NUMBERED/FINAL terkunci; koreksi harus melalui lifecycle yang sah.
6. Nomor mengikuti domain dokumen dan urutan tanggal/peristiwa, bukan urutan input.
7. `manual_description` tidak digunakan.
8. `recipient_name` adalah source; `receipt_recipient_name` adalah overlay operator.
9. UI tidak boleh melemahkan aturan backend.

---

## 2. Multi-database dan APP DATA

Database utama menyimpan user, sekolah, konfigurasi tenant, metadata global, dan referensi pengelolaan database sekolah.

Database tenant menyimpan RKAS/BKU hasil sinkronisasi, transaksi, detail SPJ, package, numbering, audit, dan data kerja sekolah.

Root data aplikasi dikonfigurasi melalui:

```env
SPJ_DATA_PATH=D:/lrvProject/spj-bosp-data
```

Database sekolah canonical:

```text
{SPJ_DATA_PATH}/school-databases/{NPSN}/spj.sqlite
```

Dummy tenant:

```text
{SPJ_DATA_PATH}/school-databases/_unselected.sqlite
```

Reset tenant hanya boleh merebuild tenant, termasuk WAL/SHM dan sequence; database utama tidak boleh ikut terhapus.

---

## 3. Source vs manual overlay

Source ARKAS/BKU mencakup nomor bukti/source key, tanggal transaksi, uraian source, kegiatan/rekening, penerima source, nilai gross/tax/net, dan payload sumber.

Overlay operator mencakup `payment_description`, `payment_method`, `payment_reference`, `receipt_recipient_name`, `spj_category`, vendor/procurement manual, detail kategori, package/document status, dan nomor.

Safe sync:

- source ada → source boleh diperbarui, overlay dipertahankan;
- source hilang → tandai `SOURCE_MISSING`, jangan hapus pekerjaan operator;
- source kembali → aktifkan lagi tanpa menghapus overlay;
- source berubah setelah package/numbering → gunakan reconciliation, jangan ubah final diam-diam.

---

## 4. Kategori SPJ canonical

```text
BARANG
KONSUMSI
PEMELIHARAAN
JASA_LAINNYA
SPPD
HONOR_PEGAWAI
```

Alias lama dinormalisasi:

```text
BELANJA_MODAL      -> BARANG
PERJALANAN_DINAS  -> SPPD
JASA_HONORARIUM   -> HONOR_PEGAWAI
UPAH               -> PEMELIHARAAN
LAINNYA            -> JASA_LAINNYA
```

SiPLah bukan kategori. Gunakan `payment_method = siplah`.

---

## 5. Workflow status canonical

```text
Perlu Perhatian   -> SOURCE_MISSING / requires_reconciliation
Belum Dikerjakan  -> belum memiliki Paket SPJ
Perlu Dilengkapi  -> DRAFT
Siap Dinomori     -> READY
Sudah Bernomor    -> NUMBERED / FINAL
```

`transaction_items` berasal dari source/sinkronisasi dan **bukan** indikator status pekerjaan operator.

---

## 6. Pengadaan barang/konsumsi

Surat Pesanan internal Non-SiPLah memiliki dua tahap:

- substansi/content harus lengkap sebelum siap;
- nomor Surat Pesanan tidak menjadi blocker DRAFT/READY dan diterbitkan saat numbering;
- pada NUMBERED/FINAL nomor yang diwajibkan harus sudah tersedia.

Nomor marketplace SiPLah (`siplah_order_number`) berbeda dari nomor Surat Pesanan SPJ (`order_number`).

Rule tanggal canonical:

```text
order_date <= transaction_date
order_date <= bap_date
bap_date <= bast_date
```

Jangan menambah rule BAP/BAST <= tanggal transaksi sebagai keputusan domain baru kecuali ada kebutuhan resmi yang disetujui.

---

## 7. Konsumsi

Auto-fill peserta pada Detail Transaksi hanya mengambil `Employee.source_type = DAPODIK`. Participant manual tetap diperbolehkan.

Jumlah peserta harus konsisten dengan total porsi sesuai rule aktif.

---

## 8. Pemeliharaan

Domain utama:

```text
1 transaction
└── 1 work order
    └── banyak workers
```

Untuk kasus pemeliharaan yang sumber BKU memisahkan bahan/barang dan upah, transaksi dapat saling ditautkan melalui:

```text
maintenance_material_transaction_id
maintenance_labor_transaction_id
```

Aturan UI/domain linkage:

- jika transaksi yang sedang dikerjakan adalah **upah**, operator hanya memilih transaksi **bahan/barang**;
- jika transaksi yang sedang dikerjakan adalah **bahan/barang**, operator hanya memilih transaksi **upah**;
- transaksi tidak boleh menautkan dirinya sendiri;
- label pilihan memakai `NOMOR BUKTI - PAYMENT DESCRIPTION`;
- kandidat tetap harus berada dalam konteks tahun anggaran/sumber dana aktif dan source yang layak.

Linkage tidak boleh dianggap selesai untuk RAB sampai generator benar-benar menggabungkan sumber bahan + upah dan rekonsiliasi nilainya jelas.

---

## 9. SPPD

```text
1 transaction
└── banyak travels/pelaksana
```

Surat tugas/numbering tetap mengikuti lifecycle dokumen; preview tidak boleh mengalokasikan nomor.

---

## 10. Honor Pegawai

Satu transaksi boleh memiliki banyak penerima honor. Honor tidak boleh dicampur dengan worker pemeliharaan tanpa mapping domain yang eksplisit.

---

## 11. JASA_LAINNYA multi-penerima

Satu transaksi BKU boleh memiliki banyak penerima/penyedia jasa tanpa memecah transaction/package.

Implementasi dasar aktif menggunakan `serviceRecipients` dengan dimensi seperti:

```text
recipient/vendor
service_type
service_description
quantity
unit
rental_days
daily_rate
usage period
payment reference
agreement
```

Perhitungan dasar sewa harian:

```text
quantity × rental_days × daily_rate = amount penerima
```

Target domain yang wajib sebelum dianggap end-to-end:

```text
Σ gross penerima = transaction.gross_amount
Σ tax penerima   = transaction.tax_total
Σ net penerima   = transaction.net_amount
```

Pajak harus dapat dipertahankan pada level penerima/service line bila dasar potong/identitas berbeda. Package belum boleh READY bila rekonsiliasi yang diwajibkan tidak sesuai.

Jangan membuat kategori baru `SEWA_LAPTOP`, `SEWA_MOBIL`, dan sejenisnya. Gunakan subtype/detail di bawah `JASA_LAINNYA`.

---

## 12. Lifecycle dan locking

Status package:

```text
DRAFT
READY
NUMBERED
FINAL
CANCELLED
```

Prinsip:

- DRAFT/READY dapat diedit sesuai authorization;
- NUMBERED/FINAL tidak diedit normal;
- cancellation/reissue/reopen harus eksplisit dan audited;
- preview tidak mengubah lifecycle.

---

## 13. Penomoran

1. Setiap jenis dokumen memiliki domain nomor sendiri.
2. Nomor mengikuti tanggal/peristiwa dokumen bila tersedia.
3. Input order transaksi tidak menentukan nomor.
4. Nomor aktif tidak boleh ditimpa.
5. Nomor dibatalkan tetap menjadi history.
6. Reissue tidak boleh menciptakan identitas aktif ganda.
7. Untuk transaksi pada tanggal sama, gunakan urutan source timestamp sebelum fallback ke ID/nomor bukti.

---

## 14. UI Paket SPJ

Sub-tab package:

```text
Rincian
├── Rincian Transaksi
└── Dokumen & Template
Isian Manual
Penomoran
```

Perubahan UI tidak boleh mengubah validation, lifecycle, atau numbering.

Gunakan primitive/theme token canonical (`x-ui.*`, `ui-*`, `--ui-*`, `--theme-*`, `--spj-*`).

---

## 15. Audit

Aktivitas sensitif yang perlu audited mencakup sync, perubahan overlay, prepare/READY, numbering, cancellation/reissue, finalization/reopen, reconciliation, reset/restore tenant, dan perubahan konfigurasi penting.

---

## 16. Status implementasi

Jangan menaruh daftar PASS panjang di dokumen ini. Gap implementasi aktif dipusatkan di:

```text
docs/CURRENT_PROGRESS.md
```

Roadmap penyelesaiannya ada di:

```text
docs/DEVELOPMENT_ROADMAP.md
```
