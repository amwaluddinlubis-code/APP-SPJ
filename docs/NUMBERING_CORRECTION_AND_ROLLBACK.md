# Koreksi & Rollback Penomoran SPJ

Terakhir diperbarui: **2026-09-11**

Status: **ACTIVE DOMAIN GUIDE / IMPLEMENTATION PENDING**

Dokumen ini menetapkan kontrak bisnis untuk koreksi data setelah penomoran, pembatalan satu nomor/dokumen, rollback penomoran dari nomor tertentu, dan pembatalan penomoran triwulan.

Dokumen ini belum menyatakan fitur sudah diterapkan. Implementasi aplikasi dan regression test harus mengikuti kontrak ini pada pekerjaan berikutnya.

Gunakan bersama:

- `SPJ_DESIGN_DECISIONS.md` untuk keputusan domain permanen;
- `CURRENT_PROGRESS.md` untuk status implementasi/evidence;
- `SYNCHRONIZATION.md` untuk source order/safe-sync ARKAS/BKU;
- `USER_SCENARIOS.md` untuk alur operator.

---

## 1. Tujuan utama penomoran

Urutan nomor SPJ harus konsisten dengan urutan pembukuan/transaksi ARKAS pada context tenant yang sama.

Boundary numbering dan dependency adalah:

```text
School + Fiscal Year + Fund Source
```

Penomoran atau rollback pada satu sumber dana tidak boleh memblokir atau mengubah sumber dana lain, tahun lain, atau sekolah lain.

Contoh:

```text
Sekolah A / 2026 / BOS Reguler
```

harus terisolasi dari:

```text
Sekolah A / 2026 / BOS Kinerja
Sekolah A / 2025 / BOS Reguler
Sekolah B / 2026 / BOS Reguler
```

---

## 2. Tiga mekanisme yang berbeda

Aplikasi harus membedakan secara eksplisit:

1. **Cancel Dokumen / Cancel Nomor Individual**
2. **Rollback Penomoran dari Nomor Tertentu**
3. **Cancel Penomoran Triwulan**

Ketiganya mempunyai dampak sequence yang berbeda dan tidak boleh menggunakan semantic yang sama.

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

- nomor tetap menjadi history;
- nomor tidak digunakan kembali;
- sequence tidak mundur;
- dokumen cancelled tetap dapat diaudit;
- pembatalan harus mempunyai alasan, actor, dan timestamp.

Jadi aturan lama **“nomor cancelled tetap menjadi history” hanya berlaku untuk pembatalan individual/dokumen secara bisnis**.

---

## 4. Rollback Penomoran dari Nomor Tertentu

Dipakai jika ditemukan kesalahan urutan penomoran dibanding urutan pembukuan ARKAS.

Contoh:

```text
Nomor aktif: 01 02 03 04 05 06 07 08 09 10
```

Setelah dibandingkan dengan ARKAS, transaksi yang sekarang bernomor `10` seharusnya berada pada urutan ke-8.

Tidak boleh hanya mengganti `10 -> 08`.

Yang benar:

```text
rollback: 10 -> 09 -> 08
sequence kembali ke 7
urutkan kembali berdasarkan source order ARKAS
terbitkan ulang mulai 08
```

Kontrak utama:

- rollback hanya boleh dimulai dari nomor tertentu sampai **ekor sequence**;
- tidak boleh membuat lubang numbering aktif;
- seluruh numbering >= nomor awal rollback dilepas;
- sequence kembali ke nomor sebelum titik rollback;
- nomor yang dilepas boleh dipakai kembali;
- history numbering domain dari hasil rollback tidak dianggap nomor cancelled permanen;
- operational audit rollback tetap dipertahankan.

Contoh:

```text
rollback mulai 08

08, 09, 10 -> dilepas
sequence -> 7

numbering ulang:
08, 09, 10 -> diterbitkan kembali sesuai source order ARKAS
```

---

## 5. Cancel Penomoran Triwulan

Cancel Penomoran Triwulan adalah rollback seluruh hasil numbering pada satu triwulan dalam context tenant yang sama.

Urutan pembuatan numbering berjalan maju:

```text
TW1 -> TW2 -> TW3 -> TW4
```

Urutan pembatalan wajib berjalan mundur:

```text
TW4 -> TW3 -> TW2 -> TW1
```

### Dependency rule

- TW4 dapat dibatalkan jika memenuhi guard lain.
- TW3 hanya boleh dibatalkan jika TW4 tidak mempunyai numbering aktif.
- TW2 hanya boleh dibatalkan jika TW3 dan TW4 tidak mempunyai numbering aktif.
- TW1 hanya boleh dibatalkan jika TW2, TW3, dan TW4 tidak mempunyai numbering aktif.

Pengecekan dependency hanya pada:

```text
School + Fiscal Year + Fund Source yang sama
```

Numbering TW2 BOS Reguler tidak boleh memblokir TW1 BOS Kinerja.

---

## 6. Reset sequence setelah cancel triwulan

Cancel triwulan mengembalikan sequence ke checkpoint terakhir triwulan sebelumnya.

Contoh:

```text
akhir TW1 = 120
akhir TW2 = 245
akhir TW3 = 380
```

Maka:

```text
Cancel TW3 -> sequence = 245
Cancel TW2 -> sequence = 120
Cancel TW1 -> sequence = 0
```

Jika TW1 dibatalkan, numbering berikutnya dimulai kembali dari `1`.

Nomor dari triwulan yang di-rollback boleh digunakan kembali karena yang dibatalkan adalah **hasil proses numbering batch**, bukan pembatalan individual dokumen secara bisnis.

---

## 7. Source of truth sequence setelah rollback

Reset sequence sebaiknya tidak hanya mempercayai counter lama.

Setelah rollback, aplikasi harus menentukan sequence dari nomor aktif valid terakhir pada context domain numbering yang sama.

Secara konseptual:

```text
next sequence base = MAX(sequence nomor aktif yang masih sah)
```

Jika tidak ada nomor aktif yang masih sah:

```text
sequence = 0
```

Tujuannya mencegah counter table yang stale menghasilkan nomor loncat setelah rollback.

---

## 8. Source order ARKAS adalah dasar numbering SPJ

Numbering ulang harus deterministic dan mengikuti urutan pembukuan ARKAS.

Urutan tidak boleh ditentukan oleh:

- database auto-increment ID;
- waktu package dibuat;
- waktu operator membuka Paket;
- waktu Paket menjadi READY;
- urutan klik operator.

Urutan harus menggunakan source ordering canonical yang berasal dari ARKAS/BKU dan chronology/peristiwa dokumen yang applicable.

Target bisnis:

```text
urutan nomor SPJ = urutan pembukuan ARKAS
```

Jika source order ARKAS berubah setelah sinkronisasi/reconciliation, aplikasi harus dapat menunjukkan bahwa numbering lama perlu dikoreksi, bukan diam-diam memindahkan nomor aktif.

---

## 9. Perubahan Detail Transaksi setelah NUMBERED

`item_description` adalah satu-satunya mutation canonical pada Detail Transaksi.

Perubahan **nama/uraian item (`item_description`)** tetap diperbolehkan ketika Paket berstatus `NUMBERED`.

Perubahan ini:

- tidak membatalkan nomor;
- tidak menurunkan status package;
- tidak mengubah sequence;
- tidak mengubah source ARKAS/BKU;
- harus tercatat sebagai perubahan overlay operator jika audit overlay berlaku.

Jika dokumen belum `FINAL`, preview/generate berikutnya boleh menggunakan `item_description` terbaru dengan nomor yang sama.

Untuk `FINAL`, koreksi yang memengaruhi artifact final harus melalui lifecycle koreksi resmi.

---

## 10. Perubahan data Paket setelah NUMBERED

Data Paket SPJ yang memengaruhi substansi dokumen tidak boleh diubah langsung saat masih `NUMBERED`.

Contoh:

- `spj_category`;
- `payment_description`;
- `payment_method`;
- `payment_reference`;
- `receipt_recipient_name`;
- vendor/rekanan;
- invoice/operator-owned SiPLah metadata;
- data pengadaan;
- data konsumsi/peserta;
- data pemeliharaan/pekerja;
- data SPPD/pelaksana;
- data honor/penerima;
- data JASA_LAINNYA/penerima jasa;
- field Isian Manual lain yang memengaruhi output/validation.

Untuk mengubah data tersebut:

```text
NUMBERED
-> rollback/cancel numbering yang sesuai
-> DRAFT
-> ubah data
-> validasi ulang
-> READY
-> numbering ulang
```

Perubahan kategori setelah rollback harus memuat Isian Manual sesuai kategori baru dan Paket wajib divalidasi ulang.

---

## 11. Dampak rollback pada package dan nomor turunan

Rollback numbering harus membersihkan state numbering operasional yang berasal dari numbering yang di-rollback, termasuk bila applicable:

- `spj_packages.document_number`;
- `numbered_at`;
- nomor Surat Pesanan;
- nomor BAP;
- nomor BAST;
- nomor SPK;
- nomor RAB;
- nomor Surat Tugas Perjalanan Dinas;
- nomor otomatis lain yang diterbitkan oleh run yang sama.

Pembersihan tidak boleh menghapus source ARKAS/BKU atau overlay substansi operator.

Package yang membutuhkan koreksi dikembalikan ke `DRAFT` dan wajib melalui validation sebelum numbering ulang.

---

## 12. History domain vs operational audit

Untuk rollback numbering:

- history numbering domain yang membuat nomor dianggap pernah final/terpakai harus dilepas/reset sesuai scope rollback;
- nomor boleh digunakan kembali;
- sequence kembali ke checkpoint valid;
- **operational audit tidak dihapus**.

Audit minimal harus menyimpan:

```text
actor
school
fiscal year
fund source
quarter (jika batch)
nomor awal rollback
nomor akhir rollback
sequence sebelum
sequence sesudah
alasan
timestamp
```

Operational audit berfungsi sebagai jejak tindakan administrator/operator dan **tidak ikut menahan nomor agar tidak dapat digunakan kembali**.

---

## 13. Atomicity dan failure safety

Rollback harus dilakukan secara transaction-safe pada database tenant.

Jika salah satu tahap gagal:

- sequence tidak boleh sudah mundur sementara dokumen masih NUMBERED;
- package tidak boleh DRAFT sementara numbering aktif masih tersisa secara tidak konsisten;
- nomor turunan tidak boleh sebagian terhapus;
- fiscal-period/quarter state tidak boleh berbeda dari state dokumen.

Operasi harus all-or-nothing sejauh boundary database memungkinkan.

---

## 14. Guard sebelum rollback

Sebelum rollback aplikasi harus memvalidasi sekurang-kurangnya:

1. context `School + Fiscal Year + Fund Source` aktif dan cocok;
2. target nomor/triwulan memang memiliki numbering aktif;
3. dependency triwulan setelahnya tidak dilanggar;
4. rollback parsial dimulai dari titik tertentu sampai nomor aktif terakhir;
5. tidak ada cross-tenant mutation;
6. alasan rollback wajib diisi;
7. actor mempunyai authorization yang sesuai;
8. state `FINAL` mengikuti policy koreksi/finalization yang ditetapkan sebelum implementasi mutasi destructive.

---

## 15. Contoh kasus canonical

### A. Cancel satu dokumen

```text
001 002 003 004 005 006
                ^
                transaksi 005 dibatalkan secara bisnis

hasil:
005 = CANCELLED
sequence tetap 6
005 tidak digunakan kembali
```

### B. Salah urutan ARKAS mulai nomor 8

```text
001 ... 007 008 009 010
```

Rollback:

```text
010 -> lepas
009 -> lepas
008 -> lepas
sequence = 7
```

Numbering ulang:

```text
008 -> transaksi yang menurut ARKAS urutan ke-8
009 -> transaksi urutan ke-9
010 -> transaksi urutan ke-10
```

### C. Cancel TW3

```text
TW1 akhir 120
TW2 akhir 245
TW3 akhir 380
TW4 belum bernomor
```

Hasil:

```text
numbering TW3 dilepas
sequence = 245
package terdampak -> DRAFT
```

### D. Cancel TW1 saat TW2 masih aktif

```text
TW1 NUMBERED
TW2 NUMBERED
```

Hasil:

```text
DITOLAK
```

Operator harus cancel TW2 terlebih dahulu, baru TW1.

---

## 16. Minimum regression yang wajib dibuat saat implementasi

Implementasi tidak dianggap selesai tanpa regression test untuk sedikitnya:

1. cancel individual mempertahankan nomor cancelled dan sequence tidak mundur;
2. rollback dari nomor N melepas N..last dan sequence menjadi N-1/checkpoint valid;
3. nomor rollback dapat digunakan kembali;
4. rollback tidak membuat gap aktif;
5. rollback mengikuti tenant + fiscal year + fund source;
6. TW1 ditolak jika TW2 masih numbered pada context yang sama;
7. TW2 tidak diblokir oleh numbering source dana lain;
8. cancel TW3 mengembalikan sequence ke akhir TW2;
9. cancel TW1 mengembalikan sequence ke 0;
10. operational audit tetap ada setelah numbering history di-reset;
11. `item_description` dapat diperbaiki pada NUMBERED tanpa mengubah nomor/sequence;
12. perubahan kategori/payment/manual data ditolak sebelum rollback;
13. setelah rollback, perubahan kategori mengembalikan flow ke DRAFT -> validation -> READY -> numbering;
14. rollback gagal secara atomik tanpa meninggalkan partial state.

---

## 17. Status implementasi

Pada tanggal dokumen ini dibuat, aturan di atas adalah **keputusan domain yang disetujui untuk implementasi berikutnya**.

Jangan menganggap seluruh behavior rollback sudah tersedia hanya karena kontrak ini sudah ditulis.

Status source/test aktual tetap mengikuti `CURRENT_PROGRESS.md` dan evidence CI/test terbaru.