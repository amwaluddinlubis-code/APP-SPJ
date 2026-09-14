<?php

namespace App\Livewire;

use App\Models\Employee;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class EmployeeDirectory extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $source = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: 15)]
    public int|string $perPage = 15;

    public function mount(): void
    {
        $this->search = trim((string) request('q', request('search', '')));
        $source = (string) request('source', '');
        $this->source = in_array($source, ['ARKAS', 'DAPODIK', 'MANUAL'], true) ? $source : '';
        $status = (string) request('status', '');
        $this->status = in_array($status, ['active', 'inactive'], true) ? $status : '';
        $this->perPage = $this->normalizePerPage(request('perPage', 15));
    }

    public function updating($property): void
    {
        if (in_array($property, ['search', 'source', 'status', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'source', 'status', 'perPage']);
        $this->resetPage();
    }

    public function render(): View
    {
        $employees = Employee::query()
            ->search($this->search !== '' ? $this->search : null)
            ->when($this->source === 'ARKAS', fn ($query) => $query->fromArkas())
            ->when($this->source === 'DAPODIK', fn ($query) => $query->fromDapodik())
            ->when($this->source === 'MANUAL', fn ($query) => $query->manualOnly())
            ->when($this->status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($this->status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($this->resolvedPerPage());

        return view('livewire.employee-directory', compact('employees'));
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
