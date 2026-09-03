<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ImpersonationController extends Controller
{
    public function index(): View
    {
        return view('impersonation.index', [
            'users' => User::query()
                ->with('school')
                ->orderBy('role')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function start(Request $request, string $userId): RedirectResponse
    {
        $admin = $request->user();
        abort_unless($admin?->isAdministrator(), 403);

        if ($request->session()->has('impersonator_user_id')) {
            return redirect()->route('impersonation.index')->with('error', 'Akhiri mode uji user sebelum masuk sebagai user lain.');
        }

        $target = User::query()->findOrFail($userId);
        if ($target->is($admin)) {
            return redirect()->route('impersonation.index')->with('error', 'Tidak perlu impersonate akun yang sedang aktif.');
        }

        if ($target->isAdministrator()) {
            return redirect()->route('impersonation.index')->with('error', 'Impersonate akun administrator lain tidak diizinkan.');
        }

        $request->session()->put('impersonator_user_id', $admin->id);
        $request->session()->put('impersonator_user_name', $admin->name);
        $request->session()->put('impersonated_user_id', $target->id);
        Auth::login($target);
        $request->session()->regenerate();

        if ($target->school_id) {
            $request->session()->put('active_school_id', $target->school_id);
            $request->session()->forget(['active_fiscal_year_id', 'active_fund_source_id']);

            return redirect()->route('years.select')->with('success', 'Mode uji aktif sebagai '.$target->name.'. Pilih tahun anggaran untuk sekolah user ini.');
        }

        $request->session()->forget(['active_school_id', 'active_fiscal_year_id', 'active_fund_source_id']);

        return redirect()->route('schools.select')->with('success', 'Mode uji aktif sebagai '.$target->name.'. Pilih sekolah terlebih dahulu.');
    }

    public function stop(Request $request): RedirectResponse
    {
        $adminId = $request->session()->get('impersonator_user_id');
        if (! $adminId) {
            return redirect()->route('dashboard')->with('error', 'Mode uji user tidak sedang aktif.');
        }

        $admin = User::query()->findOrFail($adminId);
        Auth::login($admin);
        $request->session()->forget([
            'impersonator_user_id',
            'impersonator_user_name',
            'impersonated_user_id',
            'active_school_id',
            'active_fiscal_year_id',
            'active_fund_source_id',
        ]);
        $request->session()->regenerate();

        return redirect()->route('impersonation.index')->with('success', 'Sudah kembali sebagai administrator.');
    }
}
