<?php

namespace App\UseCases\Spj;

use App\Models\SpjPackage;
use App\Services\OperationalAuditService;
use App\Services\SpjDocumentLifecycleService;
use App\Services\SpjPackageValidationService;
use App\Support\ActiveSpjContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SpjPackageLifecycleUseCase
{
    public function __construct(
        private readonly SpjPackageValidationService $validator,
        private readonly SpjDocumentLifecycleService $lifecycle,
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
            return back()->with('error', 'Paket tidak ditemukan pada tahun anggaran aktif.');
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

    public function unlockPackage(Request $request, string $packageId): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $package = SpjPackage::query()->with('transaction')->findOrFail($packageId);
        abort_unless($this->context->matchesTransaction($package->transaction), 404);
        $this->lifecycle->unlock($package, $this->context->actorId(), $data['reason']);

        return back()->with('success', 'Paket dibuka kembali. Alasan pembukaan telah dicatat.');
    }
}
