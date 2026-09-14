<?php

namespace App\Livewire;

use App\Services\TaxFilterService;
use App\UseCases\Spj\SpjReportUseCase;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class TaxFilter extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $q = '';

    #[Url(except: 'semua')]
    public string $mode = 'semua';

    #[Url(except: null)]
    public ?int $periode = null;

    #[Url(except: 15)]
    public int|string $perPage = 15;

    public function mount(): void
    {
        $this->q = trim((string) request('q', ''));
        [$mode, $periode] = SpjReportUseCase::resolveModePeriode(request()->all());
        $this->mode = in_array($mode, $this->allowedModes(), true) ? $mode : 'semua';
        $this->periode = $periode;
        $this->perPage = $this->normalizePerPage(request('perPage', 15));
    }

    public function updating($property): void
    {
        if (in_array($property, ['q', 'mode', 'periode', 'perPage'], true)) {
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

    public function resetFilters(): void
    {
        $this->reset(['q', 'mode', 'periode', 'perPage']);
        $this->resetPage();
    }

    public function render(): View
    {
        $data = app(TaxFilterService::class)->taxData(
            trim($this->q),
            $this->mode === 'bulan' ? $this->periode : null,
            $this->mode === 'triwulan' ? $this->periode : null,
            $this->mode === 'semester' ? $this->periode : null,
            $this->resolvedPerPage()
        );

        return view('livewire.tax-filter', [
            'summary' => $data['summary'],
            'filteredSummary' => $data['filteredSummary'],
            'transactions' => $data['transactions'],
            'year' => $data['year'],
        ]);
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

    private function resolvedPerPage(): int
    {
        $perPage = $this->perPage === 'all' ? 10000 : (int) $this->perPage;

        return in_array($perPage, [15, 25, 50, 100, 10000], true) ? $perPage : 15;
    }

    private function normalizePerPage(mixed $raw): int|string
    {
        if ($raw === 'all') {
            return 'all';
        }

        $perPage = (int) $raw;

        return in_array($perPage, [15, 25, 50, 100], true) ? $perPage : 15;
    }
}
