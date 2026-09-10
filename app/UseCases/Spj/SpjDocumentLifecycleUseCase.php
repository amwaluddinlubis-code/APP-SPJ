<?php

namespace App\UseCases\Spj;

use App\Models\SpjDocument;
use App\Services\OperationalAuditService;
use App\Services\SpjDocumentLifecycleService;
use App\Services\SpjDocumentNumberService;
use App\Support\ActiveSpjContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SpjDocumentLifecycleUseCase
{
    public function __construct(
        private readonly SpjDocumentLifecycleService $lifecycle,
        private readonly SpjDocumentNumberService $numbers,
        private readonly OperationalAuditService $audit,
        private readonly ActiveSpjContext $context,
    ) {}

    public function finalizeDocument(string $documentId): RedirectResponse
    {
        $document = SpjDocument::query()->with('package.transaction')->findOrFail($documentId);
        abort_unless($this->context->matchesFiscalYear($document->package->transaction), 404);
        $this->lifecycle->finalize($document, $this->context->actorId());

        return back()->with('success', 'Dokumen difinalkan dan snapshot dikunci.');
    }

    public function cancelDocument(Request $request, string $documentId): RedirectResponse
    {
        abort_unless($this->context->isAdministrator(), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $document = SpjDocument::query()->with('package.transaction')->findOrFail($documentId);
        abort_unless($this->context->matchesFiscalYear($document->package->transaction), 404);
        $oldNumber = $document->document_number;
        $this->lifecycle->cancel($document, $this->context->actorId(), $data['reason']);
        $this->audit->record($document->package->transaction->fiscal_year_id, 'SPJ_DOCUMENT', $document->id, 'BATALKAN_NOMOR', 'Nomor '.$oldNumber.' dibatalkan. Alasan: '.$data['reason']);

        return back()->with('warning', 'Nomor '.$oldNumber.' dibatalkan dan slotnya tersedia untuk dialokasikan kembali. Buka paket untuk memperbaiki input.');
    }

    public function replaceDocument(Request $request, string $documentId): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $old = SpjDocument::query()->with('package.transaction')->findOrFail($documentId);
        abort_unless($this->context->matchesFiscalYear($old->package->transaction), 404);
        $this->lifecycle->cancel($old, $this->context->actorId(), $data['reason']);
        $school = $this->context->school();
        $replacement = $this->numbers->assign($old->package, $old->document_type, now(), $school->school_code ?: $school->npsn, 'REPLACEMENT:'.$old->id.':'.now()->format('YmdHis'), $old->document_template_id, $school->npsn);
        $replacement->forceFill(['replaces_document_id' => $old->id, 'is_late_entry' => true])->save();

        return back()->with('success', 'Dokumen lama dibatalkan dan dokumen pengganti mendapat nomor '.$replacement->document_number.'.');
    }
}
