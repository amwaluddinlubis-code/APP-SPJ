<?php

namespace Tests\Feature;

use Tests\TestCase;

class SpjReportLayoutTest extends TestCase
{
    public function test_report_filter_and_summary_share_horizontal_layout(): void
    {
        $blade = file_get_contents(resource_path('views/livewire/spj-report-filter.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringContainsString('aria-label="Filter laporan"', $blade);
        $this->assertStringContainsString('aria-label="Ringkasan laporan"', $blade);
        $this->assertStringContainsString('xl:grid-cols-5', $blade);
        $this->assertStringContainsString('grid grid-cols-2', $blade);
        $this->assertStringContainsString('text-xl font-extrabold', $blade);
        $this->assertStringContainsString('wire:click="setMode(', $blade);
        $this->assertStringContainsString('wire:model.live="periode"', $blade);
        $this->assertStringContainsString('wire:model.live="perPage"', $blade);
    }

    public function test_spj_tabs_use_livewire_filters_without_full_page_reload(): void
    {
        $index = file_get_contents(resource_path('views/spj/index.blade.php'));

        $this->assertIsString($index);
        $this->assertStringContainsString('<livewire:spj-preparation-filter />', $index);
        $this->assertStringContainsString('<livewire:spj-package-list />', $index);
        $this->assertStringContainsString('<livewire:spj-report-filter />', $index);
        $this->assertStringContainsString('<livewire:spj-monitoring-list />', $index);
        $this->assertStringNotContainsString('relocateReportRowControl', $index);
        $this->assertStringNotContainsString('data-report-mode', $index);
    }

    public function test_spj_main_tabs_render_as_segmented_control(): void
    {
        $css = file_get_contents(resource_path('css/spj-workspace-standardization.css'));

        $this->assertIsString($css);
        $this->assertStringContainsString('#spj-main-tabs .ui-tabs-list', $css);
        $this->assertStringContainsString('#spj-main-tabs .ui-tab-active', $css);
    }
}
