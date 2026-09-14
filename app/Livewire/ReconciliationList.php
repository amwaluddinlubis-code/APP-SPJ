<?php

namespace App\Livewire;

use App\Models\Transaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Read-only reconciliation queue: filter, search, and pagination state only. No persistence mutation. */
class ReconciliationList extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $filter = '';

    #[Url(except: '')]
    public string $q = '';

    #[Url(except: 15)]
    public int|string $perPage = 15;

    public function mount(): void
    {
        $this->filter = trim((string) request('filter'));
        $this->q = trim((string) request('q'));
        $requestedPerPage = request('perPage');

        if ($requestedPerPage === 'all') {
            $this->perPage = 'all';
        } elseif (in_array((int) $requestedPerPage, [15, 25, 50, 100], true)) {
            $this->perPage = (int) $requestedPerPage;
        }
    }

    public function updating($property): void
    {
        if (in_array($property, ['filter', 'q', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['filter', 'q']);
        $this->resetPage();
    }

    /** @return array{total:int,changed:int,missing:int,with_package:int} */
    public function getSummaryProperty(): array
    {
        $baseQuery = $this->baseQuery();

        return [
            'total' => (clone $baseQuery)->count(),
            'changed' => (clone $baseQuery)->where('requires_reconciliation', true)->count(),
            'missing' => (clone $baseQuery)->where('source_status', 'SOURCE_MISSING')->count(),
            'with_package' => (clone $baseQuery)->whereHas('spjPackage')->count(),
        ];
    }

    public function getTransactionsProperty(): LengthAwarePaginator
    {
        $query = $this->filteredQuery()
            ->with(['spjPackage:id,transaction_id,document_number,status'])
            ->withCount('items');

        $perPage = $this->perPage === 'all' ? 100 : (int) $this->perPage;
        $perPage = in_array($perPage, [15, 25, 50, 100], true) ? $perPage : 15;

        $paginator = $query
            ->orderByRaw("CASE WHEN source_status = 'SOURCE_MISSING' THEN 0 ELSE 1 END")
            ->orderByDesc('requires_reconciliation')
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->paginate($perPage);

        if ($paginator->total() > 0 && $paginator->currentPage() > $paginator->lastPage()) {
            $lastPage = $paginator->lastPage();
            $this->paginators['page'] = $lastPage;

            return $query->paginate($perPage, ['*'], 'page', $lastPage);
        }

        return $paginator;
    }

    public function render(): View
    {
        return view('livewire.reconciliation-list', [
            'summary' => $this->summary,
            'transactions' => $this->transactions,
        ]);
    }

    private function baseQuery(): Builder
    {
        return Transaction::query()
            ->activeContext()
            ->where(function ($query): void {
                $query->where('requires_reconciliation', true)
                    ->orWhere('source_status', 'SOURCE_MISSING');
            });
    }

    private function filteredQuery(): Builder
    {
        $query = clone $this->baseQuery();

        if ($this->filter === 'changed') {
            $query->where('requires_reconciliation', true);
        } elseif ($this->filter === 'missing') {
            $query->where('source_status', 'SOURCE_MISSING');
        } elseif ($this->filter === 'with_package') {
            $query->whereHas('spjPackage');
        }

        if (trim($this->q) !== '') {
            $search = trim($this->q);
            $query->where(function ($query) use ($search): void {
                $query->where('no_bukti', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('payment_description', 'like', "%{$search}%")
                    ->orWhere('recipient_name', 'like', "%{$search}%")
                    ->orWhere('receipt_recipient_name', 'like', "%{$search}%");
            });
        }

        return $query;
    }
}
