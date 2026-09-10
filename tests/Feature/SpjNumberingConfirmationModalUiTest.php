<?php

namespace Tests\Feature;

use Tests\TestCase;

class SpjNumberingConfirmationModalUiTest extends TestCase
{
    public function test_bootstrap_loads_the_numbering_confirmation_modal(): void
    {
        $bootstrap = file_get_contents(resource_path('js/bootstrap.js'));

        $this->assertStringContainsString("import './spj-numbering-confirmation-modal';", $bootstrap);
    }

    public function test_modal_intercepts_reissue_and_document_replacement_forms(): void
    {
        $script = file_get_contents(resource_path('js/spj-numbering-confirmation-modal.js'));

        foreach ([
            '/spj/paket/',
            '/nomor',
            '/spj/dokumen/',
            '/ganti',
            'spj-numbering-confirmation-modal',
            'Konfirmasi & Nomori Ulang',
            'Konfirmasi & Terbitkan Nomor',
            'form.dataset.confirmed = \'true\'',
            'form.requestSubmit(submitter || undefined)',
        ] as $contract) {
            $this->assertStringContainsString($contract, $script);
        }
    }

    public function test_numbering_view_keeps_confirmation_metadata_for_reissue_paths(): void
    {
        $blade = file_get_contents(resource_path('views/spj/partials/package/numbering.blade.php'));

        $this->assertStringContainsString('Terbitkan ulang nomor SPJ tanpa mengubah data paket?', $blade);
        $this->assertStringContainsString('Terbitkan nomor SPJ baru sebagai pengganti nomor yang dibatalkan?', $blade);
        $this->assertStringContainsString('Nomor lama {{ $document->document_number }} akan dibatalkan permanen', $blade);
    }
}
