<?php

use App\Models\DocumentTemplate;
use App\Models\FiscalYear;
use App\Models\School;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Services\SpjPackageValidationService;
use App\Services\SpjPdfService;
use App\Services\SpjTemplateService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\PhpWord;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$school = School::query()->where('npsn', '10208183')->firstOrFail();
$source = $school->databaseRecord->database_path;
$folder = storage_path('app/test-runs/'.now()->format('Ymd-His'));
File::ensureDirectoryExists($folder);
$copy = $folder.'/school-test.sqlite';
File::copy($source, $copy);
Config::set('database.connections.school.database', $copy);
DB::purge('school');
DB::reconnect('school');
$year = FiscalYear::query()->orderByDesc('year')->firstOrFail();

$types = [
    'BARANG' => fn ($q) => $q->where('account_code', 'not like', '5.2%')->where('description', 'not like', '%Upah%'),
    'BELANJA_MODAL' => fn ($q) => $q->where('account_code', 'like', '5.2%'),
    'UPAH' => fn ($q) => $q->where('description', 'like', '%Upah%'),
    'PEMELIHARAAN' => fn ($q) => $q->where('account_code', 'like', '5.1.02.03%'),
];
$results = [];
foreach ($types as $type => $scope) {
    $query = Transaction::query()->where('fiscal_year_id', $year->id)->has('items');
    $transaction = $scope($query)->with(['items', 'workers'])->first();
    if (! $transaction) {
        $results[$type] = ['status' => 'SKIP', 'reason' => 'Sampel transaksi tidak ditemukan'];

        continue;
    }
    $transaction->forceFill([
        'spj_category' => $type,
        'payment_description' => 'Pembayaran uji penerimaan '.$type,
        'payment_method' => 'UJI-'.substr($type, 0, 4),
        'payment_reference' => 'UJI-'.substr($type, 0, 4),
        'invoice_number' => 'INV-UJI-'.substr($type, 0, 4),
        'invoice_date' => $transaction->transaction_date,
        'invoice_status' => 'LUNAS',
        'work_description' => 'Pelaksanaan pekerjaan uji '.$type,
        'work_location' => 'Lokasi uji sekolah',
        'work_started_at' => $transaction->transaction_date,
        'work_completed_at' => $transaction->transaction_date,
        'spk_number' => 'SPK-UJI-'.substr($type, 0, 4),
        'spk_date' => $transaction->transaction_date,
        'signatory_name' => $transaction->recipient_name ?: 'Pelaksana Uji',
        'signatory_role' => 'Penerima/Pelaksana',
    ])->save();
    if ($type === 'UPAH') {
        $transaction->workers()->delete();
        $transaction->workers()->create(['name' => 'Pekerja Uji', 'job_description' => 'Pekerjaan uji', 'work_days' => 2, 'daily_rate' => 100000, 'amount' => 200000, 'is_receipt_recipient' => true]);
    }
    $package = SpjPackage::firstOrCreate(['transaction_id' => $transaction->id], ['status' => 'DRAFT']);
    $package->forceFill(['document_number' => 'UJI/'.substr($type, 0, 6).'/'.$year->year, 'quarter_code' => 'TW-1', 'semester_code' => 'SEM-I', 'status' => 'BERNOMOR'])->save();
    $package = SpjPackage::query()->with(['transaction.items', 'transaction.workers'])->findOrFail($package->id);
    $issues = app(SpjPackageValidationService::class)->validate($package);
    $pdf = app(SpjPdfService::class)->download($package, $school);
    $word = new PhpWord;
    $section = $word->addSection();
    $section->addText('UJI {{NOMOR_SPJ}}');
    $table = $section->addTable();
    $row = $table->addRow();
    foreach (['{{ITEM_NO}}', '{{ITEM_URAIAN}}', '{{ITEM_JUMLAH}}'] as $marker) {
        $row->addCell()->addText($marker);
    } $wordPath = $folder.'/'.$type.'.docx';
    WordIOFactory::createWriter($word, 'Word2007')->save($wordPath);
    $book = new Spreadsheet;
    $sheet = $book->getActiveSheet();
    $sheet->setCellValue('A1', 'UJI {{NOMOR_SPJ}}');
    $sheet->fromArray(['{{ITEM_NO}}', '{{ITEM_URAIAN}}', '{{ITEM_JUMLAH}}'], null, 'A3');
    $excelPath = $folder.'/'.$type.'.xlsx';
    (new Xlsx($book))->save($excelPath);
    // File template selalu disimpan relatif terhadap storage/app agar konsisten
    // pada Windows maupun Linux.
    $relativeFolder = 'test-runs/'.basename($folder);
    $wordTemplate = DocumentTemplate::create(['fiscal_year_id' => $year->id, 'document_type' => 'UJI_'.$type.'_WORD', 'name' => 'Uji '.$type.' Word', 'format' => 'docx', 'file_path' => $relativeFolder.'/'.$type.'.docx', 'is_active' => true]);
    $excelTemplate = DocumentTemplate::create(['fiscal_year_id' => $year->id, 'document_type' => 'UJI_'.$type.'_EXCEL', 'name' => 'Uji '.$type.' Excel', 'format' => 'xlsx', 'file_path' => $relativeFolder.'/'.$type.'.xlsx', 'is_active' => true]);
    $templates = app(SpjTemplateService::class);
    $wordResponse = $templates->download($wordTemplate, $package, $school);
    $excelResponse = $templates->download($excelTemplate, $package, $school);
    $results[$type] = ['status' => empty($issues) && $pdf->getStatusCode() === 200 && $wordResponse->getStatusCode() === 200 && $excelResponse->getStatusCode() === 200 ? 'LULUS' : 'GAGAL', 'no_bukti' => $transaction->no_bukti, 'items' => $package->transaction->items->count(), 'workers' => $package->transaction->workers->count(), 'validation_issues' => count($issues), 'pdf_status' => $pdf->getStatusCode(), 'word_status' => $wordResponse->getStatusCode(), 'excel_status' => $excelResponse->getStatusCode()];
}

$audit = [];
$transactions = DB::connection('school')->table('transactions')->where('fiscal_year_id', $year->id);
$audit['transaksi_tanpa_kegiatan'] = (clone $transactions)->where(fn ($q) => $q->whereNull('activity_code')->orWhereNull('activity_name')->orWhere('activity_code', '')->orWhere('activity_name', ''))->count();
$audit['transaksi_tanpa_rekening'] = (clone $transactions)->where(fn ($q) => $q->whereNull('account_code')->orWhereNull('account_name')->orWhere('account_code', '')->orWhere('account_name', ''))->count();
$audit['pajak_tidak_konsisten'] = (clone $transactions)->whereRaw('ABS(tax_total - (ppn + pph21 + pph22 + pph23 + pph4 + sspd)) > 0.01')->count();
$audit['detail_duplikat'] = DB::connection('school')->table('transaction_items')->selectRaw('transaction_id, source_item_id, COUNT(*) c')->groupBy('transaction_id', 'source_item_id')->having('c', '>', 1)->count();
$audit['nomor_bukti_duplikat'] = (clone $transactions)->selectRaw('no_bukti, COUNT(*) c')->groupBy('no_bukti')->having('c', '>', 1)->count();
$audit['selisih_total_belanja_bku_vs_transaksi'] = (float) DB::connection('school')->table('arkas_bku_rows')->where(['fiscal_year_id' => $year->id, 'category' => 'BELANJA'])->sum('amount') - (float) (clone $transactions)->sum('gross_amount');
$audit['total_rkas'] = (float) DB::connection('school')->table('arkas_rkas_items')->where('fiscal_year_id', $year->id)->sum('amount');
$audit['total_realisasi_bku'] = (float) (clone $transactions)->sum('gross_amount');

$report = ['tested_at' => now()->toIso8601String(), 'database_copy' => $copy, 'year' => $year->year, 'packages' => $results, 'audit' => $audit];
file_put_contents($folder.'/acceptance-report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
