<?php

namespace Tests\Feature;

use Tests\TestCase;

class SpjPeriodicReportModuleUiTest extends TestCase
{
    public function test_periodic_report_center_is_mounted_inside_the_spj_report_tab(): void
    {
        $blade = file_get_contents(resource_path('views/livewire/spj-report-filter.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringContainsString('<livewire:spj-periodic-report-center', $blade);
    }

    public function test_periodic_report_center_keeps_template_binding_separate_from_the_report_contract(): void
    {
        $blade = file_get_contents(resource_path('views/livewire/spj-periodic-report-center.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringContainsString('Pusat Laporan Pertanggungjawaban', $blade);
        $this->assertStringContainsString('Paket laporan periodik', $blade);
        $this->assertStringContainsString('Formula serta tata letak resmi tiap dokumen akan mengikuti template laporan yang dipasang kemudian.', $blade);
        $this->assertStringNotContainsString('route(\'spj.periodic-report.export', $blade);
    }

    public function test_periodic_report_center_uses_shared_theme_primitives_without_local_css(): void
    {
        $blade = file_get_contents(resource_path('views/livewire/spj-periodic-report-center.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringNotContainsString('<style>', $blade);
        $this->assertStringContainsString('<x-ui.select', $blade);
        $this->assertStringContainsString('var(--ui-surface-base)', $blade);
        $this->assertStringContainsString('var(--ui-line)', $blade);
        $this->assertStringContainsString('var(--ui-fg-muted)', $blade);
    }
}
