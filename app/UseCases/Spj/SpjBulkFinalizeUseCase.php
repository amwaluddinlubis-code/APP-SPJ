<?php

namespace App\UseCases\Spj;

use App\Models\SpjPackage;
use App\Services\OperationalAuditService;
use App\Services\SpjDocumentLifecycleService;
use App\Support\ActiveSpjContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class SpjBulkFinalizeUseCase
{
    public function __construct(
        private readonly SpjDocumentLifecycleService $lifecycle,
        private readonly OperationalAuditService $audit,
        private readonly ActiveSpjContext $context,
    ) {}

    public function handle(Request $request): RedirectResponse
    {
        $data = $request->validate(['quarter' => ['required', 'integer', 'between:1,4']]);
        $quarter = (int) $data['quarter'];

        try {
            $finalized = DB::connection('school')->transaction(function () use ($quarter): int {
                $packages = SpjPackage::query()
                    ->with('transaction')
                    ->where('status', 'NUMBERED')
                    ->whereHas('transaction', fn ($query) => $query
                        ->forSpjContext($this->context)
                        ->whereMonth('transaction_date', '>=', (($quarter - 1) * 3) + 1)
                        ->whereMonth('transaction_date', '<=', $quarter * 3))
                    ->orderBy('id')
                    ->get();

                foreach ($packages as $package) {
                    $this->lifecycle->finalizePackage($package, $this->context->actorId());
                }

                $count = $packages->count();
                $this->audit->record(
                    $this->context->fiscalYearId(),
                    'SPJ_QUARTER',
                    $quarter,
                    'FINALISASI_BATCH',
                    "Bulk finalisasi triwulan {$quarter}: {$count} paket difinalkan.",
                );

                return $count;
            });
        } catch (Throwable $exception) {
            return back()->with('error', 'Bulk finalisasi dibatalkan. Tidak ada paket yang difinalkan: '.$exception->getMessage());
        }

        if ($finalized === 0) {
            return back()->with('warning', "Tidak ada paket NUMBERED yang dapat difinalkan pada triwulan {$quarter}.");
        }

        return back()->with('success', "Bulk finalisasi triwulan {$quarter} berhasil: {$finalized} paket difinalkan.");
    }
}
