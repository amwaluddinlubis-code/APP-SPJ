<?php

namespace Tests\Feature;

use Tests\TestCase;

class StudentIndexThemePrimitiveUiTest extends TestCase
{
    public function test_student_index_uses_shared_table_and_theme_tokens(): void
    {
        $blade = file_get_contents(resource_path('views/students/index.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringContainsString('<x-ui.table', $blade);
        $this->assertStringContainsString('var(--ui-surface-base)', $blade);
        $this->assertStringContainsString('var(--ui-surface-soft)', $blade);
        $this->assertStringContainsString('var(--ui-line)', $blade);
        $this->assertStringContainsString('var(--ui-fg-muted)', $blade);
        $this->assertStringNotContainsString('<table class=', $blade);
    }

    public function test_student_index_does_not_reintroduce_legacy_non_semantic_palette(): void
    {
        $blade = file_get_contents(resource_path('views/students/index.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringNotContainsString('text-slate-', $blade);
        $this->assertStringNotContainsString('border-slate-', $blade);
        $this->assertStringNotContainsString('bg-slate-', $blade);
        $this->assertStringNotContainsString('bg-white', $blade);
        $this->assertStringNotContainsString('text-indigo-', $blade);
    }
}
