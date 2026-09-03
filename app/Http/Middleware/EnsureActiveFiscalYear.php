<?php

namespace App\Http\Middleware;

use App\Models\FiscalYear;
use Closure;
use Illuminate\Http\Request;

class EnsureActiveFiscalYear
{
    public function handle(Request $request, Closure $next)
    {
        $year = FiscalYear::find(session('active_fiscal_year_id'));
        $allowed = $year
            && (int) $year->fund_source_id === (int) session('active_fund_source_id')
            && session()->has('active_school_id');
        if (! $allowed) {
            $request->session()->forget(['active_fiscal_year_id', 'active_fund_source_id']);

            return redirect()->route('years.select')->with('error', 'Pilih tahun anggaran aktif terlebih dahulu.');
        }

        return $next($request);
    }
}
