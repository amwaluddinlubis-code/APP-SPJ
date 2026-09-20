<?php

namespace App\Http\Controllers;

use App\Jobs\SynchronizeArkasRawMirror;
use App\Models\ArkasSource;
use App\Models\BackgroundOperation;
use App\Models\FiscalYear;
use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

final class ArkasRawMirrorController implements HasMiddleware
{
    /** @return array<int, string> */
    public static function middleware(): array
    {
        return ['active-school', 'active-year', 'administrator'];
    }

    public function __invoke(Request $request): RedirectResponse
    {
        $school = School::query()->findOrFail(session('active_school_id'));
        $year = FiscalYear::query()->findOrFail(session('active_fiscal_year_id'));
        $source = ArkasSource::query()->where('school_id', $school->id)->firstOrFail();
        $operation = BackgroundOperation::query()->create([
            'school_id' => $school->id,
            'fiscal_year_id' => $year->id,
            'requested_by' => $request->user()?->id,
            'type' => 'ARKAS_RAW_MIRROR',
            'status' => 'QUEUED',
            'progress' => 0,
            'message' => 'Raw mirror ARKAS masuk antrean.',
        ]);

        SynchronizeArkasRawMirror::dispatch($operation->id, $school->id, $year->id, (int) $year->fund_source_id, $source->id)
            ->onConnection('database')
            ->onQueue('operations');

        return redirect()->route('arkas.settings')->with('success', 'Sinkronisasi seluruh database ARKAS masuk antrean.');
    }
}
