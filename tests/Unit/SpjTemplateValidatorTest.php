<?php

namespace Tests\Unit;

use App\Services\SpjDocumentTypeRegistry;
use App\Services\SpjTemplateValidator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpjTemplateValidatorTest extends TestCase
{
    #[Test]
    public function valid_spk_contract_passes(): void
    {
        $definition = SpjDocumentTypeRegistry::definition(SpjDocumentTypeRegistry::SPK_PEMELIHARAAN);
        $markers = array_merge($definition['required'], [
            'NAMA_BENDAHARA_BOSP',
            'NIP_BENDAHARA_BOSP',
        ]);

        $result = app(SpjTemplateValidator::class)->validateMarkers(
            SpjDocumentTypeRegistry::SPK_PEMELIHARAAN,
            $markers,
        );

        $this->assertTrue($result['valid']);
        $this->assertSame([], $result['errors']);
    }

    #[Test]
    public function missing_required_paired_and_unknown_placeholders_are_reported(): void
    {
        $result = app(SpjTemplateValidator::class)->validateMarkers(
            SpjDocumentTypeRegistry::SPK_PEMELIHARAAN,
            [
                'NOMOR_SPK',
                'TANGGAL_SPK',
                'NAMA_PENERIMA',
                'URAIAN_PEKERJAAN',
                'LOKASI_PEKERJAAN',
                'TANGGAL_MULAI',
                'TANGGAL_SELESAI',
                'NILAI_PEKERJAAN',
                'NILAI_PEKERJAAN_TERBILANG',
                'NAMA_KEPALA_SEKOLAH',
                'PLACEHOLDER_ASING',
            ],
        );

        $codes = collect($result['errors'])->pluck('code')->all();

        $this->assertFalse($result['valid']);
        $this->assertContains('MISSING_REQUIRED', $codes);
        $this->assertContains('PAIRED_PLACEHOLDER_MISSING', $codes);
        $this->assertContains('UNKNOWN_PLACEHOLDER', $codes);
    }

    #[Test]
    public function known_placeholder_from_another_document_is_a_warning_not_an_unknown_error(): void
    {
        $definition = SpjDocumentTypeRegistry::definition(SpjDocumentTypeRegistry::SPK_PEMELIHARAAN);
        $markers = array_merge($definition['required'], ['NOMOR_INVOICE']);

        $result = app(SpjTemplateValidator::class)->validateMarkers(
            SpjDocumentTypeRegistry::SPK_PEMELIHARAAN,
            $markers,
        );

        $this->assertTrue($result['valid']);
        $this->assertContains('UNEXPECTED_PLACEHOLDER', collect($result['warnings'])->pluck('code')->all());
        $this->assertNotContains('UNKNOWN_PLACEHOLDER', collect($result['errors'])->pluck('code')->all());
    }

    #[Test]
    public function repeat_placeholders_must_share_one_example_row(): void
    {
        $required = SpjDocumentTypeRegistry::definition(SpjDocumentTypeRegistry::RINCIAN_BELANJA)['required'];
        $repeat = SpjDocumentTypeRegistry::definition(SpjDocumentTypeRegistry::RINCIAN_BELANJA)['repeat_required'];
        $markers = array_merge($required, $repeat);
        $rows = [];

        foreach ($repeat as $index => $marker) {
            $rows[$marker] = [$index === array_key_last($repeat) ? 7 : 6];
        }

        $result = app(SpjTemplateValidator::class)->validateMarkers(
            SpjDocumentTypeRegistry::RINCIAN_BELANJA,
            $markers,
            $rows,
        );

        $this->assertFalse($result['valid']);
        $this->assertContains('REPEAT_ROW_MISMATCH', collect($result['errors'])->pluck('code')->all());
    }

    #[Test]
    public function excel_validator_uses_canonical_sheet_and_ignores_placeholder_map(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'spj-validator-').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('TPL_RINCIAN');
        $sheet->setCellValue('A1', '{{NOMOR_DOKUMEN}}');
        $sheet->setCellValue('B1', '{{NOMOR_BUKTI}}');
        $sheet->setCellValue('C1', '{{NILAI_BRUTO}}');
        $sheet->fromArray([
            '{{ITEM_NO}}',
            '{{ITEM_URAIAN}}',
            '{{ITEM_VOLUME}}',
            '{{ITEM_SATUAN}}',
            '{{ITEM_HARGA_SATUAN}}',
            '{{ITEM_JUMLAH}}',
        ], null, 'A5');

        $technical = $spreadsheet->createSheet();
        $technical->setTitle('PLACEHOLDER_MAP');
        $technical->setCellValue('A1', '{{PLACEHOLDER_ASING}}');

        (new Xlsx($spreadsheet))->save($path);

        try {
            $result = app(SpjTemplateValidator::class)->validateFile(
                SpjDocumentTypeRegistry::RINCIAN_BELANJA,
                $path,
                'xlsx',
            );

            $this->assertTrue($result['valid']);
            $this->assertSame('TPL_RINCIAN', $result['sheet']);
            $this->assertNotContains('PLACEHOLDER_ASING', $result['markers']);
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function single_sheet_excel_with_noncanonical_name_is_allowed_with_warning(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'spj-validator-').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rincian Belanja');
        $sheet->setCellValue('A1', '{{NOMOR_DOKUMEN}}');
        $sheet->setCellValue('B1', '{{NOMOR_BUKTI}}');
        $sheet->setCellValue('C1', '{{NILAI_BRUTO}}');
        $sheet->fromArray([
            '{{ITEM_NO}}',
            '{{ITEM_URAIAN}}',
            '{{ITEM_VOLUME}}',
            '{{ITEM_SATUAN}}',
            '{{ITEM_HARGA_SATUAN}}',
            '{{ITEM_JUMLAH}}',
        ], null, 'A5');

        (new Xlsx($spreadsheet))->save($path);

        try {
            $result = app(SpjTemplateValidator::class)->validateFile(
                SpjDocumentTypeRegistry::RINCIAN_BELANJA,
                $path,
                'xlsx',
            );

            $this->assertTrue($result['valid']);
            $this->assertContains('NON_CANONICAL_SHEET_NAME', collect($result['warnings'])->pluck('code')->all());
        } finally {
            @unlink($path);
        }
    }
}
