<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Reactive filter card for the RKAS budgeting page.
 *
 * Holds filter state only. Every change navigates (SPA) to the canonical
 * GET URL so the existing controller remains the single owner of the
 * budgeting queries and the GET contract stays bookmarkable.
 */
class RkasBudgetFilter extends Component
{
    #[Url(except: 'semua')]
    public string $mode = 'semua';

    #[Url(except: null)]
    public ?int $periode = null;

    #[Url(except: '')]
    public string $q = '';

    #[Url(except: '')]
    public string $program = '';

    #[Url(except: '')]
    public string $sub = '';

    #[Url(except: '')]
    public string $kegiatan = '';

    /** @var array<string, string> */
    public array $modes = [
        'semua' => 'Semua',
        'bulan' => 'Bulan',
        'triwulan' => 'Triwulan',
        'semester' => 'Semester',
    ];

    public function mount(): void
    {
        $request = request();

        if ($request->query('mode') === null) {
            $aliases = ['year' => 'semua', 'month' => 'bulan', 'quarter' => 'triwulan', 'semester' => 'semester'];
            $legacyScope = trim((string) $request->query('scope', ''));
            if (isset($aliases[$legacyScope])) {
                $this->mode = $aliases[$legacyScope];
            }
        }

        if ($request->query('periode') === null && $request->query('scope_value') !== null) {
            $this->periode = (int) $request->query('scope_value') ?: null;
        }

        if ($request->query('sub') === null) {
            $this->sub = trim((string) $request->query('subprogram', ''));
        }

        if ($request->query('kegiatan') === null) {
            $this->kegiatan = trim((string) $request->query('activity', ''));
        }

        $this->q = trim((string) $this->q);

        if (! isset($this->modes[$this->mode])) {
            $this->mode = 'semua';
        }

        if (! $this->isPeriodeValid($this->periode)) {
            $this->periode = null;
        }

        $this->coerceHierarchy();
    }

    public function updatedMode(): void
    {
        if (! isset($this->modes[$this->mode])) {
            $this->mode = 'semua';
        }
        $this->periode = null;
        $this->navigate();
    }

    public function updatedPeriode(): void
    {
        if (! $this->isPeriodeValid($this->periode)) {
            $this->periode = null;
        }
        $this->navigate();
    }

    public function updatedQ(): void
    {
        $this->q = trim((string) $this->q);
        $this->navigate();
    }

    public function updatedProgram(): void
    {
        $this->sub = '';
        $this->kegiatan = '';
        $this->coerceHierarchy();
        $this->navigate();
    }

    public function updatedSub(): void
    {
        $this->kegiatan = '';
        $this->coerceHierarchy();
        $this->navigate();
    }

    public function updatedKegiatan(): void
    {
        $this->coerceHierarchy();
        $this->navigate();
    }

    public function clearFilters(): void
    {
        $this->reset(['mode', 'periode', 'q', 'program', 'sub', 'kegiatan']);
        $this->mode = 'semua';
        $this->navigate();
    }

    public function render(): View
    {
        return view('livewire.rkas-budget-filter', [
            'bulanOptions' => $this->bulanOptions(),
            'twOptions' => $this->twOptions(),
            'semOptions' => $this->semOptions(),
            'programOptions' => $this->programOptions(),
            'subOptions' => $this->subOptions(),
            'kegiatanOptions' => $this->kegiatanOptions(),
        ]);
    }

    protected function navigate(): void
    {
        $query = array_filter([
            'mode' => $this->mode === 'semua' ? null : $this->mode,
            'periode' => $this->periode,
            'q' => $this->q !== '' ? $this->q : null,
            'program' => $this->program !== '' ? $this->program : null,
            'sub' => $this->sub !== '' ? $this->sub : null,
            'kegiatan' => $this->kegiatan !== '' ? $this->kegiatan : null,
        ], fn ($value): bool => $value !== null && $value !== '');

        $this->redirect(route('rkas-budget.index', $query), navigate: true);
    }

    protected function isPeriodeValid(?int $periode): bool
    {
        if ($this->mode === 'semua') {
            return $periode === null;
        }

        if ($periode === null) {
            return true;
        }

        return match ($this->mode) {
            'bulan' => $periode >= 1 && $periode <= 12,
            'triwulan' => $periode >= 1 && $periode <= 4,
            'semester' => $periode >= 1 && $periode <= 2,
            default => false,
        };
    }

    protected function fiscalYearId(): ?int
    {
        $sessionYearId = (int) session('active_fiscal_year_id');

        return $sessionYearId > 0 ? $sessionYearId : null;
    }

    protected function effectiveFundSourceId(): ?int
    {
        $sessionFundId = (int) session('active_fund_source_id');

        return $sessionFundId > 0 ? $sessionFundId : null;
    }

    /** @return Collection<int, array{id:int,nama:string,n:int}> */
    protected function bulanOptions(): Collection
    {
        $counts = $this->periodCounts('month_number');

        $names = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        return collect($names)->map(fn (string $name, int $index): array => [
            'id' => $index + 1,
            'nama' => $name,
            'n' => (int) ($counts[$index + 1] ?? 0),
        ])->values();
    }

    /** @return Collection<int, array{id:int,nama:string,n:int}> */
    protected function twOptions(): Collection
    {
        $counts = $this->periodCounts('quarter_number');

        return collect(range(1, 4))->map(fn (int $quarter): array => [
            'id' => $quarter,
            'nama' => 'Triwulan '.$quarter,
            'n' => (int) ($counts[$quarter] ?? 0),
        ])->values();
    }

    /** @return Collection<int, array{id:int,nama:string,n:int}> */
    protected function semOptions(): Collection
    {
        $counts = $this->periodCounts('semester_number');

        return collect(range(1, 2))->map(fn (int $semester): array => [
            'id' => $semester,
            'nama' => 'Semester '.$semester,
            'n' => (int) ($counts[$semester] ?? 0),
        ])->values();
    }

    /** @return array<int, int> */
    protected function periodCounts(string $column): array
    {
        $fiscalYearId = $this->fiscalYearId();
        $fundSourceId = $this->effectiveFundSourceId();

        if ($fiscalYearId === null || $fundSourceId === null) {
            return [];
        }

        return DB::connection('school')->table('arkas_rkas_periods')
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('fund_source_id', $fundSourceId)
            ->where(function ($query): void {
                $query->where('amount', '>', 0)->orWhere('volume', '>', 0);
            })
            ->selectRaw($column.' as period, COUNT(DISTINCT source_rapbs_id) as n')
            ->groupBy($column)
            ->pluck('n', 'period')
            ->map(fn ($value): int => (int) $value)
            ->all();
    }

    /** @return array<string, string> activity code => name */
    protected function referenceNames(): array
    {
        $fiscalYearId = $this->fiscalYearId();

        if ($fiscalYearId === null) {
            return [];
        }

        $db = DB::connection('school');
        $names = $db->table('activity_references')
            ->where('fiscal_year_id', $fiscalYearId)
            ->pluck('activity_name', 'activity_code')
            ->mapWithKeys(fn ($name, $code): array => [trim((string) $code, '.') => (string) $name])
            ->all();

        try {
            $hierarchy = $db->table('activity_hierarchy_references')
                ->where('fiscal_year_id', $fiscalYearId)
                ->select(['program_code', 'program_name', 'sub_program_code', 'sub_program_name'])
                ->distinct()
                ->get();
        } catch (\Throwable) {
            return $names;
        }

        foreach ($hierarchy as $row) {
            $programCode = trim((string) ($row->program_code ?? ''), '.');
            $subprogramCode = trim((string) ($row->sub_program_code ?? ''), '.');
            if ($programCode !== '' && ! isset($names[$programCode])) {
                $names[$programCode] = (string) ($row->program_name ?: 'Program');
            }
            if ($subprogramCode !== '' && ! isset($names[$subprogramCode])) {
                $names[$subprogramCode] = (string) ($row->sub_program_name ?: 'Subprogram');
            }
        }

        return $names;
    }

    /** @return Collection<int, string> */
    protected function activityCodes(): Collection
    {
        $fiscalYearId = $this->fiscalYearId();
        $fundSourceId = $this->effectiveFundSourceId();

        if ($fiscalYearId === null || $fundSourceId === null) {
            return collect();
        }

        return DB::connection('school')->table('arkas_rkas_items')
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('fund_source_id', $fundSourceId)
            ->select('activity_code')
            ->distinct()
            ->orderBy('activity_code')
            ->pluck('activity_code')
            ->map(fn ($code): string => trim((string) $code, '.'))
            ->filter()
            ->values();
    }

    /** @return Collection<int, array{kode:string,nama:string}> */
    protected function programOptions(): Collection
    {
        $names = $this->referenceNames();

        return $this->activityCodes()
            ->map(fn (string $code): string => explode('.', $code)[0])
            ->unique()
            ->values()
            ->map(fn (string $code): array => ['kode' => $code, 'nama' => $names[$code] ?? 'Program'])
            ->values();
    }

    /** @return Collection<int, array{kode:string,nama:string}> */
    protected function subOptions(): Collection
    {
        if ($this->program === '') {
            return collect();
        }

        $names = $this->referenceNames();

        return $this->activityCodes()
            ->filter(fn (string $code): bool => self::isWithin($code, $this->program))
            ->map(function (string $code): ?string {
                $parts = explode('.', $code);

                return count($parts) >= 2 ? implode('.', array_slice($parts, 0, 2)) : null;
            })
            ->filter()
            ->unique()
            ->values()
            ->map(fn (string $code): array => ['kode' => $code, 'nama' => $names[$code] ?? 'Subprogram'])
            ->values();
    }

    /** @return Collection<int, array{kode:string,nama:string}> */
    protected function kegiatanOptions(): Collection
    {
        $fiscalYearId = $this->fiscalYearId();
        $fundSourceId = $this->effectiveFundSourceId();

        if ($fiscalYearId === null || $fundSourceId === null) {
            return collect();
        }

        $query = DB::connection('school')->table('arkas_rkas_items')
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('fund_source_id', $fundSourceId)
            ->select(['activity_code', 'activity_name'])
            ->distinct()
            ->orderBy('activity_code');

        if ($this->sub !== '') {
            $sub = $this->sub;
            $query->where(function ($filter) use ($sub): void {
                $filter->where('activity_code', $sub)->orWhere('activity_code', 'like', $sub.'.%');
            });
        } elseif ($this->program !== '') {
            $program = $this->program;
            $query->where(function ($filter) use ($program): void {
                $filter->where('activity_code', $program)->orWhere('activity_code', 'like', $program.'.%');
            });
        } else {
            return collect();
        }

        return $query->get()
            ->map(fn ($row): array => [
                'kode' => trim((string) $row->activity_code, '.'),
                'nama' => $row->activity_name ?: 'Kegiatan belum diisi',
            ])
            ->filter(fn (array $option): bool => $option['kode'] !== '')
            ->unique('kode')
            ->values();
    }

    protected function coerceHierarchy(): void
    {
        $codes = $this->activityCodes()->all();
        $isWithin = fn (string $code, string $parent): bool => self::isWithin($code, $parent);

        if ($this->program !== '' && ! in_array($this->program, array_map(fn (string $code): string => explode('.', $code)[0], $codes), true)) {
            $this->program = '';
        }

        $subCodes = [];
        foreach ($codes as $code) {
            $parts = explode('.', $code);
            if (count($parts) >= 2) {
                $subCodes[] = implode('.', array_slice($parts, 0, 2));
            }
        }
        $subCodes = array_values(array_unique($subCodes));

        if ($this->sub !== '' && (! in_array($this->sub, $subCodes, true) || ($this->program !== '' && ! $isWithin($this->sub, $this->program)))) {
            $this->sub = '';
        }

        if ($this->kegiatan !== '' && (! in_array($this->kegiatan, $codes, true)
            || ($this->sub !== '' && ! $isWithin($this->kegiatan, $this->sub))
            || ($this->sub === '' && $this->program !== '' && ! $isWithin($this->kegiatan, $this->program)))) {
            $this->kegiatan = '';
        }

        if ($this->kegiatan !== '') {
            $parts = explode('.', $this->kegiatan);
            $this->program = $parts[0];
            $this->sub = count($parts) >= 2 ? implode('.', array_slice($parts, 0, 2)) : '';
        }
    }

    protected static function isWithin(string $code, string $parent): bool
    {
        return $code === $parent || str_starts_with($code, $parent.'.');
    }
}
