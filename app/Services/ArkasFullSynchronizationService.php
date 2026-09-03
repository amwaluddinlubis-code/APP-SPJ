<?php

namespace App\Services;

use App\Models\ArkasSource;
use App\Models\FiscalYear;
use App\Models\School;
use Illuminate\Support\Collection;

/** Coordinates the complete ARKAS refresh in the dependency-safe order. */
class ArkasFullSynchronizationService
{
    public function __construct(
        private ArkasReferenceSynchronizationService $references,
        private ArkasSynchronizationServiceV2 $transactions,
    ) {}

    /** @return Collection<int, FiscalYear> */
    public function bootstrapFiscalYears(ArkasSource $source): Collection
    {
        return $this->references->synchronizeFiscalYearContexts($source);
    }

    /** @return array<string,int> */
    public function synchronize(School $school, FiscalYear $year, ArkasSource $source): array
    {
        // Profile, employees, accounts and periods do not depend on BKU.
        $counts = $this->references->synchronizeBase($year, $source);
        // Core service validates NPSN, then imports RKAS → raw BKU → transactions.
        $counts += $this->transactions->synchronize($school, $year, $source);

        // Activities and suppliers are derived from the just-imported raw data.
        return $counts + $this->references->synchronizeDerived($year);
    }
}
