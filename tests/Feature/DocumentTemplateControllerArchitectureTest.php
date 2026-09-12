<?php

namespace Tests\Feature;

use App\Services\DocumentTemplateSampleGenerator;
use Tests\TestCase;

class DocumentTemplateControllerArchitectureTest extends TestCase
{
    public function test_controller_is_an_http_adapter_for_template_workflows(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/DocumentTemplateController.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('UploadDocumentTemplateUseCase', $source);
        $this->assertStringContainsString('ImportDocumentTemplatePackageUseCase', $source);
        $this->assertStringContainsString('DocumentTemplateSampleGenerator', $source);
        $this->assertStringContainsString('DocumentTemplateLibraryService', $source);
        $this->assertStringContainsString('DocumentTemplateMasterExportService', $source);

        foreach ([
            'DocumentTemplateReplacementService',
            'SpjTemplatePackageImporter',
            'SpjTemplateValidator',
            'new Spreadsheet',
            'new PhpWord',
            'IOFactory as WordWriter',
            'DocumentTemplate::query()',
            "session('",
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $source, $forbidden);
        }
    }

    public function test_upload_use_case_owns_validation_and_atomic_replacement_orchestration(): void
    {
        $source = file_get_contents(app_path('UseCases/DocumentTemplates/UploadDocumentTemplateUseCase.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('SpjTemplateValidator', $source);
        $this->assertStringContainsString('DocumentTemplateReplacementService', $source);
        $this->assertStringContainsString('validateFile(', $source);
        $this->assertStringContainsString('$this->replacement->replace(', $source);
        $this->assertStringContainsString('$this->context->fiscalYearId()', $source);
        $this->assertStringNotContainsString("session('", $source);
    }

    public function test_package_import_orchestration_lives_outside_controller(): void
    {
        $source = file_get_contents(app_path('UseCases/DocumentTemplates/ImportDocumentTemplatePackageUseCase.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('SpjTemplatePackageImporter', $source);
        $this->assertStringContainsString('$this->importer->importPackage(', $source);
        $this->assertStringContainsString('$this->context->fiscalYearId()', $source);
        $this->assertStringNotContainsString("session('", $source);
    }

    public function test_sample_generator_still_creates_both_supported_artifacts(): void
    {
        $generator = app(DocumentTemplateSampleGenerator::class);
        $paths = [];

        try {
            foreach (['docx', 'xlsx'] as $format) {
                $sample = $generator->generate($format);
                $paths[] = $sample['path'];

                $this->assertFileExists($sample['path']);
                $this->assertSame('CONTOH-TEMPLATE-SPJ.'.$format, $sample['download_name']);
                $this->assertGreaterThan(0, filesize($sample['path']));
            }
        } finally {
            foreach ($paths as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
    }
}
