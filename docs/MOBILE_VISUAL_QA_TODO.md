# TODO — Mobile Visual Regression QA

Status: **TODO / RVR — NON-BLOCKER untuk target release desktop/laptop saat ini**

Terakhir diperbarui: **2026-09-11**

Dokumen ini mencatat bahwa visual regression mobile belum ditutup. Aplikasi **belum boleh disebut mobile-verified/mobile-complete** sebelum checklist berikut dijalankan pada viewport target.

Target operator release aktif saat ini adalah **desktop/laptop**. Karena itu, mobile/responsive penuh tidak menjadi blocker release saat ini, tetapi setiap klaim kompatibilitas mobile tetap harus menunggu evidence checklist ini.

Status release keseluruhan dan prioritas aktif tetap mengikuti `CURRENT_PROGRESS.md` dan `DEVELOPMENT_ROADMAP.md`.

---

## 1. Viewport target

Target utama:

```text
390 × 844
```

Tambahkan tablet portrait/landscape bila tersedia.

---

## 2. Halaman minimum

- Dashboard `/`
- Transactions `/transaksi`
- Transaction Detail `/transaksi/{id}`
- SPJ Workspace `/spj`
- SPJ Paket `/spj?tab=paket&package_id=...`
- SPJ Numbering `/spj/penomoran`
- Database Manager
- Reset Database
- Document Number Formats
- Document Templates

---

## 3. Theme minimum

- Dark Professional
- Yellow Bright
- Violet Premium

Bila memungkinkan tambahkan Slate Minimal dan Indigo Executive.

---

## 4. Checklist global mobile

- tidak ada horizontal overflow tak disengaja;
- Page Header stack benar;
- action wrap tanpa overlap;
- primary/secondary action readable;
- summary card tidak pecah;
- tabs usable;
- tabel horizontal-scroll atau pattern mobile yang sesuai;
- modal tidak keluar viewport;
- sticky action/Ke atas tidak menutup konten;
- input/select/textarea usable;
- pagination/per-page dapat dijangkau;
- theme selector konsisten;
- dark form controls tidak kembali putih;
- Livewire/Alpine navigation tidak menghilangkan theme;
- tidak ada HTTP 500 atau layout unusable.

---

## 5. Checklist khusus Detail Transaksi

- panel ARKAS/BKU vs SPJ tetap jelas;
- uraian item compact tidak memotong informasi penting;
- form kategori `KONSUMSI` usable;
- tombol auto-fill peserta dan `+ Peserta manual` tidak overlap;
- daftar participant dapat discroll bila perlu;
- validation tanggal pengadaan tetap terlihat;
- dark form control readable.

---

## 6. Checklist khusus SPJ Paket

Perubahan 2026-09-06 wajib masuk regression:

### Tab Rincian

- **Panel Rincian Transaksi** dan **Panel Dokumen & Template** terlihat sebagai dua card berbeda;
- header masing-masing mengikuti theme dan tetap readable;
- gap antar panel cukup jelas;
- compact document rows tidak terlalu padat untuk touch;
- tombol Preview/Unduh wrap dengan benar;
- group header/status badge tidak menyebabkan overflow.

### Tab Isian Manual

- background panel mengikuti theme;
- label/hint/control readable;
- panel pajak tidak memaksa dark/light surface yang salah;
- gap antar panel konsisten;
- select/input tidak terpotong.

### Tab Penomoran

- normal/hover/active quarter card tetap readable;
- card tidak melebar keluar viewport;
- badge status tidak overlap.

---

## 7. Status dan pelaporan

Jika halaman/theme belum benar-benar diuji pada viewport target, gunakan:

```text
RVR
```

Jangan mengubah ke PASS berdasarkan desktop/tablet observation saja. Sebaliknya, status mobile `RVR` tidak boleh digunakan untuk menurunkan functional PASS desktop/laptop yang sudah mempunyai evidence terpisah.

---

## 8. Exit criteria

TODO dapat ditutup jika:

1. viewport sekitar `390 × 844` diuji secara reliabel;
2. seluruh halaman minimum diperiksa;
3. Dark, Yellow, dan Violet minimal diperiksa;
4. package layout terbaru diperiksa pada Rincian/Isian Manual/Penomoran;
5. tidak ada BLOCKER/HIGH mobile issue tersisa;
6. hasil akhir dicatat di regression report atau dokumentasi GUI.
