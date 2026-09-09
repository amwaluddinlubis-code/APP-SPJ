# Modul Importer dan Sinkronisasi ARKAS

Terakhir diperbarui: **2026-09-10**

Modul ini adalah jalur kanonik untuk membaca database ARKAS melalui Bridge, menyimpan snapshot staging, memvalidasi mapping, melakukan preview rekonsiliasi, dan mengisi domain aplikasi melalui adapter.

## Status saat ini

**IMPLEMENTED / SOURCE-KEY PASS / TENANT BOUNDARY PASS / SYNC-MODE PASS / HARDENING OPEN.**

Checkpoint correctness:

```text
6aed816a4034c6351498922f6dfdaf74a76d7566
source-key resolver + non-empty Generic Import regression

b3aa081c1a16e51ccdf80466877d2398b2b0de3e
tenant boundary + cross-school/cross-year regression

50794b4872d3be273ee73fbaccdd438fcca4569d
Upsert / Incremental / Full Refresh regression
```

CI pada checkpoint terbaru:

```text
frontend build         PASS
Blade view cache       PASS
SPJ Critical PHPUnit   PASS — 134 tests / 896 assertions
repository Pint        WARN — 1 pre-existing single_quote issue
```

Pint warning tetap berada pada `tests/Feature/SyncProgressUiTest.php` dan bukan regression importer.

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

## Source key

Source key harus deterministic antara preview, staging, reconciliation, dan sync.

`ArkasSourceKeyResolver` memakai urutan:

```text
configured source key (case-insensitive)
-> known canonical ARKAS identifiers
-> payload hash fallback
```

Payload hash bukan pengganti primary key bisnis. Untuk raw profile tanpa stable identifier, perubahan payload dapat menghasilkan source key baru sementara versi lama tetap ada pada Upsert/Incremental. Policy raw stable-key masih harus ditutup sebelum release-ready.

Regression source-key:

```text
tests/Unit/ArkasSourceKeyResolverTest.php
tests/Feature/ArkasGenericImportSourceKeyTest.php
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

Bridge-side `updated-since`/cursor tetap optimasi setelah correctness selesai, bukan requirement untuk deterministic semantics saat ini.

### Full Refresh — PASS

Kontrak yang sudah diregresikan:

- staging untuk profile + fiscal year aktif diganti penuh;
- row lama pada scope aktif yang hilang dari source dihapus;
- domain target fiscal year aktif diganti sesuai adapter;
- staging profile lain pada fiscal year yang sama tetap ada;
- staging profile yang sama pada fiscal year lain tetap ada;
- domain fiscal year lain tetap ada.

Regression RKAS nyata menggunakan snapshot:

```text
active year snapshot 1 : R1, R2
active year snapshot 2 : R2(updated), R3
active year state akhir: R2(updated), R3
other fiscal year      : tetap utuh
other staging profile  : tetap utuh
```

Full Refresh tidak boleh dipakai untuk menghapus overlay manual SPJ di luar ownership importer.

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

## Preview rekonsiliasi

Preview penuh membandingkan source terhadap staging dan menampilkan Baru/Berubah/Tetap/Hilang. Preview harus bersifat read-only terhadap target domain.

**Regression read-only preview masih terbuka** dan menjadi pekerjaan P0-08 berikutnya.

## Schema drift

Daftar kolom source disimpan saat mapping disimpan. Perubahan kolom harus dapat ditampilkan sebagai schema drift warning.

Masih perlu regression untuk memastikan hilangnya source key atau mandatory mapping benar-benar memblokir sync sesuai kontrak, bukan hanya menampilkan warning.

## Source kosong

Behavior source kosong belum ditutup dengan regression release-critical. Test berikutnya harus membedakan dengan jelas:

- Upsert/Incremental: tidak menghapus staging lama hanya karena snapshot kosong;
- Full Refresh: membersihkan hanya scope profile/year/domain aktif sesuai semantics refresh;
- fiscal year/profile/tenant lain tetap utuh.

## Background queue

Jika `ARKAS_SYNC_ASYNC=true`, job harus:

1. mengambil `school_id` dari database utama;
2. mengaktifkan tenant lewat `SchoolDatabaseManager`;
3. baru membaca profile/fiscal year tenant;
4. menjalankan importer pada connection sekolah yang benar.

Regression queue/background tenant activation masih wajib sebelum release-ready.

## Lock dan histori

Hardening yang masih terbuka:

- lock staging/import harus memasukkan identitas sekolah karena profile/fiscal-year ID lokal dapat sama antar tenant;
- `created_at` existing staging row harus mempertahankan first-created semantics bila field itu digunakan untuk audit;
- histori import harus membedakan read/new/changed/unchanged/removed;
- `records_written` jangan dianggap actual changed-row metric sampai semantics tersebut diperbaiki.

## Regression checklist P0-08

```text
[x] 1. GET importer mengaktifkan tenant sekolah yang benar
[x] 2. save mapping menulis hanya pada tenant aktif
[ ] 3. preview penuh tidak menulis domain
[x] 4. sync record non-kosong tidak memanggil method yang hilang
[x] 5. Upsert deterministic + preserves absent rows
[x] 6. Incremental deterministic berdasarkan last_synced_at
[x] 7. Full Refresh hanya membersihkan scope aktif
[x] 8. cross-school isolation
[x] 9. cross-year context ditolak sebelum tenant access salah
[ ] 10. source kosong aman
[ ] 11. schema drift memblokir sesuai kontrak
[ ] 12. background queue mengaktifkan tenant yang benar
[ ] 13. raw profile mempunyai stable-key policy eksplisit
```

## Status release

Source-key, tenant boundary, Upsert, Incremental, dan Full Refresh sekarang **FUNCTIONAL PASS**. Generic Importer **belum release-ready / belum READY FOR OPERATOR TEST** sampai preview read-only, raw stable-key, source kosong, schema drift, dan queue/background regression ditutup serta hardening concurrency/timestamp utama diselesaikan.

Status canonical dibaca bersama:

```text
docs/CURRENT_PROGRESS.md
docs/DEVELOPMENT_ROADMAP.md
```
