<?php

namespace App\UseCases\Spj;

use App\Models\DocumentTemplate;
use App\Models\SpjPackage;
use App\Services\SpjGeneratedDocumentValidator;
use App\Services\SpjMaintenanceDocumentContextService;
use App\Services\SpjPackageValidationService;
use App\Services\SpjTemplateRenderPreflight;
use App\Services\SpjTemplateService;
use App\Support\ActiveSpjContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SpjDocumentUseCase
{
    public function __construct(private readonly ActiveSpjContext $context) {}

    public function download(string $packageId)
    {
        $validator = app(SpjPackageValidationService::class);
        $templates = app(SpjTemplateService::class);
        $preflight = app(SpjTemplateRenderPreflight::class);
        $package = SpjPackage::query()->with(['transaction.items', 'transaction.goods', 'transaction.workers', 'transaction.participants', 'transaction.travels'])->find($packageId);
        if (! $package || ! $this->context->matchesFiscalYear($package->transaction)) {
            return redirect()->route('spj.index', ['tab' => 'paket', 'package_id' => $packageId])->with('error', 'Paket dokumen tidak ditemukan pada tahun anggaran aktif.');
        }
        $issues = $validator->validate($package);
        if ($issues) {
            return back()->with('error', 'PDF belum dapat dibuat. Lengkapi seluruh data wajib terlebih dahulu.');
        }

        $this->applyDocumentContext($package);
        $school = $this->context->school();
        $activeTemplates = $this->activeTemplatesForPackage($package);
        $preflight->assertAllRenderable($activeTemplates, $package, $school);
        $response = $templates->downloadPackagePdf($activeTemplates, $package, $school);

        return $this->assertPdfOutput($response, 'Paket SPJ PDF');
    }

    public function downloadPackageExcel(string $packageId)
    {
        $package = SpjPackage::query()->with(['transaction.items', 'transaction.goods', 'transaction.workers', 'transaction.participants', 'transaction.travels'])->find($packageId);
        if (! $package || ! $this->context->matchesFiscalYear($package->transaction)) {
            return redirect()->route('spj.index', ['tab' => 'paket', 'package_id' => $packageId])->with('error', 'Paket dokumen tidak ditemukan pada tahun anggaran aktif.');
        }

        $this->applyDocumentContext($package);
        $school = $this->context->school();
        $activeTemplates = $this->activeTemplatesForPackage($package);
        app(SpjTemplateRenderPreflight::class)->assertAllRenderable($activeTemplates, $package, $school);
        $response = app(SpjTemplateService::class)->downloadPackageExcel($activeTemplates, $package, $school);

        return $this->assertBinaryOutput($response, 'xlsx', 'Paket SPJ Excel');
    }

    public function previewPackage(string $packageId): View|RedirectResponse
    {
        $package = SpjPackage::query()->with(['transaction.items', 'transaction.goods', 'transaction.workers', 'transaction.participants', 'transaction.travels'])->find($packageId);
        if (! $package || ! $this->context->matchesFiscalYear($package->transaction)) {
            return redirect()->route('spj.index', ['tab' => 'paket', 'package_id' => $packageId])->with('error', 'Paket dokumen tidak ditemukan pada tahun anggaran aktif.');
        }

        $validationIssues = app(SpjPackageValidationService::class)->validate($package);
        $this->applyDocumentContext($package);
        $templates = $this->activeTemplatesForPackage($package);
        $school = $this->context->school();
        app(SpjTemplateRenderPreflight::class)->assertAllRenderable($templates, $package, $school);
        $template = new DocumentTemplate(['name' => 'Paket SPJ', 'format' => 'xlsx']);

        return view('spj-documents.template-preview', [
            'package' => $package,
            'template' => $template,
            'previewHtml' => app(SpjTemplateService::class)->packagePreviewHtml($templates, $package, $school),
            'validationIssues' => $validationIssues,
        ]);
    }

    public function downloadTemplate(string $packageId, string $templateId)
    {
        $validator = app(SpjPackageValidationService::class);
        $templates = app(SpjTemplateService::class);
        $package = SpjPackage::query()->with(['transaction.items', 'transaction.goods', 'transaction.workOrder', 'transaction.workers', 'transaction.participants', 'transaction.travels'])->find($packageId);
        $template = DocumentTemplate::query()->find($templateId);
        if (! $package || ! $template || ! $template->is_active || ! $this->context->matchesFiscalYear($package->transaction) || $template->fiscal_year_id !== $this->context->fiscalYearId()) {
            return redirect()->route('spj.index', ['tab' => 'paket', 'package_id' => $packageId])->with('error', 'Paket atau template tidak ditemukan pada tahun anggaran aktif.');
        }
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
        $validator = app(SpjPackageValidationService::class);
        $templates = app(SpjTemplateService::class);
        $package = SpjPackage::query()->with(['transaction.items', 'transaction.goods', 'transaction.workOrder', 'transaction.workers', 'transaction.participants', 'transaction.travels'])->find($packageId);
        $template = DocumentTemplate::query()->find($templateId);
        if (! $package || ! $template || ! $template->is_active || ! $this->context->matchesFiscalYear($package->transaction) || $template->fiscal_year_id !== $this->context->fiscalYearId()) {
            return redirect()->route('spj.index', ['tab' => 'paket', 'package_id' => $packageId])->with('error', 'Paket atau template aktif tidak ditemukan pada tahun anggaran aktif.');
        }
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
        if (! $package || ! $template || ! $template->is_active || ! $this->context->matchesFiscalYear($package->transaction) || $template->fiscal_year_id !== $this->context->fiscalYearId()) {
            return redirect()->route('spj.index', ['tab' => 'paket', 'package_id' => $packageId])->with('error', 'Paket atau template tidak ditemukan pada tahun anggaran aktif.');
        }
        $school = $this->context->school();
        $validationIssues = $validator->validate($package);
        $this->applyDocumentContext($package);
        app(SpjTemplateRenderPreflight::class)->assertRenderable($template, $package, $school);
        $previewHtml = $templates->previewHtml($template, $package, $school);

        return view('spj-documents.template-preview', [
            'package' => $package,
            'template' => $template,
            'previewHtml' => $previewHtml,
            'validationIssues' => $validationIssues,
        ]);
    }

    /** @return Collection<int, DocumentTemplate> */
    private function activeTemplatesForPackage(SpjPackage $package): Collection
    {
        $category = strtoupper((string) $package->transaction->spj_category);

        return DocumentTemplate::query()
            ->where(['fiscal_year_id' => $package->transaction->fiscal_year_id, 'is_active' => true])
            ->orderBy('document_type')
            ->get()
            ->filter(fn (DocumentTemplate $template): bool => empty($template->applicable_categories)
                || in_array('SEMUA', $template->applicable_categories, true)
                || in_array($category, $template->applicable_categories, true));
    }

    private function applyDocumentContext(SpjPackage $package): void
    {
        app(SpjMaintenanceDocumentContextService::class)->apply($package);
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
