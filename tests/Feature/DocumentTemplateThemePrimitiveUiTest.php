<?php

namespace Tests\Feature;

use Tests\TestCase;

class DocumentTemplateThemePrimitiveUiTest extends TestCase
{
    public function test_document_template_index_uses_theme_tokens_instead_of_hardcoded_palette(): void
    {
        $blade = file_get_contents(resource_path('views/document-templates/index.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringNotContainsString('<style>', $blade);
        $this->assertStringNotContainsString('text-slate-', $blade);
        $this->assertStringNotContainsString('bg-slate-', $blade);
        $this->assertStringNotContainsString('border-slate-', $blade);
        $this->assertStringNotContainsString('text-indigo-', $blade);
        $this->assertStringNotContainsString('text-violet-', $blade);
        $this->assertStringNotContainsString('bg-violet-', $blade);
        $this->assertStringNotContainsString('bg-white', $blade);
        $this->assertStringContainsString('var(--ui-surface-base)', $blade);
        $this->assertStringContainsString('var(--ui-surface-soft)', $blade);
        $this->assertStringContainsString('var(--ui-fg-muted)', $blade);
        $this->assertStringContainsString('var(--theme-content-accent)', $blade);
    }
}
