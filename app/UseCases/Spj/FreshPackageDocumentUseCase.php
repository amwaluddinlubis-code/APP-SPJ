<?php

namespace App\UseCases\Spj;

use App\Models\DocumentTemplate;
use App\Models\SpjFreshPackage;
use App\Models\SpjPackage;
use App\Services\SpjFreshPackageDocumentAdapter;
use App\Services\SpjFreshPackageValidationService;
use App\Services\SpjPackageTemplateSelector;
use App\Services\SpjTemplateService;
use App\Support\ActiveSpjContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

final class FreshPackageDocumentUseCase
{
    public function __construct(
        private readonly ActiveSpjContext $context,
        private readonly SpjFreshPackageDocumentAdapter $adapter,
        private readonly SpjPackageTemplateSelector $templateSelector,
        private readonly SpjFreshPackageValidationService $validator,
        private readonly SpjTemplateService $templates,
    ) {}

    /** @return Collection<int, DocumentTemplate> */
    public function templates(SpjFreshPackage $package): Collection
    {
        return $this->templateSelector->forPackage($this->adapter->toLegacyPackage($package));
    }

    public function preview(string $packageId): View|RedirectResponse
    {
        $resolved = $this->resolved($packageId);
        if ($resolved === null) {
            return $this->missing($packageId);
        }
        [$freshPackage, $package, $templates] = $resolved;
        if ($templates->isEmpty()) {
            return $this->backToFresh($freshPackage, 'Belum ada template aktif yang sesuai dengan kategori paket fresh ini.');
        }

        return view('spj-documents.template-preview', [
            'package' => $package,
            'template' => new DocumentTemplate(['name' => 'Paket SPJ Fresh', 'format' => 'xlsx']),
            'previewHtml' => $this->templates->packagePreviewHtml($templates, $package, $this->context->school()),
            'previewPdfUrl' => route('spj.fresh-preview-package-pdf', $freshPackage->id),
            'previewPdfReady' => false,
            'validationIssues' => [],
        ]);
    }

    public function previewPdf(string $packageId): Response|RedirectResponse
    {
        $resolved = $this->resolved($packageId);
        if ($resolved === null) {
            return $this->missing($packageId);
        }
        [$freshPackage, $package, $templates] = $resolved;
        if ($templates->isEmpty()) {
            return $this->backToFresh($freshPackage, 'Belum ada template aktif yang sesuai dengan kategori paket fresh ini.');
        }

        $contents = $this->templates->packagePreviewPdfBytes($templates, $package, $this->context->school());

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="PRATINJAU-PAKET-FRESH-'.$freshPackage->id.'.pdf"',
            'Content-Length' => (string) strlen($contents),
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function downloadExcel(string $packageId)
    {
        $resolved = $this->resolved($packageId);
        if ($resolved === null) {
            return $this->missing($packageId);
        }
        [$freshPackage, $package, $templates] = $resolved;
        if ($templates->isEmpty()) {
            return $this->backToFresh($freshPackage, 'Belum ada template aktif yang sesuai dengan kategori paket fresh ini.');
        }

        return $this->templates->downloadPackageExcel($templates, $package, $this->context->school());
    }

    public function downloadPdf(string $packageId)
    {
        $resolved = $this->resolved($packageId);
        if ($resolved === null) {
            return $this->missing($packageId);
        }
        [$freshPackage, $package, $templates] = $resolved;
        if ($templates->isEmpty()) {
            return $this->backToFresh($freshPackage, 'Belum ada template aktif yang sesuai dengan kategori paket fresh ini.');
        }

        return $this->templates->downloadPackagePdf($templates, $package, $this->context->school());
    }

    /** @return array{0:SpjFreshPackage,1:SpjPackage,2:Collection<int,DocumentTemplate>}|null */
    private function resolved(string $packageId): ?array
    {
        $freshPackage = SpjFreshPackage::query()
            ->with(['transaction.items.rawMirrorRow', 'transaction.rawMirrorRow', 'documents'])
            ->whereKey($packageId)
            ->whereHas('transaction', fn ($query) => $query->forSpjContext($this->context))
            ->first();
        if ($freshPackage === null || $this->validator->validate($freshPackage) !== []) {
            return null;
        }

        $package = $this->adapter->toLegacyPackage($freshPackage);

        return [$freshPackage, $package, $this->templateSelector->forPackage($package)->filter(fn (DocumentTemplate $template): bool => strtolower((string) $template->format) === 'xlsx')->values()];
    }

    private function missing(string $packageId): RedirectResponse
    {
        return redirect()->route('spj.index', ['tab' => 'paket', 'fresh_package_id' => $packageId])
            ->with('error', 'Paket fresh tidak ditemukan atau belum lulus validasi dasar.');
    }

    private function backToFresh(SpjFreshPackage $package, string $message): RedirectResponse
    {
        return redirect()->route('spj.index', ['tab' => 'paket', 'fresh_package_id' => $package->id])->with('error', $message);
    }
}
