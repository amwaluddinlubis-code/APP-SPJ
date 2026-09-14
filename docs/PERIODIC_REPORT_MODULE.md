# Modul Laporan Periodik SPJ

Status: **SOURCE IMPLEMENTED / TEMPLATE BINDING PENDING**  
Branch: `gui-standardization`

Dokumen ini mendefinisikan kontrak modul laporan periodik. Audit, koreksi, dan binding workbook/template resmi dilakukan terpisah dan tidak menjadi prasyarat untuk kontrak periode, daftar laporan, atau query sumber data.

## Integrasi UI

Modul laporan periodik dipisahkan dari **Laporan SPJ**. Sidebar **SPJ & Laporan** sekarang memiliki dua entry berbeda:

- **Laporan SPJ** → workspace riwayat/ekspor paket SPJ existing pada `/spj?tab=laporan`;
- **Laporan Periode** → parent menu khusus untuk modul laporan periodik.

Submenu **Laporan Periode**:

- Bulanan → `jenis_laporan=periode&paket_laporan=bulan`;
- Triwulan → `jenis_laporan=periode&paket_laporan=triwulan`;
- Semester → `jenis_laporan=periode&paket_laporan=semester`;
- Tahunan → `jenis_laporan=periode&paket_laporan=tahunan`.

Keduanya masih memakai route SPJ yang sama agar middleware dan konteks aktif tetap konsisten, tetapi surface Livewire-nya terpisah. `SpjReportFilter` memilih surface periodik hanya ketika `jenis_laporan=periode`; tanpa parameter tersebut, halaman tetap merender Laporan SPJ lama dan tidak memasang pusat laporan periodik.

Markup sidebar dipisahkan ke partial `resources/views/components/layouts/partials/spj-report-navigation.blade.php`. Alpine hanya mengelola buka/tutup parent **Laporan Periode**, sedangkan scope laporan tetap dimiliki state URL/Livewire `SpjPeriodicReportCenter` melalui parameter `paket_laporan`.

Pengguna memilih kelompok periode dan, bila diperlukan, nomor periode. Modul kemudian membaca transaksi dari `ActiveSpjContext`, sehingga batas sekolah aktif, tahun anggaran aktif, dan sumber dana aktif tetap berlaku.

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

## Arsitektur

- `App\Services\SpjPeriodicReportRegistry` adalah kontrak canonical untuk empat kelompok dan 39 slot laporan.
- `App\UseCases\Spj\SpjPeriodicReportUseCase` menangani boundary periode dan ringkasan sumber data transaksi.
- `App\Livewire\SpjPeriodicReportCenter` menangani state filter periode pada UI.
- `App\Livewire\SpjReportFilter` menjadi switch surface: Laporan SPJ existing atau Laporan Periode berdasarkan `jenis_laporan`.
- `resources/views/livewire/spj-periodic-report-page.blade.php` adalah surface khusus yang memasang `SpjPeriodicReportCenter`.
- `resources/views/livewire/spj-periodic-report-center.blade.php` merender pusat laporan dengan shared theme primitives.
- `resources/views/livewire/spj-report-filter.blade.php` kembali khusus untuk riwayat/ekspor Laporan SPJ dan tidak memasang pusat laporan periodik.
- `resources/views/components/layouts/partials/spj-report-navigation.blade.php` merender entry **Laporan SPJ** dan parent/submenu **Laporan Periode** tanpa mengambil alih state bisnis Livewire.

## Boundary periode

- Bulanan: satu bulan kalender pada tahun anggaran aktif.
- Tahap & Triwulan: 3 bulan per triwulan.
- Semester: 6 bulan per semester.
- Tahunan: 1 Januari sampai 31 Desember tahun anggaran aktif.

Query transaksi tetap dibatasi oleh `Transaction::forSpjContext()`, sehingga `fiscal_year_id` dan `fund_source_id` aktif tidak boleh bocor ke konteks lain.

## Ringkasan sumber data

Sebelum template resmi diikat, modul sudah menyediakan ringkasan periode:

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

Ringkasan ini adalah sumber data operasional, **bukan pengganti formula resmi masing-masing formulir**.

## Boundary template

Pada tahap ini modul tidak mengarang layout, rumus formulir, placeholder, atau export resmi untuk SPTJM/K7/SPB/SP2B/SP2T dan laporan lainnya. Template resmi akan diikat kemudian setelah template yang dipakai sekolah siap.

Karena itu UI boleh menyatakan sumber data tersedia, tetapi tidak boleh mengklaim dokumen resmi siap diunduh sebelum generator/template masing-masing laporan benar-benar dipasang dan diverifikasi.

## Regression contract

`SpjPeriodicReportRegistryTest` mengunci:

- empat kelompok laporan;
- nama laporan sesuai daftar canonical;
- total 39 slot dokumen;
- boundary periode 12/4/2/tahunan.

`SpjPeriodicReportModuleUiTest` mengunci:

- pusat laporan periodik berada pada surface khusus dan tidak lagi berada di view riwayat Laporan SPJ;
- template binding tetap dipisahkan dari kontrak sumber data;
- UI menggunakan shared theme primitives dan tidak menambah CSS lokal.

`SpjReportSidebarNavigationTest` mengunci:

- layout memakai partial sidebar laporan khusus;
- `Laporan SPJ` tetap entry mandiri;
- `Laporan Periode` menjadi parent mandiri;
- submenu Bulanan, Triwulan, Semester, dan Tahunan tersedia;
- link submenu meneruskan `tab=laporan`, `jenis_laporan=periode`, dan `paket_laporan` ke state URL Livewire.
