# TODO — Mobile Visual Regression QA

Status: **LEGACY/ADDITIONAL TODO — RVR — NON-BLOCKER untuk target release desktop/laptop saat ini**

Terakhir diperbarui: **2026-09-12**

Dokumen ini adalah checklist tambahan untuk visual regression mobile. Checklist canonical penutupan GUI-AUDIT-13 berada di `GUI_RUNTIME_QA.md`; bila ada perbedaan viewport atau status, `GUI_RUNTIME_QA.md` dan `CURRENT_PROGRESS.md` yang berlaku.

Aplikasi **belum boleh disebut mobile-verified/mobile-complete** sebelum runtime QA dilakukan pada viewport canonical. Target operator release aktif saat ini adalah **desktop/laptop**, sehingga mobile/responsive penuh bukan blocker release utama, tetapi minimum usability mobile/tablet tetap RVR sampai mempunyai evidence.

Status release keseluruhan dan prioritas aktif tetap mengikuti `CURRENT_PROGRESS.md` dan `DEVELOPMENT_ROADMAP.md`.

---

## 1. Viewport target

Viewport canonical GUI-AUDIT-13:

```text
375 × 812
768 × 1024
1024 × 768
```

Viewport berikut boleh dipakai sebagai regression tambahan/legacy compatibility target, tetapi tidak menggantikan matrix canonical:

```text
390 × 844
```

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

Perubahan workspace/package yang sudah masuk wajib tetap tercakup dalam regression runtime:

### Checklist Paket (`/spj/paket/{id}/checklist`)

- daftar blocking bernomor terbaca tanpa scroll horizontal yang tidak disengaja;
- badge Paket/Transaksi tidak overflow;
- `details` Sudah lengkap/Opsional dapat dibuka via touch;
- stat cards tidak bertumpuk atau kehilangan informasi pada viewport sempit.

### Strip ringkasan `/spj` dan tabel persiapan

- stat `x-stat-item` tetap terbaca pada viewport canonical;
- tabel persiapan dapat di-scroll horizontal bila memang diperlukan tanpa memotong kolom Aksi secara permanen.

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

Jika halaman/theme belum benar-benar diuji pada matrix canonical, gunakan:

```text
RVR
```

Source-level responsive guard atau observation pada satu viewport tambahan tidak boleh dipromosikan menjadi mobile/tablet PASS. Sebaliknya, status mobile `RVR` tidak boleh digunakan untuk menurunkan functional PASS desktop/laptop yang mempunyai evidence terpisah.

---

## 8. Exit criteria

Checklist tambahan ini dapat dianggap tertutup hanya bila:

1. matrix canonical `375 × 812`, `768 × 1024`, dan `1024 × 768` sudah diuji secara reliabel sesuai `GUI_RUNTIME_QA.md`;
2. seluruh halaman minimum yang applicable diperiksa;
3. Dark, Yellow, dan Violet minimal diperiksa;
4. package layout terbaru diperiksa pada Rincian/Isian Manual/Penomoran;
5. tidak ada BLOCKER/HIGH mobile issue tersisa;
6. hasil akhir dicatat di regression report atau dokumentasi GUI.

Viewport `390 × 844` tetap berguna sebagai additional regression target, tetapi bukan syarat tunggal penutupan GUI-AUDIT-13.
