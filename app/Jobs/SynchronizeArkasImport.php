<?php

namespace App\Jobs;

use App\Models\ArkasImportProfile;
use App\Models\ArkasSource;
use App\Models\BackgroundOperation;
use App\Models\FiscalYear;
use App\Models\School;
use App\Services\ArkasGenericImportService;
use App\Services\SchoolDatabaseManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SynchronizeArkasImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 2;

    public function __construct(public int $operationId, public int $schoolId, public int $profileId, public int $fiscalYearId, public int $sourceId) {}

    public function handle(ArkasGenericImportService $importer, SchoolDatabaseManager $databases): void
    {
        $operation = BackgroundOperation::query()->findOrFail($this->operationId);
        $operation->update(['status' => 'RUNNING', 'progress' => 10, 'started_at' => now(), 'message' => 'Mengambil data tabel ARKAS ke staging.']);
        $school = School::query()->findOrFail($this->schoolId);
        $databases->activate($school);
        $profile = ArkasImportProfile::query()->findOrFail($this->profileId);
        $year = FiscalYear::query()->findOrFail($this->fiscalYearId);
        $source = ArkasSource::query()->findOrFail($this->sourceId);
        $run = $importer->synchronize($profile, $year, $source);
        $operation->update(['status' => 'COMPLETED', 'progress' => 100, 'result' => ['run_id' => $run->id, 'records_written' => $run->records_written], 'message' => $run->message, 'finished_at' => now()]);
    }

    public function failed(Throwable $exception): void
    {
        BackgroundOperation::query()->whereKey($this->operationId)->update(['status' => 'FAILED', 'progress' => 100, 'message' => 'Import ARKAS gagal. Periksa histori importer dan log aplikasi.', 'finished_at' => now()]);
    }
}
