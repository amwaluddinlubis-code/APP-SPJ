<?php

namespace App\Jobs;

use App\Models\ArkasSource;
use App\Models\BackgroundOperation;
use App\Models\FiscalYear;
use App\Models\School;
use App\Services\ArkasFullSynchronizationService;
use App\Services\SchoolDatabaseManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SynchronizeArkas implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 2;

    public function __construct(public int $operationId, public int $schoolId, public int $fiscalYearId, public int $sourceId) {}

    public function handle(ArkasFullSynchronizationService $synchronizer, SchoolDatabaseManager $databases): void
    {
        $operation = BackgroundOperation::query()->findOrFail($this->operationId);
        $operation->update(['status' => 'RUNNING', 'progress' => 10, 'started_at' => now(), 'message' => 'Menghubungkan sumber ARKAS.']);
        $school = School::query()->findOrFail($this->schoolId);
        $databases->activate($school);
        $year = FiscalYear::query()->findOrFail($this->fiscalYearId);
        $source = ArkasSource::query()->findOrFail($this->sourceId);
        $result = $synchronizer->synchronize($school, $year, $source);

        Cache::forget('school:'.$school->id.':header-years');
        Cache::forget('school:'.$school->id.':year:'.$year->id.':transaction-statuses');
        $operation->update([
            'status' => 'COMPLETED', 'progress' => 100, 'result' => $result,
            'message' => "Sinkronisasi selesai: {$result['bku']} baris BKU.", 'finished_at' => now(),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Queued ARKAS synchronization failed.', ['operation_id' => $this->operationId, 'exception' => $exception]);
        BackgroundOperation::query()->whereKey($this->operationId)->update([
            'status' => 'FAILED', 'message' => 'Sinkronisasi gagal. Periksa log aplikasi untuk detail teknis.', 'finished_at' => now(),
        ]);
    }
}
