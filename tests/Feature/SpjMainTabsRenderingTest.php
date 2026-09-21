<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SpjMainTabsRenderingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');
        Artisan::call('migrate', ['--database' => 'school', '--path' => 'database/migrations/school', '--force' => true]);
        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        FiscalYear::query()->create(['id' => 1, 'year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
        $this->actingAs(User::factory()->create(['role' => 'ADMIN']));
        $this->withoutMiddleware()->withSession(['active_fiscal_year_id' => 1, 'active_fund_source_id' => 1]);
    }

    protected function tearDown(): void
    {
        DB::purge('school');

        parent::tearDown();
    }

    public function test_main_tabs_only_render_navigation_and_not_tab_content_partials(): void
    {
        $mainTabs = file_get_contents(resource_path('views/spj/partials/main-tabs.blade.php'));
        $index = file_get_contents(resource_path('views/spj/index.blade.php'));

        $this->assertStringContainsString("['id' => 'laporan'", $mainTabs);
        $this->assertStringContainsString("['id' => 'monitoring'", $mainTabs);
        $this->assertStringNotContainsString("@include('spj.partials.laporan')", $mainTabs);
        $this->assertStringNotContainsString("@include('spj.partials.monitoring')", $mainTabs);

        $this->assertSame(1, substr_count($index, "x-show=\"tab === 'laporan'\""));
        $this->assertSame(1, substr_count($index, "x-show=\"tab === 'monitoring'\""));
        $this->assertSame(1, substr_count($index, "x-show=\"tab === 'persiapan'\""));
        $this->assertSame(1, substr_count($index, "x-show=\"tab === 'paket'\""));
    }

    public function test_tab_switching_prefers_spa_navigation_with_reload_fallback(): void
    {
        $index = file_get_contents(resource_path('views/spj/index.blade.php'));

        $this->assertIsString($index);
        $this->assertStringContainsString('Livewire.navigate', $index);
        $this->assertStringContainsString('window.location.assign', $index);
        $this->assertStringContainsString("templatePreview('close')", $index);
        $this->assertStringNotContainsString("querySelectorAll('[data-close-template-preview]')", $index);
    }

    public function test_preparation_links_use_the_correct_package_identifier_for_fresh_packages(): void
    {
        $preparation = file_get_contents(resource_path('views/livewire/spj-preparation-filter.blade.php'));

        $this->assertIsString($preparation);
        $this->assertStringContainsString("instanceof \\App\\Models\\SpjFreshPackage ? 'fresh_package_id' : 'package_id'", $preparation);
    }

    public function test_preview_modal_uses_direct_pdf_frame_when_available(): void
    {
        $index = file_get_contents(resource_path('views/spj/index.blade.php'));
        $documents = file_get_contents(resource_path('views/spj/partials/package/documents.blade.php'));

        $this->assertIsString($index);
        $this->assertIsString($documents);
        $this->assertStringContainsString('button.dataset.templatePreviewPdf || button.dataset.templatePreview', $index);
        $this->assertStringContainsString('data-template-preview-pdf="{{ route(\'spj.preview-package-pdf\'', $documents);
        $this->assertStringContainsString('route(\'spj.preview-template-pdf\'', $documents);
    }

    public function test_laporan_tab_is_livewire_driven_without_get_form(): void
    {
        $html = $this->get(route('spj.index', ['tab' => 'laporan']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('wire:id', $html);
        $this->assertStringContainsString('wire:click="setMode', $html);
        $this->assertStringContainsString('wire:model.live="periode"', $html);
        $this->assertStringContainsString('wire:model.live="perPage"', $html);
        $this->assertStringNotContainsString('data-report-mode', $html);
        $this->assertStringNotContainsString('id="spj-report-mode"', $html);
        $this->assertStringNotContainsString('relocateReportRowControl', $html);
    }

    public function test_each_tab_renders_its_own_panel(): void
    {
        foreach ([
            'persiapan' => 'Antrean persiapan SPJ',
            'paket' => 'Daftar Paket SPJ',
            'laporan' => 'Ringkasan laporan',
            'monitoring' => 'Monitoring dan penutupan periode',
        ] as $tab => $marker) {
            $this->get(route('spj.index', ['tab' => $tab]))
                ->assertOk()
                ->assertSee($marker, false);
        }
    }

    public function test_package_readiness_sections_are_composed_inside_the_rincian_panel(): void
    {
        $index = file_get_contents(resource_path('views/spj/index.blade.php'));
        $validation = file_get_contents(resource_path('views/spj/partials/package/validation.blade.php'));
        $documents = file_get_contents(resource_path('views/spj/partials/package/documents.blade.php'));

        $this->assertIsString($index);
        $this->assertIsString($validation);
        $this->assertIsString($documents);
        $this->assertStringContainsString("@include('spj.partials.package.validation')", $index);
        $this->assertStringContainsString("@include('spj.partials.package.documents')", $index);
        $this->assertStringContainsString('id="package-panel-rincian"', $index);
        $this->assertStringContainsString('class="overflow-hidden border-b border-[var(--ui-line)] pb-1"', $validation);
        $this->assertStringContainsString('class="overflow-hidden pt-1"', $documents);
        $this->assertStringNotContainsString('mx-5 mt-5 overflow-hidden rounded-xl border', $validation);
        $this->assertStringNotContainsString('mx-5 mt-5 overflow-hidden rounded-xl border', $documents);
    }

    public function test_fresh_package_validation_exposes_pass_status_without_claiming_document_workflow(): void
    {
        $freshValidation = file_get_contents(resource_path('views/spj/partials/package/fresh-validation.blade.php'));

        $this->assertIsString($freshValidation);
        $this->assertStringContainsString("'PASS'", $freshValidation);
        $this->assertStringContainsString('Penomoran dilewati karena transaksi sudah memiliki nomor.', $freshValidation);
        $this->assertStringContainsString('Preview HTML serta unduh Excel dan PDF tersedia.', $freshValidation);
    }

    public function test_fresh_package_document_actions_use_dedicated_routes(): void
    {
        $freshDocuments = file_get_contents(resource_path('views/spj/partials/package/fresh-documents.blade.php'));

        $this->assertIsString($freshDocuments);
        $this->assertStringContainsString("route('spj.fresh-preview-package'", $freshDocuments);
        $this->assertStringContainsString("route('spj.fresh-download-package-excel'", $freshDocuments);
        $this->assertStringContainsString("route('spj.fresh-download'", $freshDocuments);
        $this->assertStringNotContainsString('data-template-preview-pdf="{{ route(\'spj.fresh-preview-package-pdf\'', $freshDocuments);
        $this->assertStringContainsString('grid gap-px bg-[var(--ui-line)] md:grid-cols-2', $freshDocuments);
        $this->assertStringContainsString('ui-btn ui-btn-secondary min-h-10 min-w-10', $freshDocuments);
    }

    public function test_package_summary_uses_one_neutral_surface_with_theme_accent_for_key_value(): void
    {
        $summary = file_get_contents(resource_path('views/spj/partials/package/transaction-summary.blade.php'));

        $this->assertIsString($summary);
        $this->assertStringContainsString('background: var(--ui-surface-soft)', $summary);
        $this->assertStringContainsString('background: var(--ui-surface-base)', $summary);
        $this->assertStringContainsString('var(--theme-content-accent)', $summary);
        $this->assertStringNotContainsString('linear-gradient', $summary);
        $this->assertStringNotContainsString('var(--text-comfort-on-dark)', $summary);
    }
}
