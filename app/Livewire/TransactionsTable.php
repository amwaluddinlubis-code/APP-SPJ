<?php

namespace App\Livewire;

use App\Models\FiscalYear;
use App\Models\Transaction;
use App\Services\SpjTransactionDetailsService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
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

    public bool $showEditor = false;

    public ?int $editingTransactionId = null;

    /** @var array<string, mixed> */
    public array $form = [
        'spj_category' => '',
        'payment_description' => '',
        'payment_method' => '',
        'payment_reference' => '',
        'receipt_recipient_name' => '',
    ];

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

    public function edit(int $transactionId): void
    {
        $transaction = $this->findActiveTransaction($transactionId);

        if (! $transaction) {
            $this->dispatch('app-notify', type: 'error', message: 'Transaksi tidak ditemukan pada tahun aktif.');

            return;
        }

        if ($transaction->spjPackage && ! $transaction->spjPackage->isEditable()) {
            $this->dispatch('app-notify', type: 'error', message: 'Transaksi dikunci karena paket SPJ sudah bernomor atau final.');

            return;
        }

        $this->editingTransactionId = $transaction->id;
        $this->form = [
            'spj_category' => (string) ($transaction->spj_category ?: ''),
            'payment_description' => (string) ($transaction->payment_description ?: $transaction->description ?: ''),
            'payment_method' => (string) ($transaction->payment_method ?: ''),
            'payment_reference' => (string) ($transaction->payment_reference ?: ''),
            'receipt_recipient_name' => (string) ($transaction->receipt_recipient_name ?: $transaction->effective_receipt_recipient_name ?: ''),
        ];
        $this->resetValidation();
        $this->showEditor = true;
    }

    public function closeEditor(): void
    {
        $this->showEditor = false;
        $this->editingTransactionId = null;
        $this->resetValidation();
    }

    public function save(SpjTransactionDetailsService $details): void
    {
        $transaction = $this->findActiveTransaction($this->editingTransactionId);

        if (! $transaction) {
            $this->dispatch('app-notify', type: 'error', message: 'Transaksi tidak ditemukan pada tahun aktif.');
            $this->closeEditor();

            return;
        }

        if ($transaction->spjPackage && ! $transaction->spjPackage->isEditable()) {
            $this->dispatch('app-notify', type: 'error', message: 'Transaksi dikunci karena paket SPJ sudah bernomor atau final.');
            $this->closeEditor();

            return;
        }

        $data = $this->validate([
            'form.spj_category' => ['nullable', Rule::in(['BARANG', 'KONSUMSI', 'PEMELIHARAAN', 'JASA_LAINNYA', 'SPPD', 'HONOR_PEGAWAI'])],
            'form.payment_description' => ['nullable', 'string', 'max:4000'],
            'form.payment_method' => ['nullable', Rule::in(['transfer_bank', 'siplah', 'tunai'])],
            'form.payment_reference' => ['nullable', 'string', 'max:160'],
            'form.receipt_recipient_name' => ['nullable', 'string', 'max:255'],
        ])['form'];

        $transaction->update([
            'spj_category' => blank($data['spj_category'] ?? null) ? null : $data['spj_category'],
            'payment_description' => blank($data['payment_description'] ?? null) ? null : trim($data['payment_description']),
            'payment_method' => blank($data['payment_method'] ?? null) ? null : trim($data['payment_method']),
            'payment_reference' => blank($data['payment_reference'] ?? null) ? null : trim($data['payment_reference']),
            'receipt_recipient_name' => blank($data['receipt_recipient_name'] ?? null) ? null : trim($data['receipt_recipient_name']),
        ]);

        $transaction->load('items');
        $details->synchronize($transaction, $data);

        $this->dispatch('app-notify', type: 'success', message: 'Data SPJ transaksi berhasil disimpan.');
        $this->closeEditor();
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
        return Cache::remember($this->cacheKey('transaction-status-labels'), 60, function (): Collection {
            return (clone $this->baseQuery())
                ->whereNotNull('status')
                ->distinct()
                ->pluck('status')
                ->map(fn ($status) => $this->transactionStatusLabel((string) $status))
                ->unique()
                ->sort()
                ->values();
        });
    }

    public function getTransactionsProperty(): LengthAwarePaginator
    {
        $query = $this->filteredQuery()
            ->with('spjPackage:id,transaction_id,document_number,status,finalized_at')
            ->withCount('items');

        $perPage = $this->perPage === 'all' ? 100 : (int) $this->perPage;
        $perPage = in_array($perPage, [15, 25, 50, 100], true) ? $perPage : 15;

        // Work queue operator: transaksi yang belum memiliki deskripsi SPJ selalu
        // tampil paling atas. Setelah itu transaksi yang siap dibuka, draft paket,
        // bernomor, final, lalu yang dibatalkan. ID menjaga urutan stabil di tiap grup.
        $paginator = $query
            ->orderByRaw("CASE
                WHEN payment_description IS NULL OR TRIM(payment_description) = '' THEN 0
                WHEN EXISTS (SELECT 1 FROM spj_packages WHERE spj_packages.transaction_id = transactions.id AND spj_packages.status IN ('CANCELLED', 'CANCELED')) THEN 5
                WHEN EXISTS (SELECT 1 FROM spj_packages WHERE spj_packages.transaction_id = transactions.id AND (spj_packages.finalized_at IS NOT NULL OR spj_packages.status IN ('FINAL', 'ARCHIVED', 'ARSIP'))) THEN 4
                WHEN EXISTS (SELECT 1 FROM spj_packages WHERE spj_packages.transaction_id = transactions.id AND spj_packages.document_number IS NOT NULL) THEN 3
                WHEN EXISTS (SELECT 1 FROM spj_packages WHERE spj_packages.transaction_id = transactions.id) THEN 2
                ELSE 1
            END ASC")
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
        if (blank($transaction->payment_description)) {
            return ['status' => 'DRAFT', 'label' => 'Perlu deskripsi'];
        }

        $package = $transaction->spjPackage;
        $packageStatus = strtoupper((string) ($package?->status ?? ''));

        if (in_array($packageStatus, ['CANCELLED', 'CANCELED'], true)) {
            return ['status' => 'CANCELLED', 'label' => 'Dibatalkan'];
        }

        if ($package?->finalized_at || in_array($packageStatus, ['FINAL', 'ARCHIVED', 'ARSIP'], true)) {
            return ['status' => 'FINAL', 'label' => 'Final'];
        }

        if (filled($package?->document_number)) {
            return ['status' => 'NUMBERED', 'label' => 'Bernomor'];
        }

        if ($package) {
            return ['status' => 'DRAFT', 'label' => 'Draft paket'];
        }

        return ['status' => 'READY', 'label' => 'Siap detail'];
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
            $query->whereIn('status', $this->databaseStatusesForFilter($this->status));
        }

        return $query;
    }

    private function findActiveTransaction(?int $transactionId): ?Transaction
    {
        if (! $transactionId) {
            return null;
        }

        return Transaction::query()->with('spjPackage')->activeContext()->find($transactionId);
    }

    private function activeYear(): FiscalYear
    {
        return Cache::remember($this->cacheKey('active-year'), 300, fn () => FiscalYear::query()->findOrFail(session('active_fiscal_year_id')));
    }

    private function cacheKey(string $reference): string
    {
        return implode(':', ['school', session('active_school_id'), 'year', session('active_fiscal_year_id'), $reference]);
    }

    private function transactionStatusLabel(string $status): string
    {
        return match (strtoupper(trim($status))) {
            'DRAFT', 'BELUM_LENGKAP' => 'Belum lengkap',
            'READY', 'SIAP', 'DISIAPKAN' => 'Siap diproses',
            'NUMBERED', 'BERNOMOR' => 'Sudah bernomor',
            'PRINTED', 'DICETAK' => 'Sudah dicetak',
            'FINAL', 'ARCHIVED', 'ARSIP' => 'Final',
            'CANCELLED', 'CANCELED' => 'Dibatalkan',
            default => str($status)->replace('_', ' ')->lower()->ucfirst()->toString(),
        };
    }

    /** @return array<int,string> */
    private function databaseStatusesForFilter(string $filter): array
    {
        $normalized = trim($filter);
        $map = [
            'Belum lengkap' => ['DRAFT', 'BELUM_LENGKAP'],
            'Siap diproses' => ['READY', 'SIAP', 'DISIAPKAN'],
            'Sudah bernomor' => ['NUMBERED', 'BERNOMOR'],
            'Sudah dicetak' => ['PRINTED', 'DICETAK'],
            'Final' => ['FINAL', 'ARCHIVED', 'ARSIP'],
            'Dibatalkan' => ['CANCELLED', 'CANCELED'],
        ];

        return $map[$normalized] ?? [$normalized];
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
