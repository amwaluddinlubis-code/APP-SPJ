<?php

namespace App\Livewire;

use App\Services\SchoolDatabaseManager;
use Livewire\Component;

class DatabaseStatusSummary extends Component
{
    public string $schoolName = 'Belum dipilih';

    public string $npsn = '—';

    public string $sessionId = '—';

    public string $health = 'BELUM TERSEDIA';

    public string $healthHint = 'Aktifkan sekolah untuk health check';

    public string $storage = '—';

    public string $databaseSize = '—';

    public string $walSize = '—';

    public int $tableCount = 0;

    public string $tableRows = '0';

    public function mount(array $active, ?array $activeStatus = null): void
    {
        $school = $active['school'] ?? null;
        if ($school) {
            $this->schoolName = $school->name;
            $this->npsn = (string) ($school->npsn ?? '—');
            $this->sessionId = (string) ($active['schoolId'] ?? '—');
            $health = app(SchoolDatabaseManager::class)->health($school);
            $this->health = strtoupper((string) ($health['level'] ?? 'unknown'));
            $this->healthHint = empty($health['issues']) ? 'Tidak ada masalah terdeteksi' : implode(' · ', $health['issues']);
        }

        if ($activeStatus) {
            $formatBytes = static fn (int $bytes): string => $bytes < 1024 ? $bytes.' B' : ($bytes < 1048576 ? number_format($bytes / 1024, 1).' KB' : number_format($bytes / 1048576, 2).' MB');
            $this->tableCount = count($activeStatus['tableCounts'] ?? []);
            $this->storage = $formatBytes((int) ($activeStatus['totalSize'] ?? 0));
            $this->databaseSize = $formatBytes((int) ($activeStatus['size'] ?? 0));
            $this->walSize = $formatBytes((int) ($activeStatus['walSize'] ?? 0));
            $this->tableRows = number_format((int) collect($activeStatus['tableCounts'] ?? [])->sum(), 0, ',', '.');
        }
    }

    public function render()
    {
        return view('livewire.database-status-summary');
    }
}
