<?php

use App\Models\DocumentTemplate;
use App\Models\School;
use App\Services\SchoolDatabaseManager;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$mapping = [
    'COVER_SPJ' => [], 'CHECKLIST' => [], 'KUITANSI' => [], 'RINCIAN_BELANJA' => [], 'REKAP_PAJAK' => [],
    'INVOICE_PESANAN' => ['BARANG', 'BELANJA_MODAL'], 'SURAT_PESANAN' => ['BARANG', 'BELANJA_MODAL'],
    'RAB_PEMELIHARAAN' => ['PEMELIHARAAN'], 'SPK_PEMELIHARAAN' => ['PEMELIHARAAN'],
    'BA_PEMERIKSAAN' => ['PEMELIHARAAN'], 'BA_SERAH_TERIMA' => ['PEMELIHARAAN'],
    'DAFTAR_HADIR_UPAH' => ['UPAH', 'PEMELIHARAAN'], 'LAMPIRAN_UPAH' => ['UPAH', 'PEMELIHARAAN'],
];
$result = [];
foreach (School::query()->get() as $school) {
    app(SchoolDatabaseManager::class)->activate($school);
    Artisan::call('migrate', ['--database' => 'school', '--path' => 'database/migrations/school', '--force' => true]);
    $updated = 0;
    foreach ($mapping as $type => $categories) {
        $updated += DocumentTemplate::query()->where('document_type', $type)->update(['applicable_categories' => json_encode($categories), 'is_active' => true]);
    }
    // Desain TPL_* Excel menjadi standar aktif. Template Word awal tetap
    // disimpan sebagai alternatif, tetapi tidak membanjiri daftar paket.
    DocumentTemplate::query()->where('name', 'like', 'Template awal %')->where('format', 'docx')->update(['is_active' => false]);
    $result[] = ['npsn' => $school->npsn, 'templates_mapped' => $updated];
}
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
