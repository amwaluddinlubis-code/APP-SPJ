<?php

namespace App\Http\Controllers;

use App\Models\ArkasSource;
use App\Models\FiscalYear;
use App\Models\SpjFreshTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SpjFreshTransactionController
{
    public function index(Request $request): View
    {
        $fiscalYearId = (int) session('active_fiscal_year_id');
        $fundSourceId = (int) session('active_fund_source_id');
        $source = ArkasSource::query()->where('school_id', session('active_school_id'))->first();
        $query = SpjFreshTransaction::query()
            ->with(['rawMirrorRow', 'package'])
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('fund_source_id', $fundSourceId);

        $search = trim((string) $request->query('q'));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('source_key', 'like', '%'.$search.'%')
                    ->orWhere('source_status', 'like', '%'.$search.'%');
            });
        }

        $status = strtoupper(trim((string) $request->query('status')));
        if (in_array($status, ['ACTIVE', 'DELETED'], true)) {
            $query->where('source_status', $status);
        } else {
            $status = '';
        }

        $statsQuery = clone $query;
        $stats = [
            'total' => (clone $statsQuery)->count(),
            'active' => (clone $statsQuery)->where('source_status', 'ACTIVE')->count(),
            'reconciliation' => (clone $statsQuery)->where('requires_reconciliation', true)->count(),
            'packaged' => (clone $statsQuery)->whereHas('package')->count(),
        ];
        $perPage = min(max($request->integer('per_page', 25), 10), 100);
        $transactions = $query->latest('id')->paginate($perPage)->withQueryString();
        $activeYear = FiscalYear::query()->with('fundSource')->find($fiscalYearId);

        return view('spj.fresh-transactions', compact('transactions', 'activeYear', 'source', 'search', 'status', 'stats'));
    }
}
