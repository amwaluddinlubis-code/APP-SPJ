<?php

namespace App\Http\Controllers;

use App\Models\ArkasSource;
use App\Models\FiscalYear;
use App\Models\School;
use App\Services\ArkasFullSynchronizationService;
use App\Services\SchoolDatabaseManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class YearSelectionController extends Controller
{
    public function create(Request $request, SchoolDatabaseManager $databases): View
    {
        abort_unless($request->user()->isAdministrator() || $request->user()->school_id === (int) session('active_school_id'), 403);

        $school = School::query()->findOrFail(session('active_school_id'));
        $databases->activate($school);
        $hasFundSourceContext = Schema::connection('school')->hasTable('fund_sources')
            && Schema::connection('school')->hasColumn('fiscal_years', 'fund_source_id');
        if (! $hasFundSourceContext) {
            $databases->migrate($school);
            $hasFundSourceContext = Schema::connection('school')->hasTable('fund_sources')
                && Schema::connection('school')->hasColumn('fiscal_years', 'fund_source_id');
        }
        $years = FiscalYear::query()
            ->when($hasFundSourceContext, function ($query): void {
                $query->with('fundSource')
                    ->whereNotNull('fund_source_id')
                    ->where('is_active', true);
            })
            ->when(! $hasFundSourceContext, fn ($query) => $query->whereRaw('1 = 0'))
            ->orderByDesc('year')
            ->orderBy('fund_source')
            ->get();
        if (session('active_fiscal_year_id') && ! $years->contains('id', (int) session('active_fiscal_year_id'))) {
            $request->session()->forget(['active_fiscal_year_id', 'active_fund_source_id']);
        }

        return view('years.select', [
            'years' => $years,
            'hasFundSourceContext' => $hasFundSourceContext,
            'school' => $school,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdministrator() || $request->user()->school_id === (int) session('active_school_id'), 403);
        $data = $request->validate([
            'fiscal_year_id' => ['required', 'integer'],
            'return_to' => ['nullable', 'string', 'max:1000'],
        ]);
        $year = FiscalYear::query()->findOrFail($data['fiscal_year_id']);
        if (! Schema::connection('school')->hasColumn('fiscal_years', 'fund_source_id')) {
            return redirect()->route('years.select')->with('error', 'Database sekolah belum dimigrasikan untuk sumber dana. Jalankan migrasi database terlebih dahulu.');
        }
        abort_unless($year->fund_source_id, 422, 'Tahun anggaran belum memiliki sumber dana.');
        $request->session()->put([
            'active_fiscal_year_id' => $year->id,
            'active_fund_source_id' => $year->fund_source_id,
        ]);

        if (filled($data['return_to'] ?? null) && str_starts_with($data['return_to'], url('/'))) {
            return redirect()->to($data['return_to']);
        }

        return redirect()->route('dashboard');
    }

    public function synchronize(Request $request, SchoolDatabaseManager $databases, ArkasFullSynchronizationService $synchronizer): RedirectResponse
    {
        abort_unless($request->user()->isAdministrator() || $request->user()->school_id === (int) session('active_school_id'), 403);

        try {
            $request->validate(['confirm_sync' => ['accepted']]);
            $school = School::findOrFail(session('active_school_id'));
            $source = ArkasSource::where('school_id', $school->id)->first();

            if (! $source) {
                return redirect()
                    ->route('years.select')
                    ->with('error', 'Sumber ARKAS untuk '.$school->name.' belum disimpan. Atur pengaturan ARKAS terlebih dahulu.');
            }

            $databases->activate($school);

            // Ensure database has required tables
            if (! Schema::connection('school')->hasTable('fund_sources')
                || ! Schema::connection('school')->hasColumn('fiscal_years', 'fund_source_id')) {
                $databases->migrate($school);
            }

            // Tenant baru belum memiliki fiscal_years. Temukan tahun dan sumber
            // dana langsung dari ARKAS sebelum menjalankan sinkronisasi penuh.
            $years = $synchronizer->bootstrapFiscalYears($source);

            if ($years->isEmpty()) {
                return redirect()
                    ->route('years.select')
                    ->with('error', 'Tidak ada tahun anggaran aktif. Hubungi administrator untuk menambahkan tahun anggaran.');
            }

            return redirect()
                ->route('years.select')
                ->with('success', 'Daftar tahun dan sumber dana berhasil diimpor dari ARKAS. Pilih salah satu konteks, lalu jalankan Sinkron Semua ARKAS dari dashboard.');
        } catch (\Throwable $exception) {
            Log::error('ARKAS synchronization from year selection failed.', [
                'user_id' => $request->user()?->id,
                'school_id' => session('active_school_id'),
                'exception' => $exception,
            ]);

            return redirect()
                ->route('years.select')
                ->with('error', 'Sinkronisasi ARKAS gagal: '.$this->safeErrorMessage($exception));
        }
    }

    private function safeErrorMessage(\Throwable $exception): string
    {
        $message = trim($exception->getMessage());
        $message = preg_replace('/(ARKAS_BRIDGE_PASSWORD|password)=\S+/i', '$1=[disembunyikan]', $message) ?: $message;

        return $message !== '' ? $message : 'detail error tidak tersedia';
    }
}
