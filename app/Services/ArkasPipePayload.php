<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ArkasPipePayload
{
    public static function decode(string $output, string $command = 'unknown'): array
    {
        $fields = [];
        $records = [];
        foreach (preg_split('/\R/', trim($output)) as $lineNumber => $line) {
            $parts = explode('|', $line);
            $tag = array_shift($parts);
            if ($tag === 'FIELDS') {
                $fields = array_map(
                    fn (string $field, int $index): string => self::normalizeText($field, $command, $lineNumber + 1, 'field_'.$index),
                    $parts,
                    array_keys($parts),
                );
            }
            if ($tag === 'DATA' && $fields) {
                $values = array_pad($parts, count($fields), '');
                $normalized = [];
                foreach ($fields as $index => $field) {
                    $normalized[$field] = self::normalizeText(
                        (string) ($values[$index] ?? ''),
                        $command,
                        $lineNumber + 1,
                        $field,
                    );
                }
                $records[] = $normalized;
            }
        }

        return $records;
    }

    public static function values(string $output, string $command = 'unknown'): array
    {
        $data = [];
        foreach (preg_split('/\R/', trim($output)) as $lineNumber => $line) {
            [$key, $value] = array_pad(explode('|', $line, 2), 2, '');
            $key = self::normalizeText($key, $command, $lineNumber + 1, 'key');
            if ($key !== '') {
                $data[$key] = self::normalizeText($value, $command, $lineNumber + 1, $key);
            }
        }

        return $data;
    }

    public static function pairs(string $output, string $command = 'unknown'): array
    {
        $records = [];
        foreach (preg_split('/\R/', trim($output)) as $lineNumber => $line) {
            [$id, $name] = array_pad(explode('|', $line, 2), 2, '');
            $id = self::normalizeText($id, $command, $lineNumber + 1, 'id');
            if ($id !== '' && $id !== 'SCHEMA') {
                $records[] = [
                    'id' => $id,
                    'name' => self::normalizeText($name, $command, $lineNumber + 1, 'name'),
                ];
            }
        }

        return $records;
    }

    /** @return array<int, string> */
    public static function lines(string $output, string $command = 'unknown'): array
    {
        $lines = preg_split('/\R/', trim($output)) ?: [];

        return array_map(
            fn (string $line, int $index): string => self::normalizeText($line, $command, $index + 1, 'line'),
            $lines,
            array_keys($lines),
        );
    }

    private static function normalizeText(string $value, string $command, int $line, string $field): string
    {
        if ($value === '' || mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $converted = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        if (! mb_check_encoding($converted, 'UTF-8')) {
            $converted = mb_scrub($value, 'UTF-8');
        }

        Log::warning('ARKAS Bridge mengirim teks non-UTF-8; nilai dinormalisasi.', [
            'command' => $command,
            'line' => $line,
            'field' => $field,
            'source_encoding' => 'Windows-1252/fallback-scrub',
        ]);

        return $converted;
    }
}
