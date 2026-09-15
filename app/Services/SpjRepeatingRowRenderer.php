<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Cell\ReferenceHelper;
use PhpOffice\PhpSpreadsheet\Style\Style;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class SpjRepeatingRowRenderer
{
    /**
     * @param  array<int, array{marker: string, key: string}>  $columns
     * @param  array<int, array<string, mixed>>  $records
     */
    public function render(Worksheet $sheet, array $columns, array $records): void
    {
        if ($columns === []) {
            return;
        }

        $templateRows = $this->findTemplateRows($sheet, $columns);

        if ($templateRows === []) {
            return;
        }

        if ($records === []) {
            foreach ($templateRows as $templateRow) {
                $this->clearMarkersOnRow($sheet, $templateRow, $columns);
            }

            return;
        }

        $lastTemplateRow = max($templateRows);
        $rowAssignments = [];
        $recordIndex = 0;

        foreach ($templateRows as $templateRow) {
            if ($recordIndex >= count($records)) {
                $this->clearMarkersOnRow($sheet, $templateRow, $columns);

                continue;
            }

            $rowAssignments[] = [
                'row' => $templateRow,
                'record' => $records[$recordIndex],
            ];
            $recordIndex++;
        }

        $remainingCount = count($records) - $recordIndex;

        if ($remainingCount > 0) {
            $sheet->insertNewRowBefore($lastTemplateRow + 1, $remainingCount);

            for ($offset = 1; $offset <= $remainingCount; $offset++) {
                $targetRow = $lastTemplateRow + $offset;
                $this->copyTemplateRow($sheet, $lastTemplateRow, $targetRow);
                $rowAssignments[] = [
                    'row' => $targetRow,
                    'record' => $records[$recordIndex],
                ];
                $recordIndex++;
            }
        }

        foreach ($rowAssignments as $assignment) {
            $this->fillRecordRow(
                $sheet,
                $assignment['row'],
                $columns,
                $assignment['record'],
            );
        }
    }

    /**
     * @param  array<int, array{marker: string, key: string}>  $columns
     * @return array<int, int>
     */
    private function findTemplateRows(Worksheet $sheet, array $columns): array
    {
        $markers = array_values(array_unique(array_map(
            static fn (array $column): string => trim((string) ($column['marker'] ?? '')),
            $columns,
        )));
        $markers = array_values(array_filter($markers, static fn (string $marker): bool => $marker !== ''));

        if ($markers === []) {
            return [];
        }

        $rows = [];
        $maxRow = $sheet->getHighestDataRow();
        $highestDataColumn = $sheet->getHighestDataColumn();
        $maxColumn = Coordinate::columnIndexFromString($highestDataColumn);

        for ($row = 1; $row <= $maxRow; $row++) {
            $found = false;

            for ($column = 1; $column <= $maxColumn; $column++) {
                $value = (string) ($sheet->getCell([$column, $row])->getValue() ?? '');

                foreach ($markers as $marker) {
                    if (str_contains($value, '{{'.$marker.'}}')) {
                        $rows[] = $row;
                        $found = true;

                        break 2;
                    }
                }
            }

            if ($found) {
                continue;
            }
        }

        return array_values(array_unique($rows));
    }

    /**
     * @param  array<int, array{marker: string, key: string}>  $columns
     */
    private function clearMarkersOnRow(Worksheet $sheet, int $row, array $columns): void
    {
        $maxColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        for ($column = 1; $column <= $maxColumn; $column++) {
            $cell = $sheet->getCell([$column, $row]);
            $value = (string) ($cell->getValue() ?? '');
            $updated = $value;

            foreach ($columns as $definition) {
                $marker = trim((string) ($definition['marker'] ?? ''));

                if ($marker === '') {
                    continue;
                }

                $updated = str_replace('{{'.$marker.'}}', '', $updated);
            }

            if ($updated !== $value) {
                $cell->setValue($updated);
            }
        }
    }

    /**
     * @param  array<int, array{marker: string, key: string}>  $columns
     * @param  array<string, mixed>  $record
     */
    private function fillRecordRow(Worksheet $sheet, int $row, array $columns, array $record): void
    {
        $maxColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        for ($column = 1; $column <= $maxColumn; $column++) {
            $cell = $sheet->getCell([$column, $row]);
            $value = (string) ($cell->getValue() ?? '');
            $updated = $value;

            foreach ($columns as $definition) {
                $marker = trim((string) ($definition['marker'] ?? ''));
                $key = trim((string) ($definition['key'] ?? ''));

                if ($marker === '' || $key === '') {
                    continue;
                }

                $replacement = $record[$key] ?? '';
                $replacement = is_scalar($replacement) ? (string) $replacement : '';

                $updated = str_replace('{{'.$marker.'}}', $replacement, $updated);
            }

            if ($updated !== $value) {
                $cell->setValueExplicit($updated, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
        }
    }

    private function copyTemplateRow(Worksheet $sheet, int $sourceRow, int $targetRow): void
    {
        $maxColumn = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($column = 1; $column <= $maxColumn; $column++) {
            $sourceCell = $sheet->getCell([$column, $sourceRow]);
            $targetCell = $sheet->getCell([$column, $targetRow]);

            $targetCell->setValue(
                $this->translateFormulaForCopiedRow(
                    $sourceCell->getValue(),
                    $sourceCell->getCoordinate(),
                    $targetCell->getCoordinate(),
                ),
            );

            if ($sourceCell->hasStyle()) {
                $targetCell->setXfIndex($sourceCell->getXfIndex());
            } else {
                $targetCell->setStyle(new Style);
            }

            if ($sourceCell->hasDataValidation()) {
                $targetCell->setDataValidation(clone $sourceCell->getDataValidation());
            } else {
                $targetCell->setDataValidation(new DataValidation);
            }
        }

        $sourceDimension = $sheet->getRowDimension($sourceRow);
        $targetDimension = $sheet->getRowDimension($targetRow);
        $targetDimension->setRowHeight($sourceDimension->getRowHeight());
        $targetDimension->setVisible($sourceDimension->getVisible());
        $targetDimension->setCollapsed($sourceDimension->getCollapsed());
        $targetDimension->setOutlineLevel($sourceDimension->getOutlineLevel());

        foreach ($sheet->getMergeCells() as $mergeRange) {
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
