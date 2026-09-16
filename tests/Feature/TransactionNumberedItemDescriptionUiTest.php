<?php

namespace Tests\Feature;

use Tests\TestCase;

class TransactionNumberedItemDescriptionUiTest extends TestCase
{
    public function test_numbered_package_keeps_spj_description_editor_enabled(): void
    {
        $blade = file_get_contents(resource_path('views/transactions/partials/detail/items.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringContainsString("\$isNumberedPackage = \$transaction->spjPackage?->status === 'NUMBERED';", $blade);
        $this->assertStringContainsString('@disabled(! $spjDescriptionsEditable)', $blade);
        $this->assertStringContainsString('Koreksi uraian tetap diperbolehkan', $blade);
        $this->assertStringNotContainsString('window.confirm', $blade);
        $this->assertStringContainsString('Nomor SPJ, status paket, tanggal transaksi, nilai bruto, pajak, netto, dan urutan penomoran tidak berubah.', $blade);
    }

    public function test_payment_correction_and_source_description_are_rendered_only_for_numbered_package(): void
    {
        $blade = file_get_contents(resource_path('views/transactions/partials/detail/items.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringContainsString('$isNumberedPackage = $transaction->spjPackage?->status === \'NUMBERED\';', $blade);
        $this->assertStringContainsString('@if($isNumberedPackage)', $blade);
        $this->assertStringContainsString('name="payment_description"', $blade);
        $this->assertStringContainsString('Uraian sumber ARKAS/BKU', $blade);
    }

    public function test_spj_description_endpoint_keeps_final_guard_and_numbering_safe_message(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/TransactionController.php'));

        $this->assertIsString($controller);
        $this->assertStringContainsString("\$transaction->spjPackage?->status === 'FINAL'", $controller);
        $this->assertStringContainsString("\$descriptions->updatePaymentDescription(\$transaction, \$data['payment_description'] ?? null)", $controller);
        $this->assertStringContainsString("'item_description' => trim(\$itemData['item_description'])", $controller);
        $this->assertStringContainsString('berhasil disimpan tanpa mengubah data sumber ARKAS/BKU atau penomoran', $controller);

        $livewire = file_get_contents(app_path('Livewire/TransactionDetailWorkspace.php'));

        $this->assertIsString($livewire);
        $this->assertStringContainsString("type: 'success', message: 'Koreksi uraian berhasil disimpan.'", $livewire);
        $this->assertStringContainsString("type: 'error', message: \$exception->validator->errors()->first()", $livewire);
    }
}
