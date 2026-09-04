# SPJ BOSP Web — Panduan Standardisasi GUI

Terakhir diperbarui: 2026-09-04

Dokumen ini menjadi acuan visual dan pengalaman pengguna untuk seluruh halaman aplikasi SPJ BOSP Web. Tujuannya adalah menjaga tampilan seragam, mudah dipahami operator sekolah, konsisten antar modul, dan kompatibel dengan tema yang dipilih user.

---

## 1. Prinsip utama

- UI harus terasa sebagai aplikasi operasional sekolah, bukan admin panel generik.
- Bahasa di layar harus manusiawi dan berorientasi pekerjaan operator.
- Data sumber ARKAS/BKU harus dibedakan jelas dari data manual SPJ.
- Komponen visual sejenis wajib memakai primitive yang sama.
- Jangan membuat hard-coded accent color pada komponen baru jika warna tersebut bukan warna semantik.
- Tailwind menangani visual, Alpine hanya interaksi UI ringan, Livewire menangani state/data reaktif, Laravel menangani route/auth/validation/aturan bisnis.
- Jangan membuat satu halaman menjadi satu card raksasa.

---

## 2. Layout global

Urutan standar:

```text
Header aplikasi global
Breadcrumb sticky
Header halaman + summary
Toolbar / filter
Form / section
Tabel / workspace
Sticky actions / utility footer
```

Sidebar mempertahankan state collapse/expand. Context sekolah, tahun anggaran, dan sumber dana aktif tetap terlihat pada header.

Pada halaman panjang tersedia kontrol sticky **Ke atas** yang muncul setelah user melakukan scroll.

---

## 3. Sistem tema

Accent non-semantik wajib memakai token:

```text
--theme-accent
--theme-accent-strong
--theme-accent-soft
--theme-sidebar
--theme-sidebar-deep
```

Surface/text/border memakai token UI seperti:

```text
--ui-page-bg
--ui-surface
--ui-surface-soft
--ui-border
--ui-border-strong
--ui-text
--ui-muted
```

Aturan:

- pilihan tema user harus otomatis memengaruhi primitive UI;
- dark mode tidak memerlukan versi komponen terpisah;
- success/warning/danger tetap memakai warna semantik agar arti tidak berubah karena theme;
- kompatibilitas markup lama boleh memakai compatibility layer, tetapi kode baru harus memakai primitive `x-ui.*` dan token tema.

---

## 4. Primitive UI resmi

Komponen utama yang tersedia:

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

Komponen lama yang sudah diarahkan ke design system baru:

```text
page-filter
page-table-per-page
tabs
stat-item
error-alert
loading-spinner
```

Jangan membuat primitive baru jika kebutuhan sudah ditangani salah satu komponen di atas.

---

## 5. Breadcrumb dan page header

Breadcrumb global:

- berada tepat di bawah header global;
- sticky;
- tidak boleh diduplikasi secara lokal;
- mengikuti hierarchy yang mudah dibaca;
- boleh wrap di mobile.

Header halaman dan summary yang berkaitan langsung boleh berada dalam satu card. Form, filter, tabel, atau detail lain harus berada pada section terpisah.

Komponen utama:

```text
<x-page-header>
<x-stat-item>
<x-section-card>
```

---

## 6. Form dan input

Gunakan:

```text
<x-ui.field>
<x-ui.input>
<x-ui.select>
<x-ui.textarea>
<x-ui.button>
<x-ui.form-section>
<x-ui.sticky-actions>
```

Standar:

- label selalu terlihat;
- required memakai tanda `*`;
- hint/error dekat field;
- readonly dan disabled mudah dibedakan;
- focus ring mengikuti theme accent;
- textarea untuk uraian/alamat panjang;
- form panjang dibagi section;
- sticky actions dipakai bila tombol Simpan/Batal perlu selalu terlihat.

---

## 7. Toolbar dan filter

Gunakan `<x-ui.toolbar>` dan komponen filter yang sudah ada. Tombol utama, reset filter, export, refresh, sync, dan action tambahan harus mengikuti hierarchy yang sama.

Filter lama yang masih hard-coded diarahkan melalui compatibility layer; view baru tidak boleh mengunci warna ke indigo/slate bila fungsinya hanya accent biasa.

---

## 8. Tabel

Tabel aplikasi memakai standardisasi global dan primitive `<x-ui.table>`.

Standar:

- header konsisten;
- zebra/hover row;
- angka memakai tabular alignment;
- kolom nominal rata kanan;
- status rata tengah bila sesuai;
- action cell konsisten;
- uraian panjang boleh wrap;
- horizontal overflow responsif;
- pagination/per-page memiliki pola visual yang sama;
- dark mode dan tema aktif otomatis diterapkan.

Tabel Filament internal tidak dipaksa memakai override aplikasi. Tabel khusus dapat opt-out dengan `data-ui-table="off"` bila benar-benar diperlukan.

---

## 9. Status dan badge

Status teknis tidak boleh menjadi bahasa utama operator.

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

Gunakan `<x-ui.status-badge>` untuk status dan `<x-ui.badge>` untuk kategori/role/metode pembayaran/label non-status.

---

## 10. Alert, modal, empty state, loading

Gunakan:

- `<x-ui.alert>` untuk info/warning/error/success;
- `<x-ui.modal>` untuk dialog;
- `<x-ui.empty-state>` untuk kondisi data kosong;
- `<x-ui.loading>` untuk spinner/skeleton/loading state;
- `<x-ui.danger-zone>` untuk reset/hapus/restore/reopen/cancel yang bersifat sensitif;
- `<x-ui.action-menu>` bila tabel memiliki banyak aksi sekunder.

Aksi destruktif tetap memerlukan authorization backend dan konfirmasi yang sesuai.

---

## 11. Detail Transaksi sebagai workspace operator

Struktur utama:

```text
Data ARKAS/BKU readonly
→ Data Umum SPJ editable
→ Detail Kategori
→ Checklist Kelengkapan
→ Buat Paket SPJ
→ Penomoran / Preview / Cetak / Final
```

Data sumber harus memiliki treatment visual readonly yang berbeda dari area editable operator.

---

## 12. Responsive dan dark mode

- Desktop tetap menjadi workspace utama, tetapi mobile/tablet harus dapat digunakan.
- Tabel lebar menggunakan horizontal scroll, bukan memaksa kolom menjadi tidak terbaca.
- Action target tetap nyaman disentuh pada mobile.
- Primitive UI harus memakai token surface/accent agar dark mode otomatis konsisten.
- Jangan membuat CSS dark khusus per halaman kecuali ada alasan visual yang benar-benar spesifik.

---

## 13. Compatibility layer

Selama view legacy belum seluruhnya dimigrasikan:

- global CSS/JS boleh menormalkan tabel, status, accent, loading, pagination, dan beberapa pola lama;
- compatibility layer bukan alasan untuk menulis markup lama pada fitur baru;
- setiap view yang disentuh sebaiknya dimigrasikan ke primitive resmi secara bertahap.

---

## 14. Checklist sebelum halaman dianggap selesai

- breadcrumb tidak double;
- page header mengikuti pola global;
- action utama mudah ditemukan;
- readonly vs editable jelas;
- form memakai primitive standar;
- filter/toolbar konsisten;
- tabel memakai standardisasi global;
- empty/loading/error state manusiawi;
- status memakai label manusiawi;
- warna accent mengikuti theme;
- dark mode tetap terbaca;
- mobile/tablet tetap usable;
- perubahan UI tidak mengubah aturan bisnis.

---

## 15. Status roadmap GUI

Sudah menjadi fondasi global:

1. sidebar persisten;
2. breadcrumb global;
3. page header/summary;
4. form/input/button system;
5. status badge manusiawi;
6. table standardization;
7. theme-aware primitive system;
8. alert/empty/modal/detail/toolbar/loading/danger/action primitives;
9. sticky scroll-to-top;
10. compatibility layer legacy.

Masih perlu penyelesaian pada level halaman/workflow:

- rekonsiliasi;
- SPJ/numbering end-to-end;
- dashboard operasional final;
- lifecycle/revisi;
- migrasi view legacy tersisa;
- authorization/safety audit.

---

## 16. Aturan untuk agent/coder berikutnya

1. baca `AGENTS.md` dan dokumen ini sebelum mengubah UI;
2. cek primitive yang sudah ada sebelum membuat komponen baru;
3. jangan menambah breadcrumb lokal;
4. jangan membuat accent color hard-coded untuk fungsi non-semantik;
5. gunakan `x-ui.*` untuk kode baru/view yang sedang disentuh;
6. jangan mengubah nilai enum/status backend hanya untuk tampilan;
7. gunakan semantic success/warning/danger untuk makna tindakan;
8. bedakan data sumber readonly dan data operator editable;
9. jalankan `npm run build` setelah perubahan frontend;
10. perubahan GUI tidak boleh melemahkan validation/authorization backend.
