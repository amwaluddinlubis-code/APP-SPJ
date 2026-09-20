<?php

namespace Tests\Feature;

use App\Services\ArkasPersistentReferenceAuthority;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class ArkasPersistentReferenceRehearsalTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private array $globalTables = ['mst_wilayah', 'ref_level_wilayah', 'ref_negara', 'ref_jabatan', 'ref_jenis_instansi', 'ref_satuan', 'ref_periode', 'ref_level_kode', 'ref_indikator'];

    public function test_persistent_central_rehearsal_is_order_independent_and_idempotent(): void
    {
        $databases = $this->databases();
        if ($databases === []) {
            self::markTestSkipped('Set ARKAS_PARITY_DUMP_A/B/C for the persistent central rehearsal.');
        }

        $forward = $this->import($databases, ['A', 'B', 'C']);
        $forwardSnapshot = $this->snapshot();
        $this->import($databases, ['A', 'B', 'C']);
        self::assertSame($forwardSnapshot, $this->snapshot(), 'Repeated persistent import must be idempotent.');

        $this->clearAuthority();
        $reverse = $this->import($databases, ['C', 'A', 'B']);
        $reverseSnapshot = $this->snapshot();

        self::assertSame($forwardSnapshot, $reverseSnapshot, 'Forward and reverse persistent imports must converge.');
        self::assertGreaterThan(0, $forward['accepted']);
        self::assertGreaterThan(0, $forward['quarantined']);
        self::assertSame($this->invalidAcuanUniqueCount($databases), DB::table('central_reference_quarantines')->where(['source_table' => 'ref_acuan_barang', 'status' => 'QUARANTINED_INVALID_ID'])->count());
        self::assertSame(0, DB::table('central_reference_rows')->where('source_table', 'ref_acuan_barang')->whereRaw("json_extract(semantic_payload, '$.id_barang') IS NULL OR trim(json_extract(semantic_payload, '$.id_barang')) = ''")->count());
        self::assertSame(0, DB::table('central_code_applicabilities')->where(['tenant_key' => 'A', 'source_code' => '05.02.05.', 'fiscal_year' => '2025', 'fund_source' => '1', 'education_level' => '6'])->count(), 'The quarantined conflict context must not become central authority.');
        self::assertSame(0, DB::table('central_code_applicabilities')->where(['tenant_key' => 'B', 'source_code' => '05.02.05.', 'fiscal_year' => '2025', 'fund_source' => '1', 'education_level' => '6'])->count(), 'The quarantined conflict context must not leak to another tenant.');
    }

    /** @param array<string, PDO> $databases @param array<int, string> $order @return array{accepted: int, quarantined: int} */
    private function import(array $databases, array $order): array
    {
        $authority = new ArkasPersistentReferenceAuthority;
        $accepted = 0;
        $quarantined = 0;
        $tables = array_merge($this->globalTables, ['ref_rekening', 'ref_acuan_barang', 'ref_bku', 'ref_sumber_dana']);

        foreach ($order as $tenantKey) {
            foreach ($tables as $table) {
                $rows = $this->rows($databases[$tenantKey], $table);
                $result = $authority->promote($table, $rows, '2026.09', [], $tenantKey);
                $accepted += $result['accepted'];
                $quarantined += $result['quarantined'];

                if ($table === 'ref_sumber_dana') {
                    foreach ($rows as $row) {
                        $authority->attachExtension($table, $tenantKey, $row, '2026.09');
                    }
                }
            }

            $codeResult = $authority->promoteCodeVariants($tenantKey, $this->rows($databases[$tenantKey], 'ref_kode'), '2026.09', $tenantKey);
            $accepted += $codeResult['accepted'];
            $quarantined += $codeResult['quarantined'];
        }

        return compact('accepted', 'quarantined');
    }

    private function clearAuthority(): void
    {
        DB::table('central_reference_extensions')->delete();
        DB::table('central_code_applicabilities')->delete();
        DB::table('central_code_variants')->delete();
        DB::table('central_reference_quarantines')->delete();
        DB::table('central_reference_rows')->delete();
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        return [
            'rows' => DB::table('central_reference_rows')->orderBy('source_table')->orderBy('natural_key')->orderBy('version_key')->get(['source_table', 'natural_key', 'version_key', 'arkas_release', 'semantic_payload'])->map(fn (object $row): array => (array) $row)->all(),
            'variants' => DB::table('central_code_variants')->orderBy('source_code')->orderBy('variant_key')->orderBy('arkas_release')->get(['source_code', 'variant_key', 'arkas_release', 'parent_code', 'description', 'level_code', 'variant_type'])->map(fn (object $row): array => (array) $row)->all(),
            'applicabilities' => DB::table('central_code_applicabilities as applicability')->join('central_code_variants as variant', 'variant.id', '=', 'applicability.variant_id')->orderBy('applicability.tenant_key')->orderBy('applicability.source_code')->orderBy('applicability.fiscal_year')->orderBy('applicability.fund_source')->orderBy('applicability.education_level')->get(['applicability.tenant_key', 'applicability.source_code', 'applicability.arkas_release', 'applicability.fiscal_year', 'applicability.fund_source', 'applicability.education_level', 'applicability.applicability_payload', 'variant.variant_key'])->map(fn (object $row): array => (array) $row)->all(),
            'quarantines' => DB::table('central_reference_quarantines')->orderBy('source_table')->orderBy('status')->orderBy('context_key')->orderBy('payload_hash')->get(['source_table', 'status', 'context_key', 'reason', 'row_payload', 'payload_hash'])->map(fn (object $row): array => (array) $row)->all(),
            'extensions' => DB::table('central_reference_extensions')->orderBy('source_table')->orderBy('tenant_key')->orderBy('natural_key')->get(['source_table', 'tenant_key', 'natural_key', 'version_key', 'payload'])->map(fn (object $row): array => (array) $row)->all(),
        ];
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

    /** @return array<int, array<string, mixed>> */
    private function rows(PDO $database, string $table): array
    {
        return $database->query('SELECT * FROM '.$database->quote($table))->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param array<string, PDO> $databases */
    private function invalidAcuanUniqueCount(array $databases): int
    {
        $payloads = [];
        foreach ($databases as $database) {
            foreach ($database->query("SELECT * FROM ref_acuan_barang WHERE id_barang IS NULL OR trim(id_barang) = ''")->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $payloads[hash('sha256', json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))] = true;
            }
        }

        return count($payloads);
    }
}
