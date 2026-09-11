<?php

namespace Tests\Feature;

use App\Http\Controllers\DocumentTemplateController;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DocumentTemplateUploadRoutingRegressionTest extends TestCase
{
    public function test_explicit_package_mode_stays_on_package_validation_when_post_body_is_empty(): void
    {
        $request = Request::create('/pengaturan/template-dokumen?upload=package', 'POST');
        $request->setLaravelSession(app('session')->driver());

        try {
            app()->call([app(DocumentTemplateController::class), 'store'], ['request' => $request]);
            $this->fail('Package upload without body should fail package validation.');
        } catch (ValidationException $exception) {
            $this->assertSame('templatePackageUpload', $exception->errorBag);
            $this->assertArrayHasKey('template_package', $exception->errors());
            $this->assertArrayNotHasKey('document_type', $exception->errors());
            $this->assertArrayNotHasKey('template', $exception->errors());
        }
    }

    public function test_explicit_single_mode_stays_on_single_template_validation_when_post_body_is_empty(): void
    {
        $request = Request::create('/pengaturan/template-dokumen?upload=single', 'POST');
        $request->setLaravelSession(app('session')->driver());

        try {
            app()->call([app(DocumentTemplateController::class), 'store'], ['request' => $request]);
            $this->fail('Single upload without body should fail single-template validation.');
        } catch (ValidationException $exception) {
            $this->assertSame('templateUpload', $exception->errorBag);
            $this->assertArrayHasKey('document_type', $exception->errors());
            $this->assertArrayHasKey('name', $exception->errors());
            $this->assertArrayHasKey('template', $exception->errors());
            $this->assertArrayNotHasKey('template_package', $exception->errors());
        }
    }

    public function test_upload_page_uses_explicit_modes_and_separate_error_bags(): void
    {
        $source = file_get_contents(resource_path('views/document-templates/index.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString("['upload' => 'package']", $source);
        $this->assertStringContainsString("['upload' => 'single']", $source);
        $this->assertStringContainsString("getBag('templatePackageUpload')", $source);
        $this->assertStringContainsString("getBag('templateUpload')", $source);
        $this->assertStringContainsString('upload_max_filesize', $source);
        $this->assertStringContainsString('post_max_size', $source);
    }
}
