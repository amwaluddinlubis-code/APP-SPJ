<?php

namespace App\Http\Controllers;

use App\Models\DocumentTemplate;
use App\Services\SpjDocumentTypeRegistry;
use App\Services\SpjTemplatePackageImporter;
use App\Services\SpjTemplateService;
use App\Services\SpjTemplateValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\IOFactory as WordWriter;
use PhpOffice\PhpWord\PhpWord;
use Throwable;

class DocumentTemplateController extends Controller
{
    public function index(Request $request, SpjTemplateValidator $validator): View
    {
        $categories = SpjDocumentTypeRegistry::categories();
        $filters = $request->validate([
            'status' => ['nullable', 'in:all,active,inactive'],
            'category' => ['nullable', 'in:'.implode(',', $categories)],
        ]);
        $query = DocumentTemplate::query()->where('fiscal_year_id', session('active_fiscal_year_id'));
        match ($filters['status'] ?? 'all') {
            'active' => $query->where('is_active', true),
            'inactive' => $query->where('is_active', false),
            default => null,
        };
        if ($filters['category'] ?? null) {
            $category = $filters['category'];
            $query->where(function ($builder) use ($category): void {
                $builder->whereNull('applicable_categories')
                    ->orWhere('applicable_categories', '[]')
                    ->orWhereJsonContains('applicable_categories', $category);
            });
        }

        $templates = $query->orderBy('document_type')->orderBy('name')->paginate(15)->withQueryString();
        $validationResults = [];
        foreach ($templates->getCollection() as $template) {
            $validationResults[$template->id] = $this->validateStoredTemplate($template, $validator);
        }

        return view('document-templates.index', [
            'templates' => $templates,
            'categories' => $categories,
            'filters' => $filters,
            'placeholderGroups' => SpjTemplateService::placeholderGroups(),
            'documentTypes' => SpjDocumentTypeRegistry::options(),
            'validationResults' => $validationResults,
        ]);
    }

    public function store(Request $request, SpjTemplateValidator $validator): RedirectResponse
    {
        $categories = SpjDocumentTypeRegistry::categories();
        $data = $request->validate([
            'document_type' => ['required', 'string', 'in:'.implode(',', SpjDocumentTypeRegistry::codes())],
            'name' => ['required', 'string', 'max:120'],
            'template' => ['required', 'file', 'mimes:docx,xlsx', 'max:10240'],
            'applicable_categories' => ['nullable', 'array'],
            'applicable_categories.*' => ['string', 'in:'.implode(',', $categories)],
        ]);
        $documentType = strtoupper($data['document_type']);
        $uploaded = $request->file('template');
        $extension = strtolower($uploaded->getClientOriginalExtension());
        $temporaryPath = $uploaded->getRealPath();
        if (! is_string($temporaryPath) || $temporaryPath === '') {
            throw ValidationException::withMessages([
                'template' => 'File template tidak dapat dibaca untuk proses validasi.',
            ]);
        }

        try {
            $validation = $validator->validateFile($documentType, $temporaryPath, $extension);
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'template' => 'Template tidak dapat divalidasi: '.$exception->getMessage(),
            ]);
        }

        if (! $validation['valid']) {
            $messages = collect($validation['errors'])
                ->pluck('message')
                ->filter()
                ->values()
                ->all();

            throw ValidationException::withMessages([
                'template' => $messages ?: ['Template tidak memenuhi kontrak dokumen yang dipilih.'],
            ]);
        }

        $path = $uploaded->storeAs(
            'document-templates/'.session('active_fiscal_year_id'),
            uniqid('tpl_', true).'.'.$extension
        );
        $old = DocumentTemplate::query()->where([
            'fiscal_year_id' => session('active_fiscal_year_id'),
            'document_type' => $documentType,
            'format' => $extension,
        ])->first();
        if ($old) {
            Storage::delete($old->file_path);
        }
        DocumentTemplate::updateOrCreate(
            ['fiscal_year_id' => session('active_fiscal_year_id'), 'document_type' => $documentType, 'format' => $extension],
            ['name' => $data['name'], 'file_path' => $path, 'applicable_categories' => $data['applicable_categories'] ?? [], 'is_active' => true]
        );

        $response = back()->with('success', 'Template '.$data['name'].' berhasil disimpan.');
        if ($validation['warnings']) {
            $response->with(
                'template_validation_warnings',
                collect($validation['warnings'])->pluck('message')->filter()->values()->all()
            );
        }

        return $response;
    }

    /** Mengimpor workbook master menjadi seluruh template canonical XLSX. */
    public function importPackage(Request $request, SpjTemplatePackageImporter $importer): RedirectResponse
    {
        $data = $request->validate([
            'template_package' => ['required', 'file', 'mimes:xlsx', 'max:20480'],
            'replace_existing' => ['nullable', 'boolean'],
        ]);

        $uploaded = $request->file('template_package');
        $temporaryPath = $uploaded->getRealPath();
        if (! is_string($temporaryPath) || $temporaryPath === '') {
            throw ValidationException::withMessages([
                'template_package' => 'Workbook paket template tidak dapat dibaca.',
            ]);
        }

        try {
            $result = $importer->importPackage(
                (int) session('active_fiscal_year_id'),
                $temporaryPath,
                $request->boolean('replace_existing'),
            );
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'template_package' => $exception->getMessage(),
            ]);
        }

        if (! $result['valid']) {
            $messages = collect($result['errors'])
                ->map(fn (array $issue): string => '['.$issue['document_type'].'] '.$issue['message'])
                ->values()
                ->all();

            throw ValidationException::withMessages([
                'template_package' => $messages ?: ['Paket template tidak memenuhi kontrak canonical.'],
            ]);
        }

        $message = 'Paket template berhasil diimpor: '.$result['imported'].' template canonical.';
        if (($result['replaced'] ?? 0) > 0) {
            $message .= ' '.$result['replaced'].' template lama diganti.';
        }

        $response = back()->with('success', $message);
        if (! empty($result['warnings'])) {
            $response->with(
                'template_package_warnings',
                collect($result['warnings'])
                    ->map(fn (array $issue): string => '['.$issue['document_type'].'] '.$issue['message'])
                    ->values()
                    ->all()
            );
        }

        return $response;
    }

    /** Memperbarui status aktif dan kategori yang memakai suatu template. */
    public function updateMapping(Request $request, string $templateId): RedirectResponse
    {
        $template = DocumentTemplate::query()->find($templateId);
        if (! $template || $template->fiscal_year_id !== (int) session('active_fiscal_year_id')) {
            return back()->with('error', 'Template tidak ditemukan.');
        }
        $categories = SpjDocumentTypeRegistry::categories();
        $data = $request->validate([
            'is_active' => ['nullable', 'boolean'],
            'applicable_categories' => ['nullable', 'array'],
            'applicable_categories.*' => ['string', 'in:'.implode(',', $categories)],
        ]);
        $template->update(['is_active' => (bool) ($data['is_active'] ?? false), 'applicable_categories' => $data['applicable_categories'] ?? []]);

        return back()->with('success', 'Pemetaan template berhasil diperbarui.');
    }

    /** Mengunduh file template terakhir yang tersimpan tanpa menjalankan renderer SPJ. */
    public function downloadStored(string $templateId)
    {
        $template = DocumentTemplate::query()->find($templateId);
        if (! $template || $template->fiscal_year_id !== (int) session('active_fiscal_year_id')) {
            return back()->with('error', 'Template tidak ditemukan.');
        }

        if (! Storage::exists($template->file_path)) {
            return back()->with('error', 'Berkas template tidak ditemukan pada penyimpanan. Unggah ulang template ini.');
        }

        return Storage::download($template->file_path, $this->storedTemplateDownloadName($template));
    }

    public function destroy(string $templateId): RedirectResponse
    {
        $template = DocumentTemplate::query()->find($templateId);
        if (! $template || $template->fiscal_year_id !== (int) session('active_fiscal_year_id')) {
            return back()->with('error', 'Template tidak ditemukan.');
        }
        Storage::delete($template->file_path);
        $template->delete();

        return back()->with('success', 'Template berhasil dihapus.');
    }

    public function sample(string $format)
    {
        abort_unless(in_array($format, ['docx', 'xlsx'], true), 404);
        $directory = storage_path('app/generated-documents');
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        $path = $directory.'/CONTOH-TEMPLATE-SPJ-'.uniqid().'.'.$format;

        if ($format === 'docx') {
            $word = new PhpWord;
            $section = $word->addSection();
            $section->addText('CONTOH TEMPLATE RINCIAN BELANJA SPJ', ['bold' => true, 'size' => 14]);
            $section->addText('Nomor SPJ: {{NOMOR_SPJ}}');
            $section->addText('No. Bukti: {{NO_BUKTI}}');
            $section->addText('Penerima: {{NAMA_PENERIMA}}');
            $table = $section->addTable(['borderSize' => 6, 'borderColor' => '64748B']);
            $headingRow = $table->addRow();
            foreach (['No', 'Uraian', 'Volume', 'Satuan', 'Harga', 'Jumlah'] as $heading) {
                $headingRow->addCell()->addText($heading);
            }
            $row = $table->addRow();
            foreach (['{{ITEM_NO}}', '{{ITEM_URAIAN}}', '{{ITEM_VOLUME}}', '{{ITEM_SATUAN}}', '{{ITEM_HARGA_SATUAN}}', '{{ITEM_JUMLAH}}'] as $marker) {
                $row->addCell()->addText($marker);
            }
            $section->addText('Total bruto: {{NILAI_BRUTO}}');
            WordWriter::createWriter($word, 'Word2007')->save($path);
        } else {
            $book = new Spreadsheet;
            $sheet = $book->getActiveSheet()->setTitle('Rincian Belanja');
            $sheet->setCellValue('A1', 'CONTOH TEMPLATE RINCIAN BELANJA SPJ');
            $sheet->setCellValue('A2', 'Nomor SPJ: {{NOMOR_SPJ}}');
            $sheet->setCellValue('A3', 'No. Bukti: {{NO_BUKTI}}');
            $sheet->fromArray(['No', 'Uraian', 'Volume', 'Satuan', 'Harga', 'Jumlah'], null, 'A5');
            $sheet->fromArray(['{{ITEM_NO}}', '{{ITEM_URAIAN}}', '{{ITEM_VOLUME}}', '{{ITEM_SATUAN}}', '{{ITEM_HARGA_SATUAN}}', '{{ITEM_JUMLAH}}'], null, 'A6');
            $sheet->setCellValue('E8', 'Total bruto');
            $sheet->setCellValue('F8', '{{NILAI_BRUTO}}');
            foreach (range('A', 'F') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
            (new Xlsx($book))->save($path);
        }

        return response()->download($path, 'CONTOH-TEMPLATE-SPJ.'.$format)->deleteFileAfterSend(true);
    }

    private function storedTemplateDownloadName(DocumentTemplate $template): string
    {
        $extension = strtolower(trim((string) $template->format));
        $base = trim((string) ($template->name ?: $template->document_type));
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '-', $base) ?: 'template-'.$template->id;
        $base = trim($base, '-_.');

        if ($extension !== '' && ! str_ends_with(strtolower($base), '.'.$extension)) {
            $base .= '.'.$extension;
        }

        return $base;
    }

    /** @return array<string,mixed> */
    private function validateStoredTemplate(DocumentTemplate $template, SpjTemplateValidator $validator): array
    {
        try {
            if (! Storage::exists($template->file_path)) {
                return [
                    'valid' => false,
                    'document_type' => SpjDocumentTypeRegistry::canonical((string) $template->document_type),
                    'sheet' => null,
                    'markers' => [],
                    'errors' => [[
                        'code' => 'TEMPLATE_FILE_MISSING',
                        'message' => 'Berkas template tidak ditemukan pada penyimpanan. Unggah ulang template ini.',
                        'markers' => [],
                    ]],
                    'warnings' => [],
                ];
            }

            return $validator->validateFile(
                (string) $template->document_type,
                Storage::path($template->file_path),
                (string) $template->format,
            );
        } catch (Throwable $exception) {
            return [
                'valid' => false,
                'document_type' => SpjDocumentTypeRegistry::canonical((string) $template->document_type),
                'sheet' => null,
                'markers' => [],
                'errors' => [[
                    'code' => 'VALIDATION_FAILED',
                    'message' => 'Template tidak dapat divalidasi: '.$exception->getMessage(),
                    'markers' => [],
                ]],
                'warnings' => [],
            ];
        }
    }
}
