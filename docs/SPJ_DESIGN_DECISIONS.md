# SPJ BOSP Web — Keputusan Desain & Aturan Bisnis

Dokumen ini adalah catatan permanen keputusan rancangan aplikasi SPJ BOSP Web.

Tujuannya sederhana: ketika pengembangan dilanjutkan lagi, Codex atau developer lain tidak perlu menganalisis ulang dari nol. Baca dokumen ini terlebih dahulu sebelum mengubah schema, relasi, sinkronisasi ARKAS, modul transaksi, modul SPJ, penomoran, atau aturan penguncian dokumen.

Terakhir diperbarui: 2026-08-31

---

## 1. Prinsip utama aplikasi

SPJ BOSP Web adalah aplikasi penyusunan dokumen SPJ berdasarkan data transaksi dari ARKAS.

Prinsip rancangan yang harus dijaga:

1. Data sumber ARKAS dianggap sebagai data hasil sinkronisasi.
2. Database tenant/sekolah boleh dikosongkan dan dibangun ulang mengikuti migrasi baru pada fase pengembangan ini.
3. Setelah aplikasi dipakai sungguhan, sinkronisasi ARKAS tidak boleh merusak data manual/operator.
4. Data manual SPJ harus dipisahkan secara jelas dari data sumber ARKAS.
5. Sumber dana harus dikunci berdasarkan tahun anggaran aktif.
6. Relasi dokumen SPJ harus mengikuti realitas lapangan, bukan sekadar urutan transaksi.
7. Penomoran dokumen harus otomatis, tetapi baru dilakukan saat dokumen/transaksi sudah siap.
8. Dokumen yang sudah bernomor atau final tidak boleh berubah diam-diam.
9. Setiap tabel harus memiliki `created_at` dan `updated_at`, kecuali tabel sistem Laravel yang memang punya pola sendiri.
10. Kolom `manual_description` dihapus. Uraian manual/operator menggunakan `payment_description` atau field detail kategori SPJ yang lebih spesifik.

---

## 2. Konsep multi-database / tenant

Aplikasi memakai pola multi-database:

### 2.1 Database utama

Database utama menyimpan data global aplikasi:

- user/login;
- daftar sekolah;
- konfigurasi database sekolah;
- konfigurasi sumber ARKAS;
- data setup aplikasi;
- metadata yang tidak spesifik ke transaksi sekolah.

Administrator boleh menambahkan sekolah mana pun karena setiap sekolah memakai database tenant yang berbeda.

Implikasinya:

- akses sekolah tidak boleh hanya bergantung pada satu database global;
- ketika sekolah dipilih, koneksi `school` harus diarahkan ke database tenant sekolah tersebut;
- setiap query transaksi/SPJ harus berada dalam konteks sekolah dan tahun anggaran aktif;
- operator tidak boleh melihat data sekolah lain kecuali aturan role memang mengizinkan.

### 2.2 Database sekolah / tenant

Database tenant menyimpan data operasional sekolah:

- tahun anggaran;
- sumber dana;
- data RKAS hasil sync;
- data BKU hasil sync;
- transaksi SPJ;
- item transaksi;
- data manual SPJ;
- paket SPJ;
- nomor dokumen;
- work order;
- pekerja;
- peserta;
- perjalanan dinas;
- audit operasional;
- riwayat sinkronisasi.

Karena database tenant terpisah, pengosongan data tenant untuk migrasi baru hanya boleh menyasar database tenant, bukan database utama.

---

## 3. Sumber data dan strategi sinkronisasi ARKAS

### 3.1 Sumber data utama

Sumber data awal berasal dari hasil sinkronisasi ARKAS:

- RKAS;
- BKU;
- referensi kegiatan;
- referensi rekening;
- sumber dana;
- transaksi belanja;
- pajak;
- metadata SiPLah/non-tunai apabila tersedia.

Data ARKAS disimpan sebagai data sumber, bukan sebagai data manual operator.

### 3.2 Tabel hasil sync

Tabel sumber ARKAS perlu diperlakukan sebagai cache/snapshot dari ARKAS:

- `arkas_rkas_items`
- `arkas_bku_rows`
- tabel referensi ARKAS lain jika ada

Data ini boleh diperbarui penuh oleh proses sync.

Namun transaksi dan detail SPJ manual tidak boleh asal dihapus hanya karena data ARKAS berubah.

### 3.3 Strategi sinkronisasi aman

Sinkronisasi harus memakai strategi tidak merusak data manual:

1. Data RKAS/BKU mentah boleh di-refresh dari ARKAS.
2. Transaksi hasil ARKAS dicocokkan dengan kunci sumber.
3. Jika transaksi masih ditemukan di ARKAS:
   - field sumber boleh diperbarui;
   - field manual/operator harus dipertahankan;
   - paket SPJ, nomor dokumen, work order, pekerja, peserta, perjalanan, dan detail manual tidak boleh hilang.
4. Jika transaksi tidak muncul sementara pada sync berikutnya:
   - jangan langsung hapus transaksi;
   - tandai sebagai `SOURCE_MISSING`;
   - isi `source_missing_since`;
   - pertahankan data manual dan paket dokumen.
5. Jika transaksi muncul kembali:
   - ubah kembali menjadi `ACTIVE`;
   - kosongkan `source_missing_since`;
   - rekonsiliasi perubahan bila nilai sumber berubah.
6. Jika data sumber berubah setelah SPJ disiapkan/bernomor:
   - jangan ubah dokumen final otomatis;
   - tandai `requires_reconciliation`;
   - minta operator/admin memilih tindakan.

### 3.4 Kunci pencocokan transaksi

Pencocokan transaksi sebaiknya bertingkat:

1. `source_key`, bila sudah tersedia.
2. Kombinasi ID item BKU sumber.
3. `id_kas_umum`.
4. `no_bukti`.

Catatan:

- `no_bukti` bisa berubah atau tidak selalu cukup unik.
- Transaksi yang terdiri dari beberapa baris BKU harus punya kunci gabungan yang stabil.
- Pajak biasanya terhubung ke parent BKU, bukan menjadi transaksi belanja mandiri.

### 3.5 Field sumber vs field manual

Field yang berasal dari ARKAS:

- `no_bukti`;
- `id_kas_umum`;
- `transaction_date`;
- `description`;
- `activity_code`;
- `activity_name`;
- `account_code`;
- `account_name`;
- `recipient_name` dari ARKAS;
- `gross_amount`;
- `tax_total`;
- `net_amount`;
- rincian pajak;
- `is_siplah`;
- payload sumber.

Field manual/operator:

- `payment_description`;
- `spj_category`, jika operator mengubah atau mengunci kategori;
- `payment_method`, jika operator mengubah;
- `payment_reference`;
- `receipt_recipient_name`;
- data barang;
- data pekerja;
- data peserta;
- data perjalanan dinas;
- data work order;
- nomor dokumen;
- status dokumen;
- catatan rekonsiliasi.

Aturan penting:

- Jangan gunakan lagi `manual_description`.
- `manual_description` dan `payment_description` dianggap tumpang tindih.
- Field yang dipakai adalah `payment_description`.
- Uraian barang/jasa yang lebih detail harus masuk ke tabel detail terkait, misalnya `transaction_items.item_description`, `spj_goods.description`, `spj_work_orders.work_description`, atau tabel kategori lain.
- Nama penerima kuitansi tidak boleh menumpang ke `recipient_name` karena `recipient_name` adalah data BKU/ARKAS.
- Field manual penerima kuitansi adalah `receipt_recipient_name`.
- Jika `receipt_recipient_name` kosong, aplikasi boleh fallback ke `spj_recipient_name`, lalu `signatory_name`, lalu `recipient_name`.

---

## 4. Aturan sumber dana

Sumber dana harus dikunci.

Makna “dikunci”:

1. User memilih sekolah.
2. User memilih tahun anggaran.
3. Tahun anggaran memiliki sumber dana aktif, misalnya BOS Reguler.
4. Semua query transaksi, RKAS, BKU, SPJ, paket dokumen, dan laporan harus memakai `active_fiscal_year_id` dan `active_fund_source_id`.
5. Sinkronisasi ARKAS hanya mengambil data sesuai sumber dana aktif.
6. Transaksi dari sumber dana lain tidak boleh tercampur dalam daftar transaksi atau penomoran.
7. Penomoran dokumen juga harus per sumber dana/periode, bukan global seluruh sekolah.

Risiko jika tidak dikunci:

- transaksi BOS Reguler dan sumber dana lain tercampur;
- nomor dokumen tidak konsisten;
- laporan triwulan salah;
- SPJ bisa memakai pagu/realisasi dari sumber dana yang keliru.

---

## 5. Modul transaksi

Modul transaksi adalah pondasi utama migrasi ke TALL stack.

Status terakhir:

- halaman `/transaksi` sudah mulai memakai Livewire;
- komponen utama: `App\Livewire\TransactionsTable`;
- view utama: `resources/views/livewire/transactions-table.blade.php`;
- halaman wrapper: `resources/views/transactions/index.blade.php`;
- controller index transaksi hanya merender view;
- modal edit data SPJ memakai Alpine untuk buka/tutup modal;
- penyimpanan data SPJ masih memakai form `PUT` ke route controller.

### 5.1 Tujuan modul transaksi

Modul transaksi harus menjadi tempat operator:

- melihat hasil transaksi ARKAS;
- mencari/filter transaksi;
- melihat status transaksi;
- melihat apakah data sumber aktif/hilang;
- mengisi kategori SPJ;
- mengisi uraian pembayaran/SPJ;
- memilih metode pembayaran;
- mengisi referensi pembayaran;
- masuk ke detail transaksi;
- menyiapkan data sesuai kategori.

### 5.2 Urutan tabel transaksi

Keputusan terakhir:

Daftar transaksi diurutkan berdasarkan:

1. `status`;
2. `id` ascending.

Tujuannya agar tampilan stabil dan transaksi dalam status yang sama mengikuti urutan ID kecil ke besar.

Jika nanti dibutuhkan urutan dokumen final, jangan memaksa urutan transaksi mentah menjadi urutan dokumen. Gunakan sistem penomoran dokumen tersendiri.

### 5.3 Modal edit data SPJ

Masalah yang pernah terjadi:

- setelah modal muncul, halaman berubah ke `/transaksi/{id}/uraian-manual`;
- route tersebut hanya menerima `PUT`;
- ketika dibuka sebagai `GET`, muncul 404;
- setelah klik luar modal, 404 hilang dan modal masih terlihat.

Penyebab:

- tombol modal masih memiliki kombinasi pemicu lama yang membuat browser/Livewire mengarah ke route simpan;
- modal terbuka, tetapi URL ikut berubah ke endpoint update.

Keputusan perbaikan:

- tombol “Ubah data SPJ” tidak boleh memakai `wire:click="edit(...)"` bila modal sudah diisi dari atribut `data-*`;
- tombol hanya membuka modal via Alpine;
- form di dalam modal memakai `method="POST"` + `@method('PUT')`;
- action form diisi dari `data-action`;
- endpoint `/transaksi/{id}/uraian-manual` tetap route update, bukan halaman.

### 5.4 Metode pembayaran

Metode pembayaran dikunci menjadi tiga nilai canonical:

| Nilai database | Label UI |
| --- | --- |
| `transfer_bank` | Transfer Bank (CMS / Non Tunai) |
| `siplah` | SiPLah Kemdikbud |
| `tunai` | Tunai Kas BOS |

Aturan default:

1. Jika `IS_SIPLAH` bernilai benar, metode pembayaran adalah `siplah`.
2. Jika `NO_BUKTI` atau `KODE_BKU` menunjukkan non-tunai, metode pembayaran adalah `transfer_bank`.
3. Jika `KODE_BKU` diawali `bnu`, metode pembayaran adalah `transfer_bank`.
4. Selain itu, metode pembayaran adalah `tunai`.

Tanda non-tunai yang dikenali:

- `non_tunai`;
- `non tunai`;
- awalan kode `bnu`;
- teks transfer/CMS pada data lama.

Catatan:

- Form baru harus memakai select, bukan input bebas.
- Backend tetap boleh menormalisasi nilai lama agar data historis tidak rusak.
- Tampilan UI sebaiknya menampilkan label manusiawi, bukan mentah `transfer_bank`.

---

## 6. Kategori SPJ dan relasi detail

Kategori SPJ utama:

- `BARANG`;
- `KONSUMSI`;
- `PEMELIHARAAN`;
- `JASA_LAINNYA`;
- `SPPD`;
- `HONOR_PEGAWAI`.

Alias lama yang perlu dinormalisasi:

| Alias lama | Kategori baru |
| --- | --- |
| `BELANJA_MODAL` | `BARANG` |
| `PERJALANAN_DINAS` | `SPPD` |
| `JASA_HONORARIUM` | `HONOR_PEGAWAI` |
| `UPAH` | `PEMELIHARAAN` |
| `LAINNYA` | `JASA_LAINNYA` |

### 6.1 Barang

Relasi umum:

- 1 transaksi memiliki banyak item transaksi;
- item transaksi boleh disalin/diterjemahkan menjadi detail barang SPJ;
- pemesanan, penerimaan, pemeriksaan, dan pembayaran tidak harus terjadi dalam urutan yang sama.

Dokumen terkait barang dapat memiliki nomor masing-masing:

- surat pesanan;
- BAP/pemeriksaan;
- BAST/penerimaan;
- kuitansi/pembayaran;
- dokumen SPJ final.

### 6.2 Konsumsi

Konsumsi dapat membutuhkan:

- data peserta;
- jumlah porsi;
- kegiatan;
- lokasi;
- tanggal;
- bukti pembayaran.

Jika konsumsi dibeli sebagai barang/jasa, detail barang tetap bisa dipakai sebagai rincian belanja, sementara peserta menjelaskan dasar konsumsi.

### 6.3 Pemeliharaan

Keputusan relasi:

1 transaksi pemeliharaan memiliki 1 work order.

1 work order memiliki banyak pekerja.

Struktur konseptual:

```text
transactions
└── spj_work_orders
    └── spj_workers[]
```

Aturan:

- `spj_work_orders` menyimpan pekerjaan utama;
- `spj_workers` menyimpan daftar pekerja;
- salah satu pekerja dapat ditandai sebagai penerima kuitansi jika diperlukan;
- jika transaksi sudah punya work order, sinkronisasi ARKAS tidak boleh sembarang mengganti penerima manual dengan nama toko/vendor ARKAS.

### 6.4 SPPD

Keputusan relasi:

1 pembayaran SPPD boleh untuk lebih dari 1 orang.

Artinya:

```text
transactions
└── spj_travels[]
```

Aturan:

- 1 transaksi pembayaran dapat memiliki banyak pelaksana perjalanan;
- setiap pelaksana dapat punya tujuan, tanggal berangkat, tanggal pulang, transport, dan nominal;
- total nominal perjalanan tidak boleh melebihi nilai transaksi;
- validasi harus memastikan data traveler minimal lengkap sebelum dokumen siap.

### 6.5 Honor pegawai

Honor mirip pola pekerja:

- banyak penerima;
- jabatan/peran;
- nominal;
- periode/kegiatan.

Jika schema memisahkan honor dari pekerja, gunakan tabel khusus honor. Jika belum, `spj_workers` atau `spj_honors` harus dipakai konsisten, jangan dicampur tanpa aturan.

---

## 7. Status transaksi, paket, dan dokumen

Status perlu jelas karena berpengaruh pada locking, penomoran, dan sinkronisasi.

### 7.1 Status sumber sync

Untuk transaksi/item dari ARKAS:

| Status | Arti |
| --- | --- |
| `ACTIVE` | Masih ditemukan di sync ARKAS terakhir |
| `SOURCE_MISSING` | Tidak muncul pada sync terakhir, tetapi data manual dipertahankan |

Field pendukung:

- `source_key`;
- `source_hash`;
- `source_status`;
- `last_seen_sync_run_id`;
- `source_missing_since`;
- `requires_reconciliation`.

### 7.2 Status transaksi SPJ

Rekomendasi status transaksi:

| Status | Arti |
| --- | --- |
| `DRAFT` | Baru hasil sync atau belum lengkap |
| `READY` / `SIAP` | Data SPJ lengkap dan siap diproses |
| `NUMBERED` / `BERNOMOR` | Sudah masuk proses penomoran dokumen |
| `FINAL` | Sudah final/terkunci |
| `NEEDS_RECONCILIATION` | Data sumber berubah/hilang dan perlu ditinjau |

Saat ini codebase masih memakai campuran status seperti `DRAFT`, `SIAP`, dan status paket. Jika nanti dirapikan, pilih satu set canonical dan normalisasi alias lama.

### 7.3 Status paket SPJ

Rekomendasi status paket:

| Status | Arti |
| --- | --- |
| `DRAFT` | Paket dibuat, masih bisa diedit |
| `READY` / `DISIAPKAN` | Validasi lulus, siap diberi nomor/dicetak |
| `NUMBERED` | Sudah diberi nomor dokumen |
| `PRINTED` / `DICETAK` | Pernah dicetak/generate |
| `ARCHIVED` / `DIARSIPKAN` | Arsip final |

### 7.4 Aturan penguncian dokumen

Dokumen masih boleh diedit jika:

- status paket masih `DRAFT`;
- belum memiliki nomor dokumen;
- belum final;
- tidak sedang dalam status rekonsiliasi wajib;
- user punya hak akses.

Dokumen harus dikunci jika:

- sudah memiliki nomor dokumen final;
- status `FINAL`, `PRINTED`, atau `ARCHIVED`;
- masuk laporan final triwulan;
- sudah masuk arsip SPJ;
- perubahan akan mengubah nilai uang setelah dokumen bernomor.

Jika data ARKAS berubah setelah dokumen terkunci:

- jangan ubah dokumen final otomatis;
- tandai butuh rekonsiliasi;
- tampilkan perbedaan lama vs baru;
- sediakan aksi admin: revisi, buat versi baru, atau pertahankan arsip.

---

## 8. Penomoran dokumen

Penomoran dokumen harus otomatis, tetapi tidak boleh bergantung pada urutan transaksi dibuat/sync.

Alasan:

- barang yang dipesan dulu belum tentu diterima dulu;
- barang yang diterima dulu belum tentu dibayar dulu;
- pembayaran bisa terjadi belakangan;
- SPJ biasanya disiapkan per triwulan;
- operator sering melengkapi data tidak berurutan.

### 8.1 Prinsip penomoran

1. Setiap jenis dokumen memiliki nomor sendiri.
2. Nomor dokumen tidak harus sama dengan nomor transaksi.
3. Nomor dokumen tidak boleh diberikan terlalu awal.
4. Nomor diberikan saat dokumen sudah `READY`.
5. Penomoran dapat dilakukan massal setelah semua transaksi triwulan siap.
6. Setelah nomor diberikan, field penting harus dikunci.

### 8.2 Jenis dokumen yang dapat punya sequence sendiri

Contoh entitas penomoran:

- SPJ final;
- kuitansi;
- surat pesanan;
- BAP/pemeriksaan;
- BAST/penerimaan;
- SPK/work order;
- surat tugas;
- SPPD;
- laporan penerimaan;
- daftar hadir;
- berita acara lain.

### 8.3 Basis tanggal penomoran

Nomor tiap dokumen sebaiknya mengikuti tanggal kejadian dokumen, bukan ID transaksi.

Contoh:

- surat pesanan memakai `order_date`;
- BAP memakai `bap_date`;
- BAST memakai `bast_date`;
- kuitansi memakai tanggal pembayaran/transaksi;
- SPPD memakai tanggal perjalanan atau tanggal dokumen SPPD;
- SPK/work order memakai `spk_date`;
- SPJ final memakai tanggal penyusunan/akhir periode.

Jika tanggal kosong:

- jangan beri nomor otomatis;
- tampilkan sebagai data belum lengkap.

### 8.4 Penomoran massal per triwulan

Keputusan:

Sebaiknya ada fitur update/assign penomoran massal untuk semua entitas dokumen setelah transaksi triwulan berstatus `READY`.

Alur yang disarankan:

1. Operator menyelesaikan detail transaksi.
2. Sistem validasi kelengkapan.
3. Transaksi/paket berubah menjadi `READY`.
4. Admin/operator membuka halaman penomoran triwulan.
5. Sistem menampilkan simulasi nomor:
   - nomor lama;
   - nomor baru;
   - jenis dokumen;
   - tanggal dasar;
   - transaksi terkait.
6. User menekan “Tetapkan Nomor”.
7. Sistem menyimpan nomor dalam satu transaksi database.
8. Dokumen yang sudah bernomor dikunci.

### 8.5 Risiko renumbering

Renumbering berbahaya jika dokumen sudah dicetak atau dilaporkan.

Aturan:

- renumbering hanya boleh untuk dokumen belum final;
- jika sudah final, buat revisi/versi baru;
- simpan audit log setiap perubahan nomor;
- jangan mengubah nomor diam-diam setelah laporan triwulan dipakai.

---

## 9. Menjaga data manual saat ARKAS berubah

Pertanyaan penting dari user:

Bagaimana mempertahankan data SPJ manual ketika transaksi hasil sinkronisasi ARKAS berubah atau tidak muncul sementara?

Jawaban rancangan:

Gunakan pola “source data + manual overlay”.

### 9.1 Source data

Data dari ARKAS masuk ke field sumber.

Data ini boleh berubah setiap sync.

Contoh:

- tanggal transaksi dari ARKAS;
- uraian ARKAS;
- nilai bruto;
- pajak;
- penerima sumber;
- rincian BKU.

### 9.2 Manual overlay

Data manual disimpan terpisah.

Contoh:

- kategori SPJ yang dipilih;
- uraian pembayaran/SPJ;
- metode pembayaran;
- referensi pembayaran;
- data barang;
- data perjalanan;
- pekerja;
- work order;
- nomor dokumen;
- status dokumen.

### 9.3 Reconciliation

Jika data sumber berubah:

- hitung `source_hash`;
- bandingkan dengan hash sebelumnya;
- jika berbeda dan dokumen belum final, boleh update data sumber sambil mempertahankan manual;
- jika berbeda dan dokumen sudah disiapkan/bernomor, tandai `requires_reconciliation`;
- tampilkan peringatan ke user.

### 9.4 Soft missing

Jika transaksi tidak muncul:

- tandai `SOURCE_MISSING`;
- jangan hapus;
- jangan hapus detail manual;
- jangan hapus nomor dokumen;
- jangan hapus paket;
- tampilkan label “Tidak muncul pada sync terakhir”.

Jika muncul lagi:

- status kembali `ACTIVE`;
- `source_missing_since` dikosongkan;
- manual overlay tetap ada.

---

## 10. Timestamp dan audit

Keputusan:

Semua tabel aplikasi perlu memiliki:

- `created_at`;
- `updated_at`.

Tujuannya:

- melacak kapan data dibuat;
- melacak kapan data berubah;
- membantu audit;
- membantu sinkronisasi;
- membantu debugging perubahan data.

Untuk aktivitas penting, timestamp saja tidak cukup. Gunakan audit log.

Aktivitas yang sebaiknya masuk audit:

- sinkronisasi ARKAS;
- transaksi berubah status;
- kategori SPJ diubah;
- data manual SPJ diubah;
- nomor dokumen diberikan;
- nomor dokumen diubah;
- dokumen difinalisasi;
- dokumen dibuka kembali;
- data sumber ARKAS berubah setelah dokumen siap/final;
- restore database;
- migrasi tenant;
- perubahan sumber dana.

---

## 11. Strategi migrasi schema

Keputusan dari percakapan:

- database tenant boleh dikosongkan;
- migrasi baru dipakai sebagai sumber schema;
- schema harus disamakan dengan dummy;
- migrasi perlu dipisahkan agar mudah dirawat.

Rekomendasi pemisahan migrasi tenant:

1. referensi dasar:
   - `fund_sources`;
   - `fiscal_years`;
   - rekening;
   - kegiatan;
2. ARKAS raw/sync:
   - `arkas_rkas_items`;
   - `arkas_bku_rows`;
   - `sync_runs`;
3. transaksi:
   - `transactions`;
   - `transaction_items`;
4. detail SPJ:
   - barang;
   - konsumsi/peserta;
   - perjalanan;
   - honor;
   - pemeliharaan;
   - work order;
5. dokumen:
   - `spj_packages`;
   - document numbers;
   - template dokumen;
6. audit:
   - operational audit logs;
   - reconciliation logs.

Aturan:

- jangan mencampur terlalu banyak perubahan besar dalam satu file migrasi;
- setiap tabel harus jelas pemilik datanya: ARKAS, manual, dokumen, audit;
- index harus mengikuti pola query utama;
- foreign key harus menjaga integritas tanpa menghapus data manual secara tidak sengaja.

---

## 12. TALL stack

Project ini sudah mengarah ke TALL stack:

- Tailwind CSS;
- Alpine.js;
- Laravel;
- Livewire.

Keputusan:

Migrasi ke TALL dilakukan modular, mulai dari `/transaksi`.

Alasan:

- modul transaksi adalah pusat workflow SPJ;
- masalah modal dan filter lebih cocok ditangani Livewire/Alpine;
- migrasi bertahap lebih aman daripada rewrite total;
- controller lama masih bisa dipertahankan untuk penyimpanan form yang sudah stabil.

Strategi migrasi:

1. Mulai dari `/transaksi`.
2. Setelah stabil, lanjut ke detail transaksi.
3. Lanjut ke modul SPJ.
4. Lanjut ke penomoran dokumen.
5. Lanjut ke laporan.

Aturan TALL:

- Livewire untuk state server-side seperti filter, pagination, validasi, save;
- Alpine untuk interaksi UI ringan seperti buka/tutup modal;
- jangan menjalankan Alpine dua kali;
- jangan mencampur modal Alpine dengan redirect/submit yang tidak disengaja;
- form destructive/final harus tetap punya validasi backend.

---

## 13. Risiko terbesar yang harus dijaga

Risiko paling penting:

1. Data manual hilang saat sync ulang ARKAS.
2. Transaksi yang hilang sementara dari ARKAS dianggap dihapus permanen.
3. Sumber dana tercampur.
4. Nomor dokumen berubah setelah dicetak.
5. Urutan transaksi disalahgunakan sebagai urutan dokumen.
6. Satu pembayaran SPPD hanya dianggap satu orang.
7. Pemeliharaan tidak punya struktur work order yang jelas.
8. Metode pembayaran bebas teks sehingga laporan tidak konsisten.
9. Modal/form mengarah ke route update sebagai halaman dan menghasilkan 404.
10. Test menghapus database utama karena konfigurasi testing salah.
11. Field lama seperti `manual_description` muncul lagi.
12. Schema migrasi tidak sinkron dengan dummy/realitas data ARKAS.
13. Dokumen final berubah karena sync, edit massal, atau renumbering.

---

## 14. Checklist sebelum melanjutkan pengembangan

Sebelum mengerjakan fitur baru, baca dan cek:

1. Apakah fitur menyentuh data sumber ARKAS?
2. Apakah fitur menyentuh data manual/operator?
3. Apakah fitur bisa merusak dokumen bernomor/final?
4. Apakah query sudah terkunci sekolah, tahun, dan sumber dana aktif?
5. Apakah transaksi `SOURCE_MISSING` ditangani?
6. Apakah perubahan butuh audit log?
7. Apakah status dan locking sudah jelas?
8. Apakah penomoran mengikuti tanggal dokumen, bukan sekadar ID?
9. Apakah metode pembayaran memakai nilai canonical?
10. Apakah test memakai database testing, bukan database utama?

---

## 15. Cara menggunakan dokumen ini di sesi berikutnya

Jika butuh melanjutkan pengembangan bersama Codex, gunakan instruksi:

```text
Baca dulu docs/SPJ_DESIGN_DECISIONS.md dan docs/CURRENT_PROGRESS.md, lalu lanjutkan dari aturan itu.
```

Jika ada keputusan baru, update dokumen ini sebelum atau sesudah implementasi.

Dokumen ini adalah sumber keputusan desain yang lebih baru daripada `docs/ARCHITECTURE_COMPLETE.md` jika ada konflik.

Dokumen pendamping untuk alur kerja pengguna:

```text
docs/USER_SCENARIOS.md
```

Gunakan dokumen tersebut saat merancang UI, role, validasi kelengkapan, dan urutan kerja operator.
