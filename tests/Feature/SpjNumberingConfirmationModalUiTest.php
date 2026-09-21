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

    public function test_numbering_ui_exposes_server_validation_and_uses_the_existing_action_route(): void
    {
        $modal = file_get_contents(resource_path('views/spj/partials/package/numbering-preflight-modal.blade.php'));
        $readonly = file_get_contents(resource_path('views/spj/package-readonly.blade.php'));

        $this->assertStringContainsString('Penomoran tetap divalidasi oleh server.', $modal);
        $this->assertStringContainsString('$effectiveNumberingBlocked', $modal);
        $this->assertStringContainsString('@disabled($effectiveNumberingBlocked)', $modal);
        $this->assertStringContainsString("route('spj.assign-number'", $modal);
        $this->assertStringContainsString('$effectiveNumberingPreflight[\'active\']', $readonly);
        $this->assertStringNotContainsString("route('spj.quarter-numbering'", $readonly);
    }

    public function test_quarter_numbering_ui_limits_v2_to_spj_and_keeps_legacy_selection(): void
    {
        $view = file_get_contents(resource_path('views/spj/numbering.blade.php'));
        $useCase = file_get_contents(app_path('UseCases/Spj/SpjQuarterNumberingUseCase.php'));

        $this->assertStringContainsString("config('spj.v2_read_path', 'legacy') === 'v2'", $view);
        $this->assertStringContainsString('$isV2Numbering ? [\'SPJ\'] : $documentTypes', $view);
        $this->assertStringContainsString('SpjV2NumberingBatchService', $useCase);
        $this->assertStringContainsString('assignEffectiveBatchNumbers', $useCase);
        $this->assertStringContainsString("\$this->v2Batch->issueBatch(\$authorizedCandidates, 'SPJ')", $useCase);
        $effectiveAction = substr($useCase, strpos($useCase, 'private function assignEffectiveBatchNumbers'));
        $this->assertStringNotContainsString('$this->numbers->assignAutomaticNumbers', $effectiveAction);
    }
}
