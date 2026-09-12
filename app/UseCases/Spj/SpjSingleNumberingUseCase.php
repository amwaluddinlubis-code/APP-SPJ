<?php

namespace App\UseCases\Spj;

use App\Models\SpjPackage;
use App\Services\OperationalAuditService;
use App\Services\SpjDocumentNumberService;
use App\Services\SpjNumberingGateService;
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
        private readonly SpjNumberingGateService $numberingGate,
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
        if (! $package || ! $this->context->matchesTransaction($package->transaction)) {
            return redirect()->route('spj.index', ['tab' => 'paket', 'package_id' => $packageId])->with('error', 'Paket dokumen tidak ditemukan pada konteks sekolah, tahun anggaran, dan sumber dana aktif.');
        }
        if ($package->document_number && in_array($package->status, ['NUMBERED', 'FINAL'], true)) {
            return back()->with('success', 'Nomor dokumen SPJ sudah ditetapkan.');
        }
        if ($blocker = $this->numberingGate->issuanceBlocker($package, 'SPJ')) {
            return back()->with('error', $blocker);
        }
        if ($issues = $this->validator->validateForNumbering($package)) {
            return back()->with('error', 'Penomoran ditolak. '.collect($issues)->pluck('message')->implode(' '));
        }
        if ($blocker = $this->order->singleNumberingBlocker($package, ['SPJ'])) {
            return back()->with('error', $blocker);
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
        if (! $package || ! $this->context->matchesTransaction($package->transaction)) {
            return back()->with('error', 'Paket tidak ditemukan pada konteks sekolah, tahun anggaran, dan sumber dana aktif.');
        }

        $documentType = $this->numberingPolicy->canonicalAutomaticDocumentType($documentType);
        if ($documentType === null) {
            return back()->with('error', 'Penomoran ditolak. Jenis dokumen tidak termasuk '.count($this->numberingPolicy->automaticDocumentTypes()).' domain penomoran canonical aplikasi.');
        }
        $definition = $this->numberingPolicy->numberingDefinition($documentType);
        if (! $definition) {
            return back()->with('error', 'Metadata penomoran canonical tidak ditemukan.');
        }
        if ($blocker = $this->numberingGate->issuanceBlocker($package, $documentType)) {
            return back()->with('error', $blocker);
        }
        if ($issues = $this->validator->validateForNumbering($package)) {
            return back()->with('error', 'Penomoran ditolak. '.collect($issues)->pluck('message')->implode(' '));
        }

        $data = $request->validate([
            'document_date' => ['required', 'date'],
            'event_date' => ['nullable', 'date'],
            'scope_key' => ['nullable', 'string', 'max:80'],
        ]);
        $scopeKey = $data['scope_key'] ?? 'MAIN';
        if ($definition['scope_rule'] === 'MAIN' && $scopeKey !== 'MAIN') {
            return back()->with('error', 'Penomoran '.$definition['label'].' hanya menggunakan scope MAIN sesuai registry canonical.');
        }
        if ($definition['scope_rule'] === 'TRAVEL' && ! preg_match('/^TRAVEL-\d+$/', $scopeKey)) {
            return back()->with('error', 'Penomoran '.$definition['label'].' memerlukan scope perjalanan yang valid.');
        }
        if (blank($this->numberingPolicy->documentEventDateValue($package->transaction, $documentType, $scopeKey))) {
            return back()->with('error', 'Tanggal peristiwa '.$definition['label'].' belum tersedia sesuai aturan event date registry canonical.');
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
            $scopeKey,
            npsn: $school->npsn,
        );
        if (filled($data['event_date'] ?? null)) {
            $document->forceFill(['event_date' => $data['event_date']])->save();
        }
        $this->audit->record($package->transaction->fiscal_year_id, 'SPJ_DOCUMENT', $document->id, 'TETAPKAN_NOMOR', 'Nomor '.$document->document_type.' '.$document->document_number.' ditetapkan.');

        return back()->with('success', 'Nomor '.$definition['label'].' berhasil dibuat: '.$document->document_number);
    }
}
