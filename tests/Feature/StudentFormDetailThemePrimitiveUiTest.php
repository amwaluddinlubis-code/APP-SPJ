<?php

namespace Tests\Feature;

use Tests\TestCase;

class StudentFormDetailThemePrimitiveUiTest extends TestCase
{
    public function test_student_form_uses_theme_tokens_and_sticky_action_primitive(): void
    {
        $blade = file_get_contents(resource_path('views/students/form.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringContainsString('<x-ui.sticky-actions', $blade);
        $this->assertStringContainsString('var(--ui-surface-soft)', $blade);
        $this->assertStringContainsString('var(--ui-surface-muted)', $blade);
        $this->assertStringContainsString('var(--ui-fg-muted)', $blade);
        $this->assertStringNotContainsString('text-slate-', $blade);
        $this->assertStringNotContainsString('border-slate-', $blade);
        $this->assertStringNotContainsString('bg-slate-', $blade);
        $this->assertStringNotContainsString('bg-white', $blade);
        $this->assertStringNotContainsString('text-indigo-', $blade);
    }

    public function test_student_detail_uses_detail_primitives_and_theme_tokens(): void
    {
        $blade = file_get_contents(resource_path('views/students/show.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringContainsString('<x-ui.detail-list', $blade);
        $this->assertStringContainsString('<x-ui.detail-item', $blade);
        $this->assertStringContainsString('var(--ui-surface-base)', $blade);
        $this->assertStringContainsString('var(--ui-surface-soft)', $blade);
        $this->assertStringContainsString('var(--ui-line)', $blade);
        $this->assertStringContainsString('var(--ui-fg-muted)', $blade);
        $this->assertStringNotContainsString('text-slate-', $blade);
        $this->assertStringNotContainsString('border-slate-', $blade);
        $this->assertStringNotContainsString('bg-slate-', $blade);
        $this->assertStringNotContainsString('bg-white', $blade);
    }
}
