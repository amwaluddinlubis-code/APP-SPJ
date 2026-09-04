# SPJ BOSP Web — Rencana Pengembangan

Terakhir diperbarui: 2026-09-04

Dokumen ini menjadi acuan urutan pengembangan setelah fondasi transaksi, sinkronisasi ARKAS, paket SPJ, template dokumen, penomoran, database tenant, dan design system utama mulai terbentuk.

## 1. Tujuan utama fase berikutnya

Fokus berikutnya bukan membangun ulang GUI atau menambah banyak menu baru, tetapi memastikan operator sekolah dapat menyelesaikan satu alur SPJ dari awal sampai final dengan langkah yang jelas, aman, dan konsisten.

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
- UI harus selalu menunjukkan langkah berikutnya kepada operator;
- fitur baru harus memakai primitive UI theme-aware yang sudah tersedia.

---

## 2. Fondasi yang sudah dianggap tersedia

### 2.1 Arsitektur use case SPJ

`SpjController` sudah dipisahkan ke:

```text
SpjWorkspaceUseCase
SpjPackageUseCase
SpjNumberingUseCase
SpjDocumentUseCase
SpjReportUseCase
```

Pekerjaan berikutnya bukan menggabungkannya kembali, tetapi menjaga boundary use case tetap jelas dan memindahkan logic reusable ke service bila perlu.

### 2.2 Database tenant

Sudah tersedia provision, migrate, backup/restore, dan reset database sekolah aktif secara penuh. Reset tenant melakukan rebuild file SQLite sehingga sequence/auto-increment kembali dari awal tanpa menyentuh database global.

### 2.3 Design system GUI

Sudah tersedia fondasi global:

- sidebar persisten;
- breadcrumb sticky;
- page header/summary;
- form primitives;
- table system;
- pagination/per-page;
- alert/empty/modal/badge/detail/toolbar;
- loading/skeleton;
- action menu;
- sticky actions;
- danger zone;
- sticky scroll-to-top;
- dynamic theme + dark mode;
- compatibility layer untuk view legacy.

Karena fondasi GUI sudah tersedia, pekerjaan berikutnya adalah migrasi view yang tersisa dan konsistensi pemakaian, bukan membuat sistem visual baru.

---

## 3. Prioritas P0 — wajib sebelum dianggap stabil

### 3.1 Stabilkan generator dokumen PDF/Excel/Word

Target:

- Paket PDF dapat dibuat dan ditampilkan tanpa blank/error.
- Kop surat, identitas sekolah, kepala sekolah, bendahara, penerima, rincian transaksi, pajak, dan nomor dokumen konsisten pada semua format.
- Generator Excel tetap mendukung `{{KOP_SURAT}}` sebagai anchor gambar.
- Kesalahan template ditampilkan dengan pesan yang mudah dipahami operator.

### 3.2 Pratinjau dokumen di browser yang sama

Target UX:

- klik `Pratinjau` tidak membuka tab baru;
- preview tampil pada halaman yang sama;
- tersedia tombol kembali ke paket;
- preview Excel ditampilkan sebagai HTML;
- preview Paket PDF inline;
- preview tidak memberi nomor, tidak mengubah status menjadi DICETAK, dan tidak mengubah data paket.

### 3.3 Finalisasi lifecycle/status SPJ

Target operator:

```text
Belum lengkap
→ Siap diproses
→ Sudah bernomor
→ Sudah dicetak
→ Final
```

Backend dapat tetap memakai:

```text
DRAFT → READY → NUMBERED → DICETAK/PRINTED → FINAL/ARCHIVED
```

Aturan:

- DRAFT tidak boleh langsung dinomori/download final tanpa proses jelas;
- READY berarti seluruh data wajib valid;
- NUMBERED berarti nomor resmi diterbitkan;
- preview tidak mengubah status;
- FINAL tidak boleh diedit tanpa revisi/buka kunci yang sah.

### 3.4 Penomoran triwulan final

Target:

- pilih triwulan;
- hanya dokumen READY menjadi kandidat;
- tersedia simulasi nomor sebelum commit;
- nomor dipisahkan per jenis dokumen;
- nomor aktif tidak ditimpa;
- nomor ganda dicegah;
- commit atomik;
- hasil tercatat pada audit log.

### 3.5 Penguncian dan finalisasi dokumen

Target:

- paket bernomor terkunci dari perubahan normal;
- paket final terkunci penuh;
- koreksi hanya melalui pembatalan/revisi/buka kunci sesuai role;
- nomor lama tersimpan dalam riwayat;
- alasan pembatalan/penggantian wajib;
- audit menampilkan siapa, kapan, dan apa yang berubah.

---

## 4. Prioritas P1 — keandalan data dan kontrol internal

### 4.1 Rekonsiliasi ARKAS dengan snapshot sebelum/sesudah

Kondisi sekarang:

- transaksi dapat ditandai `SOURCE_MISSING`;
- transaksi dapat ditandai `requires_reconciliation`;
- paket/manual operator dipertahankan saat sumber berubah/hilang.

Pengembangan berikutnya:

- simpan snapshot sumber sebelum perubahan;
- simpan snapshot sesudah perubahan;
- tampilkan diff yang mudah dibaca operator;
- sediakan keputusan rekonsiliasi eksplisit;
- jangan mengubah dokumen final secara otomatis.

### 4.2 Hardening role dan authorization

Role:

- ADMIN;
- OPERATOR;
- VIEWER.

Target:

- VIEWER benar-benar read-only pada backend;
- route POST/PUT/PATCH/DELETE dilindungi backend;
- aksi sensitif seperti pembatalan nomor, buka kunci, finalisasi, reset/restore database, dan konfigurasi memiliki authorization eksplisit;
- tes authorization dibuat per role.

### 4.3 End-to-end test operator sekolah

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

Harus mencakup Barang, Konsumsi, Pemeliharaan, SPPD, Honor Pegawai, dan Jasa Lainnya.

### 4.4 Test operasional database tenant

Sebelum release:

- backup/restore diuji end-to-end;
- reset tenant diuji dengan database nyata development;
- setelah reset, ID/auto-increment kembali dari awal;
- database global tetap utuh;
- session tenant setelah reset tidak menyisakan tahun/sumber dana lama.

---

## 5. Prioritas P2 — peningkatan kenyamanan operator

### 5.1 Satu CTA utama sesuai status

Contoh:

- belum lengkap → `Lengkapi data`;
- lengkap tetapi DRAFT → `Tandai siap`;
- READY → `Siap untuk penomoran`;
- NUMBERED → `Pratinjau dokumen`;
- sudah diperiksa → `Unduh / Cetak`;
- sudah dicetak → `Finalkan`.

### 5.2 Validasi dekat field

- field bermasalah diberi indikator visual;
- pesan validasi dekat field;
- checklist memiliki `Perbaiki sekarang`;
- pesan menggunakan bahasa operator.

### 5.3 Peringatan perubahan belum disimpan

Untuk form panjang, deteksi dirty state dan beri peringatan sebelum user meninggalkan halaman.

### 5.4 Pencarian global

Cari nomor bukti, nomor SPJ, penerima, uraian, rekanan, kode rekening, dan kode kegiatan.

### 5.5 Filter persisten

Filter bulan, triwulan, kategori, status, dan pencarian sebaiknya dipertahankan ketika user kembali dari detail.

### 5.6 Workspace per triwulan

Ringkasan:

- total transaksi;
- belum lengkap;
- siap dinomori;
- sudah bernomor;
- sudah dicetak;
- final;
- perlu rekonsiliasi.

### 5.7 Riwayat aktivitas paket

Timeline:

```text
Dibuat → Dilengkapi → Siap → Dinomori → Dipreview → Dicetak → Final
```

### 5.8 `Simpan & buka berikutnya`

Untuk pekerjaan massal operator.

### 5.9 Migrasi view legacy ke design system

Fondasi alert, empty state, tabel, pagination, toolbar, tabs, loading, modal, detail, badge, action menu, danger zone, dan sticky actions sudah tersedia.

Target berikutnya:

- hapus hard-coded accent color pada view yang disentuh;
- gunakan `x-ui.*` secara konsisten;
- kurangi Tailwind markup panjang;
- tetap pertahankan compatibility layer sampai migrasi cukup luas.

---

## 6. Prioritas P3 — dokumen dan laporan lengkap

Setelah workflow inti stabil:

- K7A;
- K7;
- K8;
- SPTJM;
- K7B;
- K7C;
- Buku Pembantu Kas;
- Buku Pembantu Bank;
- Buku Pembantu Pajak;
- laporan bulanan;
- rekap belanja modal;
- rekap barang/jasa;
- daftar pembayaran honor;
- batch export dokumen per transaksi/triwulan.

---

## 7. Standar UX yang dipakai mulai sekarang

1. User selalu tahu konteks sekolah, tahun anggaran, dan sumber dana aktif.
2. User selalu tahu status transaksi/paket.
3. User selalu tahu langkah berikutnya.
4. Aksi destruktif selalu memakai konfirmasi dan authorization backend.
5. Preview tidak memiliki efek samping pada data/status.
6. Tombol dengan fungsi sama memakai label dan primitive konsisten.
7. Status sistem diterjemahkan ke bahasa manusia.
8. Form kategori hanya menampilkan field relevan.
9. Desktop menjadi workspace utama, mobile/tablet tetap usable.
10. Accent non-semantik mengikuti tema aktif.
11. Success/warning/danger tetap semantik.
12. Perubahan UI tidak boleh melemahkan validasi dan authorization backend.

---

## 8. Urutan pengerjaan yang disepakati

```text
1. Stabilkan PDF/dokumen
2. Preview terpadu di browser yang sama
3. Finalisasi lifecycle/status SPJ
4. Finalisasi penomoran triwulan
5. Finalisasi locking/revisi
6. Rekonsiliasi ARKAS dengan snapshot/diff
7. Hardening role/authorization
8. Uji end-to-end operator
9. Migrasi view tersisa + UX produktivitas
10. Penyempurnaan dokumen & laporan BOS
```

---

## 9. Definisi siap rilis

Versi dianggap layak kandidat rilis jika:

- satu transaksi dapat diproses dari sinkronisasi sampai final tanpa intervensi database manual;
- seluruh kategori utama dapat diselesaikan;
- preview dan download konsisten;
- penomoran tidak ganda dan tidak berubah diam-diam;
- data manual tidak hilang setelah sinkronisasi;
- dokumen final tidak dapat diedit tanpa revisi;
- VIEWER tidak dapat mutation;
- test kritis lulus;
- alur operator diuji melalui browser;
- backup/restore dan reset database sekolah diuji;
- frontend build berhasil dengan design system/theme aktif.

Dokumen ini harus dibaca bersama:

```text
docs/SPJ_DESIGN_DECISIONS.md
docs/CURRENT_PROGRESS.md
docs/USER_SCENARIOS.md
docs/GUI_STANDARDIZATION.md
```
