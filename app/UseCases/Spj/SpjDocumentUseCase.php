<?php

namespace App\UseCases\Spj;

use App\Models\DocumentTemplate;
use App\Models\SpjPackage;
use App\Services\SpjGeneratedDocumentValidator;
use App\Services\SpjMaintenanceDocumentContextService;
use App\Services\SpjPackageTemplateSelector;
use App\Services\SpjPackageValidationService;
use App\Services\SpjSpreadsheetPdfConverter;
use App\Services\SpjTemplateRenderPreflight;
use App\Services\SpjTemplateService;
use App\Services\SpjV2PackageReadContextService;
use App\Support\ActiveSpjContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SpjDocumentUseCase
{
    public function __construct(
        private readonly ActiveSpjContext $context,
        private readonly SpjPackageTemplateSelector $templateSelector,
        private readonly SpjV2PackageReadContextService $packageReadContext,
    ) {}

    public function download(string $packageId)
    {
        $package = SpjPackage::query()->with(['transaction.items', 'transaction.goods', 'transaction.workers', 'transaction.participants', 'transaction.travels'])->find($packageId);
        if (! $package || ! $this->packageReadContext->prepare($package)) {
            return redirect()->route('spj.index', ['tab' => 'paket', 'package_id' => $packageId])->with('error', 'Paket dokumen tidak ditemukan pada konteks tahun anggaran dan sumber dana aktif.');
        }
        $validator = app(SpjPackageValidationService::class);
        $templates = app(SpjTemplateService::class);
        $preflight = app(SpjTemplateRenderPreflight::class);
        $issues = $validator->validate($package);
        if ($issues) {
            return back()->with('error', 'PDF belum dapat dibuat. Lengkapi seluruh data wajib terlebih dahulu.');
        }

        $this->applyDocumentContext($package);
        $school = $this->context->school();
        $activeTemplates = $this->spreadsheetTemplatesForPackage($package);
        $preflight->assertAllRenderable($activeTemplates, $package, $school);
        $response = $templates->downloadPackagePdf($activeTemplates, $package, $school);

        return $this->assertPdfOutput($response, 'Paket SPJ PDF');
    }

    public function downloadPackageExcel(string $packageId)
    {
        $package = SpjPackage::query()->with(['transaction.items', 'transaction.goods', 'transaction.workers', 'transaction.participants', 'transaction.travels'])->find($packageId);
        if (! $package || ! $this->packageReadContext->prepare($package)) {
            return redirect()->route('spj.index', ['tab' => 'paket', 'package_id' => $packageId])->with('error', 'Paket dokumen tidak ditemukan pada konteks tahun anggaran dan sumber dana aktif.');
        }

        $this->applyDocumentContext($package);
        $school = $this->context->school();
        $activeTemplates = $this->spreadsheetTemplatesForPackage($package);
        app(SpjTemplateRenderPreflight::class)->assertAllRenderable($activeTemplates, $package, $school);
        $response = app(SpjTemplateService::class)->downloadPackageExcel($activeTemplates, $package, $school);

        return $this->assertBinaryOutput($response, 'xlsx', 'Paket SPJ Excel');
    }

    public function previewPackage(string $packageId): View|RedirectResponse
    {
        $package = SpjPackage::query()->with(['transaction.items', 'transaction.goods', 'transaction.workers', 'transaction.participants', 'transaction.travels'])->find($packageId);
        if (! $package || ! $this->packageReadContext->prepare($package)) {
            return redirect()->route('spj.index', ['tab' => 'paket', 'package_id' => $packageId])->with('error', 'Paket dokumen tidak ditemukan pada konteks tahun anggaran dan sumber dana aktif.');
        }

        $validationIssues = app(SpjPackageValidationService::class)->validate($package);
        $this->applyDocumentContext($package);
        $templates = $this->spreadsheetTemplatesForPackage($package);
        $school = $this->context->school();
        // Pratinjau adalah operasi baca ringan: jangan jalankan preflight
        // download (assertAllRenderable) di sini karena itu me-render ulang
        // seluruh workbook dan membuat halaman timeout/OOM. Validasi wajib
        // sudah dihitung di atas; kegagalan render ditangani di bawah.
        $template = new DocumentTemplate(['name' => 'Paket SPJ', 'format' => 'xlsx']);
        $previewPdfReady = $templates->contains(fn (DocumentTemplate $row): bool => strtolower((string) $row->format) === 'xlsx');

        try {
            // Bila PDF tersedia, HTML tidak perlu dihitung karena Blade
            // mengutamakan <embed> PDF. Ini menghindari 2x render berat.
            $previewHtml = $previewPdfReady
                ? null
                : app(SpjTemplateService::class)->packagePreviewHtml($templates, $package, $school);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('spj.index', ['tab' => 'paket', 'package_id' => $packageId])->with('error', 'Pratinjau paket gagal dibuat: '.$exception->getMessage());
        }

        return view('spj-documents.template-preview', [
            'package' => $package,
            'template' => $template,
            'previewHtml' => $previewHtml,
            'previewPdfUrl' => route('spj.preview-package-pdf', [$packageId]),
            'previewPdfReady' => $previewPdfReady,
            'validationIssues' => $validationIssues,
        ]);
    }

    public function downloadTemplate(string $packageId, string $templateId)
    {
        $package = SpjPackage::query()->with(['transaction.items', 'transaction.goods', 'transaction.workOrder', 'transaction.workers', 'transaction.participants', 'transaction.travels'])->find($packageId);
        $template = DocumentTemplate::query()->find($templateId);
        if (! $package || ! $template || ! $template->is_active || ! $this->packageReadContext->prepare($package) || $template->fiscal_year_id !== $this->context->fiscalYearId()) {
            return redirect()->route('spj.index', ['tab' => 'paket', 'package_id' => $packageId])->with('error', 'Paket atau template tidak ditemukan pada konteks tahun anggaran dan sumber dana aktif.');
        }
        $validator = app(SpjPackageValidationService::class);
        $templates = app(SpjTemplateService::class);
        if ($validator->validate($package)) {
            return back()->with('error', 'Dokumen dari template belum dapat dibuat. Lengkapi data wajib terlebih dahulu.');
        }

        $this->applyDocumentContext($package);
        $school = $this->context->school();
        $documentType = strtoupper($template->document_type);
        $document = $package->documents()
            ->where('document_type', $documentType)
            ->where('scope_key', 'MAIN')
            ->where('status', '!=', 'CANCELLED')
            ->whereNotNull('document_number')
            ->latest('id')
            ->first();
        if ($document) {
            $package->setAttribute('document_number', $document->document_number);
        }

        $response = $templates->download($template, $package, $school);

        return $this->assertBinaryOutput(
            $response,
            (string) $template->format,
            'Dokumen '.(string) $template->document_type,
            (string) $template->document_type,
        );
    }

    public function downloadTemplatePdf(string $packageId, string $templateId)
    {
        $package = SpjPackage::query()->with(['transaction.items', 'transaction.goods', 'transaction.workOrder', 'transaction.workers', 'transaction.participants', 'transaction.travels'])->find($packageId);
        $template = DocumentTemplate::query()->find($templateId);
        if (! $package || ! $template || ! $template->is_active || ! $this->packageReadContext->prepare($package) || $template->fiscal_year_id !== $this->context->fiscalYearId()) {
            return redirect()->route('spj.index', ['tab' => 'paket', 'package_id' => $packageId])->with('error', 'Paket atau template aktif tidak ditemukan pada konteks tahun anggaran dan sumber dana aktif.');
        }
        $validator = app(SpjPackageValidationService::class);
        $templates = app(SpjTemplateService::class);
        if ($validator->validate($package)) {
            return back()->with('error', 'PDF dari template belum dapat dibuat. Lengkapi data wajib terlebih dahulu.');
        }

        $this->applyDocumentContext($package);
        $school = $this->context->school();
        app(SpjTemplateRenderPreflight::class)->assertRenderable($template, $package, $school);
        $response = $templates->downloadPdf($template, $package, $school);

        return $this->assertPdfOutput($response, 'PDF '.(string) $template->document_type);
    }

    public function previewTemplate(string $packageId, string $templateId): View|RedirectResponse
    {
        $validator = app(SpjPackageValidationService::class);
        $templates = app(SpjTemplateService::class);
        $package = SpjPackage::query()->with(['transaction.items', 'transaction.goods', 'transaction.workers', 'transaction.participants', 'transaction.travels'])->find($packageId);
        $template = DocumentTemplate::query()->find($templateId);
        if (! $package || ! $template || ! $template->is_active || ! $this->packageReadContext->prepare($package) || $template->fiscal_year_id !== $this->context->fiscalYearId()) {
            return redirect()->route('spj.index', ['tab' => 'paket', 'package_id' => $packageId])->with('error', 'Paket atau template tidak ditemukan pada konteks tahun anggaran dan sumber dana aktif.');
        }
        $school = $this->context->school();
        $validationIssues = $validator->validate($package);
        $this->applyDocumentContext($package);
        // Jangan render PDF penuh hanya untuk cek readiness: itu mahal lalu
        // PDF di-render ulang oleh <embed>. Cek murah: xlsx selalu siap
        // (fallback Dompdf), docx butuh LibreOffice. HTML hanya dihitung
        // bila PDF tidak siap.
        $isXlsx = strtolower((string) $template->format) === 'xlsx';
        $previewPdfReady = $isXlsx || app(SpjSpreadsheetPdfConverter::class)->isAvailable();

        try {
            $previewHtml = $previewPdfReady ? null : $templates->previewHtml($template, $package, $school);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('spj.index', ['tab' => 'paket', 'package_id' => $packageId])->with('error', 'Pratinjau dokumen gagal dibuat: '.$exception->getMessage());
        }

        return view('spj-documents.template-preview', [
            'package' => $package,
            'template' => $template,
            'previewHtml' => $previewHtml,
            'previewPdfUrl' => route('spj.preview-template-pdf', [$packageId, $templateId]),
            'previewPdfReady' => $previewPdfReady,
            'validationIssues' => $validationIssues,
        ]);
    }

    /**
     * Inline PDF for print-worthy preview. Render-only: same filled input and
     * engine as the download path, never issues numbers or mutates lifecycle.
     */
    public function previewTemplatePdf(string $packageId, string $templateId): Response|RedirectResponse
    {
        $templates = app(SpjTemplateService::class);
        $package = SpjPackage::query()->with(['transaction.items', 'transaction.goods', 'transaction.workers', 'transaction.participants', 'transaction.travels'])->find($packageId);
        $template = DocumentTemplate::query()->find($templateId);
        if (! $package || ! $template || ! $template->is_active || ! $this->packageReadContext->prepare($package) || $template->fiscal_year_id !== $this->context->fiscalYearId()) {
            return redirect()->route('spj.index', ['tab' => 'paket', 'package_id' => $packageId])->with('error', 'Paket atau template tidak ditemukan pada konteks tahun anggaran dan sumber dana aktif.');
        }
        $school = $this->context->school();
        $this->applyDocumentContext($package);
        try {
            $contents = $templates->previewTemplatePdfBytes($template, $package, $school);
        } catch (Throwable $exception) {
            report($exception);

            return response('Pratinjau PDF gagal dibuat: '.$exception->getMessage(), 500);
        }
        if ($contents === null) {
            return redirect()->route('spj.preview-template', [$packageId, $templateId])->with('error', 'Pratinjau PDF membutuhkan LibreOffice di server. Unduh dokumen asli lalu cetak dari aplikasi Office.');
        }

        // Download/preview adalah operasi baca. Lifecycle Paket hanya boleh berubah
        // melalui workflow READY/NUMBERED/FINAL/CANCELLED yang eksplisit.

        return $this->inlinePdfResponse($contents, 'PRATINJAU-'.$template->document_type.'-'.$package->document_number.'.pdf');
    }

    /**
     * Combined package inline PDF for print-worthy preview. Render-only,
     * same filled input and engine as downloadPackagePdf.
     */
    public function previewPackagePdf(string $packageId): Response|RedirectResponse
    {
        $package = SpjPackage::query()->with(['transaction.items', 'transaction.goods', 'transaction.workers', 'transaction.participants', 'transaction.travels'])->find($packageId);
        if (! $package || ! $this->packageReadContext->prepare($package)) {
            return redirect()->route('spj.index', ['tab' => 'paket', 'package_id' => $packageId])->with('error', 'Paket dokumen tidak ditemukan pada konteks tahun anggaran dan sumber dana aktif.');
        }

        $this->applyDocumentContext($package);
        $templates = $this->spreadsheetTemplatesForPackage($package);
        $school = $this->context->school();
        if ($templates->isEmpty()) {
            return redirect()->route('spj.preview-package', [$packageId])->with('error', 'Belum ada template Excel aktif untuk pratinjau PDF paket ini.');
        }
        try {
            $contents = Cache::remember(
                $this->packagePreviewCacheKey($package, $templates),
                now()->addMinutes(10),
                fn (): string => app(SpjTemplateService::class)->packagePreviewPdfBytes($templates, $package, $school),
            );
        } catch (Throwable $exception) {
            report($exception);

            return response('Pratinjau PDF paket gagal dibuat: '.$exception->getMessage(), 500);
        }

        // Download/preview adalah operasi baca. Lifecycle Paket hanya boleh berubah
        // melalui workflow READY/NUMBERED/FINAL/CANCELLED yang eksplisit.

        return $this->inlinePdfResponse($contents, 'PRATINJAU-PAKET-SPJ-'.$package->document_number.'.pdf');
    }

    /** @return Collection<int, DocumentTemplate> */
    private function spreadsheetTemplatesForPackage(SpjPackage $package): Collection
    {
        return $this->templateSelector->spreadsheetsForPackage($package);
    }

    /** @param Collection<int, DocumentTemplate> $templates */
    private function packagePreviewCacheKey(SpjPackage $package, Collection $templates): string
    {
        $transaction = $package->transaction;
        $transaction->loadMissing([
            'items',
            'goods',
            'workOrder',
            'workers',
            'participants.item',
            'travels',
            'honors',
            'serviceRecipients',
            'payments',
            'goodsReceipts',
            'fiscalYear',
        ]);

        $revision = [
            'school_id' => $this->context->schoolId(),
            'fiscal_year_id' => $this->context->fiscalYearId(),
            'fund_source_id' => $this->context->fundSourceId(),
            'package' => $this->stableAttributes($package),
            'transaction' => $this->stableAttributes($transaction),
            'fiscal_year' => $transaction->fiscalYear ? $this->stableAttributes($transaction->fiscalYear) : null,
            'items' => $this->stableModels($transaction->items),
            'goods' => $this->stableModels($transaction->goods),
            'work_order' => $transaction->workOrder ? $this->stableAttributes($transaction->workOrder) : null,
            'workers' => $this->stableModels($transaction->workers),
            'participants' => $transaction->participants
                ->sortBy(fn ($participant): string => sprintf('%010d:%010d', (int) ($participant->sort_order ?? 0), (int) $participant->getKey()))
                ->values()
                ->map(fn ($participant): array => [
                    'attributes' => $this->stableAttributes($participant),
                    'item' => $participant->item ? $this->stableAttributes($participant->item) : null,
                ])
                ->all(),
            'travels' => $this->stableModels($transaction->travels),
            'honors' => $this->stableModels($transaction->honors),
            'service_recipients' => $this->stableModels($transaction->serviceRecipients),
            'payments' => $this->stableModels($transaction->payments),
            'goods_receipts' => $this->stableModels($transaction->goodsReceipts),
            'templates' => $templates
                ->sortBy(fn (DocumentTemplate $template): string => sprintf('%010d:%s', (int) $template->getKey(), (string) $template->document_type))
                ->values()
                ->map(fn (DocumentTemplate $template): array => $this->stableAttributes($template))
                ->all(),
            'school' => $this->stableAttributes($this->context->school()),
            'school_profile' => DB::connection('school')
                ->table('school_profiles')
                ->where('fiscal_year_id', $transaction->fiscal_year_id)
                ->first(),
        ];

        return 'spj:preview-package-pdf:'.hash('sha256', serialize($revision));
    }

    private function stableAttributes(object $model): array
    {
        $attributes = $model->getAttributes();
        ksort($attributes);

        return $attributes;
    }

    private function stableModels(Collection $models): array
    {
        return $models
            ->sortBy(fn ($model): string => sprintf('%010d:%010d', (int) ($model->sort_order ?? 0), (int) $model->getKey()))
            ->values()
            ->map(fn ($model): array => $this->stableAttributes($model))
            ->all();
    }

    private function applyDocumentContext(SpjPackage $package): void
    {
        app(SpjMaintenanceDocumentContextService::class)->apply($package);
    }

    private function inlinePdfResponse(string $contents, string $fileName): Response
    {
        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->safeInlineName($fileName).'"',
            'Content-Length' => (string) strlen($contents),
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    private function safeInlineName(string $name): string
    {
        return preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?: 'pratinjau-spj.pdf';
    }

    private function assertBinaryOutput(
        BinaryFileResponse $response,
        string $format,
        string $documentLabel,
        ?string $documentType = null,
    ): BinaryFileResponse {
        $path = $response->getFile()->getPathname();

        try {
            app(SpjGeneratedDocumentValidator::class)->assertBinaryResponse(
                $response,
                $format,
                $documentLabel,
                $documentType,
            );
        } catch (Throwable $exception) {
            if (is_file($path)) {
                @unlink($path);
            }

            throw $exception;
        }

        return $response;
    }

    private function assertPdfOutput(Response $response, string $documentLabel): Response
    {
        app(SpjGeneratedDocumentValidator::class)->assertPdfResponse($response, $documentLabel);

        return $response;
    }
}
