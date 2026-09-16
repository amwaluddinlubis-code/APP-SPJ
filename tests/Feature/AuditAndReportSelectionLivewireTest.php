<?php

namespace Tests\Feature;

use App\Http\Controllers\AuditReportController;
use App\Http\Controllers\SpjController;
use App\Livewire\AuditReportWorkspace;
use App\Livewire\SpjReportTransactionSelector;
use App\Services\AuditReportService;
use App\UseCases\Spj\ExtendedSpjReportUseCase;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class AuditAndReportSelectionLivewireTest extends TestCase
{
    public function test_audit_controller_only_renders_livewire_shell_without_building_report(): void
    {
        $reports = Mockery::mock(AuditReportService::class);
        $reports->shouldNotReceive('build');

        $view = (new AuditReportController($reports))->index();

        $this->assertSame('audit-reports.index', $view->name());
    }

    public function test_audit_named_paginator_resets_when_its_page_size_changes(): void
    {
        $reports = Mockery::mock(AuditReportService::class);
        $reports->shouldReceive('build')->atLeast()->once()->andReturn($this->auditReport(40));
        $this->app->instance(AuditReportService::class, $reports);

        Livewire::test(AuditReportWorkspace::class)
            ->set('tab', 'reconciliation')
            ->set('reconciliationPerPage', 10)
            ->assertSee('R-001')
            ->assertDontSee('R-011')
            ->call('setPage', 2, 'reconciliation_page')
            ->assertSee('R-011')
            ->assertDontSee('R-001')
            ->set('reconciliationPerPage', 25)
            ->assertSee('R-001')
            ->assertSee('R-020');
    }

    public function test_audit_per_page_url_binding_preserves_legacy_query_aliases(): void
    {
        $source = file_get_contents(app_path('Livewire/AuditReportWorkspace.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString("#[Url(as: 'reconciliation_perPage', except: 15)]", $source);
        $this->assertStringContainsString("#[Url(as: 'register_perPage', except: 15)]", $source);
        $this->assertStringContainsString("#[Url(as: 'completeness_perPage', except: 15)]", $source);
        $this->assertStringContainsString("#[Url(as: 'history_perPage', except: 15)]", $source);
    }

    public function test_audit_pagination_uses_real_boolean_disabled_attributes(): void
    {
        $blade = file_get_contents(resource_path('views/components/livewire/audit-pagination.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringContainsString('@disabled($paginator->onFirstPage())', $blade);
        $this->assertStringContainsString('@disabled(! $paginator->hasMorePages())', $blade);
        $this->assertStringNotContainsString('disabled="{{', $blade);
    }

    public function test_honor_selector_stores_selection_in_session_instead_of_query_string(): void
    {
        $useCase = Mockery::mock(ExtendedSpjReportUseCase::class);
        $useCase->shouldReceive('selectionTransactions')->zeroOrMoreTimes()->andReturn(collect());
        $this->app->instance(ExtendedSpjReportUseCase::class, $useCase);

        Livewire::test(SpjReportTransactionSelector::class, ['category' => 'HONOR_PEGAWAI'])
            ->set('selected', [11, 12])
            ->call('continueToCompose')
            ->assertRedirect(route('spj.honor-payments.compose'))
            ->assertSessionHas('spj_report_selection.honor', [11, 12]);
    }

    public function test_service_selector_uses_its_own_session_selection_key(): void
    {
        $useCase = Mockery::mock(ExtendedSpjReportUseCase::class);
        $useCase->shouldReceive('selectionTransactions')->zeroOrMoreTimes()->andReturn(collect());
        $this->app->instance(ExtendedSpjReportUseCase::class, $useCase);

        Livewire::test(SpjReportTransactionSelector::class, ['category' => 'JASA_LAINNYA'])
            ->set('selected', [21])
            ->call('continueToCompose')
            ->assertRedirect(route('spj.service-recipients.compose'))
            ->assertSessionHas('spj_report_selection.service', [21]);
    }

    public function test_compose_controller_hydrates_honor_selection_from_session_once(): void
    {
        $request = Request::create('/spj/laporan/honor/susun', 'GET');
        $session = app('session')->driver();
        $session->start();
        $request->setLaravelSession($session);
        $request->session()->put('spj_report_selection.honor', [11, 12]);

        $useCase = Mockery::mock(ExtendedSpjReportUseCase::class);
        $useCase->shouldReceive('composeHonorPayments')
            ->once()
            ->with(Mockery::on(fn (Request $candidate): bool => $candidate->input('transaction_ids') === [11, 12]))
            ->andReturn(view('spj-reports.honor-select', ['transactions' => collect()]));

        (new SpjController)->composeHonorPayments($request, $useCase);

        $this->assertFalse($request->session()->has('spj_report_selection.honor'));
    }

    /** @return array<string,mixed> */
    private function auditReport(int $reconciliationCount): array
    {
        return [
            'year' => (object) ['year' => 2026, 'fund_source' => 'BOS Reguler'],
            'fundSource' => (object) ['name' => 'BOS Reguler'],
            'summary' => [
                'budget' => 0,
                'bku' => 0,
                'transactions' => 0,
                'tax' => 0,
                'transactionCount' => 0,
                'bkuCount' => 0,
                'spjPackaged' => 0,
                'spjNumbered' => 0,
                'siplahCount' => 0,
                'nonSiplahCount' => 0,
                'mismatchCount' => 0,
                'exceptionCount' => 0,
                'inspectionReadyCount' => 0,
                'syncCount' => 0,
                'auditCount' => 0,
            ],
            'reconciliationRows' => new Collection(array_map(
                fn (int $number): object => (object) [
                    'no_bukti' => sprintf('R-%03d', $number),
                    'transaction_date' => null,
                    'rkas_amount' => 0,
                    'bku_amount' => 0,
                    'transaction_amount' => 0,
                    'variance' => 0,
                    'spj_status' => 'BELUM ADA',
                    'status' => 'SESUAI',
                ],
                range(1, $reconciliationCount),
            )),
            'register' => collect(),
            'taxSummary' => collect(),
            'taxRows' => collect(),
            'completenessRows' => collect(),
            'inspectionIndex' => collect(),
            'syncRuns' => collect(),
            'auditLogs' => collect(),
            'limitations' => [],
        ];
    }
}
