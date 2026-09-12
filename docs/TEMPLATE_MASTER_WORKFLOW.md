# Master Template Dokumen — Workflow Canonical

Terakhir diverifikasi: **2026-09-12** pada branch `gui-standardization`.

Dokumen ini menjelaskan lifecycle **Import Paket Template**, **update satu template**, **download template individu**, **Cek Placeholder**, dan **Unduh Master Template Terbaru**. Kontrak placeholder tetap berada di `DOCUMENT_TEMPLATE_PLACEHOLDERS.md`; status release/gate tetap berada di `CURRENT_PROGRESS.md`.

## 1. Prinsip sumber kebenaran

Master XLSX yang pernah di-upload **bukan file mutable yang terus ditimpa**. Source of truth runtime adalah record `DocumentTemplate` aktif untuk fiscal year aktif.

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

## 5. Cek Placeholder tanpa upload ulang

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

## 6. Unduh Master Template Terbaru

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

## 7. Larangan master parsial

Master terbaru **tidak boleh** dibuat jika satu atau lebih template XLSX canonical aktif tidak tersedia.

Alasannya: workbook hasil download harus tetap memenuhi kontrak paket dan dapat di-import kembali melalui jalur Import Paket Template.

Jika set template tidak lengkap, request ditolak dengan pesan yang menyebut document type XLSX yang belum tersedia.

Berkas source aktif juga wajib tersedia pada disk `local`. Missing source tidak boleh diganti dengan sheet kosong atau data buatan.

## 8. Scope dan boundary

Master export dan Cek Placeholder memakai context aktif. Template master dibatasi oleh fiscal year aktif; placeholder inspector juga menjaga fund-source scope Paket yang dicari.

Fitur ini:

- tidak mengubah ARKAS/BKU;
- tidak mengubah Paket SPJ;
- tidak mengubah numbering;
- tidak menerbitkan nomor;
- tidak mengubah lifecycle dokumen;
- tidak memutasi source template ketika download berlangsung.

## 9. Source implementation

Komponen utama:

```text
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

## 10. Regression contract

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

## 11. Batas evidence

Functional regression/CI membuktikan struktur source, workbook hasil single-download dapat dibaca ulang, worksheet lain benar-benar tidak ada pada OOXML hasil download, dan kontrak master dapat di-reimport. Evidence tersebut **tidak otomatis membuktikan visual fidelity di Microsoft Excel/LibreOffice atau hasil cetak**.

Master workbook hasil komposisi memakai PhpSpreadsheet sehingga official-template visual QA, formula lintas-sheet yang kompleks, drawing, print area, page breaks, header/footer, dan target Office viewer tetap mengikuti status RVR pada `CURRENT_PROGRESS.md`.

Untuk download individu, pruning OOXML menghindari rewrite worksheet terpilih, tetapi hasil aktual tetap perlu dibuka pada Microsoft Excel/LibreOffice bila template nyata memiliki drawing, formula/reference eksternal, defined name kompleks, atau fitur Office lain yang sensitif terhadap penghapusan worksheet.
