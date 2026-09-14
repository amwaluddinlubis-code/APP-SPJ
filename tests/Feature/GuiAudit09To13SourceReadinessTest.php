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
        $component = file_get_contents(resource_path('views/livewire/database-reset-form.blade.php'));

        $this->assertIsString($blade);
        $this->assertIsString($component);
        $this->assertStringContainsString('<livewire:database-reset-form', $blade);
        $this->assertStringContainsString('<x-ui.button type="submit" variant="danger"', $component);
        $this->assertStringContainsString('<x-ui.danger-zone', $component);
        $this->assertStringContainsString('var(--ui-fg-muted)', $component);
        $this->assertStringContainsString('var(--ui-surface-base)', $component);
        $this->assertStringNotContainsString('text-slate-', $component);
        $this->assertStringNotContainsString('text-indigo-', $component);
        $this->assertStringNotContainsString('bg-slate-', $component);
    }

    public function test_database_table_explorer_has_one_standard_pagination_control(): void
    {
        $blade = file_get_contents(resource_path('views/database-manager/index.blade.php'));
        $partial = file_get_contents(resource_path('views/database-manager/partials/tables.blade.php'));
        $component = file_get_contents(resource_path('views/livewire/database-table-explorer.blade.php'));
        $css = file_get_contents(resource_path('css/ui-generalization.css'));

        $this->assertIsString($blade);
        $this->assertIsString($partial);
        $this->assertIsString($component);
        $this->assertIsString($css);
        $this->assertStringContainsString("@include('database-manager.partials.tables')", $blade);
        $this->assertStringContainsString('<livewire:database-table-explorer', $partial);
        $this->assertStringContainsString('wire:model.live.debounce.250ms="search"', $component);
        $this->assertStringContainsString('wire:click="setPage(', $component);
        $this->assertStringContainsString('.ui-pagination-control:first-child', $css);
        $this->assertStringContainsString('.ui-pagination-control:last-child', $css);
    }

    public function test_database_manager_summary_and_tabs_are_livewire_consumers(): void
    {
        $blade = file_get_contents(resource_path('views/database-manager/index.blade.php'));
        $tabs = file_get_contents(resource_path('views/livewire/database-manager-tabs.blade.php'));
        $summary = file_get_contents(resource_path('views/livewire/database-status-summary.blade.php'));

        $this->assertIsString($blade);
        $this->assertIsString($tabs);
        $this->assertIsString($summary);
        $this->assertStringContainsString('<livewire:database-status-summary', $blade);
        $this->assertStringContainsString('<livewire:database-manager-tabs', $blade);
        $this->assertStringContainsString('wire:click="selectTab(', $tabs);
        $this->assertStringContainsString('data-livewire-summary="true"', $summary);
    }

    public function test_database_diagnostics_is_a_livewire_read_only_panel(): void
    {
        $blade = file_get_contents(resource_path('views/database-manager/index.blade.php'));
        $partial = file_get_contents(resource_path('views/database-manager/partials/diagnostics.blade.php'));
        $component = file_get_contents(resource_path('views/livewire/database-diagnostics.blade.php'));

        $this->assertIsString($blade);
        $this->assertIsString($partial);
        $this->assertIsString($component);
        $this->assertStringContainsString("@include('database-manager.partials.diagnostics')", $blade);
        $this->assertStringContainsString('<livewire:database-diagnostics', $partial);
        $this->assertStringContainsString('data-livewire-diagnostics="true"', $component);
        $this->assertStringContainsString('Jalankan integrity check', $component);
        $this->assertStringContainsString('tableCounts', $component);
    }

    public function test_database_school_maintenance_and_reset_use_livewire_consumers(): void
    {
        $index = file_get_contents(resource_path('views/database-manager/index.blade.php'));
        $schoolPartial = file_get_contents(resource_path('views/database-manager/partials/school-list.blade.php'));
        $maintenancePartial = file_get_contents(resource_path('views/database-manager/partials/maintenance.blade.php'));
        $schoolList = file_get_contents(resource_path('views/livewire/database-school-list.blade.php'));
        $maintenance = file_get_contents(resource_path('views/livewire/database-maintenance.blade.php'));
        $reset = file_get_contents(resource_path('views/livewire/database-reset-form.blade.php'));

        $this->assertIsString($index);
        $this->assertIsString($schoolPartial);
        $this->assertIsString($maintenancePartial);
        $this->assertIsString($schoolList);
        $this->assertIsString($maintenance);
        $this->assertIsString($reset);
        $this->assertStringContainsString("@include('database-manager.partials.school-list')", $index);
        $this->assertStringContainsString("@include('database-manager.partials.maintenance')", $index);
        $this->assertStringContainsString('<livewire:database-school-list', $schoolPartial);
        $this->assertStringContainsString('<livewire:database-maintenance', $maintenancePartial);
        $this->assertStringContainsString('wire:model.live.debounce.250ms="search"', $schoolList);
        $this->assertStringContainsString('wire:click="run(', $maintenance);
        $this->assertStringContainsString('wire:submit="resetDatabase"', $reset);
    }

    public function test_database_overview_is_a_livewire_panel(): void
    {
        $blade = file_get_contents(resource_path('views/database-manager/index.blade.php'));
        $partial = file_get_contents(resource_path('views/database-manager/partials/overview.blade.php'));
        $overview = file_get_contents(resource_path('views/livewire/database-overview.blade.php'));

        $this->assertIsString($blade);
        $this->assertIsString($partial);
        $this->assertIsString($overview);
        $this->assertStringContainsString("@include('database-manager.partials.overview')", $blade);
        $this->assertStringContainsString('<livewire:database-overview', $partial);
        $this->assertStringContainsString('data-livewire-overview="true"', $overview);
        $this->assertStringContainsString('Aksi cepat', $overview);
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

    public function test_global_layout_uses_canonical_icons_without_legacy_navigation_symbols(): void
    {
        $layout = file_get_contents(resource_path('views/components/layouts/tailwind-app.blade.php'));

        $this->assertIsString($layout);
        $this->assertStringContainsString('<x-ui.icon', $layout);
        $this->assertStringNotContainsString('<x-ui-icon', $layout);
        $this->assertStringNotContainsString('№', $layout);
        $this->assertStringNotContainsString('↺', $layout);
        $this->assertStringNotContainsString('◎', $layout);
    }

    public function test_scroll_to_top_uses_theme_tokens_in_authenticated_layout_css(): void
    {
        $css = file_get_contents(resource_path('css/layout-token-native.css'));

        $this->assertIsString($css);
        $this->assertStringContainsString('#app-scroll-to-top', $css);
        $this->assertStringContainsString('var(--ui-component-surface', $css);
        $this->assertStringContainsString('var(--ui-component-text', $css);
        $this->assertStringContainsString('var(--theme-accent)', $css);
    }

    public function test_spj_density_pilot_stays_compact_without_reducing_touch_targets(): void
    {
        $css = file_get_contents(resource_path('css/spj-workspace-standardization.css'));
        $summary = file_get_contents(resource_path('views/spj/partials/summary.blade.php'));

        $this->assertIsString($css);
        $this->assertIsString($summary);
        $this->assertStringContainsString('spj-work-summary', $summary);
        $this->assertStringContainsString('--profile-control-height: 2.5rem;', $css);
        $this->assertStringContainsString('min-height: 0 !important;', $css);
        $this->assertStringContainsString('.page-header-summary', $css);
        $this->assertStringContainsString('@media (max-width: 1023px)', $css);
        $this->assertStringContainsString('--profile-control-height: 2.75rem;', $css);
        $this->assertStringContainsString('min-height: 2.75rem;', $css);
    }

    public function test_core_operator_lists_keep_desktop_and_mobile_source_fallbacks(): void
    {
        $employees = file_get_contents(resource_path('views/employees/index.blade.php'));
        $employeeComponent = file_get_contents(resource_path('views/livewire/employee-directory.blade.php'));
        $students = file_get_contents(resource_path('views/students/index.blade.php'));
        $spj = file_get_contents(resource_path('views/spj/index.blade.php'));
        $spjLivewire = implode("\n", array_map(
            fn ($view): string => (string) file_get_contents(resource_path('views/livewire/'.$view)),
            ['spj-preparation-filter.blade.php', 'spj-package-list.blade.php', 'spj-report-filter.blade.php', 'spj-monitoring-list.blade.php']
        ));

        $this->assertIsString($employees);
        $this->assertIsString($employeeComponent);
        $this->assertIsString($students);
        $this->assertIsString($spj);

        $this->assertStringContainsString('<livewire:employee-directory', $employees);
        $this->assertStringContainsString('wire:model.live.debounce.300ms="search"', $employeeComponent);
        $this->assertStringContainsString('hidden md:block', $students);
        $this->assertStringContainsString('md:hidden', $students);
        $this->assertStringContainsString('lg:hidden', $spj.$spjLivewire);
        $this->assertStringContainsString('overflow-x-auto', $spj.$spjLivewire);
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
