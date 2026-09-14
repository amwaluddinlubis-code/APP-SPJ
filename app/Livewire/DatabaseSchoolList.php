<?php

namespace App\Livewire;

use App\Models\School;
use App\Services\OperationalAuditService;
use App\Services\SchoolDatabaseManager;
use Livewire\Component;

class DatabaseSchoolList extends Component
{
    public array $rows = [];

    public string $search = '';

    public function mount(array $rows = []): void
    {
        $this->rows = $rows;
    }

    public function activate(int $schoolId, SchoolDatabaseManager $manager, OperationalAuditService $audit): void
    {
        $school = School::findOrFail($schoolId);
        $manager->activate($school);
        session(['active_school_id' => $school->id]);
        session()->forget(['active_fiscal_year_id', 'active_fund_source_id']);
        $audit->record(session('active_fiscal_year_id'), 'SCHOOL_DATABASE', $school->id, 'AKTIFKAN', 'Database sekolah diaktifkan.');
        session()->flash('success', 'Database aktif diganti ke '.$school->name.'.');
        $this->dispatch('database-status-refresh');
    }

    public function migrate(int $schoolId, SchoolDatabaseManager $manager, OperationalAuditService $audit): void
    {
        $school = School::findOrFail($schoolId);
        try {
            $manager->migrate($school);
            $audit->record(session('active_fiscal_year_id'), 'SCHOOL_DATABASE', $school->id, 'MIGRASI', 'Migrasi database sekolah dijalankan.');
            session()->flash('success', 'Migrasi berhasil untuk '.$school->name.'.');
        } catch (\Throwable) {
            session()->flash('error', 'Migrasi database gagal. Periksa log aplikasi.');
        }
    }

    public function render()
    {
        $needle = strtolower(trim($this->search));

        return view('livewire.database-school-list', ['visibleRows' => collect($this->rows)->filter(fn (array $row): bool => $needle === '' || str_contains(strtolower($row['school']->name.' '.$row['school']->npsn), $needle))]);
    }
}
