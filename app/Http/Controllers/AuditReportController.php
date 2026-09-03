<?php

namespace App\Http\Controllers;

use App\Services\AuditReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class AuditReportController extends Controller
{
    public function __construct(private AuditReportService $reports) {}

    public function index(Request $request): View
    {
        $report = $this->reports->build();
        $report['reconciliationRows'] = $this->paginate($report['reconciliationRows'], $request, 'reconciliation_page', 15, 'reconciliation');
        $report['register'] = $this->paginate($report['register'], $request, 'register_page', 15, 'register');
        $report['completenessRows'] = $this->paginate($report['completenessRows'], $request, 'completeness_page', 15, 'completeness');
        $report['syncRuns'] = $this->paginate($report['syncRuns'], $request, 'history_page', 15, 'history');
        $report['auditLogs'] = collect($report['auditLogs']);

        return view('audit-reports.index', $report);
    }

    public function export(string $format): Response|BinaryFileResponse
    {
        $report = $this->reports->build();
        abort_unless(in_array($format, ['pdf', 'xlsx'], true), 404);

        if ($format === 'pdf') {
            return Pdf::loadView('audit-reports.pdf', $report)
                ->setPaper('a4', 'landscape')
                ->stream('AUDIT-REPORT-'.$report['year']->year.'.pdf');
        }

        $book = new Spreadsheet;
        $this->addSheet($book->getActiveSheet()->setTitle('Rekonsiliasi'), [
            ['No Bukti', 'Tanggal', 'RKAS Terkait', 'RKAS', 'BKU', 'Transaksi', 'Selisih', 'SPJ', 'Status'],
            ...$report['reconciliationRows']->map(fn (object $row): array => [
                $row->no_bukti, optional($row->transaction_date)->format('d-m-Y'), $row->rkas_ids ?: '-',
                $row->rkas_amount, $row->bku_amount, $row->transaction_amount, $row->variance, $row->spj_status, $row->status,
            ])->all(),
        ], ['D', 'E', 'F', 'G']);
        $this->addSheet($book->createSheet()->setTitle('Buku Kas'), [
            ['No', 'No Bukti', 'Tanggal', 'Uraian', 'Penerima', 'Kegiatan', 'Rekening', 'Bruto', 'Pajak', 'Dibayarkan', 'SPJ'],
            ...$report['register']->values()->map(fn ($transaction, int $index): array => [
                $index + 1, $transaction->no_bukti, optional($transaction->transaction_date)->format('d-m-Y'),
                $transaction->description, $transaction->recipient_name, $transaction->activity_name,
                $transaction->account_code, (float) $transaction->gross_amount, (float) $transaction->tax_total,
                (float) $transaction->net_amount, $transaction->spjPackage?->document_number ?: ($transaction->spjPackage ? 'DRAFT' : 'BELUM ADA'),
            ])->all(),
        ], ['H', 'I', 'J']);
        $this->addSheet($book->createSheet()->setTitle('Pajak'), [
            ['Jenis Pajak', 'Transaksi', 'Nominal'],
            ...$report['taxSummary']->map(fn (object $row): array => [$row->label, $row->count, $row->amount])->all(),
        ], ['C']);
        $this->addSheet($book->createSheet()->setTitle('Kelengkapan SPJ'), [
            ['No Bukti', 'Tanggal', 'Penerima', 'Bruto', 'Status', 'Temuan'],
            ...$report['completenessRows']->map(fn (object $row): array => [
                $row->no_bukti, optional($row->transaction_date)->format('d-m-Y'), $row->recipient_name,
                $row->amount, $row->status, implode('; ', $row->issues) ?: '-',
            ])->all(),
        ], ['D']);
        $this->addSheet($book->createSheet()->setTitle('Riwayat Operasional'), [
            ['Jenis', 'Status/Aksi', 'Waktu', 'Keterangan', 'Dibaca', 'Ditulis'],
            ...$report['syncRuns']->map(fn (object $row): array => [
                'SINKRONISASI', $row->status, $row->started_at, $row->message, $row->records_read, $row->records_written,
            ])->merge($report['auditLogs']->map(fn (object $row): array => [
                $row->entity_type, $row->action, $row->created_at, $row->description, null, null,
            ]))->all(),
        ], []);

        $path = storage_path('app/generated-documents/audit-report-'.uniqid().'.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        (new Xlsx($book))->save($path);

        return response()->download($path, 'AUDIT-REPORT-'.$report['year']->year.'.xlsx')->deleteFileAfterSend(true);
    }

    /** @param array<int,array<int,mixed>> $rows @param array<int,string> $moneyColumns */
    private function addSheet(Worksheet $sheet, array $rows, array $moneyColumns): void
    {
        $sheet->fromArray($rows, null, 'A1');
        foreach ($moneyColumns as $column) {
            $sheet->getStyle($column.'2:'.$column.max(2, count($rows)))->getNumberFormat()->setFormatCode('#,##0');
        }
        foreach (range('A', chr(64 + min(26, count($rows[0] ?? [])))) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $sheet->freezePane('A2');
    }

    /** @param Collection<int,mixed> $items */
    private function paginate(Collection $items, Request $request, string $pageName, int $perPage, string $tab): LengthAwarePaginator
    {
        $page = max(1, $request->integer($pageName, 1));

        return (new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'pageName' => $pageName,
            ],
        ))->appends([...$request->query(), 'tab' => $tab]);
    }
}
