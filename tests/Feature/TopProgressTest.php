<?php

namespace Tests\Feature;

use Tests\TestCase;

class TopProgressTest extends TestCase
{
    public function test_authenticated_layout_renders_top_progress_marker(): void
    {
        $layout = file_get_contents(resource_path('views/components/layouts/tailwind-app.blade.php'));

        $this->assertIsString($layout);
        $this->assertStringContainsString('id="app-top-progress"', $layout);
        $this->assertSame(1, substr_count($layout, 'id="app-top-progress"'));
    }

    public function test_top_progress_styles_follow_active_theme(): void
    {
        $css = file_get_contents(resource_path('css/top-progress.css'));

        $this->assertIsString($css);
        $this->assertStringContainsString('#app-top-progress', $css);
        $this->assertStringContainsString('.is-active', $css);
        $this->assertStringContainsString('var(--theme-accent)', $css);
        $this->assertStringContainsString('prefers-reduced-motion', $css);
    }

    public function test_top_progress_hooks_cover_load_navigation_and_livewire(): void
    {
        $js = file_get_contents(resource_path('js/top-progress.js'));

        $this->assertIsString($js);
        $this->assertStringContainsString('beforeunload', $js);
        $this->assertStringContainsString('livewire:init', $js);
        $this->assertStringContainsString('livewire:navigate', $js);
        $this->assertStringContainsString('livewire:navigated', $js);
        $this->assertStringContainsString('morph.updated', $js);
        $this->assertStringContainsString('disableProgressBar', $js);
        $this->assertStringContainsString("addEventListener('load'", $js);
    }
}
