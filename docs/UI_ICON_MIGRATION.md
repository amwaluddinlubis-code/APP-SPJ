# Migrasi Icon Canonical

Terakhir diverifikasi: **2026-09-12** terhadap implementasi icon aktif pada branch `gui-standardization`.

Status: **PARTIAL MIGRATION / CANONICAL GLOBAL LAYOUT / COMPATIBILITY BRIDGE MASIH DIPAKAI.**

Dokumen ini menjelaskan arah migrasi icon UI. Ia bukan sumber status release umum; gunakan `CURRENT_PROGRESS.md` untuk status project dan `GUI_STANDARDIZATION.md` untuk kontrak visual utama.

---

## 1. Target canonical

Untuk markup/action baru, sumber icon canonical adalah:

```text
<x-ui.icon name="..." />
<x-ui.button icon="...">...</x-ui.button>
```

Komponen utama:

```text
resources/views/components/ui/icon.blade.php
resources/views/components/ui/button.blade.php
```

Aturan baru:

- action baru memakai `<x-ui.icon>` atau prop `icon` pada `<x-ui.button>`;
- jangan menambah SVG action inline baru bila icon canonical dapat dipakai/diperluas;
- jangan menambah emoji/simbol teks sebagai pengganti icon action baru;
- icon dekoratif pada button berlabel harus `aria-hidden`;
- icon standalone yang membawa makna harus mempunyai label aksesibel melalui prop `label` atau label pada action induknya.

`<x-ui.icon>` mendukung ukuran `xs`, `sm`, `md`, `lg`, dan `xl`, serta fallback visual bila nama icon tidak dikenal. Fallback tersebut **bukan alasan** untuk memakai nama icon yang belum didaftarkan: nama yang diperlukan tetap harus ditambahkan secara eksplisit ke katalog canonical.

---

## 2. Temuan audit: compatibility component masih aktif

Project sudah memakai `<x-ui.icon>` sebagai registry/rendering canonical, tetapi compatibility component lama belum dapat dihapus karena consumer feature lama masih harus dimigrasikan bertahap.

### 2.1 Canonical action icon

```text
<x-ui.icon>
```

Digunakan oleh primitive UI baru, `<x-ui.button>`, global authenticated layout/sidebar, dan compatibility template action.

### 2.2 Legacy compatibility icon

Komponen berikut masih dipertahankan sebagai adapter compatibility:

```text
<x-ui-icon>
resources/views/components/ui-icon.blade.php
```

Registry SVG terpisah sudah tidak ada; adapter tersebut meneruskan rendering ke `<x-ui.icon>`. Global authenticated layout/sidebar sudah dimigrasikan ke `<x-ui.icon>`, termasuk icon Penomoran, Reset Database, Uji Sebagai User, menu mobile, profil, dan navigasi utama.

Consumer feature legacy yang masih ada harus tetap dimigrasikan bertahap. Karena itu `<x-ui-icon>` **tidak boleh dihapus secara prematur** sebelum seluruh pemakaian tersisa diverifikasi dan hasil visual/runtime diperiksa.

Target akhirnya tetap satu component canonical, tetapi migrasi harus dilakukan bertahap.

---

## 3. Compatibility action bridge yang masih aktif

View lama yang besar belum harus direwrite sekaligus. Untuk action legacy berbasis label, aplikasi masih memakai:

```text
resources/views/components/ui/icon-templates.blade.php
resources/js/legacy-action-icon-migrator.js
resources/js/action-icon-deduplicator.js
```

`legacy-action-icon-migrator.js` dimuat dari bundle canonical melalui:

```text
resources/js/app.js
→ resources/js/bootstrap.js
→ legacy-action-icon-migrator.js
```

`action-icon-deduplicator.js` ikut dimuat setelahnya untuk membersihkan icon ganda dari normalizer lama/page-specific.

Compatibility layer ini adalah **jembatan**, bukan API icon baru.

---

## 4. Cara kerja migrator legacy

Migrator memindai:

```text
button
a[href]
```

Lalu:

1. membaca label text/`aria-label`/`title`;
2. membersihkan emoji/prefix legacy;
3. mencocokkan label terhadap rule action;
4. mengambil clone SVG dari `icon-templates.blade.php`;
5. memasang icon canonical pada action yang belum mempunyai icon;
6. menandai element dengan `data-ui-icon-migrated="true"`.

DOM baru juga ditangani melalui:

```text
DOMContentLoaded
livewire:navigated
MutationObserver
```

Migrator tidak mengubah:

- route;
- HTTP method;
- form payload;
- authorization;
- validation;
- lifecycle;
- business rule.

Action yang sudah mempunyai SVG/icon langsung tidak didekorasi ulang.

---

## 5. Subset compatibility bukan katalog canonical

`icon-templates.blade.php` hanya menyediakan subset icon yang diperlukan migrator legacy, saat ini seperti:

```text
save
edit
trash
plus
download
printer
arrow-left
arrow-right
refresh
arrow-up
arrow-down
eye
document
check
close
external-link
filter
lock
```

Daftar tersebut **bukan** daftar penuh `<x-ui.icon>`.

Jangan menambah icon ke compatibility template hanya agar markup baru dapat memakainya. Markup baru harus langsung memanggil `<x-ui.icon>`.

Tambahkan ke compatibility template hanya bila masih ada action legacy yang memang harus dijembatani sampai view tersebut dimigrasikan.

---

## 6. Cakupan label compatibility saat ini

Migrator masih mengenali action seperti:

```text
Simpan
Buat/Buka Paket SPJ
Tambah
Hapus
Edit/Ubah/Perbaiki
Download/Unduh
Cetak/Print/Pratinjau PDF
Kembali/Semua Paket
Refresh/Muat Ulang/Sinkronisasi
Naik/Turun urutan
Lihat/Buka transaksi atau Paket
Isi data
Paket terkunci
Rincian
Isian Manual
Kesiapan
Penomoran
Berikutnya/Lanjut
```

Rule ini bergantung pada label operator dan karena itu tidak boleh menjadi foundation jangka panjang untuk action baru.

Jika wording action baru berubah, jangan otomatis memperluas regex migrator. Pertama tentukan apakah action tersebut seharusnya sudah memakai component canonical secara langsung.

---

## 7. Deduplication contract

`action-icon-deduplicator.js` mempertahankan aturan:

```text
canonical/global SVG > transaction-detail-inline-icon
```

Jika action memiliki SVG canonical dan icon hasil normalizer halaman, icon halaman dihapus.

Jika normalizer halaman menghasilkan lebih dari satu icon, hanya satu yang dipertahankan.

Keberadaan deduplicator menunjukkan compatibility debt masih aktif. File ini tidak boleh dianggap pengganti cleanup markup jangka panjang.

---

## 8. Known migration debt

Audit terbaru menunjukkan debt berikut masih nyata:

1. `<x-ui.icon>` dan adapter `<x-ui-icon>` masih hidup bersamaan untuk consumer feature legacy;
2. compatibility action migrator masih dibutuhkan oleh sebagian markup;
3. page-specific icon normalizer masih membutuhkan deduplicator;
4. consumer feature lama masih harus dimigrasikan secara bertahap sebelum adapter `<x-ui-icon>` dapat dihapus;
5. browser QA tetap diperlukan untuk memastikan icon tidak hilang/ganda setelah Livewire navigation dan DOM update.

Milestone yang sudah dicapai:

- global authenticated layout/sidebar memakai `<x-ui.icon>`;
- simbol navigasi global `№`, `↺`, dan `◎` sudah diganti dengan icon canonical `number`, `refresh`, dan `user`;
- menu mobile, profile actions, logout, dan chevron group global memakai registry canonical;
- tab Laporan Audit dan action Tambah Pegawai yang disentuh juga memakai icon canonical.

Debt tersebut adalah **P2 visual/maintainability**, bukan alasan mengubah lifecycle atau domain SPJ.

---

## 9. Urutan migrasi yang aman

Jangan memulai dengan menghapus compatibility file.

Urutan canonical:

```text
1. Inventaris nama icon yang dipakai <x-ui-icon>
2. Tambahkan padanan/alias yang diperlukan ke <x-ui.icon>
3. Verifikasi ukuran, stroke, alignment, dan accessibility
4. Migrasikan layout/sidebar ke <x-ui.icon>                         [DONE source-level]
5. Ganti simbol teks action/navigation global yang relevan          [DONE source-level]
6. Saat view feature disentuh, migrasikan action legacy ke <x-ui.button icon="..."> / <x-ui.icon>
7. Kurangi rule legacy-action-icon-migrator yang sudah tidak mempunyai consumer
8. Hapus page-specific duplicate icon normalizer bila tidak lagi diperlukan
9. Hapus action-icon-deduplicator setelah tidak ada sumber icon ganda
10. Hapus icon-templates + legacy migrator hanya setelah tidak ada consumer legacy
11. Hapus <x-ui-icon> setelah seluruh pemakaian selesai dimigrasikan
```

Setiap tahap harus kecil dan dapat direview. Jangan melakukan penggantian massal hanya berdasarkan kesamaan nama icon.

`DONE source-level` pada tahap 4–5 tidak berarti browser visual PASS; runtime tetap mengikuti `GUI_RUNTIME_QA.md`.

---

## 10. Rule saat menyentuh view

Saat sebuah view disentuh secara substansial:

- action baru/yang sedang dirapikan diarahkan ke `<x-ui.button icon="...">` bila cocok;
- bila bukan button component, gunakan `<x-ui.icon>` langsung;
- jangan menambah ketergantungan baru pada migrator berdasarkan label;
- jangan menambahkan inline SVG duplikat;
- pertahankan text label untuk action penting; icon tidak menggantikan nama aksi kecuali desain icon-only memang mempunyai accessible label;
- jangan mengubah route/method/payload hanya sebagai efek samping migrasi visual.

Compatibility code boleh tetap hidup untuk view lain yang belum disentuh.

---

## 11. Exit criteria compatibility layer

`legacy-action-icon-migrator.js`, `icon-templates.blade.php`, dan `action-icon-deduplicator.js` baru dapat dipensiunkan bila:

- tidak ada action operasional yang bergantung pada label-based icon injection;
- tidak ada page-specific icon injection yang berkompetisi dengan canonical icon;
- layout/sidebar sudah tidak memakai `<x-ui-icon>`;
- seluruh nama icon yang dibutuhkan tersedia di `<x-ui.icon>`;
- simbol teks legacy yang berfungsi sebagai icon sudah dimigrasikan atau sengaja dipertahankan dengan keputusan UI eksplisit;
- browser QA memastikan icon tidak hilang/ganda setelah Livewire navigation dan DOM update.

Global layout/sidebar sekarang memenuhi bagian source-level dari kriterianya, tetapi compatibility layer belum boleh dihapus selama consumer feature dan runtime evidence tersisa.

Setelah seluruh exit criteria terpenuhi, component/file compatibility dapat dihapus melalui patch terpisah dengan regression/build verification.

---

## 12. Verification setelah perubahan icon

Minimum deterministic check:

```powershell
npm run build
php artisan view:cache --no-interaction
git diff --check
```

Untuk perubahan yang menyentuh layout, Livewire, atau compatibility migrator, tambahkan browser QA:

```text
- desktop sidebar expanded/collapsed
- header/profile actions
- Daftar Transaksi
- Detail Transaksi
- Paket SPJ
- action setelah livewire:navigated
- modal/dynamic DOM
- dark/light theme
- keyboard/focus/accessibility label
- tidak ada icon ganda
- tidak ada action kehilangan label/icon
```

Build/Blade PASS saja tidak membuktikan hasil visual browser.

---

## 13. Arah akhir

Target akhirnya:

```text
SATU catalog icon canonical
→ <x-ui.icon>
→ dipakai langsung oleh primitive/view
→ tanpa label-based migrator
→ tanpa duplicate-icon cleanup runtime
```

Sampai target tersebut tercapai, compatibility bridge tetap dipertahankan secara terkontrol dan tidak boleh diperluas menjadi arsitektur permanen.
