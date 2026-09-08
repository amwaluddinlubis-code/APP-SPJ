# SPJ BOSP Web — Panduan Standardisasi GUI

Terakhir diverifikasi: **2026-09-08**

Dokumen ini adalah acuan visual dan UX untuk branch `gui-standardization`.

## 1. Prinsip utama

- UI harus terasa sebagai aplikasi kerja operator sekolah, bukan admin panel generik.
- Data ARKAS/BKU harus terlihat sebagai readonly source; data operator SPJ terlihat editable.
- Detail Transaksi dan Paket SPJ tidak boleh menyediakan input ganda untuk field yang sama.
- Komponen sejenis memakai primitive yang sama.
- Tailwind terutama untuk layout/spacing/responsive; warna non-semantik mengikuti token theme.
- Alpine/JS hanya menangani interaksi UI; business rule tetap backend.
- Jangan membuat satu halaman menjadi satu card raksasa.
- Perubahan visual tidak boleh mengubah lifecycle/validation/numbering secara implisit.

## 2. Layout global

Urutan standar:

```text
Header aplikasi
Breadcrumb sticky
Page Header + summary
Toolbar/filter
Form/section/workspace
Tabel/detail
Sticky action / utility
```

Pada halaman panjang tersedia kontrol sticky **Ke atas**.

Header kanan authenticated memakai menu **Profil User**, bukan badge teknis runtime. Dropdown dapat menampilkan identitas user, role, akses manajemen user untuk admin, dan logout.

## 3. Sistem tema

Gunakan token utama:

```text
--theme-accent
--theme-accent-strong
--theme-accent-soft
--theme-content-accent
--theme-action-bg
--theme-action-fg
--theme-action-hover-bg
--theme-action-hover-fg

--ui-surface-base
--ui-surface-soft
--ui-surface-muted
--ui-line
--ui-line-strong
--ui-fg
--ui-fg-strong
--ui-fg-muted
```

Semantic success/warning/danger tetap boleh memakai semantic color. Kode baru tidak boleh bergantung pada hard-coded palette non-semantik hanya karena compatibility layer tersedia.

## 4. Primitive UI resmi

Utamakan:

```text
x-ui.page-shell
x-ui.alert
x-ui.empty-state
x-ui.badge
x-ui.detail-list / detail-item
x-ui.toolbar
x-ui.modal
x-ui.action-menu
x-ui.loading
x-ui.sticky-actions
x-ui.danger-zone
x-ui.table
x-ui.field
x-ui.input
x-ui.select
x-ui.textarea
x-ui.button
x-ui.icon
x-ui.form-section
x-ui.status-badge
```

Icon action baru sebaiknya memakai `<x-ui.icon>` ketika markup native dirapikan. Icon dekoratif pada button berlabel menggunakan `aria-hidden`; icon standalone diberi label aksesibel.

## 5. Form dan input

- label, required, hint/error, readonly/disabled, focus ring harus jelas;
- form panjang dibagi menjadi section bermakna;
- input numeric mengikuti tipe data sebenarnya;
- uang/tarif/harga menggunakan accounting Indonesia tanpa `Rp` dan tanpa desimal (`1.000`);
- hari/porsi/bulan/kali menggunakan integer tanpa koma/desimal;
- width field mengikuti pola datanya, bukan semua dibuat sama lebar.

## 6. Tabel dan daftar

Kontrak umum:

- row compact untuk data repetitif;
- angka rata kanan;
- tabel lebar memakai horizontal scroll;
- hover mengikuti token theme;
- satu tabel hanya boleh mempunyai satu pagination;
- jika sebuah tabel sudah memiliki pager lokal Alpine, beri `data-pagination="none"` agar `table-ui-standardization.js` tidak menyuntik pager kedua.

Untuk tabel kategori SPJ non-BARANG:

- pagination/filter/per-page berada di bawah tabel;
- satu radio **Penerima Utama**;
- row height/input dibuat compact;
- currency accounting, count integer.

## 7. Daftar Transaksi

Setiap transaksi menampilkan **satu tombol Aksi** pada layout aktif. Tombol membuka modal yang berisi pilihan navigasi seperti Detail Transaksi dan Paket SPJ.

Jangan menghidupkan kembali editor SPJ di `TransactionsTable` Livewire. Tabel transaksi bukan workspace mutation kategori/payment/vendor SPJ.

Markup mobile dan desktop boleh sama-sama memiliki trigger pada source template, tetapi hanya layout yang relevan yang tampil pada viewport masing-masing.

## 8. Detail Transaksi

Detail Transaksi adalah workspace source/context, bukan builder SPJ.

Urutan canonical:

```text
Header Transaksi
→ Informasi Referensi ARKAS/BKU + Total Pajak
→ Rincian Barang/Jasa
→ Status Paket SPJ
```

`item_description` adalah satu-satunya field item yang editable. `description`, quantity, unit, unit price, amount readonly.

Rincian pajak PPN/PPh/SSPD tidak perlu menjadi panel besar di Detail Transaksi. Detail lengkap tersedia pada Paket SPJ → **Rincian Pajak**.

## 9. Filter workflow

Kontrak canonical:

```text
Perlu Perhatian   -> SOURCE_MISSING atau requires_reconciliation
Belum Dikerjakan  -> transaksi normal belum memiliki Paket SPJ
Perlu Dilengkapi  -> paket DRAFT
Siap Dinomori     -> paket READY
Sudah Bernomor    -> paket NUMBERED atau FINAL
```

Keberadaan `transaction_items` bukan indikator pekerjaan operator.

## 10. Paket SPJ — toolbar dan summary

URL utama: `/spj?tab=paket&package_id=...`.

Toolbar canonical:

```text
[ Semua Paket ] [ Paket Sebelumnya ] [ Paket Setelahnya ]          [ Lihat Transaksi ]
```

- semua action memiliki icon/hover/title yang jelas;
- previous/next hanya bernavigasi dalam sekolah+tahun+sumber dana aktif yang sama;
- bila previous/next tidak ada, tombol tetap terlihat dalam disabled/muted state dan klik memberikan warning operator.

Summary canonical:

```text
Periode | Penerima | Bruto | Pajak | Nilai Dibayarkan
```

Nilai uang menggunakan accounting tanpa `Rp`/desimal.

## 11. Paket SPJ — sub-tab

Urutan canonical:

```text
1. Rincian
2. Isian Manual
3. Rincian Pajak
4. Penomoran
```

### Rincian

Memuat Rincian Transaksi readonly dan Dokumen & Template sebagai panel yang jelas dan terpisah.

### Isian Manual

Hanya berisi data yang memang boleh diubah operator.

### Rincian Pajak

Readonly reference dari transaksi/BKU. Tidak boleh memiliki input PPN/PPh/SSPD.

### Penomoran

Mengelola lifecycle/numbering sesuai backend. Nomor otomatis tidak diedit melalui Isian Manual.

## 12. Isian Manual — Kategori SPJ

Baris atas desktop:

```text
Kategori SPJ 1/4 | Konteks kategori 3/4
```

BARANG:

```text
Kategori | ○ SiPLah  ○ Non SiPLah
```

Kedua radio harus berada pada radio group yang sama dan hanya satu dapat aktif. Bila source memaksa SiPLah, Non SiPLah dapat disabled.

PEMELIHARAAN:

```text
Kategori | Combo transaksi pasangan
```

Selector harus benar-benar tampil di baris kategori, bukan sebagai panel besar di bawah yang kemudian secara visual terasa terpisah.

Keterangan/hint yang tidak diperlukan operator dihilangkan.

## 13. Informasi nomor otomatis

Setelah Kategori SPJ terdapat strip informasi horizontal untuk nomor otomatis yang relevan:

```text
SPJ | PESANAN/BAP/BAST atau SPK/RAB | ...
```

Status tanpa nomor menggunakan teks seperti `Belum diterbitkan`.

Nomor otomatis bukan `<input readonly>` pada form manual.

Nomor marketplace/manual seperti nomor SiPLah/invoice tetap dapat menjadi field bila memang operator perlu mengisinya.

## 14. Data Umum Dokumen

Desktop menggunakan dua kolom besar:

```text
┌──────────────────────────────┬──────────────────────────────┐
│ Uraian pembayaran            │ Metode | Referensi          │
│ textarea 5 baris             │ Utama  | Penyedia           │
│                              │ Pemilik| NPWP               │
└──────────────────────────────┴──────────────────────────────┘
```

Semua input umum selain `payment_description` harus berada di kolom kanan, tidak turun menjadi row penuh di bawah textarea pada desktop.

Istilah canonical: **Penerima Utama**.

## 15. Tabel kategori non-BARANG

KONSUMSI, PEMELIHARAAN, HONOR_PEGAWAI, SPPD, dan JASA_LAINNYA memakai pola compact.

- filter/per-page/pager hanya satu dan berada di bawah tabel;
- Penerima Utama menggunakan radio;
- Hari/Porsi/Bulan-Kali = integer;
- Tarif/Harga/Nilai = accounting `1.000`;
- field sekunder tidak perlu membuat row utama terlalu lebar; gunakan detail row/editor bila diperlukan.

## 16. PEMELIHARAAN linkage

Selector pasangan transaksi ditampilkan di Paket SPJ, tetapi state relasi tetap dimiliki transaction/context dan disimpan lewat endpoint maintenance-link khusus.

UI tidak boleh mengubah relasi tersebut menjadi field package form biasa.

## 17. Dokumen & Template

Gunakan compact list `status / nama / tipe-format / actions`. Metadata padat, status semantic, action mudah ditemukan, dan theme-aware.

## 18. Dashboard dan Database Aktif

Dashboard utama harus memprioritaskan pekerjaan operator, terutama masalah source/reconciliation, DRAFT yang belum selesai, READY, lalu pekerjaan baru.

Halaman Database Aktif tetap menjadi Pusat Kontrol Database Sekolah dengan pemisahan status, explorer read-only, diagnostik, maintenance, backup, dan zona berbahaya untuk reset.

## 19. Compatibility layer

Compatibility CSS/JS boleh ada sementara, tetapi markup baru harus menuju primitive/token canonical. Jangan menghidupkan kembali stale selector atau standalone Vite entry lama.

Vite canonical:

```text
resources/css/app.css
resources/js/app.js
```

## 20. Responsive dan mobile

Desktop adalah workspace utama, tetapi mobile/tablet harus usable. QA mobile resmi tetap mengikuti `MOBILE_VISUAL_QA_TODO.md`.

## 21. Checklist UI sebelum selesai

- ownership source vs editable jelas;
- tidak ada input ganda Detail Transaksi/Paket;
- icon/hover/focus/disabled state jelas;
- tidak ada pagination ganda;
- radio group benar-benar exclusive;
- accounting dan integer sesuai pola field;
- tabel compact namun masih terbaca;
- Data Umum tidak pecah ke bawah textarea pada desktop;
- dark/theme tidak rusak;
- mobile minimum usable;
- perubahan UI tidak melemahkan backend validation/lifecycle.
