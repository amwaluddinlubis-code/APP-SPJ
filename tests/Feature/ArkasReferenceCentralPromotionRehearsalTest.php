<?php

namespace Tests\Feature;

use App\Services\ArkasReferencePromotionService;
use PDO;
use RuntimeException;
use Tests\TestCase;

class ArkasReferenceCentralPromotionRehearsalTest extends TestCase
{
    /** @var array<int, string> */
    private array $confirmedTables = ['mst_wilayah', 'ref_level_wilayah', 'ref_negara', 'ref_jabatan', 'ref_jenis_instansi', 'ref_satuan', 'ref_periode', 'ref_level_kode', 'ref_indikator'];

    /** @var array<int, string> */
    private array $versionedTables = ['ref_rekening', 'ref_bku'];

    public function test_three_dump_central_promotion_is_idempotent_and_order_independent(): void
    {
        $databases = $this->databases();
        if ($databases === []) {
            self::markTestSkipped('Set ARKAS_PARITY_DUMP_A/B/C for the central promotion rehearsal.');
        }

        foreach ($this->confirmedTables as $table) {
            $first = $this->promoteTable($databases, $table, ['A', 'B', 'C']);
            $reverse = $this->promoteTable($databases, $table, ['C', 'B', 'A']);
            self::assertSame($this->canonical($first->readCentral($table)), $this->canonical($reverse->readCentral($table)), $table);
            self::assertSame($this->validRowCount($databases['A'], $table), count($first->readCentral($table)), $table);
        }
        foreach ($this->versionedTables as $table) {
            $first = $this->promoteTable($databases, $table, ['A', 'B', 'C']);
            $reverse = $this->promoteTable($databases, $table, ['C', 'B', 'A']);
            self::assertSame($this->canonical($first->readCentral($table)), $this->canonical($reverse->readCentral($table)), $table);
            self::assertNotEmpty($first->readCentral($table), $table);
        }
    }

    public function test_hybrid_base_and_tenant_extensions_are_isolated_and_fail_closed_for_orphans(): void
    {
        $databases = $this->databases();
        if ($databases === []) {
            self::markTestSkipped('Set ARKAS_PARITY_DUMP_A/B/C for the hybrid rehearsal.');
        }

        $promotion = new ArkasReferencePromotionService;
        foreach ($databases as $name => $database) {
            $promotion->promote('ref_sumber_dana', $this->rows($database, 'ref_sumber_dana'), '2026.09');
            $promotion->attachTenantExtension('ref_sumber_dana', $name, $this->rows($database, 'ref_sumber_dana'), '2026.09', ['fiscal_year' => 2026]);
        }

        self::assertNotEmpty($promotion->readTenantExtension('ref_sumber_dana', 'A'));
        self::assertSame([], $promotion->readTenantExtension('ref_sumber_dana', 'UNKNOWN'));

        $this->expectException(RuntimeException::class);
        $promotion->attachTenantExtension('ref_sumber_dana', 'A', [['kode' => 'ORPHAN', 'nama_sumber_dana' => 'Orphan']], '2026.09', ['fiscal_year' => 2026]);
    }

    public function test_ref_kode_unexplained_same_context_conflict_fails_closed(): void
    {
        $databases = $this->databases();
        if ($databases === []) {
            self::markTestSkipped('Set ARKAS_PARITY_DUMP_A/B/C for the hybrid rehearsal.');
        }

        $this->expectExceptionMessage('contradictory semantic definition');
        (new ArkasReferencePromotionService)->promoteCodeVariants('A', $this->rows($databases['A'], 'ref_kode'), '2026.09');
    }

    public function test_acuan_barang_invalid_source_rows_are_rejected_before_promotion(): void
    {
        $databases = $this->databases();
        if ($databases === []) {
            self::markTestSkipped('Set ARKAS_PARITY_DUMP_A/B/C for the central promotion rehearsal.');
        }

        $database = $databases['A'];
        $invalid = $database->query("SELECT * FROM ref_acuan_barang WHERE trim(id_barang) = '' OR id_barang IS NULL LIMIT 1")->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(1, $invalid);

        $promotion = new ArkasReferencePromotionService;
        $report = $promotion->promoteReport('ref_acuan_barang', $invalid, '2026.09');
        self::assertSame(0, $report['accepted']);
        self::assertSame(1, $report['diagnostics']['quarantined']);
        self::assertSame('QUARANTINED_INVALID_ID', $report['quarantined'][0]['status']);
        self::assertSame([], $promotion->readCentral('ref_acuan_barang'));

        $valid = $database->query("SELECT * FROM ref_acuan_barang WHERE trim(id_barang) <> '' LIMIT 25")->fetchAll(PDO::FETCH_ASSOC);
        $validReport = $promotion->promoteReport('ref_acuan_barang', $valid, '2026.09');
        self::assertSame(25, $validReport['accepted']);
        self::assertSame(0, $validReport['diagnostics']['quarantined']);
    }

    /** @return array<string, PDO> */
    private function databases(): array
    {
        $paths = ['A' => (string) (getenv('ARKAS_PARITY_DUMP_A') ?: env('ARKAS_PARITY_DUMP_A', '')), 'B' => (string) (getenv('ARKAS_PARITY_DUMP_B') ?: env('ARKAS_PARITY_DUMP_B', '')), 'C' => (string) (getenv('ARKAS_PARITY_DUMP_C') ?: env('ARKAS_PARITY_DUMP_C', ''))];
        if (count(array_filter($paths, 'is_file')) !== 3) {
            return [];
        }

        $databases = [];
        foreach ($paths as $name => $path) {
            $databases[$name] = new PDO('sqlite::memory:');
            $databases[$name]->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $databases[$name]->exec(file_get_contents($path));
        }

        return $databases;
    }

    /** @param array<string, PDO> $databases @param array<int, string> $order */
    private function promoteTable(array $databases, string $table, array $order): ArkasReferencePromotionService
    {
        $promotion = new ArkasReferencePromotionService;
        foreach ($order as $name) {
            $rows = $this->rows($databases[$name], $table);
            $promotion->promote($table, $rows, '2026.09');
        }

        return $promotion;
    }

    /** @return array<int, array<string, mixed>> */
    private function rows(PDO $database, string $table): array
    {
        return $database->query('SELECT * FROM '.$database->quote($table))->fetchAll(PDO::FETCH_ASSOC);
    }

    private function rowCount(PDO $database, string $table): int
    {
        return (int) $database->query('SELECT COUNT(*) FROM '.$database->quote($table))->fetchColumn();
    }

    private function validRowCount(PDO $database, string $table): int
    {
        if ($table !== 'ref_acuan_barang') {
            return $this->rowCount($database, $table);
        }

        return (int) $database->query("SELECT COUNT(*) FROM {$database->quote($table)} WHERE trim(id_barang) <> ''")->fetchColumn();
    }

    private function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonical($item);
        }
        if (array_keys($value) !== range(0, count($value) - 1)) {
            ksort($value, SORT_STRING);
        }

        return $value;
    }
}
