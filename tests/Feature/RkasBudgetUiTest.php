<?php

namespace Tests\Feature;

use Tests\TestCase;

class RkasBudgetUiTest extends TestCase
{
    public function test_rkas_filter_uses_shared_responsive_grid_without_local_style_override(): void
    {
        $view = file_get_contents(resource_path('views/rkas-budget/index.blade.php'));

        $this->assertStringNotContainsString('<style>', $view);
        $this->assertStringNotContainsString('rkas-filter-grid', $view);
        $this->assertStringContainsString('class="ui-filter-grid lg:!grid-cols-7"', $view);
    }
}
