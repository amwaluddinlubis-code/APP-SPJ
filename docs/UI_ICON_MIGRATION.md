# Migrasi Icon Canonical

Terakhir diverifikasi: **2026-09-11** terhadap implementasi icon aktif pada branch `gui-standardization`.

Status: **PARTIAL MIGRATION / DUAL ICON SYSTEM ACTIVE / COMPATIBILITY BRIDGE MASIH DIPAKAI.**

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

## 2. Temuan audit: dua sistem icon masih aktif

Project saat ini belum memakai satu component icon saja.

### 2.1 Canonical action icon

```text
<x-ui.icon>
```

Digunakan oleh primitive UI baru, `<x-ui.button>`, dan compatibility template action.

### 2.2 Legacy/global navigation icon

Komponen lain masih aktif:

```text
<x-ui-icon>
resources/views/components/ui-icon.blade.php
```

Komponen ini masih dipakai luas pada layout/sidebar dan mempunyai katalog nama sendiri, misalnya:

```text
dashboard
budget
transaction
tax
report
audit
sync
archive
server
logout
employee
preview
excel
pdf
work
number
priority
progress
queue
system
balance
```

Sebagian nama tersebut belum mempunyai padanan langsung pada `<x-ui.icon>`.

Karena itu `<x-ui-icon>` **tidak boleh dihapus atau diganti massal** sebelum katalog canonical diperluas/di-alias-kan dan hasil visual diverifikasi.

Target akhirnya tetap satu sistem canonical, tetapi migrasi harus dilakukan bertahap.

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

Audit menemukan debt berikut yang masih nyata:

1. `<x-ui.icon>` dan `<x-ui-icon>` hidup bersamaan;
2. katalog kedua component belum identik;
3. layout/sidebar masih memakai `<x-ui-icon>` secara luas;
4. beberapa navigasi masih memakai simbol teks seperti `№`, `↺`, atau `◎`;
5. legacy action migrator masih dibutuhkan oleh sebagian markup;
6. page-specific icon normalizer masih membutuhkan deduplicator.

Debt tersebut adalah **P2 visual/maintainability**, bukan alasan mengubah lifecycle atau domain SPJ.

---

## 9. Urutan migrasi yang aman

Jangan memulai dengan menghapus compatibility file.

Urutan canonical:

```text
1. Inventaris nama icon yang dipakai <x-ui-icon>
2. Tambahkan padanan/alias yang diperlukan ke <x-ui.icon>
3. Verifikasi ukuran, stroke, alignment, dan accessibility
4. Migrasikan layout/sidebar ke <x-ui.icon>
5. Ganti simbol teks action/navigation yang relevan dengan icon canonical
6. Saat view feature disentuh, migrasikan action legacy ke <x-ui.button icon="..."> / <x-ui.icon>
7. Kurangi rule legacy-action-icon-migrator yang sudah tidak mempunyai consumer
8. Hapus page-specific duplicate icon normalizer bila tidak lagi diperlukan
9. Hapus action-icon-deduplicator setelah tidak ada sumber icon ganda
10. Hapus icon-templates + legacy migrator hanya setelah tidak ada consumer legacy
11. Hapus <x-ui-icon> setelah seluruh pemakaian selesai dimigrasikan
```

Setiap tahap harus kecil dan dapat direview. Jangan melakukan penggantian massal hanya berdasarkan kesamaan nama icon.

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

Setelah itu component/file compatibility dapat dihapus melalui patch terpisah dengan regression/build verification.

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
