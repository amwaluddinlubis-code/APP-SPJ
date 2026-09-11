<?php

namespace Tests\Feature;

use App\Services\ExtendedSpjTemplateService;
use App\Services\SpjDocumentTypeRegistry;
use App\Services\SpjTemplateService;
use App\Services\SpjTemplateValidator;
use App\UseCases\Spj\ExtendedSpjReportUseCase;
use App\UseCases\Spj\SpjReportUseCase;
use Tests\TestCase;

class SpjSupplementaryTemplateContractTest extends TestCase
{
    public function test_consumption_support_documents_are_registered_for_consumption_only(): void
    {
        $attendance = SpjDocumentTypeRegistry::definition(SpjDocumentTypeRegistry::DAFTAR_HADIR_KONSUMSI);
        $recipients = SpjDocumentTypeRegistry::definition(SpjDocumentTypeRegistry::DAFTAR_PENERIMA_KONSUMSI);

        $this->assertSame('TPL_DAFTAR_HADIR_KONSUMSI', $attendance['sheet']);
        $this->assertSame(['KONSUMSI'], $attendance['applicable_categories']);
        $this->assertContains('KONSUMSI_NAMA', $attendance['required']);

        $this->assertSame('TPL_DAFTAR_PENERIMA_KONSUMSI', $recipients['sheet']);
        $this->assertSame(['KONSUMSI'], $recipients['applicable_categories']);
        $this->assertContains('TOTAL_KONSUMSI', $recipients['required']);
    }

    public function test_consumption_placeholders_are_exposed_in_runtime_catalog(): void
    {
        $groups = app(SpjTemplateService::class)::placeholderGroups();

        $this->assertArrayHasKey('Konsumsi & kegiatan', $groups);
        $this->assertContains('TANGGAL_KEGIATAN', $groups['Konsumsi & kegiatan']);
        $this->assertContains('KONSUMSI_NAMA', $groups['Konsumsi & kegiatan']);
        $this->assertContains('TOTAL_KONSUMSI', $groups['Konsumsi & kegiatan']);
    }

    public function test_consumption_placeholders_are_known_by_template_validator(): void
    {
        $definition = SpjDocumentTypeRegistry::definition(SpjDocumentTypeRegistry::DAFTAR_PENERIMA_KONSUMSI);
        $markers = array_values(array_unique(array_merge(
            $definition['required'],
            $definition['optional'],
            $definition['repeat_required'],
            $definition['repeat_optional'],
            $definition['image'],
        )));

        $result = app(SpjTemplateValidator::class)->validateMarkers(
            SpjDocumentTypeRegistry::DAFTAR_PENERIMA_KONSUMSI,
            $markers,
        );

        $errorCodes = collect($result['errors'])->pluck('code')->all();
        $this->assertNotContains('UNKNOWN_PLACEHOLDER', $errorCodes);
    }

    public function test_revised_master_workbook_metadata_and_unimplemented_design_sheets_are_technical(): void
    {
        $technical = SpjDocumentTypeRegistry::technicalSheets();

        $this->assertContains('TEMPLATE_REGISTER', $technical);
        $this->assertContains('REGULASI_REFERENSI', $technical);
        $this->assertContains('BACKEND_TEMPLATE_RULES', $technical);
        $this->assertContains('TPL_DAFTAR_PENERIMAAN_HONOR', $technical);
        $this->assertContains('TPL_SPPD', $technical);
    }

    public function test_runtime_resolves_extended_template_and_report_services(): void
    {
        $this->assertInstanceOf(ExtendedSpjTemplateService::class, app(SpjTemplateService::class));
        $this->assertInstanceOf(ExtendedSpjReportUseCase::class, app(SpjReportUseCase::class));
    }

    public function test_bap_points_to_revised_inspection_and_acceptance_sheet(): void
    {
        $definition = SpjDocumentTypeRegistry::definition(SpjDocumentTypeRegistry::BAP);

        $this->assertSame('TPL_BA_PEMERIKSAAN_PENERIMAAN', $definition['sheet']);
    }
}
