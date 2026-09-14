<?php

namespace Tests\Feature;

use App\Services\SpjPeriodicReportRegistry;
use Tests\TestCase;

class SpjPeriodicReportPrintableTest extends TestCase
{
    public function test_periodic_report_routes_expose_browser_print_and_pdf(): void
    {
        $routes = file_get_contents(base_path('routes/web.php'));

        $this->assertIsString($routes);
        $this->assertStringContainsString('use App\\Http\\Controllers\\PeriodicReportController;', $routes);
        $this->assertStringContainsString("->name('spj.periodic-reports.print')", $routes);
        $this->assertStringContainsString("->name('spj.periodic-reports.pdf')", $routes);
    }

    public function test_periodic_report_center_exposes_print_and_pdf_actions_only_after_period_is_ready(): void
    {
        $blade = file_get_contents(resource_path('views/livewire/spj-periodic-report-center.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringContainsString("route('spj.periodic-reports.print'", $blade);
        $this->assertStringContainsString("route('spj.periodic-reports.pdf'", $blade);
        $this->assertStringContainsString("@if(\$summary['ready'])", $blade);
        $this->assertStringContainsString('Siap dicetak', $blade);
    }

    public function test_print_and_pdf_views_share_the_same_document_partial(): void
    {
        $print = file_get_contents(resource_path('views/periodic-reports/print.blade.php'));
        $pdf = file_get_contents(resource_path('views/periodic-reports/pdf.blade.php'));
        $document = file_get_contents(resource_path('views/periodic-reports/partials/document.blade.php'));

        $this->assertIsString($print);
        $this->assertIsString($pdf);
        $this->assertIsString($document);
        $this->assertStringContainsString("@include('periodic-reports.partials.document')", $print);
        $this->assertStringContainsString("@include('periodic-reports.partials.document')", $pdf);
        $this->assertStringContainsString('Kepala Sekolah', $document);
        $this->assertStringContainsString('Bendahara BOSP', $document);
    }

    public function test_internal_print_service_covers_every_registered_periodic_report_key(): void
    {
        $registry = app(SpjPeriodicReportRegistry::class);
        $service = file_get_contents(app_path('Services/SpjPeriodicReportPrintService.php'));

        $this->assertIsString($service);

        $keys = collect($registry->packages())
            ->flatten(1)
            ->pluck('key')
            ->unique()
            ->values();

        foreach ($keys as $key) {
            $this->assertStringContainsString("'{$key}'", $service, "Periodic report key {$key} is not covered by the internal print service.");
        }
    }
}
