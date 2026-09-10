<?php

namespace App\UseCases\Spj;

use App\Models\SpjPackage;
use App\Services\SpjNumberingOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Compatibility façade for callers that still resolve the historical
 * SpjNumberingUseCase. New behavior lives in focused use cases/services.
 */
class SpjNumberingUseCase
{
    public function __construct(
        private readonly SpjSingleNumberingUseCase $singleNumbering,
        private readonly SpjQuarterNumberingUseCase $quarterNumbering,
        private readonly SpjPackageLifecycleUseCase $packageLifecycle,
        private readonly SpjDocumentLifecycleUseCase $documentLifecycle,
        private readonly SpjFiscalPeriodUseCase $fiscalPeriod,
        private readonly SpjSettlementUseCase $settlement,
        private readonly SpjNumberingOrderService $order,
    ) {}

    public function assignNumber(string $packageId): RedirectResponse
    {
        return $this->singleNumbering->assignNumber($packageId);
    }

    public function markReady(string $packageId): RedirectResponse
    {
        return $this->packageLifecycle->markReady($packageId);
    }

    public function assignQuarterNumbers(Request $request): RedirectResponse
    {
        return $this->quarterNumbering->assignQuarterNumbers($request);
    }

    public function assignDocumentNumber(Request $request, string $packageId, string $documentType): RedirectResponse
    {
        return $this->singleNumbering->assignDocumentNumber($request, $packageId, $documentType);
    }

    public function finalizeDocument(string $documentId): RedirectResponse
    {
        return $this->documentLifecycle->finalizeDocument($documentId);
    }

    public function cancelDocument(Request $request, string $documentId): RedirectResponse
    {
        return $this->documentLifecycle->cancelDocument($request, $documentId);
    }

    public function replaceDocument(Request $request, string $documentId): RedirectResponse
    {
        return $this->documentLifecycle->replaceDocument($request, $documentId);
    }

    public function closeQuarter(Request $request): RedirectResponse
    {
        return $this->fiscalPeriod->closeQuarter($request);
    }

    public function reopenQuarter(Request $request, string $periodId): RedirectResponse
    {
        return $this->fiscalPeriod->reopenQuarter($request, $periodId);
    }

    public function storePayment(Request $request, string $transactionId): RedirectResponse
    {
        return $this->settlement->storePayment($request, $transactionId);
    }

    public function storeGoodsReceipt(Request $request, string $transactionId): RedirectResponse
    {
        return $this->settlement->storeGoodsReceipt($request, $transactionId);
    }

    public function unlockPackage(Request $request, string $packageId): RedirectResponse
    {
        return $this->packageLifecycle->unlockPackage($request, $packageId);
    }

    /**
     * @param  Collection<int, SpjPackage>  $packages
     * @return Collection<int, SpjPackage>
     */
    public function orderedPackagesForDocumentType(Collection $packages, string $documentType): Collection
    {
        return $this->order->orderedPackagesForDocumentType($packages, $documentType);
    }

    public function documentEventDate(SpjPackage $package, string $documentType): Carbon
    {
        return $this->order->documentEventDate($package, $documentType);
    }

    /**
     * Retained for backward-compatible tests/callers while the ordering policy
     * is now owned by SpjNumberingOrderService.
     *
     * @param  array<int, string>  $documentTypes
     */
    private function singleNumberingBlocker(SpjPackage $package, array $documentTypes): ?string
    {
        return $this->order->singleNumberingBlocker($package, $documentTypes);
    }
}
