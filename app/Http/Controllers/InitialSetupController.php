<?php

namespace App\Http\Controllers;

use App\Models\FiscalYear;
use App\Models\School;
use App\Models\User;
use App\Services\SchoolDatabaseManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InitialSetupController extends Controller
{
    public function create(): View
    {
        abort_if(User::query()->exists(), 404);

        return view('setup');
    }

    public function store(Request $request, SchoolDatabaseManager $databases): RedirectResponse
    {
        abort_if(User::query()->exists(), 404);
        $data = $request->validate(
            ['npsn' => ['required', 'string', 'max:16', 'unique:schools,npsn'],
                'school_code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:schools,school_code'],
                'school_name' => ['required', 'string', 'max:255'], 'year' => ['required', 'integer', 'between:2020,2100'], 'admin_name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'unique:users,email'], 'password' => ['required', 'confirmed', 'min:12'],
            ]);
        $result = DB::transaction(function () use ($data, $databases) {
            $school = School::create(['npsn' => $data['npsn'], 'school_code' => $data['school_code'], 'name' => $data['school_name']]);
            $databases->provision($school);
            $year = FiscalYear::create(['year' => $data['year'], 'fund_source' => 'BOSP', 'is_active' => true]);
            $user = User::create(['school_id' => $school->id, 'role' => 'ADMIN', 'name' => $data['admin_name'], 'email' => $data['email'], 'password' => $data['password']]);

            return compact('school', 'year', 'user');
        });
        Auth::login($result['user']);
        $request->session()->regenerate();
        $request->session()->put('active_school_id', $result['school']->id);

        return redirect()->route('years.select');
    }
}
