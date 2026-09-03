# SPJ BOSP Web — Rencana Pengembangan

Terakhir diperbarui: 2026-09-04

Dokumen ini menjadi acuan urutan pengembangan setelah fondasi transaksi, sinkronisasi ARKAS, paket SPJ, template dokumen, penomoran, dan dashboard operasional mulai terbentuk.

## 1. Tujuan utama fase berikutnya

Fokus berikutnya bukan menambah banyak menu baru, tetapi memastikan operator sekolah dapat menyelesaikan satu alur SPJ dari awal sampai final dengan langkah yang jelas, aman, dan konsisten.

Alur target:

```text
Transaksi ARKAS/BKU
→ Lengkapi data SPJ
→ Validasi
→ READY
→ Penomoran
→ Pratinjau
→ Unduh/Cetak
→ FINAL/ARSIP
```

Prinsip utama:

- data sumber ARKAS/BKU tidak boleh menimpa data manual operator;
- pratinjau tidak boleh mengubah status dokumen;
- penomoran tidak boleh terjadi diam-diam saat preview/download;
- dokumen bernomor/final harus terkunci;
- perubahan setelah penomoran harus tercatat dalam audit/revisi;
- UI harus selalu menunjukkan langkah berikutnya kepada operator.

---

## 2. Prioritas P0 — wajib sebelum dianggap stabil

### 2.1 Stabilkan generator dokumen PDF/Excel/Word

Target:

- Paket PDF dapat dibuat dan ditampilkan tanpa blank/error.
- Kop surat, identitas sekolah, kepala sekolah, bendahara, penerima, rincian transaksi, pajak, dan nomor dokumen konsisten pada semua format.
- Generator Excel tetap mendukung `{{KOP_SURAT}}` sebagai anchor gambar.
- Kesalahan template ditampilkan dengan pesan yang mudah dipahami operator.

Catatan terbaru:

- kop surat sekolah sudah tersimpan melalui `schools.letterhead_path` dan file dapat dibaca dari disk `local`;
- generator Excel telah terbukti dapat menampilkan kop surat jika template memiliki placeholder `{{KOP_SURAT}}` pada sel yang tepat;
- PDF masih perlu diuji penuh dari browser setelah generator dan response HTTP dipastikan stabil.

### 2.2 Pratinjau dokumen di browser yang sama

Target UX:

- klik `Pratinjau` tidak membuka tab browser baru;
- preview tampil pada halaman browser yang sama;
- tersedia tombol kembali ke paket yang sedang dikerjakan;
- preview Excel ditampilkan sebagai HTML;
- preview Paket PDF ditampilkan inline di browser;
- preview tidak memberi nomor, tidak mengubah status menjadi `DICETAK`, dan tidak mengubah data paket.

Urutan implementasi:

1. Preview Excel pada browser yang sama.
2. Preview Paket PDF inline.
3. Preview template Word melalui konversi PDF jika dukungan runtime tersedia.

### 2.3 Finalisasi lifecycle/status SPJ

Status target yang digunakan operator:

```text
Belum lengkap
→ Siap diproses
→ Sudah bernomor
→ Sudah dicetak
→ Final
```

Status teknis yang dapat tetap digunakan backend:

```text
DRAFT → READY → NUMBERED → DICETAK/PRINTED → FINAL/ARCHIVED
```

Aturan:

- DRAFT tidak boleh langsung dinomori/download final tanpa proses yang jelas;
- READY berarti seluruh data wajib valid;
- NUMBERED berarti nomor sudah resmi diterbitkan;
- preview tidak mengubah status;
- aksi cetak/download final dapat mengubah status ke DICETAK hanya jika memang itu keputusan workflow;
- FINAL tidak boleh diedit tanpa mekanisme revisi/buka kunci yang sah.

### 2.4 Penomoran triwulan final

Target:

- operator/admin memilih triwulan;
- hanya dokumen READY yang masuk kandidat;
- tersedia simulasi nomor sebelum commit;
- nomor dipisahkan per jenis dokumen;
- tanggal dokumen menentukan domain urutan sesuai aturan bisnis;
- nomor aktif tidak ditimpa;
- nomor ganda dicegah oleh database dan service;
- commit dilakukan atomik;
- seluruh hasil tercatat pada audit log.

Download atau preview dokumen tidak boleh menjadi jalan pintas yang otomatis membuat nomor baru.

### 2.5 Penguncian dan finalisasi dokumen

Target:

- paket bernomor terkunci dari perubahan normal;
- paket final terkunci penuh;
- koreksi hanya melalui pembatalan/revisi/buka kunci sesuai role;
- nomor lama tetap tersimpan dalam riwayat;
- alasan pembatalan/penggantian wajib diisi;
- audit menampilkan siapa, kapan, dan apa yang berubah.

---

## 3. Prioritas P1 — keandalan data dan kontrol internal

### 3.1 Rekonsiliasi ARKAS dengan snapshot sebelum/sesudah

Kondisi sekarang:

- transaksi dapat ditandai `SOURCE_MISSING`;
- transaksi dapat ditandai `requires_reconciliation`;
- paket/manual operator dipertahankan saat sumber berubah/hilang.

Pengembangan berikutnya:

- simpan snapshot sumber ARKAS sebelum perubahan;
- simpan snapshot sumber setelah perubahan;
- tampilkan diff yang mudah dibaca operator;
- sediakan keputusan rekonsiliasi yang eksplisit;
- jangan mengubah dokumen final secara otomatis.

### 3.2 Hardening role dan authorization

Role:

- ADMIN;
- OPERATOR;
- VIEWER.

Target:

- VIEWER benar-benar read-only pada backend;
- route POST/PUT/PATCH/DELETE tidak cukup hanya disembunyikan dari UI;
- aksi sensitif seperti pembatalan nomor, buka kunci, finalisasi, dan konfigurasi memiliki authorization yang eksplisit;
- tes authorization dibuat per role.

### 3.3 End-to-end test operator sekolah

Skenario wajib:

```text
Login
→ Pilih sekolah
→ Pilih tahun & sumber dana
→ Sinkron ARKAS/BKU
→ Buka transaksi
→ Lengkapi data SPJ
→ Validasi
→ READY
→ Penomoran triwulan
→ Preview dokumen
→ Unduh/cetak
→ Final
```

Pengujian harus mencakup kategori:

- Barang;
- Konsumsi;
- Pemeliharaan;
- SPPD/Perjalanan Dinas;
- Honor Pegawai;
- Jasa Lainnya.

---

## 4. Prioritas P2 — peningkatan kenyamanan operator

### 4.1 Satu tombol aksi utama sesuai status

Setiap halaman paket harus mempunyai satu CTA utama berdasarkan kondisi saat ini.

Contoh:

- belum lengkap → `Lengkapi data`;
- lengkap tetapi DRAFT → `Tandai siap`;
- READY → `Siap untuk penomoran`;
- NUMBERED → `Pratinjau dokumen`;
- sudah diperiksa → `Unduh / Cetak`;
- sudah dicetak → `Finalkan`.

Aksi sekunder tetap tersedia tetapi tidak boleh mengalahkan CTA utama.

### 4.2 Validasi dekat field

Target:

- field yang bermasalah diberi indikator visual;
- pesan validasi tampil dekat field;
- checklist memiliki tombol `Perbaiki sekarang` yang scroll/fokus ke bagian yang tepat;
- pesan menggunakan bahasa operator, bukan nama kolom database.

### 4.3 Peringatan perubahan belum disimpan

Untuk form panjang:

- deteksi perubahan form;
- tampilkan peringatan jika user berpindah halaman sebelum menyimpan;
- hindari kehilangan data saat operator tidak sengaja menutup halaman.

Auto-save dapat dipertimbangkan setelah lifecycle/locking stabil.

### 4.4 Pencarian global

Pencarian sebaiknya dapat menemukan:

- nomor bukti;
- nomor SPJ;
- penerima BKU;
- penerima kuitansi;
- uraian pembayaran;
- nama toko/rekanan;
- kode rekening/kegiatan.

Hasil pencarian harus membawa operator langsung ke transaksi/paket yang dimaksud.

### 4.5 Filter persisten

Filter transaksi/paket seperti:

- bulan;
- triwulan;
- kategori;
- status;

sebaiknya tetap dipertahankan ketika user membuka detail lalu kembali ke daftar.

### 4.6 Workspace per triwulan

Sediakan ringkasan per triwulan:

- total transaksi;
- belum lengkap;
- siap dinomori;
- sudah bernomor;
- sudah dicetak;
- final;
- perlu rekonsiliasi.

Tujuannya agar operator bekerja per periode tanpa harus menyusun filter berulang kali.

### 4.7 Riwayat aktivitas per paket

Tampilkan timeline ringkas:

```text
Dibuat → Dilengkapi → Siap → Dinomori → Dipreview → Dicetak → Final
```

Untuk aksi koreksi:

```text
Nomor dibatalkan → Paket dibuka → Dikoreksi → Dinomori ulang
```

### 4.8 `Simpan & buka berikutnya`

Untuk pekerjaan massal, tambahkan aksi:

```text
Simpan & buka transaksi/paket berikutnya
```

Ini mengurangi jumlah klik ketika operator mengerjakan banyak SPJ sekaligus.

### 4.9 Empty state dan pesan error yang membantu

Contoh:

- bukan `Tidak ada data`;
- gunakan `Belum ada paket SPJ. Mulai dari transaksi yang sudah memiliki rincian.`

Error runtime/database tidak boleh ditampilkan mentah kepada operator.

---

## 5. Prioritas P3 — dokumen dan laporan lengkap

Setelah workflow inti stabil, lanjutkan penyempurnaan:

- K7A;
- K7;
- K8;
- SPTJM;
- K7B Register Penutupan Kas;
- K7C Berita Acara Pemeriksaan Kas;
- Buku Pembantu Kas;
- Buku Pembantu Bank;
- Buku Pembantu Pajak;
- laporan bulanan;
- rekap belanja modal;
- rekap barang/jasa;
- daftar pembayaran honor;
- batch export dokumen per transaksi/triwulan.

Semua laporan harus memakai sumber data dan lifecycle yang sama agar tidak muncul angka berbeda antar modul.

---

## 6. Standar UX yang dipakai mulai sekarang

Setiap fitur baru/ubah harus mengikuti prinsip berikut:

1. User selalu tahu konteks sekolah, tahun anggaran, dan sumber dana aktif.
2. User selalu tahu status transaksi/paket saat ini.
3. User selalu tahu apa yang harus dilakukan berikutnya.
4. Aksi destruktif selalu meminta konfirmasi dan alasan bila diperlukan.
5. Preview tidak memiliki efek samping pada data/status.
6. Tombol dengan fungsi sama memakai label dan tampilan konsisten.
7. Status sistem diterjemahkan ke bahasa manusia.
8. Form kategori hanya menampilkan field yang relevan.
9. Tampilan desktop menjadi prioritas, tetapi mobile/tablet tetap dapat digunakan.
10. Perubahan UI tidak boleh melemahkan validasi dan authorization backend.

---

## 7. Urutan pengerjaan yang disepakati

Urutan kerja utama:

```text
1. Stabilkan PDF/dokumen
2. Preview terpadu di browser yang sama
3. Finalisasi lifecycle/status SPJ
4. Finalisasi penomoran triwulan
5. Finalisasi locking/revisi
6. Rekonsiliasi ARKAS dengan snapshot/diff
7. Hardening role/authorization
8. Uji end-to-end operator
9. UX produktivitas: pencarian, filter persisten, simpan & berikutnya
10. Penyempurnaan dokumen & laporan BOS
```

Jangan menambah fitur besar di luar urutan ini jika masih ada blocker pada P0, kecuali perubahan tersebut diperlukan untuk memperbaiki blocker.

---

## 8. Definisi siap rilis

Versi dianggap layak kandidat rilis jika:

- satu transaksi dapat diproses dari sinkronisasi sampai final tanpa intervensi database manual;
- seluruh kategori utama memiliki alur SPJ yang dapat diselesaikan;
- preview dan download dokumen bekerja konsisten;
- penomoran tidak ganda dan tidak berubah diam-diam;
- data manual tidak hilang setelah sinkronisasi ARKAS;
- dokumen final tidak dapat diedit tanpa prosedur revisi;
- VIEWER tidak dapat melakukan mutation;
- test kritis lulus;
- alur operator diuji melalui browser;
- backup/restore database sekolah diuji sebelum rilis.

Dokumen ini harus dibaca bersama:

```text
docs/SPJ_DESIGN_DECISIONS.md
docs/CURRENT_PROGRESS.md
docs/USER_SCENARIOS.md
docs/GUI_STANDARDIZATION.md
```
