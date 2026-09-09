<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\SchoolBackup;
use App\Services\SchoolBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class SchoolBackupController extends Controller
{
    public function index(): View
    {
        $this->ensureAdministrator();
        $school = School::query()->findOrFail(session('active_school_id'));

        return view('school-backups.index', [
            'school' => $school,
            'backups' => SchoolBackup::query()->where('school_id', $school->id)->latest()->limit(30)->get(),
        ]);
    }

    public function store(Request $request, SchoolBackupService $backups): RedirectResponse
    {
        $this->ensureAdministrator();
        $school = School::query()->findOrFail(session('active_school_id'));
        try {
            $backup = $backups->create($school, 'MANUAL', $request->user()->id);
        } catch (\Throwable $exception) {
            Log::error('Manual school backup failed.', ['school_id' => $school->id, 'exception' => $exception]);

            return back()->with('error', 'Backup database gagal. Periksa log aplikasi.');
        }

        return back()->with('success', 'Backup database sekolah berhasil dibuat: '.$backup->file_name);
    }

    public function restore(string $backupId, Request $request, SchoolBackupService $backups): RedirectResponse
    {
        $this->ensureAdministrator();
        $request->validate(['confirm_restore' => ['accepted']]);
        $school = School::query()->findOrFail(session('active_school_id'));
        $backup = SchoolBackup::query()->where(['id' => $backupId, 'school_id' => $school->id])->firstOrFail();

        try {
            $backups->restore($school, $backup, $request->user()->id);
        } catch (\Throwable $exception) {
            Log::error('School database restore failed.', ['school_id' => $school->id, 'backup_id' => $backup->id, 'exception' => $exception]);

            return back()->with('error', 'Pemulihan database gagal. Periksa log aplikasi.');
        }

        return back()->with('success', 'Database sekolah berhasil dipulihkan. Backup kondisi sebelum pemulihan dibuat otomatis.');
    }

    private function ensureAdministrator(): void
    {
        abort_unless(request()->user()?->isAdministrator(), 403);
    }
}
