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
        $package = SpjPackage::query()->with([
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
        if (! $package || ! $this->context->matchesTransaction($package->transaction)) {
            return back()->with('error', 'Paket tidak ditemukan pada konteks sekolah, tahun anggaran, dan sumber dana aktif.');
        }
        if ($package->status !== 'DRAFT') {
            return back()->with('error', 'Hanya paket DRAFT yang dapat ditandai siap.');
        }
        if ($issues = $this->validator->validate($package)) {
            return back()->with('error', 'Paket belum siap: '.collect($issues)->pluck('message')->implode(' '));
        }

        $package->forceFill(['status' => 'READY'])->save();
        $this->audit->record($package->transaction->fiscal_year_id, 'SPJ_PACKAGE', $package->id, 'PAKET_READY', 'Paket dinyatakan siap untuk penomoran triwulan.');

        return back()->with('success', 'Paket siap dan akan masuk penomoran triwulan.');
    }
}
