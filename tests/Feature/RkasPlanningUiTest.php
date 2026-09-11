<?php

namespace Tests\Feature;

use Tests\TestCase;

class RkasPlanningUiTest extends TestCase
{
    public function test_rkas_planning_exports_use_shared_button_primitive(): void
    {
        $view = file_get_contents(resource_path('views/rkas-planning/index.blade.php'));

        $this->assertSame(2, substr_count($view, '<x-ui.button variant="secondary"'));
        $this->assertStringContainsString("route('rkas-planning.export', 'pagu-awal')", $view);
        $this->assertStringContainsString("route('rkas-planning.export', 'sisa-pagu')", $view);
        $this->assertStringNotContainsString('hover:brightness-95', $view);
    }
}
