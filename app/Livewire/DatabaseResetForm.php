<?php

namespace App\Livewire;

use App\Models\School;
use App\Services\SchoolDatabaseResetService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class DatabaseResetForm extends Component
{
    public ?School $school = null;

    public string $databasePath = '—';

    public string $status = '—';

    public string $integrity = '—';

    public string $confirmation = '';

    public function mount(array $active, ?array $activeStatus = null): void
    {
        $this->school = $active['school'] ?? null;
        $this->databasePath = (string) ($activeStatus['path'] ?? $active['database'] ?? '—');
        $this->status = (string) ($activeStatus['status'] ?? '—');
        $this->integrity = (string) ($activeStatus['integrity'] ?? '—');
    }

    public function resetDatabase(SchoolDatabaseResetService $resetter): void
    {
        $this->authorizeAdministrator();

        if (! $this->school || (int) session('active_school_id') !== (int) $this->school->id) {
            throw ValidationException::withMessages(['confirmation' => 'Reset hanya dapat dijalankan untuk sekolah yang sedang aktif.']);
        }

        $expected = 'RESET '.$this->school->npsn;
        $this->validate(['confirmation' => ['required', 'string', 'max:80', 'in:'.$expected]]);
        $resetter->reset($this->school);
        session()->forget(['active_fiscal_year_id', 'active_fund_source_id']);
        session()->flash('success', 'Database '.$this->school->name.' berhasil direset total.');
        $this->confirmation = '';
    }

    public function render()
    {
        return view('livewire.database-reset-form');
    }

    private function authorizeAdministrator(): void
    {
        abort_unless(auth()->user()?->isAdministrator(), 403);
    }
}
