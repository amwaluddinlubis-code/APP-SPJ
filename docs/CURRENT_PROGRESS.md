# SPJ BOSP Web — Catatan Progres Terakhir

## Pembaruan katalog penanda template dokumen

- Halaman Template Dokumen menampilkan seluruh penanda yang didukung dan mengelompokkannya menurut domain data.
- Katalog UI bersumber dari `SpjTemplateService::placeholderGroups()` agar tidak berbeda dari kemampuan generator.
- Penanda `REFERENSI_BAYAR` kini terhubung ke referensi pembayaran transaksi sehingga `CARA_BAYAR_REFERENSI` dapat diproses dengan aman.
- Referensi lengkap dan aturan baris berulang tersedia di `docs/DOCUMENT_TEMPLATE_PLACEHOLDERS.md`.

Dokumen ini mencatat keadaan terakhir project agar sesi pengembangan berikutnya tidak mulai dari nol.

Terakhir diperbarui: 2026-09-02

---

## 1. Konteks project

Path project:

```text
D:\lrvProject\spj-bosp-web
```

Jenis project:

- Laravel;
- arah stack: TALL;
- Livewire mulai diterapkan modular;
- fokus saat ini: modul `/transaksi`, schema tenant, sinkronisasi ARKAS aman, SPJ, locking, dan penomoran dokumen.

Database:

- database utama untuk user/sekolah/konfigurasi;
- database tenant/sekolah untuk data operasional;
- pada fase pengembangan, database tenant boleh dikosongkan dan dibangun ulang mengikuti migrasi baru.

---

## 2. Keputusan bisnis yang sudah disepakati

Keputusan penting:

1. Sumber data utama berasal dari hasil sinkronisasi ARKAS.
2. Data tenant boleh dikosongkan untuk mengikuti migrasi baru.
3. Sinkronisasi ARKAS tidak boleh merusak data manual/operator.
4. `manual_description` dihapus.
5. Gunakan `payment_description` untuk uraian pembayaran/SPJ.
6. Semua tabel aplikasi perlu `created_at` dan `updated_at`.
7. Sumber dana harus dikunci berdasarkan konteks tahun anggaran aktif.
8. Administrator boleh menambahkan sekolah mana pun karena database tenant terpisah.
9. 1 pembayaran SPPD boleh berisi lebih dari 1 orang.
10. 1 transaksi pemeliharaan memiliki 1 work order.
11. 1 work order memiliki banyak pekerja.
12. Penomoran dokumen harus otomatis.
13. Setiap jenis dokumen punya nomor sendiri.
14. Barang yang dipesan dulu belum tentu diterima/dibayar dulu, jadi nomor dokumen tidak boleh bergantung pada urutan input transaksi.
15. Penomoran massal per triwulan setelah status transaksi/paket `READY` adalah strategi yang disarankan.
16. Dokumen yang sudah bernomor/final harus dikunci.

---

## 3. Pekerjaan yang sudah dilakukan

### 3.1 Proteksi testing database

Masalah sebelumnya:

- test sempat berisiko memakai database utama;
- database utama SQLite pernah kosong akibat test.

Perbaikan:

- `phpunit.xml` diarahkan ke database testing:

```text
database/testing.sqlite
```

Hasil:

- test tidak lagi mengosongkan database utama.

Catatan:

- setelah insiden tersebut, data minimal central sempat direstorasi:
  - user admin;
  - beberapa sekolah;
  - record database sekolah.

### 3.2 Alur pilih sekolah dan pilih tahun

Masalah sebelumnya:

- user tertahan di `/pilih-tahun`;
- sinkronisasi tidak bisa lanjut;
- alur sekolah dan tahun perlu disederhanakan.

Keputusan:

- pemilihan sekolah dan tahun dipisahkan;
- memilih sekolah harus membawa user ke pemilihan tahun;
- sinkronisasi ARKAS dilakukan setelah konteks sekolah/tahun benar.

Test yang ada:

- `Tests\Feature\SchoolYearSelectionFlowTest`.

### 3.3 Migrasi awal modul transaksi ke TALL

Perubahan:

- dibuat komponen Livewire:

```text
app/Livewire/TransactionsTable.php
```

- dibuat view Livewire:

```text
resources/views/livewire/transactions-table.blade.php
```

- halaman transaksi utama menjadi wrapper:

```text
resources/views/transactions/index.blade.php
```

- `TransactionController@index` hanya merender view transaksi.

Status:

- modul `/transaksi` sudah mulai TALL secara modular;
- belum semua modul SPJ dimigrasikan ke Livewire.

### 3.4 Perbaikan Alpine/Livewire

Masalah sebelumnya:

- Alpine pernah start dua kali;
- ada error terkait `$persist`;
- modal/komponen rawan bentrok dengan Livewire.

Perbaikan:

- `resources/js/app.js` dibuat agar Alpine hanya dijalankan jika `window.Alpine` belum ada.

Status:

- build frontend sudah pernah berhasil.

### 3.5 Modal ubah data SPJ

Masalah:

- tombol “Ubah data SPJ” membuka modal, tetapi halaman ikut berpindah ke:

```text
/transaksi/{id}/uraian-manual
```

- route tersebut adalah endpoint update `PUT`, bukan halaman `GET`;
- akibatnya muncul 404.

Perbaikan:

- tombol modal tidak lagi memakai pemicu Livewire lama `wire:click="edit(...)"`;
- tombol memakai Alpine dan atribut `data-*`;
- form modal tetap submit ke route update dengan method spoofing `PUT`;
- modal tidak lagi mengarahkan browser ke endpoint update sebagai halaman.

File terkait:

```text
resources/views/livewire/transactions-table.blade.php
app/Livewire/TransactionsTable.php
app/Http/Controllers/TransactionController.php
```

### 3.6 Metode pembayaran menjadi select

Permintaan:

Ganti input metode pembayaran menjadi pilihan:

```php
[
    "transfer_bank" => "Transfer Bank (CMS / Non Tunai)",
    "siplah" => "SiPLah Kemdikbud",
    "tunai" => "Tunai Kas BOS",
]
```

Status:

- sudah diterapkan pada modal transaksi;
- sudah diterapkan pada halaman detail transaksi;
- sudah diterapkan pada halaman SPJ yang masih memakai form lama;
- backend memvalidasi nilai canonical.

File terkait:

```text
resources/views/livewire/transactions-table.blade.php
resources/views/transactions/show.blade.php
resources/views/spj/index.blade.php
app/Http/Controllers/TransactionController.php
app/Livewire/TransactionsTable.php
```

### 3.7 Logika default metode pembayaran

Aturan:

- jika `IS_SIPLAH` benar → `siplah`;
- jika `NO_BUKTI` atau `KODE_BKU` mengandung non-tunai → `transfer_bank`;
- jika `KODE_BKU` diawali `bnu` → `transfer_bank`;
- selain itu → `tunai`.

Status:

- diterapkan pada tampilan modal transaksi;
- diterapkan saat simpan manual;
- diterapkan pada importer sync lama;
- diterapkan pada importer sync baru.

File terkait:

```text
app/Services/ArkasSynchronizationService.php
app/Services/ArkasSynchronizationServiceV2.php
app/Http/Controllers/TransactionController.php
app/Livewire/TransactionsTable.php
```

### 3.8 Urutan tabel transaksi

Permintaan terakhir:

Setelah order by status, tambahkan `order_by id asc` pada tabel transaksi.

Status:

- query transaksi sekarang memakai:

```php
->orderBy('status')
->orderBy('id')
```

File:

```text
app/Livewire/TransactionsTable.php
```

### 3.9 Form manual detail transaksi sesuai kategori SPJ

Permintaan:

Rapikan form isian manual pada halaman detail transaksi agar field yang muncul sesuai kategori SPJ dan tata letaknya lebih compact.

Status:

- halaman detail transaksi sudah memakai pola isian umum + isian khusus kategori;
- hidden input `spj_category` ganda dihapus agar pilihan kategori dari select tidak tertimpa;
- isian umum berlaku untuk semua kategori:
  - uraian ARKAS;
  - uraian dokumen/pembayaran;
  - metode pembayaran;
  - referensi pembayaran;
  - penandatangan;
  - jabatan/peran;
- kategori `BARANG` dan `KONSUMSI` menampilkan data pembelian:
  - invoice/faktur;
  - pesanan;
  - BAP;
  - BAST;
- kategori `KONSUMSI` menampilkan data acara dan peserta;
- kategori `PEMELIHARAAN` menampilkan work order dan daftar pekerja;
- kategori `SPPD` menampilkan banyak pelaksana perjalanan dalam satu transaksi;
- kategori `HONOR_PEGAWAI` menampilkan banyak penerima honor;
- kategori `JASA_LAINNYA` menampilkan isian jasa ringkas;
- layout input dibuat lebih compact dengan grid kecil, padding lebih rendah, dan label pendek.

Perubahan backend:

- route `spj.prepare` sekarang menerima validasi `workers[]`;
- metode pembayaran di `spj.prepare` dikunci ke nilai canonical;
- `SpjTransactionDetailsService` sekarang menyimpan daftar pekerja pemeliharaan dari form detail transaksi ke relasi work order.

File terkait:

```text
resources/views/transactions/show.blade.php
app/Http/Controllers/SpjController.php
app/Services/SpjTransactionDetailsService.php
```

### 3.10 Field penerima kuitansi manual

Keputusan:

Nama penerima kuitansi per transaksi bisa berbeda dari penerima BKU/ARKAS, sehingga dibuat field manual baru:

```text
transactions.receipt_recipient_name
```

Aturan:

- `recipient_name` tetap dianggap data sumber BKU/ARKAS;
- `receipt_recipient_name` adalah data manual untuk kuitansi;
- sinkronisasi ARKAS tidak boleh menimpa `receipt_recipient_name`;
- jika kosong, aplikasi fallback ke `spj_recipient_name`, lalu `signatory_name`, lalu `recipient_name`;
- pemilihan pekerja sebagai penerima kuitansi pada modul pemeliharaan mengisi `receipt_recipient_name`, bukan menimpa `recipient_name`.

Perubahan:

- field ditambahkan pada migrasi tenant lengkap;
- dibuat migrasi tambahan untuk database tenant yang sudah ada;
- model `Transaction` memiliki accessor `effective_receipt_recipient_name`;
- form detail transaksi menampilkan input `Penerima Kuitansi`;
- `spj.prepare` dan update manual transaksi menerima field ini;
- template/PDF memakai penerima kuitansi efektif untuk kuitansi dan tanda tangan penerima.

File terkait:

```text
database/migrations/school/2026_09_01_000000_create_complete_spj_tenant_tables.php
database/migrations/school/2026_09_01_010000_add_receipt_recipient_name_to_transactions_table.php
app/Models/Transaction.php
app/Http/Controllers/TransactionController.php
app/Http/Controllers/SpjController.php
app/Http/Controllers/SpjDocumentController.php
app/Services/SpjTemplateService.php
app/Services/SpjPackageValidationService.php
resources/views/transactions/show.blade.php
resources/views/spj-documents/pdf/package.blade.php
```

### 3.11 Dokumentasi skenario pengguna

Dokumen baru dibuat:

```text
docs/USER_SCENARIOS.md
```

Isi utama:

- peran Administrator, Operator/Bendahara, dan Kepala Sekolah/Pejabat Pemeriksa;
- alur kerja utama dari login sampai arsip dokumen;
- skenario kategori SPJ:
  - Barang;
  - Konsumsi;
  - Pemeliharaan;
  - SPPD;
  - Honor Pegawai;
  - Jasa Lainnya;
- skenario data ARKAS berubah/hilang/muncul kembali;
- skenario penomoran dokumen triwulan;
- skenario validasi kelengkapan;
- skenario laporan;
- skenario kesalahan pengguna;
- prioritas implementasi berikutnya.

Dokumen ini harus dipakai saat merancang UI dan workflow, agar aplikasi tetap mengikuti cara kerja operator sekolah.

### 3.12 Implementasi awal skenario pengguna ke aplikasi

Skenario pengguna mulai diimplementasikan langsung pada modul transaksi.

Perubahan halaman detail transaksi:

- halaman detail transaksi sekarang memiliki ringkasan “Alur operator”;
- aplikasi menampilkan status sumber ARKAS:
  - data ARKAS aktif;
  - tidak muncul pada sinkronisasi terakhir;
  - perlu rekonsiliasi;
- aplikasi menampilkan checklist kelengkapan operator;
- checklist dibangun dari isian umum dan kategori SPJ aktif;
- checklist menggunakan bahasa operator, bukan bahasa teknis database;
- penerima BKU/ARKAS dan penerima kuitansi manual dipisahkan secara jelas;
- tombol “Isi data” mengarah ke form kategori.

Checklist umum yang ditampilkan:

- kategori SPJ;
- uraian dokumen;
- penerima kuitansi;
- metode pembayaran;
- uraian item SPJ.

Checklist kategori yang ditampilkan:

- `BARANG`: data pembelian dan dokumen penerimaan;
- `KONSUMSI`: data acara dan peserta/porsi;
- `PEMELIHARAAN`: work order, daftar pekerja, penerima kuitansi pekerja;
- `SPPD`: pelaksana perjalanan dan tanggal perjalanan;
- `HONOR_PEGAWAI`: penerima honor;
- `JASA_LAINNYA`: uraian jasa.

Perubahan daftar transaksi:

- daftar transaksi menampilkan penerima BKU dan penerima kuitansi;
- daftar transaksi menampilkan label `Tidak muncul di sync` dan `Rekonsiliasi` bila relevan;
- pencarian transaksi sekarang juga mencari `receipt_recipient_name`;
- modal daftar transaksi memiliki input `Penerima Kuitansi`.

File terkait:

```text
resources/views/transactions/show.blade.php
resources/views/livewire/transactions-table.blade.php
app/Livewire/TransactionsTable.php
```

### 3.13 Modul impersonate administrator

Modul baru dibuat agar administrator bisa menguji halaman sebagai user operator tanpa logout-login manual.

Route:

```text
GET  /pengaturan/impersonate
POST /pengaturan/impersonate/{userId}
POST /pengaturan/impersonate/selesai
```

Aturan:

- hanya administrator yang bisa membuka halaman dan memulai impersonate;
- administrator tidak bisa impersonate dirinya sendiri;
- administrator tidak bisa impersonate administrator lain;
- saat impersonate operator, sesi menyimpan:
  - `impersonator_user_id`;
  - `impersonator_user_name`;
  - `impersonated_user_id`;
- jika user target punya `school_id`, aplikasi mengaktifkan sekolah user dan mengarahkan ke pilih tahun;
- tombol “Kembali sebagai Admin” tersedia lewat banner global meskipun user target bukan admin;
- setelah kembali sebagai admin, konteks sekolah/tahun/sumber dana dibersihkan.

File terkait:

```text
app/Http/Controllers/ImpersonationController.php
resources/views/impersonation/index.blade.php
resources/views/components/layouts/tailwind-app.blade.php
routes/web.php
tests/Feature/ImpersonationTest.php
```

Validasi:

```text
21 tests passed
87 assertions
```

### 3.14 Modul manajemen user dan role

Modul baru dibuat agar administrator bisa membuat dan mengatur akun pengguna dari aplikasi.

Route:

```text
GET    /pengaturan/user
POST   /pengaturan/user
PUT    /pengaturan/user/{userId}
DELETE /pengaturan/user/{userId}
```

Role awal:

- `ADMIN`: administrator lintas sekolah;
- `OPERATOR`: operator/bendahara sekolah;
- `VIEWER`: pemeriksa/read-only pada fase berikutnya.

Aturan yang sudah diterapkan:

- hanya administrator yang bisa membuka halaman manajemen user;
- administrator bisa menambah user, memilih role, memilih sekolah, dan mengisi password awal;
- administrator bisa mengubah nama, email, sekolah, role, dan password;
- password tidak berubah jika field password dikosongkan saat edit;
- administrator tidak bisa menghapus akun yang sedang digunakan;
- administrator tidak bisa menurunkan role administrator dirinya sendiri;
- sistem menjaga agar minimal tetap ada satu administrator.

File terkait:

```text
app/Http/Controllers/UserManagementController.php
app/Models/User.php
resources/views/users/index.blade.php
resources/views/components/layouts/tailwind-app.blade.php
routes/web.php
tests/Feature/UserManagementTest.php
```

Catatan desain:

- role saat ini masih sederhana berbasis kolom `users.role`;
- jika nanti butuh hak akses rinci, tambahkan policy/permission per aksi, bukan hanya role global;
- `VIEWER` sudah tersedia sebagai role pondasi, tetapi aturan read-only rinci perlu difinalkan pada fase berikutnya.

### 3.15 Strategi aktivasi administrator di laptop lain

Masalah:

- tanpa menyalin database, laptop baru tidak memiliki akun administrator dari laptop lama;
- database lokal/tenant memang sebaiknya tidak dipindahkan hanya untuk aktivasi akun.

Strategi yang disarankan:

1. Laptop baru menjalankan migrasi database kosong.
2. User membuka halaman aktivasi administrator.
3. User memasukkan email dan kode aktivasi.
4. Sistem memvalidasi kode aktivasi melalui server pusat atau file aktivasi bertanda tangan.
5. Sistem membuat akun administrator lokal baru.
6. Data sekolah/transaksi tetap diambil dari setup sekolah dan sinkronisasi ARKAS.

Pilihan:

- online activation server: paling aman untuk lisensi, revoke, dan audit;
- offline signed activation file: cocok jika laptop sekolah sering tanpa internet, tetapi kontrol revoke lebih lemah.

Keputusan sementara:

- jangan copy database untuk memindahkan administrator;
- implementasi saat ini baru modul manajemen user lokal;
- fitur aktivasi lintas laptop perlu dibuat sebagai modul terpisah agar tidak bercampur dengan data transaksi/SPJ.

### 3.16 Penyempurnaan alur transaksi, paket, dan penomoran SPJ

Perubahan skenario yang sudah diterapkan:

- format nomor dapat diatur operator per jenis dokumen;
- `{SCHOOL}` memakai Kode Sekolah dan `{NPSN}` memakai NPSN;
- nomor pesanan, BAP, BAST, SPK, RAB, surat tugas perjalanan dinas, dokumen, dan SPJ memakai tanggal relasinya masing-masing;
- validasi kesesuaian nilai transaksi dan total rincian dijalankan sebelum penyimpanan/penomoran;
- paket yang nilainya tidak konsisten tetap dapat dibuka untuk koreksi tetapi tidak dapat dinomori;
- nomor aktif dapat dibatalkan dengan alasan dan riwayat tetap dipertahankan;
- slot nomor yang dibatalkan dapat dipakai kembali pada domain dan periode yang sama;
- penerbitan ulang paket yang sama memakai kembali baris identitas dokumen yang dibatalkan agar tidak melanggar unique key `spj_package_id + document_type + scope_key`;
- status pembatalan konsisten antara tabel transaksi dan halaman paket berdasarkan relasi ID transaksi;
- field paket/dokumen yang terkunci dibuat non-editable dan backend tetap menjadi lapisan proteksi utama;
- laporan Honor Pegawai dapat menggabungkan beberapa transaksi pada Daftar Pembayaran, sedangkan kuitansi tetap satu per transaksi, dengan kolom PPh 21 dan tanda tangan;
- notifikasi memakai toast mengambang dan konfirmasi memakai dialog UI aplikasi;
- tab utama SPJ memakai navigasi URL normal dan tidak lagi mengganti root DOM Alpine secara manual.

Dokumentasi skenario rinci sudah diselaraskan pada `docs/USER_SCENARIOS.md`, terutama bagian penomoran, validasi, laporan, kesalahan pengguna, dan prioritas berikutnya.

---

## 4. Test terakhir

Test yang sudah dijalankan dan lolos:

```text
php artisan test
```

Hasil terakhir:

```text
31 tests passed
124 assertions
```

Test penting:

- `CriticalDocumentWorkflowTest`;
- `DocumentNumberingWorkflowTest`;
- `DocumentNumberFormatSettingsTest`;
- `SafeArkasSynchronizationTest`;
- `SchoolDatabaseManagerTest`;
- `SchoolYearSelectionFlowTest`;
- `SecurityHardeningTest`;
- `TransactionsTableLivewireTest`.
- `ImpersonationTest`.
- `UserManagementTest`.

Catatan:

- Setelah perubahan kecil, minimal jalankan test terkait.
- Setelah perubahan schema, sync, locking, atau penomoran, jalankan full test.

---

## 5. File penting yang perlu dibaca saat melanjutkan

Dokumen:

```text
docs/SPJ_DESIGN_DECISIONS.md
docs/CURRENT_PROGRESS.md
docs/ARCHITECTURE_COMPLETE.md
```

Modul transaksi:

```text
app/Livewire/TransactionsTable.php
resources/views/livewire/transactions-table.blade.php
resources/views/transactions/index.blade.php
resources/views/transactions/show.blade.php
app/Http/Controllers/TransactionController.php
```

Sinkronisasi:

```text
app/Services/ArkasSynchronizationService.php
app/Services/ArkasSynchronizationServiceV2.php
app/Services/ArkasBridgeClient.php
```

Detail SPJ:

```text
app/Services/SpjTransactionDetailsService.php
app/Services/SpjPackageValidationService.php
app/Services/SpjTemplateService.php
app/Http/Controllers/SpjController.php
resources/views/spj/index.blade.php
```

Migrasi:

```text
database/migrations
database/migrations/school
```

Test:

```text
tests/Feature
tests/Unit
```

---

## 6. Hal yang masih perlu dilanjutkan

### 6.0 Operasional cache, antrean, dan pemantauan performa

- Referensi tahun aktif, tahun pada header, profil sekolah, dan pilihan status transaksi di-cache singkat berdasarkan sekolah/tahun.
- Sinkronisasi ARKAS dijalankan sebagai job pada queue `operations`; jalankan worker saat aplikasi aktif:

```text
php artisan queue:work --queue=operations,default --tries=2 --timeout=900
```

- Status proses tersimpan pada tabel pusat `background_operations` dan ditampilkan pada dashboard untuk kondisi menunggu, berjalan, atau gagal.
- Request lambat dicatat bila melampaui `PERFORMANCE_SLOW_REQUEST_MS` dan query lambat dicatat bila melampaui `PERFORMANCE_SLOW_QUERY_MS`.
- Log performa tersedia di `storage/logs/performance-*.log`; SQL dicatat tanpa nilai binding agar data sensitif tidak masuk log.
- Header `Server-Timing` tersedia pada respons web untuk melihat durasi aplikasi dari alat pengembang browser.
- Setelah mengubah referensi sekolah, tahun, atau template, cache terkait perlu dihapus melalui `php artisan cache:clear` jika perubahan belum terlihat.

### 6.1 Rapikan tampilan label metode pembayaran

Nilai database sudah canonical:

- `transfer_bank`;
- `siplah`;
- `tunai`.

Namun beberapa tampilan mungkin masih menampilkan nilai mentah.

Rekomendasi:

- buat helper/enum/label map;
- tampilkan “Transfer Bank (CMS / Non Tunai)” dan bukan `transfer_bank`.

### 6.2 Finalisasi schema tenant

Perlu cek ulang semua migrasi tenant:

- pastikan `manual_description` tidak ada;
- pastikan semua tabel aplikasi punya `created_at` dan `updated_at`;
- pastikan relasi SPPD dan pemeliharaan sesuai keputusan;
- pastikan foreign key aman;
- pastikan index mengikuti kebutuhan query.

### 6.3 Work order pemeliharaan

Pastikan struktur akhir:

```text
transactions
└── spj_work_orders
    └── spj_workers[]
```

Jika `spj_workers` saat ini masih langsung ke `transactions`, perlu evaluasi:

- apakah perlu migrasi `work_order_id`;
- apakah tetap menyimpan `transaction_id` untuk kemudahan query;
- bagaimana menjaga kompatibilitas data lama.

### 6.4 SPPD banyak orang

Pastikan:

- 1 transaksi bisa punya banyak `spj_travels`;
- validasi total nominal perjalanan tidak melebihi `net_amount` atau `gross_amount` sesuai aturan yang dipilih;
- dokumen SPPD bisa dibuat per orang atau gabungan sesuai kebutuhan laporan.

### 6.5 Penomoran dokumen massal triwulan

Fitur yang disarankan:

- halaman simulasi penomoran triwulan;
- filter sekolah/tahun/sumber dana/triwulan;
- hanya ambil dokumen `READY`;
- nomor per jenis dokumen;
- preview sebelum simpan;
- simpan atomik;
- audit log.

### 6.6 Locking dokumen

Perlu dipastikan semua form edit mengecek:

- apakah paket masih editable;
- apakah dokumen sudah bernomor;
- apakah dokumen final;
- apakah transaksi butuh rekonsiliasi.

Jangan hanya kunci di UI. Backend juga harus menolak perubahan.

### 6.7 Rekonsiliasi data ARKAS

Perlu halaman/fitur untuk:

- melihat transaksi `SOURCE_MISSING`;
- melihat transaksi `requires_reconciliation`;
- membandingkan data ARKAS lama vs baru;
- memilih aksi: pertahankan manual, update sumber, revisi dokumen, atau abaikan.

### 6.8 Migrasi modul SPJ ke TALL

Setelah `/transaksi` stabil, lanjutkan bertahap:

1. detail transaksi;
2. persiapan SPJ;
3. modal/section kategori;
4. validasi ready;
5. penomoran;
6. export/dokumen.

### 6.9 Penyempurnaan kategori Honor Pegawai

Status: **TODO — pengujian alur dasar kategori Honor Pegawai sudah selesai.**

Prioritas tinggi:

1. Deteksi pembayaran honor ganda menggunakan kombinasi penerima/identitas, jenis honor, periode, kode rekening, dan tahun anggaran.
2. Validasi periode honor terhadap Bulan/Kali dan total transaksi.
3. Ringkasan nilai bruto, PPh 21, dan nilai bersih pada form serta laporan gabungan.

Prioritas lanjutan:

- pisahkan konsep penandatangan dokumen dari penerima honor;
- tambahkan NIK/NIP/nomor identitas penerima;
- dukung alasan tidak dikenakan PPh 21;
- lengkapi filter laporan honor;
- audit perubahan data honor setelah paket dibuat;
- gunakan status workflow: `Belum lengkap → Siap dibuat → Paket dibuat → Siap dinomori → Bernomor → Final`;
- setelah pembatalan nomor, gunakan status: `Nomor dibatalkan → Perlu koreksi → Siap diterbitkan ulang`.

---

## 7. Catatan teknis penting

### 7.1 Jangan pakai `manual_description`

Jika menemukan `manual_description`:

- hapus dari migrasi baru;
- hapus dari model/fillable;
- ubah referensi ke `payment_description` atau field detail yang tepat.

### 7.2 Jangan hapus data manual saat sync

Saat sync ARKAS:

- update field sumber;
- pertahankan field manual;
- tandai missing/reconciliation;
- jangan cascade delete paket/dokumen manual karena source hilang sementara.

### 7.3 Jangan ubah dokumen final diam-diam

Jika dokumen:

- sudah bernomor;
- sudah dicetak;
- sudah final;
- sudah diarsipkan;

maka perubahan harus melalui mekanisme revisi/rekonsiliasi/audit.

### 7.4 Test harus aman

Pastikan test memakai:

```text
database/testing.sqlite
```

Jangan biarkan test memakai database utama.

---

## 8. Instruksi cepat untuk Codex berikutnya

Jika membuka sesi baru, mulai dengan:

```text
Baca docs/SPJ_DESIGN_DECISIONS.md dan docs/CURRENT_PROGRESS.md. Lanjutkan pengembangan berdasarkan aturan di sana. Jangan mulai analisis dari nol kecuali ada konflik dengan kode terbaru.
```

Setelah membaca dokumen:

1. cek file yang akan diubah;
2. cek apakah perubahan menyentuh sync/manual/locking/penomoran;
3. implementasikan kecil-kecil;
4. jalankan test proporsional;
5. update dokumen ini jika ada keputusan baru.
