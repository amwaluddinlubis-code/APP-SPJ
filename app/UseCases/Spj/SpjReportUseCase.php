<?php

namespace App\UseCases\Spj;

use App\Models\FiscalPeriodClosure;
use App\Models\FiscalYear;
use App\Models\School;
use App\Models\SpjHonor;
use App\Models\SpjPackage;
use App\Models\Transaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SpjReportUseCase
{
    public function tabLaporan(Request $request): View
    {
        $perPageRaw = $request->input('perPage', 15);
        $perPage = $perPageRaw === 'all' ? 10000 : (int) $perPageRaw;
        $perPage = in_array($perPage, [15, 25, 50, 100, 10000]) ? $perPage : 15;

        $pendingPerPageRaw = $request->input('pendingPerPage', 15);
        $pendingPerPage = $pendingPerPageRaw === 'all' ? 10000 : (int) $pendingPerPageRaw;
        $pendingPerPage = in_array($pendingPerPage, [15, 25, 50, 100, 10000]) ? $pendingPerPage : 15;

        [$packages, $summary] = $this->report($request, $perPage, $pendingPerPage);
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
            'periodClosures' => FiscalPeriodClosure::query()->where('fiscal_year_id', session('active_fiscal_year_id'))->orderBy('quarter')->get()->keyBy('quarter'),
            'pendingPaginator' => $pendingPaginator,
            'summary' => $summary,
            'transactions' => null,
            ...app(SpjWorkspaceUseCase::class)->overviewMetrics(),
            'spjTypes' => [],
            'filters' => [],
        ]);
    }

    public function export(Request $request, string $format)
    {
        [$packages, $summary] = $this->report($request);
        if ($format === 'pdf') {
            return Pdf::loadView('spj-reports.pdf', compact('packages', 'summary'))
                ->setPaper('a4', 'landscape')
                ->stream('REKAP-SPJ-'.$summary['year'].'.pdf');
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

        return response()->download($path, 'REKAP-SPJ-'.$summary['year'].'.xlsx')->deleteFileAfterSend(true);
    }

    public function exportHonorPayments(Request $request, string $format)
    {
        abort_unless(in_array($format, ['pdf', 'xlsx'], true), 404);
        $year = FiscalYear::query()->findOrFail(session('active_fiscal_year_id'));
        $school = School::query()->findOrFail(session('active_school_id'));
        $honors = SpjHonor::query()
            ->with(['item.transaction.spjPackage'])
            ->whereHas('item.transaction', function ($query) use ($request): void {
                $query->activeContext()->where('spj_category', 'HONOR_PEGAWAI');
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
            return Pdf::loadView('spj-reports.honor-payments', compact('honors', 'summary', 'year', 'school'))
                ->setPaper('a4', 'landscape')
                ->stream('DAFTAR-PEMBAYARAN-HONOR-'.$year->year.'.pdf');
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

        return response()->download($path, 'DAFTAR-PEMBAYARAN-HONOR-'.$year->year.'.xlsx')->deleteFileAfterSend(true);
    }

    private function report(Request $request, ?int $perPage = null, ?int $pendingPerPage = null): array
    {
        $year = FiscalYear::query()->findOrFail(session('active_fiscal_year_id'));
        $transactionFilter = fn ($query) => $this->applyReportTransactionFilters($query->activeContext(), $request, $year);
        $packageQuery = SpjPackage::query()->with(['transaction', 'documents'])
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
                return $package;
            });
        };

        if ($perPage) {
            $packages = $packageQuery->paginate($perPage, ['*'], 'page')->withQueryString();
            $packages->setCollection($decorate($packages->getCollection()));
        } else {
            $packages = $decorate($packageQuery->get())->values();
        }

        $pendingQuery = Transaction::query()->with('spjPackage.documents')
            ->tap(fn ($query) => $this->applyReportTransactionFilters($query->activeContext(), $request, $year))
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

        $activities = Transaction::query()->activeContext()
            ->selectRaw("COALESCE(activity_code, '-') as activity_code, COALESCE(activity_name, 'Kegiatan belum diisi') as activity_name, SUM(gross_amount) as realization")
            ->groupBy('activity_code', 'activity_name')->orderByDesc('realization')->get();
        $accounts = Transaction::query()->activeContext()
            ->selectRaw("COALESCE(account_code, '-') as account_code, COALESCE(account_name, 'Rekening belum diisi') as account_name, SUM(gross_amount) as realization")
            ->groupBy('account_code', 'account_name')->orderBy('account_code')->get();

        $successfulTransactions = Transaction::query()
            ->tap(fn ($query) => $this->applyReportTransactionFilters($query->activeContext(), $request, $year))
            ->whereHas('spjPackage', fn ($package) => $package->whereNotNull('document_number'));
        $successfulSummary = (clone $successfulTransactions)->selectRaw('COUNT(*) as aggregate_count, COALESCE(SUM(gross_amount), 0) as gross, COALESCE(SUM(tax_total), 0) as tax, COALESCE(SUM(net_amount), 0) as net, COALESCE(SUM(ppn), 0) as ppn, COALESCE(SUM(pph21), 0) as pph21, COALESCE(SUM(pph22), 0) as pph22, COALESCE(SUM(pph23), 0) as pph23, COALESCE(SUM(pph4), 0) as pph4, COALESCE(SUM(sspd), 0) as sspd')->first();
        $cancelledCount = SpjPackage::query()
            ->whereHas('transaction', $transactionFilter)
            ->whereNull('document_number')
            ->whereHas('documents', fn ($document) => $document->where(['document_type' => 'SPJ', 'scope_key' => 'MAIN', 'status' => 'CANCELLED']))
            ->count();

        return [$packages, [
            'year' => $year->year,
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
            'pending_transactions' => $pendingTransactions,
            'activities' => $activities,
            'accounts' => $accounts,
        ]];
    }

    private function applyReportTransactionFilters($query, Request $request, FiscalYear $year)
    {
        if ($request->filled('month')) {
            $query->whereMonth('transaction_date', $request->integer('month'));
        }
        if ($request->filled('quarter')) {
            $quarter = $request->integer('quarter');
            $query->whereBetween('transaction_date', [now()->setYear($year->year)->setMonth(($quarter - 1) * 3 + 1)->startOfMonth(), now()->setYear($year->year)->setMonth($quarter * 3)->endOfMonth()]);
        }
        if ($request->filled('semester')) {
            $semester = $request->integer('semester');
            $query->whereBetween('transaction_date', [now()->setYear($year->year)->setMonth($semester === 1 ? 1 : 7)->startOfMonth(), now()->setYear($year->year)->setMonth($semester === 1 ? 6 : 12)->endOfMonth()]);
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
