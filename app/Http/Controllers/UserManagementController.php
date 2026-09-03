<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(): View
    {
        return view('users.index', [
            'users' => User::query()->with('school')->orderBy('role')->orderBy('name')->get(),
            'schools' => School::query()->orderBy('name')->get(),
            'roles' => User::roleOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        User::query()->create($data);

        return back()->with('success', 'User baru berhasil dibuat.');
    }

    public function update(Request $request, string $userId): RedirectResponse
    {
        $user = User::query()->findOrFail($userId);
        $data = $this->validated($request, $user);

        if ($user->is($request->user()) && ($data['role'] ?? null) !== User::ROLE_ADMIN) {
            return back()->with('error', 'Anda tidak dapat menurunkan role administrator akun sendiri.');
        }

        if ($user->isAdministrator() && ($data['role'] ?? null) !== User::ROLE_ADMIN && $this->adminCount() <= 1) {
            return back()->with('error', 'Minimal harus ada satu administrator aktif.');
        }

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

        return back()->with('success', 'User berhasil diperbarui.');
    }

    public function destroy(Request $request, string $userId): RedirectResponse
    {
        $user = User::query()->findOrFail($userId);

        if ($user->is($request->user())) {
            return back()->with('error', 'Anda tidak dapat menghapus akun yang sedang digunakan.');
        }

        if ($user->isAdministrator() && $this->adminCount() <= 1) {
            return back()->with('error', 'Minimal harus ada satu administrator aktif.');
        }

        $user->delete();

        return back()->with('success', 'User berhasil dihapus.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'school_id' => ['nullable', 'exists:schools,id'],
            'role' => ['required', Rule::in(array_keys(User::roleOptions()))],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
        ]);
    }

    private function adminCount(): int
    {
        return User::query()->where('role', User::ROLE_ADMIN)->count();
    }
}
