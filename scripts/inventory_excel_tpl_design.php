<?php

use PhpOffice\PhpSpreadsheet\IOFactory;

require __DIR__.'/../vendor/autoload.php';

$book = IOFactory::load(__DIR__.'/../public/assets/TPL_DESIGN.xlsx');
foreach ($book->getWorksheetIterator() as $sheet) {
    echo '['.$sheet->getTitle().']'.PHP_EOL;
    foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
        $value = $sheet->getCell($coordinate)->getValue();
        if (is_string($value) && preg_match('/[\{\[][^}\]]+[}\]]/', $value)) {
            echo $coordinate.'='.str_replace(["\r", "\n"], ' ', $value).PHP_EOL;
        }
    }
}
