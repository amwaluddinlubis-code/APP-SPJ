<?php

namespace App\UseCases\Spj;

use App\Models\FiscalPeriodClosure;
use App\Services\FiscalPeriodWorkflowService;
use App\Services\SpjReadPathSelector;
use App\Services\SpjV2PeriodLifecycleService;
use App\Support\ActiveSpjContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SpjFiscalPeriodUseCase
{
    public function __construct(
        private readonly FiscalPeriodWorkflowService $periods,
        private readonly ActiveSpjContext $context,
        private readonly SpjReadPathSelector $readPaths,
        private readonly SpjV2PeriodLifecycleService $v2Periods,
    ) {}

    public function closeQuarter(Request $request): RedirectResponse
    {
        $data = $request->validate(['quarter' => ['required', 'integer', 'between:1,4']]);
        if ($this->isV2Requested()) {
            $result = $this->v2Periods->close((int) $data['quarter']);

            return $result['status'] === 'CLOSED'
                ? back()->with('success', 'Triwulan '.$data['quarter'].' berhasil ditutup melalui effective-context V2.')
                : back()->with('error', $result['reason']);
        }
        $period = $this->periods->period($this->context->fiscalYearId(), (int) $data['quarter']);

        try {
            $this->periods->close($period, (int) $this->context->fundSourceId(), $this->context->actorId());
        } catch (\RuntimeException $exception) {
            $flashType = str_contains($exception->getMessage(), 'belum FINAL') ? 'warning' : 'error';

            return back()->with($flashType, $exception->getMessage());
        }

        return back()->with('success', 'Triwulan '.$data['quarter'].' berhasil ditutup.');
    }

    public function reopenQuarter(Request $request, string $periodId): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        if ($this->isV2Requested()) {
            $result = $this->v2Periods->reopen((int) $periodId, (string) $data['reason']);

            return $result['status'] === 'OPEN'
                ? back()->with('success', 'Triwulan dibuka kembali melalui effective-context V2.')
                : back()->with('error', $result['reason']);
        }
        $period = FiscalPeriodClosure::query()->where('fiscal_year_id', $this->context->fiscalYearId())->findOrFail($periodId);
        $this->periods->reopen($period, $this->context->actorId(), $data['reason']);

        return back()->with('success', 'Triwulan dibuka kembali dan alasan telah dicatat.');
    }

    private function isV2Requested(): bool
    {
        return $this->readPaths->select(
            DB::connection('school'),
            $this->context->fiscalYearId(),
            (int) $this->context->fundSourceId(),
        )['requested'] === SpjReadPathSelector::V2;
    }
}
