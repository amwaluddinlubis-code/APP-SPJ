<?php

namespace App\Livewire;

use App\Services\RkasPlanningSuggestionService;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;

class RkasPlanningSuggestionTables extends Component
{
    use WithPagination;

    private const PER_PAGE = 10;

    public function render(RkasPlanningSuggestionService $suggestions): View
    {
        $report = $suggestions->build(
            (int) session('active_fiscal_year_id'),
            (int) session('active_fund_source_id'),
        );

        return view('livewire.rkas-planning-suggestion-tables', [
            'initial' => $report['initial'],
            'remaining' => $report['remaining'],
            'initialPaginator' => $this->paginateRows(collect($report['initial']['rows']), 'initial_page'),
            'remainingPaginator' => $this->paginateRows(collect($report['remaining']['rows']), 'remaining_page'),
        ]);
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    private function paginateRows(Collection $rows, string $pageName): LengthAwarePaginator
    {
        $lastPage = max(1, (int) ceil($rows->count() / self::PER_PAGE));
        $requestedPage = $this->getPage($pageName);
        $currentPage = min(max(1, $requestedPage), $lastPage);

        if ($currentPage !== $requestedPage) {
            $this->setPage($currentPage, $pageName);
        }

        return new LengthAwarePaginator(
            $rows->forPage($currentPage, self::PER_PAGE)->values(),
            $rows->count(),
            self::PER_PAGE,
            $currentPage,
            ['path' => Paginator::resolveCurrentPath(), 'pageName' => $pageName],
        );
    }
}
