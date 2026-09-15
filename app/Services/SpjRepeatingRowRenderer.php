<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\ReferenceHelper;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class SpjRepeatingRowRenderer
{
    /**
     * @param  callable(int): array<string, string>  $valuesForIndex
     */
    public function render(
        Worksheet $sheet,
        string $anchorPlaceholder,
        string $placeholderPrefix,
        int $recordCount,
        callable $valuesForIndex,
    ): void {
        $templateRows = $this->templateRows($sheet, $anchorPlaceholder);
        if ($templateRows === [] || $recordCount < 1) {
            return;
        }

        sort($templateRows);
        $sourceRow = $templateRows[0];
        $highestColumn = $sheet->getHighestColumn();
        $lastColumn = Coordinate::columnIndexFromString($highestColumn);
        $horizontalMerges = $this->horizontalMergeColumns($sheet, $sourceRow);

        if ($recordCount > count($templateRows)) {
            $extraRows = $recordCount - count($templateRows);
            $insertAt = max($templateRows) + 1;
            $sheet->insertNewRowBefore($insertAt, $extraRows);

            for ($offset = 0; $offset < $extraRows; $offset++) {
                $targetRow = $insertAt + $offset;
                $this->cloneTemplateRow(
                    $sheet,
                    $sourceRow,
                    $targetRow,
                    $lastColumn,
                    $horizontalMerges,
                );
                $templateRows[] = $targetRow;
            }
        }

        sort($templateRows);

        for ($index = 0; $index < $recordCount; $index++) {
            $replacements = [];
            foreach ($valuesForIndex($index + 1) as $key => $value) {
                $replacements['{{'.$key.'}}'] = $value;
            }

            $this->replaceRowPlaceholders(
                $sheet,
                $templateRows[$index],
                $lastColumn,
                $replacements,
            );
        }

        foreach (array_slice($templateRows, $recordCount) as $unusedRow) {
            $this->clearUnusedPlaceholders($sheet, $unusedRow, $lastColumn, $placeholderPrefix);
        }
    }

    /** @return array<int, int> */
    private function templateRows(Worksheet $sheet, string $anchorPlaceholder): array
    {
        $rows = [];

        foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
            $value = $sheet->getCell($coordinate)->getValue();
            if (is_string($value) && str_contains($value, $anchorPlaceholder)) {
                $rows[] = $sheet->getCell($coordinate)->getRow();
            }
        }

        return array_values(array_unique($rows));
    }

    /** @return array<int, array{0:int,1:int}> */
    private function horizontalMergeColumns(Worksheet $sheet, int $sourceRow): array
    {
        $ranges = [];

        foreach (array_values($sheet->getMergeCells()) as $range) {
            [[$startColumn, $startRow], [$endColumn, $endRow]] = Coordinate::rangeBoundaries($range);
            if ($startRow === $sourceRow && $endRow === $sourceRow) {
                $ranges[] = [$startColumn, $endColumn];
            }
        }

        return $ranges;
    }

    /**
     * @param  array<int, array{0:int,1:int}>  $horizontalMerges
     */
    private function cloneTemplateRow(
        Worksheet $sheet,
        int $sourceRow,
        int $targetRow,
        int $lastColumn,
        array $horizontalMerges,
    ): void {
        $sourceDimension = $sheet->getRowDimension($sourceRow);
        $targetDimension = $sheet->getRowDimension($targetRow);
        $targetDimension
            ->setRowHeight($sourceDimension->getRowHeight())
            ->setVisible($sourceDimension->getVisible())
            ->setOutlineLevel($sourceDimension->getOutlineLevel())
            ->setCollapsed($sourceDimension->getCollapsed())
            ->setZeroHeight($sourceDimension->getZeroHeight());

        for ($column = 1; $column <= $lastColumn; $column++) {
            $columnName = Coordinate::stringFromColumnIndex($column);
            $sourceCoordinate = $columnName.$sourceRow;
            $targetCoordinate = $columnName.$targetRow;
            $sourceCell = $sheet->getCell($sourceCoordinate);
            $value = $sourceCell->getValue();

            if (is_string($value) && str_starts_with($value, '=')) {
                $value = ReferenceHelper::getInstance()->updateFormulaReferences(
                    $value,
                    'A1',
                    0,
                    $targetRow - $sourceRow,
                    $sheet->getTitle(),
                );
            }

            $sheet->getCell($targetCoordinate)->setValue($value);
            $sheet->duplicateStyle($sheet->getStyle($sourceCoordinate), $targetCoordinate);

            if ($sourceCell->hasDataValidation()) {
                $sheet->setDataValidation($targetCoordinate, clone $sourceCell->getDataValidation());
            }
        }

        foreach ($horizontalMerges as [$startColumn, $endColumn]) {
            $range = Coordinate::stringFromColumnIndex($startColumn).$targetRow
                .':'.Coordinate::stringFromColumnIndex($endColumn).$targetRow;

            if (! isset($sheet->getMergeCells()[$range])) {
                $sheet->mergeCells($range);
            }
        }
    }

    /** @param array<string, string> $replacements */
    private function replaceRowPlaceholders(
        Worksheet $sheet,
        int $row,
        int $lastColumn,
        array $replacements,
    ): void {
        for ($column = 1; $column <= $lastColumn; $column++) {
            $coordinate = Coordinate::stringFromColumnIndex($column).$row;
            $value = $sheet->getCell($coordinate)->getValue();
            if (is_string($value)) {
                $sheet->getCell($coordinate)->setValue(strtr($value, $replacements));
            }
        }
    }

    private function clearUnusedPlaceholders(
        Worksheet $sheet,
        int $row,
        int $lastColumn,
        string $placeholderPrefix,
    ): void {
        $needle = '{{'.$placeholderPrefix;

        for ($column = 1; $column <= $lastColumn; $column++) {
            $coordinate = Coordinate::stringFromColumnIndex($column).$row;
            $value = $sheet->getCell($coordinate)->getValue();
            if (is_string($value) && str_contains($value, $needle)) {
                $sheet->getCell($coordinate)->setValue('');
            }
        }
    }
}
