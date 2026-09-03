<?php

namespace App\Http\Middleware;

use App\Models\School;
use App\Services\SchoolDatabaseManager;
use Closure;
use Illuminate\Http\Request;

class EnsureActiveSchool
{
    public function handle(Request $request, Closure $next)
    {
        $school = School::find(session('active_school_id'));
        $allowed = $school && ($request->user()->isAdministrator() || $request->user()->school_id === $school->id);
        if (! $allowed) {
            $request->session()->forget(['active_school_id', 'active_fiscal_year_id', 'active_fund_source_id']);

            return redirect()->route('schools.select')->with('error', 'Pilih sekolah aktif terlebih dahulu.');
        }

        app(SchoolDatabaseManager::class)->ensureMigrated($school);

        return $next($request);
    }
}
