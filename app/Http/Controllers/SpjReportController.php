<?php

namespace App\Http\Controllers;

use App\Models\FiscalYear;
use App\Models\SpjPackage;
use App\Models\Transaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SpjReportController extends Controller
{
    public function index(Request $request)
    {
        [$packages, $summary] = $this->report($request);
        // Samakan aturan pagination 15,25,50,100 All dalam 1 page_table — untuk 2 tabel di tab
        $perPageRaw = $request->input('perPage', 15);
        $perPage = $perPageRaw === 'all' ? 10000 : (int) $perPageRaw;
        $perPage = in_array($perPage, [15, 25, 50, 100, 10000]) ? $perPage : 15;

        $pendingPerPageRaw = $request->input('pendingPerPage', 15);
        $pendingPerPage = $pendingPerPageRaw === 'all' ? 10000 : (int) $pendingPerPageRaw;
        $pendingPerPage = in_array($pendingPerPage, [15, 25, 50, 100, 10000]) ? $pendingPerPage : 15;

        $currentPage = (int) $request->input('page', 1);
        $pendingPage = (int) $request->input('pending_page', 1);

        $packagesPaginator = new LengthAwarePaginator(
            $packages->forPage($currentPage, $perPage),
            $packages->count(),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query(), 'pageName' => 'page']
        );
        $pendingCollection = $summary['pending_transactions'];
        $pendingPaginator = new LengthAwarePaginator(
            $pendingCollection->forPage($pendingPage, $pendingPerPage),
            $pendingCollection->count(),
            $pendingPerPage,
            $pendingPage,
            ['path' => $request->url(), 'query' => $request->query(), 'pageName' => 'pending_page']
        );

        // packages tetap untuk export, tapi untuk view pakai paginator
        $packages = $packagesPaginator;
        $pendingPaginator->withPath($request->url());

        return view('spj-reports.index', compact('packages', 'summary', 'pendingPaginator'));
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

    private function report(Request $request): array
    {
        $year = FiscalYear::query()->findOrFail(session('active_fiscal_year_id'));
        $packages = SpjPackage::query()->with('transaction')
            ->whereHas('transaction', function ($query) use ($year, $request): void {
                $query->activeContext();
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
            })
            ->whereNotNull('document_number')
            ->orderBy('document_number')
            ->get();
        $pendingTransactions = Transaction::query()->with(['spjPackage', 'items'])
            ->activeContext()
            ->has('items')
            ->get()
            ->when($request->filled('month'), fn ($transactions) => $transactions->filter(fn (Transaction $transaction): bool => (int) ($transaction->transaction_date?->month ?? 0) === $request->integer('month')))
            ->when($request->filled('quarter'), fn ($transactions) => $transactions->filter(fn (Transaction $transaction): bool => (int) ceil(((int) ($transaction->transaction_date?->month ?? 0)) / 3) === $request->integer('quarter')))
            ->when($request->filled('semester'), fn ($transactions) => $transactions->filter(fn (Transaction $transaction): bool => ((int) ($transaction->transaction_date?->month ?? 0) <= 6 ? 1 : 2) === $request->integer('semester')))
            ->filter(fn (Transaction $transaction) => ! $transaction->spjPackage || ! $transaction->spjPackage->document_number)
            ->values();

        $activities = Transaction::query()->activeContext()
            ->selectRaw("COALESCE(activity_code, '-') as activity_code, COALESCE(activity_name, 'Kegiatan belum diisi') as activity_name, SUM(gross_amount) as realization")
            ->groupBy('activity_code', 'activity_name')->orderByDesc('realization')->get();
        $accounts = Transaction::query()->activeContext()
            ->selectRaw("COALESCE(account_code, '-') as account_code, COALESCE(account_name, 'Rekening belum diisi') as account_name, SUM(gross_amount) as realization")
            ->groupBy('account_code', 'account_name')->orderBy('account_code')->get();

        return [$packages, [
            'year' => $year->year,
            'count' => $packages->count(),
            'gross' => $packages->sum(fn ($p) => (float) $p->transaction->gross_amount),
            'tax' => $packages->sum(fn ($p) => (float) $p->transaction->tax_total),
            'net' => $packages->sum(fn ($p) => (float) $p->transaction->net_amount),
            'ppn' => $packages->sum(fn ($p) => (float) $p->transaction->ppn),
            'pph21' => $packages->sum(fn ($p) => (float) $p->transaction->pph21),
            'pph22' => $packages->sum(fn ($p) => (float) $p->transaction->pph22),
            'pph23' => $packages->sum(fn ($p) => (float) $p->transaction->pph23),
            'pph4' => $packages->sum(fn ($p) => (float) $p->transaction->pph4),
            'sspd' => $packages->sum(fn ($p) => (float) $p->transaction->sspd),
            'pending_transactions' => $pendingTransactions,
            'activities' => $activities,
            'accounts' => $accounts,
        ]];
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
