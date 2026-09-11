<?php

use PhpOffice\PhpSpreadsheet\IOFactory;

require __DIR__.'/vendor/autoload.php';

$workbookPath = $argv[1] ?? null;

if ($workbookPath === null) {
    fwrite(STDERR, "Usage: php inspect_fixed_cells.php <path-to-workbook.xlsx>\n");
    exit(64);
}

if (! is_file($workbookPath)) {
    fwrite(STDERR, "Workbook not found: {$workbookPath}\n");
    exit(66);
}

try {
    $book = IOFactory::load($workbookPath);
} catch (Throwable $exception) {
    fwrite(STDERR, "Failed to load workbook: {$exception->getMessage()}\n");
    exit(65);
}

$sheetNames = [
    'TPL_SURAT_PESANAN',
    'TPL_BA_PEMERIKSAAN_PENERIMAAN',
    'TPL_RAB_PEMELIHARAAN',
];

$missingSheets = [];

foreach ($sheetNames as $name) {
    $sheet = $book->getSheetByName($name);

    if ($sheet === null) {
        $missingSheets[] = $name;
        fwrite(STDERR, "Sheet not found: {$name}\n");

        continue;
    }

    echo '== '.$name." ==\n";

    foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
        $value = $sheet->getCell($coordinate)->getValue();

        if ($value !== null && $value !== '') {
            echo $coordinate."\t".str_replace(["\r", "\n"], ' ', (string) $value)."\n";
        }
    }
}

$book->disconnectWorksheets();

exit($missingSheets === [] ? 0 : 2);
