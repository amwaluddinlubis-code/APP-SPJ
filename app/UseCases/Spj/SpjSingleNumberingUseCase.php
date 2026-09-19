<?php

namespace App\UseCases\Spj;

use App\Models\SpjPackage;
use App\Services\OperationalAuditService;
use App\Services\SpjDocumentNumberService;
use App\Services\SpjNumberingGateService;
use App\Services\SpjNumberingOrderService;
use App\Services\SpjNumberingPolicyService;
use App\Services\SpjPackageValidationService;
use App\Services\SpjV2NumberingAuthorizationService;
use App\Services\SpjV2NumberingIssuanceService;
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
        private readonly SpjV2NumberingAuthorizationService $numberingAuthorization,
        private readonly SpjV2NumberingIssuanceService $v2Numbering,
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
        if (! $package || ! $this->packageMatchesActivePath($package)) {
            return redirect()->route('spj.index', ['tab' => 'paket', 'package_id' => $packageId])->with('error', 'Paket dokumen tidak ditemukan pada konteks sekolah, tahun anggaran, dan sumber dana aktif.');
        }
        if ($this->v2Requested()) {
            return $this->respondToV2Issuance(
                $package,
                $this->v2Numbering->issue($package, 'SPJ', 'MAIN'),
                'Nomor SPJ',
            );
        }
        if ($package->status === 'READY') {
            $authorization = $this->numberingAuthorization->authorize($package, ['SPJ']);
            if (! $authorization['authorized']) {
                return back()->with('error', 'Penomoran ditolak oleh authorization boundary: '.$authorization['reason']);
            }
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
        if (! $package || ! $this->packageMatchesActivePath($package)) {
            return back()->with('error', 'Paket tidak ditemukan pada konteks sekolah, tahun anggaran, dan sumber dana aktif.');
        }

        $documentType = $this->numberingPolicy->canonicalAutomaticDocumentType($documentType);
        if ($documentType === null) {
            return back()->with('error', 'Penomoran ditolak. Jenis dokumen tidak termasuk domain penomoran canonical aplikasi.');
        }

        if ($this->v2Requested()) {
            $data = $request->validate([
                'scope_key' => ['nullable', 'string', 'max:80'],
            ]);
            $scopeKey = trim((string) ($data['scope_key'] ?? '')) ?: 'MAIN';

            return $this->respondToV2Issuance(
                $package,
                $this->v2Numbering->issue($package, $documentType, $scopeKey),
                $this->numberingPolicy->numberingDefinition($documentType)['label'] ?? $documentType,
            );
        }

        if ($package->status === 'READY') {
            $authorization = $this->numberingAuthorization->authorize($package, [$documentType]);
            if (! $authorization['authorized']) {
                return back()->with('error', 'Penomoran ditolak oleh authorization boundary: '.$authorization['reason']);
            }
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

    private function v2Requested(): bool
    {
        return config('spj.v2_read_path', 'legacy') === 'v2';
    }

    private function packageMatchesActivePath(SpjPackage $package): bool
    {
        if ($this->context->matchesTransaction($package->transaction)) {
            return true;
        }

        return $this->v2Requested()
            && $this->context->fundSourceId() !== null
            && (int) $package->transaction->fund_source_id === (int) $this->context->fundSourceId();
    }

    /** @param array<string,mixed> $result */
    private function respondToV2Issuance(SpjPackage $package, array $result, string $label): RedirectResponse
    {
        if (($result['status'] ?? null) === 'ISSUED') {
            $message = ($result['idempotent'] ?? false)
                ? $label.' sudah diterbitkan sebelumnya: '.($result['document_number'] ?? '-')
                : $label.' berhasil diterbitkan: '.($result['document_number'] ?? '-');

            return back()->with('success', $message);
        }

        return back()->with('error', $this->v2FailureMessage((string) ($result['error_code'] ?? ''), $label));
    }

    private function v2FailureMessage(string $errorCode, string $label): string
    {
        return match ($errorCode) {
            'PACKAGE_NOT_ELIGIBLE', 'AUTHORIZATION_BLOCKED', 'VALIDATION_BLOCKED', 'ORDER_BLOCKED' => $label.' ditolak karena paket belum memenuhi seluruh syarat penomoran.',
            'EFFECTIVE_PERIOD_CLOSED', 'EFFECTIVE_PERIOD_UNPROVEN', 'EFFECTIVE_CONTEXT_UNRESOLVED' => $label.' ditolak karena periode effective tidak terbuka atau tidak dapat dibuktikan.',
            'RESUME_DRIFT', 'SOURCE_MISSING', 'RECONCILIATION_REQUIRED' => $label.' ditolak karena transaksi memerlukan rekonsiliasi atau sumber tidak lagi aktif.',
            'FORMAT_MISSING', 'DOCUMENT_TYPE_INVALID', 'SCOPE_INVALID' => $label.' ditolak karena registry dokumen effective belum lengkap.',
            'ISSUANCE_COLLISION' => $label.' belum selesai karena terjadi konflik nomor. Silakan periksa ulang dan coba lagi.',
            default => $label.' gagal diterbitkan. Tidak ada perubahan yang disimpan.',
        };
    }
}
