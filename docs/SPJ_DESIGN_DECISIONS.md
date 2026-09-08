# SPJ BOSP Web — Keputusan Desain & Aturan Bisnis

Terakhir diperbarui: **2026-09-08**

Dokumen ini adalah sumber keputusan bisnis permanen. Status implementasi/gap aktif ada di `docs/CURRENT_PROGRESS.md`.

## 1. Prinsip utama

1. ARKAS/BKU adalah source readonly, bukan data manual operator.
2. Data operator SPJ disimpan terpisah dan dipertahankan saat sync ulang.
3. Sekolah, tahun anggaran, dan sumber dana aktif adalah boundary operasi tenant.
4. Preview/download tidak boleh menerbitkan nomor diam-diam.
5. NUMBERED/FINAL terkunci; koreksi harus melalui lifecycle yang sah.
6. Nomor mengikuti domain dokumen dan urutan tanggal/peristiwa, bukan urutan input.
7. `manual_description` tidak digunakan.
8. `recipient_name` adalah source; `receipt_recipient_name` adalah overlay operator/Penerima Utama.
9. UI tidak boleh melemahkan aturan backend.
10. Detail Transaksi dan Paket SPJ harus memiliki ownership data tunggal; field yang sama tidak boleh diedit dari dua workspace.

## 2. Multi-database dan APP DATA

Database utama menyimpan user, sekolah, konfigurasi tenant, metadata global, dan referensi pengelolaan database sekolah.

Database tenant menyimpan RKAS/BKU hasil sinkronisasi, transaksi, detail SPJ, package, numbering, audit, dan data kerja sekolah.

Root data aplikasi dapat dikonfigurasi melalui:

```env
SPJ_DATA_PATH=D:/lrvProject/spj-bosp-data
```

Jika env tidak tersedia, fallback ke `storage/app`.

Database sekolah canonical:

```text
{SPJ_DATA_PATH}/school-databases/{NPSN}/spj.sqlite
```

Dummy tenant:

```text
{SPJ_DATA_PATH}/school-databases/_unselected.sqlite
```

Reset tenant hanya boleh merebuild tenant, termasuk WAL/SHM dan sequence; database utama tidak boleh ikut terhapus.

## 3. Source vs manual overlay

Source ARKAS/BKU mencakup nomor bukti/source key, tanggal transaksi, uraian source, kegiatan/rekening, penerima source, nilai gross/tax/net, dan payload sumber.

Overlay operator mencakup `payment_description`, `payment_method`, `payment_reference`, `receipt_recipient_name`, `spj_category`, vendor/procurement manual, detail kategori, package/document status, dan nomor.

Safe sync:

- source ada → source boleh diperbarui, overlay dipertahankan;
- source hilang → tandai `SOURCE_MISSING`, jangan hapus pekerjaan operator;
- source kembali → aktifkan lagi tanpa menghapus overlay;
- source berubah setelah package/numbering → gunakan reconciliation, jangan ubah final diam-diam.

## 4. Ownership Detail Transaksi ↔ Paket SPJ

### 4.1 Detail Transaksi

Detail Transaksi adalah workspace fakta transaksi/source. Struktur UI saat ini disederhanakan menjadi:

```text
Header Transaksi
→ Informasi Referensi ARKAS/BKU + Total Pajak
→ Rincian Barang/Jasa
→ Status Paket SPJ
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

`item_description` harus tersimpan sebelum Paket SPJ dapat dibuat/dibuka. Nilai yang baru diketik tetapi belum disimpan tidak dianggap valid.

Detail Transaksi tidak boleh menjadi workspace kedua untuk kategori, payment, vendor, pajak manual, atau data kategori SPJ.

### 4.2 Paket SPJ

Paket SPJ adalah satu-satunya workspace mutation data dokumen pertanggungjawaban:

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

Paket hanya membaca `item_description`, quantity, unit, unit price, amount, dan pajak dari transaksi. Paket tidak boleh menyediakan edit kedua untuk data source tersebut.

### 4.3 Pajak

Pajak adalah source transaksi:

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

Paket SPJ tidak boleh menghitung ulang atau menulis ulang PPN/PPh/SSPD/tax_total/net_amount.

UI canonical:

- Detail Transaksi cukup menampilkan **Total Pajak** pada Informasi Referensi ARKAS/BKU;
- Paket SPJ menampilkan **Pajak** pada summary card;
- rincian lengkap PPN/PPh/SSPD berada pada tab readonly **Rincian Pajak**.

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

Pergantian kategori pada Paket harus berjalan tanpa full page reload. Persist backend authoritative; bila gagal, UI kembali ke kategori sebelumnya.

### 5.1 Baris Kategori SPJ

Pada desktop:

```text
Kategori SPJ 1/4 | Kontrol konteks 3/4
```

- `BARANG` → radio `SiPLah / Non SiPLah`, hanya satu boleh aktif;
- `PEMELIHARAAN` → selector transaksi pasangan yang relevan;
- kategori lain → area konteks boleh kosong.

Hint dekoratif yang tidak membantu operator tidak perlu ditampilkan.

## 6. Workflow status canonical

```text
Perlu Perhatian   -> SOURCE_MISSING / requires_reconciliation
Belum Dikerjakan  -> belum memiliki Paket SPJ
Perlu Dilengkapi  -> DRAFT
Siap Dinomori     -> READY
Sudah Bernomor    -> NUMBERED / FINAL
```

`transaction_items` berasal dari source dan bukan indikator status pekerjaan operator.

Gateway Detail Transaksi → Paket wajib memvalidasi `item_description` tersimpan sebelum draft dibuat/dibuka.

## 7. Pengadaan barang/konsumsi

Surat Pesanan internal Non-SiPLah memiliki dua tahap:

- substansi/content harus lengkap sebelum siap;
- nomor Surat Pesanan tidak menjadi blocker DRAFT/READY dan diterbitkan saat numbering;
- pada NUMBERED/FINAL nomor yang diwajibkan harus tersedia.

Nomor marketplace SiPLah (`siplah_order_number`) berbeda dari nomor Surat Pesanan SPJ (`order_number`).

Rule tanggal canonical:

```text
order_date <= transaction_date
order_date <= bap_date
bap_date <= bast_date
```

Jangan menambah rule BAP/BAST <= tanggal transaksi tanpa keputusan domain baru.

## 8. Konsumsi

Auto-fill peserta di Paket SPJ hanya mengambil `Employee.source_type = DAPODIK` bila fitur auto-fill digunakan. Participant manual tetap diperbolehkan.

Jumlah peserta harus konsisten dengan total porsi.

Porsi adalah integer pada UI; jangan tampilkan desimal untuk nilai porsi.

## 9. Pemeliharaan

Domain utama:

```text
1 transaction
└── 1 work order
    └── banyak workers
```

Untuk kasus BKU memisahkan bahan/barang dan upah, transaksi dapat saling ditautkan melalui:

```text
maintenance_material_transaction_id
maintenance_labor_transaction_id
```

Aturan linkage:

- jika transaksi saat ini upah, operator memilih transaksi bahan/barang;
- jika transaksi saat ini bahan/barang, operator memilih transaksi upah;
- transaksi tidak boleh menautkan dirinya sendiri;
- kandidat tetap dalam konteks tahun anggaran/sumber dana aktif;
- relationship state tetap transaction/context-owned dan disimpan lewat endpoint maintenance-link khusus, meskipun selector ditampilkan di Paket SPJ.

Rendering dokumen boleh menggabungkan material dari transaksi bahan dan pekerja/upah dari transaksi pasangan hanya untuk document context; source BKU tidak boleh ditulis ulang.

## 10. SPPD

```text
1 transaction
└── banyak travels/pelaksana
```

Surat tugas/numbering mengikuti lifecycle dokumen; preview tidak boleh mengalokasikan nomor.

## 11. Honor Pegawai

Satu transaksi boleh memiliki banyak penerima honor. Rincian honor adalah data Paket SPJ, bukan Detail Transaksi.

## 12. JASA_LAINNYA multi-penerima

Satu transaksi BKU boleh memiliki banyak penerima/penyedia jasa tanpa memecah transaction/package.

Perhitungan dasar:

```text
quantity × rental_days × daily_rate = gross/amount penerima
```

Kontrak agregat:

```text
Σ gross penerima = transaction.gross_amount
Σ tax penerima   = transaction.tax_total
Σ net penerima   = transaction.net_amount
```

Jika source hanya menyediakan pajak/netto agregat, aplikasi boleh mengalokasikan tax/net secara proporsional dengan koreksi rounding pada baris terakhir. Output dokumen jamak harus mempertahankan identitas, gross, tax, dan net tiap penerima.

## 13. Penerima Utama

Semua tabel kategori non-BARANG menggunakan istilah **Penerima Utama** untuk pihak utama/penanda tangan kuitansi.

- UI menggunakan radio button, bukan checkbox, karena hanya satu yang utama;
- jika tabel mempunyai data, aplikasi harus dapat menentukan satu Penerima Utama;
- pilihan tersebut disinkronkan ke `receipt_recipient_name` dan, bila model relasi mendukungnya, flag row-level terkait.

## 14. Format numeric UI

Nilai uang/tarif/harga memakai pola accounting Indonesia:

```text
1000     -> 1.000
1250000  -> 1.250.000
```

Tanpa `Rp` dan tanpa desimal pada field/tampilan accounting standar.

Hari, porsi, bulan/kali, dan hitungan diskret lain memakai integer tanpa koma/desimal.

Database/backend tetap menerima nilai numeric mentah; format accounting adalah presentation/input formatting.

## 15. Lifecycle dan locking

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
- cancellation/reissue/reopen eksplisit dan audited;
- preview tidak mengubah lifecycle.

## 16. Penomoran

1. Setiap jenis dokumen memiliki domain nomor sendiri.
2. Nomor mengikuti tanggal/peristiwa dokumen bila tersedia.
3. Input order transaksi tidak menentukan nomor.
4. Nomor aktif tidak boleh ditimpa.
5. Nomor dibatalkan tetap menjadi history.
6. Reissue tidak boleh menciptakan identitas aktif ganda.
7. Nomor otomatis **bukan input operator** pada Isian Manual.
8. Nomor otomatis ditampilkan sebagai informasi horizontal di bawah baris kategori.

## 17. UI Paket SPJ

Toolbar Paket:

```text
Semua Paket | Paket Sebelumnya | Paket Setelahnya
```

Previous/next hanya boleh mencari Paket pada sekolah, tahun anggaran, dan sumber dana aktif yang sama. Urutan navigasi berdasarkan `transaction_date`, lalu transaction `id`.

Summary Paket:

```text
Periode | Penerima | Bruto | Pajak | Nilai Dibayarkan
```

Sub-tab package:

```text
1. Rincian
   ├── Rincian Transaksi (readonly)
   └── Dokumen & Template
2. Isian Manual
3. Rincian Pajak (readonly)
4. Penomoran
```

### Isian Manual

Urutan canonical:

```text
Kategori SPJ + kontrol konteks
→ Informasi Penomoran Otomatis
→ Data Umum Dokumen
→ Data kategori / tabel rincian
→ Simpan Paket
```

Data Umum Dokumen pada desktop:

```text
kiri  : Uraian pembayaran, textarea 5 baris
kanan : Metode, Referensi, Penerima Utama, Penyedia, Pemilik, NPWP
```

Semua field umum selain textarea harus berkumpul di kolom kanan, bukan turun di bawah textarea.

Tabel kategori non-BARANG harus compact dan hanya memiliki **satu** pagination lokal. Global table standardizer tidak boleh menyuntik pagination kedua.

## 18. Compatibility path

Compatibility write-path ownership sudah ditutup:

- create/open draft melalui `CreateSpjDraftUseCase`;
- update Paket melalui `UpdateSpjPackageDetailsUseCase`;
- `TransactionController` hanya mengubah `item_description` melalui endpoint khusus;
- route/use-case/controller legacy yang menulis SPJ dari transaksi sudah dipensiunkan.

## 19. Audit

Aktivitas sensitif yang perlu audited mencakup sync, perubahan overlay, create/open draft, perubahan kategori, update Paket, READY, numbering, cancellation/reissue, finalization/reopen, reconciliation, reset/restore tenant, dan perubahan konfigurasi penting.

## 20. Status implementasi

Gap aktif dipusatkan di:

```text
docs/CURRENT_PROGRESS.md
```

Roadmap:

```text
docs/DEVELOPMENT_ROADMAP.md
```

Arsip migrasi ownership:

```text
docs/URGENT_TRANSACTION_SPJ_MIGRATION.md
```
