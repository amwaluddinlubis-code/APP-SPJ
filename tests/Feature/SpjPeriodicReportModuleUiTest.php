<?php

namespace Tests\Feature;

use Tests\TestCase;

class SpjPeriodicReportModuleUiTest extends TestCase
{
    public function test_periodic_report_center_uses_a_dedicated_surface_separate_from_spj_report_history(): void
    {
        $spjReport = file_get_contents(resource_path('views/livewire/spj-report-filter.blade.php'));
        $periodicPage = file_get_contents(resource_path('views/livewire/spj-periodic-report-page.blade.php'));
        $component = file_get_contents(app_path('Livewire/SpjReportFilter.php'));

        $this->assertIsString($spjReport);
        $this->assertIsString($periodicPage);
        $this->assertIsString($component);
        $this->assertStringNotContainsString('<livewire:spj-periodic-report-center', $spjReport);
        $this->assertStringContainsString('<livewire:spj-periodic-report-center', $periodicPage);
        $this->assertStringContainsString("request('jenis_laporan') === 'periode'", $component);
        $this->assertStringContainsString("view('livewire.spj-periodic-report-page')", $component);
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
