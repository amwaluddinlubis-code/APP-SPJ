<?php

namespace Tests\Feature;

use Tests\TestCase;

class ThemeEarlyInitTest extends TestCase
{
    public function test_early_theme_script_covers_all_js_theme_profiles(): void
    {
        $js = file_get_contents(resource_path('js/theme-profiles.js'));
        $partial = file_get_contents(resource_path('views/components/theme-init.blade.php'));

        $this->assertIsString($js);
        $this->assertIsString($partial);

        preg_match_all('/^\\s{4}(\\w+): profile\\(\\{/m', $js, $jsMatches);
        $jsThemes = $jsMatches[1];
        $this->assertNotEmpty($jsThemes);

        preg_match('/const profiles = (\{.*?\});/s', $partial, $mapMatches);
        $this->assertNotEmpty($mapMatches, 'Partial harus memuat peta profil tema.');
        $map = json_decode($mapMatches[1], true);
        $this->assertIsArray($map);

        $this->assertSame(
            collect($jsThemes)->sort()->values()->all(),
            collect(array_keys($map))->sort()->values()->all(),
            'Peta head harus mencakup semua profil di theme-profiles.js agar tidak flash.'
        );

        foreach ($map as $name => $profile) {
            $this->assertCount(14, $profile, "Profil {$name} harus lengkap (14 nilai).");
        }
    }

    public function test_early_theme_script_applies_dataset_and_tokens(): void
    {
        $partial = file_get_contents(resource_path('views/components/theme-init.blade.php'));

        $this->assertIsString($partial);

        foreach (['dataset.theme', 'dataset.themeProfile', 'dataset.uiAppearance', 'dataset.uiDensity', '--theme-accent', '--theme-accent-soft', '--theme-sidebar-deep', 'colorScheme'] as $needle) {
            $this->assertStringContainsString($needle, $partial);
        }
    }

    public function test_layouts_use_shared_early_theme_script(): void
    {
        foreach (['components/layouts/tailwind-app.blade.php', 'components/layouts/public-tailwind.blade.php'] as $layout) {
            $blade = file_get_contents(resource_path('views/'.$layout));

            $this->assertIsString($blade);
            $this->assertStringContainsString('<x-theme-init />', $blade);
            $this->assertStringNotContainsString("localStorage.getItem('spj-theme')", $blade);
        }
    }
}
