# Kamus Penggunaan CSS Aplikasi SPJ

Dokumen ini menjadi pedoman penggunaan CSS pada branch `gui-standardization` agar halaman baru dan perbaikan UI tetap konsisten dengan tema yang dipilih pengguna.

## 1. Prinsip utama

1. **Jangan hard-code warna tampilan utama** pada Blade jika elemen harus mengikuti tema.
2. Gunakan **token CSS** (`var(--...)`) untuk background, border, font, accent, hover, radius, shadow, dan focus state.
3. Gunakan komponen kelas `ui-*` yang sudah tersedia sebelum membuat class baru.
4. Tailwind tetap dipakai terutama untuk **layout, ukuran, spacing, grid, flex, responsive**, bukan sebagai sumber warna utama.
5. Warna semantic seperti sukses, peringatan, dan error boleh tetap berbeda, tetapi background harus tetap nyaman pada light/dark/theme berwarna.
6. CSS khusus halaman harus **di-scope** ke halaman/fitur tersebut agar tidak merusak modul lain.
7. Hindari menambah CSS ke JavaScript. Semua CSS masuk melalui `resources/css/app.css` → `theme-system.css`.

---

## 2. Urutan CSS aplikasi

Entry point utama:

```css
/* resources/css/app.css */
@import './app-base.css';
@import './human-ui.css';
@import './forms-standardization.css';
@import './theme-system.css';
```

`theme-system.css` adalah entry point sistem tema dan harus menjaga urutan cascade. File paling akhir memiliki prioritas koreksi paling tinggi.

Saat ini urutannya mencakup antara lain:

```text
theme-profiles.css
theme-profile-components.css
comfortable-text.css
theme-soft-surfaces.css
full-dark.css
dark-theme-refinement.css
token-native-components.css
layout-token-native.css
page-header-unified.css
...
transactions-standardization.css
spj-workspace-standardization.css
...
dark-form-controls.css
spj-package-theme-fix.css
```

### Aturan

- Jangan import file CSS fitur dari `resources/js/app.js`.
- Jika membuat layer koreksi theme-specific, import melalui `theme-system.css`.
- Jangan mengubah urutan tanpa alasan karena urutan tersebut adalah bagian dari contract cascade.

---

## 3. Token warna utama

### Surface / background

Gunakan:

```css
var(--ui-surface-base)
var(--ui-surface-soft)
var(--ui-surface-muted)
```

Untuk shared component gunakan token canonical:

```css
var(--ui-component-surface)
var(--ui-component-surface-soft)
```

### Border

```css
var(--ui-line)
var(--ui-line-strong)
```

atau:

```css
var(--ui-component-border)
var(--ui-component-border-strong)
```

### Font / foreground

```css
var(--ui-fg)
var(--ui-fg-strong)
var(--ui-fg-muted)
```

Untuk shared component:

```css
var(--ui-component-text)
var(--ui-component-text-strong)
var(--ui-component-text-muted)
var(--ui-component-placeholder)
```

### Accent tema

```css
var(--theme-accent)
var(--theme-accent-soft)
var(--theme-accent-strong)
var(--theme-content-accent)
```

### Action / tombol utama

```css
var(--theme-action-bg)
var(--theme-action-fg)
var(--theme-action-hover-bg)
var(--theme-action-hover-fg)
```

### Radius / shadow / density

```css
var(--profile-card-radius)
var(--profile-control-radius)
var(--profile-header-radius)
var(--profile-card-shadow)
var(--profile-floating-shadow)
var(--profile-control-height)
var(--profile-content-padding)
var(--profile-section-gap)
```

Token ini berubah sesuai pengaturan UI pengguna, sehingga jangan menggantinya dengan radius/shadow hard-coded untuk komponen utama.

---

## 4. Kamus class komponen yang dianjurkan

### Tombol

```html
<button class="ui-btn ui-btn-primary">Simpan</button>
<button class="ui-btn ui-btn-secondary">Batal</button>
<button class="ui-btn ui-btn-ghost">Lihat</button>
<button class="ui-btn ui-btn-success">Selesai</button>
<button class="ui-btn ui-btn-danger">Hapus</button>
```

Gunakan Tailwind hanya untuk penyesuaian geometri jika perlu:

```html
<button class="ui-btn ui-btn-primary px-4 py-2 text-sm">Simpan</button>
```

Hindari:

```html
<button class="bg-indigo-600 hover:bg-indigo-700 text-white">Simpan</button>
```

jika tombol tersebut harus mengikuti tema.

---

### Input, select, textarea

```html
<input class="ui-input">
<select class="ui-select"></select>
<textarea class="ui-textarea"></textarea>
```

Readonly:

```html
<input readonly class="ui-input ui-input-readonly">
```

Yang sudah otomatis mengikuti theme:

- background
- border
- font
- placeholder
- hover
- focus ring
- disabled
- readonly
- dark appearance

Hindari:

```html
<input class="bg-white border-slate-300 text-slate-900">
```

---

### Card / section

Gunakan komponen Blade bila tersedia:

```blade
<x-section-card title="Judul" description="Keterangan">
    ...
</x-section-card>
```

Jika markup manual diperlukan:

```html
<section class="rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
    ...
</section>
```

Untuk panel sekunder:

```html
<div class="rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-3">
    ...
</div>
```

Untuk panel readonly / subordinate:

```html
<div class="rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-muted)] p-3">
    ...
</div>
```

---

## 5. Font dan hierarki teks

Judul utama / label kuat:

```html
<h3 class="text-[var(--ui-fg-strong)]">...</h3>
```

Isi normal:

```html
<p class="text-[var(--ui-fg)]">...</p>
```

Keterangan / hint:

```html
<p class="text-[var(--ui-fg-muted)]">...</p>
```

Accent/link utama:

```html
<a class="text-[var(--theme-content-accent)]">...</a>
```

Hindari untuk teks umum:

```text
text-slate-900
text-slate-800
text-slate-700
text-slate-500
text-indigo-700
```

Kelas tersebut masih ada pada legacy markup, tetapi untuk kode baru gunakan token tema.

---

## 6. Hover yang benar

### Card biasa

```css
.my-card:hover {
    border-color: color-mix(in srgb, var(--theme-accent) 28%, var(--ui-line));
    background: color-mix(in srgb, var(--theme-accent-soft) 18%, var(--ui-surface-soft));
}
```

Atau markup sederhana:

```html
<div class="transition hover:bg-[var(--ui-surface-soft)]">...</div>
```

### Row tabel

Gunakan token hover tabel bila konteksnya tabel:

```css
tr:hover {
    background: var(--ui-table-row-hover);
}
```

### Hindari

```text
hover:bg-slate-50
hover:bg-white
hover:border-slate-300
hover:bg-indigo-50
```

untuk elemen yang harus mengikuti theme.

---

## 7. Semantic color: success, warning, danger

Warna semantic tetap boleh digunakan untuk membedakan status.

Contoh text:

```html
<span class="text-emerald-700 dark:text-emerald-300">Sesuai</span>
<span class="text-amber-700 dark:text-amber-300">Perhatian</span>
<span class="text-rose-700 dark:text-rose-300">Gagal</span>
```

Untuk background semantic pada area yang juga harus mengikuti tema, lebih aman gunakan blend:

```css
background-color: color-mix(in srgb, var(--ui-surface-base) 84%, #10b981);
```

Warning:

```css
background-color: color-mix(in srgb, var(--ui-surface-base) 84%, #f59e0b);
```

Danger:

```css
background-color: color-mix(in srgb, var(--ui-surface-base) 84%, #f43f5e);
```

Dengan pola ini panel tidak berubah menjadi warna putih/pastel terang yang mengganggu saat dark mode.

---

## 8. Spacing dan margin antar panel

Untuk halaman dengan beberapa panel vertikal, gunakan satu sumber spacing.

Contoh:

```html
<div class="space-y-4">
    <section>...</section>
    <section>...</section>
    <section>...</section>
</div>
```

atau CSS:

```css
.feature-panels > * + * {
    margin-top: .875rem;
}
```

Gunakan token jika panel merupakan komponen umum:

```css
gap: var(--profile-section-gap);
```

Jangan mencampur `mt-2`, `mt-3`, `mt-4`, `mt-6` secara acak untuk panel yang berada pada level hierarki yang sama.

---

## 9. Pola layout yang tetap boleh menggunakan Tailwind

Tailwind sangat dianjurkan untuk:

```text
flex
grid
hidden/block
w-*/h-*
min-w-*/max-w-*
px-*/py-*/p-*
gap-*
space-y-*
items-*
justify-*
overflow-*
truncate
whitespace-nowrap
sm:/md:/lg:/xl:
```

Contoh yang baik:

```html
<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    ...
</div>
```

Warna tetap berasal dari token tema.

---

## 10. SPJ-specific contract

Semua workspace SPJ baru sebaiknya memiliki wrapper:

```html
<div class="spj-semantic-workspace">
    ...
</div>
```

Wrapper menyediakan alias:

```css
--spj-surface
--spj-surface-soft
--spj-surface-muted
--spj-border
--spj-border-strong
--spj-text
--spj-text-strong
--spj-text-muted
```

Contoh:

```html
<div class="spj-semantic-workspace">
    <section class="rounded-xl border p-4">
        ...
    </section>
</div>
```

Untuk CSS fitur SPJ:

```css
.spj-semantic-workspace .package-card {
    border-color: var(--spj-border);
    background: var(--spj-surface);
    color: var(--spj-text);
}

.spj-semantic-workspace .package-card:hover {
    border-color: color-mix(in srgb, var(--theme-accent) 28%, var(--spj-border));
    background: color-mix(in srgb, var(--theme-accent-soft) 18%, var(--spj-surface-soft));
}
```

---

## 11. Transaction detail contract

Halaman detail transaksi sudah dinormalisasi melalui:

```text
resources/css/transactions-standardization.css
```

Untuk elemen baru pada halaman tersebut gunakan token UI, bukan warna statis.

Contoh:

```html
<div class="rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-3">
    <p class="font-bold text-[var(--ui-fg-strong)]">Judul</p>
    <p class="text-sm text-[var(--ui-fg-muted)]">Keterangan</p>
</div>
```

---

## 12. Dark mode

Jangan menulis dark mode secara manual untuk surface umum jika token theme sudah cukup.

Lebih baik:

```html
<div class="bg-[var(--ui-surface-base)] text-[var(--ui-fg)]">
```

Daripada:

```html
<div class="bg-white text-slate-900 dark:bg-slate-900 dark:text-slate-100">
```

`dark:*` masih boleh digunakan untuk warna semantic kecil bila memang diperlukan, misalnya error/success text.

Form control dark mode juga sudah memiliki safety layer di:

```text
resources/css/dark-form-controls.css
```

---

## 13. File CSS dan tanggung jawabnya

| File | Fungsi |
|---|---|
| `app.css` | Entry point CSS aplikasi |
| `app-base.css` | Framework/base |
| `human-ui.css` | Legacy/shared UI |
| `forms-standardization.css` | Standardisasi form lama |
| `theme-system.css` | Urutan seluruh layer theme |
| `theme-profiles.css` | Radius, density, shadow, UI profile |
| `theme-profile-components.css` | Mapping profile ke komponen |
| `token-native-components.css` | Contract `ui-*` dan shared tokens |
| `layout-token-native.css` | Layout aplikasi berbasis token |
| `page-header-unified.css` | Header halaman |
| `transactions-standardization.css` | Detail/daftar transaksi |
| `spj-workspace-standardization.css` | Workspace SPJ |
| `spj-package-theme-fix.css` | Final safety layer SPJ Paket/Isian Manual |
| `dark-form-controls.css` | Safety layer input pada dark appearance |
| `theme-accessibility.css` | Accessibility/focus/contrast |

---

## 14. Kapan membuat CSS baru

Buat file CSS fitur baru jika:

- selector hanya berlaku pada satu modul/halaman;
- markup legacy terlalu besar untuk langsung direfaktor;
- diperlukan compatibility layer terhadap class warna lama;
- state hover/focus/active tidak cukup ditangani oleh komponen existing.

Nama yang dianjurkan:

```text
<feature>-standardization.css
<feature>-theme-fix.css
```

Scope selalu ke identifier khusus:

```css
main:has(#feature-root) ...
```

atau:

```css
.feature-semantic-workspace ...
```

Jangan membuat selector global seperti:

```css
div { ... }
.card { ... }
input { ... }
```

kecuali memang berada pada file shared contract dan sudah diuji lintas halaman.

---

## 15. Pola yang dilarang untuk kode baru

Hindari:

```text
bg-white
bg-slate-50
bg-slate-100
text-slate-900
text-slate-800
text-slate-700
border-slate-200
border-slate-300
hover:bg-slate-50
hover:border-slate-300
bg-indigo-50
text-indigo-700
```

jika elemen tersebut adalah surface/foreground utama yang harus berubah mengikuti tema.

Juga hindari:

```css
background: #fff;
color: #0f172a;
border-color: #e2e8f0;
```

pada komponen theme-aware.

Hard-coded hex hanya boleh untuk:

- warna semantic khusus;
- ilustrasi/branding;
- fallback token;
- kasus yang sengaja tidak mengikuti theme dan terdokumentasi.

---

## 16. Template cepat untuk panel baru

```html
<section class="rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-[var(--profile-card-shadow)]">
    <header class="border-b border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-4 py-3">
        <h2 class="font-bold text-[var(--ui-fg-strong)]">Judul Panel</h2>
        <p class="mt-1 text-sm text-[var(--ui-fg-muted)]">Keterangan panel.</p>
    </header>

    <div class="p-4">
        <label class="text-sm font-semibold text-[var(--ui-fg-strong)]">Nama</label>
        <input class="ui-input mt-1" placeholder="Isi nama">
    </div>
</section>
```

---

## 17. Template cepat card clickable

```html
<a class="block rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-4 text-[var(--ui-fg)] transition hover:border-[var(--theme-accent)] hover:bg-[var(--ui-surface-soft)]">
    <p class="font-bold text-[var(--ui-fg-strong)]">Judul</p>
    <p class="mt-1 text-sm text-[var(--ui-fg-muted)]">Deskripsi</p>
</a>
```

Jika hover accent terasa terlalu kuat, gunakan CSS `color-mix()` agar mengikuti karakter masing-masing tema.

---

## 18. Checklist sebelum commit UI

Sebelum commit perubahan UI, periksa:

- light appearance;
- dark appearance;
- minimal dua theme/profile berbeda;
- hover card dan row;
- focus input/select/textarea;
- readonly dan disabled;
- text strong/muted tetap terbaca;
- semantic success/warning/error tetap jelas;
- mobile width;
- spacing antar panel konsisten;
- tidak ada `bg-white`/`text-slate-*` baru yang seharusnya theme-aware;
- jalankan `npm run build` jika CSS/JS berubah.

---

## 19. Ringkasan keputusan

Untuk kode baru, urutan pilihan adalah:

```text
1. Gunakan Blade UI component yang sudah ada.
2. Gunakan class ui-*.
3. Gunakan CSS variable/token theme.
4. Gunakan Tailwind untuk layout/spacing/responsive.
5. Buat CSS scoped khusus fitur hanya bila 1–4 tidak cukup.
6. Jangan kembali ke warna hard-coded untuk surface utama.
```

Dokumen ini adalah contract praktis CSS untuk pekerjaan lanjutan pada branch `gui-standardization`.