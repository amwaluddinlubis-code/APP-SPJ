# TODO — Mobile Visual Regression QA

Status: TODO

Terakhir diperbarui: 2026-09-05

Dokumen ini mencatat bahwa visual regression khusus mobile belum diselesaikan pada Phase 3 GUI Standardization karena viewport sekitar 390 px tidak dapat dibentuk dan diverifikasi secara reliabel pada browser QA yang tersedia.

## Ruang lingkup

Target utama:

```text
390 × 844
```

Halaman minimum yang harus diuji:

- Dashboard `/`
- Transactions `/transaksi`
- Transaction Detail `/transaksi/{id}`
- SPJ Workspace `/spj`
- Database Manager
- Reset Database
- Document Number Formats
- Document Templates

Theme minimum:

- Dark Professional
- Yellow Bright
- Violet Premium

Bila waktu memungkinkan, lengkapi juga Slate Minimal dan Indigo Executive.

## Checklist mobile

- tidak ada horizontal overflow yang tidak disengaja;
- Page Header stack dengan benar;
- action header wrap tanpa saling menimpa;
- primary/secondary action tetap readable;
- summary cards tidak pecah atau terpotong;
- tabs tetap dapat digunakan;
- tabel memakai horizontal scroll atau mobile-card pattern yang sesuai;
- modal tidak keluar viewport;
- sticky actions tidak menutup konten;
- form/input/select tetap dapat digunakan;
- pagination/per-page tetap dapat dijangkau;
- theme selector tetap konsisten;
- Livewire navigation tidak menghilangkan theme;
- tidak ada HTTP 500 atau layout unusable.

## Status terhadap Phase 3

Mobile visual regression ditetapkan sebagai pekerjaan tertunda (`TODO` / `RVR`) dan **tidak menjadi blocker untuk penyelesaian regression desktop/tablet Phase 3**.

Namun, aplikasi **belum boleh dinyatakan mobile-verified atau mobile-complete** sebelum checklist pada dokumen ini dijalankan dan hasilnya dicatat.

Jika suatu halaman belum benar-benar diuji pada target mobile, gunakan status:

```text
RVR
```

Jangan mengubahnya menjadi `PASS` berdasarkan desktop/tablet observation saja.

## Exit criteria

TODO ini dapat ditutup jika:

1. viewport sekitar `390 × 844` berhasil diuji secara reliabel;
2. halaman minimum di atas telah diperiksa;
3. Dark, Yellow, dan Violet minimal telah diperiksa;
4. tidak ada BLOCKER/HIGH issue mobile yang belum diselesaikan;
5. hasil akhir mobile dicatat di regression report atau dokumentasi GUI.
