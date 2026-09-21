<?php

namespace App\UseCases\Spj;

use App\Models\Employee;
use App\Models\FiscalPeriodClosure;
use App\Models\SpjFreshPackage;
use App\Models\SpjFreshTransaction;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Services\SpjFreshPackageValidationService;
use App\Services\SpjPackageTemplateSelector;
use App\Services\SpjPackageValidationService;
use App\Services\SpjV2MutationContextService;
use App\Services\SpjV2NumberingAuthorizationService;
use App\Services\SpjV2PackageReadContextService;
use App\Services\SpjV2PackageReadMembershipService;
use App\Services\SpjWorkflowFilterService;
use App\Support\ActiveSpjContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as ConcretePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SpjWorkspaceUseCase
{
    public function __construct(
        private readonly SpjWorkflowFilterService $workflowFilters,
        private readonly ActiveSpjContext $context,
        private readonly SpjPackageTemplateSelector $templateSelector,
        private readonly SpjV2PackageReadContextService $packageReadContext,
        private readonly SpjV2PackageReadMembershipService $packageReadMembership,
        private readonly SpjV2MutationContextService $mutationContext,
        private readonly SpjV2NumberingAuthorizationService $numberingAuthorization,
    ) {}

    public function handle(Request $request): View|RedirectResponse
    {
        $tab = $request->query('tab', 'persiapan');

        return match ($tab) {
            'persiapan' => $this->tabPersiapan($request),
            'paket' => $this->tabPaket($request),
            'laporan' => app(SpjReportUseCase::class)->tabLaporan($request),
            'monitoring' => app(SpjReportUseCase::class)->tabMonitoring($request),
            default => $this->tabPersiapan($request),
        };
    }

    public function overviewMetrics(): array
    {
        $packages = $this->packageMembershipQuery();

        if (! Transaction::query()->forSpjContext($this->context)->exists()) {
            $freshTransactions = SpjFreshTransaction::query()->forSpjContext($this->context);
            $freshPackages = DB::connection('school')->table('spj_fresh_packages')
                ->join('spj_fresh_transactions', 'spj_fresh_transactions.id', '=', 'spj_fresh_packages.spj_fresh_transaction_id')
                ->where('spj_fresh_transactions.fiscal_year_id', $this->context->fiscalYearId())
                ->where('spj_fresh_transactions.fund_source_id', $this->context->fundSourceId());

            return [
                'totalPackages' => (clone $freshPackages)->count(),
                'numberedPackages' => (clone $freshPackages)->whereNotNull('document_number')->count(),
                'readyTransactions' => (clone $freshTransactions)->has('items')->count(),
            ];
        }

        return [
            'totalPackages' => (clone $packages)->count(),
            'numberedPackages' => (clone $packages)->whereNotNull('document_number')->count(),
            'readyTransactions' => Transaction::query()->forSpjContext($this->context)->has('items')->count(),
        ];
    }

    public function participantRoster()
    {
        $statusId = static fn (Employee $employee): int => is_numeric($employee->payload['status_kepegawaian_id'] ?? null)
            ? (int) $employee->payload['status_kepegawaian_id']
            : PHP_INT_MAX;

        // Roster melayani semua operator (ARKAS-only maupun Dapodik-only):
        // seluruh pegawai aktif tanpa filter sumber, identitas sudah menyatu.
        return Employee::query()->where('is_active', true)->orderBy('name')
            ->get(['id', 'name', 'position', 'staff_type', 'source_type', 'nip', 'nuptk', 'payload'])
            ->sortBy(fn (Employee $employee) => sprintf('%s-%d-%d', mb_strtolower(trim($employee->name)), $employee->source_type === 'DAPODIK' ? 0 : 1, filled($employee->nuptk) ? 0 : 1))
            ->unique(fn (Employee $employee) => mb_strtolower(trim($employee->name)))
            ->sortBy(fn (Employee $employee) => sprintf('%010d-%s', $statusId($employee), mb_strtolower(trim($employee->name))))
            ->values();
    }

    /** @return array<string, array<int, string>> */
    public static function preparationFilterRules(): array
    {
        return [
            'month' => ['nullable', 'integer', 'between:1,12'],
            'quarter' => ['nullable', 'integer', 'between:1,4'],
            'spj_category' => ['nullable', 'string', 'max:40'],
            'state' => ['nullable', 'in:all,attention,needs_details,unprepared,draft,ready,numbered'],
        ];
    }

    /**
     * Query antrean persiapan dari filter eksplisit memakai implementasi yang
     * sama dengan jalur HTTP, untuk dipakai komponen Livewire.
     *
     * @return array{transactions: LengthAwarePaginator, workQueueCounts: array<string, int>, spjTypes: Collection}
     */
    public function preparationData(array $filters, int $perPage, ?int $page = null): array
    {
        if (! Transaction::query()->forSpjContext($this->context)->exists()) {
            return $this->freshPreparationData($filters, $perPage, $page);
        }

        $month = isset($filters['month']) && $filters['month'] !== null ? (int) $filters['month'] : null;
        $quarter = isset($filters['quarter']) && $filters['quarter'] !== null ? (int) $filters['quarter'] : null;

        $query = Transaction::query()->forSpjContext($this->context)
            ->when($month, fn ($q, $selectedMonth) => $q->whereMonth('transaction_date', $selectedMonth))
            ->when(! $month && $quarter, function ($q) use ($quarter): void {
                $q->whereMonth('transaction_date', '>=', (($quarter - 1) * 3) + 1)
                    ->whereMonth('transaction_date', '<=', $quarter * 3);
            })
            ->when($filters['spj_category'] ?? null, fn ($q, $type) => $q->where('spj_category', $type));

        $workQueueCounts = ['all' => (clone $query)->count()];
        foreach (array_keys($this->workflowFilters->options()) as $state) {
            $workQueueCounts[$state] = $this->workflowFilters->apply(clone $query, $state)->count();
        }
        $workQueueCounts['needs_details'] = $workQueueCounts['attention'];

        $this->workflowFilters->apply($query, $filters['state'] ?? 'all');

        $transactions = $query
            ->with('spjPackage')->withCount('items')
            ->orderByRaw("CASE WHEN source_status = 'SOURCE_MISSING' OR requires_reconciliation = 1 THEN 0 ELSE 1 END")
            ->orderByRaw("COALESCE((SELECT CASE status WHEN 'DRAFT' THEN 0 WHEN 'READY' THEN 2 WHEN 'NUMBERED' THEN 3 WHEN 'FINAL' THEN 4 ELSE 5 END FROM spj_packages WHERE spj_packages.transaction_id = transactions.id LIMIT 1), 1)")
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->paginate($perPage)->withQueryString();

        return [
            'transactions' => $transactions,
            'workQueueCounts' => $workQueueCounts,
            'spjTypes' => Transaction::query()->forSpjContext($this->context)->whereNotNull('spj_category')->where('spj_category', '!=', '')->distinct()->orderBy('spj_category')->pluck('spj_category'),
        ];
    }

    /**
     * Read-only compatibility queue used after a database reset when the
     * explicit fresh projection exists but the legacy transaction table does
     * not. This path never creates a legacy transaction or package.
     *
     * @param  array{month:int|null,quarter:int|null,spj_category:string|null,state:string}  $filters
     * @return array{transactions: LengthAwarePaginator, workQueueCounts: array<string, int>, spjTypes: Collection}
     */
    private function freshPreparationData(array $filters, int $perPage, ?int $page = null): array
    {
        $allRows = SpjFreshTransaction::query()
            ->forSpjContext($this->context)
            ->whereIn('source_status', ['ACTIVE', 'SOURCE_MISSING'])
            ->with(['rawMirrorRow', 'items.rawMirrorRow', 'spjPackage'])
            ->withCount('items')
            ->get();

        $filteredRows = $allRows
            ->filter(function (SpjFreshTransaction $transaction) use ($filters): bool {
                $month = $transaction->transaction_date?->month;
                if ($filters['month'] !== null && $month !== $filters['month']) {
                    return false;
                }
                if ($filters['quarter'] !== null && (int) ceil(($month ?: 1) / 3) !== $filters['quarter']) {
                    return false;
                }
                if ($filters['spj_category'] !== null && (string) $transaction->spj_category !== $filters['spj_category']) {
                    return false;
                }

                return $this->freshTransactionMatchesState($transaction, (string) $filters['state']);
            })
            ->sortBy(fn (SpjFreshTransaction $transaction): string => sprintf(
                '%d-%s-%010d',
                $transaction->source_status === 'SOURCE_MISSING' || $transaction->requires_reconciliation ? 0 : 1,
                (string) ($transaction->transaction_date?->toDateString() ?? ''),
                (int) $transaction->id,
            ))
            ->values();

        $counts = ['all' => $allRows->count(), 'attention' => 0, 'unprepared' => 0, 'draft' => 0, 'ready' => 0, 'numbered' => 0];
        foreach ($allRows as $transaction) {
            foreach (array_keys($this->workflowFilters->options()) as $state) {
                if ($this->freshTransactionMatchesState($transaction, $state)) {
                    $counts[$state]++;
                }
            }
        }
        $counts['needs_details'] = $counts['attention'];
        $page = max(1, $page ?? (int) request()->query('page', 1));

        return [
            'transactions' => new ConcretePaginator(
                $filteredRows->slice(($page - 1) * $perPage, $perPage)->values(),
                $filteredRows->count(),
                $perPage,
                $page,
                ['path' => request()->url(), 'query' => request()->query()],
            ),
            'workQueueCounts' => $counts,
            'spjTypes' => $allRows->pluck('spj_category')->filter()->unique()->sort()->values(),
        ];
    }

    private function freshTransactionMatchesState(SpjFreshTransaction $transaction, string $state): bool
    {
        if ($state === 'all') {
            return true;
        }
        if (in_array($state, ['attention', 'needs_details'], true)) {
            return (bool) $transaction->requires_reconciliation || $transaction->source_status === 'SOURCE_MISSING';
        }

        $status = strtoupper((string) ($transaction->spjPackage?->status ?? ''));

        return match ($state) {
            'unprepared' => $transaction->spjPackage === null,
            'draft' => $status === 'DRAFT',
            'ready' => $status === 'READY',
            'numbered' => in_array($status, ['NUMBERED', 'FINAL'], true),
            default => true,
        };
    }

    /**
     * @param  array{search?:string,status?:string,category?:string}  $filters
     */
    public function packageListData(int $perPage, array $filters = []): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = strtoupper(trim((string) ($filters['status'] ?? '')));
        $category = strtoupper(trim((string) ($filters['category'] ?? '')));

        if (! SpjPackage::query()->whereHas('transaction', fn ($query) => $query->forSpjContext($this->context))->exists()) {
            $fresh = SpjFreshPackage::query()
                ->whereHas('transaction', fn ($query) => $query->forSpjContext($this->context))
                ->with(['transaction.rawMirrorRow', 'transaction.items.rawMirrorRow'])
                ->when(in_array($status, ['DRAFT', 'READY', 'NUMBERED', 'FINAL', 'CANCELLED'], true), fn ($query) => $query->where('status', $status))
                ->when($category !== '' || $search !== '', function ($query) use ($category, $search): void {
                    $query->whereHas('transaction', function ($transactionQuery) use ($category, $search): void {
                        $transactionQuery
                            ->when($category !== '', fn ($q) => $q->where('spj_category', $category))
                            ->when($search !== '', function ($q) use ($search): void {
                                $q->where(function ($searchQuery) use ($search): void {
                                    $searchQuery->where('no_bukti', 'like', '%'.$search.'%')
                                        ->orWhere('payment_description', 'like', '%'.$search.'%')
                                        ->orWhere('description', 'like', '%'.$search.'%')
                                        ->orWhere('recipient_name', 'like', '%'.$search.'%');
                                });
                            });
                    });
                })
                ->orderByDesc('id')
                ->paginate($perPage, ['*'], 'package_page')
                ->withQueryString();
            $fresh->getCollection()->load(['transaction.rawMirrorRow', 'transaction.items.rawMirrorRow']);
            $fresh->getCollection()->each(fn (SpjFreshPackage $package): SpjFreshPackage => $package->setAttribute('read_context_path', 'fresh_compat'));

            return $fresh;
        }

        $paginator = $this->packageMembershipQuery()
            ->with(['transaction:id,no_bukti,transaction_date,payment_description,description,recipient_name,spj_category,gross_amount,fiscal_year_id,fund_source_id'])
            ->when(in_array($status, ['DRAFT', 'READY', 'NUMBERED', 'FINAL', 'CANCELLED'], true), fn ($query) => $query->where('status', $status))
            ->whereHas('transaction', function ($query) use ($search, $category): void {
                $query
                    ->when($category !== '', fn ($transactionQuery) => $transactionQuery->where('spj_category', $category))
                    ->when($search !== '', function ($transactionQuery) use ($search): void {
                        $transactionQuery->where(function ($searchQuery) use ($search): void {
                            $searchQuery->where('no_bukti', 'like', '%'.$search.'%')
                                ->orWhere('payment_description', 'like', '%'.$search.'%')
                                ->orWhere('description', 'like', '%'.$search.'%')
                                ->orWhere('recipient_name', 'like', '%'.$search.'%');
                        });
                    });
            })
            ->orderByRaw("CASE status WHEN 'CANCELLED' THEN 3 WHEN 'FINAL' THEN 2 WHEN 'NUMBERED' THEN 1 ELSE 0 END DESC")
            ->orderByDesc('numbered_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'package_page')
            ->withQueryString();

        $paginator->getCollection()->each(function (SpjPackage $package): void {
            $package->setAttribute(
                'read_context_path',
                $this->context->matchesTransaction($package->transaction) ? 'legacy' : 'v2_compat',
            );
        });

        return $paginator;
    }

    private function packageMembershipQuery(): Builder
    {
        $fundSourceId = $this->context->fundSourceId();
        if ($fundSourceId !== null) {
            $membership = $this->packageReadMembership->forContext(
                DB::connection('school'),
                $this->context->fiscalYearId(),
                $fundSourceId,
            );

            if ($membership !== null) {
                return SpjPackage::query()->whereIn('id', $membership['package_ids']);
            }
        }

        return SpjPackage::query()
            ->whereHas('transaction', fn ($query) => $query->forSpjContext($this->context));
    }

    private function tabPersiapan(Request $request): View
    {
        // Filter Livewire memiliki state/validasi sendiri; antrean + daftar
        // dirender oleh <livewire:spj-preparation-filter />, sehingga query
        // persiapan tidak dihitung di sini agar tidak dikerjakan dua kali.
        $request->validate(static::preparationFilterRules());

        return view('spj.index', [
            'tab' => 'persiapan',
            'transactions' => null,
            ...$this->overviewMetrics(),
            'spjTypes' => [],
            'filters' => [],
            'workQueueCounts' => [],
        ]);
    }

    private function tabPaket(Request $request): View|RedirectResponse
    {
        $packageId = $request->query('package_id');
        $freshPackageId = $request->query('fresh_package_id');

        if (! $packageId && ! $freshPackageId) {
            // Daftar paket dirender oleh <livewire:spj-package-list /> dengan
            // paginasinya sendiri; tidak dihitung di sini agar tidak ganda.
            return view('spj.index', [
                'tab' => 'paket',
                'package' => null,
                'packageList' => null,
                'validationIssues' => [],
                'templates' => collect(),
                'transactions' => null,
                ...$this->overviewMetrics(),
                'spjTypes' => [],
                'filters' => [],
                'periodClosures' => collect(),
                'participantRoster' => collect(),
                'consumptionOrderSources' => [],
            ]);
        }

        if ($freshPackageId) {
            $freshPackage = SpjFreshPackage::query()
                ->with(['transaction.items.rawMirrorRow', 'transaction.rawMirrorRow', 'documents'])
                ->whereKey($freshPackageId)
                ->whereHas('transaction', fn ($query) => $query->forSpjContext($this->context))
                ->first();

            if ($freshPackage) {
                return $this->freshPackageView($freshPackage);
            }

            return redirect()->route('spj.index', ['tab' => 'persiapan'])
                ->with('error', 'Paket fresh tidak ditemukan pada tahun anggaran aktif.');
        }

        $package = SpjPackage::query()->with([
            'documents.template',
            'transaction.items',
            'transaction.goods',
            'transaction.workOrder',
            'transaction.workers',
            'transaction.participants',
            'transaction.travels',
            'transaction.honors',
            'transaction.serviceRecipients',
            'transaction.payments',
            'transaction.goodsReceipts.items',
        ])->find($packageId);
        if (! $package) {
            return redirect()->route('spj.index', ['tab' => 'persiapan'])->with('error', 'Paket dokumen tidak ditemukan pada tahun anggaran aktif.');
        }

        if (! $this->context->matchesTransaction($package->transaction)) {
            if ($request->boolean('edit')
                && $package->isEditable()
                && $this->mutationContext->authorizePackageWrite($package)) {
                return $this->compatibilityEditPackage($package);
            }

            if (! $this->packageReadContext->prepare($package)
                || $package->getAttribute('read_context_path') !== 'v2_compat') {
                return redirect()->route('spj.index', ['tab' => 'persiapan'])->with('error', 'Paket dokumen tidak ditemukan pada tahun anggaran aktif.');
            }

            return $this->readOnlyCompatibilityPackage($package);
        }

        if ($package->transaction->items->isEmpty()
            || $package->transaction->items->contains(fn ($item): bool => blank(trim((string) $item->item_description)))) {
            return app(CreateSpjDraftUseCase::class)->handle((string) $package->transaction_id);
        }

        $validator = app(SpjPackageValidationService::class);
        $participantRoster = collect();
        $consumptionOrderSources = [];

        if (strtoupper((string) $package->transaction->spj_category) === 'KONSUMSI') {
            $participantRoster = $this->participantRoster();
            $consumptionOrderSources = SpjPackage::query()
                ->whereHas('transaction', fn ($query) => $query->forSpjContext($this->context)->where('spj_category', 'KONSUMSI'))
                ->where('id', '!=', $package->id)
                ->with('transaction.participants')
                ->orderByDesc('id')
                ->limit(5)
                ->get()
                ->map(fn (SpjPackage $row) => [
                    'id' => $row->id,
                    'label' => ($row->transaction->no_bukti ?: 'Tanpa bukti').' · '.($row->transaction->transaction_date?->translatedFormat('d M Y') ?: '-').' · '.$row->transaction->participants->count().' peserta',
                    'names' => $row->transaction->participants->map(fn ($participant) => $participant->name)->filter()->values()->all(),
                    'participants' => $row->transaction->participants->map(fn ($participant) => [
                        'name' => $participant->name,
                        'position' => $participant->position,
                        'nip' => $participant->nip,
                        'nuptk' => $participant->nuptk,
                        'portions' => $participant->portions ?: 1,
                    ])->values()->all(),
                ])
                ->filter(fn (array $row) => $row['names'] !== [])
                ->values()
                ->all();
        }
        $validationIssues = $validator->validate($package);
        $templates = $this->templateSelector->forPackage($package);
        $navigation = $this->packageNavigation($package);

        return view('spj.index', [
            'tab' => 'paket',
            'package' => $package,
            'packageList' => null,
            'validationIssues' => $validationIssues,
            'templates' => $templates,
            'transactions' => null,
            ...$this->overviewMetrics(),
            ...$navigation,
            'spjTypes' => [],
            'filters' => [],
            'periodClosures' => FiscalPeriodClosure::query()->where('fiscal_year_id', $this->context->fiscalYearId())->orderBy('quarter')->get()->keyBy('quarter'),
            'participantRoster' => $participantRoster,
            'consumptionOrderSources' => $consumptionOrderSources,
            'effectiveNumberingPreflight' => $this->effectiveNumberingPreflight($package),
        ]);
    }

    private function compatibilityEditPackage(SpjPackage $package): View
    {
        $transaction = $package->transaction;
        $participantRoster = strtoupper((string) $transaction->spj_category) === 'KONSUMSI'
            ? $this->participantRoster()
            : collect();
        $consumptionOrderSources = [];

        if (strtoupper((string) $transaction->spj_category) === 'KONSUMSI') {
            $consumptionOrderSources = $this->packageMembershipQuery()
                ->where('spj_packages.id', '!=', $package->id)
                ->whereHas('transaction', fn ($query) => $query->where('spj_category', 'KONSUMSI'))
                ->with('transaction.participants')
                ->orderByDesc('spj_packages.id')
                ->limit(5)
                ->get()
                ->map(fn (SpjPackage $row) => [
                    'id' => $row->id,
                    'label' => ($row->transaction->no_bukti ?: 'Tanpa bukti').' · '.($row->transaction->transaction_date?->translatedFormat('d M Y') ?: '-').' · '.$row->transaction->participants->count().' peserta',
                    'names' => $row->transaction->participants->map(fn ($participant) => $participant->name)->filter()->values()->all(),
                    'participants' => $row->transaction->participants->map(fn ($participant) => [
                        'name' => $participant->name,
                        'position' => $participant->position,
                        'nip' => $participant->nip,
                        'nuptk' => $participant->nuptk,
                        'portions' => $participant->portions ?: 1,
                    ])->values()->all(),
                ])
                ->filter(fn (array $row) => $row['names'] !== [])
                ->values()
                ->all();
        }

        return view('spj.package-compat-edit', [
            'package' => $package,
            'transaction' => $transaction,
            'participantRoster' => $participantRoster,
            'consumptionOrderSources' => $consumptionOrderSources,
        ]);
    }

    private function freshPackageView(SpjFreshPackage $package): View
    {
        $validationIssues = app(SpjFreshPackageValidationService::class)->validate($package);
        $transaction = $package->transaction;
        foreach (['goods', 'workers', 'participants', 'travels', 'honors', 'serviceRecipients', 'payments', 'goodsReceipts'] as $relation) {
            $transaction->setRelation($relation, collect());
        }
        $transaction->setRelation('workOrder', null);

        return view('spj.index', [
            'tab' => 'paket',
            'package' => $package,
            'packageList' => null,
            'validationIssues' => $validationIssues,
            'templates' => collect(),
            'transactions' => null,
            ...$this->overviewMetrics(),
            'spjTypes' => [],
            'filters' => [],
            'periodClosures' => collect(),
            'participantRoster' => collect(),
            'consumptionOrderSources' => [],
        ]);
    }

    private function readOnlyCompatibilityPackage(SpjPackage $package): View
    {
        $validator = app(SpjPackageValidationService::class);
        $transaction = $package->transaction;
        $activeSpjDocument = $package->documents
            ->first(fn ($document): bool => $document->document_type === 'SPJ'
                && $document->scope_key === 'MAIN'
                && in_array($document->status, ['NUMBERED', 'FINAL'], true)
                && filled($document->document_number));
        $cancelledSpjDocument = $package->documents
            ->where('document_type', 'SPJ')
            ->where('scope_key', 'MAIN')
            ->where('status', 'CANCELLED')
            ->sortByDesc('id')
            ->first();

        return view('spj.package-readonly', [
            'package' => $package,
            'transaction' => $transaction,
            'validationIssues' => $validator->validate($package),
            'templates' => $this->templateSelector->forPackage($package),
            'activeSpjDocument' => $activeSpjDocument,
            'cancelledSpjDocument' => $cancelledSpjDocument,
            'hasActiveSpjNumber' => $activeSpjDocument !== null && $package->status !== 'CANCELLED',
            'effectiveNumberingPreflight' => $this->effectiveNumberingPreflight($package),
        ]);
    }

    /** @return array{active:bool,authorized:bool,reason:string,path:string,effective_fiscal_year:?int,quarter:?int} */
    private function effectiveNumberingPreflight(SpjPackage $package): array
    {
        if (config('spj.v2_read_path', 'legacy') !== 'v2') {
            return [
                'active' => false,
                'authorized' => false,
                'reason' => '',
                'path' => 'legacy',
                'effective_fiscal_year' => null,
                'quarter' => null,
            ];
        }

        $authorization = $this->numberingAuthorization->authorize($package, ['SPJ']);

        return [
            'active' => true,
            'authorized' => $authorization['authorized'] && ($authorization['path'] ?? null) === 'v2_authorized_preflight',
            'reason' => (string) ($authorization['reason'] ?? 'effective numbering preflight was blocked'),
            'path' => (string) ($authorization['path'] ?? 'blocked'),
            'effective_fiscal_year' => isset($authorization['effective_fiscal_year']) ? (int) $authorization['effective_fiscal_year'] : null,
            'quarter' => isset($authorization['quarter']) ? (int) $authorization['quarter'] : null,
        ];
    }

    /** @return array{previousPackageId: int|null, nextPackageId: int|null} */
    private function packageNavigation(SpjPackage $package): array
    {
        $transaction = $package->transaction;
        $transactionDate = $transaction->transaction_date;

        $previousTransaction = Transaction::query()
            ->forSpjContext($this->context)
            ->whereHas('spjPackage')
            ->where(function ($query) use ($transactionDate, $transaction): void {
                $query->whereDate('transaction_date', '<', $transactionDate)
                    ->orWhere(function ($sameDate) use ($transactionDate, $transaction): void {
                        $sameDate->whereDate('transaction_date', $transactionDate)
                            ->where('id', '<', $transaction->id);
                    });
            })
            ->with('spjPackage:id,transaction_id')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->first();

        $nextTransaction = Transaction::query()
            ->forSpjContext($this->context)
            ->whereHas('spjPackage')
            ->where(function ($query) use ($transactionDate, $transaction): void {
                $query->whereDate('transaction_date', '>', $transactionDate)
                    ->orWhere(function ($sameDate) use ($transactionDate, $transaction): void {
                        $sameDate->whereDate('transaction_date', $transactionDate)
                            ->where('id', '>', $transaction->id);
                    });
            })
            ->with('spjPackage:id,transaction_id')
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->first();

        return [
            'previousPackageId' => $previousTransaction?->spjPackage?->id,
            'nextPackageId' => $nextTransaction?->spjPackage?->id,
        ];
    }
}
