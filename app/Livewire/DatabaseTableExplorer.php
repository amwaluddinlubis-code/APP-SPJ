<?php

namespace App\Livewire;

use App\Services\SchoolDatabaseManager;
use App\Services\SchoolDatabaseTableGuide;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class DatabaseTableExplorer extends Component
{
    use WithPagination;

    public array $tables = [];

    public string $database = 'school';

    public string $search = '';

    public string $sort = 'name';

    public string $direction = 'asc';

    public int $perPage = 15;

    public ?string $selectedTable = null;

    public ?array $detail = null;

    public function mount(array $tables = [], ?string $initialTable = null, string $database = 'school'): void
    {
        $this->tables = $tables;
        $this->database = in_array($database, ['school', 'central'], true) ? $database : 'school';
        if ($initialTable !== null) {
            $this->openTable($initialTable);
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage('databaseTablePage');
    }

    public function updatedPerPage(): void
    {
        $this->resetPage('databaseTablePage');
    }

    public function setPage(int $page, string $pageName = 'databaseTablePage'): void
    {
        $this->paginators[$pageName] = max(1, $page);
    }

    public function previousTablePage(): void
    {
        $this->previousPage('databaseTablePage');
    }

    public function nextTablePage(): void
    {
        $this->nextPage('databaseTablePage');
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, ['name', 'rows'], true)) {
            return;
        }

        if ($this->sort === $field) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $field;
            $this->direction = 'asc';
        }

        $this->resetPage('databaseTablePage');
    }

    public function openTable(string $name): void
    {
        if (! collect($this->tables)->contains(fn (array $table): bool => $table['name'] === $name)) {
            return;
        }

        $this->selectedTable = $name;
        $manager = app(SchoolDatabaseManager::class);
        $active = $manager->activeInfo();
        if ($this->database === 'school' && ! $active['school']) {
            return;
        }

        try {
            $schema = $this->database === 'central'
                ? $manager->centralTableSchema($name)
                : $manager->tableSchema($active['school'], $name);
            $data = $this->database === 'central'
                ? $manager->centralTableData($name, 10)
                : $manager->tableData($active['school'], $name, 10);
            $this->detail = [
                'name' => $name,
                'meta' => app(SchoolDatabaseTableGuide::class)->describe($name),
                'total' => $data->total(),
                'columns' => collect($schema)->map(fn (object $column): array => [
                    'name' => $column->name,
                    'type' => $column->type,
                    'required' => (bool) $column->notnull,
                    'pk' => (bool) $column->pk,
                ])->values()->all(),
                'rows' => $data->getCollection()->map(fn (object $row): array => (array) $row)->values()->all(),
            ];
        } catch (\Throwable) {
            $this->detail = null;
        }
    }

    public function closeTable(): void
    {
        $this->selectedTable = null;
        $this->detail = null;
    }

    public function render(): View
    {
        $filtered = collect($this->tables)
            ->filter(function (array $table): bool {
                $needle = strtolower(trim($this->search));
                if ($needle === '') {
                    return true;
                }

                return str_contains(strtolower(implode(' ', [
                    $table['name'] ?? '',
                    $table['label'] ?? '',
                    $table['group'] ?? '',
                    $table['blurb'] ?? '',
                ])), $needle);
            })
            ->sort(function (array $left, array $right): int {
                $a = $this->sort === 'rows' ? (int) ($left['count'] ?? 0) : strtolower((string) ($left['label'] ?? $left['name'] ?? ''));
                $b = $this->sort === 'rows' ? (int) ($right['count'] ?? 0) : strtolower((string) ($right['label'] ?? $right['name'] ?? ''));

                return ($a <=> $b) * ($this->direction === 'asc' ? 1 : -1);
            });

        $pages = max(1, (int) ceil($filtered->count() / $this->perPage));
        $currentPage = max(1, min($this->getPage('databaseTablePage'), $pages));
        $page = $filtered->forPage($currentPage, $this->perPage);

        return view('livewire.database-table-explorer', [
            'pageTables' => $page,
            'total' => $filtered->count(),
            'pages' => $pages,
            'currentPage' => $currentPage,
        ]);
    }
}
