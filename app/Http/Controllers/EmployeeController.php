<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\SpjHonor;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'source' => ['nullable', 'in:PEGAWAI,PTK,DAPODIK,MANUAL'],
            'status' => ['nullable', 'in:active,inactive'],
            'perPage' => ['nullable', 'in:15,25,50,100'],
        ]);
        $perPage = (int) ($filters['perPage'] ?? 15);

        $employees = Employee::query()
            ->search($filters['q'] ?? null)
            ->when($filters['source'] ?? null, fn (Builder $query, string $source) => $query->where('source_type', $source))
            ->when(($filters['status'] ?? null) === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->orderByDesc('is_active')->orderBy('name')->orderBy('id')
            ->paginate($perPage)->withQueryString();

        $honors = $this->honorsFor($employees->getCollection()->all());
        $employees->getCollection()->each(function (Employee $employee) use ($honors): void {
            $rows = $honors->get($this->identityKey($employee), collect());
            $employee->setAttribute('honor_count', $rows->count());
            $employee->setAttribute('honor_gross', $rows->sum('gross_amount'));
            $employee->setAttribute('honor_net', $rows->sum('net_amount'));
        });

        $summary = [
            'total' => Employee::count(),
            'active' => Employee::where('is_active', true)->count(),
            'dapodik' => Employee::where('source_type', 'DAPODIK')->count(),
            'manual' => Employee::where('source_type', 'MANUAL')->count(),
        ];

        return view('employees.index', compact('employees', 'filters', 'summary'));
    }

    public function show(int $employeeId): View
    {
        $employee = Employee::findOrFail($employeeId);
        $honors = $this->honorsFor([$employee])->get($this->identityKey($employee), collect())
            ->sortByDesc(fn (SpjHonor $honor) => $honor->item?->transaction?->transaction_date?->format('Y-m-d'));

        return view('employees.show', compact('employee', 'honors'));
    }

    public function create(): View
    {
        return view('employees.form', ['employee' => new Employee]);
    }

    public function edit(int $employeeId): View
    {
        return view('employees.form', ['employee' => Employee::findOrFail($employeeId)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $employee = new Employee;
        $this->persist($request, $employee);

        return redirect()->route('employees.show', $employee)->with('success', 'Pegawai berhasil ditambahkan.');
    }

    public function update(Request $request, int $employeeId): RedirectResponse
    {
        $employee = Employee::findOrFail($employeeId);
        $this->persist($request, $employee);

        return redirect()->route('employees.show', $employee)->with('success', 'Data pegawai berhasil diperbarui.');
    }

    public function destroy(int $employeeId): RedirectResponse
    {
        $employee = Employee::findOrFail($employeeId);
        if ($employee->source_type !== 'MANUAL') {
            return back()->with('error', 'Data hasil sinkronisasi tidak dapat dihapus; nonaktifkan atau perbaiki dari sumbernya.');
        }
        $employee->delete();

        return redirect()->route('employees.index')->with('success', 'Pegawai manual berhasil dihapus.');
    }

    private function persist(Request $request, Employee $employee): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'], 'nuptk' => ['nullable', 'string', 'max:30', Rule::unique('school.employees', 'nuptk')->ignore($employee->id)],
            'nip' => ['nullable', 'string', 'max:30'], 'nik' => ['nullable', 'string', 'max:30'], 'gender' => ['nullable', 'in:L,P'],
            'birth_place' => ['nullable', 'string', 'max:100'], 'birth_date' => ['nullable', 'date'], 'religion' => ['nullable', 'string', 'max:50'],
            'employment_status' => ['nullable', 'string', 'max:100'], 'staff_type' => ['nullable', 'string', 'max:100'], 'position' => ['nullable', 'string', 'max:150'],
            'last_education' => ['nullable', 'string', 'max:100'], 'last_study_field' => ['nullable', 'string', 'max:150'], 'rank_group' => ['nullable', 'string', 'max:100'],
            'npwp' => ['nullable', 'string', 'max:40'], 'bank_name' => ['nullable', 'string', 'max:100'], 'bank_account' => ['nullable', 'string', 'max:60'], 'is_active' => ['nullable', 'boolean'],
        ]);
        $employee->fill($data + ['source_type' => $employee->exists ? $employee->source_type : 'MANUAL', 'source_key' => $employee->exists ? $employee->source_key : 'MANUAL:'.Str::uuid()]);
        $employee->normalized_name = Str::of($data['name'])->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->squish();
        $employee->is_active = $request->boolean('is_active', true);
        $employee->save();
    }

    private function honorsFor(array $employees)
    {
        $nips = collect($employees)->pluck('nip')->filter()->unique()->values();
        $niks = collect($employees)->pluck('nik')->filter()->unique()->values();
        $names = collect($employees)->pluck('name')->filter()->unique()->values();

        if ($nips->isEmpty() && $niks->isEmpty() && $names->isEmpty()) {
            return collect();
        }

        return SpjHonor::query()
            ->with(['item.transaction.spjPackage'])
            ->whereHas('item.transaction', fn (Builder $query) => $query->activeContext())
            ->where(function (Builder $query) use ($nips, $niks, $names): void {
                $query->whereIn('nip', $nips)->orWhereIn('nik', $niks)->orWhereIn('name', $names);
            })->get()->groupBy(fn (SpjHonor $honor) => $this->identityKey($honor));
    }

    private function identityKey(object $person): string
    {
        return filled($person->nip ?? null) ? 'nip:'.trim($person->nip)
            : (filled($person->nik ?? null) ? 'nik:'.trim($person->nik) : 'name:'.mb_strtolower(trim($person->name ?? '')));
    }
}
