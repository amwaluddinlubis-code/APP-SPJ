<?php

namespace App\Livewire;

use App\Models\School;
use App\Services\SchoolDatabaseManager;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class SchoolMaster extends Component
{
    public string $search = '';

    public string $schoolCode = '';

    public string $npsn = '';

    public string $name = '';

    public string $address = '';

    public string $desa = '';

    public string $district = '';

    public string $regency = '';

    public string $province = '';

    public function createSchool(SchoolDatabaseManager $databases): void
    {
        $this->authorizeAdministrator();
        $data = $this->validate([
            'schoolCode' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:schools,school_code'],
            'npsn' => ['required', 'string', 'max:16', 'unique:schools,npsn'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'desa' => ['nullable', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
            'regency' => ['nullable', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:120'],
        ]);
        $school = School::query()->create([
            'school_code' => $data['schoolCode'], 'npsn' => $data['npsn'], 'name' => $data['name'],
            'address' => $data['address'], 'desa' => $data['desa'], 'district' => $data['district'],
            'regency' => $data['regency'], 'province' => $data['province'],
        ]);
        $databases->provision($school);
        $this->reset(['schoolCode', 'npsn', 'name', 'address', 'desa', 'district', 'regency', 'province']);
        session()->flash('success', 'Profil dan database lokal sekolah berhasil ditambahkan.');
    }

    public function render(): View
    {
        $query = School::query()->with('databaseRecord')->orderBy('name');
        if (filled($this->search)) {
            $search = trim($this->search);
            $query->where(fn ($filter) => $filter->where('name', 'like', '%'.$search.'%')->orWhere('npsn', 'like', '%'.$search.'%')->orWhere('school_code', 'like', '%'.$search.'%'));
        }

        return view('livewire.school-master', ['schools' => $query->get()]);
    }

    private function authorizeAdministrator(): void
    {
        abort_unless(auth()->user()?->isAdministrator(), 403);
    }
}
