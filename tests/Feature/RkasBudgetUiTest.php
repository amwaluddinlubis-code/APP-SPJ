<?php

namespace Tests\Feature;

use Tests\TestCase;

class RkasBudgetUiTest extends TestCase
{
    public function test_rkas_filter_is_livewire_card_without_local_style_override(): void
    {
        $view = file_get_contents(resource_path('views/rkas-budget/index.blade.php'));

        $this->assertStringNotContainsString('<style>', $view);
        $this->assertStringNotContainsString('rkas-filter-grid', $view);
        $this->assertStringContainsString('<livewire:rkas-budget-filter />', $view);
    }

    public function test_rkas_filter_card_uses_canonical_primitives_and_theme_tokens(): void
    {
        $view = file_get_contents(resource_path('views/livewire/rkas-budget-filter.blade.php'));

        $this->assertStringContainsString('class="ui-filter-panel"', $view);
        $this->assertStringContainsString('<x-ui.select', $view);
        $this->assertStringContainsString('<x-ui.input', $view);
        $this->assertStringContainsString('<x-ui.button', $view);
        $this->assertStringContainsString("\$set('mode',", $view);
        $this->assertStringContainsString('wire:model.live="program"', $view);
        $this->assertStringContainsString('wire:model.live="sub"', $view);
        $this->assertStringContainsString('wire:model.live="kegiatan"', $view);
        $this->assertStringContainsString('wire:model.live.debounce.300ms="q"', $view);
        $this->assertStringContainsString('var(--theme-action-bg)', $view);
        $this->assertStringNotContainsString('wire:model.live="tahun"', $view);
        $this->assertStringNotContainsString('wire:model.live="dana"', $view);

        $this->assertStringNotContainsString('<style>', $view);
        $this->assertStringNotContainsString('bg-blue-700', $view);
        $this->assertStringNotContainsString('border-gray-200', $view);
        $this->assertStringNotContainsString('hover:bg-gray-50', $view);
        $this->assertStringNotContainsString('class="input"', $view);
    }
}
