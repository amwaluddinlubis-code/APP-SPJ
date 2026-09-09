# Modul Importer dan Sinkronisasi ARKAS

Terakhir diperbarui: **2026-09-10**

Modul ini adalah jalur kanonik untuk membaca database ARKAS melalui Bridge, menyimpan snapshot staging, memvalidasi mapping, dan mengisi domain aplikasi melalui adapter. Operator tidak perlu mengubah atau rebuild Bridge ketika menambahkan tabel yang didukung konfigurasi importer.

## Status saat ini

**IMPLEMENTED / BLOCKED BEFORE OPERATOR TEST.**

Review terhadap head implementasi:

```text
ceb8df6f2a73c4e69cf13de8048ada2fff245fce
feat: canonicalize ARKAS importer and sync pipeline
```

menemukan dua blocker release-safety yang harus ditutup sebelum tombol sync Generic Importer dipakai untuk operator test:

1. `ArkasGenericImportService::synchronize()` memanggil `sourceKey()` yang belum memiliki implementasi pada service tersebut. Dataset non-kosong dapat gagal runtime pada jalur generic import.
2. Route importer belum seluruhnya menjamin boundary `active-school` + `active-year` sebelum model tenant connection `school` dibaca/ditulis.

Status/exit criteria canonical ada di `docs/CURRENT_PROGRESS.md` P0-08 dan `docs/DEVELOPMENT_ROADMAP.md` P0-08. Dokumentasi di bawah menjelaskan workflow target setelah blocker tersebut ditutup.

## Alur data

```text
Bridge ARKAS
  -> arkas_import_profiles
  -> arkas_import_rows (staging)
  -> preview rekonsiliasi
  -> domain adapter / snapshot raw
  -> laporan, transaksi, dan dokumen SPJ
```

Data ARKAS tetap readonly. Data manual operator SPJ tidak ditimpa oleh importer.

## Boundary tenant wajib

Importer menyimpan profile, staging, run history, fiscal year, dan domain target di database tenant/sekolah. Karena itu setiap request importer yang menyentuh connection `school` wajib berjalan setelah sekolah aktif diaktivasi dan fiscal year aktif tervalidasi.

Boundary target:

```text
authenticated user
→ administrator guard untuk konfigurasi importer
→ active school
→ active fiscal year / fund source
→ query/write connection school
```

Authorization administrator tidak menggantikan tenant activation. Keduanya adalah boundary yang berbeda.

Job background juga harus mengaktifkan tenant berdasarkan `school_id` sebelum membaca profile/fiscal year atau menulis staging/domain.

## Cara menggunakan setelah P0-08 PASS

Buka:

```text
/pengaturan/arkas/importer
```

1. Pastikan sumber database ARKAS, sekolah aktif, dan tahun anggaran aktif sudah dipilih.
2. Pilih tabel ARKAS.
3. Periksa preset dan mapping awal yang dikenali dari nama kolom.
4. Tentukan kolom kunci sumber.
5. Pilih mode sinkronisasi:
   - **Incremental**: memproses baris yang berubah setelah import terakhir; wajib memilih kolom terakhir berubah.
   - **Upsert**: membaca snapshot dan memperbarui/menambah baris tanpa menghapus staging lama.
   - **Full refresh**: mengganti snapshot dan domain target untuk konteks tahun aktif.
6. Klik **Simpan Mapping**.
7. Klik **Preview Rekonsiliasi Penuh** untuk membaca seluruh dataset tanpa menulis.
8. Periksa jumlah **Baru**, **Berubah**, **Tetap**, dan **Hilang**.
9. Klik **Sinkronkan Sekarang** jika hasilnya sesuai.

Preview contoh pada tabel bukan rekonsiliasi penuh. Rekonsiliasi penuh selalu membaca dataset Bridge lengkap.

## Source key

Source key adalah identitas baris dan harus deterministic antara preview, staging, reconciliation, dan sync.

Prioritas implementasi resolver harus konsisten di semua jalur. Preset dapat memakai identifier ARKAS seperti `ID_RAPBS`, `ID_KAS_UMUM`, `ID_KAS_NOTA`, `ID_REF_KODE`, atau identifier tabel lain yang benar-benar stabil.

Fallback hash seluruh payload hanya aman untuk kasus yang semantik sinkronisasinya sudah jelas. Jika sebuah raw profile tidak memiliki stable identifier, perubahan satu field dapat menghasilkan hash/key baru. Dalam mode Upsert/Incremental, versi lama berpotensi tetap ada. Karena itu sebelum P0-08 ditutup harus ada kebijakan eksplisit:

- wajib pilih stable source key; atau
- raw profile tanpa stable key hanya boleh Full refresh.

Jangan menganggap hash payload sebagai pengganti primary key bisnis tanpa menilai lifecycle record sumber.

## Preset domain

Preset utama yang tersedia:

| Tabel | Target | Keterangan |
| --- | --- | --- |
| `rapbs` | RKAS/RAPBS | Mengisi item RKAS |
| `rapbs_periode` | Periode RKAS | Mengisi koordinat bulan/triwulan/semester |
| `kas_umum` | BKU | Mengisi baris BKU |
| `ref_kode` | Referensi kegiatan | Menjaga hierarki kode kegiatan |
| `ref_periode` | Referensi periode | Menjaga label periode |
| `kas_umum_nota` | Snapshot raw | Menyimpan metadata nota dengan parent `id_kas_umum` |
| `kas_umum_nota_pajak` | Snapshot raw | Menyimpan rincian pajak dengan parent `id_kas_nota` |
| `ref_rekening` | Snapshot raw | Menyimpan master rekening untuk pengayaan |

Tabel tanpa adapter domain tetap dapat disimpan sebagai snapshot raw selama aturan source key-nya valid.

## Rekonsiliasi dan histori

Setiap profile menyimpan histori import pada `arkas_import_runs`. Staging menyimpan hash payload agar perubahan dapat dibedakan tanpa mengubah sumber ARKAS.

Untuk relasi child-parent, staging menyimpan:

- `source_key` sebagai identitas baris sumber;
- `parent_source_key` sebagai identitas induk;
- `relation_type` sebagai jenis tabel sumber;
- `payload` sebagai metadata asli ARKAS.

Contoh relasi nota:

```text
kas_umum.id_kas_umum
  -> kas_umum_nota.id_kas_umum
     -> kas_umum_nota_pajak.id_kas_nota
```

Semantics histori berikut masih perlu hardening sebelum importer dianggap release-ready:

- bedakan record read/new/changed/unchanged/removed;
- `created_at` existing row tidak di-reset pada setiap upsert bila field tersebut dimaksudkan sebagai first-created timestamp;
- Full refresh hanya membersihkan scope tenant/fiscal year/domain yang benar.

## Mode sinkronisasi

### Incremental

Incremental saat ini **belum melakukan delta query langsung pada Bridge**. Jalur sekarang masih membaca snapshot Bridge kemudian menyaring berdasarkan kolom terakhir berubah di aplikasi.

Ini diterima sebagai optimasi yang belum selesai, bukan blocker correctness utama, selama hasilnya deterministic dan test memverifikasi data tidak hilang/terduplikasi.

Bridge-side `updated-since`/cursor dapat ditambahkan setelah P0 correctness selesai.

### Upsert

Menambah record baru dan memperbarui record dengan key yang sama tanpa menyapu staging/domain lama di luar semantics adapter.

Mode ini membutuhkan stable source key agar perubahan payload tidak dianggap sebagai record baru.

### Full refresh

Mengganti snapshot/domain target dalam scope fiscal year/domain aktif. Full refresh tidak boleh menghapus data tenant lain, fiscal year lain, atau overlay manual SPJ di luar ownership importer.

## Lock/concurrency

Staging memakai lock agar import profile yang sama tidak berjalan bersamaan. Lock harus memasukkan identitas tenant/sekolah, bukan hanya ID lokal fiscal year atau nama tabel, karena ID tenant dapat sama pada database sekolah berbeda.

Target lock minimal secara konsep:

```text
school + profile/source table + fiscal year
```

## Mode background

Jika deployment memakai queue, aktifkan:

```env
ARKAS_SYNC_ASYNC=true
```

Jalankan worker:

```powershell
php artisan queue:work --queue=operations
```

Import mempunyai status `QUEUED`, `RUNNING`, `COMPLETED`, atau `FAILED` melalui `BackgroundOperation`, sedangkan detail jumlah baris dicatat pada histori importer.

Job queue harus:

1. mengambil sekolah dari database utama;
2. mengaktifkan `SchoolDatabaseManager` untuk sekolah tersebut;
3. baru membaca profile/fiscal year tenant dan menjalankan import.

Regression background import wajib menjadi bagian exit criteria P0-08.

## Perubahan struktur ARKAS

Saat mapping disimpan, daftar kolom sumber ikut disimpan. Jika ARKAS menambah atau menghilangkan kolom, importer menampilkan peringatan schema drift sebelum operator menjalankan sinkronisasi.

Schema drift warning tidak menggantikan mapping validation. Kolom kunci yang hilang atau role mandatory yang tidak lagi tersedia harus memblokir sync sampai mapping diperbaiki.

## Jalur sinkronisasi kanonik

Sinkronisasi dashboard, pemilihan tahun, dan job sinkronisasi utama memakai `ArkasCanonicalSyncService`. Coordinator tersebut menggunakan staging dan adapter transaksi/SPJ yang mempertahankan overlay manual serta reconciliation source.

`ArkasSynchronizationServiceV2` tetap menjadi adapter penting untuk transaksi/source pada alur canonical yang mempertahankan identitas transaksi dan manual overlay, sedangkan service legacy tidak boleh diperkenalkan kembali sebagai entry point runtime baru tanpa alasan arsitektur yang terdokumentasi.

Generic Importer adalah extension path untuk tabel/domain tambahan dan tidak boleh melemahkan safety contract canonical transaction sync.

## Regression minimum sebelum status READY FOR OPERATOR TEST

P0-08 baru dapat ditutup jika minimal skenario berikut PASS:

```text
1. GET importer dengan tenant sekolah aktif yang benar
2. save mapping pada tenant yang benar
3. preview tidak menulis domain
4. sync record non-kosong tidak memanggil method yang hilang
5. Upsert deterministic
6. Incremental deterministic
7. Full refresh hanya membersihkan scope aktif
8. cross-school isolation
9. cross-year/fund-source isolation sesuai target domain
10. source kosong aman
11. schema drift terdeteksi
12. background queue mengaktifkan tenant yang benar
13. raw profile memiliki stable-key policy yang eksplisit
```

Setelah seluruh regression ini PASS, status operator test diperbarui di `docs/CURRENT_PROGRESS.md`.
