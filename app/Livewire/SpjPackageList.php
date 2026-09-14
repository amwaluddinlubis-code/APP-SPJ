<?php

namespace App\Livewire;

use App\UseCases\Spj\SpjWorkspaceUseCase;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class SpjPackageList extends Component
{
    use WithPagination;

    #[Url(as: 'package_perPage', except: 15)]
    public int $perPage = 15;

    public function mount(): void
    {
        $perPage = request()->integer('package_perPage', 15);
        $this->perPage = in_array($perPage, [10, 15, 25, 50, 100], true) ? $perPage : 15;
    }

    public function updating($property): void
    {
        if ($property === 'perPage') {
            $this->resetPage('package_page');
        }
    }

    public function render(): View
    {
        return view('livewire.spj-package-list', [
            'packageList' => app(SpjWorkspaceUseCase::class)->packageListData($this->perPage),
        ]);
    }
}
