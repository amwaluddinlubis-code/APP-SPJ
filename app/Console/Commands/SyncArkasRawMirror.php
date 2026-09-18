<?php

namespace App\Console\Commands;

use App\Models\ArkasSource;
use App\Models\School;
use App\Services\ArkasRawMirrorService;
use App\Services\SchoolDatabaseManager;
use App\Services\SpjFreshProjectionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('arkas:sync-raw-mirror
    {--school-id= : ID sekolah target}
    {--source-id= : ID sumber database ARKAS}
    {--limit=100000 : Batas row per tabel dari ARKAS Bridge}')]
#[Description('Sinkronisasi semua tabel ARKAS ke raw mirror readonly.')]
class SyncArkasRawMirror extends Command
{
    public function handle(SchoolDatabaseManager $databases, ArkasRawMirrorService $mirror, SpjFreshProjectionService $projector): int
    {
        $schoolId = (int) $this->option('school-id');
        $sourceId = (int) $this->option('source-id');
        $limit = max(1, min((int) $this->option('limit'), 100000));
        if ($schoolId < 1 || $sourceId < 1) {
            $this->error('Gunakan --school-id dan --source-id.');

            return self::INVALID;
        }

        $school = School::query()->find($schoolId);
        $source = ArkasSource::query()->where('school_id', $schoolId)->find($sourceId);
        if ($school === null || $source === null) {
            $this->error('Sekolah atau sumber database ARKAS tidak ditemukan.');

            return self::FAILURE;
        }

        $databases->ensureMigrated($school);
        $this->info('Menyinkronkan seluruh tabel ARKAS ke raw mirror...');
        $result = $mirror->synchronize($source, $limit);
        $projection = $projector->projectAllValidContexts($source);
        $this->table(['Metrik', 'Jumlah'], [
            ['Tabel diperiksa', $result['tables']],
            ['Tabel berisi data', $result['non_empty']],
            ['Row tersimpan', $result['rows']],
            ['Tabel stale', $result['stale']],
            ['Konteks fiscal year + fund source', $projection['contexts']],
            ['Transaksi fresh baru', $projection['transactions']],
            ['Item fresh baru', $projection['items']],
        ]);

        return self::SUCCESS;
    }
}
