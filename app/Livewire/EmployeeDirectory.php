<?php

namespace App\Livewire;

use App\Models\Employee;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class EmployeeDirectory extends Component
{
    public string $search = '';

    public string $source = '';

    public string $status = '';

    public function render(): View
    {
        $employees = Employee::query()->search($this->search ?: null)->when($this->source === 'ARKAS', fn ($q) => $q->fromArkas())->when($this->source === 'DAPODIK', fn ($q) => $q->fromDapodik())->when($this->source === 'MANUAL', fn ($q) => $q->manualOnly())->when($this->status === 'active', fn ($q) => $q->where('is_active', true))->when($this->status === 'inactive', fn ($q) => $q->where('is_active', false))->orderByDesc('is_active')->orderBy('name')->limit(100)->get();

        return view('livewire.employee-directory', compact('employees'));
    }
}
