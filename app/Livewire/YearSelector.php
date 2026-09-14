<?php

namespace App\Livewire;

use App\Models\FiscalYear;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class YearSelector extends Component
{
    public string $search = '';

    public function selectYear(int $yearId): void
    {
        $year = FiscalYear::query()->findOrFail($yearId);
        abort_unless(Schema::connection('school')->hasColumn('fiscal_years', 'fund_source_id') && $year->fund_source_id, 422);
        session()->put(['active_fiscal_year_id' => $year->id, 'active_fund_source_id' => $year->fund_source_id]);
        $this->redirectRoute('dashboard');
    }

    public function render(): View
    {
        $years = FiscalYear::query()->with('fundSource')->whereNotNull('fund_source_id')->where('is_active', true)->when(trim($this->search) !== '', fn ($query) => $query->where('year', 'like', '%'.trim($this->search).'%'))->orderByDesc('year')->orderBy('fund_source')->get();

        return view('livewire.year-selector', compact('years'));
    }
}
