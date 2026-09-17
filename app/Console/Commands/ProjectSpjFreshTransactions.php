<?php

namespace App\Console\Commands;

use App\Models\ArkasSource;
use App\Models\FiscalYear;
use App\Models\School;
use App\Services\SchoolDatabaseManager;
use App\Services\SpjFreshProjectionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('spj:project-fresh-transactions
    {--school-id= : ID sekolah target}
    {--fiscal-year-id= : ID tahun anggaran target}
    {--fund-source-id= : ID sumber dana target}
    {--source-id= : ID sumber database ARKAS}')]
#[Description('Membentuk indeks transaksi fresh SPJ dari raw mirror ARKAS.')]
class ProjectSpjFreshTransactions extends Command
{
    public function handle(SchoolDatabaseManager $databases, SpjFreshProjectionService $projector): int
    {
        $schoolId = (int) $this->option('school-id');
        $yearId = (int) $this->option('fiscal-year-id');
        $fundSourceId = (int) $this->option('fund-source-id');
        $sourceId = (int) $this->option('source-id');
        $school = School::query()->find($schoolId);
        if ($school === null || $yearId < 1 || $fundSourceId < 1 || $sourceId < 1) {
            $this->error('Gunakan school-id, fiscal-year-id, fund-source-id, dan source-id yang valid.');

            return self::INVALID;
        }

        $databases->ensureMigrated($school);
        $year = FiscalYear::query()->whereKey($yearId)->where('fund_source_id', $fundSourceId)->first();
        $source = ArkasSource::query()->where('school_id', $schoolId)->find($sourceId);
        if ($year === null || $source === null) {
            $this->error('Tahun anggaran atau sumber ARKAS tidak ditemukan pada tenant target.');

            return self::FAILURE;
        }

        $result = $projector->project($year, $fundSourceId, $source);
        $this->table(['Metrik', 'Jumlah'], [
            ['Transaksi fresh baru', $result['transactions']],
            ['Item fresh baru', $result['items']],
            ['Row dilewati karena di luar tahun', $result['skipped']],
        ]);

        return self::SUCCESS;
    }
}
