<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Read-only RKAS budget monitor based on synchronized ARKAS data. */
class RkasBudgetController extends Controller
{
    public function __invoke(Request $request): View
    {
        $yearId = (int) session('active_fiscal_year_id');
        $db = DB::connection('school');
        $search = trim((string) $request->query('q'));
        $fundSourceId = (int) session('active_fund_source_id');
        $realization = $db->table('arkas_bku_rows')->selectRaw("json_extract(payload, '$.ID_RAPBS') as source_rapbs_id, SUM(amount) as realization")->where('fiscal_year_id', $yearId)->where('fund_source_id', $fundSourceId)->where('category', 'BELANJA')->groupByRaw("json_extract(payload, '$.ID_RAPBS')");
        $query = $db->table('arkas_rkas_items as r')->leftJoinSub($realization, 'b', fn ($join) => $join->on('b.source_rapbs_id', '=', 'r.source_rapbs_id'))->where('r.fiscal_year_id', $yearId)->where('r.fund_source_id', $fundSourceId)->selectRaw('r.*, COALESCE(b.realization, 0) as realization');
        if ($search !== '') {
            $query->where(function ($filter) use ($search) {
                $term = '%'.$search.'%';
                $filter->where('r.account_code', 'like', $term)->orWhere('r.activity_code', 'like', $term)->orWhere('r.description', 'like', $term)->orWhere('r.activity_name', 'like', $term);
            });
        }
        $items = $query->orderBy('r.activity_code')->orderBy('r.account_code')->paginate(30)->withQueryString();
        $items->getCollection()->transform(function ($item) {
            $payload = json_decode($item->payload, true) ?: [];
            $item->volume = (float) ($payload['VOLUME_TOTAL'] ?? 0);
            $item->unit = $payload['SATUAN'] ?? '—';
            $item->unit_price = (float) ($payload['HARGA_SATUAN'] ?? 0);
            $item->variance = (float) $item->amount - (float) $item->realization;

            return $item;
        });
        $budget = (float) $db->table('arkas_rkas_items')->where('fiscal_year_id', $yearId)->where('fund_source_id', $fundSourceId)->sum('amount');
        $spent = (float) $db->table('arkas_bku_rows')->where('fiscal_year_id', $yearId)->where('fund_source_id', $fundSourceId)->where('category', 'BELANJA')->sum('amount');
        $remaining = $budget - $spent;
        $overBudget = max(0, -$remaining);
        $underBudget = max(0, $remaining);
        $activityCount = $db->table('arkas_rkas_items')->where('fiscal_year_id', $yearId)->where('fund_source_id', $fundSourceId)->distinct('activity_code')->count('activity_code');

        return view('rkas-budget.index', compact('items', 'search', 'budget', 'spent', 'remaining', 'overBudget', 'underBudget', 'activityCount'));
    }
}
