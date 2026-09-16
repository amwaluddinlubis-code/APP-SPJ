<?php

namespace App\Livewire;

use App\Services\AuditReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class AuditReportWorkspace extends Component
{
    use WithPagination;

    #[Url(except: 'overview')]
    public string $tab = 'overview';

    #[Url(as: 'reconciliation_perPage', except: 15)]
    public int $reconciliationPerPage = 15;

    #[Url(as: 'register_perPage', except: 15)]
    public int $registerPerPage = 15;

    #[Url(as: 'completeness_perPage', except: 15)]
    public int $completenessPerPage = 15;

    #[Url(as: 'history_perPage', except: 15)]
    public int $historyPerPage = 15;

    public function mount(): void
    {
        $this->tab = in_array(request('tab'), $this->tabs(), true) ? (string) request('tab') : 'overview';

        foreach ($this->perPageParameters() as $property => $queryParameter) {
            $this->{$property} = $this->normalizePerPage(request($queryParameter, $this->{$property}));
        }
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, $this->tabs(), true)) {
            $this->tab = $tab;
        }
    }

    public function updating($property): void
    {
        $pageName = $this->pageNameForPerPageProperty((string) $property);

        if ($pageName !== null) {
            $this->resetPage($pageName);
        }
    }

    public function render(): View
    {
        $report = app(AuditReportService::class)->build();

        return view('livewire.audit-report-workspace', [
            ...$report,
            'reconciliationRows' => $this->paginate($report['reconciliationRows'], $this->reconciliationPerPage, 'reconciliation_page'),
            'register' => $this->paginate($report['register'], $this->registerPerPage, 'register_page'),
            'completenessRows' => $this->paginate($report['completenessRows'], $this->completenessPerPage, 'completeness_page'),
            'syncRuns' => $this->paginate($report['syncRuns'], $this->historyPerPage, 'history_page'),
        ]);
    }

    /** @return list<string> */
    private function tabs(): array
    {
        return ['overview', 'reconciliation', 'register', 'tax', 'completeness', 'history'];
    }

    /** @return array<string,string> */
    private function perPageParameters(): array
    {
        return [
            'reconciliationPerPage' => 'reconciliation_perPage',
            'registerPerPage' => 'register_perPage',
            'completenessPerPage' => 'completeness_perPage',
            'historyPerPage' => 'history_perPage',
        ];
    }

    private function pageNameForPerPageProperty(string $property): ?string
    {
        return match ($property) {
            'reconciliationPerPage' => 'reconciliation_page',
            'registerPerPage' => 'register_page',
            'completenessPerPage' => 'completeness_page',
            'historyPerPage' => 'history_page',
            default => null,
        };
    }

    private function normalizePerPage(mixed $value): int
    {
        $value = (int) $value;

        return in_array($value, [10, 15, 25, 50, 100], true) ? $value : 15;
    }

    private function paginate(Collection $items, int $perPage, string $pageName): LengthAwarePaginator
    {
        $page = $this->getPage($pageName);

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'pageName' => $pageName]
        );
    }
}
