<?php

namespace App\Http\Controllers;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\School;
use App\Services\SchoolDatabaseManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SchoolConfigurationController extends Controller
{
    public function index(SchoolDatabaseManager $databases): View
    {
        $schools = School::query()->with('databaseRecord')->orderBy('name')->get();
        $activeSchool = School::query()->find(session('active_school_id'));
        $profile = null;
        $activeYear = null;
        $fundSources = collect();
        if ($activeSchool) {
            $databases->activate($activeSchool);
            $fundSources = FundSource::query()->where('is_hidden', false)->orderBy('name')->get();
            if (session('active_fiscal_year_id')) {
                $activeYear = FiscalYear::query()
                    ->whereKey(session('active_fiscal_year_id'))
                    ->whereNotNull('fund_source_id')
                    ->first();
                if ($activeYear) {
                    $profile = DB::connection('school')->table('school_profiles')->where('fiscal_year_id', $activeYear->id)->first();
                }
            }
        }

        return view('schools.settings', compact('schools', 'activeSchool', 'activeYear', 'profile', 'fundSources'));
    }

    public function updateProfile(Request $request, SchoolDatabaseManager $databases): RedirectResponse
    {
        $school = School::query()->findOrFail(session('active_school_id'));
        $data = $request->validate([
            'npsn' => ['required', 'string', 'max:16', 'unique:schools,npsn,'.$school->id],
            'school_code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:schools,school_code,'.$school->id],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'district' => ['nullable', 'string', 'max:120'],
            'regency' => ['nullable', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:120'],
            'letterhead' => ['nullable', 'image', 'max:5120'],
            'principal_name' => ['nullable', 'string', 'max:180'],
            'principal_nip' => ['nullable', 'string', 'max:40'],
            'principal_email' => ['nullable', 'email', 'max:180'],
            'principal_phone' => ['nullable', 'string', 'max:40'],
            'treasurer_name' => ['nullable', 'string', 'max:180'],
            'treasurer_nip' => ['nullable', 'string', 'max:40'],
            'treasurer_email' => ['nullable', 'email', 'max:180'],
            'treasurer_phone' => ['nullable', 'string', 'max:40'],
        ]);
        $schoolData = collect($data)->only(['npsn', 'school_code', 'name', 'address', 'district', 'regency', 'province'])->toArray();
        if ($request->hasFile('letterhead')) {
            if ($school->letterhead_path) {
                Storage::delete($school->letterhead_path);
            }
            $schoolData['letterhead_path'] = $request->file('letterhead')->store('letterheads');
        }
        $school->update($schoolData);
        $databases->activate($school);
        $year = FiscalYear::query()->find(session('active_fiscal_year_id'));
        if ($year) {
            DB::connection('school')->table('school_profiles')->updateOrInsert(
                ['fiscal_year_id' => $year->id],
                array_merge(collect($data)->only(['principal_name', 'principal_nip', 'principal_email', 'principal_phone', 'treasurer_name', 'treasurer_nip', 'treasurer_email', 'treasurer_phone'])->toArray(), ['updated_at' => now(), 'created_at' => now()])
            );
        }

        return back()->with('success', 'Profil sekolah dan penandatangan dokumen berhasil diperbarui.');
    }

    /** Menampilkan kop sekolah aktif dari disk privat melalui route terautentikasi. */
    public function letterhead()
    {
        $school = School::query()->findOrFail(session('active_school_id'));
        abort_if(blank($school->letterhead_path), 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($school->letterhead_path), 404);

        return response()->file($disk->path($school->letterhead_path), [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function storeSchool(Request $request, SchoolDatabaseManager $databases): RedirectResponse
    {
        $data = $request->validate(['npsn' => ['required', 'string', 'max:16', 'unique:schools,npsn'], 'school_code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:schools,school_code'], 'name' => ['required', 'string', 'max:255'], 'address' => ['nullable', 'string'], 'district' => ['nullable', 'string', 'max:120'], 'regency' => ['nullable', 'string', 'max:120'], 'province' => ['nullable', 'string', 'max:120']]);
        $school = School::create($data);
        $databases->provision($school);

        return back()->with('success', 'Profil dan database lokal sekolah berhasil ditambahkan.');
    }

    public function storeYear(Request $request, SchoolDatabaseManager $databases): RedirectResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'year' => ['required', 'integer', 'between:2020,2100'],
            'fund_source_id' => ['required', 'integer'],
        ]);
        $school = School::query()->findOrFail($data['school_id']);
        $databases->activate($school);
        $fundSource = FundSource::query()->whereKey($data['fund_source_id'])->where('is_hidden', false)->first();
        abort_unless($fundSource, 422, 'Sumber dana tidak ditemukan pada sekolah yang dipilih.');
        FiscalYear::firstOrCreate(
            ['year' => $data['year'], 'fund_source_id' => $fundSource->id],
            ['fund_source' => $fundSource->name, 'is_active' => true]
        );
        if ($activeSchoolId = session('active_school_id')) {
            $databases->activate((int) $activeSchoolId);
        }

        return back()->with('success', 'Tahun anggaran berhasil ditambahkan pada database sekolah.');
    }
}
