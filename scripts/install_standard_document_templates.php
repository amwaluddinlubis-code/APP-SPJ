<?php

use App\Models\DocumentTemplate;
use App\Models\FiscalYear;
use App\Models\School;
use App\Services\SchoolDatabaseManager;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\PhpWord;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$school = School::query()->where('npsn', '10208183')->firstOrFail();
app(SchoolDatabaseManager::class)->activate($school);
$year = FiscalYear::query()->orderByDesc('year')->firstOrFail();
$base = 'document-templates/'.$year->id.'/starter';
File::ensureDirectoryExists(storage_path('app/'.$base));

$documents = [
    'KUITANSI' => ['KUITANSI PEMBAYARAN DANA BOSP', 'plain'],
    'RINCIAN_BELANJA' => ['RINCIAN BELANJA', 'items'],
    'CHECKLIST' => ['CHECKLIST KELENGKAPAN SPJ', 'plain'],
    'REKAP_PAJAK' => ['REKAP PAJAK TRANSAKSI', 'plain'],
    'INVOICE_PESANAN' => ['INVOICE / PESANAN', 'items'],
    'RAB_PEMELIHARAAN' => ['RENCANA ANGGARAN BIAYA PEMELIHARAAN', 'items'],
    'SPK_PEMELIHARAAN' => ['SURAT PERINTAH KERJA PEMELIHARAAN', 'plain'],
    'BA_PEMERIKSAAN' => ['BERITA ACARA PEMERIKSAAN', 'items'],
    'BA_SERAH_TERIMA' => ['BERITA ACARA SERAH TERIMA', 'items'],
    'DAFTAR_HADIR_UPAH' => ['DAFTAR HADIR PEKERJA', 'workers'],
    'LAMPIRAN_UPAH' => ['LAMPIRAN PEMBAYARAN UPAH', 'workers'],
];
$created = [];

foreach ($documents as $type => [$title, $mode]) {
    foreach (['docx', 'xlsx'] as $format) {
        if (DocumentTemplate::query()->where(['fiscal_year_id' => $year->id, 'document_type' => $type, 'format' => $format])->exists()) {
            continue;
        }
        $relative = $base.'/'.$type.'.'.$format;
        $path = storage_path('app/'.$relative);
        if ($format === 'docx') {
            $word = new PhpWord;
            $section = $word->addSection(['marginLeft' => 1000, 'marginRight' => 1000]);
            $section->addText($title, ['bold' => true, 'size' => 14], ['alignment' => 'center']);
            foreach (['Nomor SPJ: {{NOMOR_SPJ}}', 'No. Bukti: {{NO_BUKTI}}', 'Tanggal: {{TANGGAL_TRANSAKSI}}', 'Sekolah: {{NAMA_SEKOLAH}}', 'Penerima/Pelaksana: {{NAMA_PENERIMA}}', 'Kegiatan: {{KODE_KEGIATAN}} — {{NAMA_KEGIATAN}}', 'Rekening: {{KODE_REKENING}} — {{NAMA_REKENING}}'] as $line) {
                $section->addText($line);
            }
            if ($mode === 'items') {
                $table = $section->addTable(['borderSize' => 6, 'borderColor' => '64748B']);
                $header = $table->addRow();
                foreach (['No', 'Uraian', 'Vol', 'Sat', 'Harga', 'Jumlah'] as $cell) {
                    $header->addCell()->addText($cell, ['bold' => true]);
                }
                $row = $table->addRow();
                foreach (['{{ITEM_NO}}', '{{ITEM_URAIAN}}', '{{ITEM_VOLUME}}', '{{ITEM_SATUAN}}', '{{ITEM_HARGA_SATUAN}}', '{{ITEM_JUMLAH}}'] as $cell) {
                    $row->addCell()->addText($cell);
                }
            }
            if ($mode === 'workers') {
                $table = $section->addTable(['borderSize' => 6, 'borderColor' => '64748B']);
                $header = $table->addRow();
                foreach (['No', 'Nama', 'Pekerjaan', 'Hari', 'Tarif', 'Jumlah'] as $cell) {
                    $header->addCell()->addText($cell, ['bold' => true]);
                }
                $row = $table->addRow();
                foreach (['{{UPAH_NO}}', '{{UPAH_NAMA}}', '{{UPAH_PEKERJAAN}}', '{{UPAH_HARI}}', '{{UPAH_TARIF_HARI}}', '{{UPAH_JUMLAH}}'] as $cell) {
                    $row->addCell()->addText($cell);
                }
            }
            foreach (['Untuk pembayaran: {{UNTUK_PEMBAYARAN}}', 'Nilai bruto: {{NILAI_BRUTO}}', 'PPN: {{PPN}} | PPh 21: {{PPH21}} | PPh 22: {{PPH22}} | PPh 23: {{PPH23}} | SSPD: {{SSPD}}', 'Total pajak: {{TOTAL_PAJAK}}', 'Nilai dibayarkan: {{NILAI_DIBAYARKAN}}', 'Penandatangan: {{NAMA_PENANDATANGAN}} ({{JABATAN_PENANDATANGAN}})'] as $line) {
                $section->addText($line);
            }
            WordIOFactory::createWriter($word, 'Word2007')->save($path);
        } else {
            $book = new Spreadsheet;
            $sheet = $book->getActiveSheet()->setTitle(substr($type, 0, 31));
            $sheet->setCellValue('A1', $title);
            $sheet->mergeCells('A1:F1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $sheet->fromArray([['Nomor SPJ', '{{NOMOR_SPJ}}'], ['No. Bukti', '{{NO_BUKTI}}'], ['Tanggal', '{{TANGGAL_TRANSAKSI}}'], ['Penerima', '{{NAMA_PENERIMA}}'], ['Kegiatan', '{{KODE_KEGIATAN}} — {{NAMA_KEGIATAN}}'], ['Rekening', '{{KODE_REKENING}} — {{NAMA_REKENING}}']], null, 'A3');
            $row = 11;
            if ($mode === 'items') {
                $sheet->fromArray([['No', 'Uraian', 'Vol', 'Sat', 'Harga', 'Jumlah'], ['{{ITEM_NO}}', '{{ITEM_URAIAN}}', '{{ITEM_VOLUME}}', '{{ITEM_SATUAN}}', '{{ITEM_HARGA_SATUAN}}', '{{ITEM_JUMLAH}}']], null, 'A'.$row);
                $row += 4;
            }
            if ($mode === 'workers') {
                $sheet->fromArray([['No', 'Nama', 'Pekerjaan', 'Hari', 'Tarif', 'Jumlah'], ['{{UPAH_NO}}', '{{UPAH_NAMA}}', '{{UPAH_PEKERJAAN}}', '{{UPAH_HARI}}', '{{UPAH_TARIF_HARI}}', '{{UPAH_JUMLAH}}']], null, 'A'.$row);
                $row += 4;
            }
            $sheet->fromArray([['Untuk pembayaran', '{{UNTUK_PEMBAYARAN}}'], ['Nilai bruto', '{{NILAI_BRUTO}}'], ['Total pajak', '{{TOTAL_PAJAK}}'], ['Nilai dibayarkan', '{{NILAI_DIBAYARKAN}}']], null, 'A'.$row);
            foreach (range('A', 'F') as $column) {
                $sheet->getColumnDimension($column)->setWidth($column === 'B' ? 42 : 16);
            }
            (new Xlsx($book))->save($path);
        }
        DocumentTemplate::create(['fiscal_year_id' => $year->id, 'document_type' => $type, 'name' => 'Template awal '.$title, 'format' => $format, 'file_path' => $relative, 'is_active' => true]);
        $created[] = $type.'.'.$format;
    }
}
echo json_encode(['school' => $school->npsn, 'year' => $year->year, 'created' => $created, 'count' => count($created)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
