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

---

## 7. Sinkronisasi transaksi RKAS/BKU

Canonical transaction adapter bertugas menjaga identitas source dan mapping domain.

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

## 16. KONSUMSI dan Dapodik

Unified Employee master tidak mengubah rule auto-fill peserta KONSUMSI.

Kontrak tetap:

```text
Auto-fill KONSUMSI = Employee yang memenuhi source DAPODIK sesuai policy aktif
Participant manual = diperbolehkan
```

Jangan memperluas auto-fill ke semua Employee hanya karena identity master sudah unified.

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