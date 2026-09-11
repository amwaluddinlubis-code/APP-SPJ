# SPJ BOSP Web — Panduan Standardisasi GUI

Terakhir diverifikasi: **2026-09-12**

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

Pada desktop saat sidebar diciutkan, hanya icon navigasi yang ditampilkan pada rail sempit. Label menu disembunyikan agar tidak terpotong; link dan tombol utama tetap menyediakan `title`/`aria-label` untuk identifikasi dan aksesibilitas. Kontrol expand/collapse hanya berada di header aplikasi; sidebar tidak memiliki toggle kedua. Pada mobile, kontrol header yang sama membuka dan menutup drawer navigasi.

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

Radio bersifat UI-only: hanya show/hide section pengadaan, tidak menulis `payment_method` (field canonical backend). SiPLah hanya berlaku untuk BARANG.

Mode SiPLah menampilkan 4 field sejajar: Nomor Invoice | Tanggal Invoice | No Pesanan (autofill segmen terakhir invoice metadata) | Status Invoice (select Lunas/Proforma).

Mode Non SiPLah menampilkan Tanggal Pesanan | Tanggal BAP | Tanggal BAST | Status Invoice.

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

Desktop adalah workspace utama, tetapi mobile/tablet harus usable. QA runtime resmi mengikuti `GUI_RUNTIME_QA.md`. Source-level responsive guard hanya membuktikan kontrak markup/breakpoint tertentu dan **tidak** menggantikan browser visual QA.

## 20.1 Pilot density workspace SPJ

Workspace `/spj` menggunakan pilot density scoped pada `.spj-semantic-workspace`:

```text
control height desktop : 40px
control/touch <=1023px : 44px
section gap            : 16px
content padding        : 20px
panel radius           : 12px
table row vertical     : 10px
page title             : 24px
field label            : 13px
helper minimum         : 12px
package tab text       : 14px
```

Nilai ini bertujuan membuat workspace operator nyaman pada zoom browser 100% tanpa mengecilkan teks tabel di bawah baseline aksesibilitas. Primary table text tetap 14px, sedangkan header/secondary table text tetap minimal 13px sesuai `minimum-font-size.css`.

Pilot juga menormalkan variasi profile agar theme tidak membatalkan density yang sedang diuji: profile header `bold` tidak boleh memaksa `min-height` besar di `/spj`, padding tambahan summary dari personality/profile dinetralkan di scope pilot, dan summary workspace memakai marker `.spj-work-summary` agar padding compact benar-benar diterapkan. Pada viewport di bawah desktop, control dan package tab mempertahankan minimum 44px untuk touch target.

Pilot hanya mengubah presentation/density; lifecycle, validation, numbering, source ownership, authorization, route, persistence, dan workflow tetap sama. Kenyamanan visual dan tidak adanya clipping/overlap harus diverifikasi melalui checklist runtime di `GUI_RUNTIME_QA.md` sebelum dipromosikan menjadi standar global atau diterapkan ke Daftar Transaksi/Detail Transaksi.

## 21. Pola form dinamis show/hide

- Kontrol show/hide (seperti radio SiPLah) tidak boleh menulis field domain sebagai efek samping; visibilitas dan nilai backend adalah dua keputusan terpisah.
- Bila dua mode memakai field dengan `name` yang sama, hanya satu salinan boleh aktif/enabled dalam satu waktu (fieldset mode tersembunyi di-disabled agar tidak ikut tersubmit).
- Semua sumber kebenaran visibilitas (JS lama + JS baru) harus melalui satu fungsi updater; dua sistem yang mengatur elemen yang sama akan saling menimpa.
- Status awal kontrol UI diturunkan dari backend; setelah interaksi operator, kontrol hanya mengatur tampilan sampai ada perubahan backend eksplisit.

## 22. Checklist UI sebelum selesai

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

## 23. Halaman checklist paket

- Stat memakai 4 `x-stat-item` (jalur, dokumen wajib, penghalang, status).
- Item penghalang bernomor urut, masing-masing ber-badge lokasi (`Paket`/`Transaksi`) dan tombol Perbaiki ke URL yang tepat.
- Item lolos dan opsional/tidak-berlaku disembunyikan dalam `details` collapsed agar tidak mendorong konten blocking ke bawah layar.

## 24. Canonical icon registry

Satu-satunya registry/rendering icon canonical adalah:

```text
resources/views/components/ui/icon.blade.php
<x-ui.icon ...>
```

Aturan:

- markup baru atau markup yang sedang disentuh harus memakai `<x-ui.icon>`;
- `resources/views/components/ui-icon.blade.php` / `<x-ui-icon>` hanya compatibility adapter untuk consumer lama dan tidak boleh memiliki registry SVG sendiri;
- nama icon legacy yang masih dibutuhkan dipetakan sebagai alias di registry canonical;
- navigation/tab tidak memakai emoji sebagai icon utama;
- shared `x-tabs` menerima metadata `icon` dan merender icon melalui `<x-ui.icon>`;
- compatibility wrapper boleh dihapus hanya setelah semua consumer lama sudah dimigrasikan dan regression guard diperbarui.

## 25. Source readiness vs runtime QA

Status source GUI dan status visual/runtime adalah dua evidence berbeda.

```text
source regression PASS != browser visual PASS
```

GUI-AUDIT-12 (desktop/laptop) dan GUI-AUDIT-13 (mobile/tablet) hanya boleh ditutup sebagai runtime PASS setelah checklist `docs/GUI_RUNTIME_QA.md` benar-benar dijalankan pada browser dan viewport yang ditentukan.

CI boleh membuktikan:

- Blade compile;
- frontend build;
- keberadaan responsive fallback;
- penggunaan table/icon/theme primitive;
- tidak kembalinya pola source tertentu.

CI tidak membuktikan:

- tidak adanya clipping/overlap nyata;
- kualitas alignment pada viewport tertentu;
- usability touch/keyboard;
- modal/dropdown terlihat utuh;
- visual contrast yang memadai pada browser lokal.

Karena itu agent tidak boleh mengubah status RVR menjadi PASS hanya berdasarkan source inspection atau deterministic CI.
