<?php

namespace App\Livewire;

use App\Models\FiscalYear;
use App\Models\FundSource;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class YearSelector extends Component
{
    public string $search = '';

    public bool $hasFundSourceContext = true;

    public ?int $selectedYear = null;

    public ?int $selectedFundSourceId = null;

    public function updatedSelectedYear(?int $year): void
    {
        $this->selectedFundSourceId = null;
    }

    public function selectYear(int $yearId): void
    {
        $year = FiscalYear::query()->findOrFail($yearId);
        abort_unless(Schema::connection('school')->hasColumn('fiscal_years', 'fund_source_id') && $year->fund_source_id, 422);
        session()->put(['active_fiscal_year_id' => $year->id, 'active_fund_source_id' => $year->fund_source_id]);
        $this->redirectRoute('dashboard');
    }

    public function selectContext(): void
    {
        abort_unless($this->hasFundSourceContext, 422);

        $this->validate([
            'selectedYear' => ['required', 'integer'],
            'selectedFundSourceId' => ['required', 'integer'],
        ]);

        $year = FiscalYear::query()
            ->where('year', $this->selectedYear)
            ->where('fund_source_id', $this->selectedFundSourceId)
            ->where('is_active', true)
            ->firstOrFail();

        session()->put([
            'active_fiscal_year_id' => $year->id,
            'active_fund_source_id' => $year->fund_source_id,
        ]);

        $this->redirectRoute('dashboard');
    }

    public function render(): View
    {
        $term = trim($this->search);
        $years = FiscalYear::query()
            ->whereNotNull('fund_source_id')
            ->where('is_active', true)
            ->when($term !== '', fn ($query) => $query->where('year', 'like', '%'.$term.'%'))
            ->select('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year');
        $fundSourceIds = $this->selectedYear
            ? FiscalYear::query()
                ->where('year', $this->selectedYear)
                ->where('is_active', true)
                ->whereNotNull('fund_source_id')
                ->pluck('fund_source_id')
            : collect();
        $fundSources = FundSource::query()
            ->when(Schema::connection('school')->hasColumn('fund_sources', 'is_hidden'), fn ($query) => $query->where('is_hidden', false))
            ->whereIn('id', $fundSourceIds)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return view('livewire.year-selector', compact('years', 'fundSources'));
    }
}
