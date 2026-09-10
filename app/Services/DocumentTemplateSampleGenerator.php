<?php

namespace App\Services;

use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\IOFactory as WordWriter;
use PhpOffice\PhpWord\PhpWord;

final class DocumentTemplateSampleGenerator
{
    /** @return array{path:string,download_name:string} */
    public function generate(string $format): array
    {
        if (! in_array($format, ['docx', 'xlsx'], true)) {
            throw new InvalidArgumentException('Format contoh template tidak didukung.');
        }

        $directory = storage_path('app/generated-documents');
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $path = $directory.'/CONTOH-TEMPLATE-SPJ-'.uniqid().'.'.$format;

        if ($format === 'docx') {
            $this->generateDocx($path);
        } else {
            $this->generateXlsx($path);
        }

        return [
            'path' => $path,
            'download_name' => 'CONTOH-TEMPLATE-SPJ.'.$format,
        ];
    }

    private function generateDocx(string $path): void
    {
        $word = new PhpWord;
        $section = $word->addSection();
        $section->addText('CONTOH TEMPLATE RINCIAN BELANJA SPJ', ['bold' => true, 'size' => 14]);
        $section->addText('Nomor SPJ: {{NOMOR_SPJ}}');
        $section->addText('No. Bukti: {{NO_BUKTI}}');
        $section->addText('Penerima: {{NAMA_PENERIMA}}');
        $table = $section->addTable(['borderSize' => 6, 'borderColor' => '64748B']);
        $headingRow = $table->addRow();
        foreach (['No', 'Uraian', 'Volume', 'Satuan', 'Harga', 'Jumlah'] as $heading) {
            $headingRow->addCell()->addText($heading);
        }
        $row = $table->addRow();
        foreach (['{{ITEM_NO}}', '{{ITEM_URAIAN}}', '{{ITEM_VOLUME}}', '{{ITEM_SATUAN}}', '{{ITEM_HARGA_SATUAN}}', '{{ITEM_JUMLAH}}'] as $marker) {
            $row->addCell()->addText($marker);
        }
        $section->addText('Total bruto: {{NILAI_BRUTO}}');
        WordWriter::createWriter($word, 'Word2007')->save($path);
    }

    private function generateXlsx(string $path): void
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Rincian Belanja');
        $sheet->setCellValue('A1', 'CONTOH TEMPLATE RINCIAN BELANJA SPJ');
        $sheet->setCellValue('A2', 'Nomor SPJ: {{NOMOR_SPJ}}');
        $sheet->setCellValue('A3', 'No. Bukti: {{NO_BUKTI}}');
        $sheet->fromArray(['No', 'Uraian', 'Volume', 'Satuan', 'Harga', 'Jumlah'], null, 'A5');
        $sheet->fromArray(['{{ITEM_NO}}', '{{ITEM_URAIAN}}', '{{ITEM_VOLUME}}', '{{ITEM_SATUAN}}', '{{ITEM_HARGA_SATUAN}}', '{{ITEM_JUMLAH}}'], null, 'A6');
        $sheet->setCellValue('E8', 'Total bruto');
        $sheet->setCellValue('F8', '{{NILAI_BRUTO}}');
        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();
    }
}
