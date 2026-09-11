# Koreksi & Rollback Penomoran SPJ

Terakhir diperbarui: **2026-09-11**

Status: **IMPLEMENTED / FUNCTIONAL GATE PASS**

Dokumen ini menetapkan kontrak bisnis dan implementation guide untuk koreksi data setelah penomoran, pembatalan satu nomor/dokumen, rollback penomoran dari nomor tertentu, dan pembatalan penomoran triwulan.

Implementasi canonical sudah tersedia pada branch `gui-standardization` dan dibuktikan oleh regression deterministic pada functional gate berikut:

```text
code gate : a2509aad9104706da2709fcd07cce0b973282cb1
CI run    : 34595391755
CI job    : 103249843120
result    : PASS — 248 tests / 1880 assertions
```

Gunakan bersama:

- `SPJ_DESIGN_DECISIONS.md` untuk keputusan domain permanen;
- `CURRENT_PROGRESS.md` untuk status implementasi/evidence;
- `SYNCHRONIZATION.md` untuk source order/safe-sync ARKAS/BKU;
- `USER_SCENARIOS.md` untuk alur operator.

---

## 1. Tujuan utama penomoran

Urutan nomor SPJ harus konsisten dengan urutan pembukuan/transaksi ARKAS pada context tenant yang sama.

Boundary numbering, sequence, rollback, dan dependency adalah:

```text
School + Fiscal Year + Fund Source
```

Penomoran atau rollback pada satu sumber dana tidak boleh memblokir, mengubah counter, atau menghapus numbering sumber dana lain, tahun lain, atau sekolah lain.

Implementasi sequence sekarang memasukkan `fund_source_id` ke key `document_number_sequences`, sehingga counter satu sumber dana terisolasi dari sumber dana lain.

---

## 2. Tiga mekanisme yang berbeda

Aplikasi membedakan secara eksplisit:

1. **Cancel Dokumen / Cancel Nomor Individual**
2. **Rollback Penomoran dari Nomor Tertentu**
3. **Cancel Penomoran Triwulan**

Ketiganya mempunyai dampak sequence yang berbeda dan tidak boleh diperlakukan sebagai semantic yang sama.

---

## 3. Cancel Dokumen / Cancel Nomor Individual

Dipakai jika satu dokumen/transaksi memang dibatalkan secara bisnis, bukan karena salah urutan numbering.

Contoh:

```text
SPJ 005 diterbitkan dengan urutan yang benar
namun transaksi/dokumen tersebut kemudian dibatalkan
```

Maka:

```text
005 -> CANCELLED
```

Kontrak:

- nomor tetap menjadi history permanen;
- nomor tidak digunakan kembali;
- `sequence_number` tetap tersimpan;
- sequence global/domain tidak mundur;
- penerbitan dokumen berikutnya mengambil nomor setelah counter terakhir;
- reissue setelah cancel individual menghasilkan nomor baru, bukan menghidupkan kembali nomor cancelled;
- alasan, actor, dan timestamp pembatalan tetap diaudit.

Contoh:

```text
001 ACTIVE
002 CANCELLED
003 ACTIVE

nomor berikutnya -> 004
```

Cancel individual **bukan rollback numbering**.

---

## 4. Rollback Penomoran dari Nomor Tertentu

Dipakai untuk memperbaiki urutan numbering ketika urutan aplikasi tidak lagi sama dengan urutan pembukuan ARKAS.

Contoh:

```text
APP saat ini
01 02 03 04 05 06 07 08 09 10

setelah dibandingkan ARKAS:
transaksi yang sekarang mendapat 10 seharusnya berada pada urutan 8
```

Yang benar bukan mengubah `10 -> 8` secara langsung.

Rollback harus berjalan dari titik kesalahan sampai tail sequence:

```text
10 dilepas
09 dilepas
08 dilepas
sequence kembali ke 7
```

Lalu numbering ulang mengikuti canonical source order ARKAS:

```text
transaksi canonical urutan 8  -> 08
transaksi canonical urutan 9  -> 09
transaksi canonical urutan 10 -> 10
```

### Tail-only rule

Rollback hanya boleh dimulai dari nomor aktif yang benar-benar pernah diterbitkan dan harus melepas seluruh nomor aktif setelah titik tersebut.

Tidak diperbolehkan membuat hole dengan melepas satu nomor di tengah tanpa melepas tail setelahnya.

### Guard nomor cancelled permanen

Rollback **tidak boleh melintasi nomor SPJ yang telah dibatalkan secara individual**.

Contoh:

```text
001 ACTIVE
002 CANCELLED permanen
003 ACTIVE
004 ACTIVE
```

Rollback dari `001` atau `002` ditolak, karena `002` tidak boleh tersedia kembali untuk dipakai ulang.

Rollback dari `003` masih dapat dilakukan karena tidak melewati history cancelled permanen.

Guard ini menjaga dua kontrak sekaligus:

- cancel individual tetap immutable sebagai history;
- rollback hanya menggunakan kembali nomor yang memang dilepas oleh proses rollback.

---

## 5. Cancel Penomoran Triwulan

Cancel penomoran triwulan adalah rollback batch untuk seluruh numbering aktif pada triwulan target dalam context yang sama.

Urutan penerbitan:

```text
TW1 -> TW2 -> TW3 -> TW4
```

Urutan pembatalan:

```text
TW4 -> TW3 -> TW2 -> TW1
```

Rule:

- TW4 dapat dibatalkan jika memenuhi guard lifecycle lainnya;
- TW3 tidak dapat dibatalkan bila TW4 masih mempunyai numbering aktif;
- TW2 tidak dapat dibatalkan bila TW3 atau TW4 masih mempunyai numbering aktif;
- TW1 tidak dapat dibatalkan bila TW2, TW3, atau TW4 masih mempunyai numbering aktif.

Dependency hanya dihitung untuk:

```text
School + Fiscal Year + Fund Source
```

Numbering TW2 BOS Reguler tidak boleh memblokir rollback TW1 pada sumber dana lain.

### Checkpoint sequence

Konsep canonical:

```text
Akhir TW1 = 120
Akhir TW2 = 245
Akhir TW3 = 380
```

Cancel TW3:

```text
numbering aktif TW3 dilepas
sequence dibangun ulang dari numbering yang masih sah
checkpoint efektif kembali ke 245
```

Cancel TW2 setelah TW3 sudah dilepas:

```text
checkpoint efektif kembali ke 120
```

Cancel TW1 jika tidak ada history nomor permanen sebelumnya:

```text
sequence menjadi kosong / efektif 0
numbering berikutnya dimulai dari 1
```

### Guard cancel individual di dalam triwulan

Full quarter reset ditolak bila triwulan target memiliki SPJ `CANCELLED` hasil pembatalan individual permanen.

Alasannya: full quarter reset tidak boleh membuat nomor cancelled permanen tersedia kembali.

Jika kondisi tersebut terjadi, administrator harus menyelesaikan koreksi melalui lifecycle yang tidak melanggar history nomor permanen.

---

## 6. Apa yang terjadi saat rollback

Rollback dijalankan dalam transaction database sekolah.

Untuk Paket terdampak:

- numbering aktif pada Paket dilepas;
- `spj_documents` non-CANCELLED hasil numbering rollback dihapus dari numbering-domain history;
- `spj_documents` yang sudah `CANCELLED` secara individual dipertahankan;
- nomor turunan yang berasal dari dokumen yang dilepas dibersihkan, termasuk bila applicable:
  - `order_number`;
  - `bap_number`;
  - `bast_number`;
  - `spk_number`;
  - `rab_number`;
  - nomor surat tugas perjalanan dinas;
- Paket dikembalikan ke `DRAFT`;
- current `document_number`, `numbered_at`, finalization/snapshot state yang terkait numbering aktif dibersihkan;
- sequence dibangun ulang dari nomor yang masih valid pada context aktif;
- operational audit rollback tetap disimpan.

Rollback bukan edit langsung terhadap source ARKAS/BKU.

---

## 7. Riwayat numbering vs operational audit

Dua jenis riwayat harus dibedakan.

### Numbering-domain history

Untuk numbering yang benar-benar di-rollback:

- identity numbering aktif dilepas;
- nomor dapat tersedia kembali sesuai checkpoint;
- run penomoran triwulan target dapat dihapus/reset agar tidak dianggap run aktif/berlaku.

### Operational audit

Operational audit **tidak dihapus**.

Audit harus tetap dapat menjelaskan minimal:

```text
siapa
kapan
context fiscal year + fund source
rollback dari nomor berapa / triwulan berapa
alasan
```

Dengan demikian operator dapat memperbaiki sequence tanpa menghilangkan accountability tindakan administrator.

---

## 8. Koreksi data setelah NUMBERED

### 8.1 `item_description`

Satu-satunya koreksi Detail Transaksi yang tetap diperbolehkan ketika Paket berstatus `NUMBERED` adalah:

```text
item_description
```

Perubahan ini:

- tidak membatalkan nomor;
- tidak menurunkan Paket menjadi DRAFT;
- tidak mengubah sequence;
- tidak mengubah source description ARKAS/BKU;
- dapat tercermin pada rendering dokumen berikutnya selama lifecycle dokumen mengizinkan.

Jika Paket sudah `FINAL`, `item_description` terkunci dan harus melalui lifecycle koreksi resmi terlebih dahulu.

### 8.2 Data Paket/manual/payment

Perubahan berikut pada `NUMBERED`/`FINAL` tidak boleh dilakukan langsung:

- `spj_category`;
- payment description/method/reference;
- penerima utama;
- vendor/penyedia;
- invoice / metadata procurement operator-owned;
- data barang/pengadaan;
- peserta konsumsi;
- pekerja pemeliharaan;
- penerima honor;
- pelaksana SPPD;
- penerima JASA_LAINNYA;
- Isian Manual Paket lainnya yang memengaruhi substansi dokumen.

Untuk memperbaikinya:

```text
NUMBERED
  -> rollback numbering yang sesuai
  -> DRAFT
  -> perbaiki kategori/isian
  -> validasi ulang
  -> READY
  -> numbering ulang berdasarkan canonical ARKAS order
```

`FINAL` tetap lebih ketat dan tidak boleh dimutasi melalui jalur edit normal.

---

## 9. Source order authoritative

Nomor SPJ tidak boleh mengikuti:

- local package id;
- waktu operator membuka Paket;
- waktu Paket menjadi READY;
- urutan klik operator.

Numbering ulang tetap menggunakan `SpjNumberingOrderService`/canonical source order yang sudah dipakai aplikasi, termasuk source event/order ARKAS yang relevan.

Tujuannya:

```text
urutan SPJ = urutan pembukuan ARKAS
```

selama source canonical tidak berubah.

---

## 10. UI dan authorization

Fitur correction/rollback tersedia melalui jalur administrator.

UI menyediakan:

- rollback dari nomor sequence tertentu + alasan;
- cancel numbering triwulan + alasan;
- daftar nomor SPJ pada context aktif untuk membantu menentukan titik rollback.

Mutation backend tetap authoritative. Menyembunyikan tombol saja tidak cukup sebagai authorization.

---

## 11. Regression yang menjaga kontrak

Regression utama berada pada:

```text
tests/Feature/SpjNumberingRollbackTest.php
tests/Feature/DocumentNumberingWorkflowTest.php
tests/Feature/SpjOwnershipMigrationTest.php
tests/Feature/SpjWorkspaceMigrationTest.php
```

Gate membuktikan antara lain:

- individual cancel tidak reuse nomor;
- reissue setelah cancel individual mendapat sequence baru;
- tail rollback menghapus numbering aktif dan mengembalikan sequence;
- later quarter pada context yang sama memblokir rollback quarter lebih lama;
- later quarter pada fund source lain tidak memblokir;
- sequence sumber dana lain tidak berubah;
- `item_description` editable pada NUMBERED;
- `item_description` terkunci pada FINAL;
- manual Package mutation tetap terkunci pada NUMBERED/FINAL;
- migration sequence per fund source dapat dijalankan oleh suite tenant migration.

Functional evidence terbaru:

```text
commit : a2509aad9104706da2709fcd07cce0b973282cb1
CI run : 34595391755
CI job : 103249843120
PASS   : 248 tests / 1880 assertions
```

Catatan: Pint masih advisory/non-blocking dan pada gate ini melaporkan 3 style issues repository. Functional regression, frontend build, dan Blade compile tetap PASS.
