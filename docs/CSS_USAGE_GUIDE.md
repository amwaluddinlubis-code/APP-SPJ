# Kamus Penggunaan CSS Aplikasi SPJ

Terakhir diverifikasi: **2026-09-11**

Dokumen ini adalah contract praktis CSS branch `gui-standardization`. Gunakan bersama `GUI_STANDARDIZATION.md`.

---

## 1. Prinsip utama

1. Jangan hard-code warna utama pada elemen yang harus mengikuti tema.
2. Gunakan token CSS `var(--...)` untuk surface, border, foreground, accent, hover, radius, shadow, focus, dan density.
3. Gunakan primitive/class `ui-*` sebelum membuat class baru.
4. Tailwind tetap dipakai terutama untuk layout, spacing, ukuran, grid, flex, responsive, overflow, truncate.
5. Semantic success/warning/danger/status boleh berbeda, tetapi surface harus tetap nyaman di light/dark/theme berwarna.
6. CSS fitur harus scoped.
7. CSS tidak di-import dari feature/runtime JavaScript. Semua CSS masuk melalui `app.css` → `theme-system.css`.

---

## 2. Entry point dan cascade

`resources/css/app.css`:

```css
@import './app-base.css';
@import './human-ui.css';
@import './forms-standardization.css';
@import './theme-system.css';
```

Bagian akhir `theme-system.css` saat ini:

```text
...
theme-accessibility.css
arkas-theme-profiles.css
sidebar-toggle-fix.css
dark-form-controls.css
spj-package-theme-fix.css
spj-package-document-placement.css
view-theme-hardening.css
semantic-status-colors.css
```

Urutan ini disengaja. `view-theme-hardening.css` adalah safety layer global terakhir untuk **warna non-semantik** pada authenticated application views. `semantic-status-colors.css` adalah satu-satunya exception setelahnya dan hanya mengembalikan identitas warna status workflow canonical agar `READY`, `NUMBERED`, `PRINTED`, dan status sejenis tetap mudah dibedakan. Keduanya tidak mengubah business rule atau markup domain.

---

## 3. Token canonical

### Surface

```css
var(--ui-surface-base)
var(--ui-surface-soft)
var(--ui-surface-muted)
var(--ui-component-surface)
var(--ui-component-surface-soft)
```

### Border

```css
var(--ui-line)
var(--ui-line-strong)
var(--ui-component-border)
var(--ui-component-border-strong)
```

### Foreground

```css
var(--ui-fg)
var(--ui-fg-strong)
var(--ui-fg-muted)
var(--ui-component-text)
var(--ui-component-text-strong)
var(--ui-component-text-muted)
var(--ui-component-placeholder)
```

### Theme accent/action

```css
var(--theme-accent)
var(--theme-accent-soft)
var(--theme-accent-strong)
var(--theme-content-accent)
var(--theme-action-bg)
var(--theme-action-fg)
var(--theme-action-hover-bg)
var(--theme-action-hover-fg)
```

### Radius, shadow, density

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

---

## 4. Komponen/class yang dianjurkan

### Tombol

```html
<button class="ui-btn ui-btn-primary">Simpan</button>
<button class="ui-btn ui-btn-secondary">Batal</button>
<button class="ui-btn ui-btn-ghost">Lihat</button>
<button class="ui-btn ui-btn-success">Selesai</button>
<button class="ui-btn ui-btn-danger">Hapus</button>
```

Untuk Blade baru, prefer `<x-ui.button>` dan icon canonical:

```blade
<x-ui.button type="submit">
    <x-ui.icon name="save" size="sm" />
    Simpan
</x-ui.button>

<x-ui.button variant="danger">
    <x-ui.icon name="trash" size="sm" />
    Hapus
</x-ui.button>
```

`x-ui.icon` menggunakan SVG `currentColor`, jadi tidak membutuhkan warna icon hard-coded. Ukuran tersedia: `xs`, `sm`, `md`, `lg`, `xl`. Untuk tombol normal gunakan `size="sm"`; untuk icon standalone gunakan `md` kecuali hierarchy membutuhkan ukuran lain.

Icon dekoratif di dalam tombol berlabel dibiarkan tanpa `label`. Icon standalone harus diberi label aksesibel:

```blade
<x-ui.icon name="info" label="Informasi" />
```

### Form control

```html
<input class="ui-input">
<select class="ui-select"></select>
<textarea class="ui-textarea"></textarea>
<input readonly class="ui-input ui-input-readonly">
```

### Panel/card

```html
<section class="rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
    <header class="border-b border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-4 py-3">
        <h2 class="font-bold text-[var(--ui-fg-strong)]">Judul</h2>
        <p class="text-sm text-[var(--ui-fg-muted)]">Keterangan</p>
    </header>
    <div class="p-4">...</div>
</section>
```

---

## 5. Hierarki teks

```text
strong/title  -> --ui-fg-strong
normal        -> --ui-fg
muted/helper  -> --ui-fg-muted
accent/link   -> --theme-content-accent
```

Kelas `text-slate-*`, `text-gray-*`, `text-zinc-*`, `text-indigo-*`, `text-violet-*`, `text-blue-*`, `text-sky-*`, dan `text-cyan-*` masih dapat ditemukan pada legacy markup. Pada authenticated `<main>`, warna non-semantiknya sekarang diterjemahkan oleh `view-theme-hardening.css` ke token theme. Kelas tersebut **bukan pola yang boleh ditambah pada kode baru**.

---

## 6. Hover/focus theme-aware

Card hover:

```css
.card:hover {
    border-color: color-mix(in srgb, var(--theme-accent) 28%, var(--ui-line));
    background: color-mix(in srgb, var(--theme-accent-soft) 18%, var(--ui-surface-soft));
}
```

Table row:

```css
tr:hover {
    background: var(--ui-table-row-hover);
}
```

Focus form control sudah ditangani `ui-*` dan compatibility layer.

Hindari untuk surface theme-aware:

```text
hover:bg-slate-50
hover:bg-white
hover:border-slate-300
hover:bg-indigo-50
```

Legacy hover/focus/ring dengan palette neutral atau accent diterjemahkan oleh `view-theme-hardening.css`, termasuk variant ber-opacity yang sebelumnya masih dapat kembali ke warna light-only.

---

## 7. Semantic color

Success/warning/danger tetap boleh memakai emerald/amber/rose untuk makna status. Untuk background besar, blend dengan current surface:

```css
background: color-mix(in srgb, var(--ui-surface-base) 84%, #10b981); /* success */
background: color-mix(in srgb, var(--ui-surface-base) 84%, #f59e0b); /* warning */
background: color-mix(in srgb, var(--ui-surface-base) 84%, #f43f5e); /* danger */
```

Audit view global **sengaja tidak menimpa** palette semantic berikut:

```text
emerald / green          -> success
amber / yellow / orange  -> warning / attention
rose / red               -> danger / error
```

Untuk status workflow canonical yang historically memakai `sky`, `indigo`, atau `violet`, `<x-ui.status-badge>` kini memiliki marker `ui-status-badge` + `data-status`, lalu `semantic-status-colors.css` mengembalikan hue semantic dengan `color-mix()` terhadap surface/border/font theme saat ini. Jadi makna status dipertahankan tanpa membuat badge menjadi light-only atau dark-only.

`text-white`, overlay `bg-white/10..20`, dan `border-white/*` pada hero gelap/theme accent juga dipertahankan bila dibutuhkan untuk kontras. View PDF/print/template preview dan public/auth/setup yang tidak berada pada authenticated `<main>` tidak dipaksa mengikuti palette aplikasi karena warna dapat merupakan bagian dari output cetak/branding/pre-login.

---

## 8. Spacing antar panel

Panel yang berada pada level hierarchy sama harus memakai spacing konsisten.

Shared area:

```css
gap: var(--profile-section-gap);
```

Compatibility area dapat memakai ukuran lokal yang sengaja ditetapkan. Contoh Paket → Isian Manual saat ini memakai sekitar `.875rem` desktop dan `.75rem` mobile.

Jangan mencampur `mt-2`, `mt-3`, `mt-4`, `mt-6` secara acak untuk sibling panel.

---

## 9. Tailwind yang tetap dianjurkan

Gunakan Tailwind untuk:

```text
flex / grid
w-* / h-*
min-* / max-*
p-* / px-* / py-*
gap-* / space-y-*
items-* / justify-*
overflow-*
truncate
whitespace-nowrap
sm:/md:/lg:/xl:
```

Warna/surface utama tetap dari token.

---

## 10. Contract SPJ workspace

Wrapper:

```html
<div class="spj-semantic-workspace">...</div>
```

Alias yang tersedia:

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

Gunakan alias ini untuk CSS fitur Paket agar tetap konsisten dengan profile/theme global.

---

## 11. Contract Paket SPJ terbaru

### Rincian

Tab Rincian memiliki dua panel setingkat:

```text
Rincian Transaksi
Dokumen & Template
```

Keduanya harus mempunyai card boundary sendiri. Jangan menyatukannya menjadi satu blok panjang tanpa hierarchy.

Header panel boleh membedakan intensitas accent, misalnya:

```css
background: color-mix(in srgb, var(--theme-accent) 10%, var(--spj-surface-soft));
```

untuk panel transaksi, dan accent sedikit lebih kuat untuk panel Dokumen & Template.

### Dokumen & Template

Gunakan compact list, bukan card tinggi per dokumen. Baris perlu memuat nama, metadata tipe/format, status, dan actions dengan vertical padding kecil.

### Isian Manual

Surface, font, controls, semantic panels, panel pajak, focus, readonly, dan spacing ditangani oleh `spj-package-theme-fix.css`.

### Penomoran

Card triwulan harus menggunakan current theme surface/accent untuk normal/hover/active. Jangan kembali ke `hover:bg-slate-50`.

---

## 12. Dark appearance

Gunakan token, bukan pasangan `bg-white dark:bg-slate-900` untuk surface utama.

`resources/css/dark-form-controls.css` adalah safety layer global control dark mode, termasuk Chrome autofill. `view-theme-hardening.css` bekerja setelah feature compatibility layers supaya utility legacy tidak mengembalikan surface/font ke palette light statis. `semantic-status-colors.css` kemudian hanya memulihkan perbedaan warna status workflow secara theme-safe.

---

## 13. File CSS dan tanggung jawab

| File | Tanggung jawab |
|---|---|
| `app.css` | Entry point CSS aplikasi |
| `app-base.css` | Base/framework |
| `human-ui.css` | Shared/legacy UI |
| `forms-standardization.css` | Fallback form legacy |
| `theme-system.css` | Urutan canonical cascade |
| `theme-profiles.css` | Density/radius/shadow profile |
| `theme-profile-components.css` | Mapping profile → components |
| `token-native-components.css` | Contract `ui-*`/component tokens |
| `layout-token-native.css` | Layout token-native |
| `page-header-unified.css` | Page Header |
| `transactions-standardization.css` | Daftar/detail transaksi |
| `spj-workspace-standardization.css` | SPJ workspace base |
| `dark-form-controls.css` | Dark control safety |
| `spj-package-theme-fix.css` | Paket/Isian Manual/theme compatibility, compact template list, numbering hover |
| `spj-package-document-placement.css` | Pemisahan panel Rincian Transaksi vs Dokumen Template setelah DOM placement |
| `view-theme-hardening.css` | Global bridge untuk hard-coded neutral/accent background, font, border, gradient, hover/focus/ring pada authenticated views |
| `semantic-status-colors.css` | Exception semantic sesudah hardening untuk mempertahankan perbedaan workflow status canonical |
| `theme-accessibility.css` | Focus/contrast/accessibility |

JavaScript placement terkait:

```text
resources/js/spj-package-document-placement.js
```

JS tersebut hanya memindahkan section Dokumen & Template ke panel Rincian; CSS tetap berada di file CSS, bukan di JS.

---

## 14. Kapan membuat CSS baru

Buat CSS fitur baru bila:

- selector benar-benar scoped ke modul/halaman;
- markup legacy terlalu besar untuk segera dipecah;
- compatibility layer diperlukan;
- hover/focus/state tidak cukup dari primitive existing.

Nama:

```text
<feature>-standardization.css
<feature>-theme-fix.css
<feature>-placement.css
```

`view-theme-hardening.css` adalah pengecualian yang sengaja bersifat global karena scope-nya hanya authenticated `<main>` dan fungsinya sebagai migration bridge lintas-view. Jangan menambah rule feature-specific ke file tersebut. `semantic-status-colors.css` juga tidak boleh dipakai untuk dekorasi; scope-nya hanya status canonical.

---

## 15. Pola yang dilarang untuk kode baru

Untuk surface/foreground theme-aware, hindari:

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

Hard-coded hex hanya untuk semantic khusus, branding/ilustrasi, fallback token, output print/PDF, atau kasus yang sengaja tidak mengikuti theme.

Jangan menambah emoji action atau SVG icon manual baru bila `x-ui.icon` sudah menyediakan icon yang setara.

---

## 16. Checklist sebelum commit UI

- light dan dark appearance;
- minimal dua theme/profile;
- hover/focus/active;
- readonly/disabled;
- strong/muted text terbaca;
- semantic status jelas;
- mobile width;
- hierarchy panel jelas;
- spacing sibling konsisten;
- icon action memakai `x-ui.icon` bila tersedia;
- tidak menambah hard-coded surface/accent baru;
- `npm run build` setelah CSS/JS/Blade berubah.

---

## 17. Urutan pilihan untuk kode baru

```text
1. Blade component existing (`x-ui.icon` untuk icon)
2. class ui-*
3. token CSS canonical
4. Tailwind layout/spacing/responsive
5. scoped feature CSS bila perlu
6. compatibility layer hanya untuk legacy
```
