<?php

namespace App\Http\Controllers;

use App\Models\DapodikConnection;
use App\Services\DapodikSynchronizationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DapodikIntegrationController extends Controller
{
    public function index(): View
    {
        return view('dapodik.index', ['connection' => DapodikConnection::first()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['base_url' => ['required', 'url', 'max:255'], 'npsn' => ['required', 'string', 'max:20'], 'token' => ['nullable', 'string', 'max:500']]);
        $connection = DapodikConnection::first() ?? new DapodikConnection;
        if (! $connection->exists && blank($data['token'] ?? null)) {
            return back()->withErrors(['token' => 'Token wajib diisi.'])->withInput();
        }
        if (blank($data['token'] ?? null)) {
            unset($data['token']);
        }
        $connection->fill($data + ['is_active' => true])->save();

        return back()->with('success', 'Konfigurasi Dapodik disimpan secara terenkripsi.');
    }

    public function test(DapodikSynchronizationService $service): RedirectResponse
    {
        $connection = DapodikConnection::firstOrFail();
        try {
            $counts = $service->test($connection);

            return back()->with('success', 'Koneksi berhasil: '.collect($counts)->map(fn ($v, $k) => "{$k}={$v}")->join(', '));
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function sync(DapodikSynchronizationService $service): RedirectResponse
    {
        $connection = DapodikConnection::firstOrFail();
        try {
            $result = $service->synchronize($connection);

            return back()->with('success', "Sinkronisasi selesai: {$result['employees']} GTK dan {$result['students']} siswa.");
        } catch (\Throwable $e) {
            $connection->update(['last_status' => 'FAILED', 'last_message' => $e->getMessage()]);

            return back()->with('error', $e->getMessage());
        }
    }
}
