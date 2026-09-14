<?php

namespace Tests\Feature;

use Tests\TestCase;

class SpjReportSidebarNavigationTest extends TestCase
{
    public function test_sidebar_uses_dedicated_report_navigation_partial(): void
    {
        $layout = file_get_contents(resource_path('views/components/layouts/tailwind-app.blade.php'));

        $this->assertIsString($layout);
        $this->assertStringContainsString(
            "@include('components.layouts.partials.spj-report-navigation')",
            $layout,
        );
    }

    public function test_spj_report_and_periodic_report_are_separate_sidebar_entries(): void
    {
        $partial = file_get_contents(resource_path('views/components/layouts/partials/spj-report-navigation.blade.php'));

        $this->assertIsString($partial);
        $this->assertStringContainsString('>Laporan SPJ</span>', $partial);
        $this->assertStringContainsString('>Laporan Periode</span>', $partial);
        $this->assertStringContainsString('aria-controls="nav-periodic-reports"', $partial);
        $this->assertStringContainsString('reportMenuOpen', $partial);
        $this->assertStringContainsString("request('jenis_laporan') === 'periode'", $partial);
        $this->assertStringContainsString("request('paket_laporan', 'bulan')", $partial);

        foreach (['bulan', 'triwulan', 'semester', 'tahunan'] as $scope) {
            $this->assertStringContainsString("'key' => '{$scope}'", $partial);
        }

        foreach (['Bulanan', 'Triwulan', 'Semester', 'Tahunan'] as $label) {
            $this->assertStringContainsString("'label' => '{$label}'", $partial);
        }

        $this->assertStringContainsString("'tab' => 'laporan'", $partial);
        $this->assertStringContainsString("'jenis_laporan' => 'periode'", $partial);
        $this->assertStringContainsString("'paket_laporan' => \$reportScope['key']", $partial);
    }

    public function test_periodic_report_uses_a_dedicated_surface_instead_of_spj_report_history(): void
    {
        $component = file_get_contents(app_path('Livewire/SpjReportFilter.php'));
        $periodicView = file_get_contents(resource_path('views/livewire/spj-periodic-report-page.blade.php'));
        $spjReport = file_get_contents(resource_path('views/livewire/spj-report-filter.blade.php'));

        $this->assertIsString($component);
        $this->assertIsString($periodicView);
        $this->assertIsString($spjReport);
        $this->assertStringContainsString("#[Url(as: 'jenis_laporan', except: null)]", $component);
        $this->assertStringContainsString("\$this->reportSurface === 'periode'", $component);
        $this->assertStringContainsString("view('livewire.spj-periodic-report-page')", $component);
        $this->assertStringContainsString('<livewire:spj-periodic-report-center', $periodicView);
        $this->assertStringNotContainsString('<livewire:spj-periodic-report-center', $spjReport);
    }
}
