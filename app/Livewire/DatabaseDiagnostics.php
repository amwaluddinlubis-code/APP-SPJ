<?php

namespace App\Livewire;

use Livewire\Component;

class DatabaseDiagnostics extends Component
{
    public bool $hasDatabase = false;

    public bool $exists = false;

    public bool $writable = false;

    public string $integrity = '—';

    public string $status = '—';

    public string $size = '—';

    public string $walSize = '—';

    public string $shmSize = '—';

    public string $path = '—';

    public string $connectionError = '';

    public array $tableCounts = [];

    public string $integrityRoute = '#';

    public function mount(?array $activeStatus = null): void
    {
        if (! $activeStatus) {
            return;
        }

        $formatBytes = static fn (int $bytes): string => $bytes < 1024 ? $bytes.' B' : ($bytes < 1048576 ? number_format($bytes / 1024, 1).' KB' : number_format($bytes / 1048576, 2).' MB');
        $this->hasDatabase = true;
        $this->exists = (bool) ($activeStatus['exists'] ?? false);
        $this->writable = (bool) ($activeStatus['isWritable'] ?? false);
        $this->integrity = (string) ($activeStatus['integrity'] ?? '—');
        $this->status = (string) ($activeStatus['status'] ?? '—');
        $this->size = $formatBytes((int) ($activeStatus['size'] ?? 0));
        $this->walSize = $formatBytes((int) ($activeStatus['walSize'] ?? 0));
        $this->shmSize = $formatBytes((int) ($activeStatus['shmSize'] ?? 0));
        $this->path = (string) ($activeStatus['path'] ?? '—');
        $this->connectionError = (string) ($activeStatus['connectionError'] ?? '');
        $this->tableCounts = $activeStatus['tableCounts'] ?? [];
        $this->integrityRoute = route('database-manager.integrity', $activeStatus['school']->id);
    }

    public function render()
    {
        return view('livewire.database-diagnostics');
    }
}
