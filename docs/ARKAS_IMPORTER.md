# Modul Importer dan Sinkronisasi ARKAS

Terakhir diperbarui: **2026-09-12**

Checkpoint commit di dokumen ini adalah **implementation history** untuk fitur importer, bukan functional gate aplikasi saat ini. Evidence gate mutakhir selalu dibaca dari `P0_VERIFICATION_KIT.md` §1 dan status release dari `CURRENT_PROGRESS.md`.

Modul ini adalah jalur kanonik untuk membaca database ARKAS melalui Bridge, menyimpan snapshot staging, memvalidasi mapping, melakukan preview rekonsiliasi, dan mengisi domain aplikasi melalui adapter.

## Status saat ini

**IMPLEMENTED / SOURCE-KEY PASS / TENANT BOUNDARY PASS / SYNC-MODE PASS / RELEASE-GUARD PASS / HARDENING PASS / READY FOR OPERATOR TEST.**

Implementation checkpoints historis:

```text
6aed816a4034c6351498922f6dfdaf74a76d7566
source-key resolver + non-empty Generic Import regression

b3aa081c1a16e51ccdf80466877d2398b2b0de3e
tenant boundary + cross-school/cross-year regression

50794b4872d3be273ee73fbaccdd438fcca4569d
Upsert / Incremental / Full Refresh regression

56aefd25a50489151b77a155bf72b21fbebfd9d9
preview/raw/source-empty/schema-drift/queue release-safety regression

111de8c2781af6c8413661bcc512b651b09acc72
concurrency lock + timestamp semantics + semantic import metrics regression
```

Checkpoint di atas berguna untuk audit implementasi fitur, tetapi tidak boleh dipakai sebagai pengganti CI code gate aktif atau jumlah test/assertion terkini.

## Alur data

```text
Bridge ARKAS
  -> arkas_import_profiles
  -> arkas_import_rows (staging)
  -> preview rekonsiliasi
  -> ArkasDomainAdapter / snapshot raw
  -> domain aplikasi
```

Data ARKAS tetap readonly. Data operator SPJ adalah overlay dan tidak boleh ditimpa sembarang jalur importer.

## Tenant boundary

Setiap action Generic Importer yang menyentuh connection `school` wajib melewati:

```text
authenticated
-> administrator
-> active-school
-> active-year
-> query/write connection school
```

`active-school` harus berjalan sebelum `active-year` karena `FiscalYear` sendiri memakai connection tenant `school`.

Regression `tests/Feature/ArkasImporterTenantBoundaryTest.php` membuktikan:

- route importer membawa administrator + active-school + active-year;
- GET mengaktifkan database sekolah yang benar;
- profile tenant lain tidak terlihat;
- forged profile tenant lain pada preview/sync menghasilkan 404;
- save mapping hanya menulis tenant aktif;
- stale fiscal-year ID tenant lain ditolak setelah koneksi sekolah aktif dipilih.

Regression `tests/Feature/ArkasImportQueueTenantTest.php` menambah kontrak background: worker yang awalnya berada pada tenant lain wajib mengaktifkan sekolah dari `school_id` job sebelum membaca `ArkasImportProfile` dan `FiscalYear` tenant.

## Source key dan raw stable-key policy

Source key harus deterministic antara preview, staging, reconciliation, dan sync.

`ArkasSourceKeyResolver` memakai urutan:

```text
configured source key (case-insensitive)
-> known canonical ARKAS identifiers
-> payload hash fallback
```

Payload hash bukan pengganti primary key bisnis. Kebijakan runtime sekarang eksplisit:

```text
raw + Upsert/Incremental -> wajib effective stable source key
raw + Full Refresh       -> boleh tanpa stable key; payload hash dipakai untuk snapshot penuh
```

`ArkasImportGuard` menerapkan policy tersebut pada save konfigurasi, sync controller, service importer, dan background job sehingga jalur queue/internal tidak dapat melewati guard UI.

Regression source-key/release guard:

```text
tests/Unit/ArkasSourceKeyResolverTest.php
tests/Feature/ArkasGenericImportSourceKeyTest.php
tests/Feature/ArkasGenericImportReleaseSafetyTest.php
tests/Feature/ArkasImporterRuntimeGuardTest.php
```

## Mode sinkronisasi

Regression mode berada pada:

```text
tests/Feature/ArkasGenericImportSyncModeTest.php
```

### Upsert — PASS

Kontrak yang sudah diregresikan:

- stable key yang sama memperbarui row existing;
- source key baru menambah row;
- tidak membuat duplikasi key pada scope profile + fiscal year;
- row lama yang **absen pada snapshot berikutnya tetap dipertahankan**;
- snapshot source kosong juga mempertahankan staging existing;
- karena itu Upsert tidak mempunyai semantics delete seperti Full Refresh.

Contoh kontrak test:

```text
snapshot 1 : A, B
snapshot 2 : A(updated), C(new)
state akhir: A(updated), B(preserved), C(new)
```

### Incremental — PASS

Implementasi sekarang membaca snapshot Bridge lalu memfilter di aplikasi. Record hanya diproses bila nilai `source_updated_column` lebih baru dari `last_synced_at` sebelumnya.

Regression membuktikan:

```text
checkpoint      : 10:00
A updated_at    : 09:55 -> tidak diterapkan
B updated_at    : 10:05 -> diterapkan
C updated_at    : 10:06 -> diterapkan
next checkpoint : 10:10
```

Snapshot source kosong menghasilkan zero processed rows dan tidak menghapus staging lama. Bridge-side `updated-since`/cursor tetap optimasi setelah correctness selesai, bukan requirement untuk deterministic semantics saat ini.

### Full Refresh — PASS

Kontrak yang sudah diregresikan:

- staging untuk profile + fiscal year aktif diganti penuh;
- row lama pada scope aktif yang hilang dari source dihapus;
- domain target fiscal year aktif diganti sesuai adapter;
- staging profile lain pada fiscal year yang sama tetap ada;
- staging profile yang sama pada fiscal year lain tetap ada;
- domain fiscal year lain tetap ada;
- snapshot source kosong membersihkan hanya active profile/year/domain scope.

Regression RKAS nyata menggunakan snapshot:

```text
active year snapshot 1 : R1, R2
active year snapshot 2 : R2(updated), R3
active year state akhir: R2(updated), R3
other fiscal year      : tetap utuh
other staging profile  : tetap utuh
```

Full Refresh tidak boleh dipakai untuk menghapus overlay manual SPJ di luar ownership importer.

## Preview rekonsiliasi — PASS read-only

Preview penuh membandingkan source terhadap staging dan menampilkan Baru/Berubah/Tetap/Hilang.

`tests/Feature/ArkasGenericImportReleaseSafetyTest.php` membuktikan preview penuh tidak mengubah:

- `arkas_import_rows` staging;
- target domain `arkas_rkas_items`;
- histori `arkas_import_runs`.

## Schema drift — PASS blocking

`ArkasImportGuard::schemaErrors()` memblokir sync bila source column yang masih digunakan hilang, termasuk effective source key, mapped column, incremental timestamp, year column, atau fund-source column yang dikonfigurasi.

## Source kosong — PASS

Semantics release-critical:

- **Upsert:** preserve staging existing;
- **Incremental:** preserve staging existing;
- **Full Refresh:** membersihkan hanya active profile/year/domain scope;
- fiscal year/profile lain tetap utuh.

Exception/failure membaca sumber tetap harus gagal sebagai error, bukan diterjemahkan menjadi empty snapshot secara diam-diam.

## Background queue — PASS tenant activation + metrics

Jika `ARKAS_SYNC_ASYNC=true`, job:

1. mengambil `school_id` dari database utama;
2. mengaktifkan tenant lewat `SchoolDatabaseManager`;
3. baru membaca profile/fiscal year tenant;
4. mengambil `ArkasSource` yang di-scope ke school tersebut;
5. menjalankan configuration + schema guard;
6. menjalankan importer pada connection sekolah yang benar;
7. menyalin metric semantic run ke `BackgroundOperation.result`.

Metric background result:

```text
run_id
records_read
records_written
records_new
records_changed
records_unchanged
records_removed
```

## Concurrency lock — PASS

`ArkasTenantLockKey` membentuk resource lock canonical dari:

```text
school identity + source_table + fiscal_year_id
```

Generic Import dan Staging menggunakan resource key yang sama. Ini penting karena staging dan importer dapat menyentuh `arkas_import_rows` untuk tabel/tahun yang sama.

Kontrak yang diregresikan:

- tenant A dan tenant B menghasilkan lock berbeda walau nama tabel/tahun sama;
- staging dan import pada tenant+tabel+tahun yang sama menghasilkan lock identik;
- ketika lock staging resource yang sama sudah dipegang, Generic Import ditolak sebelum fetch/write;
- false contention lintas sekolah tidak terjadi.

Bila `ArkasSource.school_id` tidak tersedia, helper memakai hash path database tenant aktif sebagai fallback identity, dan gagal eksplisit bila identitas tenant sama sekali tidak tersedia.

## Timestamp semantics — PASS

`ArkasImportRowSynchronizer` sekarang menjadi jalur bersama untuk write staging/import rows.

Semantics timestamp:

```text
row baru      -> created_at = now, updated_at = now
row berubah   -> created_at tetap, updated_at = now
row unchanged -> created_at tetap, updated_at tetap
row removed   -> hanya Full Refresh, row dihapus
```

Dengan demikian `created_at` dapat dibaca sebagai first-created timestamp dan tidak lagi di-reset oleh `updateOrInsert` saat payload berubah.

## Import metrics — PASS

Migration `2026_09_10_000000_add_metrics_to_arkas_import_runs_table.php` menambahkan:

```text
records_new
records_changed
records_unchanged
records_removed
```

Bersama field existing:

```text
records_read
records_written
```

Semantics canonical:

- `records_read`: jumlah record yang masuk ke sinkronisasi setelah filter mode Incremental diterapkan;
- `records_new`: source key yang belum ada pada staging scope;
- `records_changed`: source key existing dengan payload hash berbeda;
- `records_unchanged`: source key existing dengan payload hash sama;
- `records_removed`: row existing yang dihapus oleh Full Refresh karena tidak ada pada incoming snapshot;
- `records_written`: actual insert + update, yaitu `new + changed`; removal tidak dicampur ke metric ini.

Full Refresh tidak lagi menghapus seluruh staging lebih dulu lalu membuat ulang row unchanged. Karena itu timestamp dan metrics tetap bermakna.

Regression utama:

```text
tests/Feature/ArkasImportHardeningTest.php
```

Test tersebut membuktikan Upsert metrics, Full Refresh removed metrics, timestamp preservation, dan shared tenant resource lock.

## Preset domain

Preset utama:

| Tabel | Target | Keterangan |
| --- | --- | --- |
| `rapbs` | RKAS/RAPBS | Item RKAS |
| `rapbs_periode` | Periode RKAS | Koordinat periode |
| `kas_umum` | BKU | Baris BKU |
| `ref_kode` | Referensi kegiatan | Hierarki kegiatan |
| `ref_periode` | Referensi periode | Label periode |
| `kas_umum_nota` | Raw | Metadata nota |
| `kas_umum_nota_pajak` | Raw | Rincian pajak |
| `ref_rekening` | Raw | Master rekening |

Tabel tanpa adapter domain dapat disimpan sebagai raw snapshot bila source-key contract-nya aman.

## Scale/performance lanjutan

Dua hal berikut masih perlu dievaluasi pada data besar, tetapi bukan blocker correctness operator-test yang sudah diregresikan:

- Bridge-side incremental delta fetch agar Incremental tidak menarik snapshot penuh;
- fetch limit Bridge saat ini `100000`, sehingga sumber yang lebih besar perlu strategi paging/explicit overflow detection agar tidak berisiko truncation diam-diam.

## Regression checklist P0-08

```text
[x] 1. GET importer mengaktifkan tenant sekolah yang benar
[x] 2. save mapping menulis hanya pada tenant aktif
[x] 3. preview penuh tidak menulis staging/domain/import history
[x] 4. sync record non-kosong tidak memanggil method yang hilang
[x] 5. Upsert deterministic + preserves absent rows
[x] 6. Incremental deterministic berdasarkan last_synced_at
[x] 7. Full Refresh hanya membersihkan scope aktif
[x] 8. cross-school isolation
[x] 9. cross-year context ditolak sebelum tenant access salah
[x] 10. source kosong aman sesuai semantics mode
[x] 11. schema drift memblokir mapped/key/update column yang hilang
[x] 12. background queue mengaktifkan tenant sebelum tenant model read
[x] 13. raw profile mempunyai stable-key policy eksplisit
[x] 14. staging/import memakai tenant resource lock yang sama
[x] 15. existing row mempertahankan first-created created_at
[x] 16. import history membedakan read/write/new/changed/unchanged/removed
```

## Status release

Generic Importer sekarang **READY FOR OPERATOR TEST** dari sisi functional correctness/hardening yang sudah diketahui dan diregresikan.

Ini bukan klaim bahwa keseluruhan aplikasi final release-ready. Yang masih terbuka berada pada real-tenant/operator verification, official-template/output QA, browser/runtime QA, installed-runtime checks yang masih DEFERRED, serta scale/performance importer. Six-category deterministic workflow dan functional document generator sendiri sudah berstatus PASS pada status canonical saat ini.

Status canonical dibaca bersama:

```text
docs/CURRENT_PROGRESS.md
docs/P0_VERIFICATION_KIT.md §1
docs/DEVELOPMENT_ROADMAP.md
```
