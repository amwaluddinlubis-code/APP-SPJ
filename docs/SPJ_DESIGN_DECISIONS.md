# SPJ BOSP Web — Keputusan Desain & Aturan Bisnis

Terakhir diperbarui: **2026-09-08**

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
10. Detail Transaksi dan Paket SPJ harus memiliki ownership data tunggal; field yang sama tidak boleh diedit dari dua workspace.

---

## 2. Multi-database dan APP DATA

Database utama menyimpan user, sekolah, konfigurasi tenant, metadata global, dan referensi pengelolaan database sekolah.

Database tenant menyimpan RKAS/BKU hasil sinkronisasi, transaksi, detail SPJ, package, numbering, audit, dan data kerja sekolah.

Root data aplikasi dapat dikonfigurasi melalui:

```env
SPJ_DATA_PATH=D:/lrvProject/spj-bosp-data
```

Jika env tersebut tidak tersedia, aplikasi fallback ke `storage/app` agar source project tidak mengunci path mesin tertentu.

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

## 4. Ownership Detail Transaksi ↔ Paket SPJ

Keputusan ini bersifat permanen dan menjadi dasar migrasi aktif.

### 4.1 Detail Transaksi

Detail Transaksi adalah workspace fakta transaksi/source. Ia boleh menampilkan:

```text
nomor bukti/source key
tanggal transaksi
uraian source
kegiatan/rekening
penerima source
gross
PPN
PPh 21
PPh 22
PPh 23
PPh 4(2)
SSPD / Pajak Daerah
total pajak
netto
rincian item source
status source/reconciliation
status Paket SPJ
```

Satu-satunya field rincian item yang boleh dikoreksi operator di Detail Transaksi adalah:

```text
item_description
```

Aturan item:

```text
description       = readonly source
item_description  = editable di Detail Transaksi
quantity          = readonly
unit              = readonly
unit_price        = readonly
amount            = readonly
```

`item_description` harus tersimpan sebelum Paket SPJ dapat dibuat/dibuka. Nilai yang baru diketik tetapi belum disimpan tidak dianggap valid untuk membuka Paket.

### 4.2 Paket SPJ

Paket SPJ adalah satu-satunya workspace untuk mutation data dokumen pertanggungjawaban:

```text
spj_category
payment_description
payment_method
payment_reference
receipt_recipient_name
vendor/rekanan
invoice / metadata SiPLah
data pengadaan
data konsumsi/peserta
data pemeliharaan/pekerja
data SPPD/pelaksana
data honor/penerima
data JASA_LAINNYA/penerima jasa
penomoran
preview/generate/download/finalisasi
```

Paket SPJ hanya membaca `item_description`, quantity, unit, unit price, amount, dan pajak dari transaksi. Paket tidak boleh menyediakan edit kedua untuk data tersebut.

### 4.3 Pajak

Pajak adalah data source transaksi dan tetap dibedakan:

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

Paket SPJ tidak boleh menghitung ulang atau menulis ulang PPN/PPh/SSPD/tax_total/net_amount. Bila UI Paket menampilkan tarif/nilai pajak, sifatnya readonly reference.

---

## 5. Kategori SPJ canonical

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

Pergantian kategori pada Paket SPJ harus dapat dilakukan tanpa full page reload. Persist backend tetap authoritative; bila persist gagal, UI harus kembali ke kategori sebelumnya.

---

## 6. Workflow status canonical

```text
Perlu Perhatian   -> SOURCE_MISSING / requires_reconciliation
Belum Dikerjakan  -> belum memiliki Paket SPJ
Perlu Dilengkapi  -> DRAFT
Siap Dinomori     -> READY
Sudah Bernomor    -> NUMBERED / FINAL
```

`transaction_items` berasal dari source/sinkronisasi dan **bukan** indikator status pekerjaan operator.

Gateway Detail Transaksi → Paket SPJ wajib memvalidasi `item_description` tersimpan sebelum draft dibuat/dibuka.

---

## 7. Pengadaan barang/konsumsi

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

## 8. Konsumsi

Auto-fill peserta di workspace Paket SPJ hanya mengambil `Employee.source_type = DAPODIK` bila fitur auto-fill digunakan. Participant manual tetap diperbolehkan.

Jumlah peserta harus konsisten dengan total porsi sesuai rule aktif.

Konsumsi tidak menampilkan isian SiPLah sebagai blok terpisah bila kategori/flow tidak memenuhi kondisi SiPLah yang sah.

---

## 9. Pemeliharaan

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

Pada rendering dokumen kategori `PEMELIHARAAN`, context dokumen boleh mengambil rincian barang/material dari transaksi bahan terkait dan pekerja/upah dari transaksi upah terkait. Penggabungan ini hanya untuk rendering; source BKU dan nilai transaksi asal tidak boleh ditulis ulang atau digabung permanen.

Nilai transaksi pada kuitansi/A2 tetap mengikuti transaksi Paket SPJ yang sedang diproses. Bila template RAB memerlukan nilai total pekerjaan gabungan, sumber total harus berasal dari detail bahan + upah yang dirender, bukan dengan menimpa `gross_amount` transaksi asal.

---

## 10. SPPD

```text
1 transaction
└── banyak travels/pelaksana
```

Surat tugas/numbering tetap mengikuti lifecycle dokumen; preview tidak boleh mengalokasikan nomor.

---

## 11. Honor Pegawai

Satu transaksi boleh memiliki banyak penerima honor. Honor tidak boleh dicampur dengan worker pemeliharaan tanpa mapping domain yang eksplisit.

Rincian honor adalah data Paket SPJ, bukan field yang harus diisi ulang di Detail Transaksi.

---

## 12. JASA_LAINNYA multi-penerima

Satu transaksi BKU boleh memiliki banyak penerima/penyedia jasa tanpa memecah transaction/package.

Implementasi detail menggunakan `serviceRecipients` dengan dimensi seperti:

```text
recipient/vendor
service_type
service_description
quantity
unit
rental_days
daily_rate
gross/amount
tax_amount
net_amount
usage period
payment reference
agreement
```

Perhitungan dasar sewa harian:

```text
quantity × rental_days × daily_rate = gross/amount penerima
```

Kontrak agregat:

```text
Σ gross penerima = transaction.gross_amount
Σ tax penerima   = transaction.tax_total
Σ net penerima   = transaction.net_amount
```

Jika source hanya menyediakan pajak/netto agregat transaksi, aplikasi boleh mengalokasikan tax/net ke penerima secara proporsional terhadap gross dengan koreksi rounding pada baris terakhir agar total kembali persis ke source. Jika data pajak per penerima tersedia dari sumber yang lebih akurat, data tersebut harus diprioritaskan daripada alokasi proporsional.

Package belum boleh READY bila rekonsiliasi yang diwajibkan tidak sesuai. Output dokumen yang menampilkan penerima jamak harus mempertahankan identitas, gross, tax, dan net tiap penerima.

Jangan membuat kategori baru `SEWA_LAPTOP`, `SEWA_MOBIL`, dan sejenisnya. Gunakan subtype/detail di bawah `JASA_LAINNYA`.

---

## 13. Lifecycle dan locking

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

## 14. Penomoran

1. Setiap jenis dokumen memiliki domain nomor sendiri.
2. Nomor mengikuti tanggal/peristiwa dokumen bila tersedia.
3. Input order transaksi tidak menentukan nomor.
4. Nomor aktif tidak boleh ditimpa.
5. Nomor dibatalkan tetap menjadi history.
6. Reissue tidak boleh menciptakan identitas aktif ganda.
7. Untuk transaksi pada tanggal sama, gunakan urutan source timestamp sebelum fallback ke ID/nomor bukti.

---

## 15. UI Paket SPJ

Sub-tab package:

```text
Rincian
├── Rincian Transaksi (readonly)
└── Dokumen & Template
Isian Manual
Penomoran
```

Rincian transaksi di Paket SPJ tidak menjadi pintu kedua untuk edit `item_description`, quantity, unit, harga, nilai, atau pajak.

Perubahan UI tidak boleh mengubah validation, lifecycle, atau numbering.

Gunakan primitive/theme token canonical (`x-ui.*`, `ui-*`, `--ui-*`, `--theme-*`, `--spj-*`).

---

## 16. Compatibility path selama migrasi

Compatibility path sudah ditutup. Write-path SPJ hanya melalui:

- create/open draft melalui gateway Detail Transaksi → `CreateSpjDraftUseCase`;
- update data Paket melalui `UpdateSpjPackageDetailsUseCase`;
- `TransactionController` hanya mengubah `item_description` melalui `updateSpjDescriptions()`;
- route/use-case prepare lama (`spj.prepare`, `SpjPackageUseCase`, `SpjDocumentController`, `SpjReportController`) sudah dihapus.

Rencana penutupan compatibility path ada di `docs/URGENT_TRANSACTION_SPJ_MIGRATION.md`.

---

## 17. Audit

Aktivitas sensitif yang perlu audited mencakup sync, perubahan overlay, create/open draft, perubahan kategori, update Paket, READY, numbering, cancellation/reissue, finalization/reopen, reconciliation, reset/restore tenant, dan perubahan konfigurasi penting.

---

## 18. Status implementasi

Jangan menaruh daftar PASS panjang di dokumen ini. Gap implementasi aktif dipusatkan di:

```text
docs/CURRENT_PROGRESS.md
```

Roadmap penyelesaiannya ada di:

```text
docs/DEVELOPMENT_ROADMAP.md
```

Rencana migrasi prioritas:

```text
docs/URGENT_TRANSACTION_SPJ_MIGRATION.md
```
