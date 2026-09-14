<?php

namespace App\Livewire;

use Livewire\Component;

class DatabaseOverview extends Component
{
    public bool $available = false;

    public string $integrity = '—';

    public string $databaseSize = '—';

    public string $totalStorage = '—';

    public string $lastMigrated = '—';

    public array $tableCounts = [];

    public string $integrityRoute = '#';

    public function mount(?array $activeStatus = null): void
    {
        if (! $activeStatus) {
            return;
        }

        $formatBytes = static fn (int $bytes): string => $bytes < 1024 ? $bytes.' B' : ($bytes < 1048576 ? number_format($bytes / 1024, 1).' KB' : number_format($bytes / 1048576, 2).' MB');
        $this->available = true;
        $this->integrity = (string) ($activeStatus['integrity'] ?? '—');
        $this->databaseSize = $formatBytes((int) ($activeStatus['size'] ?? 0));
        $this->totalStorage = $formatBytes((int) ($activeStatus['totalSize'] ?? 0));
        $this->lastMigrated = ! empty($activeStatus['lastMigrated']) ? $activeStatus['lastMigrated']->diffForHumans() : '—';
        $this->tableCounts = $activeStatus['tableCounts'] ?? [];
        $this->integrityRoute = route('database-manager.integrity', $activeStatus['school']->id);
    }

    public function render()
    {
        return view('livewire.database-overview');
    }
}
