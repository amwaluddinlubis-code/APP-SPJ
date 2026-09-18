# Sinkronisasi Data — ARKAS/BKU, Dapodik, Reconciliation, dan Identity

Terakhir diverifikasi: **2026-09-11** terhadap branch `gui-standardization`.

Status dokumen: **ACTIVE TECHNICAL GUIDE**.

Dokumen ini adalah panduan canonical untuk mekanisme sinkronisasi data aplikasi. Ia menjelaskan **alur runtime, ownership data, tenant boundary, safe-sync, reconciliation, dan employee identity**.

Dokumen ini bukan sumber status release. Gunakan:

- `CURRENT_PROGRESS.md` untuk status/evidence terbaru;
- `DEVELOPMENT_ROADMAP.md` untuk prioritas pekerjaan;
- `SPJ_DESIGN_DECISIONS.md` untuk kontrak domain permanen;
- `ARKAS_IMPORTER.md` untuk Generic ARKAS Importer yang bersifat profile-driven.

---

## 1. Prinsip utama sinkronisasi

1. **ARKAS/BKU adalah source readonly.**
2. **Dapodik adalah source eksternal untuk data GTK/siswa, bukan workspace manual operator.**
3. **Data operator SPJ adalah overlay dan tidak boleh hilang saat source disinkronkan ulang.**
4. **Boundary tenant canonical adalah `School + Fiscal Year + Fund Source`.**
5. **Source missing bukan alasan menghapus pekerjaan operator.**
6. **Source berubah pada Paket yang sudah lanjut harus menghasilkan reconciliation bila perubahan relevan.**
7. **NUMBERED/FINAL tidak boleh dimutasi diam-diam oleh sinkronisasi.**
8. **Identity pegawai disatukan lintas ARKAS/PTK/Dapodik dengan strong identifier; nama ambigu tidak boleh silent-merge.**
9. **Operator-locked employee tidak boleh ditimpa oleh source sync.**
10. **Sinkronisasi tidak boleh memfabrikasi data agar workflow SPJ terlihat lengkap.**

---

## 2. Jenis jalur data

Aplikasi saat ini mempunyai beberapa jalur yang berbeda dan tidak boleh dicampur:

### 2.1 Canonical ARKAS synchronization

Entry point domain utama:

```text
ArkasCanonicalSyncService
```

Tujuan:

- bootstrap tahun anggaran dan sumber dana;
- sinkronisasi profil sekolah;
- sinkronisasi PEGAWAI/PTK;
- sinkronisasi rekening/reference;
- sinkronisasi RKAS/BKU;
- membangun transaksi aplikasi;
- mempertahankan overlay SPJ;
- membuat reconciliation jika source berubah;
- membangun derived references seperti kegiatan dan rekanan.

### 2.2 Generic ARKAS Importer

Entry point terpisah yang profile-driven:

Untuk kebutuhan mirror mentah seluruh database, administrator dapat memakai tombol
**Sinkronkan Semua ARKAS** pada halaman importer atau jalur CLI
`arkas:sync-raw-mirror`. Keduanya menggunakan raw mirror yang sama.
Jalur ini menemukan semua tabel melalui Bridge, menyimpan schema dan payload tanpa
mapping domain, mempertahankan snapshot ketika tabel menjadi stale, dan tidak boleh
menulis data ke tabel domain/operator SPJ. Identity row raw mirror mengikuti primary
key SQLite yang dilaporkan Bridge. Primary key tunggal memakai nilai key asli; composite
primary key memakai seluruh komponen sesuai ordinal PK untuk membentuk identity
deterministic. Resolver fallback hanya dipakai bila tabel memang tidak mempunyai
primary key yang dapat digunakan.

Jalur GUI menjalankan job queue dengan konteks `School + Fiscal Year + Fund Source`.
Database ARKAS dibaca saja; tabel `rkas` lama dan tabel fresh SPJ tidak diubah oleh
proses mirror. Setelah snapshot selesai, job membentuk indeks transaksi/item fresh
dari `kas_umum` melalui foreign key ke raw mirror; fakta source tetap dibaca dari
payload raw dan paket SPJ tidak dibuat otomatis.

GUI Penganggaran membaca anggaran raw dengan relasi `rapbs.id_anggaran` ke
`anggaran.id_anggaran`, kemudian membatasi `tahun_anggaran` dan
`id_ref_sumber_dana` sesuai konteks aktif. Label program, subprogram, dan
kegiatan berasal dari `rapbs.id_ref_kode` ke `ref_kode.id_ref_kode`.

Filter Program, Subprogram, dan Kegiatan pada GUI lama dibentuk dari seluruh
baris raw `ref_kode` pada konteks tahun dan sumber dana aktif, bukan hanya kode
yang kebetulan sudah dipakai pada `rapbs`. Urutan pilihan mengikuti kode
numerik terkecil.

Referensi Rekening memakai master raw `ref_rekening` secara terpisah dengan
aturan `tahun = tahun aktif` dan `expired_date IS NULL`; daftar ini tidak
dibatasi hanya pada rekening yang kebetulan sudah dipakai oleh baris `rapbs`.

Tab Acuan Barang digerakkan oleh master `ref_acuan_barang`, sehingga tetap
tersedia sebelum operator mengisi `rapbs`. Baris master aktif tahun berjalan
di-inner-join secara logis ke `ref_rekening.kode_rekening`. Kode rekening utama
diambil dari `ref_acuan_barang.kode_rekening`; jika kosong, `id_barang` dipakai
sebagai identitas kelompok rekening hanya bila diawali prefix rekening belanja
yang diizinkan. Jika tidak ada pasangan kode persis, UI menampilkan `Belum Ada
Rekening` dan tidak mengarang satu kode rekening tertentu.
`rapbs` bukan sumber daftar referensi dan hanya dipakai untuk menampilkan jumlah
pemakaian bila sudah ada input operator.

Untuk menjaga halaman tetap ringan dan fokus membantu pengisian RKAS, tab ini
tidak memuat baris ketika belum ada kata kunci pencarian. Setelah dicari, setiap
barang ditampilkan satu baris dengan status rekening yang ringkas. Nama barang
yang sama disatukan pada tampilan agar hasil pencarian tidak berulang.

Relasi dibatasi pada rekening belanja `5.1.02.*`, `5.2.02.*`, `5.2.04.*`, dan
`5.2.05.*`. Satuan, harga referensi, batas bawah/atas, `kode_belanja`, dan
klasifikasi barang tetap dibaca dari master acuan barang; tabel sumber tidak
diubah. UI menampilkan harga maksimal dari master dan jumlah pemakaian dihitung
dari banyaknya baris `rapbs` untuk barang yang sama. Baris duplikat master
disatukan berdasarkan `id_barang` hanya pada tampilan.

Pemilihan snapshot anggaran tidak menjumlahkan seluruh riwayat. Sistem membatasi
`anggaran` pada `is_approve = 1`, `is_aktif = 1`, dan `soft_delete = 0`, kemudian
memilih `is_revisi` terbesar untuk tahun dan sumber dana aktif. `last_update`
terbaru dipakai sebagai tie-breaker bila terdapat lebih dari satu record pada
revisi terakhir.

```text
ArkasImporterController
→ ArkasDatabaseExplorer / Bridge
→ ArkasImportProfile
→ ArkasStagingService
→ ArkasReconciliationService
→ ArkasGenericImportService
→ ArkasDomainAdapter
```

Gunakan `ARKAS_IMPORTER.md` untuk detail mapping, sync mode, stable source key, preview, concurrency lock, dan metrics.

Generic Importer **bukan pengganti otomatis** canonical transaction sync; keduanya memiliki tujuan dan boundary berbeda.

Pada UI, Generic Importer memiliki mode **Sederhana** (preset tabel yang dikenal) dan **Lanjutan** (mapping/profile custom). Mode sederhana tetap hanya tersedia untuk administrator. Untuk referensi Program/Subprogram/Kegiatan, gunakan profile `ref_kode` dengan target `activity_reference`; setelah itu canonical sync RKAS/BKU tetap diperlukan bila transaksi lama perlu menerima perubahan nama kegiatan.

### 2.3 Dapodik synchronization

Entry point:

```text
DapodikSynchronizationService
```

Data utama:

```text
getGtk
getPesertaDidik
```

Dapodik menyinkronkan Employee/GTK dan Student ke database tenant aktif.

---

## 3. Canonical ARKAS pipeline

Runtime utama:

```text
ARKAS database
→ Arkas Bridge
→ ArkasStagingService
→ ArkasReferenceSynchronizationService
→ ArkasSynchronizationServiceV2
→ reconciliation / derived references
→ database tenant
```

`ArkasCanonicalSyncService` memegang lock:

```text
arkas-canonical-sync:{school_id}:{fiscal_year_id}
```

Tujuan lock adalah mencegah dua canonical sync berjalan bersamaan pada sekolah+tahun yang sama.

Jika lock tidak diperoleh, sinkronisasi harus berhenti dengan pesan bahwa sinkronisasi sedang berjalan.

---

## 4. Data yang di-stage dari ARKAS

Canonical sync saat ini membaca/stage data seperti:

```text
years
fund-sources
profile
pegawai
ptk
rekening
periods
rkas / rapbs
bku / kas_umum
rapbs_periode
identity
```

Staging adalah boundary sebelum data ditulis ke domain aplikasi.

Jangan membuat jalur baru yang membaca database ARKAS lalu langsung menulis tabel transaksi/overlay tanpa melalui service/domain boundary yang sesuai.

---

## 5. Bootstrap tahun anggaran dan sumber dana

Sebelum operasi tahun tertentu, aplikasi dapat membaca daftar tahun dan sumber dana dari ARKAS.

Canonical context disimpan sebagai kombinasi:

```text
Fiscal Year + Fund Source
```

dan tetap berada di bawah sekolah tenant aktif.

### Scope anggaran pada GUI lama

GUI Penganggaran tetap memakai tampilan lama, tetapi ketika raw mirror tersedia
ia membaca `rapbs` dan `rapbs_periode` secara read-only. Total tahunan memakai
`rapbs.JUMLAH`; filter bulan, triwulan, dan semester menjumlahkan
`rapbs_periode.JUMLAH` pada periode yang sama. Realisasi hanya mengambil
`kas_umum` yang terhubung melalui `ID_RAPBS_PERIODE` dan berada pada scope
tersebut, sehingga `sisa = anggaran scope - realisasi scope`.

Validasi terhadap laporan RKAS per triwulan SMP Negeri 2 Ranto Baek tahun 2026
menunjukkan total tahunan Rp276.390.000 dan empat triwulan masing-masing
Rp69.097.500.

Boundary operasional penuh:

```text
School + Fiscal Year + Fund Source
```

Route/job yang memakai connection `school` wajib memastikan tenant sekolah benar sudah aktif sebelum membaca atau menulis model tenant.

---

## 6. Reference synchronization ARKAS

`ArkasReferenceSynchronizationService` menangani reference yang aman disegarkan dari source, termasuk:

- fiscal year contexts;
- fund sources;
- school profile;
- PEGAWAI/PTK → unified Employee;
- account references;
- ARKAS periods;
- activity references;
- business partners/rekanan derived dari source.

Reference sync tidak boleh mengubah operator SPJ overlay hanya karena source reference berubah.

### 6.1 Hierarki referensi kegiatan — lokasi canonical

Jangan mencari Program/Sub Program dari tabel transaksi atau menebak namanya dari
`activity_name`. Sumber canonical hierarki kegiatan berada pada:

```text
Tabel sumber       : activity_references
View siap pakai    : activity_hierarchy_references
Migration          : 2026_09_12_150000_create_activity_hierarchy_references_view
```

View tersebut menggabungkan baris `activity_references` berdasarkan `fiscal_year_id`
dan prefix `activity_code`:

```text
Panjang 3 karakter  = Program
Panjang 6 karakter  = Sub Program
Panjang 9 karakter  = Kegiatan
```

Contoh:

```text
06.        -> program_code / program_name
06.05.     -> sub_program_code / sub_program_name
06.05.08.  -> activity_code / activity_name
```

Kolom canonical pada view:

```text
fiscal_year_id
program_code, program_name
sub_program_code, sub_program_name
activity_code, activity_name
```

Seluruh query hierarki wajib mengikat `fiscal_year_id`; kode yang sama dari tahun
anggaran berbeda tidak boleh dicampur. `SpjTemplateService` membaca view ini untuk
placeholder Program/Sub Program, sedangkan `KODE_KEGIATAN` dan `NAMA_KEGIATAN` tetap
mengikuti snapshot transaksi.

---

## 7. Sinkronisasi transaksi RKAS/BKU

Canonical transaction adapter bertugas menjaga identitas source dan mapping domain.

Daftar Transaksi membaca indeks `spj_fresh_transactions` dan payload raw. Satu
transaksi fresh mewakili satu kelompok BELANJA dengan `NO_BUKTI` yang sama; setiap
`ID_KAS_UMUM` tetap menjadi item fresh terpisah. `source_key` transaksi dibentuk
sebagai SHA-256 dari daftar `ID_KAS_UMUM` item yang diurutkan, sama dengan identity
canonical transaction sync lama, sedangkan `source_key` item adalah
`ID_KAS_UMUM` itu sendiri.

Jalur workspace/overlay lama tidak boleh mengasumsikan `source_key` transaksi fresh
sama dengan satu `ID_KAS_UMUM`. Compatibility resolver untuk grouped source key
merupakan tahap integrasi terpisah sebelum flow operator fresh dinyatakan siap.

Setelah projection, transaksi source yang tidak lagi terbentuk dari snapshot
`kas_umum` ditandai `SOURCE_MISSING` pada indeks fresh. Proses ini tidak menghapus
baris, overlay operator, Paket SPJ, nomor dokumen, maupun data audit. Jika source
muncul kembali pada snapshot berikutnya, projection mengaktifkan kembali status
sumber dan mengosongkan `source_missing_since`.

Projection transaksi hanya menerima `kas_umum` yang `id_anggaran`-nya berada
di snapshot `anggaran` aktif untuk tahun dan sumber dana yang sama. Snapshot
anggaran dipilih dengan `is_approve = 1`, `is_aktif = 1`, `soft_delete = 0`,
revisi terbesar, dan `last_update` terbaru. Hanya row BELANJA
(`id_ref_bku` 4/15/24/35 atau kategori bridge `BELANJA`) yang menjadi item
transaksi; row pajak dan arus lain tidak dibuat sebagai transaksi tersendiri.

Agregasi bruto/pajak/netto lintas seluruh item grouped transaction tetap menjadi
hardening lanjutan. Sampai tahap itu ditutup oleh focused regression, accessor/UI
tidak boleh dianggap sudah merepresentasikan total grouped transaction secara penuh.

Kontrak utama:

```text
source transaction/item = readonly facts
operator SPJ overlay     = preserved
```

Contoh source facts:

- source key;
- nomor bukti;
- tanggal transaksi;
- uraian BKU;
- rekening/kegiatan;
- penerima source;
- quantity/unit/unit price/amount;
- gross/tax/net;
- payload source.

Contoh overlay:

- `item_description`;
- `spj_category`;
- `payment_description`;
- `payment_method`;
- `payment_reference`;
- `receipt_recipient_name`;
- vendor/procurement manual;
- detail kategori;
- Paket/dokumen/numbering/lifecycle.

Sinkronisasi tidak boleh menggunakan source refresh sebagai alasan untuk menulis ulang overlay tersebut.

---

## 8. Safe-sync semantics

Canonical behavior:

```text
source tetap ada
→ source fields boleh diperbarui
→ overlay dipertahankan

source hilang
→ tandai SOURCE_MISSING / state equivalent
→ pertahankan transaction identity
→ pertahankan overlay/package/document state

source kembali
→ gunakan identity transaction yang sama
→ aktifkan kembali source state
→ overlay tetap ada

source berubah
→ update source facts
→ buat/refresh reconciliation jika perubahan berdampak pada pekerjaan SPJ
```

Yang dilarang:

- delete transaction hanya karena source tidak muncul pada satu sync;
- delete Paket saat source hilang;
- menghapus manual data agar source dan Paket terlihat cocok;
- silent rewrite terhadap NUMBERED/FINAL;
- memaksa data source agar validation Paket lulus.

---

## 9. Reconciliation

Reconciliation adalah boundary antara perubahan source dan pekerjaan operator yang sudah ada.

Gunakan reconciliation ketika source berubah setelah operator sudah membuat atau mengisi Paket.

Reconciliation harus dapat menunjukkan perubahan bermakna seperti:

```text
before
→ after
```

pada field yang relevan.

Prinsip:

- perubahan source tidak langsung dianggap operator error;
- operator harus dapat melihat perubahan yang perlu ditinjau;
- stale reconciliation event tidak boleh menimpa resolution terbaru;
- NUMBERED/FINAL tidak boleh dimutasi otomatis karena reconciliation.

---

## 10. Employee identity lintas ARKAS dan Dapodik

Employee adalah identity layer bersama.

Source yang dapat berkontribusi:

```text
ARKAS PEGAWAI
ARKAS PTK
DAPODIK GTK
manual/operator
```

Matching canonical menggunakan strong identity terlebih dahulu:

1. NUPTK;
2. NIP;
3. NIK;
4. normalized name hanya jika hasilnya unik/non-ambiguous.

Nama yang sama tetapi ambiguous **tidak boleh** menyebabkan silent merge.

Source provenance disimpan agar satu Employee dapat tetap diketahui berasal dari lebih dari satu feed.

---

## 11. ARKAS employee synchronization

PEGAWAI dan PTK dari ARKAS adalah dua feed untuk identity yang sama, bukan dua master pegawai terpisah.

Setelah feed diproses:

```text
EmployeeIdentityService::fuseDuplicates(false)
```

dapat menyatukan duplicate legacy yang mempunyai bukti identity kuat.

Setelah itu dilakukan sweep berdasarkan row yang terlihat pada sync tersebut.

Employee hanya boleh dinonaktifkan bila aturan effective-active menunjukkan ia sudah tidak aktif pada seluruh source relevan.

---

## 12. Dapodik synchronization

`DapodikSynchronizationService`:

1. mengambil GTK dari `getGtk`;
2. mengambil siswa dari `getPesertaDidik`;
3. menjalankan transaction pada connection `school`;
4. mencari Employee existing melalui shared `EmployeeIdentityService`;
5. membuat Employee baru bila tidak ada match valid;
6. merge source provenance;
7. mempertahankan operator lock;
8. fuse duplicate legacy;
9. sweep stale Dapodik rows;
10. menonaktifkan Student Dapodik yang tidak lagi terlihat pada feed.

---

## 13. Operator lock pada Employee

Jika `operator_locked = true`, koreksi operator menjadi canonical untuk field yang sudah terisi.

Source sync tetap boleh:

- memperbarui provenance/source payload;
- memperbarui last-seen metadata;
- mengisi field canonical yang masih kosong jika tersedia.

Source sync tidak boleh menimpa koreksi operator yang sudah terkunci.

---

## 14. Effective active state Employee

Status aktif Employee tidak boleh ditentukan hanya oleh satu source terakhir yang disinkronkan.

Contoh prinsip:

```text
masih aktif di ARKAS, hilang dari Dapodik
→ jangan otomatis nonaktif jika source lain masih menyatakan aktif

hilang dari ARKAS tetapi aktif di Dapodik
→ tetap aktif

operator-locked/manual identity
→ jangan disapu hanya karena feed eksternal kosong
```

Empty feed juga tidak boleh dianggap otomatis berarti seluruh pegawai harus dinonaktifkan.

---

## 15. Student synchronization

Student saat ini bersumber dari Dapodik.

Matching utama menggunakan identifier seperti:

```text
NISN
DAPODIK peserta_didik_id
```

Setelah sync, row Dapodik yang tidak muncul lagi dapat ditandai `is_active = false` sesuai semantics service.

Student tidak memakai fusion logic Employee karena domain identity dan identifier berbeda.

---

## 16. KONSUMSI, SPPD, dan roster menyatu

Roster peserta KONSUMSI dan SPPD memakai master Pegawai menyatu (keputusan aktif; Dapodik-only dicabut).

Kontrak tetap:

```text
Auto-fill KONSUMSI/SPPD = master Pegawai menyatu (ARKAS + Dapodik + Manual)
Participant manual = diperbolehkan
```

---

## 17. SiPLah dan sinkronisasi

SiPLah adalah procurement/payment channel, bukan kategori SPJ.

Jika metadata SiPLah berasal dari source:

- source-authoritative fields harus dipertahankan sebagai provenance/reference;
- operator-owned correction tidak boleh ditimpa tanpa rule eksplisit;
- perubahan metadata source dapat memicu reconciliation bila berdampak pada Paket;
- internal Surat Pesanan dan nomor marketplace SiPLah tetap dua konsep berbeda.

---

## 18. Sync mode Generic Importer

Generic ARKAS Importer mendukung mode yang berbeda dari canonical sync, termasuk:

```text
Upsert
Incremental
Full Refresh
```

Mode tersebut hanya boleh bekerja pada scope target yang benar.

Full Refresh tidak berarti menghapus seluruh database tenant atau overlay SPJ.

Stable source key harus konsisten antara:

```text
preview
staging
reconciliation
sync
```

Detail kontrak importer berada di `ARKAS_IMPORTER.md`.

---

## 19. Concurrency dan queue

Sinkronisasi yang dapat berjalan lama harus mempunyai resource lock sesuai scope.

Untuk background job:

- school tenant wajib diaktifkan dari identity job sebelum query/write connection `school`;
- fiscal year/context harus diverifikasi kembali;
- jangan bergantung pada tenant yang kebetulan aktif pada worker sebelumnya.

Queue worker adalah proses panjang; context tenant lama tidak boleh bocor ke job berikutnya.

---

## 20. Audit trail dan metrics

Sinkronisasi operasional sebaiknya meninggalkan evidence yang cukup untuk menjawab:

- siapa/apa yang menjalankan sync;
- sekolah/tahun/sumber dana mana;
- source mana;
- berapa row dibaca;
- berapa new/changed/unchanged/removed;
- apakah ada reconciliation;
- apakah sync berhasil/gagal.

Generic Importer sudah mempunyai semantic metrics dan import runs. Canonical sync/reconciliation harus tetap dapat diaudit melalui operational audit/log yang relevan.

---

## 21. Failure semantics

Sinkronisasi harus gagal dengan jelas jika boundary kritis tidak terpenuhi, misalnya:

- database/source ARKAS tidak tersedia;
- tahun/sumber dana tidak ditemukan;
- tenant belum aktif;
- source key tidak stabil;
- schema drift membuat mapping tidak aman;
- concurrent sync sedang berjalan;
- Dapodik endpoint gagal HTTP;
- payload tidak sesuai contract.

Failure tidak boleh dianggap sebagai empty source lalu menyapu data yang sebelumnya valid.

---

## 22. Read-only audit sebelum real-data mutation

Audit real-data bukan sync.

Gunakan jalur read-only seperti:

```powershell
php artisan spj:audit-quarter <NPSN> --quarter=<TW>
```

Audit tidak boleh:

- membuat tenant baru;
- auto-migrate database;
- memperbaiki source;
- menjalankan sync;
- mengubah package;
- menerbitkan nomor.

Jika real-data perlu diperbaiki setelah audit, lakukan pada isolated copy sesuai kontrak release verification.

---

## 23. Checklist sebelum menambah jalur sinkronisasi baru

Sebelum menambah source/feed baru, jawab:

1. Source apa dan siapa authoritative owner-nya?
2. Apakah row mempunyai stable source key?
3. Tenant scope apa yang berlaku?
4. Apa yang boleh di-update?
5. Apa yang merupakan operator overlay?
6. Bagaimana source missing ditangani?
7. Apakah source returning mempertahankan identity lama?
8. Apakah perubahan source membutuhkan reconciliation?
9. Bagaimana NUMBERED/FINAL dilindungi?
10. Apakah ada operator lock?
11. Bagaimana duplicate identity dicegah?
12. Bagaimana empty feed dibedakan dari data benar-benar kosong?
13. Apakah background job mengaktifkan tenant sendiri?
14. Apakah ada lock/concurrency guard?
15. Metrics/audit apa yang dihasilkan?
16. Regression test apa yang masuk release-critical suite?

---

## 24. Verification minimum

Untuk perubahan synchronization yang menyentuh release-safety, verifikasi minimal harus mencakup regression yang relevan terhadap:

- tenant isolation;
- overlay preservation;
- source missing/returning;
- reconciliation before/after;
- NUMBERED/FINAL protection;
- stable source key;
- employee strong-identity merge;
- ambiguous-name no-merge;
- operator-locked employee preservation;
- empty-feed safety;
- background tenant activation;
- concurrency lock.

Gunakan `P0_VERIFICATION_KIT.md` untuk release verification flow.

---

## 25. Dokumen terkait

```text
docs/CURRENT_PROGRESS.md
docs/SPJ_DESIGN_DECISIONS.md
docs/ARCHITECTURE_COMPLETE.md
docs/ARKAS_IMPORTER.md
docs/USER_SCENARIOS.md
docs/P0_VERIFICATION_KIT.md
docs/P0_01_SOURCE_AUDIT.md
```

Jika ada konflik:

1. keputusan domain permanen mengikuti `SPJ_DESIGN_DECISIONS.md`;
2. status/evidence mengikuti `CURRENT_PROGRESS.md`;
3. dokumen ini menjadi panduan teknis canonical untuk perilaku sinkronisasi.
