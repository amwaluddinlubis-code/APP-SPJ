<?php

namespace App\UseCases\Spj;

use App\Models\FiscalPeriodClosure;
use App\Services\FiscalPeriodWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SpjFiscalPeriodUseCase
{
    public function __construct(private readonly FiscalPeriodWorkflowService $periods) {}

    public function closeQuarter(Request $request): RedirectResponse
    {
        $data = $request->validate(['quarter' => ['required', 'integer', 'between:1,4']]);
        $period = $this->periods->period((int) session('active_fiscal_year_id'), (int) $data['quarter']);
        $this->periods->close($period, (int) session('active_fund_source_id'), (int) auth()->id());

        return back()->with('success', 'Triwulan '.$data['quarter'].' berhasil ditutup.');
    }

    public function reopenQuarter(Request $request, string $periodId): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $period = FiscalPeriodClosure::query()->where('fiscal_year_id', session('active_fiscal_year_id'))->findOrFail($periodId);
        $this->periods->reopen($period, (int) auth()->id(), $data['reason']);

        return back()->with('success', 'Triwulan dibuka kembali dan alasan telah dicatat.');
    }
}
