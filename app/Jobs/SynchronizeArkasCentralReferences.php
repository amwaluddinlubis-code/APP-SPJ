<?php

namespace App\Jobs;

use App\Models\ArkasSource;
use App\Models\School;
use App\Services\ArkasCentralReferenceSynchronizationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class SynchronizeArkasCentralReferences implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public int $schoolId, public int $sourceId) {}

    public function handle(ArkasCentralReferenceSynchronizationService $synchronizer): void
    {
        $school = School::query()->findOrFail($this->schoolId);
        $source = ArkasSource::query()->where('school_id', $school->id)->findOrFail($this->sourceId);
        $synchronizer->synchronizeCodeReference($school, $source);
    }

    public function failed(Throwable $exception): void
    {
        report($exception);
    }
}
