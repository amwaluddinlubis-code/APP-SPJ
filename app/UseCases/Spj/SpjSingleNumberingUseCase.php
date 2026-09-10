<?php

namespace App\UseCases\Spj;

use App\Models\SpjPackage;
use App\Services\OperationalAuditService;
use App\Services\SpjDocumentNumberService;
use App\Services\SpjNumberingOrderService;
use App\Services\SpjNumberingPolicyService;
use App\Services\SpjPackageValidationService;
use App\Support\ActiveSpjContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SpjSingleNumberingUseCase
{
    public function __construct(
        private readonly SpjDocumentNumberService $numbers,
        private readonly SpjPackageValidationService $validator,
        private readonly SpjNumberingOrderService $order,
        private readonly SpjNumberingPolicyService $numberingPolicy,
        private readonly OperationalAuditService $audit,
        private readonly ActiveSpjContext $context,
    ) {}

    public function assignNumber(string $packageId): RedirectResponse
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
            'documents',
        ])->find($packageId);
        if (! $package || ! $this->context->matchesFiscalYear($package->transaction)) {
            return redirect()->route('spj.index', ['tab' => 'paket', 'package_id' => $packageId])->with('error', 'Paket dokumen tidak ditemukan pada tahun anggaran aktif.');
        }
        if ($package->document_number && in_array($package->status, ['NUMBERED', 'FINAL'], true)) {
            return back()->with('success', 'Nomor dokumen SPJ sudah ditetapkan.');
        }
        if ($issues = $this->validator->validate($package)) {
            return back()->with('error', 'Penomoran ditolak. '.collect($issues)->pluck('message')->implode(' '));
        }
        if ($blocker = $this->order->singleNumberingBlocker($package, ['SPJ'])) {
            return back()->with('error', $blocker);
        }
        if ($package->status === 'CANCELLED') {
            $package->forceFill(['document_number' => null, 'numbered_at' => null])->save();
        }

        $school = $this->context->school();
        $result = $this->numbers->assignAutomaticNumbers($package, $school->school_code ?: $school->npsn, $school->npsn, ['SPJ']);
        $package->refresh();
        $this->audit->record($package->transaction->fiscal_year_id, 'SPJ_PACKAGE', $package->id, 'TETAPKAN_NOMOR', 'Nomor SPJ '.$package->document_number.' ditetapkan.');

        return back()->with('success', "Penomoran SPJ selesai: {$result['created']} nomor baru; {$result['skipped']} nomor yang sudah ada dilewati.");
    }

    public function assignDocumentNumber(Request $request, string $packageId, string $documentType): RedirectResponse
    {
        $package = SpjPackage::query()->with([
            'documents',
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
        if (! $package || ! $this->context->matchesFiscalYear($package->transaction)) {
            return back()->with('error', 'Paket tidak ditemukan pada tahun anggaran aktif.');
        }
        if ($issues = $this->validator->validate($package)) {
            return back()->with('error', 'Penomoran ditolak. '.collect($issues)->pluck('message')->implode(' '));
        }
        $data = $request->validate([
            'document_date' => ['required', 'date'],
            'event_date' => ['nullable', 'date'],
            'scope_key' => ['nullable', 'string', 'max:80'],
        ]);
        $documentType = strtoupper(trim($documentType));
        if ($this->numberingPolicy->isAutomaticDocumentType($documentType)
            && ! $this->numberingPolicy->isAutomaticDocumentEligible($package->transaction, $documentType)) {
            $category = $this->numberingPolicy->canonicalCategory((string) $package->transaction->spj_category) ?: '-';

            return back()->with('error', 'Penomoran '.$documentType.' tidak berlaku untuk kategori '.$category.'.');
        }
        if ($blocker = $this->order->singleNumberingBlocker($package, [$documentType])) {
            return back()->with('error', $blocker);
        }

        $school = $this->context->school();
        $document = $this->numbers->assign(
            $package,
            $documentType,
            Carbon::parse($data['document_date']),
            $school->school_code ?: $school->npsn,
            $data['scope_key'] ?? 'MAIN',
            npsn: $school->npsn,
        );
        if (filled($data['event_date'] ?? null)) {
            $document->forceFill(['event_date' => $data['event_date']])->save();
        }
        $this->audit->record($package->transaction->fiscal_year_id, 'SPJ_DOCUMENT', $document->id, 'TETAPKAN_NOMOR', 'Nomor '.$document->document_type.' '.$document->document_number.' ditetapkan.');

        return back()->with('success', 'Nomor '.$document->document_type.' berhasil dibuat: '.$document->document_number);
    }
}
