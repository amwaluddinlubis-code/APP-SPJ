<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\SchoolBackup;
use App\Services\SchoolDatabaseManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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

    public function store(Request $request, SchoolDatabaseManager $databases): RedirectResponse
    {
        $this->ensureAdministrator();
        $school = School::query()->findOrFail(session('active_school_id'));
        try {
            $backup = $this->makeBackup($school, $databases, 'MANUAL', $request->user()->id);
        } catch (\Throwable $exception) {
            Log::error('Manual school backup failed.', ['school_id' => $school->id, 'exception' => $exception]);

            return back()->with('error', 'Backup database gagal. Periksa log aplikasi.');
        }

        return back()->with('success', 'Backup database sekolah berhasil dibuat: '.$backup->file_name);
    }

    public function restore(string $backupId, Request $request, SchoolDatabaseManager $databases): RedirectResponse
    {
        $this->ensureAdministrator();
        $request->validate(['confirm_restore' => ['accepted']]);
        $school = School::query()->findOrFail(session('active_school_id'));
        $backup = SchoolBackup::query()->where(['id' => $backupId, 'school_id' => $school->id])->firstOrFail();
        $source = storage_path('app/'.$backup->file_path);
        $target = $school->databaseRecord?->database_path;
        if (! $target || ! File::exists($source)) {
            return back()->with('error', 'Berkas backup tidak ditemukan atau database sekolah belum tersedia.');
        }

        try {
            $this->makeBackup($school, $databases, 'SEBELUM_PEMULIHAN', $request->user()->id);
            DB::purge('school');
            $temporaryTarget = $target.'.restore.tmp';
            File::copy($source, $temporaryTarget);
            if (File::size($temporaryTarget) !== File::size($source)) {
                File::delete($temporaryTarget);
                throw new \RuntimeException('Berkas pemulihan tidak konsisten.');
            }
            File::move($temporaryTarget, $target, true);
            $databases->activate($school);
        } catch (\Throwable $exception) {
            Log::error('School database restore failed.', ['school_id' => $school->id, 'backup_id' => $backup->id, 'exception' => $exception]);

            return back()->with('error', 'Pemulihan database gagal. Periksa log aplikasi.');
        }

        return back()->with('success', 'Database sekolah berhasil dipulihkan. Backup kondisi sebelum pemulihan dibuat otomatis.');
    }

    private function makeBackup(School $school, SchoolDatabaseManager $databases, string $reason, int $userId): SchoolBackup
    {
        $databases->activate($school);
        $source = $school->databaseRecord?->database_path;
        if (! $source || ! File::exists($source)) {
            throw new \RuntimeException('Database lokal sekolah tidak ditemukan.');
        }
        DB::connection('school')->statement('PRAGMA wal_checkpoint(TRUNCATE)');
        $folder = storage_path('app/school-backups/'.$school->npsn);
        File::ensureDirectoryExists($folder);
        $name = 'spj-'.$school->npsn.'-'.now()->format('Ymd-His').'-'.strtolower($reason).'.sqlite';
        $target = $folder.'/'.$name;
        $temporaryTarget = $target.'.tmp';
        File::copy($source, $temporaryTarget);
        if (File::size($temporaryTarget) !== File::size($source)) {
            File::delete($temporaryTarget);
            throw new \RuntimeException('Ukuran backup tidak konsisten.');
        }
        File::move($temporaryTarget, $target);

        $backup = SchoolBackup::create([
            'school_id' => $school->id,
            'file_path' => 'school-backups/'.$school->npsn.'/'.$name,
            'file_name' => $name,
            'file_size' => File::size($target),
            'reason' => $reason,
            'created_by' => $userId,
        ]);

        $retention = max(1, (int) config('spj.backup_retention', 30));
        $expired = SchoolBackup::query()
            ->where('school_id', $school->id)
            ->latest('created_at')
            ->skip($retention)
            ->get();
        foreach ($expired as $oldBackup) {
            File::delete(storage_path('app/'.$oldBackup->file_path));
            $oldBackup->delete();
        }

        return $backup;
    }

    private function ensureAdministrator(): void
    {
        abort_unless(request()->user()?->isAdministrator(), 403);
    }
}
