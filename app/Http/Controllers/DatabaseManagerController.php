<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Services\OperationalAuditService;
use App\Services\SchoolDatabaseManager;
use App\Services\SchoolDatabaseResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class DatabaseManagerController extends Controller
{
    public function index(Request $request, SchoolDatabaseManager $manager): View
    {
        $active = $manager->activeInfo();
        $list = $manager->listAll();
        $activeStatus = null;
        $tables = [];
        $table = $request->query('table');
        $schema = null;
        $tableData = null;
        $tableError = null;

        if ($active['school']) {
            $activeStatus = $manager->status($active['school']);
            try {
                $tables = $manager->listTables($active['school']);
            } catch (\Throwable $e) {
                Log::error('Database table listing failed.', ['exception' => $e]);
                $tableError = 'Tabel database tidak dapat dibaca.';
            }
            if ($table) {
                try {
                    $schema = $manager->tableSchema($active['school'], $table);
                    $perPageRaw = $request->input('perPage', 15);
                    $perPage = $perPageRaw === 'all' ? 10000 : (int) $perPageRaw;
                    $perPage = in_array($perPage, [15, 25, 50, 100, 10000]) ? $perPage : 15;
                    $tableData = $manager->tableData($active['school'], $table, $perPage);
                } catch (\Throwable $e) {
                    Log::error('Database table inspection failed.', ['table' => $table, 'exception' => $e]);
                    $tableError = 'Data tabel tidak dapat dibaca.';
                }
            }
        }

        return view('database-manager.index', compact('active', 'list', 'activeStatus', 'tables', 'table', 'schema', 'tableData', 'tableError'));
    }

    public function resetForm(SchoolDatabaseManager $manager): View
    {
        $active = $manager->activeInfo();
        $activeStatus = $active['school'] ? $manager->status($active['school']) : null;

        return view('database-manager.reset', compact('active', 'activeStatus'));
    }

    public function activate(string $schoolId, Request $request, SchoolDatabaseManager $manager, OperationalAuditService $audit): RedirectResponse
    {
        $school = School::findOrFail($schoolId);
        $manager->activate($school);
        session(['active_school_id' => $school->id]);
        session()->forget(['active_fiscal_year_id', 'active_fund_source_id']);
        $audit->record(session('active_fiscal_year_id'), 'SCHOOL_DATABASE', $school->id, 'AKTIFKAN', 'Database sekolah diaktifkan.');

        return back()->with('success', 'Database aktif diganti ke '.$school->name.' ('.$school->npsn.')');
    }

    public function migrate(string $schoolId, SchoolDatabaseManager $manager, OperationalAuditService $audit): RedirectResponse
    {
        $school = School::findOrFail($schoolId);
        try {
            $out = $manager->migrate($school);
            $audit->record(session('active_fiscal_year_id'), 'SCHOOL_DATABASE', $school->id, 'MIGRASI', 'Migrasi database sekolah dijalankan.');

            return back()->with('success', 'Migrasi berhasil untuk '.$school->name.'. '.str($out)->limit(200));
        } catch (\Throwable $e) {
            Log::error('School database migration failed.', ['school_id' => $school->id, 'exception' => $e]);

            return back()->with('error', 'Migrasi database gagal. Periksa log aplikasi.');
        }
    }

    public function checkpoint(string $schoolId, SchoolDatabaseManager $manager, OperationalAuditService $audit): RedirectResponse
    {
        $school = School::findOrFail($schoolId);
        try {
            $manager->checkpoint($school);
            $audit->record(session('active_fiscal_year_id'), 'SCHOOL_DATABASE', $school->id, 'CHECKPOINT', 'WAL checkpoint database dijalankan.');

            return back()->with('success', 'WAL checkpoint berhasil untuk '.$school->name);
        } catch (\Throwable $e) {
            Log::error('School database checkpoint failed.', ['school_id' => $school->id, 'exception' => $e]);

            return back()->with('error', 'Checkpoint database gagal. Periksa log aplikasi.');
        }
    }

    public function vacuum(string $schoolId, SchoolDatabaseManager $manager, OperationalAuditService $audit): RedirectResponse
    {
        $school = School::findOrFail($schoolId);
        try {
            $manager->vacuum($school);
            $audit->record(session('active_fiscal_year_id'), 'SCHOOL_DATABASE', $school->id, 'VACUUM', 'VACUUM database dijalankan.');

            return back()->with('success', 'VACUUM berhasil untuk '.$school->name);
        } catch (\Throwable $e) {
            Log::error('School database vacuum failed.', ['school_id' => $school->id, 'exception' => $e]);

            return back()->with('error', 'VACUUM database gagal. Periksa log aplikasi.');
        }
    }

    public function integrity(string $schoolId, SchoolDatabaseManager $manager, OperationalAuditService $audit): RedirectResponse
    {
        $school = School::findOrFail($schoolId);
        try {
            $result = $manager->integrityCheck($school);
            $audit->record(session('active_fiscal_year_id'), 'SCHOOL_DATABASE', $school->id, 'INTEGRITY_CHECK', 'Pemeriksaan integritas database dijalankan.');
            $msg = strtolower(trim($result)) === 'ok' ? 'Integrity OK untuk '.$school->name : 'Integrity database bermasalah. Periksa log dan backup.';
            $type = strtolower(trim($result)) === 'ok' ? 'success' : 'error';

            return back()->with($type, $msg);
        } catch (\Throwable $e) {
            Log::error('School database integrity check failed.', ['school_id' => $school->id, 'exception' => $e]);

            return back()->with('error', 'Pemeriksaan integritas database gagal. Periksa log aplikasi.');
        }
    }

    public function provision(string $schoolId, SchoolDatabaseManager $manager, OperationalAuditService $audit): RedirectResponse
    {
        $school = School::findOrFail($schoolId);
        try {
            $manager->provision($school);
            $audit->record(session('active_fiscal_year_id'), 'SCHOOL_DATABASE', $school->id, 'PROVISION', 'Provision database sekolah dijalankan.');

            return back()->with('success', 'Database provisioned ulang untuk '.$school->name);
        } catch (\Throwable $e) {
            Log::error('School database provisioning failed.', ['school_id' => $school->id, 'exception' => $e]);

            return back()->with('error', 'Provision database gagal. Periksa log aplikasi.');
        }
    }

    public function reset(string $schoolId, Request $request, SchoolDatabaseResetService $resetter): RedirectResponse
    {
        $school = School::findOrFail($schoolId);
        $data = $request->validate([
            'confirmation' => ['required', 'string', 'max:80'],
        ]);
        $expectedConfirmation = 'RESET '.$school->npsn;

        if (trim((string) $data['confirmation']) !== $expectedConfirmation) {
            return back()->withErrors([
                'confirmation' => 'Konfirmasi tidak sesuai. Ketik tepat: '.$expectedConfirmation,
            ])->withInput();
        }

        try {
            $resetter->reset($school);
            if ((int) session('active_school_id') === (int) $school->id) {
                session()->forget(['active_fiscal_year_id', 'active_fund_source_id']);
            }

            Log::warning('School database reset completed.', [
                'school_id' => $school->id,
                'npsn' => $school->npsn,
                'user_id' => auth()->id(),
            ]);

            return redirect()->route('database-manager.reset-form')
                ->with('success', 'Database '.$school->name.' berhasil direset total. Semua data tenant, sequence, dan auto-increment telah dimulai ulang.');
        } catch (\Throwable $e) {
            Log::error('School database reset failed.', [
                'school_id' => $school->id,
                'user_id' => auth()->id(),
                'exception' => $e,
            ]);

            return back()->with('error', 'Reset database gagal. Database tidak boleh digunakan sampai statusnya diperiksa kembali.');
        }
    }
}
