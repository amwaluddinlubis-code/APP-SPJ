<?php

namespace Tests\Feature;

use Tests\TestCase;

class EmployeeIndexThemePrimitiveUiTest extends TestCase
{
    public function test_employee_index_delegates_to_livewire_directory_without_dead_markup(): void
    {
        $blade = file_get_contents(resource_path('views/employees/index.blade.php'));
        $directory = file_get_contents(resource_path('views/livewire/employee-directory.blade.php'));

        $this->assertIsString($blade);
        $this->assertIsString($directory);
        $this->assertStringContainsString('<livewire:employee-directory />', $blade);
        $this->assertStringNotContainsString('class="hidden', $blade);
        $this->assertStringContainsString('<x-ui.button', $directory);
        $this->assertStringContainsString('var(--ui-surface-base)', $directory);
        $this->assertStringContainsString('var(--ui-line)', $directory);
        $this->assertStringContainsString('var(--ui-fg-muted)', $directory);
        $this->assertStringNotContainsString('<table ', $directory);
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
