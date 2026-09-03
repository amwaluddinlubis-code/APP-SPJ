<?php

namespace App\Http\Controllers;

use App\Models\ArkasSource;
use App\Models\School;
use App\Services\ArkasBridgeClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class ArkasSourceController
{
    public function index(Request $request, ArkasBridgeClient $bridgeClient): View
    {
        $schools = School::query()->orderBy('name')->get();
        $sources = ArkasSource::query()->get()->keyBy('school_id');
        $selectedSchoolId = (int) old('school_id', $request->integer('school_id', session('active_school_id')));
        $selectedSource = $sources->get($selectedSchoolId);
        $resolvedBridge = $bridgeClient->resolveBridgeExecutable($selectedSource);

        $health = [
            'database' => $selectedSource ? File::exists($selectedSource->database_path) : false,
            'bridge' => File::exists($resolvedBridge),
            'password' => $selectedSource && filled($selectedSource->database_password),
        ];

        return view('arkas.settings', compact('schools', 'sources', 'selectedSchoolId', 'selectedSource', 'health', 'resolvedBridge'));
    }

    public function store(Request $request, ArkasBridgeClient $bridgeClient): RedirectResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'database_path' => ['required', 'string', 'max:1000'],
            'bridge_path' => ['nullable', 'string', 'max:1000'],
            'database_password' => ['nullable', 'string', 'max:1000'],
        ]);

        $source = ArkasSource::firstOrNew(['school_id' => $data['school_id']]);
        $source->database_path = trim($data['database_path']);
        $source->bridge_path = filled($data['bridge_path'] ?? null)
            ? trim($data['bridge_path'])
            : $bridgeClient->resolveBridgeExecutable();

        if (filled($data['database_password'])) {
            $source->database_password = $data['database_password'];
        }

        if (! $source->exists && blank($source->database_password)) {
            return back()->withInput()->withErrors(['database_password' => 'Kata sandi database ARKAS wajib diisi saat pertama kali disimpan.']);
        }

        $source->save();

        return redirect()->route('arkas.settings')->with('success', 'Sumber ARKAS tersimpan. Path dan kata sandi siap digunakan saat Sinkron Semua ARKAS.');
    }
}
