<?php

namespace App\Livewire;

use App\UseCases\Spj\SpjReportUseCase;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class SpjReportFilter extends Component
{
    use WithPagination;

    #[Url(except: 'semua')]
    public string $mode = 'semua';

    #[Url(except: null)]
    public ?int $periode = null;

    #[Url(except: 15)]
    public int|string $perPage = 15;

    public function mount(): void
    {
        [$mode, $periode] = SpjReportUseCase::resolveModePeriode(request()->all());
        $this->mode = in_array($mode, $this->allowedModes(), true) ? $mode : 'semua';
        $this->periode = $periode;
        $this->perPage = $this->normalizePerPage(request('perPage', 15));
    }

    public function updating($property): void
    {
        if (in_array($property, ['periode', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    public function setMode(string $mode): void
    {
        if (! in_array($mode, $this->allowedModes(), true)) {
            return;
        }

        $this->mode = $mode;
        $this->periode = null;
        $this->resetPage();
    }

    public function render(): View
    {
        if (request('jenis_laporan') === 'periode') {
            return view('livewire.spj-periodic-report-page');
        }

        [$packages, $summary] = app(SpjReportUseCase::class)
            ->reportData($this->mode, $this->periode, $this->resolvedPerPage(), 15);

        return view('livewire.spj-report-filter', [
            'packages' => $packages,
            'summary' => $summary,
        ]);
    }

    private function resolvedPerPage(): int
    {
        $perPage = $this->perPage === 'all' ? 10000 : (int) $this->perPage;

        return in_array($perPage, [10, 15, 25, 50, 100, 10000], true) ? $perPage : 15;
    }

    private function normalizePerPage(mixed $raw): int|string
    {
        if ($raw === 'all') {
            return 'all';
        }

        $perPage = (int) $raw;

        return in_array($perPage, [10, 15, 25, 50, 100], true) ? $perPage : 15;
    }

    /** @return list<string> */
    public function allowedModes(): array
    {
        return ['semua', 'semester', 'triwulan', 'bulan'];
    }

    /** @return list<array{0: string, 1: string}> */
    public function modes(): array
    {
        return [
            ['semua', 'Semua'],
            ['semester', 'Semester'],
            ['triwulan', 'Triwulan'],
            ['bulan', 'Bulan'],
        ];
    }
}
