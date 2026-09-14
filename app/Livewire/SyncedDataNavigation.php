<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class SyncedDataNavigation extends Component
{
    public array $tables = [];

    public array $counts = [];

    public string $type = 'overview';

    public string $search = '';

    public function render(): View
    {
        $groups = collect($this->tables)->groupBy('group', true);
        $term = mb_strtolower(trim($this->search));
        if ($term !== '') {
            $groups = $groups->map(fn ($items) => $items->filter(fn (array $item): bool => str_contains(mb_strtolower($item['label']), $term)))->filter(fn ($items) => $items->isNotEmpty());
        }

        return view('livewire.synced-data-navigation', compact('groups'));
    }
}
