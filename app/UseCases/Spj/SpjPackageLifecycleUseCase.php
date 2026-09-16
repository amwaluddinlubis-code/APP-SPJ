<?php

namespace App\UseCases\Spj;

use App\Models\SpjPackage;
use App\Services\OperationalAuditService;
use App\Services\SpjPackageValidationService;
use App\Support\ActiveSpjContext;
use Illuminate\Http\RedirectResponse;

class SpjPackageLifecycleUseCase
{
    public function __construct(
        private readonly SpjPackageValidationService $validator,
        private readonly OperationalAuditService $audit,
        private readonly ActiveSpjContext $context,
    ) {}

    public function markReady(string $packageId): RedirectResponse
    {
        $result = $this->markReadyResult($packageId);

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    /** @return array{success: bool, message: string} */
    public function markReadyResult(string $packageId): array
    {
        $package = $this->findPackage($packageId);
        if (! $package || ! $this->context->matchesTransaction($package->transaction)) {
            return ['success' => false, 'message' => 'Paket tidak ditemukan pada konteks sekolah, tahun anggaran, dan sumber dana aktif.'];
        }
        if ($package->status !== 'DRAFT') {
            return ['success' => false, 'message' => 'Hanya paket DRAFT yang dapat ditandai siap.'];
        }
        if ($issues = $this->validator->validate($package)) {
            return ['success' => false, 'message' => 'Paket belum siap: '.collect($issues)->pluck('message')->implode(' ')];
        }

        $package->forceFill(['status' => 'READY'])->save();
        $this->audit->record($package->transaction->fiscal_year_id, 'SPJ_PACKAGE', $package->id, 'PAKET_READY', 'Paket dinyatakan siap untuk penomoran triwulan.');

        return ['success' => true, 'message' => 'Paket siap dan masuk antrean penomoran.'];
    }

    public function canMarkReady(string $packageId): bool
    {
        $package = $this->findPackage($packageId);

        return $package !== null
            && $this->context->matchesTransaction($package->transaction)
            && $package->status === 'DRAFT'
            && $this->validator->validate($package) === [];
    }

    private function findPackage(string $packageId): ?SpjPackage
    {
        return SpjPackage::query()->with([
            'transaction.items',
            'transaction.goods',
            'transaction.goodsReceipts',
            'transaction.workOrder',
            'transaction.honors',
            'transaction.travels',
            'transaction.payments',
            'transaction.workers',
            'transaction.participants',
            'transaction.serviceRecipients',
            'transaction.spjPackage',
        ])->find($packageId);
    }
}
