<?php

namespace Tests\Feature;

use App\Services\ArkasReferenceParityService;
use PDO;
use Tests\TestCase;

class ArkasCentralReferenceParityRehearsalTest extends TestCase
{
    /** @var array<int, string> */
    private array $confirmedTables = [
        'mst_wilayah',
        'ref_level_wilayah',
        'ref_negara',
        'ref_jabatan',
        'ref_jenis_instansi',
        'ref_satuan',
        'ref_periode',
        'ref_level_kode',
        'ref_indikator',
    ];

    public function test_three_school_reference_parity_and_import_order_are_deterministic(): void
    {
        $paths = [
            'A' => (string) env('ARKAS_PARITY_DUMP_A', ''),
            'B' => (string) env('ARKAS_PARITY_DUMP_B', ''),
            'C' => (string) env('ARKAS_PARITY_DUMP_C', ''),
        ];
        if (count(array_filter($paths, 'is_file')) !== 3) {
            self::markTestSkipped('Set ARKAS_PARITY_DUMP_A/B/C for the three-school parity rehearsal.');
        }

        $databases = [];
        foreach ($paths as $name => $path) {
            $databases[$name] = $this->loadDump($path);
        }

        $parity = new ArkasReferenceParityService;
        foreach ($this->confirmedTables as $table) {
            $sources = [];
            foreach ($databases as $name => $database) {
                $sources[$name] = [
                    'schema' => $this->schema($database, $table),
                    'rows' => $this->rows($database, $table),
                ];
            }

            $keyColumns = $this->primaryKey($databases['A'], $table);
            $result = $parity->compare($sources, $keyColumns);

            self::assertSame('GLOBAL_REFERENCE_CONFIRMED', $result['status'], $table);
            self::assertSame(0, $result['different_common_rows'], $table);
            self::assertSame([], $result['only_rows'], $table);
            self::assertSame(0, array_sum($result['duplicate_keys']), $table);
            self::assertSame($result['rowset_hashes']['A'], $result['rowset_hashes']['B'], $table);
            self::assertSame($result['rowset_hashes']['A'], $result['rowset_hashes']['C'], $table);

            $firstOrder = $this->importOrder($sources, $keyColumns, ['A', 'B', 'C']);
            $secondOrder = $this->importOrder($sources, $keyColumns, ['C', 'A', 'B']);
            self::assertSame($firstOrder, $secondOrder, $table);
        }
    }

    private function loadDump(string $path): PDO
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec(file_get_contents($path));

        return $database;
    }

    /** @return array<int, array<string, mixed>> */
    private function schema(PDO $database, string $table): array
    {
        return $database->query('PRAGMA table_info('.$database->quote($table).')')->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int, string> */
    private function primaryKey(PDO $database, string $table): array
    {
        $primary = [];
        foreach ($this->schema($database, $table) as $column) {
            if ((int) $column['pk'] > 0) {
                $primary[(int) $column['pk']] = $column['name'];
            }
        }
        ksort($primary);

        return array_values($primary);
    }

    /** @return array<int, array<string, mixed>> */
    private function rows(PDO $database, string $table): array
    {
        return $database->query('SELECT * FROM '.$database->quote($table))->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param array<string, array{schema: array<int, array<string, mixed>>, rows: array<int, array<string, mixed>>}> $sources */
    /** @param array<int, string> $keyColumns */
    /** @param array<int, string> $order */
    private function importOrder(array $sources, array $keyColumns, array $order): string
    {
        $merged = [];
        foreach ($order as $sourceName) {
            foreach ($sources[$sourceName]['rows'] as $row) {
                $identity = [];
                foreach ($keyColumns as $column) {
                    $identity[] = (string) $row[$column];
                }
                $key = json_encode($identity, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $value = json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (isset($merged[$key]) && $merged[$key] !== $value) {
                    self::fail('Central rehearsal conflict for '.$key);
                }
                $merged[$key] = $value;
            }
        }
        ksort($merged, SORT_STRING);

        return hash('sha256', implode("\n", $merged));
    }
}
