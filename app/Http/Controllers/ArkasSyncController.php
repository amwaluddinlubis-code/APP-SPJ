<?php

namespace App\Http\Controllers;

use App\Jobs\SynchronizeArkas;
use App\Models\ArkasSource;
use App\Models\BackgroundOperation;
use App\Models\FiscalYear;
use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ArkasSyncController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        try {
            $request->validate(['confirm_sync' => ['accepted']]);
            $school = School::findOrFail(session('active_school_id'));
            $year = FiscalYear::findOrFail(session('active_fiscal_year_id'));
            $source = ArkasSource::where('school_id', $school->id)->first();
            if (! $source) {
                return redirect()
                    ->route('arkas.settings')
                    ->with('error', 'Sumber ARKAS untuk '.$school->name.' belum disimpan. Isi path database dan kata sandi terlebih dahulu.');
            }
            $operation = BackgroundOperation::query()->create([
                'school_id' => $school->id,
                'fiscal_year_id' => $year->id,
                'requested_by' => $request->user()?->id,
                'type' => 'ARKAS_SYNC',
                'status' => 'QUEUED',
                'message' => 'Menunggu worker antrean.',
            ]);
            SynchronizeArkas::dispatch($operation->id, $school->id, $year->id, $source->id)->onQueue('operations');

            return back()->with('success', 'Sinkronisasi ARKAS masuk antrean. Proses tetap berjalan di latar belakang. ID proses: '.$operation->id.'.');
        } catch (\Throwable $exception) {
            Log::error('ARKAS synchronization failed.', [
                'user_id' => $request->user()?->id,
                'school_id' => session('active_school_id'),
                'fiscal_year_id' => session('active_fiscal_year_id'),
                'exception' => $exception,
            ]);

            return back()->with('error', 'Sinkronisasi ARKAS gagal: '.$this->safeErrorMessage($exception));
        }
    }

    private function safeErrorMessage(\Throwable $exception): string
    {
        $message = trim($exception->getMessage());
        $message = preg_replace('/(ARKAS_BRIDGE_PASSWORD|password)=\S+/i', '$1=[disembunyikan]', $message) ?: $message;

        return $message !== '' ? $message : 'detail error tidak tersedia';
    }
}
