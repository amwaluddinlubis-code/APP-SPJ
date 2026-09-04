# SPJ BOSP Web — Panduan Standardisasi GUI

Dokumen ini menjadi acuan visual dan pengalaman pengguna untuk seluruh halaman aplikasi SPJ BOSP Web. Tujuannya adalah menjaga tampilan seragam, mudah dipahami operator sekolah, dan konsisten dengan pendekatan TALL Stack.

Terakhir diperbarui: 2026-09-04

---

## 1. Prinsip utama

- UI harus terasa sebagai aplikasi operasional sekolah, bukan admin panel generik.
- Bahasa di layar harus manusiawi dan berorientasi pekerjaan operator.
- Data sumber dari ARKAS/BKU harus dibedakan jelas dari data manual SPJ.
- Komponen visual yang sejenis wajib memakai pola, ukuran, spacing, dan hierarchy yang sama.
- Tailwind menangani visual, Alpine hanya interaksi UI ringan, Livewire menangani state/data reaktif, Laravel menangani route, auth, validation, dan aturan bisnis.
- Jangan membuat satu halaman menjadi satu card raksasa. Header + summary boleh satu card, sedangkan form, tabel, filter, dan detail harus tetap menjadi section/card terpisah.
- Semua warna accent non-semantik wajib berasal dari token `--theme-*`; jangan mengunci warna utama ke indigo/blue/violet pada komponen baru.
- Warna success/warning/danger tetap semantik dan tidak mengikuti accent theme.

---

## 2. Layout global

Urutan visual halaman standar:

```text
Header aplikasi global
Breadcrumb sticky

[ Header halaman + summary/statistik ]

[ Form / filter / section ]

[ Tabel / rincian / panel kerja ]
```

Sidebar harus mempertahankan state collapse/expand antar navigasi dan reload.

Context sekolah, tahun anggaran, dan sumber dana aktif harus tetap terlihat pada header aplikasi.

Gunakan `<x-ui.page-shell>` untuk halaman baru atau halaman yang sedang direfactor agar lebar dan ritme vertikal seragam.

---

## 3. Breadcrumb global

Breadcrumb menggunakan satu resolver global dan tidak boleh diduplikasi di dalam page header atau section lokal.

Contoh:

```text
Beranda > Keuangan > Transaksi > Detail Transaksi
Beranda > Data & Sinkronisasi > Integrasi ARKAS
Beranda > Administrasi > Profil Sekolah
```

Aturan:

- breadcrumb berada tepat di bawah header global;
- breadcrumb menggunakan border, background putih/transparan, blur, dan shadow tipis;
- breadcrumb bersifat `sticky` saat scroll;
- posisi sticky harus mengikuti tinggi header global, bukan memakai offset hard-coded;
- halaman aktif diberi treatment visual yang lebih tegas;
- level tengah lebih ringan agar hierarchy mudah dipindai;
- breadcrumb lokal lama harus dihapus atau disembunyikan agar tidak double;
- pada layar kecil breadcrumb boleh wrap, tetapi tetap mudah dibaca.

Implementasi saat ini berpusat pada:

```text
resources/views/components/global-breadcrumb.blade.php
resources/views/components/layouts/tailwind-app.blade.php
```

---

## 4. Header halaman + summary

Breadcrumb berada di luar page header.

Header halaman dan summary/statistik yang berkaitan langsung boleh digabung menjadi satu bordered card.

Komponen utama:

```text
<x-page-header>
<x-stat-item>
<x-section-card>
<x-ui.page-shell>
```

`<x-stat-item>` sekarang memakai primitive `.ui-stat` dan otomatis mengikuti theme aktif.

---

## 5. Standardisasi form dan input

Semua form harus mengikuti satu sistem kontrol.

Komponen utama:

```text
<x-ui.field>
<x-ui.input>
<x-ui.select>
<x-ui.textarea>
<x-ui.button>
<x-ui.form-section>
<x-ui.sticky-actions>
```

Standar visual:

- tinggi input medium sekitar 40–42 px;
- `text-sm` untuk kontrol utama;
- border netral yang jelas tetapi tidak berat;
- hover state sedikit lebih tegas;
- focus menggunakan accent theme dan ring tipis;
- readonly/disabled memiliki background lebih redup;
- label selalu terlihat;
- required menggunakan tanda `*`;
- hint dan error berada tepat di bawah field;
- textarea dipakai untuk uraian/alamat panjang, bukan input satu baris;
- tombol menggunakan hierarchy primary, secondary, success, danger, atau ghost;
- form panjang dibagi menjadi section berdasarkan konteks, bukan satu grid besar tanpa struktur;
- action bar boleh sticky pada form panjang agar tombol Simpan/Batal selalu mudah dijangkau.

Global fallback style tetap harus menjaga form lama agar konsisten sampai seluruh halaman selesai dimigrasikan ke komponen `x-ui.*`.

---

## 6. Detail Transaksi sebagai workspace operator

Detail transaksi bukan sekadar halaman detail. Halaman ini adalah ruang kerja operator.

### 6.1 Data ARKAS/BKU — readonly

Menampilkan data sumber sinkronisasi sebagai referensi resmi. Operator tidak mengubah data sumber ini dari Detail Transaksi.

### 6.2 Data SPJ Operator — editable

Menampilkan data yang memang harus dilengkapi operator seperti kategori SPJ, uraian pembayaran, penerima kuitansi, metode pembayaran, referensi pembayaran, penandatangan, rincian item, dan detail kategori.

Urutan kerja yang disarankan:

```text
Data ARKAS / BKU
    ↓
Data Umum SPJ
    ↓
Detail Kategori
    ↓
Checklist Kelengkapan
    ↓
Buat Paket SPJ
    ↓
Penomoran / Cetak / Final
```

Workspace boleh memiliki navigasi sticky internal untuk membantu operator berpindah bagian pada halaman panjang.

---

## 7. Status dan badge manusiawi

Status teknis tidak boleh menjadi bahasa utama yang dibaca operator. Nilai teknis tetap disimpan di backend untuk aturan bisnis, tetapi UI menampilkan istilah kerja yang mudah dipahami.

Mapping utama:

```text
DRAFT / BELUM_LENGKAP            -> Belum lengkap
READY / SIAP / DISIAPKAN         -> Siap diproses
NUMBERED / BERNOMOR              -> Sudah bernomor
PRINTED / DICETAK                -> Sudah dicetak
FINAL / ARCHIVED / ARSIP         -> Final
CANCELLED / CANCELED             -> Dibatalkan
SOURCE_MISSING                   -> Tidak muncul di sinkronisasi
requires_reconciliation          -> Perlu rekonsiliasi
DITETAPKAN                       -> Sudah ditetapkan
PENDING                          -> Menunggu diproses
PROCESSING / RUNNING             -> Sedang diproses
COMPLETED / SUCCESS / SUCCEEDED  -> Selesai
FAILED / ERROR                   -> Gagal
LOCKED                           -> Terkunci
UNLOCKED                         -> Dapat diedit
REPLACED                         -> Diganti
GENERATED                        -> Dokumen dibuat
```

Komponen standar:

```blade
<x-ui.status-badge :status="$package->status" />
<x-ui.badge variant="theme">Transfer Bank</x-ui.badge>
<x-ui.badge variant="success">Aktif</x-ui.badge>
```

`status-badge` dipakai untuk lifecycle/status bisnis, sedangkan `badge` dipakai untuk kategori, role, metode pembayaran, triwulan, dan label non-status.

---

## 8. Sistem tabel global

Semua tabel aplikasi memakai baseline global `app-data-table`/`<x-ui.table>`.

Standar:

- header konsisten dan mengikuti accent theme;
- nominal/angka rata kanan dan memakai tabular numbers;
- status rata tengah;
- uraian dapat wrap;
- action cell ringkas;
- hover/zebra konsisten;
- wrapper horizontal scroll pada layar kecil;
- pagination visual sama;
- dark mode wajib didukung;
- tabel Filament internal tidak dioverride;
- tabel khusus dapat opt-out dengan `data-ui-table="off"`.

---

## 9. Primitive UI generalisasi

Komponen reusable resmi:

```text
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
<x-ui.page-shell>
<x-ui.table>
```

CSS utama:

```text
resources/css/ui-generalization.css
resources/css/table-standardization.css
```

Keduanya dimuat global dari layout melalui `toast-notifications` sehingga halaman lama tetap memperoleh compatibility layer selama migrasi bertahap.

### Penggunaan semantik

- `alert`: pesan info, success, warning, danger di dalam halaman;
- `empty-state`: data kosong, hasil filter kosong, belum sinkron, belum ada paket;
- `badge`: kategori/role/metode pembayaran/label singkat;
- `detail-list` + `detail-item`: pasangan label/value;
- `toolbar`: kumpulan action/filter/export/sync;
- `modal`: dialog UI umum;
- `action-menu`: kumpulan aksi row/tabel;
- `loading`: indikator proses Livewire/async;
- `sticky-actions`: tombol form panjang;
- `danger-zone`: reset/hapus/reopen/cancel dan aksi berisiko;
- `page-shell`: container standar halaman;
- `table`: tabel aplikasi baru/refactor.

---

## 10. Kompatibilitas tema

Semua primitive non-semantik menggunakan token berikut:

```text
--theme-accent
--theme-accent-strong
--theme-accent-soft
--theme-sidebar
--theme-sidebar-deep
```

Surface/text/border menggunakan token UI:

```text
--ui-surface
--ui-surface-soft
--ui-border
--ui-border-strong
--ui-text
--ui-muted
```

Dengan pola ini, tema light, dark, slate, gray, zinc, neutral, stone, red, orange, yellow, lime, green, teal, sky, blue, indigo, violet, purple, cyan, emerald, amber, rose, pink, dan fuchsia tetap kompatibel.

Aturan:

- jangan memakai `bg-indigo-*`, `text-indigo-*`, `border-indigo-*` untuk accent baru;
- gunakan `.theme-*` atau primitive `ui-*`;
- success tetap hijau, warning amber, danger rose/red;
- dark mode harus tetap menjaga semantic feedback terbaca;
- compatibility layer boleh mewarnai markup lama, tetapi kode baru harus memakai primitive resmi.

---

## 11. Checklist UX sebelum sebuah halaman dianggap selesai

Sebuah halaman dianggap konsisten jika:

- tidak memiliki breadcrumb double;
- breadcrumb global tampil dan sticky;
- header + summary berada dalam satu card bila memang berkaitan;
- form/tabel lain tidak ikut terbungkus page header;
- input/select/textarea mengikuti standar form;
- tabel mengikuti sistem global;
- empty state memakai primitive standar;
- alert/callout memakai primitive standar;
- action utama mudah ditemukan;
- readonly dan editable dapat dibedakan sekilas;
- status menggunakan bahasa operator dan badge semantik;
- filter/tabs/stat cards mengikuti theme aktif;
- aksi destruktif ditempatkan dalam danger zone bila relevan;
- form panjang memakai sticky action bila dibutuhkan;
- loading state tersedia untuk proses reaktif/async;
- layout tetap nyaman pada mobile;
- tidak ada Alpine dan Livewire yang berebut state UI yang sama;
- perubahan visual tidak mengubah aturan bisnis tanpa alasan eksplisit.

---

## 12. Status roadmap GUI

1. Sidebar persisten — diterapkan.
2. Header + summary + breadcrumb global — diterapkan.
3. Standardisasi Form & Input — fondasi global diterapkan.
4. Generalisasi tabel — diterapkan global.
5. Generalisasi primitive UI — diterapkan global.
6. Detail Transaksi sebagai workspace operator — terus disempurnakan.
7. Status & badge manusiawi — fondasi diterapkan, migrasi view berlangsung.
8. UI Rekonsiliasi — migrasi ke primitive baru berikutnya.
9. Workflow SPJ & Penomoran — migrasi ke primitive baru berikutnya.
10. Dashboard operasional — konsolidasi terakhir.
11. Authorization/safety audit.
12. Lifecycle dokumen dan revisi.

---

## 13. Aturan untuk agent/coder berikutnya

Sebelum mengubah Blade, Livewire, CSS, atau layout:

1. baca `AGENTS.md`;
2. baca dokumen ini;
3. cek komponen UI yang sudah ada sebelum membuat komponen baru;
4. jangan membuat primitive baru jika fungsi yang sama sudah tersedia;
5. jangan membuat breadcrumb lokal baru;
6. jangan membungkus seluruh `{{ $slot }}` dalam satu card global;
7. gunakan komponen `x-ui.*` untuk form dan pola UI baru atau yang sedang disentuh;
8. jangan hard-code accent color baru;
9. bedakan data sumber readonly dan data operator editable;
10. jangan tampilkan status teknis mentah jika mapping manusiawi tersedia;
11. gunakan `<x-ui.danger-zone>` untuk kelompok aksi destruktif;
12. gunakan `<x-ui.empty-state>` untuk kondisi kosong;
13. gunakan `<x-ui.toolbar>` untuk kelompok aksi halaman;
14. lakukan perubahan dalam batch kecil dan cek browser setelah build;
15. jalankan `npm run build` setelah perubahan frontend dan test Laravel yang relevan setelah perubahan backend.
