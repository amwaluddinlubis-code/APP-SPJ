<?php

use App\Models\DocumentTemplate;
use App\Models\FiscalYear;
use App\Models\School;
use App\Services\SchoolDatabaseManager;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$school = School::query()->where('npsn', '10208183')->firstOrFail();
app(SchoolDatabaseManager::class)->activate($school);
$year = FiscalYear::query()->orderByDesc('year')->firstOrFail();
$source = __DIR__.'/../public/assets/TPL_DESIGN.xlsx';
$targetBase = 'document-templates/'.$year->id.'/excel-design';
File::ensureDirectoryExists(storage_path('app/'.$targetBase));

$map = [
    'TPL_COVER_SPJ' => ['COVER_SPJ', 'Cover SPJ'], 'TPL_CHECKLIST_SPJ' => ['CHECKLIST', 'Checklist SPJ'],
    'TPL_KUITANSI' => ['KUITANSI', 'Kuitansi Pembayaran'], 'TPL_RINCIAN' => ['RINCIAN_BELANJA', 'Rincian Belanja'],
    'TPL_REKAP_PAJAK' => ['REKAP_PAJAK', 'Rekap Pajak'], 'TPL_SURAT_PESANAN' => ['SURAT_PESANAN', 'Surat Pesanan'],
    'TPL_BA_PEMERIKSAAN' => ['BA_PEMERIKSAAN', 'Berita Acara Pemeriksaan'], 'TPL_BA_SERAH_TERIMA' => ['BA_SERAH_TERIMA', 'Berita Acara Serah Terima'],
    'TPL_INVOICE' => ['INVOICE_PESANAN', 'Invoice / Pesanan'], 'TPL_RAB_PEMELIHARAAN' => ['RAB_PEMELIHARAAN', 'RAB Pemeliharaan'],
    'TPL_SPK_PEMELIHARAAN' => ['SPK_PEMELIHARAAN', 'SPK Pemeliharaan'],
];
$aliases = [
    'NOMOR_DOKUMEN' => 'NOMOR_SPJ', 'NOMOR BUKTI' => 'NO_BUKTI', 'NOMOR_BUKTI' => 'NO_BUKTI', 'TANGGAL_DOKUMEN' => 'TANGGAL_TRANSAKSI', 'TANGGAL TRANSAKSI' => 'TANGGAL_TRANSAKSI', 'SUMBER_DANA_PERIODE' => 'SUMBER_DANA_PERIODE', 'JENIS_SPJ' => 'JENIS_SPJ', 'NILAI_BRUTO' => 'NILAI_BRUTO', 'POTONGAN_PAJAK' => 'POTONGAN_PAJAK', 'NILAI DIBAYARKAN' => 'NILAI_DIBAYARKAN', 'NILAI_DIBAYARKAN' => 'NILAI_DIBAYARKAN', 'NAMA_PENERIMA' => 'NAMA_PENERIMA', 'PENERIMA_PENYEDIA' => 'PENERIMA_PENYEDIA', 'NAMA_PENYEDIA' => 'NAMA_PENYEDIA', 'UNTUK_PEMBAYARAN' => 'UNTUK_PEMBAYARAN', 'CARA_BAYAR_REFERENSI' => 'CARA_BAYAR_REFERENSI', 'KODE_KEGIATAN' => 'KODE_KEGIATAN', 'NAMA_KEGIATAN' => 'NAMA_KEGIATAN', 'KODE_REKENING' => 'KODE_REKENING', 'NAMA_REKENING' => 'NAMA_REKENING', 'URAIAN_TRANSAKSI' => 'URAIAN_TRANSAKSI', 'URAIAN_PEKERJAAN' => 'URAIAN_PEKERJAAN', 'NOMOR_SPK' => 'NOMOR_SPK', 'TANGGAL_SPK' => 'TANGGAL_SPK', 'TANGGAL_RAB' => 'TANGGAL_RAB', 'NAMA_BENDAHARA_BOSP' => 'NAMA_BENDAHARA_BOSP', 'NIP_BENDAHARA_BOSP' => 'NIP_BENDAHARA_BOSP', 'NAMA_KEPALA_SEKOLAH' => 'NAMA_KEPALA_SEKOLAH', 'NIP_KEPALA_SEKOLAH' => 'NIP_KEPALA_SEKOLAH', 'NAMA_KEPALA_SATUAN_PENDIDKAN' => 'NAMA_KEPALA_SATUAN_PENDIDIKAN', 'NIP_KEPALA_SATUAN_PENDIDKAN' => 'NIP_KEPALA_SATUAN_PENDIDIKAN', 'NAMA_KEPALA_SATUAN_PENDIDIKAN' => 'NAMA_KEPALA_SATUAN_PENDIDIKAN', 'NIP_KEPALA_SATUAN_PENDIDIKAN' => 'NIP_KEPALA_SATUAN_PENDIDIKAN', 'NAMA_SATUAN_PENDIDIKAN' => 'NAMA_SATUAN_PENDIDIKAN', 'KOP_SURAT' => 'KOP_SURAT', 'TERBILANG_NETO' => 'TERBILANG_NETO', 'NILAI_PEKERJAAN' => 'NILAI_PEKERJAAN', 'NILAI_PEKERJAAN_TERBILANG' => 'NILAI_PEKERJAAN_TERBILANG', 'LOKASI PEKERJAAN' => 'LOKASI_PEKERJAAN', 'TANGGAL_MULAI' => 'TANGGAL_MULAI', 'TANGGAL_SELESAI' => 'TANGGAL_SELESAI', 'TANGGAL_PENYERAHAN' => 'TANGGAL_PENYERAHAN', 'TEMPAT PENYERAHAN' => 'TEMPAT_PENYERAHAN', 'TANGGAL TANDA TANGAN' => 'TANGGAL_TANDA_TANGAN', 'KECAMATAN' => 'KECAMATAN',
];
$normalise = static function (string $value) use ($aliases): string {
    if (str_starts_with($value, '=')) {
        return $value;
    }

    return preg_replace_callback('/(?:\{\{|\{|\[)\s*([^}\]]+?)\s*(?:\}\}|\}|\])/', static function ($matches) use ($aliases) {
        $token = strtoupper(trim(preg_replace('/\s+/', ' ', $matches[1])));

        return isset($aliases[$token]) ? '{{'.$aliases[$token].'}}' : $matches[0];
    }, $value);
};
$updated = [];
$skipped = [];
foreach ($map as $sheetName => [$type, $label]) {
    // Memulai dari workbook asli mempertahankan seluruh referensi style, gambar,
    // merge cell, page setup, dan print area milik desain Excel.
    $book = IOFactory::load($source);
    $sheet = $book->getSheetByName($sheetName);
    if (! $sheet) {
        $skipped[] = $sheetName.' (tidak ditemukan)';

        continue;
    }
    foreach (array_reverse(range(0, $book->getSheetCount() - 1)) as $index) {
        if ($book->getSheet($index)->getTitle() !== $sheetName) {
            $book->removeSheetByIndex($index);
        }
    }
    foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
        $value = $sheet->getCell($coordinate)->getValue();
        if (is_string($value)) {
            $sheet->setCellValue($coordinate, $normalise($value));
        }
    }
    if ($sheetName === 'TPL_KUITANSI') {
        $sheet->setCellValue('F10', '{{NAMA_KEGIATAN}}');
        $sheet->setCellValue('F12', '{{NAMA_REKENING}}');
    }
    if ($sheetName === 'TPL_RINCIAN') {
        for ($row = 13; $row <= 17; $row++) {
            $sheet->setCellValue('B'.$row, '{{ITEM_NO}}');
            $sheet->setCellValue('C'.$row, '{{ITEM_URAIAN}}');
            $sheet->setCellValue('G'.$row, '{{ITEM_VOLUME}} {{ITEM_SATUAN}}');
            $sheet->setCellValue('I'.$row, '{{ITEM_HARGA_SATUAN}}');
            $sheet->setCellValue('K'.$row, '{{ITEM_JUMLAH}}');
        }
    }
    if ($sheetName === 'TPL_RAB_PEMELIHARAAN') {
        for ($row = 14; $row <= 18; $row++) {
            $sheet->setCellValue('B'.$row, '{{ITEM_NO}}');
            $sheet->setCellValue('C'.$row, 'Bahan/Upah');
            $sheet->setCellValue('D'.$row, '{{ITEM_URAIAN}}');
            $sheet->setCellValue('F'.$row, '{{ITEM_VOLUME}}');
            $sheet->setCellValue('G'.$row, '{{ITEM_SATUAN}}');
            $sheet->setCellValue('H'.$row, '{{ITEM_HARGA_SATUAN}}');
            $sheet->setCellValue('I'.$row, '{{ITEM_JUMLAH}}');
        } $sheet->setCellValue('H19', '{{NILAI_BRUTO}}');
    }
    if ($sheetName === 'TPL_INVOICE') {
        foreach (['D5' => 'KODE_KEGIATAN', 'H5' => 'NOMOR_INVOICE', 'D6' => 'KODE_REKENING', 'H6' => 'STATUS_INVOICE', 'D7' => 'NOMOR_PESANAN', 'H7' => 'TANGGAL_INVOICE', 'D8' => 'TANGGAL_PESANAN', 'D10' => 'NAMA_PENERIMA', 'D11' => 'ALAMAT_PENYEDIA', 'D12' => 'NPWP_PENYEDIA', 'D13' => 'TELEPON_PENYEDIA', 'G13' => 'NAMA_SATUAN_PENDIDIKAN'] as $cell => $token) {
            $sheet->setCellValue($cell, '{{'.$token.'}}');
        }
    }
    $relative = $targetBase.'/'.$type.'.xlsx';
    (new Xlsx($book))->save(storage_path('app/'.$relative));
    $existing = DocumentTemplate::query()->where(['fiscal_year_id' => $year->id, 'document_type' => $type, 'format' => 'xlsx'])->first();
    if ($existing && ! str_starts_with($existing->name, 'Template awal')) {
        $skipped[] = $type.' (template kustom dipertahankan)';

        continue;
    }
    DocumentTemplate::updateOrCreate(['fiscal_year_id' => $year->id, 'document_type' => $type, 'format' => 'xlsx'], ['name' => 'Desain Excel '.$label, 'file_path' => $relative, 'is_active' => true]);
    $updated[] = $type;
}
echo json_encode(['school' => $school->npsn, 'year' => $year->year, 'updated' => $updated, 'skipped' => $skipped], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
