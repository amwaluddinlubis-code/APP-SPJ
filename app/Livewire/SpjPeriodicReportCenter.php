<?php

namespace App\Livewire;

use App\Services\SpjPeriodicReportRegistry;
use App\UseCases\Spj\SpjPeriodicReportUseCase;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

class SpjPeriodicReportCenter extends Component
{
    #[Url(as: 'paket_laporan', except: 'bulan')]
    public string $scope = SpjPeriodicReportRegistry::SCOPE_MONTHLY;

    #[Url(as: 'periode_laporan', except: null)]
    public ?int $periode = null;

    public function mount(): void
    {
        $registry = app(SpjPeriodicReportRegistry::class);

        if (! $registry->isScope($this->scope)) {
            $this->scope = SpjPeriodicReportRegistry::SCOPE_MONTHLY;
        }

        if (! $registry->periodRequired($this->scope)) {
            $this->periode = null;
        } elseif (! $registry->isValidPeriod($this->scope, $this->periode)) {
            $this->periode = null;
        }
    }

    public function setScope(string $scope): void
    {
        $registry = app(SpjPeriodicReportRegistry::class);

        if (! $registry->isScope($scope)) {
            return;
        }

        $this->scope = $scope;
        $this->periode = null;
    }

    public function updatedPeriode(mixed $value): void
    {
        if ($value === null || $value === '') {
            $this->periode = null;

            return;
        }

        $registry = app(SpjPeriodicReportRegistry::class);
        $period = (int) $value;
        $this->periode = $registry->isValidPeriod($this->scope, $period) ? $period : null;
    }

    /** @return list<array{value:int,label:string}> */
    public function periodOptions(): array
    {
        return match ($this->scope) {
            SpjPeriodicReportRegistry::SCOPE_MONTHLY => [
                ['value' => 1, 'label' => 'Januari'],
                ['value' => 2, 'label' => 'Februari'],
                ['value' => 3, 'label' => 'Maret'],
                ['value' => 4, 'label' => 'April'],
                ['value' => 5, 'label' => 'Mei'],
                ['value' => 6, 'label' => 'Juni'],
                ['value' => 7, 'label' => 'Juli'],
                ['value' => 8, 'label' => 'Agustus'],
                ['value' => 9, 'label' => 'September'],
                ['value' => 10, 'label' => 'Oktober'],
                ['value' => 11, 'label' => 'November'],
                ['value' => 12, 'label' => 'Desember'],
            ],
            SpjPeriodicReportRegistry::SCOPE_QUARTERLY => [
                ['value' => 1, 'label' => 'Triwulan I'],
                ['value' => 2, 'label' => 'Triwulan II'],
                ['value' => 3, 'label' => 'Triwulan III'],
                ['value' => 4, 'label' => 'Triwulan IV'],
            ],
            SpjPeriodicReportRegistry::SCOPE_SEMESTER => [
                ['value' => 1, 'label' => 'Semester I'],
                ['value' => 2, 'label' => 'Semester II'],
            ],
            default => [],
        };
    }

    public function render(): View
    {
        if (request('jenis_laporan') !== 'periode') {
            return view('livewire.spj-periodic-report-empty');
        }

        $registry = app(SpjPeriodicReportRegistry::class);
        $reports = app(SpjPeriodicReportUseCase::class);

        return view('livewire.spj-periodic-report-center', [
            'scopeLabels' => $registry->scopeLabels(),
            'reportDefinitions' => $registry->forScope($this->scope),
            'periodRequired' => $registry->periodRequired($this->scope),
            'summary' => $reports->summary($this->scope, $this->periode),
        ]);
    }
}
