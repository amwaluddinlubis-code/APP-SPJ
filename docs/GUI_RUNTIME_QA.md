# GUI Runtime QA — Desktop, Laptop, Mobile, dan Tablet

Terakhir diperbarui: **2026-09-11**

Dokumen ini adalah checklist runtime untuk menutup **GUI-AUDIT-12** dan **GUI-AUDIT-13**. Source-level regression dan CI tidak boleh dipakai sebagai pengganti verifikasi visual/runtime di browser.

## Status saat ini

```text
GUI-AUDIT-12 source readiness : PASS
GUI-AUDIT-12 browser runtime  : RVR
GUI-AUDIT-13 source readiness : PASS
GUI-AUDIT-13 mobile/tablet     : RVR
```

Evidence source-readiness terakhir:

```text
code gate : 8180d566621dcf7b1a84b9183a666f2f7a64b398
CI run    : 34613781674 (#326)
CI job    : 103310602936
result    : PASS — 261 tests / 1996 assertions
```

Guard terkait:

```text
tests/Feature/GuiAudit09To13SourceReadinessTest.php
```

Guard tersebut membuktikan kontrak source responsive/table/icon tertentu tetap ada. Guard **tidak** membuktikan layout benar-benar tampil tanpa overlap, clipping, overflow viewport, atau masalah interaksi pada browser nyata.

## 1. Aturan evidence runtime

Setiap sesi QA minimal mencatat:

```text
Commit SHA:
Browser + versi:
Viewport:
Role user:
Sekolah aktif:
Tahun anggaran:
Sumber dana:
Route:
Status: PASS / BLOCKED / RVR
Catatan:
Screenshot/reference bila ada:
```

Jangan memberi status PASS berdasarkan inspeksi Blade saja.

## 2. Matrix viewport desktop/laptop — GUI-AUDIT-12

Wajib diuji minimal:

| Viewport | Target |
| --- | --- |
| 1366 × 768 | laptop umum |
| 1440 × 900 | laptop/desktop medium |
| 1920 × 1080 | desktop lebar |

Pemeriksaan umum:

- header dan breadcrumb sticky tidak saling menutupi;
- page header/action tetap terbaca;
- form tidak terpotong;
- tombol utama/secondary/danger terlihat dan dapat diklik;
- modal muat dalam viewport dan dapat discroll bila tinggi;
- dropdown/menu tidak terpotong container;
- tabel lebar scroll horizontal hanya di container tabel, bukan seluruh halaman;
- tidak ada pagination/filter ganda;
- sticky action tidak menutup field terakhir;
- focus/hover/disabled state jelas;
- theme token tidak menghasilkan foreground/background berkontras buruk;
- icon canonical sejajar dengan label dan tidak menggeser row height berlebihan.

## 3. Matrix mobile/tablet — GUI-AUDIT-13

Minimum usability diuji pada:

| Viewport | Target |
| --- | --- |
| 375 × 812 | mobile portrait |
| 768 × 1024 | tablet portrait |
| 1024 × 768 | tablet landscape |

Pemeriksaan umum:

- tidak ada horizontal viewport overflow kecuali container tabel yang memang scrollable;
- daftar yang menyediakan desktop table + mobile card benar-benar berpindah pada breakpoint yang sesuai;
- action penting tetap dapat ditemukan tanpa hover;
- tab/navigation dapat dibaca atau discroll tanpa memotong label;
- modal tidak keluar viewport;
- form field, textarea, select, radio, dan checkbox dapat digunakan dengan sentuhan;
- label dan validation message tidak tertutup;
- text panjang/path/database identifier wrap atau scroll secara aman;
- tombol tidak bertumpuk atau terpotong;
- sticky header/action tidak menghabiskan terlalu banyak tinggi layar.

Mobile/tablet masih non-blocker untuk target release desktop/laptop saat ini, tetapi minimum usability tetap harus dicatat dan blocker serius tidak boleh diabaikan.

## 4. Route minimum yang harus diperiksa

### Core operator

```text
/dashboard
/transaksi atau route daftar transaksi aktif
Detail Transaksi
/spj?tab=paket
/spj/penomoran
/spj/penomoran/koreksi
```

Khusus Paket SPJ:

- tab utama Persiapan/Paket/Laporan/Monitoring memakai icon canonical;
- toolbar Semua Paket / Sebelumnya / Setelahnya / Lihat Transaksi tetap usable;
- sub-tab Rincian / Isian Manual / Rincian Pajak / Penomoran tidak overflow;
- `NUMBERED` masih mengizinkan koreksi `item_description` melalui Detail Transaksi;
- `FINAL` tetap terkunci;
- Data Umum desktop tetap dua kolom sesuai kontrak;
- tabel kategori non-BARANG tetap compact dan hanya mempunyai satu pager.

### Master dan sinkronisasi

```text
Master Siswa index/form/show
Master Pegawai index/form/show
Data Hasil Sinkron
Integrasi Dapodik
ARKAS Importer / settings terkait
```

Verifikasi khusus:

- table/card responsive fallback Siswa dan Pegawai;
- masking identitas tetap benar;
- filter/per-page tidak pecah pada width kecil;
- tabel Data Hasil Sinkron tidak mendorong seluruh viewport secara horizontal.

### Admin/settings

```text
Laporan Audit
Template Dokumen
Format Penomoran
Database Manager
Reset Database Sekolah
Backup & Pemulihan
Pengguna & Hak Akses
Impersonation
```

Reset Database wajib diperiksa untuk:

- danger zone tetap jelas;
- warning icon canonical tampil;
- konfirmasi reset tidak terpotong;
- tombol Reset / Batal / Backup & Pemulihan tetap terlihat;
- semantic danger tidak terganggu oleh theme.

## 5. Kriteria penutupan GUI-AUDIT-12

GUI-AUDIT-12 hanya boleh menjadi **PASS** bila:

1. seluruh viewport desktop minimum sudah diuji pada runtime lokal/installed build;
2. route core operator tidak mempunyai blocker visual/interaksi;
3. issue yang ditemukan sudah diperbaiki atau secara eksplisit dicatat sebagai non-blocking dengan alasan;
4. evidence commit/browser/viewport dicatat;
5. `docs/CURRENT_PROGRESS.md` diperbarui berdasarkan evidence nyata.

## 6. Kriteria penutupan GUI-AUDIT-13

GUI-AUDIT-13 hanya boleh menjadi **PASS** bila:

1. tiga viewport mobile/tablet minimum sudah diuji;
2. core flow dapat dinavigasi tanpa clipping/overflow fatal;
3. input dan action penting dapat digunakan tanpa hover-only interaction;
4. blocker responsive telah diperbaiki atau dicatat dengan keputusan release yang eksplisit;
5. evidence runtime dicatat.

## 7. Hubungan dengan CI

CI menjaga kontrak source, build, Blade compile, dan regression. CI tidak dapat membuktikan persepsi visual atau usability browser pada aplikasi lokal. Karena itu status canonical adalah:

```text
source-readiness PASS + runtime belum diuji = RVR
source-readiness PASS + runtime evidence lengkap tanpa blocker = PASS
```

Jangan mengubah aturan ini hanya untuk menutup checklist lebih cepat.
