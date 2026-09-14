<?php

namespace App\Livewire;

use App\Models\School;
use App\Services\OperationalAuditService;
use App\Services\SchoolDatabaseManager;
use Livewire\Component;

class DatabaseMaintenance extends Component
{
    public ?int $schoolId = null;

    public bool $available = false;

    public function mount(?array $activeStatus = null): void
    {
        if ($activeStatus && isset($activeStatus['school'])) {
            $this->schoolId = (int) $activeStatus['school']->id;
            $this->available = true;
        }
    }

    public function run(string $action, SchoolDatabaseManager $manager, OperationalAuditService $audit): void
    {
        if (! $this->schoolId || ! in_array($action, ['checkpoint', 'migrate', 'vacuum', 'provision'], true)) {
            return;
        }

        $school = School::findOrFail($this->schoolId);
        try {
            match ($action) {
                'checkpoint' => $manager->checkpoint($school),
                'migrate' => $manager->migrate($school),
                'vacuum' => $manager->vacuum($school),
                'provision' => $manager->provision($school),
            };
            $audit->record(session('active_fiscal_year_id'), 'SCHOOL_DATABASE', $school->id, strtoupper($action), 'Operasi database '.$action.' dijalankan.');
            session()->flash('success', ucfirst($action).' berhasil untuk '.$school->name.'.');
        } catch (\Throwable) {
            session()->flash('error', ucfirst($action).' database gagal. Periksa log aplikasi.');
        }
    }

    public function render()
    {
        return view('livewire.database-maintenance');
    }
}
