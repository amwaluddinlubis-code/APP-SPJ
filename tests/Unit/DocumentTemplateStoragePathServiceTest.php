<?php

namespace Tests\Unit;

use App\Models\School;
use App\Services\DocumentTemplateStoragePathService;
use App\Support\ActiveSpjContext;
use Tests\TestCase;

class DocumentTemplateStoragePathServiceTest extends TestCase
{
    public function test_template_directory_is_namespaced_by_school_npsn_and_fiscal_year(): void
    {
        $school = School::query()->create([
            'npsn' => '10208183',
            'name' => 'Sekolah Path Test',
        ]);
        $context = new ActiveSpjContext($school->id, 2026);

        $service = new DocumentTemplateStoragePathService($context);

        $this->assertSame('document-templates/10208183/2026', $service->directory(2026));
        $this->assertSame('document-templates/10208183/2026/package', $service->directory(2026, 'package'));
    }
}
