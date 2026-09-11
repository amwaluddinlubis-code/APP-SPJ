<?php

namespace App\UseCases\Spj;

use App\Models\FiscalYear;
use App\Models\SpjHonor;
use App\Services\RoutineHonorRegisterService;
use App\Support\ActiveSpjContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExtendedSpjReportUseCase extends SpjReportUseCase
{
    public function __construct(
        private readonly ActiveSpjContext $activeContext,
        private readonly RoutineHonorRegisterService $routineHonorRegister,
    ) {
        parent::__construct($activeContext);
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
            return Pdf::loadView('spj-reports.honor-routine-register', compact('rows', 'summary', 'year', 'school'))
                ->setPaper('a4', 'landscape')
                ->stream('DAFTAR-PENERIMAAN-HONOR-'.$year->year.'.pdf');
        }

        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Penerimaan Honor');
        $sheet->fromArray([
            'No', 'Penerima', 'Jabatan/Jenis Honor', 'Periode', 'Bulan/Kali', 'Tarif',
            'Bruto', 'PPh 21', 'Dibayarkan', 'Referensi Paket SPJ', 'Tanda Tangan',
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
                $row['package_references'],
                ($index + 1).'. __________________',
            ]], null, 'A'.($index + 2));
        }

        $totalRow = $rows->count() + 2;
        $sheet->fromArray([['', '', '', '', '', 'TOTAL', $summary['gross'], $summary['tax'], $summary['net'], '', '']], null, 'A'.$totalRow);
        foreach (['F', 'G', 'H', 'I'] as $column) {
            $sheet->getStyle($column.'2:'.$column.$totalRow)->getNumberFormat()->setFormatCode('#,##0');
        }
        $sheet->getStyle('A1:K1')->getFont()->setBold(true);
        $sheet->getStyle('A1:K'.$totalRow)->getAlignment()->setWrapText(true)->setVertical('center');
        foreach (range('A', 'K') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $sheet->getPageSetup()->setOrientation('landscape')->setPaperSize(9)->setFitToWidth(1)->setFitToHeight(1);
        $sheet->getPageMargins()->setTop(0.35)->setBottom(0.35)->setLeft(0.35)->setRight(0.35);

        $path = storage_path('app/generated-documents/daftar-penerimaan-honor-'.uniqid().'.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        (new Xlsx($book))->save($path);

        return response()->download($path, 'DAFTAR-PENERIMAAN-HONOR-'.$year->year.'.xlsx')->deleteFileAfterSend(true);
    }
}
