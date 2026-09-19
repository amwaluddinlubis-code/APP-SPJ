<?php

namespace App\UseCases\Spj;

use App\Models\FiscalYear;
use App\Models\SpjHonor;
use App\Models\SpjServiceRecipient;
use App\Models\Transaction;
use App\Services\DocumentStoragePathService;
use App\Services\RoutineHonorRegisterService;
use App\Services\SpjV2ReportFinancialSummaryService;
use App\Support\ActiveSpjContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExtendedSpjReportUseCase extends SpjReportUseCase
{
    public function __construct(
        private readonly ActiveSpjContext $activeContext,
        private readonly RoutineHonorRegisterService $routineHonorRegister,
        SpjV2ReportFinancialSummaryService $v2FinancialSummary,
    ) {
        parent::__construct($activeContext, $v2FinancialSummary);
    }

    /** @return Collection<int,Transaction> */
    public function selectionTransactions(string $category, array $filters = []): Collection
    {
        $relation = $category === 'HONOR_PEGAWAI' ? 'honors' : 'serviceRecipients';

        return Transaction::query()
            ->with([$relation, 'spjPackage'])
            ->forSpjContext($this->activeContext)
            ->where('spj_category', $category)
            ->has($relation)
            ->when(! empty($filters['month']), fn ($query) => $query->whereMonth('transaction_date', (int) $filters['month']))
            ->when(! empty($filters['quarter']), fn ($query) => $query->whereBetween(DB::raw("CAST(strftime('%m', transaction_date) AS INTEGER)"), [(((int) $filters['quarter'] - 1) * 3) + 1, (int) $filters['quarter'] * 3]))
            ->when(! empty($filters['semester']), fn ($query) => $query->whereBetween(DB::raw("CAST(strftime('%m', transaction_date) AS INTEGER)"), [(int) $filters['semester'] === 1 ? 1 : 7, (int) $filters['semester'] === 1 ? 6 : 12]))
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();
    }

    public function selectHonorPayments(Request $request): View
    {
        $transactions = $this->selectionTransactions('HONOR_PEGAWAI', $request->all());

        return view('spj-reports.honor-select', compact('transactions'));
    }

    public function composeHonorPayments(Request $request): View
    {
        $data = $request->validate([
            'transaction_ids' => ['required', 'array', 'min:1'],
            'transaction_ids.*' => ['integer', 'distinct'],
        ]);
        $transactions = Transaction::query()
            ->with(['honors', 'spjPackage'])
            ->forSpjContext($this->activeContext)
            ->where('spj_category', 'HONOR_PEGAWAI')
            ->whereKey($data['transaction_ids'])
            ->has('honors')
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();
        abort_if($transactions->count() !== count($data['transaction_ids']), 422, 'Sebagian transaksi honor tidak berada pada konteks aktif atau bukan kategori Honor Pegawai.');

        $honors = SpjHonor::query()
            ->with(['item.transaction.spjPackage'])
            ->whereHas('item', fn ($query) => $query->whereIn('transaction_id', $transactions->modelKeys()))
            ->get()
            ->sortBy(fn (SpjHonor $honor) => sprintf('%s-%010d-%010d', $honor->item->transaction->transaction_date?->format('Y-m-d') ?? '', $honor->item->transaction_id, $honor->id))
            ->values();
        $register = $this->routineHonorRegister->aggregate($honors);

        return view('spj-reports.honor-compose', [
            'transactions' => $transactions,
            'rows' => $register['rows'],
            'summary' => $register['summary'],
        ]);
    }

    public function exportHonorPayments(Request $request, string $format)
    {
        abort_unless(in_array($format, ['pdf', 'xlsx'], true), 404);

        $year = FiscalYear::query()->findOrFail($this->activeContext->fiscalYearId());
        $school = $this->activeContext->school();
        $honors = SpjHonor::query()
            ->with(['item.transaction.spjPackage'])
            ->whereHas('item.transaction', function ($query) use ($request): void {
                $query->forSpjContext($this->activeContext)->where('spj_category', 'HONOR_PEGAWAI');
                if ($request->filled('transaction_ids')) {
                    $query->whereIn('id', collect($request->input('transaction_ids', []))->map(fn ($id): int => (int) $id)->all());
                }
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
                $honor->id,
            ))
            ->values();

        $register = $this->routineHonorRegister->aggregate($honors);
        $rows = $register['rows'];
        $summary = $register['summary'];

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('spj-reports.honor-routine-register', compact('rows', 'summary', 'year', 'school'))->setPaper('a4', 'landscape');
            app(DocumentStoragePathService::class)->archiveReportPdf($pdf->output(), 'DAFTAR-PENERIMAAN-HONOR-'.$year->year.'.pdf', (int) $year->year);

            return $pdf->stream('DAFTAR-PENERIMAAN-HONOR-'.$year->year.'.pdf');
        }

        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Penerimaan Honor');
        $sheet->fromArray([
            'No', 'Penerima', 'Jabatan/Jenis Honor', 'Periode', 'Bulan/Kali', 'Tarif',
            'Bruto', 'PPh 21', 'Dibayarkan', 'No Bukti/BPU', 'Nomor SPJ', 'Tanda Tangan',
        ], null, 'A1');

        foreach ($rows as $index => $row) {
            $sheet->fromArray([[
                $index + 1,
                $row['name'],
                $row['position'],
                $row['period'],
                $row['honor_units'],
                $row['rate_per_unit'],
                $row['gross'],
                $row['tax'],
                $row['net'],
                $row['proof_references'],
                $row['spj_references'],
                ($index + 1).'. __________________',
            ]], null, 'A'.($index + 2));
        }

        $totalRow = $rows->count() + 2;
        $sheet->fromArray([['', '', '', '', '', 'TOTAL', $summary['gross'], $summary['tax'], $summary['net'], '', '', '']], null, 'A'.$totalRow);
        foreach (['F', 'G', 'H', 'I'] as $column) {
            $sheet->getStyle($column.'2:'.$column.$totalRow)->getNumberFormat()->setFormatCode('#,##0');
        }
        $sheet->getStyle('A1:K1')->getFont()->setBold(true);
        $sheet->getStyle('A1:K'.$totalRow)->getAlignment()->setWrapText(true)->setVertical('center');
        foreach (range('A', 'L') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $sheet->getPageSetup()->setOrientation('landscape')->setPaperSize(9)->setFitToWidth(1)->setFitToHeight(1);
        $sheet->getPageMargins()->setTop(0.35)->setBottom(0.35)->setLeft(0.35)->setRight(0.35);

        $path = storage_path('app/generated-documents/daftar-penerimaan-honor-'.uniqid().'.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        (new Xlsx($book))->save($path);

        return app(DocumentStoragePathService::class)->downloadReportFile($path, 'DAFTAR-PENERIMAAN-HONOR-'.$year->year.'.xlsx', (int) $year->year);
    }

    public function exportServiceRecipients(Request $request, string $format)
    {
        abort_unless(in_array($format, ['pdf', 'xlsx'], true), 404);

        $year = FiscalYear::query()->findOrFail($this->activeContext->fiscalYearId());
        $school = $this->activeContext->school();
        $recipients = SpjServiceRecipient::query()
            ->with('transaction.spjPackage')
            ->whereHas('transaction', function ($query) use ($request): void {
                $query->forSpjContext($this->activeContext)->where('spj_category', 'JASA_LAINNYA');
                if ($request->filled('transaction_ids')) {
                    $query->whereIn('id', collect($request->input('transaction_ids', []))->map(fn ($id): int => (int) $id)->all());
                }
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
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $summary = [
            'gross' => $recipients->sum(fn (SpjServiceRecipient $recipient): float => (float) $recipient->amount),
            'tax' => $recipients->sum(fn (SpjServiceRecipient $recipient): float => (float) $recipient->tax_amount),
            'net' => $recipients->sum(fn (SpjServiceRecipient $recipient): float => (float) $recipient->net_amount),
        ];

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('spj-reports.service-recipient-payments', compact('recipients', 'summary', 'year', 'school'))->setPaper('a4', 'landscape');
            app(DocumentStoragePathService::class)->archiveReportPdf($pdf->output(), 'DAFTAR-PEMBAYARAN-JASA-'.$year->year.'.pdf', (int) $year->year);

            return $pdf->stream('DAFTAR-PEMBAYARAN-JASA-'.$year->year.'.pdf');
        }

        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Pembayaran Jasa');
        $sheet->fromArray(['No', 'No Bukti', 'Nomor SPJ', 'Tanggal', 'Penerima Jasa', 'Jenis Jasa', 'Uraian', 'Jumlah', 'Satuan', 'Hari', 'Tarif/Hari', 'Bruto', 'Pajak', 'Dibayarkan', 'Tanda Tangan'], null, 'A1');
        foreach ($recipients as $index => $recipient) {
            $transaction = $recipient->transaction;
            $sheet->fromArray([[
                $index + 1,
                $transaction->no_bukti,
                $transaction->spjPackage?->document_number,
                $transaction->transaction_date?->format('d-m-Y'),
                $recipient->name,
                $recipient->service_type,
                $recipient->service_description,
                (float) $recipient->quantity,
                $recipient->unit,
                (float) $recipient->rental_days,
                (float) $recipient->daily_rate,
                (float) $recipient->amount,
                (float) $recipient->tax_amount,
                (float) $recipient->net_amount,
                ($index + 1).'. __________________',
            ]], null, 'A'.($index + 2));
        }
        $totalRow = $recipients->count() + 2;
        $sheet->fromArray([['', '', '', '', '', '', '', '', '', '', 'TOTAL', $summary['gross'], $summary['tax'], $summary['net'], '']], null, 'A'.$totalRow);
        foreach (['K', 'L', 'M', 'N'] as $column) {
            $sheet->getStyle($column.'2:'.$column.$totalRow)->getNumberFormat()->setFormatCode('#,##0');
        }
        foreach (range('A', 'O') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $path = storage_path('app/generated-documents/daftar-pembayaran-jasa-'.uniqid().'.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        (new Xlsx($book))->save($path);

        return app(DocumentStoragePathService::class)->downloadReportFile($path, 'DAFTAR-PEMBAYARAN-JASA-'.$year->year.'.xlsx', (int) $year->year);
    }

    public function selectServiceRecipients(Request $request): View
    {
        $transactions = $this->selectionTransactions('JASA_LAINNYA', $request->all());

        return view('spj-reports.service-recipient-select', compact('transactions'));
    }

    public function composeServiceRecipients(Request $request): View
    {
        $data = $request->validate([
            'transaction_ids' => ['required', 'array', 'min:1'],
            'transaction_ids.*' => ['integer', 'distinct'],
        ]);
        $transactions = Transaction::query()
            ->with(['serviceRecipients', 'spjPackage'])
            ->forSpjContext($this->activeContext)
            ->where('spj_category', 'JASA_LAINNYA')
            ->whereKey($data['transaction_ids'])
            ->has('serviceRecipients')
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();
        abort_if($transactions->count() !== count($data['transaction_ids']), 422, 'Sebagian transaksi jasa tidak berada pada konteks aktif atau tidak memiliki penerima jasa.');
        $recipients = $transactions->flatMap(function (Transaction $transaction) {
            return $transaction->serviceRecipients->each(
                fn (SpjServiceRecipient $recipient): SpjServiceRecipient => $recipient->setRelation('transaction', $transaction),
            );
        })->values();

        return view('spj-reports.service-recipient-compose', [
            'transactions' => $transactions,
            'recipients' => $recipients,
            'summary' => [
                'gross' => $recipients->sum('amount'),
                'tax' => $recipients->sum('tax_amount'),
                'net' => $recipients->sum('net_amount'),
            ],
        ]);
    }
}
