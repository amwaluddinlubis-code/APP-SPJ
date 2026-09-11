<?php

namespace Tests\Feature;

use Tests\TestCase;

class TransactionNumberedItemDescriptionUiTest extends TestCase
{
    public function test_numbered_package_keeps_item_description_editor_enabled(): void
    {
        $blade = file_get_contents(resource_path('views/transactions/partials/detail/items.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringContainsString("$transaction->spjPackage->status === 'NUMBERED'", $blade);
        $this->assertStringContainsString('@disabled(! $itemDescriptionsEditable)', $blade);
        $this->assertStringContainsString('Koreksi uraian tetap diperbolehkan', $blade);
        $this->assertStringContainsString('Nomor SPJ, status paket, dan urutan penomoran tidak berubah.', $blade);
    }

    public function test_item_description_endpoint_keeps_final_guard_and_numbering_safe_message(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/TransactionController.php'));

        $this->assertIsString($controller);
        $this->assertStringContainsString("$transaction->spjPackage?->status === 'FINAL'", $controller);
        $this->assertStringContainsString("'item_description' => trim($itemData['item_description'])", $controller);
        $this->assertStringContainsString('berhasil disimpan tanpa mengubah penomoran', $controller);
    }
}
