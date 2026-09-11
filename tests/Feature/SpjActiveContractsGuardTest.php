<?php

namespace Tests\Feature;

use Tests\TestCase;

class SpjActiveContractsGuardTest extends TestCase
{
    public function test_siplah_radio_never_writes_payment_method(): void
    {
        $js = file_get_contents(resource_path('js/spj-package-manual-category.js'));

        $this->assertStringNotContainsString("paymentMethod.value = 'siplah'", $js);
        $this->assertStringNotContainsString('paymentMethod.value = form.dataset', $js);
        $this->assertStringContainsString('data-spj-siplah-mode="siplah"', $js);
        $this->assertStringContainsString('data-spj-siplah-mode="non_siplah"', $js);
    }

    public function test_participant_roster_has_no_source_restriction(): void
    {
        $useCase = file_get_contents(app_path('UseCases/Spj/SpjWorkspaceUseCase.php'));

        $this->assertStringNotContainsString('fromDapodik', $useCase);
        $this->assertStringNotContainsString("where('source_type'", $useCase);
    }

    public function test_cleaned_pages_have_no_local_breadcrumb(): void
    {
        foreach ([
            'views/rkas-budget/index.blade.php',
            'views/livewire/transactions-table.blade.php',
            'views/spj/checklist.blade.php',
        ] as $view) {
            $this->assertStringNotContainsString('x-slot:breadcrumb', file_get_contents(resource_path($view)), $view);
        }
    }

    public function test_transactions_table_shows_single_filtered_stat_strip(): void
    {
        $view = file_get_contents(resource_path('views/livewire/transactions-table.blade.php'));

        $this->assertStringNotContainsString('Total Tahunan', $view);
        $this->assertStringContainsString('$filteredStats', $view);
    }
}
