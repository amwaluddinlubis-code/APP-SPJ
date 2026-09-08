<?php

namespace App\Livewire;

use App\Models\FiscalYear;
use App\Models\Transaction;
use App\Services\SpjWorkflowFilterService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class TransactionsTable extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $q = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: null)]
    public ?int $month = null;

    #[Url(except: null)]
    public ?int $quarter = null;

    #[Url(except: null)]
    public ?int $semester = null;

    #[Url(except: 15)]
    public int|string $perPage = 15;

    public function mount(): void
    {
        $this->q = trim((string) request('q'));
        $this->status = trim((string) request('status'));
        $this->month = request()->integer('month') ?: null;
        $this->quarter = request()->integer('quarter') ?: null;
        $this->semester = request()->integer('semester') ?: null;
        $requestedPerPage = request('perPage');

        if ($requestedPerPage === 'all') {
            $this->perPage = 'all';
        } elseif (in_array((int) $requestedPerPage, [15, 25, 50, 100], true)) {
            $this->perPage = (int) $requestedPerPage;
        }
    }

    public function updating($property): void
    {
        if (in_array($property, ['q', 'status', 'month', 'quarter', 'semester', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['q', 'status', 'month', 'quarter', 'semester']);
        $this->resetPage();
    }

    public function getStatsProperty(): object
    {
        return (clone $this->baseQuery())
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(gross_amount), 0) as gross, COALESCE(SUM(tax_total), 0) as tax, COALESCE(SUM(net_amount), 0) as net')
            ->first();
    }

    public function getFilteredStatsProperty(): object
    {
        return (clone $this->filteredQuery())
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(gross_amount), 0) as gross, COALESCE(SUM(tax_total), 0) as tax, COALESCE(SUM(net_amount), 0) as net')
            ->first();
    }

    public function getStatusesProperty(): Collection
    {
        return $this->workflowFilters()->labels();
    }

    public function getTransactionsProperty(): LengthAwarePaginator
    {
        $query = $this->filteredQuery()
            ->with('spjPackage:id,transaction_id,document_number,status,finalized_at')
            ->withCount('items');

        $perPage = $this->perPage === 'all' ? 100 : (int) $this->perPage;
        $perPage = in_array($perPage, [15, 25, 50, 100], true) ? $perPage : 15;

        $paginator = $query
            ->orderByRaw("CASE WHEN source_status = 'SOURCE_MISSING' OR requires_reconciliation = 1 THEN 0 ELSE 1 END")
            ->orderByRaw("COALESCE((SELECT CASE status WHEN 'DRAFT' THEN 0 WHEN 'READY' THEN 2 WHEN 'NUMBERED' THEN 3 WHEN 'FINAL' THEN 4 ELSE 5 END FROM spj_packages WHERE spj_packages.transaction_id = transactions.id LIMIT 1), 1)")
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
        return view('livewire.transactions-table', [
            'activeYear' => $this->activeYear(),
            'stats' => $this->stats,
            'filteredStats' => $this->filteredStats,
            'statuses' => $this->statuses,
            'transactions' => $this->transactions,
        ]);
    }

    /** @return array{status:string,label:string} */
    public function workStatusFor(Transaction $transaction): array
    {
        if ($transaction->source_status === 'SOURCE_MISSING') {
            return ['status' => 'SOURCE_MISSING', 'label' => 'Perlu Perhatian'];
        }

        if ($transaction->requires_reconciliation) {
            return ['status' => 'RECONCILIATION', 'label' => 'Perlu Perhatian'];
        }

        $package = $transaction->spjPackage;
        $packageStatus = strtoupper((string) ($package?->status ?? ''));

        if (in_array($packageStatus, ['CANCELLED', 'CANCELED'], true)) {
            return ['status' => 'CANCELLED', 'label' => 'Dibatalkan'];
        }

        if ($package?->finalized_at || in_array($packageStatus, ['FINAL', 'ARCHIVED', 'ARSIP'], true)) {
            return ['status' => 'FINAL', 'label' => 'Final'];
        }

        if (filled($package?->document_number) || in_array($packageStatus, ['NUMBERED', 'BERNOMOR'], true)) {
            return ['status' => 'NUMBERED', 'label' => 'Sudah Bernomor'];
        }

        if ($packageStatus === 'READY') {
            return ['status' => 'READY', 'label' => 'Siap Dinomori'];
        }

        if ($package) {
            return ['status' => 'DRAFT', 'label' => 'Perlu Dilengkapi'];
        }

        return ['status' => 'BELUM_LENGKAP', 'label' => 'Belum Dikerjakan'];
    }

    private function baseQuery(): Builder
    {
        return Transaction::query()->activeContext();
    }

    private function filteredQuery(): Builder
    {
        $activeYear = $this->activeYear();
        $query = clone $this->baseQuery();

        if ($this->month) {
            $query->whereMonth('transaction_date', $this->month);
        } elseif ($this->quarter) {
            $query->whereBetween('transaction_date', [
                now()->setYear($activeYear->year)->setMonth(($this->quarter - 1) * 3 + 1)->startOfMonth(),
                now()->setYear($activeYear->year)->setMonth($this->quarter * 3)->endOfMonth(),
            ]);
        } elseif ($this->semester) {
            $query->whereBetween('transaction_date', [
                now()->setYear($activeYear->year)->setMonth($this->semester === 1 ? 1 : 7)->startOfMonth(),
                now()->setYear($activeYear->year)->setMonth($this->semester === 1 ? 6 : 12)->endOfMonth(),
            ]);
        }

        if (trim($this->q) !== '') {
            $search = trim($this->q);
            $query->where(function (Builder $query) use ($search): void {
                $query->where('no_bukti', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('payment_description', 'like', "%{$search}%")
                    ->orWhere('recipient_name', 'like', "%{$search}%")
                    ->orWhere('receipt_recipient_name', 'like', "%{$search}%")
                    ->orWhere('activity_code', 'like', "%{$search}%")
                    ->orWhere('account_code', 'like', "%{$search}%");
            });
        }

        if ($this->status !== '') {
            $state = $this->workflowFilters()->stateForLabel($this->status);
            if ($state) {
                $this->workflowFilters()->apply($query, $state);
            }
        }

        return $query;
    }

    private function activeYear(): FiscalYear
    {
        return Cache::remember($this->cacheKey('active-year'), 300, fn () => FiscalYear::query()->findOrFail(session('active_fiscal_year_id')));
    }

    private function cacheKey(string $reference): string
    {
        return implode(':', ['school', session('active_school_id'), 'year', session('active_fiscal_year_id'), $reference]);
    }

    private function workflowFilters(): SpjWorkflowFilterService
    {
        return app(SpjWorkflowFilterService::class);
    }

    public function paymentMethodFor(Transaction $transaction): string
    {
        $current = strtolower((string) $transaction->payment_method);

        if (in_array($current, ['transfer_bank', 'siplah', 'tunai'], true)) {
            return $current;
        }

        if ($transaction->is_siplah) {
            return 'siplah';
        }

        $proofNumber = strtolower((string) $transaction->no_bukti);
        if (str_contains($proofNumber, 'non_tunai') || str_contains($proofNumber, 'non tunai') || str_starts_with($proofNumber, 'bnu')) {
            return 'transfer_bank';
        }

        if (str_contains($current, 'non tunai') || str_contains($current, 'transfer') || str_contains($current, 'cms')) {
            return 'transfer_bank';
        }

        return 'tunai';
    }
}
