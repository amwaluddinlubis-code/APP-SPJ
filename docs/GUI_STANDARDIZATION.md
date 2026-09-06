# SPJ BOSP Web — Panduan Standardisasi GUI

Terakhir diverifikasi: **2026-09-06**

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

Aturan:

- dark appearance memakai token yang sama, bukan komponen terpisah;
- semantic success/warning/danger tetap bermakna, tetapi surface harus tetap nyaman pada theme aktif;
- compatibility layer boleh mengoreksi markup lama, namun kode baru memakai token canonical.

---

## 4. Primitive UI resmi

Utamakan:

```text
<x-ui.page-shell>
<x-ui.alert>
<x-ui.empty-state>
<x-ui.badge>
<x-ui.detail-list>
<x-ui.detail-item>
<x-ui.toolbar>
<x-ui.modal>
<x-ui.action-menu>
<x-ui.loading>
<x-ui.sticky-actions>
<x-ui.danger-zone>
<x-ui.table>
<x-ui.field>
<x-ui.input>
<x-ui.select>
<x-ui.textarea>
<x-ui.button>
<x-ui.form-section>
<x-ui.status-badge>
```

Legacy components yang sudah diarahkan ke sistem baru termasuk `page-filter`, `page-table-per-page`, `tabs`, `stat-item`, `error-alert`, dan `loading-spinner`.

---

## 5. Form dan input

Gunakan `ui-input`, `ui-select`, `ui-textarea`, atau primitive Blade terkait.

Standar:

- label terlihat;
- required jelas;
- hint/error dekat field;
- readonly/disabled mudah dibedakan;
- focus ring mengikuti theme;
- form panjang dibagi menjadi panel bermakna;
- top-level panel memakai spacing konsisten.

`dark-form-controls.css` adalah safety layer agar control pada dark appearance tidak kembali putih.

---

## 6. Tabel dan daftar

Standar:

- header konsisten;
- angka/nominal rata kanan bila relevan;
- row hover mengikuti token theme;
- horizontal scroll digunakan untuk tabel lebar;
- pagination/per-page memakai pola global;
- daftar dokumen yang repetitif sebaiknya compact, bukan card besar per item.

---

## 7. Detail Transaksi

Detail Transaksi adalah workspace operator:

```text
Data ARKAS/BKU readonly
→ Data Umum SPJ
→ Detail Kategori
→ Kelengkapan
→ Buat/Perbarui Paket
```

Rincian item dibuat compact agar uraian panjang tidak menghabiskan vertical space secara berlebihan.

### Konsumsi

Auto-fill peserta melalui `fillTeachers()` saat ini mengambil **Dapodik-only**. UI tidak boleh memberi kesan data berasal dari ARKAS bila function tersebut dipakai.

---

## 8. SPJ Package — struktur canonical saat ini

URL:

```text
/spj?tab=paket&package_id=...
```

Sub-tab internal:

```text
Rincian
Isian Manual
Penomoran
```

### 8.1 Rincian

Tab Rincian harus memperlihatkan dua area yang jelas berbeda:

```text
Panel Rincian Transaksi
Panel Dokumen & Template
```

Keduanya wajib:

- memiliki border/radius/shadow sendiri;
- memiliki header yang berbeda tetapi tetap berasal dari theme accent;
- tidak terlihat seperti satu daftar panjang tanpa hierarchy;
- memakai gap konsisten antar panel.

`Dokumen & Template` dipindahkan ke sub-tab Rincian agar ketika user membuka Isian Manual atau Penomoran, daftar template tidak ikut menambah scroll.

Placement saat ini dilakukan oleh:

```text
resources/js/spj-package-document-placement.js
resources/css/spj-package-document-placement.css
```

Ini compatibility/presentation layer, bukan aturan bisnis.

### 8.2 Dokumen & Template

Daftar dokumen memakai **compact list**:

```text
status / nama dokumen / tipe-format / actions
```

Prinsip:

- satu dokumen tidak perlu card tinggi sendiri;
- group header dibuat subordinate;
- metadata dipadatkan;
- action Preview/Unduh mudah ditemukan;
- zebra/hover/theme mengikuti `--spj-*`/`--ui-*`;
- status warning/success tetap semantic.

### 8.3 Isian Manual

Isian Manual wajib mengikuti theme aktif untuk:

- background panel;
- header;
- heading/label/hint;
- input/select/textarea;
- readonly/disabled;
- panel kategori;
- panel pajak;
- focus state;
- spacing antar panel.

Compatibility layer utama:

```text
resources/css/spj-package-theme-fix.css
```

Top-level panel spacing saat ini dinormalisasi sekitar `.875rem` desktop dan `.75rem` mobile pada area tersebut.

Kategori pada `Paket → Isian Manual` harus mengikuti aturan yang sama dengan Detail Transaksi. Untuk compatibility markup Paket saat ini:

1. perubahan kategori disimpan lebih dulu lalu halaman paket dimuat ulang;
2. section `data-spj-section` yang tidak berlaku disembunyikan dan seluruh control di dalamnya dinonaktifkan, sehingga field `required` kategori lain tidak boleh memblokir tombol **Simpan Isian Paket**;
3. untuk `BARANG`, tanggal Pesanan/BAP/BAST bersifat opsional seperti pada Detail Transaksi; pada `KONSUMSI` field tersebut dapat diwajibkan oleh aturan kategori;
4. controller frontend behavior ini berada di `resources/js/spj-package-manual-category.js`.

**Gap yang masih terbuka:** markup Paket saat ini baru mempunyai section kategori khusus yang lengkap untuk `BARANG`/`KONSUMSI`. `PEMELIHARAAN`, `SPPD`, `HONOR_PEGAWAI`, dan `JASA_LAINNYA` belum mempunyai panel Isian Manual Paket yang setara dengan Detail Transaksi. Sampai shared category partial dibuat, Detail Transaksi tetap menjadi form canonical untuk detail kategori tersebut. Jangan menyatakan Isian Manual Paket sudah fully-aligned lintas semua kategori sebelum gap ini ditutup.

### 8.4 Penomoran

Card triwulan `/spj/penomoran` tidak boleh menggunakan hover light-only seperti `hover:bg-slate-50`. Normal/hover/active state harus memadukan current surface + current theme accent.

---

## 9. Header panel theme-aware

Untuk panel setingkat, header boleh memiliki accent strength berbeda agar hierarchy jelas, tetapi tetap memakai token theme.

Contoh:

```css
background: color-mix(in srgb, var(--theme-accent) 12%, var(--ui-surface-soft));
```

Jangan mengunci header ke `bg-indigo-*`, `bg-slate-*`, atau putih jika panel harus mengikuti theme.

---

## 10. Status dan badge

Gunakan `<x-ui.status-badge>` untuk status teknis dan label operator. Gunakan `<x-ui.badge>` untuk kategori/role/metode pembayaran.

Status umum:

```text
DRAFT / BELUM_LENGKAP -> Belum lengkap
READY                  -> Siap diproses
NUMBERED               -> Sudah bernomor
FINAL / ARCHIVED       -> Final
CANCELLED              -> Dibatalkan
SOURCE_MISSING         -> Tidak muncul di sinkronisasi
```

---

## 11. Compatibility layer

Selama Blade lama masih mengandung class warna Tailwind statis:

- CSS scoped boleh mengoreksi `bg-white`, `text-slate-*`, `bg-indigo-*`, odd/even variants, atau slash-opacity variants;
- compatibility layer harus terlokalisasi;
- kode baru jangan memperbanyak markup legacy;
- bila halaman disentuh besar, migrasikan ke primitive canonical secara bertahap.

Layer SPJ aktif:

```text
spj-workspace-standardization.css
spj-package-theme-fix.css
spj-package-document-placement.css
```

---

## 12. Responsive dan mobile

Desktop tetap workspace utama, tetapi mobile/tablet harus usable.

Perubahan package terbaru belum menutup QA mobile. Status resmi tetap mengikuti `MOBILE_VISUAL_QA_TODO.md`; jangan menyebut mobile-complete sebelum checklist ditutup.

---

## 13. Checklist UI sebelum selesai

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
- perubahan UI tidak melemahkan validation/authorization;
- `npm run build` dijalankan setelah CSS/JS/Blade berubah.

---

## 14. Dokumen pendamping

Aturan CSS praktis ada di:

```text
docs/CSS_USAGE_GUIDE.md
```

Kondisi implementasi terbaru ada di:

```text
docs/CURRENT_PROGRESS.md
```
