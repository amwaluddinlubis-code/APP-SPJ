<?php

namespace Tests\Feature;

use App\Services\ArkasPersistentReferenceAuthority;
use App\Services\ArkasReferenceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArkasPersistentReferenceResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_central_compat_reads_persistent_rows_without_fallback(): void
    {
        $authority = new ArkasPersistentReferenceAuthority;
        $rows = [['id_ref_bku' => '1', 'bku' => 'BKU', 'kode_bku' => '1']];
        $authority->promote('ref_bku', $rows, '2026.09');

        self::assertSame([['BKU' => 'BKU', 'ID_REF_BKU' => '1', 'KODE_BKU' => '1']], (new ArkasReferenceResolver)->readPersistent(ArkasReferenceResolver::CENTRAL_COMPAT, $authority, 'ref_bku', $rows));
        $this->expectExceptionMessage('shadow parity mismatch');
        (new ArkasReferenceResolver)->readPersistent(ArkasReferenceResolver::CENTRAL_COMPAT, $authority, 'ref_bku', [['id_ref_bku' => '2']]);
    }

    public function test_context_read_returns_only_active_reference_year(): void
    {
        $authority = new ArkasPersistentReferenceAuthority;
        $authority->promote('ref_acuan_barang', [
            ['id_barang' => 'ITEM-2025', 'nama_barang' => 'Lama', 'tahun' => 2025, 'expired_date' => '2025-12-31'],
            ['id_barang' => 'ITEM-2026', 'nama_barang' => 'Aktif', 'tahun' => 2026, 'expired_date' => null],
        ], '2026.09');

        self::assertSame(
            ['ITEM-2026'],
            array_column($authority->read('ref_acuan_barang', ['year' => 2026]), 'id_barang'),
        );
    }

    public function test_code_context_read_returns_only_active_year_and_fund(): void
    {
        $authority = new ArkasPersistentReferenceAuthority;
        $authority->promoteCodeVariants('school-1', [
            ['id_kode' => '01', 'parent_kode' => null, 'uraian_kode' => 'Program 2025', 'id_level_kode' => '1', 'tipe' => 'P', 'id_ref_kode' => 'R-2025', 'tahun' => 2025, 'sumber_dana_id' => 1, 'bentuk_pendidikan_id' => 6],
            ['id_kode' => '01', 'parent_kode' => null, 'uraian_kode' => 'Program 2026', 'id_level_kode' => '1', 'tipe' => 'P', 'id_ref_kode' => 'R-2026', 'tahun' => 2026, 'sumber_dana_id' => 1, 'bentuk_pendidikan_id' => 6],
        ], '2026.09');

        self::assertSame(
            ['R-2026'],
            array_column($authority->readForTenant('ref_kode', 'school-1', ['year' => 2026, 'fund_source_id' => 1]), 'id_ref_kode'),
        );
    }
}
