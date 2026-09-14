<?php

namespace Tests\Unit;

use App\Services\SpjTemplateService;
use App\Services\SpjTemplateValidator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class SpjTemplateExcelHeaderFooterTest extends TestCase
{
    public function test_excel_header_and_footer_placeholders_are_replaced(): void
    {
        $sheet = (new Spreadsheet)->getActiveSheet();
        $headerFooter = $sheet->getHeaderFooter();
        $headerFooter->setOddHeader('&L{{NAMA_SEKOLAH}}');
        $headerFooter->setOddFooter('&R{{NOMOR_DOKUMEN}} / {{TANGGAL_DOKUMEN}}');
        $headerFooter->setEvenFooter('{{SUMBER_DANA}}');

        $method = new \ReflectionMethod(SpjTemplateService::class, 'replaceExcelHeaderFooterPlaceholders');
        $method->setAccessible(true);
        $method->invoke(app(SpjTemplateService::class), $sheet, [
            'NAMA_SEKOLAH' => 'SMP Negeri Contoh',
            'NOMOR_DOKUMEN' => '0001/SPJ/2026',
            'TANGGAL_DOKUMEN' => '07 April 2026',
            'SUMBER_DANA' => 'BOS Reguler',
        ]);

        $this->assertSame('&LSMP Negeri Contoh', $headerFooter->getOddHeader());
        $this->assertSame('&R0001/SPJ/2026 / 07 April 2026', $headerFooter->getOddFooter());
        $this->assertSame('BOS Reguler', $headerFooter->getEvenFooter());
    }

    public function test_validator_reads_placeholders_from_excel_header_and_footer(): void
    {
        $sheet = (new Spreadsheet)->getActiveSheet();
        $sheet->getHeaderFooter()->setOddFooter('&R{{NOMOR_DOKUMEN}}');

        $method = new \ReflectionMethod(SpjTemplateValidator::class, 'extractExcelMarkers');
        $method->setAccessible(true);
        [$markers] = $method->invoke(app(SpjTemplateValidator::class), $sheet);

        $this->assertContains('NOMOR_DOKUMEN', $markers);
    }
}
