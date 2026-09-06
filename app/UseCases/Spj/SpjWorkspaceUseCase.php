<?php

namespace App\UseCases\Spj;

use App\Models\DocumentTemplate;
use App\Models\Employee;
use App\Models\FiscalPeriodClosure;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Services\SpjPackageValidationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SpjWorkspaceUseCase
{
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
        $packages = SpjPackage::query()->whereHas('transaction', fn ($query) => $query->activeContext());

        return [
            'totalPackages' => (clone $packages)->count(),
            'numberedPackages' => (clone $packages)->whereNotNull('document_number')->count(),
            'readyTransactions' => Transaction::query()->activeContext()->has('items')->count(),
        ];
    }

    public function participantRoster()
    {
        $statusId = static fn (Employee $employee): int => is_numeric($employee->payload['status_kepegawaian_id'] ?? null)
            ? (int) $employee->payload['status_kepegawaian_id']
            : PHP_INT_MAX;

        return Employee::query()->where('is_active', true)->orderBy('name')
            ->get(['id', 'name', 'position', 'staff_type', 'source_type', 'nuptk', 'payload'])
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
            'state' => ['nullable', 'in:all,ready,needs_details,unprepared,draft,numbered'],
        ]);

        $month = isset($filters['month']) ? (int) $filters['month'] : null;
        $quarter = isset($filters['quarter']) ? (int) $filters['quarter'] : null;

        $query = Transaction::query()->activeContext()
            ->when($month, fn ($q, $selectedMonth) => $q->whereMonth('transaction_date', $selectedMonth))
            ->when(! $month && $quarter, function ($q) use ($quarter): void {
                $q->whereMonth('transaction_date', '>=', (($quarter - 1) * 3) + 1)
                    ->whereMonth('transaction_date', '<=', $quarter * 3);
            })
            ->when($filters['spj_category'] ?? null, fn ($q, $type) => $q->where('spj_category', $type));

        $queueCounts = (clone $query)
            ->leftJoin('transaction_items as queue_items', 'queue_items.transaction_id', '=', 'transactions.id')
            ->leftJoin('spj_packages as queue_packages', 'queue_packages.transaction_id', '=', 'transactions.id')
            ->toBase()
            ->selectRaw('COUNT(DISTINCT transactions.id) as total')
            ->selectRaw('COUNT(DISTINCT CASE WHEN queue_items.id IS NULL THEN transactions.id END) as needs_details')
            ->selectRaw('COUNT(DISTINCT CASE WHEN queue_items.id IS NOT NULL AND queue_packages.id IS NULL THEN transactions.id END) as unprepared')
            ->selectRaw("COUNT(DISTINCT CASE WHEN queue_items.id IS NOT NULL AND queue_packages.status = 'DRAFT' THEN transactions.id END) as draft")
            ->selectRaw("COUNT(DISTINCT CASE WHEN queue_items.id IS NOT NULL AND queue_packages.status = 'READY' THEN transactions.id END) as ready")
            ->selectRaw("COUNT(DISTINCT CASE WHEN queue_items.id IS NOT NULL AND queue_packages.status IN ('NUMBERED', 'FINAL') THEN transactions.id END) as numbered")
            ->first();

        $workQueueCounts = [
            'all' => (int) $queueCounts->total,
            'needs_details' => (int) $queueCounts->needs_details,
            'unprepared' => (int) $queueCounts->unprepared,
            'draft' => (int) $queueCounts->draft,
            'ready' => (int) $queueCounts->ready,
            'numbered' => (int) $queueCounts->numbered,
        ];

        match ($filters['state'] ?? 'all') {
            'ready' => $query->whereHas('spjPackage', fn ($q) => $q->where('status', 'READY')),
            'needs_details' => $query->doesntHave('items'),
            'unprepared' => $query->has('items')->doesntHave('spjPackage'),
            'draft' => $query->whereHas('spjPackage', fn ($q) => $q->where('status', 'DRAFT')),
            'numbered' => $query->whereHas('spjPackage', fn ($q) => $q->whereIn('status', ['NUMBERED', 'FINAL'])),
            default => null,
        };

        $perPageRaw = $request->input('perPage', 15);
        $perPage = $perPageRaw === 'all' ? 10000 : (int) $perPageRaw;
        $perPage = in_array($perPage, [15, 25, 50, 100, 10000]) ? $perPage : 15;

        $transactions = $query
            ->with('spjPackage')->withCount('items')
            ->orderByRaw("COALESCE((SELECT CASE status WHEN 'DRAFT' THEN 0 WHEN 'READY' THEN 2 WHEN 'NUMBERED' THEN 3 WHEN 'FINAL' THEN 4 ELSE 5 END FROM spj_packages WHERE spj_packages.transaction_id = transactions.id LIMIT 1), 1)")
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->paginate($perPage)->withQueryString();

        return view('spj.index', [
            'tab' => 'persiapan',
            'transactions' => $transactions,
            ...$this->overviewMetrics(),
            'spjTypes' => Transaction::query()->activeContext()->whereNotNull('spj_category')->where('spj_category', '!=', '')->distinct()->orderBy('spj_category')->pluck('spj_category'),
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
            ->whereHas('transaction', fn ($query) => $query->activeContext())
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
                'periodClosures' => FiscalPeriodClosure::query()->where('fiscal_year_id', session('active_fiscal_year_id'))->orderBy('quarter')->get()->keyBy('quarter'),
                'participantRoster' => collect(),
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
            'transaction.payments',
            'transaction.goodsReceipts.items',
        ])->find($packageId);
        if (! $package || $package->transaction->fiscal_year_id !== (int) session('active_fiscal_year_id')) {
            return redirect()->route('spj.index', ['tab' => 'persiapan'])->with('error', 'Paket dokumen tidak ditemukan pada tahun anggaran aktif.');
        }

        $validator = app(SpjPackageValidationService::class);
        $category = strtoupper((string) $package->transaction->spj_category);
        $participantRoster = $this->participantRoster();
        $validationIssues = $validator->validate($package);
        $templates = DocumentTemplate::query()->where(['fiscal_year_id' => session('active_fiscal_year_id'), 'is_active' => true])->orderBy('document_type')->get()
            ->filter(fn (DocumentTemplate $template) => empty($template->applicable_categories) || in_array('SEMUA', $template->applicable_categories, true) || in_array($category, $template->applicable_categories, true));

        return view('spj.index', [
            'tab' => 'paket',
            'package' => $package,
            'packageList' => $packageList,
            'validationIssues' => $validationIssues,
            'templates' => $templates,
            'transactions' => null,
            ...$this->overviewMetrics(),
            'spjTypes' => [],
            'filters' => [],
            'periodClosures' => FiscalPeriodClosure::query()->where('fiscal_year_id', session('active_fiscal_year_id'))->orderBy('quarter')->get()->keyBy('quarter'),
            'participantRoster' => $participantRoster,
        ]);
    }
}
