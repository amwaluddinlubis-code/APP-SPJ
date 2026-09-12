# Koreksi & Rollback Penomoran SPJ

Terakhir diperbarui: **2026-09-12**

Status: **IMPLEMENTED / FUNCTIONAL PASS / REAL-DATA CORE MUTATION QA PASS**

Dokumen ini menetapkan kontrak bisnis dan implementation guide untuk penomoran, canonical numbering registry, finalization gate antar-triwulan, cancel individual, tail rollback, cancel penomoran triwulan, koreksi setelah numbering, serta format token nomor.

Evidence CI aktif tidak disalin ke feature guide ini. Gunakan `P0_VERIFICATION_KIT.md` §1 untuk checkpoint source/CI terbaru dan `CURRENT_PROGRESS.md` untuk status PASS/PENDING/RVR.

---

## 1. Boundary dan tujuan numbering

Urutan nomor SPJ harus konsisten dengan chronology/source order authoritative pada context tenant yang sama.

Boundary canonical:

```text
School + Fiscal Year + Fund Source
```

Penomoran atau rollback pada satu sumber dana tidak boleh mengubah counter, Paket, atau numbering sumber dana lain.

Sequence discope oleh:

```text
fiscal_year_id
+ fund_source_id
+ format_name
+ period_key
```

Preview/download tidak boleh mengalokasikan nomor atau sequence.

---

## 2. Canonical numbering registry

Seluruh metadata domain penomoran berasal dari satu source of truth:

```text
app/Services/SpjNumberingDocumentRegistry.php
```

Setiap definisi menyimpan:

```text
code
label
numbered
applicable_categories
channel
event_date_rule
number_target
scope_rule
```

Current numbered codes:

```text
SPJ
PESANAN
BAP
BAST
SPK
RAB
SURAT_TUGAS_PERJALANAN_DINAS
```

Daftar tersebut tidak boleh di-hardcode ulang pada controller, Blade, gate, allocator, order service, lifecycle, atau use case. Consumer memperoleh metadata melalui registry atau `SpjNumberingPolicyService` sebagai adapter runtime.

### Event-date rule

Registry menentukan sumber tanggal canonical untuk urutan dan issuance:

```text
SPJ      -> transaction.transaction_date
PESANAN  -> goods.order_date
BAP      -> goods.bap_date
BAST     -> goods.bast_date
SPK      -> workOrder.spk_date
RAB      -> workOrder.rab_date
SURAT_TUGAS_PERJALANAN_DINAS
         -> travels.assignment_letter_date
            fallback travels.departure_date
```

### Number target

Registry juga menentukan tempat nomor turunan disinkronkan:

```text
SPJ      -> package.document_number
PESANAN  -> goods.order_number
BAP      -> goods.bap_number
BAST     -> goods.bast_number
SPK      -> workOrder.spk_number
RAB      -> workOrder.rab_number
SURAT_TUGAS_PERJALANAN_DINAS
         -> travels.assignment_letter_number
```

SPJ memakai category wildcard `*` karena dokumen utama berlaku untuk setiap Paket, termasuk legacy package yang category-nya belum tersedia. Dokumen turunan tetap mengikuti category/channel applicable.

Alias legacy dinormalisasi di registry sebelum diproses oleh workflow canonical, termasuk `ORDER`/`SURAT_PESANAN` → `PESANAN`, `WORK_ORDER`/`SPK_PEMELIHARAAN` → `SPK`, dan `RAB_PEMELIHARAAN` → `RAB`.

`SpjDocumentTypeRegistry` mempunyai peran berbeda sebagai registry template/placeholder/output dan tidak boleh dianggap sebagai source sequence numbering.

---

## 3. Tiga mekanisme yang berbeda

Aplikasi membedakan:

1. **Cancel Dokumen / Cancel Nomor Individual**
2. **Rollback Penomoran dari Nomor Tertentu**
3. **Cancel Penomoran Triwulan**

Ketiganya mempunyai dampak sequence berbeda dan tidak boleh diperlakukan sebagai semantic yang sama.

---

## 4. Cancel individual

Digunakan bila satu dokumen/transaksi dibatalkan secara bisnis, bukan karena urutan numbering salah.

Kontrak:

```text
nomor -> CANCELLED permanen
sequence tidak mundur
nomor tidak dipakai ulang
```

Nomor, sequence, alasan, actor, dan timestamp tetap menjadi history. Reissue setelah cancel individual memperoleh nomor baru.

Contoh:

```text
001 ACTIVE
002 CANCELLED
003 ACTIVE
nomor berikutnya -> 004
```

### Real-data isolated evidence

Pada isolated copy real-data:

```text
BPU01 -> sequence 1 -> CANCELLED
sequence after cancel -> 1
BPU02 -> sequence 2
baseline hash -> UNCHANGED
```

Status: **PASS**.

---

## 5. Tail rollback dari sequence tertentu

Tail rollback dipakai bila urutan nomor salah terhadap canonical source order.

Jika kesalahan dimulai dari sequence `N`, seluruh active tail mulai `N` harus dilepas. Tidak diperbolehkan membuat hole dengan melepas satu nomor di tengah.

Contoh:

```text
01 02 03 04 05
rollback from 03

05 -> lepas
04 -> lepas
03 -> lepas
sequence -> 02
```

Nomor yang dilepas oleh rollback boleh digunakan kembali.

### Guard CANCELLED permanen

Rollback tidak boleh melintasi nomor SPJ yang sudah `CANCELLED` secara individual karena nomor tersebut tidak boleh tersedia kembali.

### Real-data isolated evidence

Pada fresh isolated copy:

```text
initial sequences   : 1,2,3
rollback from       : 2
sequence after      : 1
renumbered sequence : 2
baseline hash       : UNCHANGED
```

Status: **PASS**.

---

## 6. Gate FINAL sebelum penomoran triwulan berikutnya

Penerbitan nomor tetap berjalan maju:

```text
TW1 -> TW2 -> TW3 -> TW4
```

Mulai TW2, batch penomoran hanya boleh berjalan jika **seluruh Paket SPJ pada triwulan sebelumnya dalam School + Fiscal Year + Fund Source yang sama sudah FINAL**.

Kontrak:

```text
TW1 belum FINAL semua -> TW2 BLOCKED
TW2 belum FINAL semua -> TW3 BLOCKED
TW3 belum FINAL semua -> TW4 BLOCKED
```

`FINAL` berarti seluruh dokumen aktif pada Paket sudah difinalkan dan snapshot dikunci. Dokumen histori berstatus `CANCELLED` tetap disimpan tetapi **tidak menghalangi Paket menjadi FINAL** setelah seluruh dokumen aktif/penggantinya sudah FINAL.

Required numbered document identities saat FINAL tidak dibangun dari array tipe terpisah. Lifecycle membaca registry, eligibility, `event_date_rule`, dan `scope_rule` untuk menentukan dokumen canonical yang harus tersedia pada Paket tersebut.

Jika triwulan sebelumnya memang tidak mempunyai transaksi SPJ yang mempunyai item, tidak ada prasyarat Paket yang harus difinalkan dan penomoran triwulan berikutnya boleh berjalan.

Gate ini diperiksa **sebelum** `QuarterNumberingRun` dibuat agar prasyarat lifecycle yang belum selesai tidak menghasilkan run FAILED semu.

Tujuan aturan ini:

- memastikan koreksi hasil cetak pada triwulan sebelumnya sudah selesai;
- mencegah operator melanjutkan sequence periode berikutnya ketika dokumen sebelumnya masih terbuka untuk koreksi;
- menjaga alur `NUMBERED -> cetak/periksa -> koreksi uraian bila perlu -> FINAL -> triwulan berikutnya`;
- tidak mencampur sumber dana lain dalam dependency.

---

## 7. Cancel penomoran triwulan

Pembatalan penuh berjalan mundur:

```text
TW4 -> TW3 -> TW2 -> TW1
```

Rule:

- TW3 tidak boleh di-reset bila TW4 masih mempunyai numbering aktif;
- TW2 tidak boleh di-reset bila TW3/TW4 masih aktif;
- TW1 tidak boleh di-reset bila TW2/TW3/TW4 masih aktif;
- dependency hanya dihitung pada School + Fiscal Year + Fund Source yang sama;
- numbering fund source lain tidak boleh memblokir;
- full quarter reset ditolak bila target quarter memiliki nomor individual `CANCELLED` permanen;
- quarter `CLOSED` harus dibuka kembali sebelum rollback.

Checkpoint sequence dibangun ulang dari numbering yang masih sah:

```text
Cancel TW3 -> akhir TW2
Cancel TW2 -> akhir TW1
Cancel TW1 -> sequence efektif 0 bila tidak ada history sah sebelumnya
```

### Status verification

```text
FUNCTIONAL REGRESSION              : PASS
ISOLATED REAL-DATA QUARTER RUNTIME : PENDING / OPTIONAL
```

Data nyata 2026 yang sedang digunakan tidak mempunyai transaksi TW3/TW4, sehingga dependency lintas quarter tidak boleh diklaim sebagai real-data runtime coverage dan tidak boleh dipaksa dengan data fiktif.

Command isolated quarter rollback tersedia bila dibutuhkan oleh operator flow/bug:

```powershell
php artisan spj:test-quarter-rollback-copy <NPSN> \
  --database=<FRESH_COPY> \
  --year=<YEAR> \
  --quarter=<Q> \
  --fund-source=<FS>
```

Tidak perlu menjalankan smoke test tambahan hanya untuk memperbesar coverage bila tidak ada bug atau kebutuhan operator.

---

## 8. Apa yang dilepas saat rollback

Rollback dilakukan dalam transaction database sekolah.

Untuk Paket terdampak:

- `spj_documents` non-CANCELLED hasil numbering yang di-rollback dilepas;
- history individual `CANCELLED` dipertahankan;
- nomor turunan hasil numbering dibersihkan menggunakan target relation/field registry yang applicable;
- Paket kembali ke `DRAFT`;
- current document number, numbered/finalization/snapshot state yang terkait numbering aktif dibersihkan;
- sequence dibangun ulang dari nomor yang masih sah;
- operational audit rollback tetap dipertahankan.

Rollback tidak pernah mengedit source ARKAS/BKU.

---

## 9. Numbering-domain history vs operational audit

Untuk numbering yang benar-benar di-rollback, identity numbering aktif dapat dilepas sehingga nomor tersedia kembali sesuai checkpoint.

Operational audit **tidak dihapus** dan minimal harus menjelaskan:

```text
actor
waktu
fiscal year + fund source
sequence/quarter yang di-rollback
alasan
```

Individual `CANCELLED` berbeda: history nomor dan sequence-nya permanen.

---

## 10. Koreksi setelah NUMBERED

### `item_description` dan `payment_description`

Koreksi teks operator yang tetap diperbolehkan ketika Paket `NUMBERED`:

```text
item_description
payment_description
```

`description` dan rincian finansial source ARKAS/BKU tetap read-only.

Perubahan dua uraian tersebut tidak membatalkan nomor, tidak menurunkan Paket, tidak mengubah sequence, dan tidak mengubah gross/tax/net. Preview/cetak ulang memakai uraian terbaru dengan nomor yang sama.

Pada `FINAL`, kedua uraian tersebut terkunci dan koreksi harus melalui lifecycle resmi.

### Data Paket lain

Data berikut tidak boleh diubah langsung pada `NUMBERED`/`FINAL`:

- `spj_category`;
- payment method/reference selain koreksi `payment_description`;
- penerima utama;
- vendor/penyedia;
- procurement/invoice/SiPLah operator-owned metadata;
- data barang/pengadaan selain koreksi teks `item_description`;
- peserta konsumsi;
- pekerja pemeliharaan;
- penerima honor;
- pelaksana SPPD;
- penerima JASA_LAINNYA;
- Isian Manual lain yang memengaruhi substansi dokumen.

Koreksi substansi mengikuti lifecycle:

```text
NUMBERED
-> rollback/cancel numbering yang sesuai
-> DRAFT
-> perbaiki data
-> validasi ulang
-> READY
-> numbering ulang canonical
```

---

## 11. Source order authoritative

Nomor SPJ tidak mengikuti:

- local package id;
- waktu operator membuka Paket;
- waktu Paket menjadi READY;
- urutan klik operator.

Canonical order berasal dari `SpjNumberingOrderService`, tetapi pemilihan tanggal event untuk setiap document type berasal dari `event_date_rule` pada registry canonical.

Real-data preflight read-only sudah PASS sebelum mutation QA, dengan baseline SHA-256 tetap tidak berubah.

---

## 12. Halaman format dan halaman penomoran

Halaman **Format Penomoran** tidak mempunyai list document type sendiri. `DocumentNumberFormatController` memperoleh numbered codes dari policy/registry dan label tampilan juga berasal dari registry.

Halaman **Penomoran Triwulan** memakai source yang sama untuk checkbox document type. Karena itu penambahan/removal document type canonical tidak memerlukan sinkronisasi manual dua halaman.

Persisted legacy format row tidak otomatis dihapus hanya karena tidak lagi tampil sebagai numbered canonical type. History/config lama tetap dipertahankan kecuali ada migration/cleanup eksplisit.

---

## 13. Token format nomor `{TW}`

Token:

```text
{TW} -> I / II / III / IV
```

Renderer **tidak** menambahkan prefix literal `TW.`.

Default pattern yang memakai `{TW}` menghasilkan contoh:

```text
0001/SPJ/SMPN.2/II/2026
```

Jika sekolah/operator membutuhkan prefix `TW.`, tuliskan secara eksplisit pada pattern:

```text
{SEQ}/SPJ/{SCHOOL}/TW.{TW}/{YEAR}
```

Hasil:

```text
0001/SPJ/SMPN.2/TW.II/2026
```

Nomor yang telah diterbitkan sebelum perubahan format tidak dimutasi otomatis.

---

## 14. Isolated real-data mutation commands

Mutation QA tidak boleh diarahkan ke baseline asli.

Command yang tersedia:

```powershell
php artisan spj:test-numbering-copy <NPSN> --database=<COPY> --year=<Y> --quarter=<Q> --fund-source=<FS>
php artisan spj:test-cancel-copy <NPSN> --database=<COPY> --year=<Y> --quarter=<Q> --fund-source=<FS>
php artisan spj:test-tail-rollback-copy <NPSN> --database=<FRESH_COPY> --year=<Y> --quarter=<Q> --fund-source=<FS>
php artisan spj:test-quarter-rollback-copy <NPSN> --database=<FRESH_COPY> --year=<Y> --quarter=<Q> --fund-source=<FS>
```

Baseline protection harus menolak path database asli dan memverifikasi hash baseline sebelum/sesudah mutation.

Status real-data isolated saat ini:

```text
first numbering        : PASS
individual cancel      : PASS
reserved next sequence : PASS
tail rollback          : PASS
quarter rollback       : PENDING / OPTIONAL
```

---

## 15. UI dan authorization

Rollback numbering dan cancel numbering triwulan adalah action administrator. Backend authorization dan tenant context tetap authoritative; menyembunyikan tombol saja tidak cukup.

---

## 16. Regression dan strategi test

Regression utama:

```text
tests/Feature/SpjAutomaticNumberingPolicyTest.php
tests/Feature/DocumentNumberingWorkflowTest.php
tests/Feature/DocumentNumberFormatSettingsTest.php
tests/Feature/SpjNumberingRollbackTest.php
tests/Feature/SpjOwnershipMigrationTest.php
tests/Feature/SpjWorkspaceMigrationTest.php
tests/Unit/QuarterNumberingPlaceholderTest.php
```

Strategi ke depan:

```text
operator flow -> bug nyata -> fix -> focused regression bila perlu
```

Jangan menambah test/smoke baru tanpa bug atau risiko konkret yang perlu dikunci.

Untuk perubahan metadata numbering, edit registry canonical lebih dulu. Jangan membuat daftar type, label, category, event date, target field, atau scope kedua di consumer.

Checkpoint CI terbaru selalu lihat `P0_VERIFICATION_KIT.md` §1, bukan angka historis di feature guide ini.
