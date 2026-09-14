<?php

namespace App\UseCases\Spj;

use App\Models\FiscalYear;
use App\Models\Transaction;
use App\Services\SpjPeriodicReportRegistry;
use App\Support\ActiveSpjContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class SpjPeriodicReportUseCase
{
    public function __construct(
        private readonly ActiveSpjContext $context,
        private readonly SpjPeriodicReportRegistry $registry,
    ) {}

    /**
     * Payload standar untuk generator internal laporan periode.
     *
     * @return array{report:array{key:string,label:string},summary:array<string,mixed>,transactions:Collection<int,Transaction>}|null
     */
    public function payload(string $scope, string $reportKey, ?int $period): ?array
    {
        $report = $this->registry->find($scope, $reportKey);

        if ($report === null) {
            return null;
        }

        $summary = $this->summary($scope, $period);

        return [
            'report' => $report,
            'summary' => $summary,
            'transactions' => $summary['ready'] ? $this->transactions($scope, $period) : collect(),
        ];
    }

    /** @return Collection<int,Transaction> */
    public function transactions(string $scope, ?int $period): Collection
    {
        if (! $this->registry->isScope($scope)) {
            return collect();
        }

        if (! $this->registry->periodRequired($scope)) {
            $period = null;
        }

        if (! $this->registry->isValidPeriod($scope, $period)) {
            return collect();
        }

        $query = Transaction::query()
            ->with('items')
            ->forSpjContext($this->context);

        return $this->applyPeriod($query, $scope, $period)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * Ringkasan sumber data untuk generator laporan periode.
     *
     * Nilai agregat ini menjadi kontrak sumber data bersama. Penyajian setiap
     * dokumen (ledger, rekap, pernyataan, atau lampiran) dibangun oleh service
     * laporan periode tanpa bergantung pada template Paket SPJ.
     *
     * @return array{
     *   ready:bool,
     *   scope:string,
     *   scope_label:string,
     *   period:int|null,
     *   period_label:string,
     *   date_from:string|null,
     *   date_to:string|null,
     *   transaction_count:int,
     *   gross:float,
     *   tax:float,
     *   net:float,
     *   ppn:float,
     *   pph21:float,
     *   pph22:float,
     *   pph23:float,
     *   pph4:float,
     *   sspd:float
     * }
     */
    public function summary(string $scope, ?int $period): array
    {
        if (! $this->registry->isScope($scope)) {
            $scope = SpjPeriodicReportRegistry::SCOPE_MONTHLY;
        }

        if (! $this->registry->periodRequired($scope)) {
            $period = null;
        }

        $year = FiscalYear::query()->find($this->context->fiscalYearId());
        $fiscalYear = (int) ($year?->year ?: now()->year);
        $periodContext = $this->periodContext($scope, $period, $fiscalYear);

        $empty = [
            'ready' => false,
            'scope' => $scope,
            'scope_label' => $this->registry->scopeLabel($scope),
            'period' => $period,
            'period_label' => $periodContext['label'],
            'date_from' => $periodContext['from']?->toDateString(),
            'date_to' => $periodContext['to']?->toDateString(),
            'transaction_count' => 0,
            'gross' => 0.0,
            'tax' => 0.0,
            'net' => 0.0,
            'ppn' => 0.0,
            'pph21' => 0.0,
            'pph22' => 0.0,
            'pph23' => 0.0,
            'pph4' => 0.0,
            'sspd' => 0.0,
        ];

        if (! $periodContext['ready']) {
            return $empty;
        }

        $query = Transaction::query()->forSpjContext($this->context);
        $this->applyPeriod($query, $scope, $period);

        return [
            ...$empty,
            'ready' => true,
            'transaction_count' => (clone $query)->count(),
            'gross' => (float) (clone $query)->sum('gross_amount'),
            'tax' => (float) (clone $query)->sum('tax_total'),
            'net' => (float) (clone $query)->sum('net_amount'),
            'ppn' => (float) (clone $query)->sum('ppn'),
            'pph21' => (float) (clone $query)->sum('pph21'),
            'pph22' => (float) (clone $query)->sum('pph22'),
            'pph23' => (float) (clone $query)->sum('pph23'),
            'pph4' => (float) (clone $query)->sum('pph4'),
            'sspd' => (float) (clone $query)->sum('sspd'),
        ];
    }

    public function applyPeriod(Builder $query, string $scope, ?int $period): Builder
    {
        return match ($scope) {
            SpjPeriodicReportRegistry::SCOPE_MONTHLY => $period
                ? $query->whereMonth('transaction_date', $period)
                : $query,
            SpjPeriodicReportRegistry::SCOPE_QUARTERLY => $period
                ? $query->whereMonth('transaction_date', '>=', (($period - 1) * 3) + 1)
                    ->whereMonth('transaction_date', '<=', $period * 3)
                : $query,
            SpjPeriodicReportRegistry::SCOPE_SEMESTER => $period
                ? $query->whereMonth('transaction_date', '>=', (($period - 1) * 6) + 1)
                    ->whereMonth('transaction_date', '<=', $period * 6)
                : $query,
            default => $query,
        };
    }

    /** @return array{ready:bool,label:string,from:CarbonImmutable|null,to:CarbonImmutable|null} */
    private function periodContext(string $scope, ?int $period, int $year): array
    {
        if (! $this->registry->isValidPeriod($scope, $period)) {
            return [
                'ready' => false,
                'label' => $this->registry->periodRequired($scope) ? 'Pilih periode' : (string) $year,
                'from' => null,
                'to' => null,
            ];
        }

        if ($scope === SpjPeriodicReportRegistry::SCOPE_ANNUAL) {
            return [
                'ready' => true,
                'label' => (string) $year,
                'from' => CarbonImmutable::create($year, 1, 1)->startOfDay(),
                'to' => CarbonImmutable::create($year, 12, 31)->endOfDay(),
            ];
        }

        $startMonth = match ($scope) {
            SpjPeriodicReportRegistry::SCOPE_MONTHLY => (int) $period,
            SpjPeriodicReportRegistry::SCOPE_QUARTERLY => (((int) $period - 1) * 3) + 1,
            SpjPeriodicReportRegistry::SCOPE_SEMESTER => (((int) $period - 1) * 6) + 1,
            default => 1,
        };

        $monthSpan = match ($scope) {
            SpjPeriodicReportRegistry::SCOPE_MONTHLY => 1,
            SpjPeriodicReportRegistry::SCOPE_QUARTERLY => 3,
            SpjPeriodicReportRegistry::SCOPE_SEMESTER => 6,
            default => 12,
        };

        $from = CarbonImmutable::create($year, $startMonth, 1)->startOfMonth();
        $to = $from->addMonths($monthSpan - 1)->endOfMonth();

        $label = match ($scope) {
            SpjPeriodicReportRegistry::SCOPE_MONTHLY => $this->monthLabel((int) $period).' '.$year,
            SpjPeriodicReportRegistry::SCOPE_QUARTERLY => 'Triwulan '.(int) $period.' / '.$year,
            SpjPeriodicReportRegistry::SCOPE_SEMESTER => 'Semester '.(int) $period.' / '.$year,
            default => (string) $year,
        };

        return compact('label', 'from', 'to') + ['ready' => true];
    }

    private function monthLabel(int $month): string
    {
        return [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ][$month] ?? 'Periode';
    }
}
