# Modul Laporan Periodik SPJ

Status: **SOURCE IMPLEMENTED / PRINT & PDF AVAILABLE / RUNTIME QA PENDING**  
Branch: `gui-standardization`

Dokumen ini mendefinisikan kontrak modul **Laporan Periode**. Modul ini dipisahkan dari **Laporan SPJ** dan memiliki generator internal APP-SPJ sendiri. Template Paket SPJ tidak menjadi dependency untuk laporan periodik.

## Integrasi UI

Sidebar **SPJ & Laporan** memiliki dua entry berbeda:

- **Laporan SPJ** → workspace riwayat/ekspor paket SPJ existing pada `/spj?tab=laporan`;
- **Laporan Periode** → parent menu khusus pada route `/laporan-periode` (`spj.periodic-reports.index`).

Submenu **Laporan Periode**:

- Bulanan → `/laporan-periode?paket_laporan=bulan`;
- Triwulan → `/laporan-periode?paket_laporan=triwulan`;
- Semester → `/laporan-periode?paket_laporan=semester`;
- Tahunan → `/laporan-periode?paket_laporan=tahunan`.

Halaman khusus `resources/views/periodic-reports/index.blade.php` langsung memasang `SpjPeriodicReportCenter`. Pengguna memilih kelompok dan nomor periode. Setelah periode valid, setiap dokumen menyediakan aksi **Cetak** dan **PDF**.

Route output:

```text
GET /laporan-periode/{scope}/{report}/cetak
GET /laporan-periode/{scope}/{report}/pdf
```

Keduanya tetap berada di middleware `auth + active-school + active-year + spj-active-context`, sehingga tenant boundary aktif tetap berlaku.

## Kelompok laporan

### Laporan Bulanan

1. SPTJM
2. Buku Kas Umum
3. Buku Pembantu Kas
4. Buku Pembantu Bank
5. Buku Pembantu Pajak
6. Register Penutupan Kas (K7B)
7. Berita Acara Pemeriksaan Kas (K7C)
8. BOS K7A
9. Rekap Belanja Modal dan Belanja Barang / Jasa

Periode: bulan 1–12.

### Laporan Tahap & Triwulan

1. SPTJM
2. BOS K7A
3. Format K7
4. Buku Kas Umum (BKU)
5. SPB
6. SP2B
7. Lampiran SP2B
8. SP2T
9. Berita Acara Rekonsiliasi
10. Lampiran Berita Acara Rekonsiliasi

Periode: Triwulan I–IV.

### Laporan Semester

1. SPTJM
2. BOS K7A
3. Format K7
4. Buku Kas Umum (BKU)
5. SPB
6. SP2B
7. Lampiran SP2B
8. SP2T
9. Berita Acara Rekonsiliasi
10. Lampiran Berita Acara Rekonsiliasi
11. Rekap Belanja Barang Milik Daerah (Aset)

Periode: Semester I–II.

### Laporan Tahunan

1. SPTJM
2. BOS K7A
3. Format K7
4. Buku Kas Umum (BKU)
5. Rekap Barang Milik Daerah (Aset)
6. BOS K8
7. Rekap Belanja Dana BOS
8. Form 1C
9. Rekapitulasi Pengeluaran Dana BOS

Periode: tahun anggaran aktif.

Total registry: **39 slot laporan** pada empat paket periode.

## Arsitektur

- `App\Services\SpjPeriodicReportRegistry` — kontrak canonical scope dan daftar 39 slot laporan.
- `App\UseCases\Spj\SpjPeriodicReportUseCase` — boundary periode, transaksi, dan summary sumber data.
- `App\Services\SpjPeriodicReportPrintService` — membangun payload dokumen cetak berdasarkan tipe laporan.
- `App\Http\Controllers\PeriodicReportController` — endpoint pratinjau cetak dan PDF.
- `App\Livewire\SpjPeriodicReportCenter` — state filter periode pada UI.
- `resources/views/livewire/spj-periodic-report-center.blade.php` — daftar dokumen + aksi Cetak/PDF.
- `resources/views/periodic-reports/print.blade.php` — surface browser print.
- `resources/views/periodic-reports/pdf.blade.php` — surface DomPDF.
- `resources/views/periodic-reports/partials/document.blade.php` — satu renderer dokumen untuk browser dan PDF.
- `resources/views/periodic-reports/partials/document-styles.blade.php` — aturan A4 portrait/landscape dan print pagination.

Business data tetap berada di use case/service; Blade hanya merender payload.

## Boundary periode

- Bulanan: satu bulan kalender pada tahun anggaran aktif.
- Tahap & Triwulan: 3 bulan per triwulan.
- Semester: 6 bulan per semester.
- Tahunan: 1 Januari sampai 31 Desember tahun anggaran aktif.

Query transaksi tetap dibatasi oleh `Transaction::forSpjContext()`, sehingga `fiscal_year_id` dan `fund_source_id` aktif tidak boleh bocor ke konteks lain. Sekolah aktif tetap ditentukan oleh koneksi tenant yang disiapkan middleware/context.

## Sumber data dan formula dasar

Summary canonical periode:

- jumlah transaksi;
- nilai bruto;
- total pajak;
- nilai dibayarkan;
- PPN;
- PPh 21;
- PPh 22;
- PPh 23;
- PPh 4(2);
- SSPD/Pajak Daerah.

Renderer kemudian memakai keluarga data berikut:

- **BKU** → seluruh transaksi periode;
- **Buku Pembantu Kas** → transaksi dengan `payment_method = tunai`;
- **Buku Pembantu Bank** → transaksi dengan `payment_method = transfer_bank` atau `siplah`;
- **Buku Pembantu Pajak** → transaksi dengan `tax_total > 0`, dirinci per jenis pajak;
- **K7A/K8** → rekap per kegiatan;
- **K7, rekap belanja, BMD, Form 1C, rekap tahunan** → rekap per rekening;
- **Lampiran SP2B / Lampiran BA Rekonsiliasi** → rincian transaksi;
- **SPTJM/K7B/K7C/SPB/SP2B/SP2T/BA Rekonsiliasi** → dokumen pernyataan/berita acara dengan summary dan rekap rekening pendukung.

Tidak ada saldo kas/bank fiktif yang dihitung bila source tidak menyediakannya. Laporan cetak secara eksplisit meminta operator mencocokkan saldo/bukti fisik/rekening koran sebelum penandatanganan.

## Output cetak

Browser print:

- A4 portrait untuk dokumen ringkas/rekap;
- A4 landscape untuk ledger, pajak, dan lampiran transaksi;
- header identitas sekolah;
- tahun anggaran + sumber dana;
- periode;
- summary bruto/pajak/netto;
- tabel detail sesuai keluarga laporan;
- area tanda tangan Kepala Sekolah dan Bendahara BOSP dari `school_profiles`;
- tombol `window.print()` pada pratinjau browser.

PDF memakai `barryvdh/laravel-dompdf` dan renderer dokumen yang sama supaya browser print dan PDF tidak mempunyai dua formula data yang berbeda.

## Boundary dengan Laporan SPJ

Perbaikan master/template Paket SPJ tetap menjadi pekerjaan terpisah. Perubahan template Surat Pesanan, Invoice, Kuitansi, BA, dan dokumen transaksi lain **tidak boleh** menjadi dependency Laporan Periode.

Sebaliknya, layout Laporan Periode dimiliki aplikasi dan dapat dikembangkan di `SpjPeriodicReportPrintService` + view `periodic-reports/*` tanpa mengubah template Paket SPJ.

## Verification

Regression source yang relevan:

- `SpjPeriodicReportRegistryTest` — empat kelompok, 39 slot, boundary periode;
- `SpjPeriodicReportModuleUiTest` — dedicated page, generator internal, shared theme primitives;
- `SpjPeriodicReportPrintableTest` — route print/PDF, action UI, shared renderer, coverage seluruh report key;
- `SpjReportSidebarNavigationTest` — Laporan SPJ dan Laporan Periode terpisah;
- `SpjReportLayoutTest` — filter/scope/report center contract.

Status **PRINT & PDF AVAILABLE** berarti source path untuk cetak/PDF sudah tersedia. Browser operator QA, real-data visual output, pagination panjang, dan PDF viewer verification tetap **RVR** sampai diuji pada runtime aktual dan CI head terbaru hijau.
