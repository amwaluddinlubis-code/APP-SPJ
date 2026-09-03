# SPJ BOSP Web — Skenario Pengguna & Alur Kerja

Dokumen ini menjelaskan rancangan skenario pengguna aplikasi SPJ BOSP Web.

Tujuannya adalah memastikan aplikasi dibangun mengikuti cara kerja nyata operator/bendahara sekolah, bukan hanya mengikuti struktur tabel. Dokumen ini harus dibaca bersama:

- `docs/SPJ_DESIGN_DECISIONS.md`
- `docs/CURRENT_PROGRESS.md`

Terakhir diperbarui: 2026-09-02

---

## 1. Prinsip pengalaman pengguna

Aplikasi harus membuat operator merasa pekerjaannya jelas.

Operator tidak perlu memahami istilah teknis seperti tenant database, source hash, migration, atau relasi tabel. Di UI, konsep teknis harus diterjemahkan menjadi bahasa kerja:

- Data dari ARKAS.
- Data yang perlu dilengkapi.
- Data belum lengkap.
- Siap dibuat SPJ.
- Sudah bernomor.
- Terkunci.
- Perlu rekonsiliasi.
- Tidak muncul pada sinkronisasi terakhir.

Pertanyaan utama yang harus dijawab aplikasi untuk pengguna:

1. Saya sedang bekerja di sekolah, tahun, dan sumber dana yang mana?
2. Transaksi mana yang perlu saya lengkapi?
3. Data apa yang berasal dari ARKAS?
4. Data apa yang harus saya isi manual?
5. Dokumen apa yang bisa dibuat dari transaksi ini?
6. Apakah transaksi ini sudah siap?
7. Apakah dokumen ini boleh diedit?
8. Apakah ada perubahan dari ARKAS yang perlu saya tinjau?

---

## 2. Peran pengguna

### 2.1 Administrator aplikasi

Administrator adalah pengguna dengan akses lintas sekolah.

Tanggung jawab:

- menambahkan sekolah;
- mengatur database tenant sekolah;
- menjalankan/provision database sekolah;
- menguji tampilan dan akses sebagai operator melalui modul impersonate;
- mengatur koneksi ARKAS;
- mengatur user operator;
- mengatur template dokumen;
- menjalankan sinkronisasi awal bila diperlukan;
- memantau status database sekolah;
- membuka kembali dokumen/paket yang terkunci jika ada alasan resmi;
- menangani rekonsiliasi data besar;
- melakukan backup/restore;
- melihat audit log.
- mengelola user dan role.

Batasan:

- administrator tetap tidak boleh mengubah dokumen final diam-diam tanpa audit;
- pembukaan kunci dokumen harus memakai alasan;
- perubahan sumber dana/tahun aktif harus eksplisit.
- impersonate hanya untuk uji akses/tampilan dan harus mudah dikembalikan ke akun administrator.

Catatan desain:

Administrator boleh menambahkan sekolah mana pun karena setiap sekolah memakai database tenant terpisah.

### 2.1.1 Manajemen user dan role

Role awal aplikasi:

- `ADMIN`: mengelola sekolah, database tenant, integrasi, user, impersonate, backup, dan pengaturan lintas sekolah.
- `OPERATOR`: mengerjakan transaksi/SPJ pada sekolah yang ditugaskan.
- `VIEWER`: melihat/memeriksa data tanpa menjadi pengisi utama. Pada fase awal role ini disiapkan sebagai pondasi read-only, lalu perlu diperkuat dengan policy per aksi.

Aturan:

- hanya administrator yang boleh membuka manajemen user;
- administrator bisa membuat user baru, memilih role, menghubungkan user ke sekolah, dan reset password;
- minimal harus selalu ada satu administrator;
- administrator tidak boleh menghapus akun yang sedang digunakan;
- administrator tidak boleh menurunkan role administrator dirinya sendiri;
- user operator sebaiknya memiliki `school_id` agar konteks sekolah bisa otomatis saat login/impersonate;
- akun administrator boleh tidak terikat sekolah tertentu karena aksesnya lintas sekolah.

Catatan:

Manajemen role saat ini masih berbasis kolom `users.role`. Jika kebutuhan hak akses makin rinci, langkah berikutnya adalah menambahkan policy/permission per aksi, misalnya boleh sinkron, boleh cetak, boleh finalisasi, boleh buka kunci, dan boleh hapus.

### 2.1.2 Aktivasi administrator di laptop lain tanpa menyalin database

Karena aplikasi memakai database lokal/tenant, laptop baru tidak otomatis mengetahui akun administrator dari laptop lama jika database tidak disalin.

Strategi yang disarankan:

1. Buat mekanisme aktivasi, bukan copy database.
2. Simpan identitas pemilik/lisensi di server pusat atau file aktivasi bertanda tangan digital.
3. Pada laptop baru, user membuka halaman aktivasi.
4. User memasukkan email administrator dan kode aktivasi.
5. Sistem memvalidasi kode aktivasi.
6. Jika valid, sistem membuat administrator lokal baru di database laptop tersebut.
7. Database sekolah tetap dibuat kosong dan data operasional diambil ulang dari sinkronisasi ARKAS.

Pilihan implementasi:

- Online: validasi ke server pusat. Ini paling aman karena kode bisa dibatasi, dicabut, dan dilacak.
- Offline: impor file/kode aktivasi bertanda tangan. Ini tidak butuh internet, tetapi lebih sulit untuk revoke dan audit terpusat.

Aturan penting:

- jangan menyalin database hanya untuk memindahkan administrator;
- jangan menyertakan data transaksi/manual di proses aktivasi admin;
- aktivasi hanya membuat akses awal;
- data sekolah, tahun, dan transaksi tetap dibangun dari migrasi baru dan sinkronisasi ARKAS;
- jika laptop lama dan baru dipakai bersamaan, perlu kebijakan lisensi/perangkat agar tidak terjadi konflik operasional.

### 2.2 Operator sekolah / bendahara

Operator adalah pengguna harian utama.

Tanggung jawab:

- login;
- memilih sekolah yang ditugaskan;
- memilih tahun anggaran dan sumber dana;
- menjalankan sinkronisasi ARKAS jika diberi hak;
- memeriksa transaksi hasil sinkronisasi;
- memilih kategori SPJ;
- mengisi data manual transaksi;
- mengisi detail kategori SPJ;
- memastikan uraian barang/jasa lengkap;
- membuat paket SPJ;
- memperbaiki data selama dokumen belum terkunci;
- mencetak atau mengunduh dokumen jika paket sudah siap.

Batasan:

- tidak boleh mengubah database tenant;
- tidak boleh membuka dokumen final;
- tidak boleh mengubah nomor dokumen final;
- tidak boleh menghapus data sumber ARKAS;
- tidak boleh menimpa penerima BKU hanya untuk kebutuhan kuitansi.

### 2.3 Kepala sekolah / pejabat pemeriksa

Peran ini dapat ditambahkan pada fase berikutnya.

Tanggung jawab potensial:

- melihat ringkasan SPJ;
- memeriksa paket yang sudah siap;
- memberi persetujuan;
- memberi catatan koreksi;
- melihat laporan triwulan.

Batasan:

- tidak mengisi rincian teknis;
- tidak mengubah data ARKAS;
- tidak mengubah nomor dokumen.

Catatan:

Untuk fase sekarang, role ini belum wajib. Jangan menambah kompleksitas approval sebelum workflow operator dan dokumen stabil.

---

## 3. Alur kerja utama

Alur kerja ideal:

```text
Login
↓
Pilih sekolah
↓
Pilih tahun anggaran + sumber dana
↓
Sinkronisasi ARKAS
↓
Cek transaksi
↓
Lengkapi data manual sesuai kategori SPJ
↓
Validasi kelengkapan
↓
Status READY / SIAP
↓
Penomoran dokumen per triwulan
↓
Cetak / export dokumen
↓
Final / arsip
```

---

## 4. Skenario detail

### 4.1 Login dan konteks kerja

Sebagai operator, saya ingin login lalu memilih sekolah dan tahun anggaran agar saya tidak salah mengerjakan data.

Alur:

1. User login.
2. Sistem memeriksa role.
3. Jika user punya lebih dari satu sekolah, tampilkan pilih sekolah.
4. Setelah sekolah dipilih, tampilkan pilih tahun anggaran.
5. Setelah tahun dipilih, kunci sumber dana aktif.
6. User masuk dashboard.

Aturan:

- transaksi tidak boleh dibuka tanpa sekolah aktif;
- transaksi tidak boleh dibuka tanpa tahun aktif;
- query harus memakai sekolah, tahun, dan sumber dana aktif.

### 4.2 Sinkronisasi ARKAS pertama kali

Sebagai administrator/operator, saya ingin mengambil data dari ARKAS agar transaksi SPJ tersedia.

Alur:

1. User memilih sekolah.
2. User memilih tahun dan sumber dana.
3. User membuka sinkronisasi ARKAS.
4. Sistem memvalidasi NPSN database ARKAS.
5. Sistem mengambil RKAS dan BKU.
6. Sistem membuat transaksi dan rincian transaksi.
7. Transaksi masuk sebagai `DRAFT`.

Aturan:

- NPSN ARKAS harus sama dengan sekolah aktif;
- data sumber dana lain tidak boleh ikut masuk;
- data manual yang sudah ada tidak boleh ditimpa.

### 4.2.1 Administrator menguji halaman sebagai operator

Sebagai administrator, saya ingin melihat aplikasi sebagai operator tertentu agar bisa memeriksa akses, sekolah aktif, menu, dan workflow tanpa logout-login manual.

Alur:

1. Administrator membuka menu “Uji Sebagai User”.
2. Sistem menampilkan daftar user.
3. Administrator memilih user operator.
4. Sistem mengganti sesi login menjadi operator tersebut.
5. Jika operator memiliki sekolah, sistem mengaktifkan sekolah user dan mengarahkan ke pilih tahun.
6. Banner “Mode uji user aktif” selalu tampil.
7. Administrator menekan “Kembali sebagai Admin”.
8. Sistem mengembalikan sesi login ke administrator asal.

Aturan:

- hanya administrator asli yang boleh memulai impersonate;
- akun administrator lain tidak boleh di-impersonate;
- selama impersonate, hak akses mengikuti user target;
- tombol kembali harus tetap tersedia meskipun user target bukan administrator;
- mode impersonate menyimpan `impersonator_user_id` dan `impersonated_user_id` di session;
- setelah kembali sebagai admin, konteks sekolah/tahun/sumber dana dibersihkan agar admin memilih konteks lagi dengan sadar.

### 4.3 Operator melengkapi transaksi

Sebagai operator, saya ingin melihat transaksi hasil ARKAS dan melengkapi data yang kurang.

Alur:

1. User membuka `/transaksi`.
2. User mencari transaksi.
3. User membuka detail transaksi.
4. User memilih kategori SPJ.
5. Sistem menampilkan form sesuai kategori.
6. User mengisi uraian dokumen, penerima kuitansi, metode pembayaran, dan detail kategori.
7. User menyimpan.

Data umum semua kategori:

- kategori SPJ;
- uraian dokumen/pembayaran;
- metode pembayaran;
- referensi pembayaran;
- penerima kuitansi;
- nama penandatangan;
- jabatan/peran.

Aturan:

- `recipient_name` adalah penerima BKU/ARKAS;
- `receipt_recipient_name` adalah penerima kuitansi manual;
- penerima kuitansi boleh berbeda dari BKU;
- `manual_description` tidak dipakai lagi.

### 4.4 Kategori barang

Sebagai operator, saya ingin melengkapi dokumen pembelian barang.

Data yang diisi:

- invoice/faktur;
- tanggal invoice;
- nomor pesanan;
- tanggal pesanan;
- nomor BAP;
- tanggal BAP;
- nomor BAST;
- tanggal BAST;
- uraian item barang/jasa untuk SPJ.

Aturan:

- urutan pesan, terima, dan bayar tidak selalu sama;
- nomor dokumen harus mengikuti jenis dan tanggal dokumen;
- jangan menjadikan urutan transaksi sebagai urutan nomor dokumen.

Status pengujian yang sudah selesai:

- validasi `jumlah × harga = nilai item` dan total rincian terhadap nilai bruto transaksi;
- pesanan wajib memiliki minimal satu barang dengan uraian, jumlah, dan harga yang valid;
- BAP/BAST tidak dapat diterbitkan sebelum rincian barang lengkap dan konsisten;
- nomor invoice ganda dari vendor yang sama pada tahun anggaran yang sama terdeteksi sebelum penomoran.

TODO skenario lanjutan kategori Barang:

- penerimaan sebagian: satu pesanan dapat diterima melalui beberapa BAST, tetapi akumulasi jumlah diterima tidak boleh melebihi jumlah pesanan;
- barang kurang, rusak, atau ditolak: catat selisih penerimaan, alasan, tindak lanjut, dan berita acara koreksi tanpa mengubah data pesanan awal;
- retur/penggantian barang: hubungkan dokumen retur atau penggantian dengan item dan BAST asal serta simpan jejak audit;
- perubahan harga atau jumlah setelah pesanan: tampilkan perbandingan nilai pesanan, invoice, dan pembayaran serta wajibkan alasan koreksi;
- satu invoice untuk beberapa transaksi atau beberapa invoice untuk satu transaksi: tetapkan aturan pemetaan dan cegah penghitungan ganda;
- validasi satuan: cegah pencampuran satuan yang tidak setara dan sediakan konversi satuan yang tercatat jika diperlukan;
- pajak pembelian: cocokkan DPP, PPN, PPh 22, dan nilai bersih dengan rincian barang dan nilai transaksi;
- pembelian SiPLah: simpan nomor pesanan SiPLah, identitas penyedia, biaya layanan/ongkir, dan status penyelesaian;
- lampiran bukti: periksa keberadaan invoice, pesanan, BAP, BAST, bukti bayar, dan dokumen pajak sebelum finalisasi;
- pembatalan pesanan: paket tidak boleh dinomori sebagai pembelian selesai dan nomor dokumen yang sudah terbit harus mengikuti alur pembatalan resmi;
- transaksi yang berubah setelah sinkronisasi ARKAS: tandai rekonsiliasi apabila jumlah, harga, vendor, atau nilai transaksi berubah setelah paket dibuat;
- laporan monitoring barang: tampilkan jumlah dipesan, diterima, belum diterima, diretur, nilai invoice, nilai dibayar, dan selisih per transaksi.

### 4.5 Kategori konsumsi

Sebagai operator, saya ingin mengisi acara dan daftar peserta konsumsi.

Data yang diisi:

- nama acara/rapat;
- tempat;
- jumlah peserta;
- daftar peserta;
- jabatan/instansi;
- jumlah porsi;
- data pembelian/invoice jika diperlukan.

Aturan:

- daftar peserta menjadi dasar kebutuhan konsumsi;
- konsumsi tetap dapat memakai data pembelian barang/jasa;
- jumlah porsi boleh berbeda dari jumlah orang jika ada kebutuhan khusus.

### 4.6 Kategori pemeliharaan

Sebagai operator, saya ingin membuat work order dan daftar pekerja untuk transaksi pemeliharaan.

Relasi:

```text
1 transaksi
└── 1 work order
    └── banyak pekerja
```

Data work order:

- deskripsi pekerjaan;
- lokasi;
- nomor SPK;
- tanggal SPK;
- tanggal mulai;
- tanggal selesai.

Data pekerja:

- nama pekerja;
- uraian pekerjaan;
- jumlah hari;
- tarif;
- penanda penerima kuitansi;
- catatan.

Aturan:

- daftar pekerja ditampilkan dalam bentuk tabel;
- jika pekerja ditandai sebagai penerima kuitansi, sistem mengisi `receipt_recipient_name`;
- sistem tidak boleh menimpa `recipient_name` BKU/ARKAS;
- total upah pekerja perlu divalidasi agar tidak melebihi nilai transaksi pada fase validasi lanjutan.

### 4.7 Kategori SPPD

Sebagai operator, saya ingin satu pembayaran SPPD bisa memuat lebih dari satu orang.

Relasi:

```text
1 transaksi
└── banyak pelaksana perjalanan
```

Data pelaksana:

- nama pelaksana;
- tujuan;
- maksud perjalanan;
- moda transportasi;
- tanggal berangkat;
- tanggal pulang;
- nilai;
- catatan.

Aturan:

- satu transaksi boleh memiliki banyak pelaksana;
- total nilai perjalanan harus divalidasi;
- dokumen bisa dibuat per pelaksana atau gabungan sesuai template;
- penerima kuitansi tetap memakai `receipt_recipient_name`.

### 4.8 Kategori honor pegawai

Sebagai operator, saya ingin mengisi banyak penerima honor dalam satu transaksi.

Data yang diisi:

- nama penerima;
- jabatan/jenis honor;
- bulan/kali;
- tarif;
- catatan.

Aturan:

- data honor sebaiknya disimpan pada relasi honor;
- jangan campur penerima honor dengan pekerja pemeliharaan tanpa aturan;
- jika satu penerima bertindak sebagai penerima kuitansi, isi `receipt_recipient_name`.

TODO penyempurnaan kategori Honor Pegawai:

- tambahkan identitas penerima seperti NIK/NIP/nomor identitas sebagai pembeda nama yang sama;
- deteksi kemungkinan pembayaran ganda berdasarkan penerima, jenis honor, periode, kode rekening, dan tahun anggaran;
- bedakan dengan jelas penandatangan dokumen dan penerima pembayaran;
- tambahkan periode honor “dari bulan” dan “sampai bulan”, lalu cocokkan dengan nilai Bulan/Kali;
- tampilkan simulasi `Bulan/Kali × Tarif = Bruto − PPh 21 = Nilai Diterima` sebelum simpan;
- sediakan status “Tidak dikenakan PPh 21” beserta alasan jika diperlukan;
- tambah ringkasan laporan berupa total bruto, total PPh 21, total bersih, jumlah penerima, jumlah transaksi, dan daftar nomor bukti;
- tambah filter laporan berdasarkan bulan, triwulan, jenis honor, dan kode rekening;
- simpan audit perubahan tarif, Bulan/Kali, PPh 21, periode, dan penerima setelah paket dibuat.

### 4.9 Kategori jasa lainnya

Sebagai operator, saya ingin mengisi informasi jasa yang tidak masuk barang, konsumsi, pemeliharaan, SPPD, atau honor.

Data yang diisi:

- uraian jasa;
- lokasi/unit kerja;
- tanggal mulai;
- tanggal selesai;
- penerima kuitansi;
- referensi pembayaran.

Aturan:

- kategori ini jangan menjadi tempat pembuangan semua transaksi;
- jika pola transaksi sering muncul, pertimbangkan kategori baru atau mapping rekening.

---

## 5. Skenario data ARKAS berubah

### 5.1 Transaksi masih ada tetapi berubah

Sebagai operator, saya ingin tahu jika transaksi dari ARKAS berubah setelah saya melengkapi SPJ.

Alur:

1. Sinkronisasi ARKAS berjalan ulang.
2. Sistem menghitung hash sumber.
3. Sistem melihat ada perubahan.
4. Jika dokumen belum final, data sumber boleh diperbarui.
5. Jika dokumen sudah siap/bernomor/final, sistem memberi tanda rekonsiliasi.

UI harus menampilkan:

- data lama;
- data baru;
- field yang berubah;
- status dokumen;
- pilihan tindakan.

### 5.2 Transaksi tidak muncul sementara

Sebagai operator, saya tidak ingin data manual hilang hanya karena transaksi ARKAS tidak muncul pada sync terakhir.

Aturan:

- jangan hapus transaksi;
- tandai `SOURCE_MISSING`;
- isi `source_missing_since`;
- pertahankan detail manual;
- pertahankan paket dan nomor dokumen;
- tampilkan label “Tidak muncul pada sync terakhir”.

### 5.3 Transaksi muncul kembali

Sebagai operator, saya ingin transaksi yang muncul kembali dari ARKAS tersambung ke data manual lama.

Aturan:

- cocokkan memakai `source_key`/ID BKU/no bukti;
- ubah status ke `ACTIVE`;
- kosongkan `source_missing_since`;
- pertahankan data manual;
- jika nilai berubah, tandai rekonsiliasi.

---

## 6. Skenario penomoran dokumen

### 6.1 Penomoran setelah semua siap

Sebagai operator/admin, saya ingin memberi nomor dokumen secara otomatis setelah transaksi triwulan siap.

Alur:

1. User memilih triwulan.
2. Sistem menampilkan transaksi/paket `READY`.
3. Sistem menampilkan simulasi nomor.
4. User memeriksa urutan.
5. User menekan tetapkan nomor.
6. Sistem menyimpan nomor per jenis dokumen.
7. Sistem mengunci dokumen bernomor.

Aturan:

- nomor per jenis dokumen;
- urutan berdasarkan tanggal dokumen;
- jangan berdasarkan waktu input;
- jangan ubah nomor final tanpa mekanisme revisi.

### 6.2 Dokumen sudah bernomor

Sebagai operator, saya ingin tahu bahwa dokumen bernomor tidak bisa diedit sembarangan.

Aturan:

- semua field yang terkunci harus tampil non-editable/disabled agar user tidak dapat memaksa input dari UI;
- backend tetap harus menolak perubahan;
- perubahan nomor dilakukan melalui pembatalan dan penerbitan ulang, bukan mengedit nomor aktif;
- audit log wajib dibuat.

### 6.3 Format dan sumber tanggal nomor otomatis

Operator dapat membuka pengaturan **Format Penomoran** dan mengatur pola untuk:

- `NO_SPJ` berdasarkan tanggal transaksi/dokumen SPJ;
- `NO_DOKUMEN` berdasarkan tanggal dokumen;
- `NO_PESANAN` berdasarkan `tgl_pesanan`;
- `NO_BAP` berdasarkan `tgl_bap`;
- `NO_BAST` berdasarkan `tgl_bast`;
- `NO_SPK` berdasarkan `tgl_spk`;
- `NO_RAB` berdasarkan `tgl_rab`;
- `NO_SURAT_TUGAS_PERJALANAN_DINAS` berdasarkan tanggal surat tugas/perjalanan yang berelasi.

Aturan token sekolah:

- `{SCHOOL}` memakai **Kode Sekolah** dari pengaturan sekolah;
- `{NPSN}` memakai NPSN sekolah;
- keduanya tidak boleh dianggap sebagai token yang sama.

Sequence setiap jenis dokumen berdiri sendiri dan dihitung dalam domain sekolah, tahun/sumber dana, serta periode yang berlaku.

### 6.4 Pembatalan nomor karena koreksi

Sebagai operator/admin, saya ingin membatalkan nomor yang salah tanpa menghapus riwayatnya agar data dapat diperbaiki dengan aman.

Alur:

1. User membuka paket yang sudah bernomor.
2. User memilih pembatalan nomor.
3. Sistem meminta alasan melalui dialog UI aplikasi.
4. Sistem menandai dokumen `CANCELLED`, menyimpan alasan dan audit, lalu mengembalikan paket ke kondisi yang dapat diperbaiki.
5. Header paket menampilkan **Nomor SPJ dibatalkan**, bukan status penomoran OK.
6. Data yang perlu dikoreksi dibuka kembali sesuai aturan akses.

Nomor yang dibatalkan tidak dihapus dari riwayat. Nomor aktif lama tidak boleh diubah langsung.

### 6.5 Penggunaan kembali slot nomor yang dibatalkan

Nomor yang dibatalkan dapat dipakai oleh dokumen berikutnya dalam domain dan periode yang sama.

Aturan:

- urutan melanjutkan kondisi sequence sukses terakhir;
- slot kosong akibat pembatalan boleh dialokasikan kembali tanpa harus kembali ke paket asal;
- alokasi tetap berdasarkan identitas transaksi/paket dan jenis dokumen;
- satu paket hanya memiliki satu identitas dokumen untuk kombinasi `spj_package_id`, `document_type`, dan `scope_key`;
- penerbitan ulang pada paket yang sama memperbarui identitas dokumen yang dibatalkan, bukan memasukkan baris identitas duplikat;
- dokumen sukses/final lain tidak boleh tertimpa.

### 6.6 Penerbitan ulang setelah koreksi

Alur penerbitan ulang:

1. Nomor aktif dibatalkan dengan alasan.
2. Paket tampil sebagai **Dibatalkan — menunggu nomor baru**.
3. User memperbaiki data transaksi atau paket.
4. Sistem menjalankan kembali validasi kelengkapan dan kesesuaian nilai.
5. Jika valid, user menerbitkan ulang nomor dari tab Penomoran atau penomoran triwulan.
6. Sistem memakai slot yang tersedia sesuai aturan sequence dan menyimpan hubungan riwayat penggantian.

Jika validasi gagal, penomoran ulang harus ditolak dan user tetap diberi kesempatan memperbaiki data.

### 6.7 Penomoran dan rekonsiliasi per triwulan

Pada tab Monitoring/Laporan, user dapat memilih triwulan untuk menjalankan penomoran atau rekonsiliasi.

Aturan:

- transaksi yang sudah memiliki nomor aktif dilewati;
- paket yang nomornya dibatalkan dibedakan secara visual dari paket sukses;
- paket yang belum lengkap tidak ikut dinomori;
- proses memakai ID transaksi/paket sebagai identitas, bukan hanya teks nomor bukti;
- konfirmasi memakai dialog UI aplikasi, bukan dialog bawaan browser;
- hasil proses menampilkan notifikasi mengambang dengan warna sesuai status berhasil, peringatan, atau gagal.

---

## 7. Skenario validasi kelengkapan

Sebelum paket SPJ menjadi siap, sistem harus memeriksa:

- kategori SPJ sudah dipilih;
- uraian dokumen/pembayaran tersedia;
- penerima kuitansi tersedia;
- metode pembayaran tersedia;
- rincian item transaksi tersedia;
- uraian item SPJ lengkap;
- field kategori wajib sudah diisi;
- total detail kategori tidak melebihi nilai transaksi;
- total detail kategori harus sama dengan nilai transaksi ketika kategori mewajibkan rincian penuh;
- kuantitas/Bulan/Kali harus berupa integer dan tidak menerima pecahan;
- tarif/harga disimpan sebagai angka dan ditampilkan dengan pemisah ribuan;
- dokumen belum terkunci;
- tidak ada rekonsiliasi wajib yang belum diselesaikan.

Validasi harus ditampilkan dalam bahasa manusia, misalnya:

- “Penerima kuitansi belum diisi.”
- “Masih ada 2 item yang belum memiliki uraian SPJ.”
- “Total nilai perjalanan melebihi nilai transaksi.”
- “Total rincian Rp2.250.000 tidak sesuai dengan nilai transaksi Rp750.000.”
- “Data ARKAS berubah setelah paket dibuat. Tinjau rekonsiliasi terlebih dahulu.”

Validasi dijalankan sebelum menyimpan perubahan yang memengaruhi nilai dan diulang sebelum penomoran. Paket yang sudah terlanjur dibuat tetapi nilainya tidak konsisten tetap dapat dibuka untuk koreksi, namun tidak dapat diberi nomor.

---

## 8. Skenario laporan

Sebagai operator/admin, saya ingin laporan triwulan mencerminkan dokumen yang sudah siap dan bernomor.

Alur:

1. User memilih triwulan.
2. Sistem menampilkan paket bernomor.
3. Sistem menampilkan transaksi yang belum siap.
4. Sistem menampilkan transaksi yang butuh rekonsiliasi.
5. User mengunduh laporan.

Aturan:

- laporan final hanya memakai dokumen valid;
- transaksi missing/reconciliation harus diberi tanda;
- sumber dana tidak boleh tercampur.

Untuk Honor Pegawai:

- satu transaksi tetap menghasilkan satu kuitansi;
- beberapa transaksi honor, misalnya BPU01–BPU05, dapat digabung dalam satu Laporan Daftar Pembayaran;
- laporan gabungan menampilkan nilai bruto, PPh 21 bila ada, nilai diterima, dan kolom tanda tangan;
- penggabungan laporan tidak menggabungkan identitas transaksi maupun kuitansinya.

---

## 9. Skenario kesalahan pengguna

### 9.1 Salah sekolah

Jika user membuka transaksi sekolah lain:

- redirect ke daftar transaksi aktif;
- tampilkan pesan “Transaksi tidak ditemukan pada sekolah/tahun aktif.”

### 9.2 Salah tahun/sumber dana

Jika user membuka transaksi tahun lain:

- redirect;
- minta pilih tahun yang benar.

### 9.3 Dokumen terkunci

Jika user mencoba mengedit dokumen terkunci:

- tampilkan pesan dokumen sudah bernomor/final;
- jangan simpan perubahan.

### 9.4 Form kategori kosong

Jika kategori SPJ belum dipilih:

- tampilkan panduan kategori;
- jangan buat paket.

### 9.5 Endpoint update terbuka sebagai halaman

Endpoint seperti `/transaksi/{id}/uraian-manual` adalah route update, bukan halaman.

Jika dibuka sebagai `GET`, boleh 404/redirect. Tombol UI tidak boleh mengarahkan user ke endpoint ini sebagai halaman.

### 9.6 Paket yang dibuka tidak sesuai status transaksi

Tombol **Buka paket SPJ** harus mencari paket berdasarkan relasi/ID transaksi. Jika transaksi menunjukkan **Nomor dibatalkan**, halaman paket yang dibuka harus menunjukkan status pembatalan yang sama dan tidak boleh mengambil status dari paket atau dokumen lain.

### 9.7 Pergantian tab SPJ

Tab utama Persiapan, Paket, Laporan, dan Monitoring menggunakan navigasi URL yang stabil (`?tab=...`). Pergantian tab tidak boleh mengganti root DOM secara manual atau menginisialisasi ulang pohon Alpine/Livewire karena dapat memutus state dan observer browser. `package_id` hanya dipertahankan ketika membuka tab Paket.

---

## 10. Prioritas implementasi berikutnya

Urutan yang disarankan:

1. Perluas test penerbitan ulang untuk seluruh jenis dokumen bernomor.
2. Lengkapi audit tampilan riwayat pembatalan dan penggantian nomor.
3. Selesaikan rekonsiliasi perubahan data ARKAS setelah paket bernomor.
4. Terapkan locking backend dan state non-editable secara merata pada seluruh form lama.
5. Tambahkan preview/simulasi penomoran triwulan sebelum eksekusi massal.
6. Lanjutkan migrasi modul SPJ ke komponen TALL secara bertahap tanpa manipulasi root DOM manual.

---

## 10.1 Skenario operasional antrean dan proses latar belakang

### Sinkronisasi berjalan normal

1. Operator menekan **Sinkron Semua ARKAS**.
2. Sistem membuat operasi dengan status `QUEUED` dan mengembalikan halaman tanpa menunggu sinkronisasi selesai.
3. Worker mengambil pekerjaan dan mengubah status menjadi `RUNNING`.
4. Setelah selesai, status menjadi `COMPLETED`, progres 100%, dan cache referensi terkait dibersihkan.
5. Dashboard menampilkan hasil sinkronisasi terbaru.

Aturan:

- worker memproses queue `operations` sebelum `default`;
- satu job boleh dicoba maksimal dua kali;
- batas waktu sinkronisasi adalah 900 detik;
- kredensial dan password ARKAS tidak boleh ditampilkan pada status atau log performa.

### Worker tidak aktif setelah komputer restart

Gejala:

- operasi terus berstatus `QUEUED`;
- progres tetap 0%;
- data ARKAS belum berubah meskipun tombol sinkronisasi sudah ditekan.

Tindakan:

1. Jalankan worker dari direktori project:

```text
php artisan queue:work --queue=operations,default --tries=2 --timeout=900 --sleep=2
```

2. Periksa antrean dengan:

```text
php artisan queue:monitor operations --max=100
```

3. Untuk instalasi operasional, daftarkan worker sebagai Windows Task Scheduler atau Windows Service agar otomatis hidup saat startup.

Aturan:

- worker yang dijalankan manual berhenti ketika terminal ditutup atau komputer restart;
- aplikasi tetap dapat dibuka tanpa worker, tetapi pekerjaan latar belakang tidak akan diproses;
- jangan menekan sinkronisasi berulang kali hanya karena status masih `QUEUED`.

### Sinkronisasi gagal

1. Worker mencoba pekerjaan sesuai nilai `--tries=2`.
2. Jika tetap gagal, operasi menjadi `FAILED`.
3. Dashboard menampilkan pesan aman tanpa password atau detail sensitif.
4. Administrator memeriksa `storage/logs/laravel.log`, koneksi database ARKAS, path bridge, dan ketersediaan database sekolah.
5. Setelah penyebab diperbaiki, administrator dapat mengulang sinkronisasi.

Aturan:

- kegagalan tidak boleh menghapus data manual SPJ yang sudah ada;
- job gagal tidak boleh dibiarkan terus berulang tanpa batas;
- retry manual hanya dilakukan setelah penyebab kegagalan diperiksa;
- jika job berhenti karena worker dimatikan saat proses berjalan, administrator harus memeriksa status data sebelum menjalankan ulang.

### Mencegah sinkronisasi ganda

TODO penting:

- tolak permintaan baru jika sekolah dan tahun yang sama masih memiliki operasi `QUEUED` atau `RUNNING`;
- sediakan tombol **Periksa status** pada dashboard;
- tampilkan waktu mulai, durasi, pengguna peminta, dan ringkasan hasil;
- sediakan aksi retry khusus administrator untuk operasi `FAILED`;
- tandai operasi lama sebagai macet jika `RUNNING` melewati batas waktu secara tidak wajar;
- simpan audit ketika job dibuat, dimulai, selesai, gagal, atau dicoba ulang.

### Pemantauan performa

- respons web membawa header `Server-Timing` untuk mengukur durasi aplikasi;
- request di atas ambang batas dicatat pada `storage/logs/performance-*.log`;
- query lambat dicatat tanpa nilai binding agar data sensitif tidak masuk log;
- administrator perlu meninjau log ketika halaman terasa lambat, sebelum menambah cache atau indeks baru;
- ukuran dan jumlah log perlu dibatasi melalui rotasi harian agar penyimpanan tidak terus membesar.

---

## 11. Checklist desain sebelum membuat fitur baru

Sebelum membuat fitur baru, jawab:

1. Role mana yang memakai fitur ini?
2. Apakah fitur ini untuk data ARKAS atau data manual?
3. Apakah fitur ini mengubah dokumen yang sudah bernomor?
4. Apakah fitur ini perlu audit log?
5. Apakah fitur ini harus dikunci per sekolah/tahun/sumber dana?
6. Apakah fitur ini berdampak ke penomoran?
7. Apakah fitur ini bisa menyebabkan data manual hilang saat sync?
8. Apakah istilah UI mudah dipahami operator?
