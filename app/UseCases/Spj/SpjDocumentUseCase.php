<?php

namespace App\UseCases\Spj;

use App\Models\DocumentTemplate;
use App\Models\School;
use App\Models\SpjPackage;
use App\Services\SpjPackageValidationService;
use App\Services\SpjPdfService;
use App\Services\SpjTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SpjDocumentUseCase
{
    public function download(string $packageId)
    {
        $validator = app(SpjPackageValidationService::class);
        $pdf = app(SpjPdfService::class);
        $package = SpjPackage::query()->with(['transaction.items', 'transaction.goods', 'transaction.workers', 'transaction.participants', 'transaction.travels'])->find($packageId);
        if (! $package || $package->transaction->fiscal_year_id !== (int) session('active_fiscal_year_id')) {
            return redirect()->route('spj.index', ['tab' => 'paket', 'package_id' => $packageId])->with('error', 'Paket dokumen tidak ditemukan pada tahun anggaran aktif.');
        }
        $issues = $validator->validate($package);
        if ($issues) {
            return back()->with('error', 'PDF belum dapat dibuat. Lengkapi seluruh data wajib terlebih dahulu.');
        }

        $school = School::query()->findOrFail(session('active_school_id'));

        return $pdf->download($package, $school);
    }

    public function downloadTemplate(string $packageId, string $templateId)
    {
        $validator = app(SpjPackageValidationService::class);
        $templates = app(SpjTemplateService::class);
        $package = SpjPackage::query()->with(['transaction.items', 'transaction.goods', 'transaction.workOrder', 'transaction.workers', 'transaction.participants', 'transaction.travels'])->find($packageId);
        $template = DocumentTemplate::query()->find($templateId);
        if (! $package || ! $template || $package->transaction->fiscal_year_id !== (int) session('active_fiscal_year_id') || $template->fiscal_year_id !== (int) session('active_fiscal_year_id')) {
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

    public function previewTemplate(string $packageId, string $templateId): View|RedirectResponse
    {
        $validator = app(SpjPackageValidationService::class);
        $templates = app(SpjTemplateService::class);
        $package = SpjPackage::query()->with(['transaction.items', 'transaction.goods', 'transaction.workers', 'transaction.participants', 'transaction.travels'])->find($packageId);
        $template = DocumentTemplate::query()->find($templateId);
        if (! $package || ! $template || $package->transaction->fiscal_year_id !== (int) session('active_fiscal_year_id') || $template->fiscal_year_id !== (int) session('active_fiscal_year_id')) {
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
}
