<?php

namespace App\UseCases\Spj;

use App\Models\FiscalPeriodClosure;
use App\Models\FiscalYear;
use App\Models\SpjFreshPackage;
use App\Models\SpjFreshTransaction;
use App\Models\SpjHonor;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Services\DocumentStoragePathService;
use App\Services\SpjV2PackageReadMembershipService;
use App\Services\SpjV2ReportFinancialSummaryService;
use App\Support\ActiveSpjContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SpjReportUseCase
{
    public function __construct(
        private readonly ActiveSpjContext $context,
        private readonly SpjV2ReportFinancialSummaryService $v2FinancialSummary,
        private readonly SpjV2PackageReadMembershipService $packageReadMembership,
    ) {}

    public function tabLaporan(Request $request): View
    {
        $perPageRaw = $request->input('perPage', 15);
        $perPage = $perPageRaw === 'all' ? 10000 : (int) $perPageRaw;
        $perPage = in_array($perPage, [15, 25, 50, 100, 10000]) ? $perPage : 15;

        $pendingPerPageRaw = $request->input('pendingPerPage', 15);
        $pendingPerPage = $pendingPerPageRaw === 'all' ? 10000 : (int) $pendingPerPageRaw;
        $pendingPerPage = in_array($pendingPerPage, [15, 25, 50, 100, 10000]) ? $pendingPerPage : 15;

        [$packages, $summary] = $this->report($request, $perPage, $pendingPerPage, true);
        $pendingPaginator = $summary['pending_transactions'];

        return view('spj.index', [
            'tab' => 'laporan',
            'packages' => $packages,
            'summary' => $summary,
            'pendingPaginator' => $pendingPaginator,
            'transactions' => null,
            ...app(SpjWorkspaceUseCase::class)->overviewMetrics(),
            'spjTypes' => [],
            'filters' => [],
        ]);
    }

    public function tabMonitoring(Request $request): View
    {
        $pendingPerPageRaw = $request->input('pendingPerPage', 15);
        $pendingPerPage = $pendingPerPageRaw === 'all' ? 10000 : (int) $pendingPerPageRaw;
        $pendingPerPage = in_array($pendingPerPage, [15, 25, 50, 100, 10000]) ? $pendingPerPage : 15;
        [, $summary] = $this->report($request, 15, $pendingPerPage);
        $pendingPaginator = $summary['pending_transactions'];

        return view('spj.index', [
            'tab' => 'monitoring',
            'periodClosures' => FiscalPeriodClosure::query()->where('fiscal_year_id', $this->context->fiscalYearId())->orderBy('quarter')->get()->keyBy('quarter'),
            'pendingPaginator' => $pendingPaginator,
            'summary' => $summary,
            'transactions' => null,
            ...app(SpjWorkspaceUseCase::class)->overviewMetrics(),
            'spjTypes' => [],
            'filters' => [],
        ]);
    }

    /**
     * Resolve mode/periode dari input mentah, termasuk URL bookmark lama
     * yang memakai month/quarter/semester terpisah.
     *
     * @return array{0: string, 1: int|null}
     */
    public static function resolveModePeriode(array $input): array
    {
        $mode = (string) ($input['mode'] ?? '');
        $periode = isset($input['periode']) && $input['periode'] !== '' && $input['periode'] !== null
            ? (int) $input['periode']
            : null;

        if ($mode === '') {
            if (! empty($input['month'])) {
                $mode = 'bulan';
                $periode = (int) $input['month'];
            } elseif (! empty($input['quarter'])) {
                $mode = 'triwulan';
                $periode = (int) $input['quarter'];
            } elseif (! empty($input['semester'])) {
                $mode = 'semester';
                $periode = (int) $input['semester'];
            } else {
                $mode = 'semua';
            }
        }

        return [$mode, $periode];
    }

    /**
     * Jalankan query laporan dari parameter eksplisit memakai implementasi
     * yang sama dengan jalur HTTP, untuk dipakai komponen Livewire.
     */
    public function reportData(string $mode, ?int $periode, int $perPage = 15, int $pendingPerPage = 15, ?int $page = null, ?int $pendingPage = null): array
    {
        return $this->report(new Request(['mode' => $mode, 'periode' => $periode]), $perPage, $pendingPerPage, true, $page, $pendingPage);
    }

    public function export(Request $request, string $format)
    {
        [$packages, $summary] = $this->report($request);
        if ($format === 'pdf') {
            $pdf = Pdf::loadView('spj-reports.pdf', compact('packages', 'summary'))->setPaper('a4', 'landscape');
            app(DocumentStoragePathService::class)->archiveReportPdf($pdf->output(), 'REKAP-SPJ-'.$summary['year'].'.pdf', (int) $summary['year']);

            return $pdf->stream('REKAP-SPJ-'.$summary['year'].'.pdf');
        }
        abort_unless($format === 'xlsx', 404);

        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Rekap SPJ');
        $sheet->fromArray(['No', 'Nomor SPJ', 'No Bukti', 'Tanggal', 'Penerima', 'Kegiatan', 'Rekening', 'Bruto', 'Pajak', 'Dibayarkan', 'Status'], null, 'A1');
        foreach ($packages as $index => $package) {
            $t = $package->transaction;
            $sheet->fromArray([[$index + 1, $package->document_number, $t->no_bukti, optional($t->transaction_date)->format('d-m-Y'), $t->recipient_name, $t->activity_name, $t->account_name, (float) $t->gross_amount, (float) $t->tax_total, (float) $t->net_amount, $package->status]], null, 'A'.($index + 2));
        }
        foreach (['H', 'I', 'J'] as $column) {
            $sheet->getStyle($column.'2:'.$column.($packages->count() + 1))->getNumberFormat()->setFormatCode('#,##0');
        }
        foreach (range('A', 'K') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $this->addRealizationSheet($book, 'Per Kegiatan', $summary['activities'], 'activity_code', 'activity_name');
        $this->addRealizationSheet($book, 'Per Rekening', $summary['accounts'], 'account_code', 'account_name');
        $path = storage_path('app/generated-documents/rekap-spj-'.uniqid().'.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        (new Xlsx($book))->save($path);

        return app(DocumentStoragePathService::class)->downloadReportFile($path, 'REKAP-SPJ-'.$summary['year'].'.xlsx', (int) $summary['year']);
    }

    public function exportHonorPayments(Request $request, string $format)
    {
        abort_unless(in_array($format, ['pdf', 'xlsx'], true), 404);
        $year = FiscalYear::query()->findOrFail($this->context->fiscalYearId());
        $school = $this->context->school();
        $honors = SpjHonor::query()
            ->with(['item.transaction.spjPackage'])
            ->whereHas('item.transaction', function ($query) use ($request): void {
                $query->forSpjContext($this->context)->where('spj_category', 'HONOR_PEGAWAI');
                if ($request->filled('month')) {
                    $query->whereMonth('transaction_date', $request->integer('month'));
                }
                if ($request->filled('quarter')) {
                    $quarter = $request->integer('quarter');
                    $query->whereMonth('transaction_date', '>=', (($quarter - 1) * 3) + 1)
                        ->whereMonth('transaction_date', '<=', $quarter * 3);
                }
                if ($request->filled('semester')) {
                    $semester = $request->integer('semester');
                    $query->whereMonth('transaction_date', '>=', $semester === 1 ? 1 : 7)
                        ->whereMonth('transaction_date', '<=', $semester === 1 ? 6 : 12);
                }
            })
            ->get()
            ->sortBy(fn (SpjHonor $honor) => sprintf(
                '%s-%010d-%010d-%010d',
                $honor->item->transaction->transaction_date?->format('Y-m-d') ?? '',
                $honor->item->transaction_id,
                $honor->sort_order,
                $honor->id
            ))
            ->values();
        $summary = [
            'gross' => $honors->sum(fn (SpjHonor $honor) => (float) $honor->gross_amount),
            'pph21' => $honors->sum(fn (SpjHonor $honor) => (float) $honor->tax_amount),
            'net' => $honors->sum(fn (SpjHonor $honor) => (float) $honor->net_amount),
        ];

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('spj-reports.honor-payments', compact('honors', 'summary', 'year', 'school'))->setPaper('a4', 'landscape');
            app(DocumentStoragePathService::class)->archiveReportPdf($pdf->output(), 'DAFTAR-PEMBAYARAN-HONOR-'.$year->year.'.pdf', (int) $year->year);

            return $pdf->stream('DAFTAR-PEMBAYARAN-HONOR-'.$year->year.'.pdf');
        }

        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Pembayaran Honor');
        $sheet->fromArray(['No', 'No Bukti', 'Nomor SPJ', 'Tanggal', 'Penerima', 'Jabatan/Jenis Honor', 'Bulan/Kali', 'Tarif', 'Bruto', 'PPh 21', 'Dibayarkan', 'Tanda Tangan'], null, 'A1');
        foreach ($honors as $index => $honor) {
            $transaction = $honor->item->transaction;
            $sheet->fromArray([[$index + 1, $transaction->no_bukti, $transaction->spjPackage?->document_number, $transaction->transaction_date?->format('d-m-Y'), $honor->name, $honor->position, (float) $honor->honor_months, (float) $honor->rate_per_unit, (float) $honor->gross_amount, (float) $honor->tax_amount, (float) $honor->net_amount, ($index + 1).'. __________________']], null, 'A'.($index + 2));
        }
        $totalRow = $honors->count() + 2;
        $sheet->fromArray([['', '', '', '', '', 'TOTAL', '', '', $summary['gross'], $summary['pph21'], $summary['net'], '']], null, 'A'.$totalRow);
        foreach (['H', 'I', 'J', 'K'] as $column) {
            $sheet->getStyle($column.'2:'.$column.$totalRow)->getNumberFormat()->setFormatCode('#,##0');
        }
        foreach (range('A', 'L') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $path = storage_path('app/generated-documents/daftar-pembayaran-honor-'.uniqid().'.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        (new Xlsx($book))->save($path);

        return app(DocumentStoragePathService::class)->downloadReportFile($path, 'DAFTAR-PEMBAYARAN-HONOR-'.$year->year.'.xlsx', (int) $year->year);
    }

    private function report(
        Request $request,
        ?int $perPage = null,
        ?int $pendingPerPage = null,
        bool $allowV2FinancialSummary = false,
        ?int $page = null,
        ?int $pendingPage = null,
    ): array {
        $year = FiscalYear::query()->findOrFail($this->context->fiscalYearId());
        if (! Transaction::query()->forSpjContext($this->context)->exists()) {
            return $this->freshReport($request, $year, $perPage, $pendingPerPage, $page, $pendingPage);
        }

        $transactionFilter = fn ($query) => $this->applyReportTransactionFilters($query->forSpjContext($this->context), $request, $year);
        $effectiveTransactionFilter = fn ($query) => $this->applyReportTransactionFilters($query, $request, $year);

        $legacyPackageQuery = $this->reportPackageQuery($transactionFilter);
        $packageQuery = $legacyPackageQuery;
        $effectiveMembership = null;
        if ($allowV2FinancialSummary && $this->context->fundSourceId() !== null) {
            $effectiveMembership = $this->packageReadMembership->forContext(
                DB::connection('school'),
                $this->context->fiscalYearId(),
                (int) $this->context->fundSourceId(),
            );
        }

        $decorate = function ($packages) {
            return $packages->map(function (SpjPackage $package): SpjPackage {
                $cancelledDocument = $package->documents
                    ->where('document_type', 'SPJ')
                    ->where('scope_key', 'MAIN')
                    ->where('status', 'CANCELLED')
                    ->sortByDesc('id')
                    ->first();
                $package->setAttribute('report_document_number', $package->document_number ?: $cancelledDocument?->document_number);
                $package->setAttribute('report_status', $package->document_number ? $package->status : 'CANCELLED');
                $package->setAttribute('report_cancellation_reason', $package->document_number ? null : $cancelledDocument?->cancellation_reason);
                $package->setAttribute(
                    'read_context_path',
                    $this->context->matchesTransaction($package->transaction) ? 'legacy' : 'v2_compat',
                );

                return $package;
            });
        };

        $pendingQuery = Transaction::query()->with('spjPackage.documents')
            ->tap(fn ($query) => $this->applyReportTransactionFilters($query->forSpjContext($this->context), $request, $year))
            ->has('items')
            ->where(function ($query): void {
                $query->doesntHave('spjPackage')
                    ->orWhereHas('spjPackage', fn ($package) => $package->whereNull('document_number'));
            })
            ->orderBy('transaction_date')
            ->orderBy('id');
        $pendingTransactions = $pendingPerPage
            ? $pendingQuery->paginate($pendingPerPage, ['*'], 'pending_page')->withQueryString()
            : $pendingQuery->get();

        $activities = Transaction::query()->forSpjContext($this->context)
            ->selectRaw("COALESCE(activity_code, '-') as activity_code, COALESCE(activity_name, 'Kegiatan belum diisi') as activity_name, SUM(gross_amount) as realization")
            ->groupBy('activity_code', 'activity_name')->orderByDesc('realization')->get();
        $accounts = Transaction::query()->forSpjContext($this->context)
            ->selectRaw("COALESCE(account_code, '-') as account_code, COALESCE(account_name, 'Rekening belum diisi') as account_name, SUM(gross_amount) as realization")
            ->groupBy('account_code', 'account_name')->orderBy('account_code')->get();

        $financialSummary = $this->reportFinancialSummary(
            fn ($query) => $this->applyReportTransactionFilters($query->forSpjContext($this->context), $request, $year),
            $transactionFilter,
        );
        $readPath = 'legacy';

        if ($allowV2FinancialSummary && $effectiveMembership !== null) {
            $effectivePackageIds = $effectiveMembership['package_ids'];
            $effectiveFinancialSummary = $this->reportFinancialSummary(
                fn ($query) => $this->applyReportTransactionFilters(
                    $query->whereHas('spjPackage', fn ($package) => $package->whereIn('id', $effectivePackageIds)),
                    $request,
                    $year,
                ),
                fn ($query) => $effectiveTransactionFilter($query),
                $effectivePackageIds,
            );

            [$mode, $periode] = self::resolveModePeriode($request->all());
            $v2Summary = $this->v2FinancialSummary->forContext(
                DB::connection('school'),
                $this->context->fiscalYearId(),
                (int) $this->context->fundSourceId(),
                (int) $year->year,
                $mode,
                $periode,
                $effectiveFinancialSummary,
            );

            if ($v2Summary !== null) {
                $financialSummary = array_intersect_key($v2Summary, $effectiveFinancialSummary);
                $packageQuery = $this->reportPackageQuery(
                    $effectiveTransactionFilter,
                    $effectivePackageIds,
                );
                $readPath = 'v2';
            }
        }

        if ($perPage) {
            $packages = $packageQuery->paginate($perPage, ['*'], 'page')->withQueryString();
            $packages->setCollection($decorate($packages->getCollection()));
        } else {
            $packages = $decorate($packageQuery->get())->values();
        }

        return [$packages, [
            'year' => $year->year,
            ...$financialSummary,
            'read_path' => $readPath,
            'pending_transactions' => $pendingTransactions,
            'activities' => $activities,
            'accounts' => $accounts,
        ]];
    }

    /**
     * Read-only report compatibility for a reset tenant whose fresh projection
     * exists while the legacy transaction table is empty.
     */
    private function freshReport(Request $request, FiscalYear $year, ?int $perPage, ?int $pendingPerPage, ?int $page = null, ?int $pendingPage = null): array
    {
        $transactions = SpjFreshTransaction::query()
            ->forSpjContext($this->context)
            ->whereIn('source_status', ['ACTIVE', 'SOURCE_MISSING'])
            ->with(['rawMirrorRow', 'items.rawMirrorRow', 'spjPackage.transaction.rawMirrorRow', 'spjPackage.documents'])
            ->get()
            ->filter(fn (SpjFreshTransaction $transaction): bool => $this->freshMatchesReportPeriod($transaction, $request, $year))
            ->values();
        $packages = $transactions
            ->flatMap(fn (SpjFreshTransaction $transaction) => $transaction->spjPackage ? [$transaction->spjPackage] : [])
            ->filter(fn (SpjFreshPackage $package): bool => filled($package->document_number) || $package->documents->contains(fn ($document): bool => $document->status === 'CANCELLED'))
            ->values();
        $packages->each(function (SpjFreshPackage $package): void {
            $package->load(['transaction.rawMirrorRow', 'transaction.items.rawMirrorRow']);
        });

        $packages->each(function (SpjFreshPackage $package): void {
            $cancelled = $package->documents->where('status', 'CANCELLED')->sortByDesc('id')->first();
            $package->setAttribute('report_document_number', $package->document_number ?: $cancelled?->document_number);
            $package->setAttribute('report_status', $package->document_number ? $package->status : 'CANCELLED');
            $package->setAttribute('report_cancellation_reason', $package->document_number ? null : $cancelled?->cancellation_reason);
            $package->setAttribute('read_context_path', 'fresh_compat');
        });

        $successful = $packages->filter(fn (SpjFreshPackage $package): bool => $package->report_status !== 'CANCELLED');
        $summary = [
            'year' => $year->year,
            'count' => $successful->count(),
            'cancelled_count' => $packages->where('report_status', 'CANCELLED')->count(),
            'gross' => $successful->sum(fn (SpjFreshPackage $package): float => (float) $package->transaction->gross_amount),
            'tax' => $successful->sum(fn (SpjFreshPackage $package): float => (float) $package->transaction->tax_total),
            'net' => $successful->sum(fn (SpjFreshPackage $package): float => (float) $package->transaction->net_amount),
            'ppn' => 0.0, 'pph21' => 0.0, 'pph22' => 0.0, 'pph23' => 0.0, 'pph4' => 0.0, 'sspd' => 0.0,
            'read_path' => 'fresh_compat',
            'activities' => collect(),
            'accounts' => collect(),
        ];

        $pending = $transactions->filter(fn (SpjFreshTransaction $transaction): bool => $transaction->items->isNotEmpty()
            && ($transaction->spjPackage === null || blank($transaction->spjPackage->document_number)))->values();
        $pendingPaginator = $this->paginateCollection($pending, $pendingPerPage ?? 15, 'pending_page', $pendingPage);

        if ($perPage === null) {
            $listedPackages = $packages;
        } else {
            $listedPackages = $this->paginateCollection($packages, $perPage, 'page', $page);
        }

        $summary['pending_transactions'] = $pendingPaginator;

        return [$listedPackages, $summary];
    }

    private function freshMatchesReportPeriod(SpjFreshTransaction $transaction, Request $request, FiscalYear $year): bool
    {
        $date = $transaction->transaction_date;
        if ($date === null) {
            return true;
        }
        [$mode, $periode] = self::resolveModePeriode($request->all());

        return match ($mode) {
            'bulan' => $periode === null || $date->year === $year->year && $date->month === $periode,
            'triwulan' => $periode === null || $date->year === $year->year && (int) ceil($date->month / 3) === $periode,
            'semester' => $periode === null || $date->year === $year->year && (($date->month <= 6 ? 1 : 2) === $periode),
            default => true,
        };
    }

    private function paginateCollection(Collection $items, int $perPage, string $pageName, ?int $explicitPage = null): LengthAwarePaginator
    {
        $page = max(1, $explicitPage ?? (int) request()->query($pageName, 1));

        return new LengthAwarePaginator(
            $items->slice(($page - 1) * $perPage, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    private function reportPackageQuery(callable $transactionFilter, ?array $packageIds = null): Builder
    {
        return SpjPackage::query()
            ->with(['transaction', 'documents'])
            ->when($packageIds !== null, fn ($query) => $query->whereIn('id', $packageIds))
            ->whereHas('transaction', $transactionFilter)
            ->where(function ($query): void {
                $query->whereNotNull('document_number')
                    ->orWhereHas('documents', fn ($document) => $document
                        ->where('document_type', 'SPJ')
                        ->where('scope_key', 'MAIN')
                        ->where('status', 'CANCELLED'));
            })
            ->orderByRaw("COALESCE((SELECT sequence_number FROM spj_documents WHERE spj_documents.spj_package_id = spj_packages.id AND document_type = 'SPJ' ORDER BY id DESC LIMIT 1), 2147483647)")
            ->orderBy('spj_packages.id');
    }

    /**
     * @return array{count:int,cancelled_count:int,gross:float,tax:float,net:float,ppn:float,pph21:float,pph22:float,pph23:float,pph4:float,sspd:float}
     */
    private function reportFinancialSummary(
        callable $successfulTransactionFilter,
        callable $cancelledTransactionFilter,
        ?array $packageIds = null,
    ): array {
        $successfulTransactions = Transaction::query()
            ->tap($successfulTransactionFilter)
            ->whereHas('spjPackage', function ($package) use ($packageIds): void {
                $package->whereNotNull('document_number');
                if ($packageIds !== null) {
                    $package->whereIn('id', $packageIds);
                }
            });
        $successfulSummary = (clone $successfulTransactions)
            ->selectRaw('COUNT(*) as aggregate_count, COALESCE(SUM(gross_amount), 0) as gross, COALESCE(SUM(tax_total), 0) as tax, COALESCE(SUM(net_amount), 0) as net, COALESCE(SUM(ppn), 0) as ppn, COALESCE(SUM(pph21), 0) as pph21, COALESCE(SUM(pph22), 0) as pph22, COALESCE(SUM(pph23), 0) as pph23, COALESCE(SUM(pph4), 0) as pph4, COALESCE(SUM(sspd), 0) as sspd')
            ->first();

        $cancelledCount = SpjPackage::query()
            ->when($packageIds !== null, fn ($query) => $query->whereIn('id', $packageIds))
            ->whereHas('transaction', $cancelledTransactionFilter)
            ->whereNull('document_number')
            ->whereHas('documents', fn ($document) => $document->where([
                'document_type' => 'SPJ',
                'scope_key' => 'MAIN',
                'status' => 'CANCELLED',
            ]))
            ->count();

        return [
            'count' => (int) $successfulSummary->aggregate_count,
            'cancelled_count' => $cancelledCount,
            'gross' => (float) $successfulSummary->gross,
            'tax' => (float) $successfulSummary->tax,
            'net' => (float) $successfulSummary->net,
            'ppn' => (float) $successfulSummary->ppn,
            'pph21' => (float) $successfulSummary->pph21,
            'pph22' => (float) $successfulSummary->pph22,
            'pph23' => (float) $successfulSummary->pph23,
            'pph4' => (float) $successfulSummary->pph4,
            'sspd' => (float) $successfulSummary->sspd,
        ];
    }

    private function applyReportTransactionFilters($query, Request $request, FiscalYear $year)
    {
        $mode = (string) $request->input('mode', '');
        $periode = $request->integer('periode') ?: null;

        // Compatibility for bookmarked URLs that used the previous three filters.
        if ($mode === '') {
            $mode = $request->filled('month') ? 'bulan' : ($request->filled('quarter') ? 'triwulan' : ($request->filled('semester') ? 'semester' : 'semua'));
            $periode = $mode === 'bulan' ? $request->integer('month') : ($mode === 'triwulan' ? $request->integer('quarter') : ($mode === 'semester' ? $request->integer('semester') : null));
        }

        if ($mode === 'bulan' && $periode >= 1 && $periode <= 12) {
            $query->whereYear('transaction_date', $year->year)->whereMonth('transaction_date', $periode);
        } elseif ($mode === 'triwulan' && $periode >= 1 && $periode <= 4) {
            $query->whereBetween('transaction_date', [now()->setYear($year->year)->setMonth(($periode - 1) * 3 + 1)->startOfMonth(), now()->setYear($year->year)->setMonth($periode * 3)->endOfMonth()]);
        } elseif ($mode === 'semester' && $periode >= 1 && $periode <= 2) {
            $query->whereBetween('transaction_date', [now()->setYear($year->year)->setMonth($periode === 1 ? 1 : 7)->startOfMonth(), now()->setYear($year->year)->setMonth($periode === 1 ? 6 : 12)->endOfMonth()]);
        }

        return $query;
    }

    private function addRealizationSheet(Spreadsheet $book, string $title, $rows, string $code, string $name): void
    {
        $sheet = $book->createSheet()->setTitle($title);
        $sheet->fromArray(['No', 'Kode', 'Nama', 'Realisasi'], null, 'A1');
        foreach ($rows as $index => $row) {
            $sheet->fromArray([[$index + 1, $row->{$code}, $row->{$name}, (float) $row->realization]], null, 'A'.($index + 2));
        }
        $sheet->getStyle('D2:D'.($rows->count() + 1))->getNumberFormat()->setFormatCode('#,##0');
        foreach (range('A', 'D') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }
}
