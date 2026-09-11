<?php

namespace Tests\Feature;

use Tests\TestCase;

class SyncedDataThemePrimitiveUiTest extends TestCase
{
    public function test_synced_data_workspace_uses_shared_table_and_theme_tokens(): void
    {
        $blade = file_get_contents(resource_path('views/synced-data/index.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringContainsString('<x-ui.table', $blade);
        $this->assertStringContainsString('var(--ui-surface-base)', $blade);
        $this->assertStringContainsString('var(--ui-surface-soft)', $blade);
        $this->assertStringContainsString('var(--ui-line)', $blade);
        $this->assertStringContainsString('var(--ui-fg-muted)', $blade);
        $this->assertStringContainsString('var(--theme-content-accent)', $blade);
        $this->assertStringNotContainsString('<table ', $blade);
    }

    public function test_synced_data_workspace_does_not_reintroduce_legacy_group_palette(): void
    {
        $blade = file_get_contents(resource_path('views/synced-data/index.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringNotContainsString('$groupStyles', $blade);
        $this->assertStringNotContainsString('text-slate-', $blade);
        $this->assertStringNotContainsString('bg-slate-', $blade);
        $this->assertStringNotContainsString('border-slate-', $blade);
        $this->assertStringNotContainsString('bg-indigo-', $blade);
        $this->assertStringNotContainsString('text-indigo-', $blade);
        $this->assertStringNotContainsString('bg-sky-', $blade);
        $this->assertStringNotContainsString('text-sky-', $blade);
        $this->assertStringNotContainsString('bg-violet-', $blade);
        $this->assertStringNotContainsString('text-violet-', $blade);
        $this->assertStringNotContainsString('bg-white', $blade);
    }
}
