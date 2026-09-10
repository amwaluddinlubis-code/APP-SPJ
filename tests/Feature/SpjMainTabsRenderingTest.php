<?php

namespace Tests\Feature;

use Tests\TestCase;

class SpjMainTabsRenderingTest extends TestCase
{
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
    }
}
