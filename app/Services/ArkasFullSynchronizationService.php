<?php

namespace App\Services;

use App\Models\ArkasSource;
use App\Models\FiscalYear;
use App\Models\School;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/** Coordinates the complete ARKAS refresh in the dependency-safe order. */
class ArkasFullSynchronizationService
{
    public function __construct(
        private ArkasReferenceSynchronizationService $references,
        private ArkasSynchronizationServiceV2 $transactions,
        private ArkasStagingService $staging,
    ) {}

    /** @return Collection<int, FiscalYear> */
    public function bootstrapFiscalYears(ArkasSource $source): Collection
    {
        $context = new FiscalYear(['year' => 0]);
        $years = array_map(static fn (array $row): int => (int) ($row['value'] ?? 0), $this->staging->stage('__years', 'years', $context, $source, 'lines'));
        $fundSourcesByYear = [];
        foreach ($years as $sourceYear) {
            $fundSourcesByYear[$sourceYear] = $this->staging->stage('__fund_sources_'.$sourceYear, 'fund-sources', $context, $source, 'decode', $sourceYear);
        }

        return $this->references->synchronizeFiscalYearContexts($source, $years, $fundSourcesByYear);
    }

    /** @return array<string,int> */
    public function synchronize(School $school, FiscalYear $year, ArkasSource $source): array
    {
        $lock = Cache::lock('arkas-sync:'.$school->id.':'.$year->id, 900);
        if (! $lock->get()) {
            throw new \RuntimeException('Sinkronisasi ARKAS untuk sekolah dan tahun anggaran ini sedang berjalan. Tunggu sampai proses sebelumnya selesai.');
        }

        try {
            // Profile, employees, accounts and periods do not depend on BKU.
            $stagedYears = array_map(static fn (array $row): int => (int) ($row['value'] ?? 0), $this->staging->stage('__years', 'years', $year, $source, 'lines'));
            $stagedFundSourcesByYear = [];
            foreach ($stagedYears as $sourceYear) {
                $stagedFundSourcesByYear[$sourceYear] = $this->staging->stage('__fund_sources_'.$sourceYear, 'fund-sources', $year, $source, 'decode', $sourceYear);
            }
            $stagedBase = [
                'years' => $stagedYears,
                'fund_sources_by_year' => $stagedFundSourcesByYear,
                'fund_sources' => $stagedFundSourcesByYear[$year->year] ?? [],
                'profile' => ($this->staging->stage('__profile', 'profile', $year, $source, 'values', $year->year)[0] ?? []),
                'pegawai' => $this->staging->stage('__pegawai', 'pegawai', $year, $source, 'decode', $year->year),
                'ptk' => $this->staging->stage('__ptk', 'ptk', $year, $source, 'decode', $year->year),
                'rekening' => $this->staging->stage('__rekening', 'rekening', $year, $source, 'decode', $year->year),
                'periods' => $this->staging->stage('__periods', 'periods', $year, $source, 'pairs'),
            ];
            $counts = $this->references->synchronizeBase($year, $source, $stagedBase);
            // Core service validates NPSN, then imports RKAS → raw BKU → transactions.
            $stagedRkas = $this->staging->stage('rapbs', 'rkas', $year, $source);
            $stagedBku = $this->staging->stage('kas_umum', 'bku', $year, $source);
            $stagedPeriods = $this->staging->stage('rapbs_periode', 'rows', $year, $source);
            $stagedIdentity = ($this->staging->stage('__identity', 'identity', $year, $source, 'values')[0] ?? []);
            $counts += $this->transactions->synchronize($school, $year, $source, $stagedRkas, $stagedBku, $stagedPeriods, $stagedIdentity);

            // Activities and suppliers are derived from the just-imported raw data.
            return $counts + $this->references->synchronizeDerived($year);
        } finally {
            $lock->release();
        }
    }
}
