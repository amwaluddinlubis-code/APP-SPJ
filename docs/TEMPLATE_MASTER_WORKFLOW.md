# Master Template Dokumen — Workflow Canonical

Terakhir diverifikasi: **2026-09-15** pada branch `gui-standardization`.

Dokumen ini menjelaskan lifecycle **Import Paket Template**, **update satu template**, **download template individu**, **preview HTML/PDF dari Excel**, **Cek Placeholder**, **Unduh Master Template Terbaru**, dan pipeline render runtime yang menjaga fidelity workbook. Kontrak placeholder tetap berada di `DOCUMENT_TEMPLATE_PLACEHOLDERS.md`; status release/gate tetap berada di `CURRENT_PROGRESS.md`.

## 1. Prinsip sumber kebenaran

Master XLSX yang pernah di-upload **bukan file mutable yang terus ditimpa**. Source of truth runtime adalah record `DocumentTemplate` aktif untuk fiscal year aktif.

File template baru disimpan pada disk `local` dengan namespace sekolah berdasarkan `School.npsn`:

```text
document-templates/{npsn}/{fiscal_year_id}/...
```

Path lama tetap dapat dibaca untuk kompatibilitas template yang sudah tersimpan sebelum pemisahan namespace.

Untuk setiap document type canonical, aplikasi dapat mempunyai template aktif per format:

```text
fiscal_year_id
+ document_type
+ format
```

`DOCX` tetap template individu. Master workbook hanya dirakit dari template `XLSX` aktif.

## 2. Import Paket Template

Flow canonical:

```text
upload workbook master XLSX
→ validasi seluruh sheet canonical dari SpjDocumentTypeRegistry
→ registrasikan setiap document type sebagai template XLSX aktif
→ pertahankan source workbook secara aman pada storage canonical
```

Importer sengaja tidak memecah dan menulis ulang workbook kompleks secara destruktif. Source master dapat disimpan sebagai salinan penuh per record template agar relationship OOXML asli tidak rusak pada tahap import.

Independensi template berada pada **record document type**. Kontrak ini tidak berarti file source hasil import harus dipotong secara fisik menjadi satu sheet pada saat import.

## 3. Update satu template

Saat admin memilih **Tambah atau Ganti Satu Template**:

```text
pilih document type
→ validasi file DOCX/XLSX
→ replacement atomik record format tersebut
→ template lama baru dihapus setelah replacement database berhasil
```

Jika file yang diganti adalah XLSX, file baru tersebut langsung menjadi source untuk master terbaru berikutnya.

Contoh:

```text
KUITANSI_A2 v1
RINCIAN_BELANJA v1
BAP v1

upload RINCIAN_BELANJA v2

hasil source aktif:
KUITANSI_A2 v1
RINCIAN_BELANJA v2
BAP v1
```

Tidak ada proses menulis balik ke master lama pada saat upload individu.

## 4. Download template individu

Aksi **Download Template** pada daftar template harus benar-benar menghasilkan template individu.

Untuk XLSX, definisi **individu** adalah:

```text
workbook hasil download = tepat 1 worksheet fisik
worksheet tersebut      = sheet canonical document type yang dipilih
sheet document lain     = tidak ada di workbook hasil download
source/master tersimpan = tidak berubah
```

Aplikasi membuat copy sementara dari source XLSX lalu melakukan pruning OOXML pada copy tersebut. Metadata `<sheet>`, relationship workbook, dan part worksheet milik sheet lain dibuang dari file download. Sheet lain **tidak boleh** sekadar disembunyikan dengan `hidden` atau `veryHidden` dan kemudian disebut sebagai single template.

Pruning dilakukan langsung pada paket OOXML agar worksheet terpilih tidak perlu di-load lalu ditulis ulang seluruhnya dengan PhpSpreadsheet. Tujuannya adalah mempertahankan isi/relationship sheet terpilih sebanyak mungkin sekaligus benar-benar menghapus worksheet lain dari workbook hasil download.

Jika source memang sudah merupakan workbook satu-sheet, aplikasi dapat mengunduh source tersebut langsung tanpa membuat copy sementara yang tidak diperlukan.

## 5. Preview HTML/PDF dari source Excel

Preview untuk template XLSX harus berasal dari **workbook Excel aktif yang sama** dengan generator, setelah placeholder, repeating row, merged-anchor, dan KOP diproses. Preview tidak mempunyai template HTML/PDF kedua yang menjadi source dokumen.
### Pemilihan dokumen paket berdasarkan kategori

Setiap operasi Paket SPJ memilih template dengan aturan yang sama:

```text
tahun anggaran Paket
→ template aktif
→ pemetaan kategori SPJ template
→ format XLSX
→ satu worksheet canonical per document type
```

Template yang tidak dipetakan ke kategori transaksi tidak boleh dimuat atau di-render. Jika hanya satu template XLSX yang sesuai, Preview Paket, Arsip Excel Paket, dan Arsip Paket PDF hanya menghasilkan satu worksheet/dokumen. Template DOCX tetap tersedia melalui aksi dokumen individual dan bukan bagian dari workbook/PDF paket Excel.

Preview HTML untuk template XLSX harus berasal dari **workbook Excel aktif yang sama** dengan generator, setelah placeholder/repeating row/kop diproses. Preview tidak mempunyai template HTML kedua yang menjadi source dokumen.

Untuk source workbook multi-sheet hasil import master, pemilihan worksheet preview mengikuti `SpjDocumentTypeRegistry`:

```text
document_type aktif
→ canonical document type
→ nama worksheet canonical registry
→ cari worksheet tersebut pada workbook Excel
→ render worksheet canonical
```

Kontrak wajib:

- preview **tidak boleh** memilih worksheet hanya karena berada pada index `0` / sheet pertama;
- pencocokan nama sheet canonical bersifat case-insensitive;
- sheet teknis seperti `PLACEHOLDER_MAP` tidak boleh dipilih sebagai fallback dokumen;
- bila nama canonical tidak ditemukan tetapi hanya ada tepat satu worksheet non-teknis, worksheet tersebut boleh dipakai sebagai fallback kompatibilitas template individu/legacy;
- bila source multi-sheet ambigu dan sheet canonical tidak ditemukan, preview harus gagal dengan pesan yang jelas daripada menampilkan worksheet yang salah;
- preview paket memakai resolver worksheet canonical yang sama untuk setiap template XLSX di dalam paket;
- preview PDF, download PDF, dan download Excel harus memakai workbook canonical yang sama setelah pengisian data.

Kontrak ini sengaja dibedakan dari **Download Template** pada halaman pengaturan: download individu menghasilkan workbook satu-sheet fisik, sedangkan preview runtime boleh membaca source workbook multi-sheet tersimpan tetapi hanya merender worksheet canonical milik `document_type` yang sedang dipreview.

### 5.1 Pipeline render in-memory

Runtime XLSX memakai prinsip **load sekali, mutasi in-memory, save artifact akhir**. Normalisasi merged-cell tidak lagi membuat XLSX perantara sebelum generator utama.

Flow canonical:

```text
source/master aktif
→ IOFactory::load()
→ resolve worksheet canonical
→ normalisasi placeholder merged-cell pada worksheet in-memory
→ ekspansi repeating rows
→ isi scalar/KOP/header-footer
→ prune worksheet lain untuk output individual
→ save artifact akhir
→ Excel / Preview PDF / Download PDF
```

Excel hanya menampilkan nilai pada cell anchor (kiri-atas) sebuah merged range. Jika source menyimpan placeholder tambahan pada cell non-anchor sementara anchor juga merupakan placeholder, anchor adalah sumber kebenaran visual. Placeholder non-anchor dibersihkan **pada object worksheet in-memory**, bukan dengan menulis ulang master template.

### 5.2 Repeating row engine

Placeholder `ITEM_*` dan `UPAH_*` memakai `SpjRepeatingRowRenderer`. Renderer mencari row template berdasarkan placeholder anchor, memakai row yang sudah dipre-alokasikan terlebih dahulu, lalu hanya menambah row jika jumlah record melebihi kapasitas template.

Untuk row tambahan, engine menyalin struktur row template yang relevan, termasuk:

- cell value/template marker;
- style per cell;
- row height/visibility/outline/collapsed/zero-height;
- horizontal merged range pada row template;
- formula dengan penyesuaian relative row reference;
- data validation pada cell template.

Engine tidak melakukan ekspansi kolom 2D generik. Template SPJ tetap template-layout-driven; penambahan baris hanya dilakukan pada block yang mempunyai anchor repeating row resmi.

## 6. Cek Placeholder tanpa upload ulang

Halaman **Pengaturan → Template Dokumen** menyediakan aksi **Cek Placeholder** untuk membantu perbaikan template tanpa siklus upload berulang.

Flow:

```text
buka Cek Placeholder
→ masukkan Nomor Dokumen/SPJ atau No. Bukti
→ AJAX lookup read-only pada context aktif
→ resolve nilai memakai resolver generator yang sama
→ tampilkan placeholder + nilai aktual per kelompok
```

Lookup dapat menemukan Paket melalui nomor SPJ/Paket, nomor dokumen turunan yang sudah diterbitkan, atau No. Bukti. Scope tetap dibatasi oleh School + Fiscal Year + Fund Source aktif.

Fitur ini read-only: tidak menerbitkan nomor, tidak mengubah Paket SPJ, dan tidak memutasi ARKAS/BKU maupun template.

## 7. Unduh Master Template Terbaru

Aksi **Unduh Master Template Terbaru** membangun workbook baru saat request dijalankan.

Flow:

```text
ambil seluruh document type canonical dari SpjDocumentTypeRegistry
→ ambil template XLSX aktif setiap document type pada fiscal year aktif
→ pilih sheet canonical dari tiap source
→ jika source hanya mempunyai satu sheet non-teknis, gunakan sebagai fallback
→ normalisasi nama sheet ke nama canonical registry
→ gabungkan satu sheet canonical per document type
→ tulis workbook sementara
→ validasi ulang workbook memakai SpjTemplatePackageImporter::validatePackage()
→ kirim MASTER-TEMPLATE-SPJ-TERBARU.xlsx
→ hapus file sementara setelah response
```

Dengan kontrak ini, update satu template otomatis tercermin pada master download berikutnya tanpa memodifikasi file master historis.

## 8. Larangan master parsial

Master terbaru **tidak boleh** dibuat jika satu atau lebih template XLSX canonical aktif tidak tersedia.

Alasannya: workbook hasil download harus tetap memenuhi kontrak paket dan dapat di-import kembali melalui jalur Import Paket Template.

Jika set template tidak lengkap, request ditolak dengan pesan yang menyebut document type XLSX yang belum tersedia.

Berkas source aktif juga wajib tersedia pada disk `local`. Missing source tidak boleh diganti dengan sheet kosong atau data buatan.

## 9. Scope dan boundary

Master export dan Cek Placeholder memakai context aktif. Template master dibatasi oleh fiscal year aktif; placeholder inspector juga menjaga fund-source scope Paket yang dicari.

Fitur ini:

- tidak mengubah ARKAS/BKU;
- tidak mengubah Paket SPJ;
- tidak mengubah numbering;
- tidak menerbitkan nomor;
- tidak mengubah lifecycle dokumen;
- tidak memutasi source template ketika download atau preview berlangsung.

## 10. Source implementation

Komponen utama:

```text
app/Services/SpjTemplateService.php
app/Services/ExtendedSpjTemplateService.php
app/Services/PreviewAlignedSpjTemplateService.php
app/Services/SpjRepeatingRowRenderer.php
app/Services/DocumentTemplateMasterExportService.php
app/Services/DocumentTemplateReplacementService.php
app/Services/SpjTemplatePackageImporter.php
app/Services/DocumentTemplateIndividualDownloadService.php
app/Services/DocumentTemplatePlaceholderInspectorService.php
app/Http/Controllers/DocumentTemplateController.php
resources/views/document-templates/index.blade.php
resources/views/document-templates/_placeholder-checker-modal.blade.php
```

Route master:

```text
GET /pengaturan/template-dokumen/master/unduh
document-templates.master.download
```

## 11. Regression contract

`tests/Unit/SpjTemplateWorkbookPreservationTest.php` mengunci behavior berikut:

1. pruning worksheet canonical mempertahankan page setup, print area, margin, dimensions, merge, font/style, dan header/footer yang diuji;
2. merged placeholder yang setara dapat di-resolve pada anchor tanpa membongkar merge;
3. conflict langsung pada resolver tetap ditolak bila normalisasi anchor tidak dijalankan;
4. pipeline canonical membersihkan placeholder non-anchor in-memory sebelum resolution sehingga anchor menjadi sumber kebenaran visual;
5. repeating row renderer menggandakan merge horizontal, formula relative, style, dan row dimension;
6. repeating row renderer memakai row template preallocated lebih dulu dan membersihkan marker yang tidak terpakai.

`tests/Feature/SpjPreviewExcelParityTest.php` mengunci bahwa preview XLSX dan download paket Excel menggunakan workbook canonical yang sama untuk sheet, print area, orientation, margin, dan nilai placeholder yang diuji.

`tests/Unit/SpjTemplateHtmlPreviewTest.php` mengunci behavior berikut:

1. source workbook multi-sheet tidak boleh membuat preview memakai sheet pertama secara otomatis;
2. preview memilih worksheet canonical sesuai `document_type` / `SpjDocumentTypeRegistry` walaupun worksheet tersebut berada pada posisi kedua atau berikutnya;
3. jika nama canonical tidak tersedia, tepat satu worksheet non-teknis dapat menjadi fallback;
4. sheet teknis tidak boleh dipilih sebagai fallback preview.

`tests/Feature/DocumentTemplateIndividualDownloadTest.php` mengunci behavior berikut:

1. source multi-sheet menghasilkan file download dengan `getSheetCount() === 1`;
2. satu-satunya worksheet adalah sheet canonical document type terpilih;
3. part worksheet lain benar-benar hilang dari ZIP OOXML, bukan sekadar hidden/veryHidden;
4. source/master tersimpan tetap utuh dan tidak dimutasi;
5. source yang memang satu-sheet tidak membuat temporary copy yang tidak diperlukan.

`tests/Feature/DocumentTemplateMasterExportTest.php` mengunci behavior berikut:

1. master menggunakan template XLSX aktif terbaru untuk setiap document type;
2. template yang diperbarui secara individu menggantikan versi lama pada master hasil rakitan;
3. template lain tetap berasal dari versi aktif masing-masing;
4. nama sheet output mengikuti registry canonical;
5. workbook hasil export lolos validasi paket canonical;
6. master parsial ditolak bila satu document type XLSX aktif hilang.

`tests/Feature/DocumentTemplatePlaceholderInspectorTest.php` mengunci lookup placeholder actual-value, pencarian melalui nomor Paket/dokumen/No. Bukti, dan isolasi Fund Source.

## 12. Batas evidence

Functional regression/CI membuktikan pemilihan worksheet canonical, struktur source, workbook hasil single-download dapat dibaca ulang, dan kontrak yang diuji secara deterministik. Evidence tersebut **tidak otomatis membuktikan visual fidelity di Microsoft Excel/LibreOffice, browser terhadap seluruh fitur Excel, atau hasil cetak**.

HTML preview memakai renderer PhpSpreadsheet. Formula/drawing/print-layout yang bergantung pada implementasi Office dapat berbeda dari Microsoft Excel. Preview dipakai sebagai representasi workbook canonical, bukan bukti pixel-perfect terhadap Excel.

Master workbook hasil komposisi memakai PhpSpreadsheet sehingga official-template visual QA, formula lintas-sheet yang kompleks, drawing, print area, page breaks, header/footer, dan target Office viewer tetap mengikuti status RVR pada `CURRENT_PROGRESS.md`.

Untuk PDF dari workbook, generator terlebih dahulu menyimpan workbook sementara lalu mencoba konversi native melalui LibreOffice (`soffice`/`libreoffice`, atau path dari `SPJ_LIBREOFFICE_BINARY`). `SPJ_LIBREOFFICE_BINARY` menerima path file lengkap (`C:/Program Files/LibreOffice/program/soffice.exe`) maupun direktori instalasi. Jalur ini mempertahankan font, print area, page setup, scaling, merge, row/column dimension, page break, header/footer, dan drawing sesuai konfigurasi Excel. Jika LibreOffice tidak tersedia, generator memakai fallback Dompdf dengan normalisasi print area dan registrasi font sistem; fallback tetap fungsional tetapi tidak dapat dijadikan bukti pixel-perfect terhadap Excel/Office.

Halaman pratinjau tidak menjalankan preflight download dan tidak me-render PDF/HTML dua kali: readiness PDF dicek murah (`xlsx` siap, `docx` butuh LibreOffice), HTML hanya dihitung bila PDF tidak siap, dan PDF paket/template di-render lazy melalui endpoint `pratinjau-pdf`. Kegagalan render pratinjau dikembalikan sebagai redirect dengan pesan error, bukan 500.

Kontrak peran: pratinjau PDF adalah cetakan resmi SPJ (sahih penuh setelah nomor terbit melalui numbering; halaman memberi peringatan bila nomor belum terbit), sedangkan unduhan Excel/PDF hanya untuk arsip dan tetap tersimpan ke folder dokumen.

Untuk download individu, pruning OOXML menghindari rewrite worksheet terpilih, tetapi hasil aktual tetap perlu dibuka pada Microsoft Excel/LibreOffice bila template nyata memiliki drawing, formula/reference eksternal, defined name kompleks, atau fitur Office lain yang sensitif terhadap penghapusan worksheet.
