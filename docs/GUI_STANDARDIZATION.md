# SPJ BOSP Web — Panduan Standardisasi GUI

Dokumen ini menjadi acuan visual dan pengalaman pengguna untuk seluruh halaman aplikasi SPJ BOSP Web. Tujuannya adalah menjaga tampilan seragam, mudah dipahami operator sekolah, dan konsisten dengan pendekatan TALL Stack.

Terakhir diperbarui: 2026-09-03

---

## 1. Prinsip utama

- UI harus terasa sebagai aplikasi operasional sekolah, bukan admin panel generik.
- Bahasa di layar harus manusiawi dan berorientasi pekerjaan operator.
- Data sumber dari ARKAS/BKU harus dibedakan jelas dari data manual SPJ.
- Komponen visual yang sejenis wajib memakai pola, ukuran, spacing, dan hierarchy yang sama.
- Tailwind menangani visual, Alpine hanya interaksi UI ringan, Livewire menangani state/data reaktif, Laravel menangani route, auth, validation, dan aturan bisnis.
- Jangan membuat satu halaman menjadi satu card raksasa. Header + summary boleh satu card, sedangkan form, tabel, filter, dan detail harus tetap menjadi section/card terpisah.

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

Header halaman dan summary/statistik yang berkaitan langsung boleh digabung menjadi satu bordered card:

```text
┌─────────────────────────────────────┐
│ Kicker                              │
│ Judul halaman                       │
│ Deskripsi                    Action │
├─────────────────────────────────────┤
│ Stat 1 | Stat 2 | Stat 3 | Stat 4  │
└─────────────────────────────────────┘
```

Form, tabel, filter, atau detail yang tidak termasuk summary harus berada di card terpisah.

Komponen utama:

```text
<x-page-header>
<x-stat-item>
<x-section-card>
```

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
```

Standar visual:

- tinggi input medium sekitar 40–42 px;
- `text-sm` untuk kontrol utama;
- border slate yang jelas tetapi tidak berat;
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

Contoh pembagian form:

```text
Identitas utama
Kepegawaian / Sekolah & pendaftaran
Kontak & keluarga
Pajak & pembayaran / Informasi tambahan
```

Global fallback style tetap harus menjaga form lama agar konsisten sampai seluruh halaman selesai dimigrasikan ke komponen `x-ui.*`.

---

## 6. Detail Transaksi sebagai workspace operator

Detail transaksi bukan sekadar halaman detail. Halaman ini adalah ruang kerja operator.

Pemisahan utama wajib terlihat jelas:

### 6.1 Data ARKAS/BKU — readonly

Menampilkan data sumber sinkronisasi sebagai referensi resmi.

Contoh isi:

- nomor bukti;
- tanggal transaksi;
- penerima/penyedia sumber;
- kode kegiatan;
- kode rekening;
- bruto, pajak, neto;
- item hasil sinkronisasi;
- status sumber dan rekonsiliasi.

Operator tidak mengubah data sumber ini dari Detail Transaksi.

Visual harus memakai treatment readonly yang berbeda dari data editable.

### 6.2 Data SPJ Operator — editable

Menampilkan data yang memang harus dilengkapi operator:

- kategori SPJ;
- uraian pembayaran/dokumen;
- penerima kuitansi;
- metode pembayaran;
- referensi pembayaran;
- penandatangan;
- rincian item SPJ;
- detail kategori Barang, Konsumsi, Pemeliharaan, SPPD, Honor, atau Jasa.

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

## 7. Status manusiawi

Status teknis harus diterjemahkan menjadi istilah kerja operator.

Contoh:

```text
DRAFT          -> Belum lengkap
READY          -> Siap diproses
NUMBERED       -> Sudah bernomor
PRINTED        -> Sudah dicetak
FINAL          -> Final
CANCELLED      -> Dibatalkan
SOURCE_MISSING -> Tidak muncul di sinkronisasi
requires_reconciliation -> Perlu rekonsiliasi
```

Jangan menampilkan istilah teknis database bila tidak dibutuhkan pengguna.

---

## 8. Checklist UX sebelum sebuah halaman dianggap selesai

Sebuah halaman dianggap konsisten jika:

- tidak memiliki breadcrumb double;
- breadcrumb global tampil dan sticky;
- header + summary berada dalam satu card bila memang berkaitan;
- form/tabel lain tidak ikut terbungkus page header;
- input/select/textarea mengikuti standar Priority #3;
- action utama mudah ditemukan;
- readonly dan editable dapat dibedakan sekilas;
- status menggunakan bahasa operator;
- layout tetap nyaman pada mobile;
- tidak ada Alpine dan Livewire yang berebut state UI yang sama;
- perubahan visual tidak mengubah aturan bisnis tanpa alasan eksplisit.

---

## 9. Urutan prioritas GUI

Status roadmap GUI:

1. Sidebar persisten — diterapkan.
2. Header + summary + breadcrumb global — diterapkan dan terus diseragamkan.
3. Standardisasi Form & Input — diterapkan sebagai fondasi dan sedang dimigrasikan ke seluruh form.
4. Detail Transaksi sebagai workspace operator — sedang dikembangkan.
5. Status & badge manusiawi.
6. UI Rekonsiliasi.
7. Workflow SPJ & Penomoran.
8. Dashboard operasional.
9. Authorization/safety audit.
10. Lifecycle dokumen dan revisi.

---

## 10. Aturan untuk agent/coder berikutnya

Sebelum mengubah Blade, Livewire, CSS, atau layout:

1. baca `AGENTS.md`;
2. baca dokumen ini;
3. cek komponen UI yang sudah ada sebelum membuat komponen baru;
4. jangan menambah entry Vite CSS baru tanpa kebutuhan kuat;
5. jangan membuat breadcrumb lokal baru;
6. jangan membungkus seluruh `{{ $slot }}` dalam satu card global;
7. gunakan komponen `x-ui.*` untuk form baru atau form yang sedang disentuh;
8. bedakan data sumber readonly dan data operator editable;
9. lakukan perubahan dalam batch kecil dan cek browser setelah build;
10. jalankan `npm run build` setelah perubahan frontend dan test Laravel yang relevan setelah perubahan backend.
