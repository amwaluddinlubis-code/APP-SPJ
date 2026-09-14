<?php

namespace App\Http\Controllers;

use App\Services\SpjPeriodicReportPrintService;
use App\Services\SpjPeriodicReportRegistry;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class PeriodicReportController extends Controller
{
    public function __construct(
        private readonly SpjPeriodicReportRegistry $registry,
        private readonly SpjPeriodicReportPrintService $reports,
    ) {}

    public function show(Request $request, string $scope, string $report): View
    {
        return view('periodic-reports.print', $this->payload($request, $scope, $report));
    }

    public function pdf(Request $request, string $scope, string $report): Response
    {
        $payload = $this->payload($request, $scope, $report);

        return Pdf::loadView('periodic-reports.pdf', $payload)
            ->setPaper('a4', $payload['orientation'])
            ->stream($payload['fileName'].'.pdf');
    }

    /** @return array<string,mixed> */
    private function payload(Request $request, string $scope, string $report): array
    {
        abort_unless($this->registry->isScope($scope), 404);
        abort_unless($this->registry->find($scope, $report) !== null, 404);

        $period = $request->filled('periode_laporan')
            ? $request->integer('periode_laporan')
            : null;

        abort_unless($this->registry->isValidPeriod($scope, $period), 422, 'Periode laporan belum dipilih atau tidak valid.');

        $payload = $this->reports->build($scope, $report, $period);
        abort_if($payload === null, 422, 'Sumber data laporan belum siap.');

        return $payload;
    }
}
