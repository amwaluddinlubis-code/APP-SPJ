<?php

namespace App\Jobs;

use App\Models\ArkasSource;
use App\Models\BackgroundOperation;
use App\Models\FiscalYear;
use App\Models\School;
use App\Services\ArkasRawMirrorService;
use App\Services\SchoolDatabaseManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SynchronizeArkasRawMirror implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(
        public int $operationId,
        public int $schoolId,
        public int $fiscalYearId,
        public int $fundSourceId,
        public int $sourceId,
    ) {}

    public function handle(SchoolDatabaseManager $databases, ArkasRawMirrorService $mirror): void
    {
        $operation = BackgroundOperation::query()->findOrFail($this->operationId);
        $operation->update(['status' => 'RUNNING', 'progress' => 5, 'started_at' => now(), 'message' => 'Menyiapkan konteks raw mirror ARKAS.']);

        $school = School::query()->findOrFail($this->schoolId);
        $databases->ensureMigrated($school);
        $year = FiscalYear::query()->whereKey($this->fiscalYearId)->where('fund_source_id', $this->fundSourceId)->firstOrFail();
        $source = ArkasSource::query()->where('school_id', $school->id)->findOrFail($this->sourceId);
        $operation->update(['progress' => 10, 'message' => 'Menyinkronkan seluruh tabel ARKAS ke raw mirror.']);

        $result = $mirror->synchronize($source);
        $operation->update([
            'status' => 'COMPLETED',
            'progress' => 100,
            'result' => $result + ['fiscal_year_id' => $year->id, 'fund_source_id' => $this->fundSourceId],
            'message' => 'Raw mirror ARKAS selesai disinkronkan.',
            'finished_at' => now(),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        BackgroundOperation::query()->whereKey($this->operationId)->update([
            'status' => 'FAILED', 'progress' => 100, 'message' => 'Raw mirror ARKAS gagal. Periksa konfigurasi Bridge dan histori operasi.', 'finished_at' => now(),
        ]);
    }
}
