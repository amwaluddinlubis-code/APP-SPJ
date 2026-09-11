<?php

namespace Tests\Feature;

use Tests\TestCase;

class GuiAudit09To13SourceReadinessTest extends TestCase
{
    public function test_spj_main_tabs_use_canonical_icons_instead_of_emoji_labels(): void
    {
        $tabs = file_get_contents(resource_path('views/components/tabs.blade.php'));
        $spjTabs = file_get_contents(resource_path('views/spj/partials/main-tabs.blade.php'));

        $this->assertIsString($tabs);
        $this->assertIsString($spjTabs);
        $this->assertStringContainsString('<x-ui.icon', $tabs);
        $this->assertStringContainsString("'icon' => 'archive'", $spjTabs);
        $this->assertStringContainsString("'icon' => 'document'", $spjTabs);
        $this->assertStringContainsString("'icon' => 'report'", $spjTabs);
        $this->assertStringContainsString("'icon' => 'warning'", $spjTabs);
        $this->assertStringNotContainsString('📦', $spjTabs);
        $this->assertStringNotContainsString('📄', $spjTabs);
        $this->assertStringNotContainsString('📊', $spjTabs);
        $this->assertStringNotContainsString('⚠️', $spjTabs);
    }

    public function test_database_reset_page_uses_shared_actions_and_theme_tokens(): void
    {
        $blade = file_get_contents(resource_path('views/database-manager/reset.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringContainsString('<x-ui.button type="submit" variant="danger">', $blade);
        $this->assertStringContainsString('<x-ui.button variant="secondary"', $blade);
        $this->assertStringContainsString('<x-ui.icon name="warning"', $blade);
        $this->assertStringContainsString('var(--ui-fg-muted)', $blade);
        $this->assertStringContainsString('var(--ui-surface-base)', $blade);
        $this->assertStringNotContainsString('text-slate-', $blade);
        $this->assertStringNotContainsString('text-indigo-', $blade);
        $this->assertStringNotContainsString('bg-slate-', $blade);
    }

    public function test_legacy_icon_component_is_only_a_compatibility_adapter(): void
    {
        $legacy = file_get_contents(resource_path('views/components/ui-icon.blade.php'));
        $canonical = file_get_contents(resource_path('views/components/ui/icon.blade.php'));

        $this->assertIsString($legacy);
        $this->assertIsString($canonical);
        $this->assertStringContainsString('<x-ui.icon :name="$name"', $legacy);
        $this->assertStringNotContainsString('<svg', $legacy);
        foreach (['dashboard', 'transaction', 'tax', 'report', 'archive', 'number', 'database', 'users'] as $name) {
            $this->assertStringContainsString("'{$name}' =>", $canonical);
        }
    }

    public function test_core_operator_lists_keep_desktop_and_mobile_source_fallbacks(): void
    {
        $employees = file_get_contents(resource_path('views/employees/index.blade.php'));
        $students = file_get_contents(resource_path('views/students/index.blade.php'));
        $spj = file_get_contents(resource_path('views/spj/index.blade.php'));

        $this->assertIsString($employees);
        $this->assertIsString($students);
        $this->assertIsString($spj);

        $this->assertStringContainsString('hidden md:block', $employees);
        $this->assertStringContainsString('md:hidden', $employees);
        $this->assertStringContainsString('hidden md:block', $students);
        $this->assertStringContainsString('md:hidden', $students);
        $this->assertStringContainsString('lg:hidden', $spj);
        $this->assertStringContainsString('overflow-x-auto', $spj);
    }

    public function test_wide_data_views_keep_horizontal_overflow_or_shared_table_contracts(): void
    {
        $syncedData = file_get_contents(resource_path('views/synced-data/index.blade.php'));
        $numbering = file_get_contents(resource_path('views/spj/numbering.blade.php'));

        $this->assertIsString($syncedData);
        $this->assertIsString($numbering);
        $this->assertStringContainsString('<x-ui.table', $syncedData);
        $this->assertTrue(
            str_contains($numbering, '<x-ui.table') || str_contains($numbering, 'overflow-x-auto'),
            'Numbering workspace must retain a horizontally safe table contract.'
        );
    }
}
