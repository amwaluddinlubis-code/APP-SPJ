<?php

namespace App\Livewire;

use Livewire\Component;

class DatabaseManagerTabs extends Component
{
    public int $databaseCount = 0;

    public int $tableCount = 0;

    public int $centralTableCount = 0;

    public string $activeTab = 'overview';

    public function mount(int $databaseCount = 0, int $tableCount = 0, int $centralTableCount = 0): void
    {
        $this->databaseCount = $databaseCount;
        $this->tableCount = $tableCount;
        $this->centralTableCount = $centralTableCount;
    }

    public function selectTab(string $tab): void
    {
        if (! in_array($tab, ['overview', 'list', 'central', 'diagnostic', 'maintenance'], true)) {
            return;
        }

        $this->activeTab = $tab;
        $this->dispatch('database-tab-changed', tab: $tab);
    }

    public function render()
    {
        return view('livewire.database-manager-tabs');
    }
}
