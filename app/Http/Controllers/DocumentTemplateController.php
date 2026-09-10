<?php

namespace App\Http\Controllers;

use App\Services\DocumentTemplateLibraryService;
use App\Services\DocumentTemplateSampleGenerator;
use App\Services\SpjDocumentTypeRegistry;
use App\Services\SpjTemplateService;
use App\UseCases\DocumentTemplates\ImportDocumentTemplatePackageUseCase;
use App\UseCases\DocumentTemplates\UploadDocumentTemplateUseCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DocumentTemplateController extends Controller
{
    public function __construct(
        private readonly DocumentTemplateLibraryService $library,
        private readonly UploadDocumentTemplateUseCase $uploadTemplate,
        private readonly ImportDocumentTemplatePackageUseCase $importTemplatePackage,
        private readonly DocumentTemplateSampleGenerator $samples,
    ) {}

    public function index(Request $request): View
    {
        $categories = SpjDocumentTypeRegistry::categories();
        $filters = $request->validate([
            'status' => ['nullable', 'in:all,active,inactive'],
            'category' => ['nullable', 'in:'.implode(',', $categories)],
        ]);
        $catalog = $this->library->catalog($filters);

        return view('document-templates.index', [
            'templates' => $catalog['templates'],
            'categories' => $categories,
            'filters' => $filters,
            'placeholderGroups' => SpjTemplateService::placeholderGroups(),
            'documentTypes' => SpjDocumentTypeRegistry::options(),
            'validationResults' => $catalog['validationResults'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->hasFile('template_package')) {
            return $this->importPackage($request);
        }

        $categories = SpjDocumentTypeRegistry::categories();
        $data = $request->validate([
            'document_type' => ['required', 'string', 'in:'.implode(',', SpjDocumentTypeRegistry::codes())],
            'name' => ['required', 'string', 'max:120'],
            'template' => ['required', 'file', 'mimes:docx,xlsx', 'max:10240'],
            'applicable_categories' => ['nullable', 'array'],
            'applicable_categories.*' => ['string', 'in:'.implode(',', $categories)],
        ]);

        $result = $this->uploadTemplate->handle(
            (string) $data['document_type'],
            (string) $data['name'],
            $request->file('template'),
            $data['applicable_categories'] ?? [],
        );

        $response = back()->with('success', 'Template '.$data['name'].' berhasil disimpan.');
        if ($result['warnings'] !== []) {
            $response->with('template_validation_warnings', $result['warnings']);
        }

        return $response;
    }

    /** Mengimpor workbook master menjadi seluruh template canonical XLSX. */
    public function importPackage(Request $request): RedirectResponse
    {
        $request->validate([
            'template_package' => ['required', 'file', 'mimes:xlsx', 'max:20480'],
            'replace_existing' => ['nullable', 'boolean'],
        ]);

        $result = $this->importTemplatePackage->handle(
            $request->file('template_package'),
            $request->boolean('replace_existing'),
        );

        $message = 'Paket template berhasil diimpor: '.$result['imported'].' template canonical.';
        if ($result['replaced'] > 0) {
            $message .= ' '.$result['replaced'].' template lama diganti.';
        }

        $response = back()->with('success', $message);
        if ($result['warnings'] !== []) {
            $response->with('template_package_warnings', $result['warnings']);
        }

        return $response;
    }

    /** Memperbarui status aktif dan kategori yang memakai suatu template. */
    public function updateMapping(Request $request, string $templateId): RedirectResponse
    {
        $categories = SpjDocumentTypeRegistry::categories();
        $data = $request->validate([
            'is_active' => ['nullable', 'boolean'],
            'applicable_categories' => ['nullable', 'array'],
            'applicable_categories.*' => ['string', 'in:'.implode(',', $categories)],
        ]);

        if (! $this->library->updateMapping(
            $templateId,
            (bool) ($data['is_active'] ?? false),
            $data['applicable_categories'] ?? [],
        )) {
            return back()->with('error', 'Template tidak ditemukan.');
        }

        return back()->with('success', 'Pemetaan template berhasil diperbarui.');
    }

    /** Mengunduh file template terakhir yang tersimpan tanpa menjalankan renderer SPJ. */
    public function downloadStored(string $templateId)
    {
        $download = $this->library->storedDownload($templateId);
        if ($download['status'] === 'missing') {
            return back()->with('error', 'Template tidak ditemukan.');
        }
        if ($download['status'] === 'file_missing') {
            return back()->with('error', 'Berkas template tidak ditemukan pada penyimpanan. Unggah ulang template ini.');
        }

        return Storage::download($download['path'], $download['name']);
    }

    public function destroy(string $templateId): RedirectResponse
    {
        if (! $this->library->destroy($templateId)) {
            return back()->with('error', 'Template tidak ditemukan.');
        }

        return back()->with('success', 'Template berhasil dihapus.');
    }

    public function sample(string $format)
    {
        abort_unless(in_array($format, ['docx', 'xlsx'], true), 404);
        $sample = $this->samples->generate($format);

        return response()->download($sample['path'], $sample['download_name'])->deleteFileAfterSend(true);
    }
}
