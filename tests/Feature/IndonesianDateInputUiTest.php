<?php

namespace Tests\Feature;

use Tests\TestCase;

class IndonesianDateInputUiTest extends TestCase
{
    public function test_global_indonesian_date_helper_is_bootstrapped(): void
    {
        $bootstrap = file_get_contents(resource_path('js/bootstrap.js'));
        $helper = file_get_contents(resource_path('js/indonesian-date-input.js'));

        $this->assertIsString($bootstrap);
        $this->assertIsString($helper);
        $this->assertStringContainsString("import './indonesian-date-input';", $bootstrap);
        $this->assertStringContainsString('input[type="date"]', $helper);
        $this->assertStringContainsString("displayInput.placeholder = 'dd/mm/yyyy';", $helper);
        $this->assertStringContainsString("nativeInput.lang = 'id-ID';", $helper);
        $this->assertStringContainsString("document.addEventListener('livewire:navigated'", $helper);
    }

    public function test_date_helper_keeps_iso_as_native_submitted_value(): void
    {
        $helper = file_get_contents(resource_path('js/indonesian-date-input.js'));

        $this->assertIsString($helper);
        $this->assertStringContainsString('formatIsoDateForDisplay', $helper);
        $this->assertStringContainsString('parseIndonesianDate', $helper);
        $this->assertStringContainsString('nativeInput.value = isoValue;', $helper);
        $this->assertStringContainsString("nativeInput.dispatchEvent(new Event('input', { bubbles: true }))", $helper);
        $this->assertStringContainsString("nativeInput.dispatchEvent(new Event('change', { bubbles: true }))", $helper);
    }

    public function test_native_date_input_can_opt_out_when_a_real_native_field_is_required(): void
    {
        $helper = file_get_contents(resource_path('js/indonesian-date-input.js'));

        $this->assertIsString($helper);
        $this->assertStringContainsString("nativeInput.dataset.dateFormat === 'native'", $helper);
        $this->assertStringContainsString('showPicker', $helper);
    }
}
