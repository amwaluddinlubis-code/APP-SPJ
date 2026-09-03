<?php

namespace App\Services;

class ArkasPipePayload
{
    public static function decode(string $output): array
    {
        $fields = [];
        $records = [];
        foreach (preg_split('/\R/', trim($output)) as $line) {
            $parts = explode('|', $line);
            $tag = array_shift($parts);
            if ($tag === 'FIELDS') {
                $fields = $parts;
            } if ($tag === 'DATA' && $fields) {
                $records[] = array_combine($fields, array_pad($parts, count($fields), ''));
            }
        }

        return $records;
    }

    public static function values(string $output): array
    {
        $data = [];
        foreach (preg_split('/\R/', trim($output)) as $line) {
            [$key,$value] = array_pad(explode('|', $line, 2), 2, '');
            if ($key !== '') {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    public static function pairs(string $output): array
    {
        $records = [];
        foreach (preg_split('/\R/', trim($output)) as $line) {
            [$id,$name] = array_pad(explode('|', $line, 2), 2, '');
            if ($id !== '' && $id !== 'SCHEMA') {
                $records[] = ['id' => $id, 'name' => $name];
            }
        }

        return $records;
    }
}
