<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class ProductivityDashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard');
    }
}
