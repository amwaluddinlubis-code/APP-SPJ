<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class ReconciliationController extends Controller
{
    public function index(): View
    {
        return view('reconciliation.index');
    }
}
