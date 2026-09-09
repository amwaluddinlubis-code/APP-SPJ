<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use App\Models\School;
use App\Models\SpjPackage;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class SpjTemplateRenderPreflight
{
    public function __construct(
        private readonly SpjTemplateService $templates,
        private readonly SpjGeneratedDocumentValidator $outputs,
    ) {}

    public function assertRenderable(DocumentTemplate $template, SpjPackage $package, School $school): void
    {
        $response = $this->templates->download($template, $package, $school);
        if (! $response instanceof BinaryFileResponse) {
            throw new \RuntimeException('Validasi hasil generate gagal karena response dokumen tidak dikenali.');
        }

        $path = $response->getFile()->getPathname();
        try {
            $this->outputs->assertBinaryResponse(
                $response,
                (string) $template->format,
                'Dokumen '.(string) $template->document_type,
                (string) $template->document_type,
            );
        } finally {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    /** @param Collection<int, DocumentTemplate> $templates */
    public function assertAllRenderable(Collection $templates, SpjPackage $package, School $school): void
    {
        foreach ($templates as $template) {
            $this->assertRenderable($template, $package, $school);
        }
    }
}
