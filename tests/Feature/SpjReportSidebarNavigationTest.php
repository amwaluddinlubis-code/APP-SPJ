<?php

namespace Tests\Feature;

use Tests\TestCase;

class SpjReportSidebarNavigationTest extends TestCase
{
    public function test_sidebar_uses_dedicated_periodic_report_navigation_partial(): void
    {
        $layout = file_get_contents(resource_path('views/components/layouts/tailwind-app.blade.php'));

        $this->assertIsString($layout);
        $this->assertStringContainsString(
            "@include('components.layouts.partials.spj-report-navigation')",
            $layout,
        );
    }

    public function test_report_navigation_exposes_parent_and_four_period_submenus(): void
    {
        $partial = file_get_contents(resource_path('views/components/layouts/partials/spj-report-navigation.blade.php'));

        $this->assertIsString($partial);
        $this->assertStringContainsString('Laporan SPJ', $partial);
        $this->assertStringContainsString('aria-controls="nav-spj-reports"', $partial);
        $this->assertStringContainsString('reportMenuOpen', $partial);
        $this->assertStringContainsString("request('paket_laporan', 'bulan')", $partial);

        foreach (['bulan', 'triwulan', 'semester', 'tahunan'] as $scope) {
            $this->assertStringContainsString("'key' => '{$scope}'", $partial);
        }

        foreach (['Bulanan', 'Tahap & Triwulan', 'Semester', 'Tahunan'] as $label) {
            $this->assertStringContainsString("'label' => '{$label}'", $partial);
        }

        $this->assertStringContainsString("'tab' => 'laporan'", $partial);
        $this->assertStringContainsString("'paket_laporan' => \$reportScope['key']", $partial);
    }
}
