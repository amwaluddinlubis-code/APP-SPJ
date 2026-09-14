<?php

namespace App\Http\Controllers;

use App\Services\TaxFilterService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class TaxController extends Controller
{
    public function index(Request $request, TaxFilterService $taxes): View
    {
        $search = trim((string) $request->string('q'));
        $month = $request->integer('month') ?: null;
        $quarter = $request->integer('quarter') ?: null;
        $semester = $request->integer('semester') ?: null;

        $perPageRaw = $request->input('perPage', 15);
        $perPage = $perPageRaw === 'all' ? 10000 : (int) $perPageRaw;
        $perPage = in_array($perPage, [15, 25, 50, 100, 10000]) ? $perPage : 15;

        return view('taxes.index', [
            ...$taxes->taxData($search, $month, $quarter, $semester, $perPage),
            'search' => $search,
            'month' => $month,
            'quarter' => $quarter,
            'semester' => $semester,
        ]);
    }
}
