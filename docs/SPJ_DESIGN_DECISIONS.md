# SPJ BOSP Web — Keputusan Desain & Aturan Bisnis

Terakhir diperbarui: **2026-09-11**

Dokumen ini adalah sumber **keputusan bisnis/domain permanen** untuk branch `gui-standardization`.

Dokumen ini tidak menyatakan status implementasi. Gunakan:

- `docs/CURRENT_PROGRESS.md` untuk status/evidence release terbaru;
- `docs/DEVELOPMENT_ROADMAP.md` untuk prioritas pekerjaan;
- `docs/ARCHITECTURE_COMPLETE.md` untuk struktur teknis aplikasi;
- `docs/GUI_STANDARDIZATION.md` untuk kontrak visual dan layout UI.

Jika implementasi atau UI bertentangan dengan keputusan domain di dokumen ini, implementasi/UI yang harus diperbaiki kecuali keputusan domain memang diubah secara eksplisit.

---

## 1. Prinsip domain utama

1. **ARKAS/BKU adalah source readonly.** Aplikasi SPJ tidak boleh mengubah source hanya agar workflow, test, audit, atau output menjadi PASS.
2. **Data operator SPJ adalah overlay.** Overlay disimpan terpisah dan dipertahankan ketika source disinkronkan ulang.
3. **Boundary tenant canonical adalah `School + Fiscal Year + Fund Source`.** Semua query/mutation domain harus berada pada context tersebut.
4. **Ownership data harus tunggal.** Field yang sama tidak boleh diedit dari Detail Transaksi dan Paket SPJ sekaligus.
5. **Preview/download tidak menerbitkan nomor.** Rendering dokumen tidak boleh mempunyai side effect numbering.
6. **NUMBERED/FINAL terkunci dari mutation normal.** Koreksi harus melalui lifecycle resmi yang audited.
7. **Nomor mengikuti domain dokumen dan chronology/peristiwa**, bukan urutan operator melakukan input.
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

### Reset tenant

Reset database sekolah hanya boleh merebuild tenant target, termasuk WAL/SHM dan sequence tenant.

Reset tenant **tidak boleh**:

- menghapus database utama;
- menghapus tenant sekolah lain;
- menghapus data di luar scope sekolah target;
- menganggap restore/reset berhasil sebelum integrity verification selesai.

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
- metadata invoice/SiPLah yang memang operator-owned;
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

Safe sync tidak boleh:

- menghapus Paket/overlay hanya karena source sementara hilang;
- membuat identity baru untuk source yang kembali;
- menimpa NUMBERED/FINAL secara diam-diam;
- menghapus operator data untuk menyamakan source secara paksa.

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

Detail Transaksi tidak boleh menjadi workspace kedua untuk:

- kategori SPJ;
- metode/referensi pembayaran;
- vendor/penyedia;
- pajak;
- data kategori;
- numbering;
- lifecycle dokumen.

### 4.2 Paket SPJ

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

Paket boleh membaca source transaction/item/tax untuk keperluan validasi dan rendering, tetapi tidak boleh menulis ulang source tersebut.

---

## 5. Pajak adalah source transaction

Field pajak canonical berasal dari source transaksi:

```text
PPN
PPh 21
PPh 22
PPh 23
PPh 4(2)
SSPD / Pajak Daerah
tax_total
net_amount
```

Paket SPJ tidak boleh menghitung ulang lalu menulis ulang nilai source tersebut.

Jika detail kategori membutuhkan distribusi pajak per penerima, distribusi tersebut adalah **derived/operator detail** dan tidak mengubah nilai source transaction.

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

Tidak boleh menambahkan kategori baru hanya untuk merepresentasikan channel pembayaran/procurement.

### Perubahan kategori

Jika Paket berstatus READY dan kategori benar-benar berubah, Paket harus kembali ke DRAFT untuk revalidation.

Jika nilai kategori tidak berubah, lifecycle tidak boleh di-reset tanpa alasan domain.

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

Source SiPLah dan operator-owned SiPLah fields harus tetap mengikuti aturan safe sync/overlay yang sama seperti domain lain.

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

Jangan menambahkan rule chronology baru hanya berdasarkan asumsi visual atau kebiasaan operator tanpa keputusan domain eksplisit.

---

## 9. Konsumsi dan participant roster

Auto-fill peserta KONSUMSI memiliki kontrak permanen:

```text
Auto-fill peserta = Employee source DAPODIK
Participant manual = allowed
```

Unified Employee Master atau identity fusion lintas source **tidak otomatis memperluas** sumber auto-fill KONSUMSI.

Jika implementasi identity menyatukan provenance ARKAS + Dapodik, eligibility auto-fill tetap harus didasarkan pada evidence/provenance Dapodik yang sah.

Jumlah peserta/porsi harus tetap konsisten dengan aturan kategori yang berlaku.

---

## 10. Unified Employee Identity

Employee dapat mempunyai provenance dari lebih dari satu source, termasuk ARKAS/PTK dan Dapodik, serta row manual operator.

Identity resolution harus bersifat konservatif.

Prioritas match kuat menggunakan identifier yang memang dapat dipercaya, misalnya NUPTK/NIP/identifier source canonical.

Normalized name hanya boleh menjadi fallback bila kandidat **unik dan tidak ambigu**.

Aturan permanen:

- nama yang sama tidak cukup untuk silent merge bila ada lebih dari satu kandidat;
- dua orang dengan nama sama tetapi identifier berbeda tidak boleh digabung;
- ambiguity harus menghasilkan no-match/manual resolution, bukan tebakan;
- provenance source harus dipertahankan setelah fusion;
- operator-locked/manual row tidak boleh disapu hanya karena satu source tidak lagi melihat pegawai tersebut;
- deactivation karena sync harus mempertimbangkan seluruh source provenance yang relevan.

Keamanan identity lebih penting daripada mengurangi jumlah duplicate secara agresif.

---

## 11. Pemeliharaan bahan + upah

Domain utama:

```text
1 transaction
└── 1 work order
    └── banyak workers
```

Jika BKU memisahkan bahan/barang dan upah, transaksi dapat saling ditautkan melalui relationship context seperti:

```text
maintenance_material_transaction_id
maintenance_labor_transaction_id
```

Kontrak linkage:

- transaksi tidak boleh menautkan dirinya sendiri;
- kandidat harus berada dalam tenant context yang sama;
- relationship state tetap transaction/context-owned;
- rendering dokumen boleh menggabungkan context bahan dan upah;
- penggabungan document context tidak boleh menulis ulang source BKU.

---

## 12. SPPD

Satu transaksi dapat memiliki banyak travel/pelaksana.

SPPD tetap kategori canonical tersendiri.

Jika fiscal year tertentu tidak mempunyai transaksi SPPD nyata, aplikasi/dokumentasi/test real-data tidak boleh membuat SPPD fiktif hanya untuk memperoleh six-category coverage.

Deterministic fixture test boleh memakai fixture sintetis yang jelas berstatus test fixture; aturan ini melarang fabrikasi **real-data evidence**.

---

## 13. Honor Pegawai

Satu transaksi dapat mempunyai banyak penerima honor.

Rincian honor merupakan data Paket SPJ dan tidak boleh dipindahkan kembali menjadi mutation Detail Transaksi.

Employee identity yang dipakai untuk membantu pemilihan penerima tidak boleh melemahkan kontrak identity resolution pada bagian 10.

---

## 14. JASA_LAINNYA multi-penerima

Satu transaksi BKU boleh mempunyai banyak penerima/penyedia jasa tanpa memecah transaction/package hanya demi representasi penerima.

Kontrak agregat:

```text
Σ gross penerima = transaction.gross_amount
Σ tax penerima   = transaction.tax_total
Σ net penerima   = transaction.net_amount
```

Jika source hanya menyediakan tax/net agregat, aplikasi boleh membuat distribusi derived secara proporsional dengan koreksi rounding deterministik.

Distribusi tersebut tidak boleh mengubah source transaction tax/net.

Output dokumen per penerima harus mempertahankan identity, gross, tax, dan net penerima yang benar.

---

## 15. Penerima Utama

`receipt_recipient_name` adalah overlay operator untuk pihak utama/penanda tangan kuitansi.

`recipient_name` tetap source dan tidak boleh digunakan sebagai pengganti writable overlay hanya demi menyederhanakan implementasi.

Jika detail kategori mempunyai banyak penerima, aplikasi harus dapat menentukan maksimal satu Penerima Utama yang authoritative untuk Paket tersebut.

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
- READY berarti validation yang berlaku telah dipenuhi dan Paket siap masuk numbering;
- NUMBERED berarti identitas/nomor domain sudah diterbitkan dan mutation normal terkunci;
- FINAL berarti finalization/snapshot yang diwajibkan telah selesai dan Paket terkunci;
- CANCELLED adalah lifecycle eksplisit, bukan delete tersembunyi.

Cancellation, reissue, reopen, dan finalization harus mempunyai jalur domain eksplisit dan audit trail.

Preview/download tidak boleh mengubah lifecycle.

---

## 17. Penomoran dokumen

1. Setiap jenis dokumen mempunyai domain nomor sendiri bila memang diperlukan.
2. Nomor mengikuti chronology/peristiwa dokumen yang authoritative.
3. Urutan operator menginput data bukan sumber urutan nomor.
4. Nomor aktif tidak boleh ditimpa diam-diam.
5. Nomor cancelled tetap menjadi history.
6. Reissue tidak boleh menciptakan dua identity aktif untuk domain yang sama.
7. Numbering harus idempotent terhadap request yang sama.
8. Preview/download tidak boleh mengalokasikan sequence.
9. Nomor otomatis bukan field manual operator.
10. Real-data numbering hanya dilakukan setelah blocker legitimate sebelumnya diselesaikan; numbering tidak boleh dipakai untuk melompati data yang belum valid.

---

## 18. Read-only audit dan real-data verification

Audit database sekolah yang bertujuan menentukan kondisi awal harus **read-only**.

Kontrak audit real-data:

- tidak membuat tenant database yang hilang;
- tidak menjalankan auto-migration/repair sebagai side effect audit;
- tidak mengubah metadata tenant hanya karena audit dibuka;
- tidak mengubah transaction/items/package/lifecycle;
- tidak menerbitkan nomor;
- tidak mengisi vendor/penerima/SPPD/template secara otomatis untuk memperoleh PASS.

Mutation real-data untuk pengujian workflow harus dilakukan pada **isolated copy**, bukan original upload/baseline immutable.

Audit read-only dan mutation workflow adalah dua tahap yang berbeda dan tidak boleh dicampur.

---

## 19. Dokumen dan template

Template/operator artifact adalah bagian dari Paket SPJ, bukan source ARKAS/BKU.

Kontrak template:

- upload invalid tidak boleh mengganti template aktif;
- replacement harus menjaga template lama sampai replacement baru benar-benar berhasil;
- generator dan template library harus membaca lifecycle storage yang sama;
- unresolved placeholder harus dianggap error, bukan dibiarkan diam-diam ke output final;
- output yang dinyatakan generated harus lolos validation format yang sesuai.

Functional artifact generation tidak sama dengan official-template visual verification. Print area, page break, header/footer, ukuran halaman, dan hasil cetak nyata tetap memerlukan verification tersendiri.

---

## 20. Authorization dan audit trail

Role authorization dan tenant/context isolation adalah dua boundary terpisah. Memiliki role yang benar tidak memberi hak mengakses tenant/context lain.

Aktivitas sensitif yang harus dapat diaudit mencakup sekurang-kurangnya:

- sync/reconciliation;
- perubahan overlay;
- create/open draft;
- perubahan kategori;
- update Paket;
- READY;
- numbering;
- cancel/reissue/reopen;
- finalization;
- reset/restore tenant;
- perubahan konfigurasi sensitif.

Audit trail harus menyimpan context yang cukup untuk memahami siapa melakukan apa terhadap entity mana dan dalam tenant mana.

---

## 21. Aturan perubahan keputusan desain

Keputusan di dokumen ini tidak boleh diubah hanya untuk menyesuaikan implementasi yang kebetulan sudah terlanjur ada.

Jika business rule memang berubah:

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

Kontrak GUI:

```text
docs/GUI_STANDARDIZATION.md
```

Dokumen ownership migration historis:

```text
docs/URGENT_TRANSACTION_SPJ_MIGRATION.md
```
