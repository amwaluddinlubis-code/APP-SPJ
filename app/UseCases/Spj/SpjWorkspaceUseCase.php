<?php

namespace App\UseCases\Spj;

use App\Models\DocumentTemplate;
use App\Models\Employee;
use App\Models\FiscalPeriodClosure;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Services\SpjPackageValidationService;
use App\Services\SpjWorkflowFilterService;
use App\Support\ActiveSpjContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SpjWorkspaceUseCase
{
    public function __construct(
        private readonly SpjWorkflowFilterService $workflowFilters,
        private readonly ActiveSpjContext $context,
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
        $packages = SpjPackage::query()->whereHas('transaction', fn ($query) => $query->forSpjContext($this->context));

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

    private function tabPersiapan(Request $request): View
    {
        $filters = $request->validate([
            'month' => ['nullable', 'integer', 'between:1,12'],
            'quarter' => ['nullable', 'integer', 'between:1,4'],
            'spj_category' => ['nullable', 'string', 'max:40'],
            'state' => ['nullable', 'in:all,attention,needs_details,unprepared,draft,ready,numbered'],
        ]);

        $month = isset($filters['month']) ? (int) $filters['month'] : null;
        $quarter = isset($filters['quarter']) ? (int) $filters['quarter'] : null;

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

        $perPageRaw = $request->input('perPage', 15);
        $perPage = $perPageRaw === 'all' ? 10000 : (int) $perPageRaw;
        $perPage = in_array($perPage, [15, 25, 50, 100, 10000]) ? $perPage : 15;

        $transactions = $query
            ->with('spjPackage')->withCount('items')
            ->orderByRaw("CASE WHEN source_status = 'SOURCE_MISSING' OR requires_reconciliation = 1 THEN 0 ELSE 1 END")
            ->orderByRaw("COALESCE((SELECT CASE status WHEN 'DRAFT' THEN 0 WHEN 'READY' THEN 2 WHEN 'NUMBERED' THEN 3 WHEN 'FINAL' THEN 4 ELSE 5 END FROM spj_packages WHERE spj_packages.transaction_id = transactions.id LIMIT 1), 1)")
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->paginate($perPage)->withQueryString();

        return view('spj.index', [
            'tab' => 'persiapan',
            'transactions' => $transactions,
            ...$this->overviewMetrics(),
            'spjTypes' => Transaction::query()->forSpjContext($this->context)->whereNotNull('spj_category')->where('spj_category', '!=', '')->distinct()->orderBy('spj_category')->pluck('spj_category'),
            'filters' => $filters,
            'workQueueCounts' => $workQueueCounts,
        ]);
    }

    private function tabPaket(Request $request): View|RedirectResponse
    {
        $packageId = $request->query('package_id');
        $packagePerPage = $request->integer('package_perPage', 15);
        $packagePerPage = in_array($packagePerPage, [10, 15, 25, 50, 100], true) ? $packagePerPage : 15;

        $packageList = SpjPackage::query()
            ->with(['transaction:id,no_bukti,transaction_date,payment_description,description,recipient_name,spj_category,gross_amount,fiscal_year_id,fund_source_id'])
            ->whereHas('transaction', fn ($query) => $query->forSpjContext($this->context))
            ->orderByRaw("CASE status WHEN 'CANCELLED' THEN 3 WHEN 'FINAL' THEN 2 WHEN 'NUMBERED' THEN 1 ELSE 0 END DESC")
            ->orderByDesc('numbered_at')
            ->orderByDesc('id')
            ->paginate($packagePerPage, ['*'], 'package_page')
            ->withQueryString();

        if (! $packageId) {
            return view('spj.index', [
                'tab' => 'paket',
                'package' => null,
                'packageList' => $packageList,
                'validationIssues' => [],
                'templates' => collect(),
                'transactions' => null,
                ...$this->overviewMetrics(),
                'spjTypes' => [],
                'filters' => [],
                'periodClosures' => FiscalPeriodClosure::query()->where('fiscal_year_id', $this->context->fiscalYearId())->orderBy('quarter')->get()->keyBy('quarter'),
                'participantRoster' => collect(),
                'consumptionOrderSources' => [],
            ]);
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
        if (! $package || ! $this->context->matchesTransaction($package->transaction)) {
            return redirect()->route('spj.index', ['tab' => 'persiapan'])->with('error', 'Paket dokumen tidak ditemukan pada tahun anggaran aktif.');
        }
        if ($package->transaction->items->isEmpty()
            || $package->transaction->items->contains(fn ($item): bool => blank(trim((string) $item->item_description)))) {
            return app(CreateSpjDraftUseCase::class)->handle((string) $package->transaction_id);
        }

        $validator = app(SpjPackageValidationService::class);
        $category = strtoupper((string) $package->transaction->spj_category);
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
            ])
            ->filter(fn (array $row) => $row['names'] !== [])
            ->values()
            ->all();
        $validationIssues = $validator->validate($package);
        $templates = DocumentTemplate::query()->where(['fiscal_year_id' => $this->context->fiscalYearId(), 'is_active' => true])->orderBy('document_type')->get()
            ->filter(fn (DocumentTemplate $template) => empty($template->applicable_categories) || in_array('SEMUA', $template->applicable_categories, true) || in_array($category, $template->applicable_categories, true));
        $navigation = $this->packageNavigation($package);

        return view('spj.index', [
            'tab' => 'paket',
            'package' => $package,
            'packageList' => $packageList,
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
        ]);
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
