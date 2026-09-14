<?php

namespace App\Livewire;

use App\UseCases\Spj\SpjWorkspaceUseCase;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class SpjPreparationFilter extends Component
{
    use WithPagination;

    #[Url(except: null)]
    public ?int $month = null;

    #[Url(except: null)]
    public ?int $quarter = null;

    #[Url(except: '')]
    public string $spj_category = '';

    #[Url(except: 'all')]
    public string $state = 'all';

    #[Url(except: 15)]
    public int|string $perPage = 15;

    public function mount(): void
    {
        $this->month = request()->integer('month') ?: null;
        $this->quarter = request()->integer('quarter') ?: null;
        $this->spj_category = trim((string) request('spj_category', ''));
        $state = (string) request('state', 'all');
        $this->state = in_array($state, $this->allowedStates(), true) ? $state : 'all';
        $this->perPage = $this->normalizePerPage(request('perPage', 15));
    }

    public function updating($property): void
    {
        if (in_array($property, ['month', 'quarter', 'spj_category', 'state', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    public function setQueueState(string $state): void
    {
        if (! in_array($state, $this->allowedStates(), true)) {
            return;
        }

        $this->state = $state;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['month', 'quarter', 'spj_category', 'state', 'perPage']);
        $this->resetPage();
    }

    public function render(): View
    {
        $data = app(SpjWorkspaceUseCase::class)->preparationData($this->filters(), $this->resolvedPerPage());

        return view('livewire.spj-preparation-filter', [
            'transactions' => $data['transactions'],
            'workQueueCounts' => $data['workQueueCounts'],
            'spjTypes' => $data['spjTypes'],
        ]);
    }

    /** @return array{month: int|null, quarter: int|null, spj_category: string|null, state: string} */
    private function filters(): array
    {
        return [
            'month' => $this->month,
            'quarter' => $this->quarter,
            'spj_category' => $this->spj_category === '' ? null : $this->spj_category,
            'state' => $this->state,
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

    /** @return list<string> */
    private function allowedStates(): array
    {
        return ['all', 'attention', 'needs_details', 'unprepared', 'draft', 'ready', 'numbered'];
    }
}
