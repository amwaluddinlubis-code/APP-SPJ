<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\ReferenceHelper;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class SpjRepeatingRowRenderer
{
    /**
     * Render a one-dimensional repeating-row block without rebuilding the
     * worksheet. Existing template rows are consumed first; overflow rows are
     * inserted only when the record count exceeds the template capacity.
     *
     * @param  callable(int): array<string, mixed>  $valuesForIndex
     */
    public function render(
        Worksheet $sheet,
        string $anchorMarker,
        string $markerPrefix,
        int $recordCount,
        callable $valuesForIndex,
    ): void {
        $anchorMarker = trim($anchorMarker);
        $markerPrefix = trim($markerPrefix);

        if ($anchorMarker === '' || $markerPrefix === '') {
            return;
        }

        $templateRows = $this->findTemplateRows($sheet, $anchorMarker);

        if ($templateRows === []) {
            return;
        }

        $recordCount = max(0, $recordCount);

        if ($recordCount === 0) {
            foreach ($templateRows as $templateRow) {
                $this->clearMarkersOnRow($sheet, $templateRow, $markerPrefix);
            }

            return;
        }

        $lastTemplateRow = max($templateRows);
        $rowAssignments = [];
        $recordIndex = 1;

        foreach ($templateRows as $templateRow) {
            if ($recordIndex > $recordCount) {
                $this->clearMarkersOnRow($sheet, $templateRow, $markerPrefix);

                continue;
            }

            $rowAssignments[] = [
                'row' => $templateRow,
                'record_index' => $recordIndex,
            ];
            $recordIndex++;
        }

        $remainingCount = $recordCount - ($recordIndex - 1);

        if ($remainingCount > 0) {
            $sheet->insertNewRowBefore($lastTemplateRow + 1, $remainingCount);

            for ($offset = 1; $offset <= $remainingCount; $offset++) {
                $targetRow = $lastTemplateRow + $offset;
                $this->copyTemplateRow($sheet, $lastTemplateRow, $targetRow);
                $rowAssignments[] = [
                    'row' => $targetRow,
                    'record_index' => $recordIndex,
                ];
                $recordIndex++;
            }
        }

        foreach ($rowAssignments as $assignment) {
            $values = $valuesForIndex($assignment['record_index']);
            if (! is_array($values)) {
                throw new RuntimeException('Repeating-row resolver harus menghasilkan array nilai placeholder.');
            }

            $this->fillRecordRow($sheet, $assignment['row'], $markerPrefix, $values);
        }
    }

    /** @return array<int, int> */
    private function findTemplateRows(Worksheet $sheet, string $anchorMarker): array
    {
        $rows = [];
        $maxRow = $sheet->getHighestDataRow();
        $maxColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        for ($row = 1; $row <= $maxRow; $row++) {
            for ($column = 1; $column <= $maxColumn; $column++) {
                $value = (string) ($sheet->getCell([$column, $row])->getValue() ?? '');

                if (str_contains($value, $anchorMarker)) {
                    $rows[] = $row;

                    break;
                }
            }
        }

        return array_values(array_unique($rows));
    }

    private function clearMarkersOnRow(Worksheet $sheet, int $row, string $markerPrefix): void
    {
        $maxColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $pattern = '/\{\{'.preg_quote($markerPrefix, '/').'[A-Za-z0-9_]+\}\}/u';

        for ($column = 1; $column <= $maxColumn; $column++) {
            $cell = $sheet->getCell([$column, $row]);
            $value = $cell->getValue();

            if (! is_string($value)) {
                continue;
            }

            $updated = preg_replace($pattern, '', $value) ?? $value;
            if ($updated !== $value) {
                $cell->setValue($updated);
            }
        }
    }

    /** @param array<string, mixed> $values */
    private function fillRecordRow(Worksheet $sheet, int $row, string $markerPrefix, array $values): void
    {
        $maxColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $replacements = [];

        foreach ($values as $marker => $replacement) {
            $normalizedMarker = trim((string) $marker);
            if ($normalizedMarker === '' || ! str_starts_with($normalizedMarker, $markerPrefix)) {
                continue;
            }

            $replacements['{{'.$normalizedMarker.'}}'] = is_scalar($replacement) ? (string) $replacement : '';
        }

        if ($replacements === []) {
            return;
        }

        for ($column = 1; $column <= $maxColumn; $column++) {
            $cell = $sheet->getCell([$column, $row]);
            $value = $cell->getValue();

            if (! is_string($value)) {
                continue;
            }

            $updated = strtr($value, $replacements);
            if ($updated !== $value) {
                $cell->setValueExplicit($updated, DataType::TYPE_STRING);
            }
        }
    }

    private function copyTemplateRow(Worksheet $sheet, int $sourceRow, int $targetRow): void
    {
        $maxColumn = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($column = 1; $column <= $maxColumn; $column++) {
            $sourceCoordinate = Coordinate::stringFromColumnIndex($column).$sourceRow;
            $targetCoordinate = Coordinate::stringFromColumnIndex($column).$targetRow;
            $sourceCell = $sheet->getCell($sourceCoordinate);
            $targetCell = $sheet->getCell($targetCoordinate);

            $targetCell->setValue(
                $this->translateFormulaForCopiedRow(
                    $sourceCell->getValue(),
                    $sourceCoordinate,
                    $targetCoordinate,
                ),
            );
            $targetCell->setXfIndex($sourceCell->getXfIndex());

            if ($sourceCell->hasDataValidation()) {
                $targetCell->setDataValidation(clone $sourceCell->getDataValidation());
            }
        }

        $sourceDimension = $sheet->getRowDimension($sourceRow);
        $targetDimension = $sheet->getRowDimension($targetRow);
        $targetDimension->setRowHeight($sourceDimension->getRowHeight());
        $targetDimension->setVisible($sourceDimension->getVisible());
        $targetDimension->setCollapsed($sourceDimension->getCollapsed());
        $targetDimension->setOutlineLevel($sourceDimension->getOutlineLevel());

        foreach (array_values($sheet->getMergeCells()) as $mergeRange) {
            [$start, $end] = explode(':', $mergeRange, 2);
            [$startColumn, $startRow] = Coordinate::indexesFromString($start);
            [$endColumn, $endRow] = Coordinate::indexesFromString($end);

            if ($startRow !== $sourceRow || $endRow !== $sourceRow) {
                continue;
            }

            $targetRange = sprintf(
                '%s%d:%s%d',
                Coordinate::stringFromColumnIndex($startColumn),
                $targetRow,
                Coordinate::stringFromColumnIndex($endColumn),
                $targetRow,
            );

            if (! in_array($targetRange, $sheet->getMergeCells(), true)) {
                $sheet->mergeCells($targetRange);
            }
        }
    }

    private function translateFormulaForCopiedRow(mixed $value, string $sourceCoordinate, string $targetCoordinate): mixed
    {
        if (! is_string($value) || ! str_starts_with($value, '=')) {
            return $value;
        }

        try {
            return ReferenceHelper::getInstance()->updateFormulaReferences(
                $value,
                'A1',
                0,
                0,
                $targetCoordinate,
                $sourceCoordinate,
            );
        } catch (RuntimeException) {
            return $value;
        }
    }
}
