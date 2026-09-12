<?php

namespace App\UseCases\Spj;

use App\Models\SpjDocument;
use App\Services\OperationalAuditService;
use App\Services\SpjDocumentLifecycleService;
use App\Services\SpjDocumentNumberService;
use App\Services\SpjNumberingGateService;
use App\Services\SpjNumberingPolicyService;
use App\Support\ActiveSpjContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SpjDocumentLifecycleUseCase
{
    public function __construct(
        private readonly SpjDocumentLifecycleService $lifecycle,
        private readonly SpjDocumentNumberService $numbers,
        private readonly SpjNumberingPolicyService $numberingPolicy,
        private readonly SpjNumberingGateService $numberingGate,
        private readonly OperationalAuditService $audit,
        private readonly ActiveSpjContext $context,
    ) {}

    public function finalizeDocument(string $documentId): RedirectResponse
    {
        $document = SpjDocument::query()->with('package.transaction')->findOrFail($documentId);
        abort_unless($this->context->matchesTransaction($document->package->transaction), 404);
        $package = $this->lifecycle->finalizePackage($document->package, $this->context->actorId());
        $this->audit->record(
            $package->transaction->fiscal_year_id,
            'SPJ_PACKAGE',
            $package->id,
            'FINALISASI_PAKET',
            'Seluruh dokumen aktif paket difinalkan secara atomik dan snapshot dikunci.',
        );

        return back()->with('success', 'Paket SPJ difinalkan. Seluruh dokumen aktif dan snapshot paket telah dikunci.');
    }

    public function cancelDocument(Request $request, string $documentId): RedirectResponse
    {
        abort_unless($this->context->isAdministrator(), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $document = SpjDocument::query()->with('package.transaction')->findOrFail($documentId);
        abort_unless($this->context->matchesTransaction($document->package->transaction), 404);
        $oldNumber = $document->document_number;
        $this->lifecycle->cancel($document, $this->context->actorId(), $data['reason']);
        $this->audit->record($document->package->transaction->fiscal_year_id, 'SPJ_DOCUMENT', $document->id, 'BATALKAN_NOMOR', 'Nomor '.$oldNumber.' dibatalkan. Alasan: '.$data['reason']);

        return back()->with('warning', 'Nomor '.$oldNumber.' dibatalkan dan tetap disimpan sebagai histori. Nomor tersebut tidak akan digunakan kembali.');
    }

    public function replaceDocument(Request $request, string $documentId): RedirectResponse
    {
        abort_unless($this->context->isAdministrator(), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $old = SpjDocument::query()
            ->with(['package.transaction.goods', 'package.transaction.workOrder', 'package.transaction.travels'])
            ->findOrFail($documentId);
        abort_unless($this->context->matchesTransaction($old->package->transaction), 404);

        $documentType = $this->numberingPolicy->canonicalAutomaticDocumentType($old->document_type);
        if ($documentType === null) {
            return back()->with('error', 'Dokumen legacy/noncanonical tidak dapat diterbitkan ulang melalui workflow penomoran canonical.');
        }
        if ($blocker = $this->numberingGate->periodBlocker($old->package)) {
            return back()->with('error', $blocker);
        }

        $oldNumber = $old->document_number;
        $scopeKey = $old->scope_key ?: 'MAIN';
        $documentDate = $old->document_date ?: now();
        $templateId = $old->document_template_id;

        $this->lifecycle->cancel($old, $this->context->actorId(), $data['reason']);
        $package = $old->package->fresh(['transaction.goods', 'transaction.workOrder', 'transaction.travels']);
        $school = $this->context->school();
        $replacement = $this->numbers->assign(
            $package,
            $documentType,
            $documentDate,
            $school->school_code ?: $school->npsn,
            $scopeKey,
            $templateId,
            $school->npsn,
        );
        $replacement->forceFill([
            'replaces_document_id' => $old->id,
            'is_late_entry' => true,
        ])->save();
        $this->syncDerivedNumber($replacement);

        $this->audit->record(
            $package->transaction->fiscal_year_id,
            'SPJ_DOCUMENT',
            $replacement->id,
            'GANTI_DOKUMEN',
            'Nomor '.$oldNumber.' dibatalkan dan diganti dengan '.$replacement->document_number.'. Alasan: '.$data['reason'],
        );

        return back()->with('success', 'Dokumen lama dibatalkan dan dokumen pengganti mendapat nomor '.$replacement->document_number.'. Paket kembali NUMBERED sampai difinalkan ulang.');
    }

    private function syncDerivedNumber(SpjDocument $document): void
    {
        $definition = $this->numberingPolicy->numberingDefinition($document->document_type);
        if (! $definition) {
            return;
        }

        $package = $document->package()->with(['transaction.goods', 'transaction.workOrder', 'transaction.travels'])->firstOrFail();
        $transaction = $package->transaction;
        $number = $document->document_number;
        $target = $definition['number_target'];
        $field = $target['field'];
        if (! $field) {
            return;
        }

        match ($target['relation']) {
            'package' => $package->forceFill([$field => $number])->save(),
            'goods' => $transaction->goods()->whereNull($field)->update([$field => $number]),
            'workOrder' => $transaction->workOrder?->forceFill([$field => $number])->save(),
            'travels' => $this->syncScopedNumber($transaction, $document->scope_key, $field, $number),
            default => null,
        };
    }

    private function syncScopedNumber($transaction, string $scopeKey, string $field, string $number): void
    {
        if (! str_starts_with($scopeKey, 'TRAVEL-')) {
            return;
        }

        $travelId = (int) substr($scopeKey, strlen('TRAVEL-'));
        if ($travelId > 0) {
            $transaction->travels()->whereKey($travelId)->update([$field => $number]);
        }
    }
}
