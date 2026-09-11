<?php

use PhpOffice\PhpSpreadsheet\IOFactory;

require 'D:/lrvProject/spj-bosp-web-clean/vendor/autoload.php';
$book = IOFactory::load('D:/PC Data/Downloads/TPL_DESIGN_READY_FOR_APP_TEST_fixed.xlsx');
foreach (['TPL_SURAT_PESANAN', 'TPL_BA_PEMERIKSAAN_PENERIMAAN', 'TPL_RAB_PEMELIHARAAN'] as $name) {
    $sheet = $book->getSheetByName($name);
    echo '== '.$name." ==\n";
    foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
        $value = $sheet->getCell($coordinate)->getValue();
        if ($value !== null && $value !== '') {
            echo $coordinate."\t".str_replace(["\r", "\n"], ' ', (string) $value)."\n";
        }
    }
}
$book->disconnectWorksheets();
