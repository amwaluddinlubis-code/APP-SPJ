<?php

namespace Tests\Feature;

use Tests\TestCase;

class AuditReportThemePrimitiveUiTest extends TestCase
{
    public function test_audit_report_uses_theme_primitives_instead_of_local_style_overrides(): void
    {
        $blade = file_get_contents(resource_path('views/audit-reports/index.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringNotContainsString('<style>', $blade);
        $this->assertStringNotContainsString('class="audit-table', $blade);
        $this->assertStringContainsString('<x-ui.table', $blade);
        $this->assertStringContainsString('var(--ui-surface-base)', $blade);
        $this->assertStringContainsString('var(--ui-line)', $blade);
        $this->assertStringContainsString('var(--ui-fg-muted)', $blade);
        $this->assertStringContainsString('ui-btn ui-btn-primary', $blade);
    }

    public function test_all_audit_report_data_tables_use_the_shared_table_primitive(): void
    {
        $blade = file_get_contents(resource_path('views/audit-reports/index.blade.php'));

        $this->assertIsString($blade);
        $this->assertSame(5, substr_count($blade, '<x-ui.table'));
        $this->assertStringNotContainsString('<table class=', $blade);
    }
}
