# SPJ BOSP Web — Panduan Standardisasi GUI

Terakhir diverifikasi: **2026-09-07**

Dokumen ini adalah acuan visual dan UX untuk branch `gui-standardization`. Tujuannya menjaga aplikasi operasional sekolah tetap konsisten, mudah dipahami, dan mengikuti tema yang dipilih user.

---

## 1. Prinsip utama

- UI harus terasa sebagai aplikasi kerja operator sekolah, bukan admin panel generik.
- Data ARKAS/BKU harus terlihat sebagai readonly source; data operator SPJ terlihat editable.
- Komponen sejenis memakai primitive yang sama.
- Accent non-semantik tidak boleh hard-coded.
- Tailwind terutama untuk layout/spacing/responsive; theme warna berasal dari token.
- Alpine hanya untuk interaksi UI ringan; Livewire untuk state server-backed; Laravel untuk auth/validation/business rule.
- Jangan membuat satu halaman menjadi satu card raksasa.
- Perubahan visual tidak boleh mengubah business rule secara implisit.

---

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

---

## 3. Sistem tema

Token utama:

```text
--theme-accent
--theme-accent-strong
--theme-accent-soft
--theme-content-accent
--theme-action-bg
--theme-action-fg
--theme-action-hover-bg
--theme-action-hover-fg
```

Surface/text/border:

```text
--ui-surface-base
--ui-surface-soft
--ui-surface-muted
--ui-line
--ui-line-strong
--ui-fg
--ui-fg-strong
--ui-fg-muted
```

Profile/density:

```text
--profile-card-radius
--profile-control-radius
--profile-card-shadow
--profile-content-padding
--profile-section-gap
--profile-control-height
```

Aturan: dark appearance memakai token yang sama; semantic success/warning/danger/status tetap bermakna; compatibility layer boleh mengoreksi markup lama, tetapi markup baru memakai token canonical.

### 3.1 Audit warna lintas-view

Seluruh authenticated application workspace di bawah `<main>` kini memiliki global compatibility layer:

```text
resources/css/view-theme-hardening.css
```

Layer ini dijalankan setelah seluruh compatibility CSS fitur dan mengubah ownership warna legacy non-semantik dari Tailwind palette statis ke token theme aktif. Sesudahnya hanya ada satu exception terkontrol:

```text
resources/css/semantic-status-colors.css
```

Exception tersebut hanya menjaga perbedaan visual status workflow canonical pada `<x-ui.status-badge>`; ia bukan layer dekorasi umum.

Cakupan hardening utama:

```text
background/surface neutral
foreground/font neutral
accent indigo/violet/blue/sky/cyan
border/divider neutral + accent
gradient hero/chrome
hover/focus/ring
variant background dengan opacity
```

Dengan demikian class lama seperti `bg-white`, `bg-slate-*`, `text-slate-*`, `bg-indigo-*`, `text-indigo-*`, atau hover/focus sejenis dapat tetap ada sementara sebagai **compatibility hook**, tetapi warna aktual non-semantik pada authenticated view tidak lagi dimiliki oleh palette tersebut.

Pengecualian yang sengaja dipertahankan:

- emerald/green untuk success;
- amber/yellow/orange untuk warning/attention;
- rose/red untuk danger/error;
- sky/indigo/violet yang memang merepresentasikan status workflow canonical melalui `ui-status-badge`;
- `text-white` serta overlay putih transparan pada hero gelap bila dibutuhkan untuk kontras;
- PDF/print/template-preview yang membutuhkan warna output tetap;
- public/auth/setup/pre-login yang tidak berada pada authenticated `<main>` dan dapat memiliki branding sendiri.

Kode baru tetap **tidak boleh** menambah hard-coded palette hanya karena compatibility layer tersedia.

---

## 4. Primitive UI resmi

Utamakan `x-ui.page-shell`, `x-ui.alert`, `x-ui.empty-state`, `x-ui.badge`, `x-ui.detail-list`, `x-ui.detail-item`, `x-ui.toolbar`, `x-ui.modal`, `x-ui.action-menu`, `x-ui.loading`, `x-ui.sticky-actions`, `x-ui.danger-zone`, `x-ui.table`, `x-ui.field`, `x-ui.input`, `x-ui.select`, `x-ui.textarea`, `x-ui.button`, `x-ui.form-section`, dan `x-ui.status-badge`.

Legacy components yang sudah diarahkan ke sistem baru termasuk `page-filter`, `page-table-per-page`, `tabs`, `stat-item`, `error-alert`, dan `loading-spinner`.

---

## 5. Form dan input

Gunakan `ui-input`, `ui-select`, `ui-textarea`, atau primitive Blade terkait. Label, required, hint/error, readonly/disabled, dan focus ring harus jelas. Form panjang dibagi menjadi panel bermakna dan top-level panel memakai spacing konsisten.

`dark-form-controls.css` adalah safety layer agar control pada dark appearance tidak kembali putih.

---

## 6. Tabel dan daftar

Header konsisten, angka rata kanan bila relevan, hover mengikuti token theme, tabel lebar memakai horizontal scroll, pagination/per-page mengikuti pola global, dan daftar repetitif sebaiknya compact.

---

## 7. Detail Transaksi dan filter workflow

Detail Transaksi adalah workspace operator:

```text
Data ARKAS/BKU readonly
→ Data Umum SPJ
→ Detail Kategori
→ Kelengkapan
→ Buat/Perbarui Paket
```

Rincian item dibuat compact. Untuk `KONSUMSI`, auto-fill `fillTeachers()` adalah **Dapodik-only**.

Filter status pada halaman `/transaksi` dan `/spj?tab=persiapan` memakai konsep workflow operator yang sama dan tidak lagi memakai status mentah transaksi sumber. Kontrak canonical:

```text
Perlu Perhatian   -> SOURCE_MISSING atau requires_reconciliation
Belum Dikerjakan  -> transaksi normal belum memiliki Paket SPJ
Perlu Dilengkapi  -> paket DRAFT
Siap Dinomori     -> paket READY
Sudah Bernomor    -> paket NUMBERED atau FINAL
```

Kelompok normal harus eksklusif terhadap **Perlu Perhatian** agar satu transaksi tidak dihitung sekaligus sebagai masalah sumber dan pekerjaan normal. Keberadaan `transaction_items` bukan indikator pekerjaan operator karena rincian berasal dari sinkronisasi `kas_umum`; karena itu state `needs_details` bukan lagi konsep workflow canonical. URL legacy `state=needs_details` hanya dipertahankan sementara sebagai alias kompatibilitas ke **Perlu Perhatian** sampai markup Persiapan lama dirapikan.

Filter periode memakai prioritas **Bulan → Triwulan → Semester** bila lebih dari satu parameter ada; filter yang lebih spesifik tidak boleh bertabrakan dengan filter periode yang lebih luas.

---

## 8. SPJ Package — struktur canonical saat ini

URL `/spj?tab=paket&package_id=...` memiliki sub-tab `Rincian`, `Isian Manual`, dan `Penomoran`.

### 8.1 Rincian

Tab Rincian harus memperlihatkan dua sibling panel jelas: **Rincian Transaksi** dan **Dokumen & Template**. Keduanya memiliki border/radius/shadow sendiri, header berbeda tetapi tetap theme-aware, dan gap konsisten.

`Dokumen & Template` berada di sub-tab Rincian agar Isian Manual/Penomoran tidak ikut memanjang. Placement saat ini dilakukan oleh `resources/js/spj-package-document-placement.js` dan `resources/css/spj-package-document-placement.css`.

### 8.2 Dokumen & Template

Gunakan compact list `status / nama / tipe-format / actions`; group header subordinate, metadata padat, action mudah ditemukan, zebra/hover theme-aware, status tetap semantic.

### 8.3 Isian Manual

Background panel, header, text hierarchy, control, readonly/disabled, panel kategori, panel pajak, focus, dan spacing harus mengikuti theme aktif. Compatibility layer utama: `resources/css/spj-package-theme-fix.css`.

### 8.4 Penomoran

Normal/hover/active state harus memadukan current surface dan current theme accent; hindari light-only hover.

---

## 9. Dashboard canonical dan produktivitas

Dashboard utama route `/` adalah dashboard produktivitas operator:

```text
ProductivityDashboardController
resources/views/dashboard-productivity.blade.php
```

Dashboard utama harus menjawab pertanyaan **“apa yang harus saya kerjakan berikutnya?”**. Hierarki utamanya:

```text
Pekerjaan Anda
→ Prioritas berikutnya
→ Lanjutkan pekerjaan yang sudah dimulai
→ Transaksi berikutnya
→ Alur kerja operator
→ Antrean kerja terdekat
→ Status penomoran/sistem
```

Istilah canonical pada dashboard produktivitas:

```text
Belum Dikerjakan
Sedang Dikerjakan
Siap Dinomori
Sudah Bernomor
Final
```

Jangan menggunakan kembali label **Belum disentuh**. `Belum Dikerjakan` saat ini adalah definisi persisted: transaksi aktif belum memiliki Paket SPJ dan bukan rekonsiliasi/source missing. Ini bukan analytics literal tentang apakah halaman pernah dibuka.

Pekerjaan `DRAFT` harus diprioritaskan sebelum membuka pekerjaan baru agar operator menyelesaikan pekerjaan setengah jadi. Rekonsiliasi/source missing mempunyai prioritas lebih tinggi daripada antrean normal. `Belum Bernomor` merangkum antrean normal yang masih berada pada tahap Belum Dikerjakan + DRAFT + READY.

Dashboard operasional sebelumnya **harus tetap dipertahankan** sebagai pembanding/legacy pada:

```text
/dashboard-operasional
OperationalDashboardController
resources/views/dashboard-operational-v3.blade.php
```

Jangan menimpa atau menghapus view tersebut ketika iterasi dashboard produktivitas dilakukan.

Route `/dashboard-v2` adalah dashboard pembanding/QA lain dan memakai:

```text
resources/views/dashboard.blade.php
```

`resources/views/dashboard.blade.php` tetap protected working file sesuai `.ai/rules/index.md`; jangan overwrite/commit tanpa instruksi eksplisit user.

View legacy `dashboard-operational.blade.php` dan `dashboard-operational-v2.blade.php` tetap sudah dihapus dan tidak boleh dihidupkan kembali hanya untuk eksperimen. Eksperimen baru harus memiliki nama yang jelas dan tidak menimpa source pembanding yang sudah dipertahankan.

---

## 10. Pengaturan → Database Aktif

Halaman `/pengaturan/database-aktif` adalah **Pusat Kontrol Database Sekolah**, bukan halaman debug mentah. Struktur canonical:

```text
Page Header + status database aktif
→ Ringkasan
→ Database Sekolah
→ Explorer Tabel
→ Diagnostik
→ Maintenance
```

Prinsip UX:

- status sekolah/NPSN/koneksi aktif harus terlihat tanpa membuka tab;
- health, integrity, writable, file existence, DB/WAL/SHM, migrasi terakhir, dan path harus mudah dibaca;
- daftar database harus searchable dan database aktif harus paling mudah dikenali;
- Explorer Tabel bersifat read-only, dengan pencarian/sort/pagination serta pemisahan Schema vs Data;
- tindakan rutin seperti integrity/checkpoint/migrate boleh tersedia sebagai quick action;
- tindakan maintenance harus dipisahkan menurut tingkat risiko;
- reset total selalu berada pada **Zona berbahaya** dan tetap menuju konfirmasi terpisah;
- backup harus mudah dicapai sebelum tindakan berisiko;
- teknis seperti path/config boleh tampil, tetapi tidak boleh menjadi informasi utama di atas status operasional;
- semua surface, text, hover, focus, dan active state mengikuti theme.

CSS halaman ini dimiliki oleh `resources/css/settings-database-standardization.css` dan harus scoped ke `#database-control-center`.

---

## 11. Header panel theme-aware

Untuk panel setingkat, header boleh memiliki accent strength berbeda agar hierarchy jelas, tetapi tetap memakai token theme. Jangan mengunci header ke `bg-indigo-*`, `bg-slate-*`, atau putih jika panel harus mengikuti theme.

---

## 12. Status dan badge

Gunakan `<x-ui.status-badge>` untuk status teknis dan `<x-ui.badge>` untuk kategori/role/metode pembayaran. Status harus memakai bahasa manusiawi dan semantic color yang konsisten. `semantic-status-colors.css` hanya berlaku pada `ui-status-badge` agar warna workflow tetap berbeda tanpa mengunci surface ke light mode.

---

## 13. Compatibility layer

CSS scoped boleh mengoreksi markup legacy yang masih memakai warna Tailwind statis. Compatibility layer feature harus terlokalisasi; kode baru tidak boleh memperbanyak markup legacy. Layer SPJ aktif mencakup `spj-workspace-standardization.css`, `spj-package-theme-fix.css`, dan `spj-package-document-placement.css`.

Untuk Database Aktif, `settings-database-standardization.css` menjadi style layer canonical untuk root `#database-control-center`.

`view-theme-hardening.css` berbeda: layer ini sengaja global tetapi hanya berlaku pada authenticated `<main>`. Tugasnya menangkap sisa palette non-semantik lintas halaman setelah seluruh feature layer selesai, bukan menjadi tempat menambah aturan khusus satu halaman. `semantic-status-colors.css` adalah exception sempit setelahnya khusus status canonical.

---

## 14. Responsive dan mobile

Desktop tetap workspace utama, tetapi mobile/tablet harus usable. Perubahan dashboard produktivitas, package, Database Aktif, dan hardening palette lintas-view belum menutup QA mobile. Status resmi tetap mengikuti `MOBILE_VISUAL_QA_TODO.md`.

---

## 15. Checklist UI sebelum selesai

- breadcrumb tidak double;
- page header mengikuti pola global;
- hierarchy panel jelas;
- readonly vs editable jelas;
- warna mengikuti theme;
- dark appearance terbaca;
- hover/focus/active tidak kembali ke warna light-only;
- spacing antar panel konsisten;
- mobile tidak overflow tanpa alasan;
- status memakai bahasa manusiawi;
- tindakan berisiko dipisahkan secara visual;
- perubahan UI tidak melemahkan validation/authorization;
- `npm run build` dijalankan setelah CSS/JS/Blade berubah.

---

## 16. Dokumen pendamping

Aturan CSS praktis: `docs/CSS_USAGE_GUIDE.md`. Kondisi implementasi terbaru: `docs/CURRENT_PROGRESS.md`.
