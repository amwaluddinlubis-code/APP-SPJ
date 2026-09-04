<?php

namespace App\UseCases\Spj;

use App\Models\DocumentTemplate;
use App\Models\FiscalPeriodClosure;
use App\Models\QuarterNumberingRun;
use App\Models\School;
use App\Models\SpjDocument;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Services\FiscalPeriodWorkflowService;
use App\Services\OperationalAuditService;
use App\Services\SpjDocumentLifecycleService;
use App\Services\SpjDocumentNumberService;
use App\Services\SpjPackageValidationService;
use App\Services\TransactionSettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SpjNumberingUseCase
{
    public function assignNumber(string $packageId): RedirectResponse
    {
        $numbers = app(SpjDocumentNumberService::class);
        $validator = app(SpjPackageValidationService::class);
        $package = SpjPackage::query()->with(['transaction.items', 'transaction.goods', 'transaction.honors'])->find($packageId);
        if (! $package || $package->transaction->fiscal_year_id !== (int) session('active_fiscal_year_id')) {
            return redirect()->route('spj.index', ['tab' => 'paket', 'package_id' => $packageId])->with('error', 'Paket dokumen tidak ditemukan pada tahun anggaran aktif.');
        }
        if ($package->document_number && in_array($package->status, ['NUMBERED', 'FINAL'], true)) {
            return back()->with('success', 'Nomor dokumen SPJ sudah ditetapkan.');
        }
        if ($issues = $validator->validate($package)) {
            return back()->with('error', 'Penomoran ditolak. '.collect($issues)->pluck('message')->implode(' '));
        }
        if ($package->status === 'CANCELLED') {
            $package->forceFill(['document_number' => null, 'numbered_at' => null])->save();
        }

        $school = School::query()->findOrFail(session('active_school_id'));
        $result = $numbers->assignAutomaticNumbers($package, $school->school_code ?: $school->npsn, $school->npsn);
        $package->refresh();
        app(OperationalAuditService::class)->record($package->transaction->fiscal_year_id, 'SPJ_PACKAGE', $package->id, 'TETAPKAN_NOMOR', 'Nomor SPJ '.$package->document_number.' ditetapkan.');

        return back()->with('success', "Penomoran otomatis selesai: {$result['created']} nomor baru; {$result['skipped']} nomor yang sudah ada dilewati.");
    }

    public function markReady(string $packageId): RedirectResponse
    {
        $validator = app(SpjPackageValidationService::class);
        $package = SpjPackage::query()->with(['transaction.items', 'transaction.goods', 'transaction.honors'])->find($packageId);
        if (! $package || $package->transaction->fiscal_year_id !== (int) session('active_fiscal_year_id')) {
            return back()->with('error', 'Paket tidak ditemukan pada tahun anggaran aktif.');
        }
        if ($package->status !== 'DRAFT') {
            return back()->with('error', 'Hanya paket DRAFT yang dapat ditandai siap.');
        }
        if ($issues = $validator->validate($package)) {
            return back()->with('error', 'Paket belum siap: '.collect($issues)->pluck('message')->implode(' '));
        }

        $package->forceFill(['status' => 'READY'])->save();
        app(OperationalAuditService::class)->record($package->transaction->fiscal_year_id, 'SPJ_PACKAGE', $package->id, 'PAKET_READY', 'Paket dinyatakan siap untuk penomoran triwulan.');

        return back()->with('success', 'Paket siap dan akan masuk penomoran triwulan.');
    }

    public function assignQuarterNumbers(Request $request): RedirectResponse
    {
        $validator = app(SpjPackageValidationService::class);
        $numbers = app(SpjDocumentNumberService::class);
        $periods = app(FiscalPeriodWorkflowService::class);
        $data = $request->validate([
            'quarter' => ['required', 'integer', 'between:1,4'],
            'document_types' => ['nullable', 'array'],
            'document_types.*' => ['string', 'max:40'],
        ]);
        $yearId = (int) session('active_fiscal_year_id');
        $school = School::query()->findOrFail(session('active_school_id'));
        $templates = DocumentTemplate::query()->where(['fiscal_year_id' => $yearId, 'is_active' => true])->get();
        $documentTypes = collect($data['document_types'] ?? $templates->pluck('document_type')->push('SPJ'))
            ->map(fn ($type) => strtoupper(trim((string) $type)))->filter()->unique()->values();
        $quarterScope = fn ($query) => $query->activeContext()
            ->whereMonth('transaction_date', '>=', ((int) $data['quarter'] - 1) * 3 + 1)
            ->whereMonth('transaction_date', '<=', (int) $data['quarter'] * 3);
        $notReady = Transaction::query()->where($quarterScope)->has('items')
            ->where(function ($query): void {
                $query->doesntHave('spjPackage')
                    ->orWhereHas('spjPackage', function ($package): void {
                        $package->where('status', 'DRAFT')
                            ->whereDoesntHave('documents', fn ($document) => $document
                                ->where('document_type', 'SPJ')
                                ->where('scope_key', 'MAIN')
                                ->where('status', 'CANCELLED'));
                    });
            })->count();
        if ($notReady > 0) {
            return back()->with('error', "Penomoran dibatalkan: masih ada {$notReady} transaksi triwulan ini yang belum berstatus READY.");
        }

        $period = $periods->period($yearId, (int) $data['quarter']);
        if ($period->status === 'CLOSED') {
            return back()->with('error', 'Triwulan sudah ditutup. Administrator harus membuka kembali periode terlebih dahulu.');
        }
        $run = QuarterNumberingRun::query()->create([
            'fiscal_period_closure_id' => $period->id,
            'fiscal_year_id' => $yearId,
            'quarter' => $data['quarter'],
            'status' => 'RUNNING',
            'document_types' => $documentTypes->all(),
            'started_by' => auth()->id(),
            'started_at' => now(),
        ]);

        $packages = SpjPackage::query()
            ->with(['transaction.items', 'transaction.goods', 'transaction.workOrder', 'transaction.honors', 'transaction.travels'])
            ->where(function ($query): void {
                $query->whereIn('status', ['READY', 'NUMBERED'])
                    ->orWhere(function ($cancelled): void {
                        $cancelled->whereIn('status', ['DRAFT', 'CANCELLED'])
                            ->whereHas('documents', fn ($document) => $document
                                ->where('document_type', 'SPJ')
                                ->where('scope_key', 'MAIN')
                                ->where('status', 'CANCELLED'));
                    });
            })
            ->whereHas('transaction', $quarterScope)
            ->get();

        $invalidPackages = $packages->map(function (SpjPackage $package) use ($validator): ?array {
            $issues = $validator->validate($package);
            return $issues ? ['package' => $package, 'issues' => $issues] : null;
        })->filter();
        if ($invalidPackages->isNotEmpty()) {
            $proofNumbers = $invalidPackages->map(fn (array $entry) => $entry['package']->transaction->no_bukti)->implode(', ');
            $run->update([
                'status' => 'FAILED',
                'failed_count' => $invalidPackages->count(),
                'error_message' => 'Paket tidak konsisten: '.$proofNumbers,
                'completed_at' => now(),
            ]);
            return back()->with('error', 'Penomoran dibatalkan. Perbaiki paket tidak konsisten: '.$proofNumbers.'.');
        }

        $numbered = 0;
        $skipped = 0;
        try {
            foreach ($documentTypes as $documentType) {
                $orderedPackages = $packages->filter(function (SpjPackage $package) use ($documentType, $templates, $validator): bool {
                    if ($validator->validate($package)) {
                        return false;
                    }
                    if ($documentType === 'SPJ') {
                        return true;
                    }
                    $category = strtoupper((string) $package->transaction->spj_category);
                    return $templates->where('document_type', $documentType)->contains(fn (DocumentTemplate $template) => empty($template->applicable_categories) || in_array('SEMUA', $template->applicable_categories, true) || in_array($category, $template->applicable_categories, true));
                })->sortBy(fn (SpjPackage $package) => $this->documentEventDate($package, $documentType)->format('Y-m-d').'-'.str_pad((string) $package->id, 12, '0', STR_PAD_LEFT));

                foreach ($orderedPackages as $package) {
                    $templateId = $templates->where('document_type', $documentType)->first()?->id;
                    $before = $package->documents()->where(['document_type' => $documentType, 'scope_key' => 'MAIN'])->where('status', '!=', 'CANCELLED')->whereNotNull('document_number')->exists();
                    $numbers->assign($package, $documentType, $this->documentEventDate($package, $documentType), $school->school_code ?: $school->npsn, templateId: $templateId, npsn: $school->npsn);
                    $before ? $skipped++ : $numbered++;
                }
            }
            foreach ($packages as $package) {
                $automaticResult = $numbers->assignAutomaticNumbers($package, $school->school_code ?: $school->npsn, $school->npsn);
                $numbered += $automaticResult['created'];
                $skipped += $automaticResult['skipped'];
            }
            $run->update(['status' => 'COMPLETED', 'numbered_count' => $numbered, 'skipped_count' => $skipped, 'completed_at' => now()]);
            $periods->markNumbered($period, (int) auth()->id());
        } catch (\Throwable $exception) {
            $run->update(['status' => 'FAILED', 'numbered_count' => $numbered, 'skipped_count' => $skipped, 'failed_count' => 1, 'error_message' => $exception->getMessage(), 'completed_at' => now()]);
            return back()->with('error', 'Proses penomoran terhenti dan dapat dilanjutkan: '.$exception->getMessage());
        }

        app(OperationalAuditService::class)->record($yearId, 'SPJ_QUARTER', (int) $data['quarter'], 'PENOMORAN_BATCH', "Penomoran triwulan {$data['quarter']}: {$numbered} nomor baru, {$skipped} sudah bernomor.");
        return back()->with('success', "Penomoran triwulan selesai: {$numbered} nomor baru; {$skipped} dokumen dilewati karena sudah bernomor.");
    }

    public function assignDocumentNumber(Request $request, string $packageId, string $documentType): RedirectResponse
    {
        $numbers = app(SpjDocumentNumberService::class);
        $validator = app(SpjPackageValidationService::class);
        $package = SpjPackage::query()->with(['transaction.items', 'transaction.goods', 'transaction.honors'])->find($packageId);
        if (! $package || $package->transaction->fiscal_year_id !== (int) session('active_fiscal_year_id')) {
            return back()->with('error', 'Paket tidak ditemukan pada tahun anggaran aktif.');
        }
        if ($issues = $validator->validate($package)) {
            return back()->with('error', 'Penomoran ditolak. '.collect($issues)->pluck('message')->implode(' '));
        }
        $data = $request->validate([
            'document_date' => ['required', 'date'],
            'event_date' => ['nullable', 'date'],
            'scope_key' => ['nullable', 'string', 'max:80'],
        ]);
        $school = School::query()->findOrFail(session('active_school_id'));
        $document = $numbers->assign(
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
        app(OperationalAuditService::class)->record($package->transaction->fiscal_year_id, 'SPJ_DOCUMENT', $document->id, 'TETAPKAN_NOMOR', 'Nomor '.$document->document_type.' '.$document->document_number.' ditetapkan.');
        return back()->with('success', 'Nomor '.$document->document_type.' berhasil dibuat: '.$document->document_number);
    }

    public function finalizeDocument(string $documentId): RedirectResponse
    {
        $document = SpjDocument::query()->with('package.transaction')->findOrFail($documentId);
        abort_unless($document->package->transaction->fiscal_year_id === (int) session('active_fiscal_year_id'), 404);
        app(SpjDocumentLifecycleService::class)->finalize($document, (int) auth()->id());
        return back()->with('success', 'Dokumen difinalkan dan snapshot dikunci.');
    }

    public function cancelDocument(Request $request, string $documentId): RedirectResponse
    {
        abort_unless(auth()->user()->isAdministrator(), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $document = SpjDocument::query()->with('package.transaction')->findOrFail($documentId);
        abort_unless($document->package->transaction->fiscal_year_id === (int) session('active_fiscal_year_id'), 404);
        $oldNumber = $document->document_number;
        app(SpjDocumentLifecycleService::class)->cancel($document, (int) auth()->id(), $data['reason']);
        app(OperationalAuditService::class)->record($document->package->transaction->fiscal_year_id, 'SPJ_DOCUMENT', $document->id, 'BATALKAN_NOMOR', 'Nomor '.$oldNumber.' dibatalkan. Alasan: '.$data['reason']);
        return back()->with('warning', 'Nomor '.$oldNumber.' dibatalkan dan slotnya tersedia untuk dialokasikan kembali. Buka paket untuk memperbaiki input.');
    }

    public function replaceDocument(Request $request, string $documentId): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $old = SpjDocument::query()->with('package.transaction')->findOrFail($documentId);
        abort_unless($old->package->transaction->fiscal_year_id === (int) session('active_fiscal_year_id'), 404);
        $lifecycle = app(SpjDocumentLifecycleService::class);
        $numbers = app(SpjDocumentNumberService::class);
        $lifecycle->cancel($old, (int) auth()->id(), $data['reason']);
        $school = School::query()->findOrFail(session('active_school_id'));
        $replacement = $numbers->assign($old->package, $old->document_type, now(), $school->school_code ?: $school->npsn, 'REPLACEMENT:'.$old->id.':'.now()->format('YmdHis'), $old->document_template_id, $school->npsn);
        $replacement->forceFill(['replaces_document_id' => $old->id, 'is_late_entry' => true])->save();
        return back()->with('success', 'Dokumen lama dibatalkan dan dokumen pengganti mendapat nomor '.$replacement->document_number.'.');
    }

    public function closeQuarter(Request $request): RedirectResponse
    {
        $data = $request->validate(['quarter' => ['required', 'integer', 'between:1,4']]);
        $periods = app(FiscalPeriodWorkflowService::class);
        $period = $periods->period((int) session('active_fiscal_year_id'), (int) $data['quarter']);
        $periods->close($period, (int) session('active_fund_source_id'), (int) auth()->id());
        return back()->with('success', 'Triwulan '.$data['quarter'].' berhasil ditutup.');
    }

    public function reopenQuarter(Request $request, string $periodId): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $period = FiscalPeriodClosure::query()->where('fiscal_year_id', session('active_fiscal_year_id'))->findOrFail($periodId);
        app(FiscalPeriodWorkflowService::class)->reopen($period, (int) auth()->id(), $data['reason']);
        return back()->with('success', 'Triwulan dibuka kembali dan alasan telah dicatat.');
    }

    public function storePayment(Request $request, string $transactionId): RedirectResponse
    {
        $transaction = Transaction::query()->activeContext()->with('spjPackage')->findOrFail($transactionId);
        if ($transaction->spjPackage && ! $transaction->spjPackage->isEditable()) {
            return back()->with('error', 'Pembayaran tidak dapat diubah karena paket SPJ sudah dikunci. Batalkan nomor dan buka paket untuk koreksi terlebih dahulu.');
        }
        $data = $request->validate([
            'payment_date' => ['required', 'date'], 'gross_amount' => ['required', 'numeric', 'gt:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'], 'payment_method' => ['nullable', 'string', 'max:40'],
            'payment_reference' => ['nullable', 'string', 'max:160'],
        ]);
        app(TransactionSettlementService::class)->addPayment($transaction, $data);
        return back()->with('success', 'Tahap pembayaran berhasil ditambahkan.');
    }

    public function storeGoodsReceipt(Request $request, string $transactionId): RedirectResponse
    {
        $transaction = Transaction::query()->activeContext()->with('spjPackage')->findOrFail($transactionId);
        if ($transaction->spjPackage && ! $transaction->spjPackage->isEditable()) {
            return back()->with('error', 'Penerimaan barang tidak dapat diubah karena paket SPJ sudah dikunci. Batalkan nomor dan buka paket untuk koreksi terlebih dahulu.');
        }
        $data = $request->validate([
            'receipt_date' => ['required', 'date'], 'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'], 'items.*.transaction_item_id' => ['required', 'integer'],
            'items.*.quantity_received' => ['required', 'numeric', 'gt:0'], 'items.*.amount_received' => ['nullable', 'numeric', 'min:0'],
        ]);
        $items = $data['items'];
        unset($data['items']);
        app(TransactionSettlementService::class)->addGoodsReceipt($transaction, $data, $items);
        return back()->with('success', 'Tahap penerimaan barang berhasil ditambahkan.');
    }

    public function unlockPackage(Request $request, string $packageId): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $package = SpjPackage::query()->with('transaction')->findOrFail($packageId);
        abort_unless($package->transaction->fiscal_year_id === (int) session('active_fiscal_year_id'), 404);
        app(SpjDocumentLifecycleService::class)->unlock($package, (int) auth()->id(), $data['reason']);
        return back()->with('success', 'Paket dibuka kembali. Alasan pembukaan telah dicatat.');
    }

    public function documentEventDate(SpjPackage $package, string $documentType): Carbon
    {
        $transaction = $package->transaction;
        $date = match ($documentType) {
            'ORDER', 'PESANAN', 'SURAT_PESANAN' => $transaction->goods->pluck('order_date')->filter()->sort()->first(),
            'BAP' => $transaction->goods->pluck('bap_date')->filter()->sort()->first(),
            'BAST', 'RECEIPT', 'PENERIMAAN' => $transaction->goods->pluck('bast_date')->filter()->sort()->first(),
            'SPK', 'WORK_ORDER' => $transaction->workOrder?->spk_date,
            'RAB' => $transaction->workOrder?->rab_date,
            'SURAT_TUGAS_PERJALANAN_DINAS' => $transaction->travels->pluck('assignment_letter_date')->filter()->sort()->first()
                ?: $transaction->travels->pluck('departure_date')->filter()->sort()->first(),
            'SPPD' => $transaction->travels->pluck('departure_date')->filter()->sort()->first(),
            default => $transaction->transaction_date,
        };
        return Carbon::parse($date ?? now());
    }
}
