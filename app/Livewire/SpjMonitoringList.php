<?php

namespace App\Livewire;

use App\UseCases\Spj\SpjReportUseCase;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class SpjMonitoringList extends Component
{
    use WithPagination;

    #[Url(except: 'semua')]
    public string $mode = 'semua';

    #[Url(except: null)]
    public ?int $periode = null;

    #[Url(as: 'pendingPerPage', except: 15)]
    public int $pendingPerPage = 15;

    public function mount(): void
    {
        [$mode, $periode] = SpjReportUseCase::resolveModePeriode(request()->all());
        $this->mode = in_array($mode, ['semua', 'semester', 'triwulan', 'bulan'], true) ? $mode : 'semua';
        $this->periode = $periode;
        $pendingPerPage = request()->integer('pendingPerPage', 15);
        $this->pendingPerPage = in_array($pendingPerPage, [15, 25, 50, 100], true) ? $pendingPerPage : 15;
    }

    public function updating($property): void
    {
        if ($property === 'pendingPerPage') {
            $this->resetPage('pending_page');
        }
    }

    public function render(): View
    {
        [, $summary] = app(SpjReportUseCase::class)->reportData($this->mode, $this->periode, 15, $this->pendingPerPage, 1, $this->getPage('pending_page'));

        return view('livewire.spj-monitoring-list', [
            'pendingPaginator' => $summary['pending_transactions'],
        ]);
    }
}
