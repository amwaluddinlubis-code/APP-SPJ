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

    public function test_spj_preparation_filters_and_reset_share_one_row(): void
    {
        $blade = file_get_contents(resource_path('views/livewire/spj-preparation-filter.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringContainsString('sm:grid-cols-2 lg:grid-cols-5 lg:items-end', $blade);
        $this->assertStringContainsString('icon="refresh"', $blade);
        $this->assertStringContainsString('>Reset Filter</x-ui.button>', $blade);
    }

    public function test_spj_category_labels_are_title_case_in_display_surfaces(): void
    {
        $displayFiles = [
            resource_path('views/livewire/spj-preparation-filter.blade.php'),
            resource_path('views/spj/index.blade.php'),
            resource_path('views/livewire/transactions-table.blade.php'),
            resource_path('views/livewire/spj-package-list.blade.php'),
            resource_path('views/spj/checklist.blade.php'),
            resource_path('views/spj/numbering.blade.php'),
            resource_path('views/livewire/reconciliation-list.blade.php'),
        ];

        foreach ($displayFiles as $file) {
            $blade = file_get_contents($file);

            $this->assertIsString($blade);
            $this->assertStringContainsString("'BARANG' => 'Barang'", $blade);
            $this->assertStringContainsString("'KONSUMSI' => 'Konsumsi'", $blade);
            $this->assertStringContainsString("'PEMELIHARAAN' => 'Pemeliharaan'", $blade);
            $this->assertStringContainsString("'JASA_LAINNYA' => 'Jasa Lainnya'", $blade);
            $this->assertStringNotContainsString("default => str_replace('_', ' ', (string) \$value)", $blade);
        }

        $this->assertStringContainsString("'JENIS_SPJ' => \$spjCategoryLabel", file_get_contents(base_path('app/Services/SpjTemplateService.php')));
        $this->assertStringContainsString('kategori <span class="font-bold">Honor Pegawai</span>', file_get_contents(resource_path('views/spj-reports/honor-select.blade.php')));
        $this->assertStringContainsString('kategori <span class="font-bold">Jasa Lainnya</span>', file_get_contents(resource_path('views/spj-reports/service-recipient-select.blade.php')));
    }

    public function test_spj_package_tabs_use_flat_theme_indicator_and_transition_panels(): void
    {
        $blade = file_get_contents(resource_path('views/spj/index.blade.php'));
        $css = file_get_contents(resource_path('css/spj-workspace-standardization.css'));

        $this->assertIsString($blade);
        $this->assertIsString($css);
        $this->assertStringContainsString('class="spj-standard-tab"', $blade);
        $this->assertStringContainsString('x-transition:enter-start="opacity-0 translate-y-1"', $blade);
        $this->assertStringContainsString('[data-package-tab]::after', $css);
        $this->assertStringContainsString('background: var(--theme-content-accent, var(--ui-accent));', $css);
        $this->assertStringContainsString('.ui-tab-active::after', file_get_contents(resource_path('css/ui-generalization.css')));
    }

    public function test_spj_monitoring_surfaces_use_theme_tokens(): void
    {
        $index = file_get_contents(resource_path('views/spj/index.blade.php'));
        $monitoring = file_get_contents(resource_path('views/livewire/spj-monitoring-list.blade.php'));

        $this->assertIsString($index);
        $this->assertIsString($monitoring);
        $this->assertStringContainsString('bg-[var(--ui-surface-soft)]', $index);
        $this->assertStringNotContainsString('bg-amber-50/40', $index);
        $this->assertStringNotContainsString('bg-amber-50', $monitoring);
        $this->assertStringNotContainsString('bg-rose-50', $monitoring);
        $this->assertStringContainsString('spj-monitoring-table', $monitoring);
    }
}
