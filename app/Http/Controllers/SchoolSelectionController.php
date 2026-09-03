<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Services\SchoolDatabaseManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class SchoolSelectionController extends Controller
{
    public function create(Request $request): View
    {
        $schools = School::query()->with('databaseRecord')->when(! $request->user()->isAdministrator(), fn ($q) => $q->whereKey($request->user()->school_id))->orderBy('name')->get();

        return view('schools.select', compact('schools'));
    }

    public function store(Request $request, SchoolDatabaseManager $databases): RedirectResponse
    {
        $data = $request->validate(['school_id' => ['required', 'exists:schools,id']]);
        $school = School::findOrFail($data['school_id']);
        abort_unless($request->user()->isAdministrator() || $request->user()->school_id === $school->id, 403);
        $databases->activate($school);
        if (! Schema::connection('school')->hasColumn('arkas_rkas_items', 'fund_source_id')) {
            $databases->migrate($school);
        }
        $request->session()->put('active_school_id', $school->id);
        $request->session()->forget(['active_fiscal_year_id', 'active_fund_source_id']);

        return redirect()
            ->route('years.select')
            ->with('success', 'Sekolah aktif: '.$school->name.'. Pilih tahun dan sumber dana untuk melanjutkan.');
    }
}
