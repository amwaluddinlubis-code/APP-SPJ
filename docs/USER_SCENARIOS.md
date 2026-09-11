# SPJ BOSP Web — Skenario Pengguna & Alur Kerja

Terakhir diperbarui: **2026-09-11**

Dokumen ini menjelaskan alur aplikasi dari perspektif pengguna, terutama operator sekolah. Dokumen ini bukan sumber status release. Gunakan:

- `SPJ_DESIGN_DECISIONS.md` untuk kontrak bisnis/domain permanen;
- `CURRENT_PROGRESS.md` untuk status/evidence terbaru;
- `DEVELOPMENT_ROADMAP.md` untuk prioritas pekerjaan;
- `GUI_STANDARDIZATION.md` untuk detail visual/layout.

Jika contoh UX di dokumen ini bertentangan dengan kontrak domain permanen, kontrak domain yang berlaku.

---

## 1. Prinsip pengalaman pengguna

Operator harus selalu tahu:

1. sekolah, tahun anggaran, dan sumber dana aktif;
2. transaksi mana yang perlu perhatian atau dilengkapi;
3. mana data readonly dari ARKAS/BKU dan mana data overlay operator;
4. workspace mana yang menjadi pemilik setiap field;
5. apa yang masih blocking;
6. dokumen apa yang tersedia;
7. apakah Paket masih editable atau sudah terkunci;
8. langkah berikutnya tanpa side effect tersembunyi.

Prinsip UX canonical:

```text
Detail Transaksi = fakta transaksi/source + koreksi item_description
Paket SPJ        = workspace pekerjaan dokumen pertanggungjawaban
```

Field SPJ yang sama tidak boleh editable di dua workspace.

---

## 2. Peran pengguna

### ADMIN

Mengelola sekolah, database tenant, backup/restore/reset, user/role, konfigurasi, template, impersonation, dan aksi sensitif sesuai authorization.

ADMIN juga dapat menjalankan pemeriksaan teknis/real-data yang memang disediakan sebagai read-only audit. Hak administrator tidak menghapus kewajiban tenant boundary dan lifecycle guard.

### OPERATOR

Memeriksa transaksi, menyimpan `item_description`, membuat/membuka Paket, melengkapi overlay dokumen, menjalankan validation/numbering/finalization yang diizinkan, dan menindaklanjuti reconciliation.

### VIEWER

Read-only. Backend harus menolak mutation walaupun UI atau request dimanipulasi.

---

## 3. Alur utama operator

```text
Login
→ Pilih sekolah
→ Pilih tahun & sumber dana
→ Sinkronisasi ARKAS/BKU
→ Daftar Transaksi
→ Aksi → Detail Transaksi
→ Periksa Informasi Referensi ARKAS/BKU
→ Koreksi item_description bila perlu
→ Simpan Uraian Barang/Jasa
→ Siapkan / Lihat Paket SPJ
→ Lengkapi Isian Manual
→ Validasi
→ READY
→ Penomoran
→ Preview / Unduh
→ FINAL / Arsip
```

Kontrak penting:

- preview/download tidak menerbitkan nomor;
- source ARKAS/BKU tidak dimutasi agar Paket dapat lolos;
- NUMBERED/FINAL tidak diedit melalui mutation normal;
- jika source berubah/hilang, gunakan reconciliation dan lifecycle yang sah.

---

## 4. Jalur audit real-data sebelum mutation

Untuk pemeriksaan data sekolah nyata, jalur canonical adalah audit read-only terlebih dahulu.

Contoh:

```text
Pilih tenant target
→ jalankan spj:audit-quarter
→ review anomaly + candidate
→ identifikasi blocker legitimate
→ jangan ubah database asli
→ bila perbaikan/mutation memang diperlukan, gunakan isolated copy
```

Audit read-only tidak boleh:

- membuat tenant database yang hilang;
- auto-migrate database target;
- mengubah source transaction/item;
- mengubah Paket/status;
- menerbitkan numbering;
- membuat vendor/penerima/SPPD/template fiktif untuk coverage.

Audit adalah alat diagnosis, bukan jalur mutation otomatis.

---

## 5. Daftar Transaksi

Setiap row pada layout aktif memiliki satu tombol **Aksi**. Modal Aksi menyediakan navigasi yang relevan seperti Detail Transaksi dan Paket SPJ.

Tidak ada editor kategori/payment/vendor SPJ di tabel transaksi.

Status workflow yang ditampilkan harus merefleksikan pekerjaan operator, bukan sekadar keberadaan item source:

```text
Perlu Perhatian   -> SOURCE_MISSING / requires_reconciliation
Belum Dikerjakan  -> belum memiliki Paket SPJ
Perlu Dilengkapi  -> Paket DRAFT
Siap Dinomori     -> Paket READY
Sudah Bernomor    -> Paket NUMBERED / FINAL
```

---

## 6. Detail Transaksi

Struktur canonical:

```text
Header Transaksi
→ Informasi Referensi ARKAS/BKU + Total Pajak
→ Rincian Barang/Jasa
   → item_description editable
   → description/quantity/unit/harga/nilai readonly
→ Status Paket SPJ
```

Detail Transaksi tidak menjadi tempat mengisi:

```text
Kategori SPJ
Uraian pembayaran
Metode/referensi pembayaran
Penerima Utama
Vendor/invoice
Peserta konsumsi
Pekerja pemeliharaan
Pelaksana SPPD
Penerima honor
Penerima jasa lainnya
```

### Validasi sebelum Paket

Semua `item_description` harus sudah tersimpan. Perubahan yang baru diketik tetapi belum disimpan tidak dianggap valid.

Jika item belum lengkap, gateway Paket harus mengarahkan operator kembali ke pemilik field tersebut, yaitu Detail Transaksi.

---

## 7. Pajak

Pajak adalah source transaksi.

Detail Transaksi cukup menampilkan **Total Pajak** sebagai ringkasan referensi ARKAS/BKU.

Pada Paket SPJ:

- summary menampilkan Pajak;
- tab **Rincian Pajak** menampilkan PPN/PPh/SSPD lengkap secara readonly;
- operator tidak dapat mengubah source tax dari Paket;
- request forged yang mencoba menulis ulang pajak harus diabaikan/ditolak oleh backend.

---

## 8. Membuka Paket SPJ

```text
Belum ada package  → Siapkan Paket SPJ
Sudah ada package  → Lihat Paket SPJ
```

Gateway canonical:

```text
validasi active context
→ validasi item_description tersimpan
→ firstOrCreate DRAFT bila belum ada
→ buka workspace Paket
```

Operator tidak diminta mengisi data kategori/vendor pada saat menekan gateway.

---

## 9. Navigasi Paket

Toolbar dan summary mengikuti `GUI_STANDARDIZATION.md` §10 (tidak disalin di sini agar tidak divergen).

Previous/next hanya bergerak pada tenant context yang sama:

```text
School + Fiscal Year + Fund Source
```

---

## 10. Sub-tab Paket

Urutan dan isi sub-tab mengikuti `GUI_STANDARDIZATION.md` §11 (tidak disalin di sini agar tidak divergen).

Dari sisi operator: Rincian untuk memeriksa, Isian Manual satu-satunya tempat mengubah selama Paket editable, Rincian Pajak readonly, Penomoran hanya lewat flow sah — preview/download bukan shortcut numbering.

---

## 11. Isian Manual — kategori dan konteks

Kategori canonical:

```text
BARANG
KONSUMSI
PEMELIHARAAN
JASA_LAINNYA
SPPD
HONOR_PEGAWAI
```

Perubahan kategori disimpan melalui backend authoritative. Jika Paket sudah READY dan kategori benar-benar berubah, Paket kembali ke DRAFT untuk revalidation.

### BARANG

SiPLah dan Non-SiPLah adalah konteks procurement/payment, bukan kategori baru.

```text
spj_category   = BARANG
payment_method = siplah | non-siplah/tunai sesuai domain
```

Jika source menandai SiPLah secara authoritative, UI tidak boleh membaliknya sembarangan.

### PEMELIHARAAN

Selector pasangan transaksi bahan/upah boleh ditampilkan di Paket, tetapi relationship state tetap transaction/context-owned dan disimpan melalui jalur linkage khusus.

Nomor otomatis ditampilkan sebagai informasi, bukan input operator.

---

## 12. Data Umum Dokumen

Field umum operator dapat mencakup:

```text
payment_description
payment_method
payment_reference
receipt_recipient_name
vendor/rekanan
vendor owner / NPWP
invoice / metadata procurement yang memang operator-owned
```

Source field tidak boleh diduplikasi menjadi input manual hanya demi kemudahan UI.

---

## 13. Penerima Utama

Untuk kategori yang memiliki banyak row penerima/pelaksana, istilah canonical adalah **Penerima Utama**.

Aturan UX/domain:

- hanya satu Penerima Utama;
- UI memakai radio, bukan checkbox;
- pilihan disinkronkan ke `receipt_recipient_name`;
- identitas row tetap dipertahankan, bukan digabung hanya karena nama serupa.

---

## 14. Kategori BARANG

Operator melengkapi data vendor, invoice, dan data pengadaan yang memang menjadi overlay.

Rincian item dibaca readonly dari transaksi menggunakan `item_description` yang sudah tersimpan.

Rule tanggal canonical:

```text
Tanggal Pesanan <= Tanggal Transaksi
Tanggal Pesanan <= Tanggal BAP
Tanggal BAP <= Tanggal BAST
```

Nomor internal otomatis diterbitkan oleh numbering, bukan diketik pada Isian Manual.

---

## 15. Kategori KONSUMSI

Operator mengisi data acara dan participant roster sesuai kebutuhan dokumen.

Kontrak auto-fill:

```text
Auto-fill KONSUMSI/SPPD = master Pegawai menyatu (ARKAS + Dapodik + Manual)
Participant manual = allowed
```

Roster menyatu adalah kontrak aktif; koreksi operator canonical via `operator_locked`.

Susunan peserta dibantu tool cepat: tombol ↑/↓ per baris (sentuh-ramah), sortir A–Z, kembalikan ikut roster, simpan/pakai urutan tersimpan per sekolah+tahun, dan salin urutan dari 5 paket KONSUMSI terakhir. Penerima Utama selalu mengikuti orangnya, bukan posisinya.

Identitas pegawai tidak boleh silent-merge hanya berdasarkan nama yang ambigu.

---

## 16. Unified employee identity dari perspektif pengguna

Master pegawai dapat merepresentasikan satu orang yang ditemukan dari lebih dari satu source, misalnya ARKAS/PTK dan Dapodik.

Prinsip yang harus terlihat pada behavior aplikasi:

- identifier kuat seperti NUPTK/NIP digunakan sebelum fallback nama;
- nama ter-normalisasi hanya boleh menjadi fallback bila kandidat unik;
- jika nama ambigu, aplikasi tidak boleh menebak;
- provenance source tetap dipertahankan;
- row yang dikunci operator tidak boleh hilang karena sweep sync;
- participant roster tidak boleh menyatukan dua orang berbeda hanya karena ejaan nama sama.

---

## 17. Kategori PEMELIHARAAN

```text
1 transaksi
└── 1 work order
    └── banyak workers
```

Operator mengisi data pekerjaan dan pekerja di Paket.

Jika bahan dan upah berasal dari transaksi berbeda, linkage hanya menghubungkan context dokumen dan tidak menulis ulang source BKU.

---

## 18. Kategori SPPD

Satu transaksi dapat memiliki banyak pelaksana perjalanan.

Jika dataset nyata pada fiscal year tertentu tidak mempunyai SPPD, operator/developer tidak boleh membuat transaksi SPPD fiktif hanya untuk memenuhi coverage pengujian real-data.

---

## 19. Kategori HONOR_PEGAWAI

Satu transaksi dapat memiliki banyak penerima honor. Detail honor adalah overlay Paket SPJ dan harus tetap direkonsiliasi terhadap nilai source yang authoritative.

---

## 20. Kategori JASA_LAINNYA

Satu transaksi dapat memiliki banyak penerima jasa tanpa memecah transaksi/Paket.

Kontrak agregat:

```text
Σ gross penerima = transaction.gross_amount
Σ tax penerima   = transaction.tax_total
Σ net penerima   = transaction.net_amount
```

Jika source hanya memberi pajak/net agregat, alokasi per penerima boleh dilakukan sesuai policy aplikasi dengan rounding yang deterministic.

Core reconciliation multi-penerima sudah menjadi bagian dari behavior aplikasi; QA output dokumen multi-penerima tetap harus menjaga identitas dan nilai tiap penerima.

---

## 21. SiPLah

SiPLah adalah procurement/payment channel, bukan kategori SPJ dan bukan lifecycle terpisah.

Contoh canonical:

```text
spj_category        = BARANG
payment_method      = siplah
siplah_order_number = nomor marketplace
```

Nomor marketplace SiPLah berbeda dari nomor Surat Pesanan internal aplikasi.

Untuk BARANG SiPLah, requirement internal purchase order yang tidak applicable tidak boleh dipaksakan hanya karena workflow Non-SiPLah memilikinya.

Metadata SiPLah yang belum tersedia tidak boleh menghasilkan blocker READY tanpa dasar aturan bisnis yang eksplisit.

---

## 22. Penomoran dan lifecycle

Flow umum:

```text
DRAFT
→ lengkapi + validasi
→ READY
→ numbering
→ NUMBERED
→ finalisasi
→ FINAL
```

Aturan:

1. numbering idempotent;
2. nomor aktif tidak boleh ganda;
3. nomor mengikuti chronology/domain dokumen;
4. preview/download tidak mengalokasikan nomor;
5. NUMBERED/FINAL terkunci dari mutation normal;
6. cancel/reissue/reopen harus eksplisit dan audited;
7. perubahan kategori READY yang benar-benar berubah mengembalikan Paket ke DRAFT.

---

## 23. Source berubah/hilang

Safe sync mempertahankan overlay operator.

```text
source hilang  -> SOURCE_MISSING / perlu perhatian
source berubah -> reconciliation bila diperlukan
source kembali -> gunakan identity transaction/package yang sama
```

Source change tidak boleh diam-diam menulis ulang Paket NUMBERED/FINAL.

---

## 24. Template dan dokumen

Operator/admin dapat memakai template DOCX/XLSX sesuai document type.

Kontrak UX/operasional:

- upload invalid tidak mengganti template aktif;
- replacement baru dianggap aktif setelah storage/database replacement berhasil;
- preview/generate/download membaca template dari storage canonical yang sama;
- output yang lolos regression parser belum otomatis berarti visual/print fidelity sudah verified.

Official-template visual QA dan hasil cetak tetap dibedakan dari functional generator PASS.

---

## 25. Validasi manusiawi

Pesan validasi harus mengarahkan user ke workspace pemilik masalah, misalnya:

- `Simpan seluruh Uraian Barang/Jasa terlebih dahulu.` → Detail Transaksi.
- `Penerima Utama belum dipilih.` → Paket SPJ.
- `Tanggal Pesanan tidak boleh setelah Tanggal Transaksi.` → Paket SPJ.
- `Jumlah peserta harus sama dengan total porsi.` → Paket SPJ.
- reconciliation source → arahkan ke review/reconciliation, bukan menyuruh operator mengubah source.

Pesan tidak boleh menyarankan operator mengarang data yang tidak tersedia.

---

## 26. Checklist sebelum menambah fitur atau field

1. Apakah ini source atau operator overlay?
2. Siapa pemilik field: Detail Transaksi atau Paket?
3. Apakah field sudah editable di tempat lain?
4. Apakah perubahan scoped ke `School + Fiscal Year + Fund Source`?
5. Role mana yang boleh membaca/mengubah?
6. Apakah NUMBERED/FINAL terdampak?
7. Apakah mutation harus audited?
8. Apakah perubahan memengaruhi numbering?
9. Apakah perubahan berisiko menimpa safe-sync overlay?
10. Apakah identity pegawai dapat menjadi ambigu?
11. Apakah SiPLah sedang diperlakukan salah sebagai kategori?
12. Apakah real-data perlu diaudit read-only dulu?
13. Apakah mutation real-data dilakukan pada isolated copy?
14. Apakah perubahan membutuhkan browser/document visual QA?
15. Apakah backend validation tetap authoritative?

---

## 27. Batas interpretasi dokumen ini

`USER_SCENARIOS.md` menjelaskan bagaimana pengguna seharusnya menjalankan workflow yang sudah dikontrak domain.

Dokumen ini tidak boleh dipakai untuk menyimpulkan:

- bahwa sebuah fitur sudah production-verified;
- bahwa browser/mobile QA sudah PASS;
- bahwa official-template output sudah visually verified;
- bahwa real-data mutation aman tanpa audit;
- bahwa pekerjaan roadmap sudah selesai.

Untuk status tersebut gunakan `CURRENT_PROGRESS.md` dan `DEVELOPMENT_ROADMAP.md`.
