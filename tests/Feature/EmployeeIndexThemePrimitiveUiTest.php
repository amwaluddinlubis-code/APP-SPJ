<?php

namespace Tests\Feature;

use Tests\TestCase;

class EmployeeIndexThemePrimitiveUiTest extends TestCase
{
    public function test_employee_index_uses_shared_table_badges_buttons_and_theme_tokens(): void
    {
        $blade = file_get_contents(resource_path('views/employees/index.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringContainsString('<x-ui.table', $blade);
        $this->assertStringContainsString('<x-ui.badge', $blade);
        $this->assertStringContainsString('<x-ui.button', $blade);
        $this->assertStringContainsString('var(--ui-surface-base)', $blade);
        $this->assertStringContainsString('var(--ui-surface-soft)', $blade);
        $this->assertStringContainsString('var(--ui-line)', $blade);
        $this->assertStringContainsString('var(--ui-fg-muted)', $blade);
        $this->assertStringNotContainsString('<table ', $blade);
    }

    public function test_employee_index_does_not_reintroduce_legacy_non_semantic_palette(): void
    {
        $blade = file_get_contents(resource_path('views/employees/index.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringNotContainsString('text-slate-', $blade);
        $this->assertStringNotContainsString('border-slate-', $blade);
        $this->assertStringNotContainsString('bg-slate-', $blade);
        $this->assertStringNotContainsString('bg-white', $blade);
        $this->assertStringNotContainsString('text-indigo-', $blade);
        $this->assertStringNotContainsString('text-sky-', $blade);
        $this->assertStringNotContainsString('text-amber-', $blade);
    }
}
