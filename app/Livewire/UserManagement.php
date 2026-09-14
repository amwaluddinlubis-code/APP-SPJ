<?php

namespace App\Livewire;

use App\Models\School;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Livewire\Component;

class UserManagement extends Component
{
    public string $search = '';

    public string $name = '';

    public string $email = '';

    public string $role = User::ROLE_OPERATOR;

    public ?int $schoolId = null;

    public string $password = '';

    public string $passwordConfirmation = '';

    public function createUser(): void
    {
        $this->validate($this->rules());
        User::query()->create([
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'school_id' => $this->schoolId,
            'password' => $this->password,
        ]);

        $this->resetForm();
        session()->flash('success', 'User baru berhasil dibuat.');
    }

    public function updateUser(int $userId, string $name, string $email, string $role, ?int $schoolId = null, string $password = '', string $passwordConfirmation = ''): void
    {
        $user = User::query()->findOrFail($userId);
        $this->fill(compact('name', 'email', 'role', 'schoolId', 'password', 'passwordConfirmation'));
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(array_keys(User::roleOptions()))],
            'schoolId' => ['nullable', 'exists:schools,id'],
            'password' => ['nullable', 'string', 'min:8', 'same:passwordConfirmation'],
        ], [], [
            'schoolId' => 'sekolah',
            'passwordConfirmation' => 'ulangan kata sandi',
        ]);

        if ($user->is(auth()->user()) && $role !== User::ROLE_ADMIN) {
            $this->addError('form', 'Anda tidak dapat menurunkan role administrator akun sendiri.');

            return;
        }

        if ($user->isAdministrator() && $role !== User::ROLE_ADMIN && $this->adminCount() <= 1) {
            $this->addError('form', 'Minimal harus ada satu administrator aktif.');

            return;
        }

        $update = ['name' => $data['name'], 'email' => $data['email'], 'role' => $data['role'], 'school_id' => $data['schoolId']];
        if (filled($password)) {
            $update['password'] = $password;
        }
        $user->update($update);

        session()->flash('success', 'User berhasil diperbarui.');
    }

    public function deleteUser(int $userId): void
    {
        $user = User::query()->findOrFail($userId);
        if ($user->is(auth()->user())) {
            $this->addError('form', 'Anda tidak dapat menghapus akun yang sedang digunakan.');

            return;
        }
        if ($user->isAdministrator() && $this->adminCount() <= 1) {
            $this->addError('form', 'Minimal harus ada satu administrator aktif.');

            return;
        }

        $user->delete();
        session()->flash('success', 'User berhasil dihapus.');
    }

    public function render(): View
    {
        $query = User::query()->with('school')->orderBy('role')->orderBy('name');
        if (filled($this->search)) {
            $search = trim($this->search);
            $query->where(function ($filter) use ($search): void {
                $filter->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        return view('livewire.user-management', [
            'users' => $query->get(),
            'schools' => School::query()->orderBy('name')->get(),
            'roles' => User::roleOptions(),
        ]);
    }

    /** @return array<string, array<int, string|Unique>> */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in(array_keys(User::roleOptions()))],
            'schoolId' => ['nullable', 'exists:schools,id'],
            'password' => ['required', 'string', 'min:8', 'same:passwordConfirmation'],
            'passwordConfirmation' => ['required', 'string'],
        ];
    }

    private function resetForm(): void
    {
        $this->reset(['name', 'email', 'schoolId', 'password', 'passwordConfirmation']);
        $this->role = User::ROLE_OPERATOR;
    }

    private function adminCount(): int
    {
        return User::query()->where('role', User::ROLE_ADMIN)->count();
    }
}
