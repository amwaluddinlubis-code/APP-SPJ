<?php

namespace App\UseCases\DocumentTemplates;

use App\Services\SpjTemplatePackageImporter;
use App\Support\ActiveSpjContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ImportDocumentTemplatePackageUseCase
{
    public function __construct(
        private readonly SpjTemplatePackageImporter $importer,
        private readonly ActiveSpjContext $context,
    ) {}

    /** @return array{imported:int,replaced:int,warnings:array<int,string>} */
    public function handle(UploadedFile $uploaded, bool $replaceExisting): array
    {
        $temporaryPath = $uploaded->getRealPath();
        if (! is_string($temporaryPath) || $temporaryPath === '') {
            throw ValidationException::withMessages([
                'template_package' => 'Workbook paket template tidak dapat dibaca.',
            ]);
        }

        try {
            $result = $this->importer->importPackage(
                $this->context->fiscalYearId(),
                $temporaryPath,
                $replaceExisting,
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

        return [
            'imported' => (int) ($result['imported'] ?? 0),
            'replaced' => (int) ($result['replaced'] ?? 0),
            'warnings' => collect($result['warnings'] ?? [])
                ->map(fn (array $issue): string => '['.$issue['document_type'].'] '.$issue['message'])
                ->values()
                ->all(),
        ];
    }
}
