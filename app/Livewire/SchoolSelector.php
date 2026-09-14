<?php

namespace App\Livewire;

use App\Models\School;
use App\Services\SchoolDatabaseManager;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class SchoolSelector extends Component
{
    public string $search = '';

    public function selectSchool(int $schoolId, SchoolDatabaseManager $databases): void
    {
        $school = School::query()->findOrFail($schoolId);
        abort_unless(auth()->user()->isAdministrator() || auth()->user()->school_id === $school->id, 403);
        $databases->ensureMigrated($school);
        session()->put('active_school_id', $school->id);
        session()->forget(['active_fiscal_year_id', 'active_fund_source_id']);
        $this->redirectRoute('years.select');
    }

    public function render(): View
    {
        $term = trim($this->search);
        $schools = School::query()->with('databaseRecord')
            ->when(! auth()->user()->isAdministrator(), fn ($query) => $query->whereKey(auth()->user()->school_id))
            ->when($term !== '', fn ($query) => $query->where(fn ($filter) => $filter->where('name', 'like', '%'.$term.'%')->orWhere('npsn', 'like', '%'.$term.'%')))
            ->orderBy('name')->get();

        return view('livewire.school-selector', compact('schools'));
    }
}
