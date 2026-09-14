# SPJ Periodic Reporting

Dokumen ini adalah kontrak teknis modul **Laporan Pertanggungjawaban Periodik** pada workspace SPJ. Modul dipisahkan dari workbook/template dokumen agar daftar laporan, filter periode, dan boundary data dapat stabil walaupun template resmi masih diperbaiki.

## Prinsip

1. Semua sumber data wajib dibatasi oleh `ActiveSpjContext`: sekolah aktif, tahun anggaran aktif, dan sumber dana aktif.
2. Penentuan periode menggunakan `transaction_date` dari transaksi yang sudah berada pada konteks aktif.
3. Registry daftar laporan berada di `App\Services\SpjPeriodicReportRegistry` dan menjadi satu-satunya sumber daftar paket laporan pada UI.
4. Query/ringkasan sumber data berada di `App\UseCases\Spj\SpjPeriodicReportUseCase`.
5. Livewire `SpjPeriodicReportCenter` hanya mengelola state UI (jenis periode dan nomor periode), lalu mendelegasikan perhitungan ke use case.
6. Template, formula khusus formulir, layout cetak, dan placeholder resmi **tidak didefinisikan di registry**. Lapisan tersebut dapat dipasang kemudian tanpa mengubah kontrak modul.

## Paket laporan

### Bulanan

- SPTJM
- Buku Kas Umum
- Buku Pembantu Kas
- Buku Pembantu Bank
- Buku Pembantu Pajak
- Register Penutupan Kas (K7B)
- Berita Acara Pemeriksaan Kas (K7C)
- BOS K7A
- Rekap Belanja Modal dan Belanja Barang / Jasa

Periode valid: bulan `1..12`.

### Tahap & Triwulan

- SPTJM
- BOS K7A
- Format K7
- Buku Kas Umum (BKU)
- SPB
- SP2B
- Lampiran SP2B
- SP2T
- Berita Acara Rekonsiliasi
- Lampiran Berita Acara Rekonsiliasi

Periode valid: triwulan `1..4`.

### Semester

- SPTJM
- BOS K7A
- Format K7
- Buku Kas Umum (BKU)
- SPB
- SP2B
- Lampiran SP2B
- SP2T
- Berita Acara Rekonsiliasi
- Lampiran Berita Acara Rekonsiliasi
- Rekap Belanja Barang Milik Daerah (Aset)

Periode valid: semester `1..2`.

### Tahunan

- SPTJM
- BOS K7A
- Format K7
- Buku Kas Umum (BKU)
- Rekap Barang Milik Daerah (Aset)
- BOS K8
- Rekap Belanja Dana BOS
- Form 1C
- Rekapitulasi Pengeluaran Dana BOS

Periode tahunan selalu menggunakan seluruh tahun anggaran aktif dan tidak membutuhkan nomor periode tambahan.

## Data engine

Untuk periode yang sudah valid, modul menghitung ringkasan sumber data berikut langsung dari transaksi pada konteks aktif:

- jumlah transaksi;
- nilai bruto;
- total pajak;
- nilai dibayarkan/neto;
- PPN;
- PPh 21;
- PPh 22;
- PPh 23;
- PPh 4(2);
- SSPD/Pajak Daerah.

Ringkasan ini adalah **lapisan sumber data**, bukan pengganti rumus atau bentuk resmi masing-masing laporan. Misalnya aturan kolom dan saldo Buku Kas Umum, struktur SP2B, atau klasifikasi BMD harus tetap mengikuti kontrak template/formula laporan yang disepakati kemudian.

## Integrasi UI

`resources/views/livewire/spj-report-filter.blade.php` menanam `SpjPeriodicReportCenter` di atas riwayat paket SPJ. Dengan demikian halaman `/spj?tab=laporan` mempunyai dua fungsi yang terpisah:

1. **Paket laporan periodik** — memilih Bulanan/Triwulan/Semester/Tahunan dan melihat kesiapan sumber data.
2. **Riwayat paket SPJ** — fungsi lama untuk memfilter dan mengekspor paket SPJ per transaksi.

Keduanya tidak saling menimpa query atau URL state. State paket laporan menggunakan `paket_laporan` dan `periode_laporan`, sedangkan riwayat paket tetap menggunakan `mode` dan `periode`.

## Tahap berikutnya

Saat template laporan sudah siap, implementasi berikutnya adalah membuat binding `report key -> template`, resolver placeholder/report rows, lalu jalur preview/download PDF/XLSX. Binding tersebut harus memakai registry dan period use case yang sudah ada; jangan membuat ulang logika periode di controller atau template renderer.
