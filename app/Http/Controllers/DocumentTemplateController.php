<?php

namespace App\Http\Controllers;

use App\Services\DocumentTemplateIndividualDownloadService;
use App\Services\DocumentTemplateLibraryService;
use App\Services\DocumentTemplateMasterExportService;
use App\Services\DocumentTemplateSampleGenerator;
use App\Services\SpjDocumentTypeRegistry;
use App\Services\SpjTemplateService;
use App\UseCases\DocumentTemplates\ImportDocumentTemplatePackageUseCase;
use App\UseCases\DocumentTemplates\UploadDocumentTemplateUseCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class DocumentTemplateController extends Controller
{
    public function __construct(
        private readonly DocumentTemplateLibraryService $library,
        private readonly DocumentTemplateIndividualDownloadService $individualDownloads,
        private readonly DocumentTemplateMasterExportService $masterExports,
        private readonly UploadDocumentTemplateUseCase $uploadTemplate,
        private readonly ImportDocumentTemplatePackageUseCase $importTemplatePackage,
        private readonly DocumentTemplateSampleGenerator $samples,
        private readonly SpjTemplateService $templateService,
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
            'placeholderGroups' => $this->templateService::placeholderGroups(),
            'documentTypes' => SpjDocumentTypeRegistry::options(),
            'validationResults' => $catalog['validationResults'],
            'uploadLimits' => $this->uploadLimits(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // The UI sends the upload mode in the query string. This survives even when
        // PHP discards an oversized POST body because post_max_size is exceeded.
        // Keep the body-based fallback for older clients/bookmarks.
        $mode = strtolower(trim((string) $request->query('upload', '')));
        if ($mode === 'package') {
            return $this->importPackage($request);
        }
        if ($mode === 'single') {
            return $this->storeSingleTemplate($request);
        }

        if ($request->has('replace_existing') || $request->hasFile('template_package')) {
            return $this->importPackage($request);
        }

        return $this->storeSingleTemplate($request);
    }

    private function storeSingleTemplate(Request $request): RedirectResponse
    {
        $this->ensurePostBodyWithinLimit($request, 'template', 'templateUpload');

        $categories = SpjDocumentTypeRegistry::categories();
        $data = $request->validateWithBag('templateUpload', [
            'document_type' => ['required', 'string', 'in:'.implode(',', SpjDocumentTypeRegistry::codes())],
            'name' => ['required', 'string', 'max:120'],
            'template' => ['required', 'file', 'extensions:docx,xlsx', 'max:10240'],
            'applicable_categories' => ['nullable', 'array'],
            'applicable_categories.*' => ['string', 'in:'.implode(',', $categories)],
        ], [
            'template.required' => 'Pilih file template DOCX atau XLSX yang akan diunggah.',
            'template.uploaded' => 'Upload file template gagal. Periksa ukuran file dan batas upload PHP pada komputer ini.',
            'template.extensions' => 'File template harus berekstensi .docx atau .xlsx.',
            'template.max' => 'Ukuran file template maksimal 10 MB.',
        ]);

        try {
            $result = $this->uploadTemplate->handle(
                (string) $data['document_type'],
                (string) $data['name'],
                $request->file('template'),
                $data['applicable_categories'] ?? [],
            );
        } catch (ValidationException $exception) {
            $exception->errorBag = 'templateUpload';

            throw $exception;
        }

        $response = back()->with('success', 'Template '.$data['name'].' berhasil disimpan.');
        if ($result['warnings'] !== []) {
            $response->with('template_validation_warnings', $result['warnings']);
        }

        return $response;
    }

    /** Mengimpor workbook master menjadi seluruh template canonical XLSX. */
    public function importPackage(Request $request): RedirectResponse
    {
        $this->ensurePostBodyWithinLimit($request, 'template_package', 'templatePackageUpload');

        $request->validateWithBag('templatePackageUpload', [
            'template_package' => ['required', 'file', 'extensions:xlsx', 'max:20480'],
            'replace_existing' => ['nullable', 'boolean'],
        ], [
            'template_package.required' => 'Pilih workbook master XLSX yang akan diimpor.',
            'template_package.uploaded' => 'Upload workbook master gagal. Periksa ukuran file dan batas upload PHP pada komputer ini.',
            'template_package.extensions' => 'Workbook master harus berekstensi .xlsx.',
            'template_package.max' => 'Ukuran workbook master maksimal 20 MB.',
        ]);

        try {
            $result = $this->importTemplatePackage->handle(
                $request->file('template_package'),
                $request->boolean('replace_existing'),
            );
        } catch (ValidationException $exception) {
            $exception->errorBag = 'templatePackageUpload';

            throw $exception;
        }

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

    /** Mengunduh file template terpilih tanpa menjalankan renderer SPJ. */
    public function downloadStored(string $templateId)
    {
        $download = $this->library->storedDownload($templateId);
        if ($download['status'] === 'missing') {
            return back()->with('error', 'Template tidak ditemukan.');
        }
        if ($download['status'] === 'file_missing') {
            return back()->with('error', 'Berkas template tidak ditemukan pada penyimpanan. Unggah ulang template ini.');
        }

        try {
            $individualPath = $this->individualDownloads->prepare(
                (string) $download['path'],
                (string) $download['document_type'],
                (string) $download['format'],
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Template terpilih tidak dapat disiapkan sebagai workbook individual. Berkas master tetap aman dan tidak diubah.');
        }

        if (is_string($individualPath) && $individualPath !== '') {
            return response()->download($individualPath, $download['name'])->deleteFileAfterSend(true);
        }

        return Storage::disk('local')->download($download['path'], $download['name']);
    }

    /** Menyusun master XLSX terbaru dari setiap template canonical XLSX yang aktif. */
    public function downloadMaster()
    {
        try {
            $artifact = $this->masterExports->generate();
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Master template terbaru tidak dapat dibuat. Periksa template XLSX aktif lalu coba kembali.');
        }

        return response()->download($artifact['path'], $artifact['download_name'])->deleteFileAfterSend(true);
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

    /** @return array{upload_max_filesize:string,post_max_size:string,effective_max_upload:string} */
    private function uploadLimits(): array
    {
        $uploadLimit = trim((string) ini_get('upload_max_filesize')) ?: 'tidak diketahui';
        $postLimit = trim((string) ini_get('post_max_size')) ?: 'tidak diketahui';
        $uploadBytes = $this->phpSizeToBytes($uploadLimit);
        $postBytes = $this->phpSizeToBytes($postLimit);
        $effectiveBytes = min(array_filter([$uploadBytes, $postBytes], fn (int $value): bool => $value > 0) ?: [0]);

        return [
            'upload_max_filesize' => $uploadLimit,
            'post_max_size' => $postLimit,
            'effective_max_upload' => $effectiveBytes > 0 ? $this->humanBytes($effectiveBytes) : 'tidak diketahui',
        ];
    }

    private function ensurePostBodyWithinLimit(Request $request, string $field, string $errorBag): void
    {
        $contentLength = (int) $request->server('CONTENT_LENGTH', 0);
        $postMaxBytes = $this->phpSizeToBytes((string) ini_get('post_max_size'));

        if ($contentLength <= 0 || $postMaxBytes <= 0 || $contentLength <= $postMaxBytes) {
            return;
        }

        $exception = ValidationException::withMessages([
            $field => 'Upload ditolak oleh PHP karena ukuran request '.$this->humanBytes($contentLength)
                .' melebihi post_max_size '.trim((string) ini_get('post_max_size')).'. '
                .'Naikkan batas upload PHP atau pilih file yang lebih kecil.',
        ]);
        $exception->errorBag = $errorBag;

        throw $exception;
    }

    private function phpSizeToBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        if (is_numeric($value)) {
            return max(0, (int) $value);
        }

        $number = (float) $value;
        $unit = strtolower(substr($value, -1));

        return match ($unit) {
            'g' => (int) round($number * 1024 * 1024 * 1024),
            'm' => (int) round($number * 1024 * 1024),
            'k' => (int) round($number * 1024),
            default => max(0, (int) $number),
        };
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024 * 1024) {
            return number_format($bytes / (1024 * 1024 * 1024), 1, ',', '.').' GB';
        }
        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 1, ',', '.').' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1, ',', '.').' KB';
        }

        return number_format($bytes, 0, ',', '.').' B';
    }
}
