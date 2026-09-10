<?php

namespace App\UseCases\DocumentTemplates;

use App\Models\DocumentTemplate;
use App\Services\DocumentTemplateReplacementService;
use App\Services\SpjTemplateValidator;
use App\Support\ActiveSpjContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Throwable;

final class UploadDocumentTemplateUseCase
{
    public function __construct(
        private readonly SpjTemplateValidator $validator,
        private readonly DocumentTemplateReplacementService $replacement,
        private readonly ActiveSpjContext $context,
    ) {}

    /**
     * @param  array<int, string>  $applicableCategories
     * @return array{template:DocumentTemplate,warnings:array<int,string>}
     */
    public function handle(
        string $documentType,
        string $name,
        UploadedFile $uploaded,
        array $applicableCategories = [],
    ): array {
        $documentType = strtoupper($documentType);
        $extension = strtolower($uploaded->getClientOriginalExtension());
        $temporaryPath = $uploaded->getRealPath();

        if (! is_string($temporaryPath) || $temporaryPath === '') {
            throw ValidationException::withMessages([
                'template' => 'File template tidak dapat dibaca untuk proses validasi.',
            ]);
        }

        try {
            $validation = $this->validator->validateFile($documentType, $temporaryPath, $extension);
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

        $template = $this->replacement->replace(
            $this->context->fiscalYearId(),
            $documentType,
            $name,
            $uploaded,
            $extension,
            $applicableCategories,
        );

        return [
            'template' => $template,
            'warnings' => collect($validation['warnings'])
                ->pluck('message')
                ->filter()
                ->values()
                ->all(),
        ];
    }
}
