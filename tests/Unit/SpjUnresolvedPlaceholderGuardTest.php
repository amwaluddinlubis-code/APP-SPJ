<?php

namespace Tests\Unit;

use App\Services\SpjUnresolvedPlaceholderGuard;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\IOFactory as WordWriter;
use PhpOffice\PhpWord\PhpWord;
use RuntimeException;
use Tests\TestCase;

class SpjUnresolvedPlaceholderGuardTest extends TestCase
{
    public function test_it_finds_unresolved_placeholder_in_canonical_excel_sheet(): void
    {
        $path = storage_path('framework/testing/unresolved-rincian.xlsx');
        @mkdir(dirname($path), 0775, true);

        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('TPL_RINCIAN');
        $sheet->setCellValue('A1', 'Nomor {{NOMOR_DOKUMEN}}');
        $sheet->setCellValue('A2', 'Sudah terisi');
        (new Xlsx($book))->save($path);

        try {
            $markers = (new SpjUnresolvedPlaceholderGuard)->findInFile('RINCIAN_BELANJA', $path, 'xlsx');
            $this->assertSame(['NOMOR_DOKUMEN'], $markers);
        } finally {
            @unlink($path);
        }
    }

    public function test_it_ignores_unrelated_and_technical_excel_sheets(): void
    {
        $path = storage_path('framework/testing/resolved-rincian.xlsx');
        @mkdir(dirname($path), 0775, true);

        $book = new Spreadsheet;
        $book->getActiveSheet()->setTitle('TPL_RINCIAN')->setCellValue('A1', 'Sudah terisi');
        $book->createSheet()->setTitle('TPL_KUITANSI')->setCellValue('A1', '{{NOMOR_DOKUMEN}}');
        $book->createSheet()->setTitle('PLACEHOLDER_MAP')->setCellValue('A1', '{{ITEM_NO}}');
        (new Xlsx($book))->save($path);

        try {
            $markers = (new SpjUnresolvedPlaceholderGuard)->findInFile('RINCIAN_BELANJA', $path, 'xlsx');
            $this->assertSame([], $markers);
        } finally {
            @unlink($path);
        }
    }

    public function test_it_finds_unresolved_placeholder_in_word_output(): void
    {
        $path = storage_path('framework/testing/unresolved-spj.docx');
        @mkdir(dirname($path), 0775, true);

        $word = new PhpWord;
        $section = $word->addSection();
        $section->addText('Nomor {{NOMOR_DOKUMEN}}');
        WordWriter::createWriter($word, 'Word2007')->save($path);

        try {
            $markers = (new SpjUnresolvedPlaceholderGuard)->findInFile('KUITANSI_A2', $path, 'docx');
            $this->assertSame(['NOMOR_DOKUMEN'], $markers);
        } finally {
            @unlink($path);
        }
    }

    public function test_assert_resolved_throws_with_marker_names(): void
    {
        $path = storage_path('framework/testing/unresolved-assert.xlsx');
        @mkdir(dirname($path), 0775, true);

        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('TPL_RINCIAN');
        $sheet->setCellValue('A1', '{{ITEM_NO}} {{ITEM_URAIAN}}');
        (new Xlsx($book))->save($path);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('ITEM_NO, ITEM_URAIAN');
            (new SpjUnresolvedPlaceholderGuard)->assertResolved('RINCIAN_BELANJA', $path, 'xlsx');
        } finally {
            @unlink($path);
        }
    }
}
