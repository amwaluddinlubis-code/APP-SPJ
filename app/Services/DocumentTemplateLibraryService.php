<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use App\Support\ActiveSpjContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class DocumentTemplateLibraryService
{
    public function __construct(
        private readonly SpjTemplateValidator $validator,
        private readonly ActiveSpjContext $context,
    ) {}

    /**
     * @param  array{status?:string|null,category?:string|null}  $filters
     * @return array{templates:mixed,validationResults:array<int,array<string,mixed>>}
     */
    public function catalog(array $filters): array
    {
        $query = DocumentTemplate::query()->where('fiscal_year_id', $this->context->fiscalYearId());

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
            $validationResults[$template->id] = $this->cachedStoredTemplateValidation($template);
        }

        return compact('templates', 'validationResults');
    }

    /** @param array<int,string> $applicableCategories */
    public function updateMapping(string $templateId, bool $isActive, array $applicableCategories): bool
    {
        $template = $this->findInActiveYear($templateId);
        if (! $template) {
            return false;
        }

        $template->update([
            'is_active' => $isActive,
            'applicable_categories' => $applicableCategories,
        ]);

        return true;
    }

    /** @return array{status:string,path?:string,name?:string,document_type?:string,format?:string} */
    public function storedDownload(string $templateId): array
    {
        $template = $this->findInActiveYear($templateId);
        if (! $template) {
            return ['status' => 'missing'];
        }

        if (! Storage::disk('local')->exists($template->file_path)) {
            return ['status' => 'file_missing'];
        }

        return [
            'status' => 'ready',
            'path' => $template->file_path,
            'name' => $this->downloadName($template),
            'document_type' => (string) $template->document_type,
            'format' => (string) $template->format,
        ];
    }

    public function destroy(string $templateId): bool
    {
        $template = $this->findInActiveYear($templateId);
        if (! $template) {
            return false;
        }

        Storage::disk('local')->delete($template->file_path);
        $template->delete();

        return true;
    }

    private function findInActiveYear(string $templateId): ?DocumentTemplate
    {
        $template = DocumentTemplate::query()->find($templateId);

        return $template && $template->fiscal_year_id === $this->context->fiscalYearId()
            ? $template
            : null;
    }

    private function downloadName(DocumentTemplate $template): string
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
    private function cachedStoredTemplateValidation(DocumentTemplate $template): array
    {
        $disk = Storage::disk('local');
        $fingerprint = 'missing';

        try {
            if ($disk->exists($template->file_path)) {
                $path = $disk->path($template->file_path);
                $fingerprint = implode(':', [
                    (string) (@filesize($path) ?: 0),
                    (string) (@filemtime($path) ?: 0),
                ]);
            }
        } catch (Throwable) {
            // validateStoredTemplate() below will return the user-facing validation error.
        }

        $contract = SpjDocumentTypeRegistry::definition((string) $template->document_type);
        $contractHash = substr(hash('sha256', json_encode($contract, JSON_UNESCAPED_UNICODE) ?: ''), 0, 16);
        $updatedAt = $template->updated_at?->format('U.u') ?? '0';
        $key = implode(':', [
            'spj-template-validation',
            (string) $template->id,
            $updatedAt,
            $fingerprint,
            $contractHash,
        ]);

        return Cache::remember($key, now()->addHours(6), fn (): array => $this->validateStoredTemplate($template));
    }

    /** @return array<string,mixed> */
    private function validateStoredTemplate(DocumentTemplate $template): array
    {
        $disk = Storage::disk('local');

        try {
            if (! $disk->exists($template->file_path)) {
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

            return $this->validator->validateFile(
                (string) $template->document_type,
                $disk->path($template->file_path),
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
