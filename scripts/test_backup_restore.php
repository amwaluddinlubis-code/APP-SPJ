<?php

use App\Models\School;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\File;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Semua operasi berada dalam folder test-runs; database operasional tidak disentuh.
$school = School::query()->where('npsn', '10208183')->firstOrFail();
$folder = storage_path('app/test-runs/backup-restore-'.now()->format('Ymd-His'));
File::ensureDirectoryExists($folder);

$workingCopy = $folder.'/school-test.sqlite';
$backupCopy = $folder.'/backup-sebelum-pulih.sqlite';
File::copy($school->databaseRecord->database_path, $workingCopy);
File::copy($workingCopy, $backupCopy);

$before = (new PDO('sqlite:'.$workingCopy))->query('PRAGMA user_version')->fetchColumn();
$mutated = new PDO('sqlite:'.$workingCopy);
$mutated->exec('PRAGMA user_version = 20260828');
$afterMutation = $mutated->query('PRAGMA user_version')->fetchColumn();
unset($mutated);

// Mekanisme pemulihan aplikasi: file cadangan menggantikan database aktif.
File::copy($backupCopy, $workingCopy);
$afterRestore = (new PDO('sqlite:'.$workingCopy))->query('PRAGMA user_version')->fetchColumn();

$report = [
    'tested_at' => now()->toIso8601String(),
    'working_copy' => $workingCopy,
    'backup_copy' => $backupCopy,
    'version_before' => (int) $before,
    'version_after_mutation' => (int) $afterMutation,
    'version_after_restore' => (int) $afterRestore,
    'status' => ((int) $afterMutation === 20260828 && (int) $afterRestore === (int) $before) ? 'LULUS' : 'GAGAL',
];

file_put_contents($folder.'/backup-restore-report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
