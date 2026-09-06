<?php

namespace App\UseCases\Spj;

use App\Models\DocumentTemplate;
use App\Models\School;
use App\Models\SpjPackage;
use App\Services\SpjPackageValidationService;
use App\Services\SpjTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class SpjDocumentUseCase
{
    public function download(string $packageId)
    {
        $validator = app(SpjPackageValidationService::class);
        $templates = app(SpjTemplateService::class);
        $package = SpjPackage::query()->with(['transaction.items', 'transaction.goods', 'transaction.workers', 'transaction.participants', 'transaction.travels'])->find($packageId);
        if (! $package || $package->transaction->fiscal_year_id !== (int) session('active_fiscal_year_id')) {
            return redirect()->route('spj.index', ['tab' => 'paket', 'package_id' => $packageId])->with('error', 'Paket dokumen tidak ditemukan pada tahun anggaran aktif.');
        }
        $issues = $validator->validate($package);
        if ($issues) {
            return back()->with('error', 'PDF belum dapat dibuat. Lengkapi seluruh data wajib terlebih dahulu.');
        }

        $school = School::query()->findOrFail(session('active_school_id'));
        $activeTemplates = $this->activeTemplatesForPackage($package);

        return $templates->downloadPackagePdf($activeTemplates, $package, $school);
    }

    public function downloadPackageExcel(string $packageId)
    {
        $package = SpjPackage::query()->with(['transaction.items', 'transaction.goods', 'transaction.workers', 'transaction.participants', 'transaction.travels'])->find($packageId);
        if (! $package || $package->transaction->fiscal_year_id !== (int) session('active_fiscal_year_id')) {
            return redirect()->route('spj.index', ['tab' => 'paket', 'package_id' => $packageId])->with('error', 'Paket dokumen tidak ditemukan pada tahun anggaran aktif.');
        }

        return app(SpjTemplateService::class)->downloadPackageExcel($this->activeTemplatesForPackage($package), $package, School::query()->findOrFail(session('active_school_id')));
    }

    public function previewPackage(string $packageId): View|RedirectResponse
    {
        $package = SpjPackage::query()->with(['transaction.items', 'transaction.goods', 'transaction.workers', 'transaction.participants', 'transaction.travels'])->find($packageId);
        if (! $package || $package->transaction->fiscal_year_id !== (int) session('active_fiscal_year_id')) {
            return redirect()->route('spj.index', ['tab' => 'paket', 'package_id' => $packageId])->with('error', 'Paket dokumen tidak ditemukan pada tahun anggaran aktif.');
        }

        $templates = $this->activeTemplatesForPackage($package);
        $template = new DocumentTemplate(['name' => 'Paket SPJ', 'format' => 'xlsx']);

        return view('spj-documents.template-preview', [
            'package' => $package,
            'template' => $template,
            'previewHtml' => app(SpjTemplateService::class)->packagePreviewHtml($templates, $package, School::query()->findOrFail(session('active_school_id'))),
            'validationIssues' => app(SpjPackageValidationService::class)->validate($package),
        ]);
    }

    public function downloadTemplate(string $packageId, string $templateId)
    {
        $validator = app(SpjPackageValidationService::class);
        $templates = app(SpjTemplateService::class);
        $package = SpjPackage::query()->with(['transaction.items', 'transaction.goods', 'transaction.workOrder', 'transaction.workers', 'transaction.participants', 'transaction.travels'])->find($packageId);
        $template = DocumentTemplate::query()->find($templateId);
        if (! $package || ! $template || ! $template->is_active || $package->transaction->fiscal_year_id !== (int) session('active_fiscal_year_id') || $template->fiscal_year_id !== (int) session('active_fiscal_year_id')) {
            return redirect()->route('spj.index', ['tab' => 'paket', 'package_id' => $packageId])->with('error', 'Paket atau template tidak ditemukan pada tahun anggaran aktif.');
        }
        if ($validator->validate($package)) {
            return back()->with('error', 'Dokumen dari template belum dapat dibuat. Lengkapi data wajib terlebih dahulu.');
        }

        $school = School::query()->findOrFail(session('active_school_id'));
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

        return $templates->download($template, $package, $school);
    }

    public function downloadTemplatePdf(string $packageId, string $templateId)
    {
        $validator = app(SpjPackageValidationService::class);
        $templates = app(SpjTemplateService::class);
        $package = SpjPackage::query()->with(['transaction.items', 'transaction.goods', 'transaction.workOrder', 'transaction.workers', 'transaction.participants', 'transaction.travels'])->find($packageId);
        $template = DocumentTemplate::query()->find($templateId);
        if (! $package || ! $template || ! $template->is_active || $package->transaction->fiscal_year_id !== (int) session('active_fiscal_year_id') || $template->fiscal_year_id !== (int) session('active_fiscal_year_id')) {
            return redirect()->route('spj.index', ['tab' => 'paket', 'package_id' => $packageId])->with('error', 'Paket atau template aktif tidak ditemukan pada tahun anggaran aktif.');
        }
        if ($validator->validate($package)) {
            return back()->with('error', 'PDF dari template belum dapat dibuat. Lengkapi data wajib terlebih dahulu.');
        }

        return $templates->downloadPdf($template, $package, School::query()->findOrFail(session('active_school_id')));
    }

    public function previewTemplate(string $packageId, string $templateId): View|RedirectResponse
    {
        $validator = app(SpjPackageValidationService::class);
        $templates = app(SpjTemplateService::class);
        $package = SpjPackage::query()->with(['transaction.items', 'transaction.goods', 'transaction.workers', 'transaction.participants', 'transaction.travels'])->find($packageId);
        $template = DocumentTemplate::query()->find($templateId);
        if (! $package || ! $template || ! $template->is_active || $package->transaction->fiscal_year_id !== (int) session('active_fiscal_year_id') || $template->fiscal_year_id !== (int) session('active_fiscal_year_id')) {
            return redirect()->route('spj.index', ['tab' => 'paket', 'package_id' => $packageId])->with('error', 'Paket atau template tidak ditemukan pada tahun anggaran aktif.');
        }
        $school = School::query()->findOrFail(session('active_school_id'));
        $previewHtml = $templates->previewHtml($template, $package, $school);

        return view('spj-documents.template-preview', [
            'package' => $package,
            'template' => $template,
            'previewHtml' => $previewHtml,
            'validationIssues' => $validator->validate($package),
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
}
