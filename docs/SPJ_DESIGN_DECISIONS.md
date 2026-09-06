# SPJ BOSP Web — Keputusan Desain & Aturan Bisnis

Terakhir diperbarui: **2026-09-06**

Dokumen ini adalah sumber keputusan bisnis permanen. Jika ada dokumen historis yang bertentangan, gunakan dokumen ini bersama `CURRENT_PROGRESS.md` dan kode aktif.

---

## 1. Prinsip utama aplikasi

1. ARKAS/BKU adalah source hasil sinkronisasi, bukan data manual operator.
2. Data operator SPJ harus disimpan terpisah dan dipertahankan saat sync ulang.
3. Database tenant dapat direbuild pada fase development, tetapi reset tidak boleh menyentuh database utama.
4. Sekolah, tahun anggaran, dan sumber dana aktif adalah boundary setiap operasi tenant.
5. Penomoran dokumen mengikuti jenis dokumen dan peristiwa/tanggalnya, bukan urutan input transaksi.
6. Preview/download tidak boleh menerbitkan nomor diam-diam.
7. Dokumen bernomor/final tidak boleh berubah tanpa lifecycle/revisi yang sah.
8. `manual_description` tidak digunakan. Uraian operator memakai `payment_description` atau field detail kategori.
9. `recipient_name` adalah source BKU/ARKAS; `receipt_recipient_name` adalah data operator.
10. UI tidak boleh mengubah atau melemahkan aturan backend.

---

## 2. Multi-database

### Database utama

User, sekolah, konfigurasi tenant, sumber ARKAS, backup, setup, dan metadata global.

### Database tenant/sekolah

Tahun anggaran, sumber dana, RKAS/BKU, transaksi, item, data manual SPJ, paket, detail kategori, nomor dokumen, audit, reconciliation, dan data kerja sekolah.

### Reset tenant

Reset database sekolah adalah rebuild SQLite tenant, termasuk pembersihan `-wal/-shm` dan `sqlite_sequence`. Database utama tetap utuh.

---

## 3. Source vs manual overlay

### Source ARKAS/BKU

Contoh:

- nomor bukti/source key;
- tanggal transaksi;
- uraian source;
- kegiatan/rekening;
- penerima BKU;
- gross/tax/net;
- SiPLah indicator/source payload.

### Data operator

Contoh:

- `payment_description`;
- `payment_method`;
- `payment_reference`;
- `receipt_recipient_name`;
- `spj_category`;
- vendor/manual procurement data;
- rincian item SPJ;
- participants/workers/travels/honors/work order;
- package/document status dan nomor.

Safe sync tidak boleh menimpa manual overlay.

---

## 4. Safe sync dan reconciliation

Aturan:

- source masih ada → source fields dapat diperbarui, manual fields dipertahankan;
- source hilang → tandai `SOURCE_MISSING`, jangan hapus manual/package/number;
- source muncul kembali → kembali `ACTIVE`, pertahankan manual;
- source berubah setelah package dibuat/numbered → gunakan `requires_reconciliation`, jangan ubah final document diam-diam.

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

---

## 6. Kategori Konsumsi dan sumber peserta

Konsumsi membutuhkan event/acara, lokasi, tanggal, participant count, daftar peserta, dan porsi sesuai rule yang aktif.

Keputusan terbaru untuk auto-fill peserta pada Detail Transaksi:

```text
fillTeachers() -> hanya Employee aktif dengan source_type = DAPODIK
```

Data pegawai yang hanya berasal dari ARKAS tidak boleh ikut diisikan oleh tombol auto-fill konsumsi.

Manual participant tetap boleh ditambahkan operator.

---

## 7. Pengadaan barang/konsumsi

### 7.1 Surat Pesanan internal Non-SiPLah

Surat Pesanan internal memiliki dua tahap requirement.

#### Kelengkapan isi Surat Pesanan

Blocking pada tahap persiapan bila belum lengkap. Pemeriksaan meliputi:

- identitas penyedia;
- tanggal pesanan;
- minimal satu item;
- uraian item;
- quantity > 0;
- satuan;
- harga/nilai item valid;
- gross amount > 0.

#### Nomor Surat Pesanan

Nomor **tidak boleh menjadi blocker pada `DRAFT`/`READY`** karena nomor diterbitkan aplikasi saat numbering.

Nomor menjadi wajib ketika package sudah:

```text
NUMBERED
FINAL
```

Jika package sudah numbered/final tetapi nomor PESANAN tidak tersedia, itu adalah error penomoran yang harus diperbaiki.

### 7.2 SiPLah vs Surat Pesanan internal

SiPLah bukan kategori SPJ.

```text
payment_method = siplah
```

Nomor marketplace SiPLah (`siplah_order_number`) berbeda dari Nomor Surat Pesanan SPJ (`order_number`) yang diterbitkan aplikasi.

Untuk SiPLah, requirement internal order Non-SiPLah tidak diterapkan dengan cara yang sama. Dokumen tetap ditentukan oleh kategori SPJ.

---

## 8. Kronologi tanggal pengadaan

Aturan domain Paket SPJ:

```text
order_date <= transaction_date
order_date <= bap_date
bap_date <= bast_date
```

Implementasi equivalent pada `SpjPackageUseCase`:

```text
order_date before_or_equal transaction_date
bap_date after_or_equal order_date
bast_date after_or_equal bap_date
```

Tidak ada keputusan domain baru yang mewajibkan BAP/BAST harus <= tanggal transaksi.

**Known implementation mismatch:** `TransactionController::updateManualDescription()` saat ini masih menambahkan `before_or_equal:transaction_date` pada BAP dan BAST. Ini harus dianggap technical debt/entry-point inconsistency, bukan rule desain yang sengaja diputuskan.

---

## 9. Detail kategori lain

### Pemeliharaan

```text
1 transaksi
└── 1 work order
    └── banyak workers
```

### SPPD

```text
1 transaksi
└── banyak travels/pelaksana
```

### Honor Pegawai

Satu transaksi dapat memiliki banyak penerima honor. Honor tidak boleh dicampur dengan pekerja pemeliharaan tanpa mapping/service yang jelas.

### Jasa Lainnya

Digunakan untuk jasa yang tidak masuk kategori utama lain. Jangan menjadikannya bucket untuk semua transaksi.

---

## 10. Status dan locking

Status package yang aktif pada codebase mencakup:

```text
DRAFT
READY
NUMBERED
FINAL
CANCELLED
```

Prinsip:

- DRAFT/READY masih dapat diedit sesuai authorization;
- NUMBERED/FINAL tidak boleh diedit normal;
- pembatalan nomor menyimpan history/audit;
- preview tidak mengubah lifecycle;
- cancellation/reissue harus melalui flow eksplisit.

---

## 11. Penomoran dokumen

1. Setiap jenis dokumen memiliki domain number sendiri.
2. Urutan input transaction tidak menentukan urutan nomor.
3. Tanggal dokumen/peristiwa menjadi basis numbering bila tersedia.
4. Numbering sebaiknya per quarter/domain yang relevan.
5. Nomor aktif tidak boleh ditimpa.
6. Nomor yang dibatalkan tetap menjadi history.
7. Reissue pada package yang sama tidak boleh menciptakan identitas dokumen duplikat.
8. Untuk transaksi BKU pada tanggal yang sama, urutan mengikuti `create_date` ARKAS, lalu `last_update`, sebelum fallback ke ID sumber dan nomor bukti.

Jenis automatic numbering saat ini mencakup antara lain SPJ, PESANAN, BAP, BAST, SPK, RAB, dan Surat Tugas Perjalanan Dinas.

---

## 12. UI Paket SPJ — keputusan struktur

Sub-tab internal Paket:

```text
Rincian
Isian Manual
Penomoran
```

Keputusan terbaru:

- `Dokumen & Template` berada di dalam tab **Rincian**, bukan sebagai section global di atas semua sub-tab.
- Tab Rincian harus membedakan **Panel Rincian Transaksi** dan **Panel Dokumen & Template** sebagai dua card yang jelas.
- Header kedua panel mengikuti theme accent dan surface aktif.
- Daftar template menggunakan compact rows, bukan card tinggi per dokumen.
- Isian Manual dan Penomoran tidak boleh dipanjangkan oleh daftar template.
- Unduh Paket PDF merender seluruh template Excel aktif yang sesuai kategori paket; setiap template juga dapat dipratinjau, diunduh sebagai Excel, atau diunduh sebagai PDF.

Perubahan struktur tetap tidak mengubah validation atau numbering. Unduhan mengikuti template aktif agar keluaran paket dan dokumen per jenis konsisten.

---

## 13. Theme/CSS decisions

Kode baru harus mengutamakan:

```text
x-ui.*
ui-*
--ui-*
--theme-*
--spj-*
```

Tailwind tetap untuk layout/spacing/responsive. Hindari hard-coded `bg-white`, `text-slate-*`, `hover:bg-slate-50`, atau accent statis untuk surface utama.

Compatibility layer yang saat ini masih sah:

```text
spj-workspace-standardization.css
spj-package-theme-fix.css
spj-package-document-placement.css
dark-form-controls.css
```

---

## 14. Audit dan timestamp

Aktivitas sensitif yang harus dapat diaudit meliputi sync, perubahan data manual, prepare package, numbering, cancellation/reissue, finalization, reopen, restore/reset tenant, dan reconciliation penting.

---

## 15. Risiko utama

- manual data hilang saat sync;
- source missing dianggap delete permanen;
- sumber dana tercampur;
- nomor berubah setelah diterbitkan;
- circular validation karena nomor diwajibkan sebelum numbering;
- rule tanggal berbeda antar endpoint;
- participant konsumsi terisi dari source yang salah;
- final document berubah karena sync/edit massal;
- UI theme hard-coded sehingga dark/theme tertentu tidak terbaca.

---

## 16. Cara menggunakan dokumen ini

Sebelum mengubah domain SPJ, baca:

```text
docs/SPJ_DESIGN_DECISIONS.md
docs/CURRENT_PROGRESS.md
docs/ARCHITECTURE_COMPLETE.md
```

Sebelum mengubah UI/CSS, baca juga:

```text
docs/GUI_STANDARDIZATION.md
docs/CSS_USAGE_GUIDE.md
```
