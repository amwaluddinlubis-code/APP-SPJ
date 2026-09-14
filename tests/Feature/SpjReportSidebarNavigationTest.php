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
        $this->assertStringContainsString("request()->routeIs('spj.periodic-reports.*')", $partial);
        $this->assertStringContainsString("request('paket_laporan', 'bulan')", $partial);

        foreach (['bulan', 'triwulan', 'semester', 'tahunan'] as $scope) {
            $this->assertStringContainsString("'key' => '{$scope}'", $partial);
        }

        foreach (['Bulanan', 'Triwulan', 'Semester', 'Tahunan'] as $label) {
            $this->assertStringContainsString("'label' => '{$label}'", $partial);
        }

        $this->assertStringContainsString("route('spj.periodic-reports.index'", $partial);
        $this->assertStringContainsString("'paket_laporan' => \$reportScope['key']", $partial);
        $this->assertStringNotContainsString("'jenis_laporan' => 'periode'", $partial);
    }

    public function test_periodic_report_has_its_own_route_and_page(): void
    {
        $routes = file_get_contents(base_path('routes/web.php'));
        $periodicView = file_get_contents(resource_path('views/periodic-reports/index.blade.php'));
        $component = file_get_contents(app_path('Livewire/SpjReportFilter.php'));
        $spjReport = file_get_contents(resource_path('views/livewire/spj-report-filter.blade.php'));

        $this->assertIsString($routes);
        $this->assertIsString($periodicView);
        $this->assertIsString($component);
        $this->assertIsString($spjReport);

        $this->assertStringContainsString("Route::view('/laporan-periode', 'periodic-reports.index')", $routes);
        $this->assertStringContainsString("name('spj.periodic-reports.index')", $routes);
        $this->assertStringContainsString('<livewire:spj-periodic-report-center', $periodicView);
        $this->assertStringNotContainsString('reportSurface', $component);
        $this->assertStringNotContainsString('jenis_laporan', $component);
        $this->assertStringNotContainsString('<livewire:spj-periodic-report-center', $spjReport);
    }
}
