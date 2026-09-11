# SPJ BOSP Web — Keputusan Desain & Aturan Bisnis

Terakhir diperbarui: **2026-09-11**

Dokumen ini adalah sumber **keputusan bisnis/domain permanen** untuk branch `gui-standardization`.

Dokumen ini tidak menyatakan status implementasi. Gunakan:

- `docs/CURRENT_PROGRESS.md` untuk status/evidence release terbaru;
- `docs/DEVELOPMENT_ROADMAP.md` untuk prioritas pekerjaan;
- `docs/ARCHITECTURE_COMPLETE.md` untuk struktur teknis aplikasi;
- `docs/GUI_STANDARDIZATION.md` untuk kontrak visual dan layout UI;
- `docs/NUMBERING_CORRECTION_AND_ROLLBACK.md` untuk detail koreksi dan rollback numbering.

Jika implementasi atau UI bertentangan dengan keputusan domain di dokumen ini, implementasi/UI yang harus diperbaiki kecuali keputusan domain memang diubah secara eksplisit.

---

## 1. Prinsip domain utama

1. **ARKAS/BKU adalah source readonly.** Aplikasi SPJ tidak boleh mengubah source hanya agar workflow, test, audit, atau output menjadi PASS.
2. **Data operator SPJ adalah overlay.** Overlay disimpan terpisah dan dipertahankan ketika source disinkronkan ulang.
3. **Boundary tenant canonical adalah `School + Fiscal Year + Fund Source`.** Semua query/mutation domain harus berada pada context tersebut.
4. **Ownership data harus tunggal.** Field yang sama tidak boleh diedit dari Detail Transaksi dan Paket SPJ sekaligus.
5. **Preview/download tidak menerbitkan nomor.** Rendering dokumen tidak boleh mempunyai side effect numbering.
6. **NUMBERED/FINAL terkunci dari mutation normal**, kecuali koreksi `item_description` pada NUMBERED sebagaimana diatur pada bagian ownership dan numbering correction.
7. **Nomor mengikuti domain dokumen dan chronology/source order authoritative**, bukan urutan operator melakukan input.
8. **UI tidak boleh melemahkan backend rule.** Validasi bisnis authoritative tetap berada di backend/domain.
9. **Data nyata tidak boleh difabrikasi untuk coverage.** Vendor, penerima, SPPD, template, source transaction, atau identifier tidak boleh dibuat-buat hanya agar satu skenario terlihat lulus.
10. **Evidence harus dibedakan dari asumsi.** Functional regression, real-data verification, visual/runtime verification, dan deferred work adalah status berbeda.

---

## 2. Multi-database dan APP DATA

Database utama menyimpan user, sekolah, konfigurasi tenant, metadata global, dan referensi pengelolaan database sekolah.

Database tenant menyimpan RKAS/BKU hasil sinkronisasi, transaksi, detail SPJ, Paket, numbering, audit, importer state, dan data kerja sekolah.

Root data aplikasi dapat dikonfigurasi melalui:

```env
SPJ_DATA_PATH=D:/lrvProject/spj-bosp-data
```

Fallback default adalah `storage/app`.

Database sekolah canonical:

```text
{SPJ_DATA_PATH}/school-databases/{NPSN}/spj.sqlite
```

Dummy/unselected tenant:

```text
{SPJ_DATA_PATH}/school-databases/_unselected.sqlite
```

Reset database sekolah hanya boleh merebuild tenant target, termasuk WAL/SHM dan sequence tenant. Reset tenant tidak boleh menghapus database utama, tenant lain, atau data di luar scope sekolah target.

---

## 3. Source ARKAS/BKU vs operator overlay

### 3.1 Source readonly

Source mencakup fakta yang berasal dari ARKAS/BKU, misalnya:

- source key / nomor bukti;
- tanggal transaksi;
- uraian source;
- kegiatan dan rekening;
- penerima source;
- quantity/unit/unit price/amount source;
- gross/tax/net source;
- payload referensi source.

Source tersebut tidak menjadi field manual operator di workspace SPJ.

### 3.2 Operator overlay

Overlay operator mencakup data yang memang menjadi tanggung jawab penyusunan SPJ, misalnya:

- `item_description`;
- `payment_description`;
- `payment_method`;
- `payment_reference`;
- `receipt_recipient_name`;
- `spj_category`;
- vendor/procurement manual;
- metadata invoice/SiPLah operator-owned;
- detail kategori;
- package/document lifecycle;
- numbering domain.

`manual_description` bukan field canonical dan tidak boleh dihidupkan kembali.

### 3.3 Safe sync

Kontrak sinkronisasi:

```text
source ada     -> update source, pertahankan overlay
source hilang  -> tandai SOURCE_MISSING, jangan hapus pekerjaan operator
source kembali -> aktifkan kembali identity yang sama
source berubah -> lakukan reconciliation bila perlu
```

Safe sync tidak boleh menghapus Paket/overlay hanya karena source sementara hilang, membuat identity baru untuk source yang kembali, menimpa NUMBERED/FINAL secara diam-diam, atau menghapus operator data untuk menyamakan source secara paksa.

---

## 4. Ownership Detail Transaksi dan Paket SPJ

### 4.1 Detail Transaksi

Detail Transaksi adalah workspace fakta source/context.

Satu-satunya mutation rincian item yang canonical adalah:

```text
item_description
```

Ownership item:

```text
description       readonly source
item_description  editable operator
quantity          readonly source
unit              readonly source
unit_price        readonly source
amount            readonly source
```

`item_description` harus benar-benar tersimpan sebelum Paket dapat dibuat/dibuka melalui gateway canonical.

Detail Transaksi tidak boleh menjadi workspace kedua untuk kategori SPJ, metode/referensi pembayaran, vendor, pajak, data kategori, numbering, atau lifecycle dokumen.

### 4.2 Koreksi Detail Transaksi setelah NUMBERED

Perubahan `item_description` tetap diperbolehkan ketika Paket berstatus `NUMBERED`.

Koreksi ini:

- tidak membatalkan nomor;
- tidak menurunkan status Paket;
- tidak mengubah sequence;
- tidak menulis ulang source ARKAS/BKU.

Jika dokumen belum `FINAL`, preview/generate berikutnya boleh menggunakan `item_description` terbaru dengan nomor yang sama.

Untuk `FINAL`, koreksi yang memengaruhi artifact final harus melalui lifecycle koreksi resmi.

### 4.3 Paket SPJ

Paket SPJ adalah workspace mutation dokumen pertanggungjawaban.

Ownership Paket mencakup:

```text
spj_category
payment_description
payment_method
payment_reference
receipt_recipient_name
vendor / rekanan
invoice / metadata SiPLah operator-owned
data pengadaan
data konsumsi / peserta
data pemeliharaan / pekerja
data SPPD / pelaksana
data honor / penerima
data JASA_LAINNYA / penerima jasa
validation / READY
numbering
preview / generate / download
finalization / lifecycle
```

Paket boleh membaca source transaction/item/tax untuk validasi dan rendering, tetapi tidak boleh menulis ulang source tersebut.

### 4.4 Perubahan Paket setelah NUMBERED

Data Paket yang memengaruhi substansi dokumen **tidak boleh diubah langsung saat masih `NUMBERED`**.

Termasuk kategori, detail pembayaran, penerima, vendor, procurement, invoice/operator-owned SiPLah metadata, data peserta/pekerja/pelaksana/penerima, serta Isian Manual kategori.

Untuk mengubahnya:

```text
NUMBERED
-> rollback/cancel numbering yang sesuai
-> DRAFT
-> ubah data
-> validasi ulang
-> READY
-> numbering ulang
```

---

## 5. Pajak adalah source transaction

Field pajak canonical berasal dari source transaksi: PPN, PPh 21/22/23/4(2), SSPD/Pajak Daerah, `tax_total`, dan `net_amount`.

Paket SPJ tidak boleh menghitung ulang lalu menulis ulang nilai source tersebut. Distribusi pajak per penerima boleh menjadi derived/operator detail selama tidak mengubah nilai source transaction.

---

## 6. Kategori SPJ canonical

Kategori canonical hanya:

```text
BARANG
KONSUMSI
PEMELIHARAAN
JASA_LAINNYA
SPPD
HONOR_PEGAWAI
```

Alias legacy boleh dinormalisasi ke kategori canonical, misalnya:

```text
BELANJA_MODAL      -> BARANG
PERJALANAN_DINAS  -> SPPD
JASA_HONORARIUM   -> HONOR_PEGAWAI
UPAH               -> PEMELIHARAAN
LAINNYA            -> JASA_LAINNYA
```

Jika Paket berstatus READY dan kategori benar-benar berubah, Paket harus kembali ke DRAFT untuk revalidation.

Jika Paket sudah NUMBERED, kategori tidak boleh diubah sampai numbering yang relevan di-rollback/dibatalkan sesuai kontrak numbering correction.

---

## 7. SiPLah adalah channel, bukan kategori

SiPLah direpresentasikan melalui procurement/payment context, misalnya:

```text
spj_category = BARANG
payment_method = siplah
```

Kontrak permanen:

- `SIPLAH` tidak boleh menjadi `spj_category`;
- nomor marketplace SiPLah berbeda dari nomor Surat Pesanan internal SPJ;
- metadata marketplace/order/invoice/payment reference tidak boleh dicampur dengan numbering internal;
- Paket SiPLah tetap memakai lifecycle Paket SPJ normal;
- preview/download SiPLah tetap tidak boleh menerbitkan nomor;
- requirement dokumen SiPLah hanya boleh meminta dokumen yang memang applicable;
- BARANG SiPLah tidak boleh dipaksa memenuhi Surat Pesanan internal yang secara policy tidak berlaku.

---

## 8. Pengadaan barang dan chronology

Untuk Non-SiPLah, Surat Pesanan internal dapat memiliki tahap content/substansi dan tahap numbering yang berbeda.

Nomor Surat Pesanan otomatis bukan input manual operator.

Rule chronology canonical:

```text
order_date <= transaction_date
order_date <= bap_date
bap_date <= bast_date
```

---

## 9. Konsumsi dan participant roster

Auto-fill peserta KONSUMSI memiliki kontrak permanen:

```text
Auto-fill peserta = Employee source DAPODIK
Participant manual = allowed
```

Unified Employee Master atau identity fusion lintas source tidak otomatis memperluas sumber auto-fill KONSUMSI.

---

## 10. Unified Employee Identity

Employee dapat mempunyai provenance dari lebih dari satu source, termasuk ARKAS/PTK dan Dapodik, serta row manual operator.

Identity resolution harus konservatif. Prioritas match menggunakan identifier kuat; normalized name hanya fallback bila kandidat unik dan tidak ambigu.

Aturan permanen:

- nama sama tidak cukup untuk silent merge bila ada lebih dari satu kandidat;
- identifier berbeda tidak boleh digabung hanya karena nama sama;
- ambiguity menghasilkan no-match/manual resolution;
- provenance source dipertahankan;
- operator-locked/manual row tidak disapu hanya karena satu source tidak melihatnya;
- deactivation mempertimbangkan seluruh provenance source yang relevan.

---

## 11. Pemeliharaan bahan + upah

Domain utama:

```text
1 transaction
└── 1 work order
    └── banyak workers
```

Jika BKU memisahkan bahan/barang dan upah, transaksi dapat saling ditautkan melalui relationship context seperti `maintenance_material_transaction_id` dan `maintenance_labor_transaction_id`.

Linkage harus berada dalam tenant yang sama dan tidak boleh menulis ulang source BKU.

---

## 12. SPPD

Satu transaksi dapat memiliki banyak travel/pelaksana. SPPD tetap kategori canonical tersendiri.

Jika fiscal year tertentu tidak mempunyai transaksi SPPD nyata, aplikasi/dokumentasi/test real-data tidak boleh membuat SPPD fiktif hanya untuk memperoleh six-category coverage.

---

## 13. Honor Pegawai

Satu transaksi dapat mempunyai banyak penerima honor. Rincian honor merupakan data Paket SPJ dan tidak boleh dipindahkan kembali menjadi mutation Detail Transaksi.

---

## 14. JASA_LAINNYA multi-penerima

Satu transaksi BKU boleh mempunyai banyak penerima/penyedia jasa tanpa memecah transaction/package hanya demi representasi penerima.

Kontrak agregat:

```text
Σ gross penerima = transaction.gross_amount
Σ tax penerima   = transaction.tax_total
Σ net penerima   = transaction.net_amount
```

Distribusi derived tidak boleh mengubah source transaction tax/net.

---

## 15. Penerima Utama

`receipt_recipient_name` adalah overlay operator untuk pihak utama/penanda tangan kuitansi.

`recipient_name` tetap source. Jika detail kategori mempunyai banyak penerima, maksimal satu Penerima Utama menjadi authoritative untuk Paket tersebut.

---

## 16. Lifecycle Paket SPJ

Lifecycle canonical:

```text
DRAFT
READY
NUMBERED
FINAL
CANCELLED
```

Prinsip:

- DRAFT dapat dilengkapi operator;
- READY berarti validation telah dipenuhi dan Paket siap masuk numbering;
- NUMBERED berarti nomor domain sudah diterbitkan;
- FINAL berarti finalization/snapshot selesai dan Paket terkunci;
- CANCELLED adalah lifecycle eksplisit, bukan delete tersembunyi.

Cancellation, reissue, reopen, rollback, dan finalization harus mempunyai jalur domain eksplisit dan audit trail.

Preview/download tidak boleh mengubah lifecycle.

---

## 17. Penomoran dokumen dan koreksi numbering

### 17.1 Aturan dasar

1. Setiap jenis dokumen mempunyai domain nomor sendiri bila memang diperlukan.
2. Nomor mengikuti chronology/source order authoritative.
3. Urutan operator menginput data bukan sumber urutan nomor.
4. Nomor aktif tidak boleh ditimpa diam-diam.
5. Reissue tidak boleh menciptakan dua identity aktif untuk domain yang sama.
6. Numbering harus idempotent terhadap request yang sama.
7. Preview/download tidak boleh mengalokasikan sequence.
8. Nomor otomatis bukan field manual operator.
9. Boundary numbering dan rollback adalah `School + Fiscal Year + Fund Source`.
10. Target urutan SPJ adalah konsisten dengan urutan pembukuan ARKAS pada context yang sama.

### 17.2 Cancel individual

Jika satu dokumen/transaksi dibatalkan secara bisnis:

```text
nomor -> CANCELLED
```

Nomor tetap menjadi history, tidak digunakan kembali, dan sequence tidak mundur.

**Inilah scope aturan “nomor cancelled tetap menjadi history”.**

### 17.3 Rollback numbering dari nomor tertentu

Jika numbering salah urut terhadap ARKAS, rollback harus dimulai dari nomor salah sampai nomor aktif terakhir.

Contoh:

```text
01 ... 07 08 09 10
```

Jika masalah dimulai pada `08`:

```text
10 -> lepas
09 -> lepas
08 -> lepas
sequence -> 7
```

Nomor yang dilepas boleh digunakan kembali saat numbering ulang. Operational audit rollback tetap dipertahankan.

Rollback tidak boleh membuat lubang aktif di tengah sequence.

### 17.4 Cancel Penomoran Triwulan

Pembuatan numbering berjalan maju:

```text
TW1 -> TW2 -> TW3 -> TW4
```

Pembatalan numbering berjalan mundur:

```text
TW4 -> TW3 -> TW2 -> TW1
```

Dependency hanya diperiksa pada tenant+tahun+sumber dana yang sama.

Rule:

- TW3 tidak boleh dibatalkan bila TW4 masih mempunyai numbering aktif;
- TW2 tidak boleh dibatalkan bila TW3/TW4 masih aktif;
- TW1 tidak boleh dibatalkan bila TW2/TW3/TW4 masih aktif.

Cancel triwulan mengembalikan sequence ke akhir triwulan sebelumnya:

```text
Cancel TW3 -> sequence akhir TW2
Cancel TW2 -> sequence akhir TW1
Cancel TW1 -> sequence 0
```

Nomor hasil batch yang di-rollback boleh digunakan kembali.

### 17.5 Sequence setelah rollback

Sequence sesudah rollback harus merefleksikan nomor aktif valid terakhir pada domain numbering yang sama, bukan sekadar mempercayai counter lama.

Jika tidak ada nomor aktif valid yang tersisa, sequence menjadi `0`.

### 17.6 History numbering vs operational audit

Rollback numbering dapat menghapus/reset history numbering domain yang membuat nomor dianggap terpakai, tetapi operational audit tindakan rollback tetap dipertahankan.

Audit minimal menyimpan actor, tenant context, quarter bila relevant, nomor awal/akhir rollback, sequence sebelum/sesudah, alasan, dan timestamp.

Detail lengkap ada pada `NUMBERING_CORRECTION_AND_ROLLBACK.md`.

---

## 18. Read-only audit dan real-data verification

Audit database sekolah yang bertujuan menentukan kondisi awal harus read-only.

Kontrak audit real-data:

- tidak membuat tenant database yang hilang;
- tidak menjalankan auto-migration/repair sebagai side effect audit;
- tidak mengubah metadata tenant hanya karena audit dibuka;
- tidak mengubah transaction/items/package/lifecycle;
- tidak menerbitkan nomor;
- tidak mengisi vendor/penerima/SPPD/template secara otomatis untuk memperoleh PASS.

Mutation real-data untuk pengujian workflow harus dilakukan pada isolated copy, bukan original upload/baseline immutable.

---

## 19. Dokumen dan template

Template/operator artifact adalah bagian dari Paket SPJ, bukan source ARKAS/BKU.

Kontrak template:

- upload invalid tidak boleh mengganti template aktif;
- replacement menjaga template lama sampai replacement baru berhasil;
- generator dan template library membaca lifecycle storage yang sama;
- unresolved placeholder dianggap error;
- output generated harus lolos validation format yang sesuai.

Functional artifact generation tidak sama dengan official-template visual verification.

---

## 20. Authorization dan audit trail

Role authorization dan tenant/context isolation adalah dua boundary terpisah.

Aktivitas sensitif yang harus dapat diaudit mencakup sekurang-kurangnya:

- sync/reconciliation;
- perubahan overlay;
- create/open draft;
- perubahan kategori;
- update Paket;
- READY;
- numbering;
- cancel individual;
- rollback numbering;
- cancel numbering triwulan;
- reissue/reopen;
- finalization;
- reset/restore tenant;
- perubahan konfigurasi sensitif.

Audit trail harus menyimpan context yang cukup untuk memahami siapa melakukan apa terhadap entity mana dan dalam tenant mana.

---

## 21. Aturan perubahan keputusan desain

Jika business rule berubah:

1. jelaskan alasan domain/peraturan/operasionalnya;
2. perbarui keputusan di dokumen ini;
3. perbarui regression test yang menjaga rule tersebut;
4. sinkronkan `CURRENT_PROGRESS.md` dan roadmap bila status kerja ikut berubah;
5. cek dokumentasi feature/architecture/UI terkait agar tidak meninggalkan kontradiksi.

Refactor teknis yang tidak mengubah business rule tidak perlu menghasilkan keputusan desain baru.

---

## 22. Sumber status dan dokumen pendukung

Status implementasi/gap aktif:

```text
docs/CURRENT_PROGRESS.md
```

Roadmap:

```text
docs/DEVELOPMENT_ROADMAP.md
```

Arsitektur:

```text
docs/ARCHITECTURE_COMPLETE.md
```

Sinkronisasi:

```text
docs/SYNCHRONIZATION.md
```

Koreksi dan rollback numbering:

```text
docs/NUMBERING_CORRECTION_AND_ROLLBACK.md
```

Kontrak GUI:

```text
docs/GUI_STANDARDIZATION.md
```

Dokumen ownership migration historis:

```text
docs/URGENT_TRANSACTION_SPJ_MIGRATION.md
```