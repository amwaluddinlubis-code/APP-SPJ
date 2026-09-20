<?php

namespace App\UseCases\Spj;

use App\Models\SpjPackage;
use App\Services\OperationalAuditService;
use App\Services\SpjDocumentLifecycleService;
use App\Services\SpjReadPathSelector;
use App\Services\SpjV2BulkFinalizationService;
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
        private readonly SpjReadPathSelector $readPaths,
        private readonly SpjV2BulkFinalizationService $v2BulkFinalization,
    ) {}

    public function handle(Request $request): RedirectResponse
    {
        $data = $request->validate(['quarter' => ['required', 'integer', 'between:1,4']]);
        $quarter = (int) $data['quarter'];

        $selection = $this->readPaths->select(
            DB::connection('school'),
            $this->context->fiscalYearId(),
            (int) ($this->context->fundSourceId() ?? 0),
        );
        if ($selection['requested'] === SpjReadPathSelector::V2) {
            $result = $this->v2BulkFinalization->finalize($quarter);
            if ($result['status'] === 'FINALIZED') {
                return back()->with('success', $result['reason']);
            }
            if ($result['status'] === 'NOOP') {
                return back()->with('warning', $result['reason']);
            }

            return back()->with('error', $result['reason']);
        }

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
