# Modul Importer dan Sinkronisasi ARKAS

Terakhir diperbarui: **2026-09-10**

Modul ini adalah jalur kanonik untuk membaca database ARKAS melalui Bridge, menyimpan snapshot staging, memvalidasi mapping, dan mengisi domain aplikasi melalui adapter. Operator tidak perlu mengubah atau rebuild Bridge ketika menambahkan tabel yang didukung konfigurasi importer.

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

## Cara menggunakan

Buka:

```text
/pengaturan/arkas/importer
```

1. Pastikan sumber database ARKAS dan tahun anggaran aktif sudah dipilih.
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

Tabel tanpa adapter domain tetap dapat disimpan sebagai snapshot raw selama mapping kunci valid.

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

## Mode background

Jika deployment memakai queue, aktifkan:

```env
ARKAS_SYNC_ASYNC=true
```

Jalankan worker:

```powershell
php artisan queue:work --queue=operations
```

Import akan mempunyai status `QUEUED`, `RUNNING`, `COMPLETED`, atau `FAILED` melalui `BackgroundOperation`, sedangkan detail jumlah baris tetap dicatat pada histori importer.

## Perubahan struktur ARKAS

Saat mapping disimpan, daftar kolom sumber ikut disimpan. Jika ARKAS menambah atau menghilangkan kolom, importer menampilkan peringatan schema drift sebelum operator menjalankan sinkronisasi.

## Jalur sinkronisasi kanonik

Sinkronisasi dashboard, pemilihan tahun, dan job background memakai `ArkasCanonicalSyncService`. Coordinator tersebut menggunakan staging dan adapter transaksi/SPJ yang mempertahankan overlay manual serta reconciliation source. Service sinkronisasi legacy tidak menjadi entry point runtime.
