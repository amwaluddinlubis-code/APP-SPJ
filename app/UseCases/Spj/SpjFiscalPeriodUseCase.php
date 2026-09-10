<?php

namespace App\UseCases\Spj;

use App\Models\FiscalPeriodClosure;
use App\Services\FiscalPeriodWorkflowService;
use App\Support\ActiveSpjContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SpjFiscalPeriodUseCase
{
    public function __construct(
        private readonly FiscalPeriodWorkflowService $periods,
        private readonly ActiveSpjContext $context,
    ) {}

    public function closeQuarter(Request $request): RedirectResponse
    {
        $data = $request->validate(['quarter' => ['required', 'integer', 'between:1,4']]);
        $period = $this->periods->period($this->context->fiscalYearId(), (int) $data['quarter']);
        $this->periods->close($period, (int) $this->context->fundSourceId(), $this->context->actorId());

        return back()->with('success', 'Triwulan '.$data['quarter'].' berhasil ditutup.');
    }

    public function reopenQuarter(Request $request, string $periodId): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $period = FiscalPeriodClosure::query()->where('fiscal_year_id', $this->context->fiscalYearId())->findOrFail($periodId);
        $this->periods->reopen($period, $this->context->actorId(), $data['reason']);

        return back()->with('success', 'Triwulan dibuka kembali dan alasan telah dicatat.');
    }
}
