<?php

namespace Tests\Feature;

use Tests\TestCase;

class SpjReportLayoutTest extends TestCase
{
    public function test_report_filter_and_summary_share_horizontal_layout(): void
    {
        $blade = file_get_contents(resource_path('views/livewire/spj-report-filter.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringNotContainsString('<livewire:spj-periodic-report-center', $blade);
        $this->assertStringContainsString('aria-label="Filter laporan"', $blade);
        $this->assertStringContainsString('aria-label="Ringkasan laporan"', $blade);
        $this->assertStringContainsString('xl:grid-cols-5', $blade);
        $this->assertStringContainsString('grid grid-cols-2', $blade);
        $this->assertStringContainsString('text-xl font-extrabold', $blade);
        $this->assertStringContainsString('wire:click="setMode(', $blade);
        $this->assertStringContainsString('wire:model.live="periode"', $blade);
        $this->assertStringContainsString('wire:model.live="perPage"', $blade);
        $this->assertStringContainsString("'bulan' => ['month' => \$periode]", $blade);
        $this->assertStringContainsString("route('spj.honor-payments.select', \$exportQuery)", $blade);
        $this->assertStringContainsString("route('spj.service-recipients.select', \$exportQuery)", $blade);
        $this->assertStringContainsString('Susun Laporan', $blade);
        $this->assertStringContainsString('Honor Pegawai', $blade);
        $this->assertStringContainsString('Jasa Lainnya', $blade);
        $this->assertStringNotContainsString('Pratinjau PDF', $blade);
        $this->assertStringNotContainsString('Unduh Excel', $blade);
        $this->assertStringNotContainsString('Daftar Honor PDF', $blade);
        $this->assertStringNotContainsString('Daftar Penerima Jasa PDF', $blade);
    }

    public function test_periodic_report_center_lists_period_scopes_and_source_summary(): void
    {
        $blade = file_get_contents(resource_path('views/livewire/spj-periodic-report-center.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringContainsString('Paket laporan periode', $blade);
        $this->assertStringContainsString('aria-label="Jenis paket laporan"', $blade);
        $this->assertStringContainsString('wire:click="setScope(', $blade);
        $this->assertStringContainsString('wire:model.live="periode"', $blade);
        $this->assertStringContainsString('Ringkasan sumber data', $blade);
        $this->assertStringContainsString('Siap dicetak', $blade);
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

    public function test_report_preview_opens_in_shared_modal_partial(): void
    {
        $partial = file_get_contents(resource_path('views/spj/partials/preview-modal.blade.php'));
        $index = file_get_contents(resource_path('views/spj/index.blade.php'));
        $report = file_get_contents(resource_path('views/livewire/spj-report-filter.blade.php'));

        $this->assertIsString($partial);
        $this->assertIsString($index);
        $this->assertIsString($report);
        $this->assertSame(1, substr_count($partial, 'id="template-preview-modal"'));
        $this->assertSame(1, substr_count($partial, 'id="template-preview-frame"'));
        $this->assertStringContainsString('class="h-full min-h-[760px] w-full border-0 bg-transparent"', $partial);
        $this->assertStringContainsString("@include('spj.partials.preview-modal')", $index);
        $this->assertStringNotContainsString('id="template-preview-modal"', $index);
        $this->assertStringContainsString('data-template-preview', $report);
        $this->assertStringNotContainsString('target="_blank">Preview dokumen', $report);
    }

    public function test_spj_main_tabs_render_as_segmented_control(): void
    {
        $css = file_get_contents(resource_path('css/spj-workspace-standardization.css'));

        $this->assertIsString($css);
        $this->assertStringContainsString('#spj-main-tabs .ui-tabs-list', $css);
        $this->assertStringContainsString('#spj-main-tabs .ui-tab-active', $css);
    }

    public function test_siplah_goods_number_strip_uses_marketplace_order_reference(): void
    {
        $blade = file_get_contents(resource_path('views/spj/partials/package/categories/barang.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringContainsString('$isSiplah ? ($transaction->siplah_order_number', $blade);
        $this->assertStringContainsString('siplahResponse.invoice_number', $blade);
        $this->assertStringContainsString('$purchaseDetails?->order_number ?: $transaction->order_number', $blade);
    }

    public function test_siplah_metadata_uses_one_row_on_large_screens(): void
    {
        $blade = file_get_contents(resource_path('views/spj/index.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringContainsString('sm:grid-cols-2 lg:grid-cols-5', $blade);
    }
}
