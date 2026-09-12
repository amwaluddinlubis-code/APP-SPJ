<?php

namespace Tests\Feature;

use Tests\TestCase;

class TransactionNumberedItemDescriptionUiTest extends TestCase
{
    public function test_numbered_package_keeps_spj_description_editor_enabled(): void
    {
        $blade = file_get_contents(resource_path('views/transactions/partials/detail/items.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringContainsString("\$transaction->spjPackage->status === 'NUMBERED'", $blade);
        $this->assertStringContainsString('@disabled(! $spjDescriptionsEditable)', $blade);
        $this->assertStringContainsString('Koreksi uraian tetap diperbolehkan', $blade);
        $this->assertStringContainsString('Nomor SPJ, status paket, tanggal transaksi, nilai bruto, pajak, netto, dan urutan penomoran tidak berubah.', $blade);
    }

    public function test_spj_description_endpoint_keeps_final_guard_and_numbering_safe_message(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/TransactionController.php'));

        $this->assertIsString($controller);
        $this->assertStringContainsString("\$transaction->spjPackage?->status === 'FINAL'", $controller);
        $this->assertStringContainsString("'payment_description' => \$paymentDescription !== '' ? \$paymentDescription : null", $controller);
        $this->assertStringContainsString("'item_description' => trim(\$itemData['item_description'])", $controller);
        $this->assertStringContainsString('berhasil disimpan tanpa mengubah data sumber ARKAS/BKU atau penomoran', $controller);
    }
}
