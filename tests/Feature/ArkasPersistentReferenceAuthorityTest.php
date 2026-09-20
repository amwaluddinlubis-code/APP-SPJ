<?php

namespace Tests\Feature;

use App\Services\ArkasPersistentReferenceAuthority;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArkasPersistentReferenceAuthorityTest extends TestCase
{
    use RefreshDatabase;

    public function test_versioned_rows_persist_idempotently_and_invalid_acuan_is_quarantined(): void
    {
        $authority = new ArkasPersistentReferenceAuthority;
        $rows = [['id_barang' => '', 'tahun' => 2026], ['id_barang' => 'B-1', 'tahun' => 2026, 'nama_barang' => 'Valid']];

        self::assertSame(['accepted' => 1, 'quarantined' => 1], $authority->promote('ref_acuan_barang', $rows, '2026.09'));
        self::assertSame(['accepted' => 1, 'quarantined' => 1], $authority->promote('ref_acuan_barang', $rows, '2026.09'));
        self::assertCount(1, $authority->read('ref_acuan_barang'));
        self::assertCount(1, $authority->quarantines('ref_acuan_barang'));
    }

    public function test_same_natural_key_can_coexist_across_versions_and_conflict_fails_closed(): void
    {
        $authority = new ArkasPersistentReferenceAuthority;
        $row = ['id_ref_bku' => '1', 'bku' => 'BKU', 'kode_bku' => '1'];

        $authority->promote('ref_bku', [$row], '2026.09');
        $authority->promote('ref_bku', [$row], '2027.01');
        self::assertCount(2, $authority->read('ref_bku'));

        $this->expectExceptionMessage('Persistent central semantic conflict');
        $authority->promote('ref_bku', [['id_ref_bku' => '1', 'bku' => 'Changed', 'kode_bku' => '1']], '2026.09');
    }

    public function test_code_variant_and_quarantine_are_persisted_with_tenant_scope(): void
    {
        $authority = new ArkasPersistentReferenceAuthority;
        $rows = [
            ['id_kode' => '01.', 'parent_kode' => null, 'uraian_kode' => 'A', 'id_level_kode' => 1, 'tipe' => 0, 'tahun' => 2026, 'sumber_dana_id' => 1, 'bentuk_pendidikan_id' => 6],
            ['id_kode' => '01.', 'parent_kode' => null, 'uraian_kode' => 'B', 'id_level_kode' => 1, 'tipe' => 0, 'tahun' => 2026, 'sumber_dana_id' => 1, 'bentuk_pendidikan_id' => 6],
        ];

        $result = $authority->promoteCodeVariants('A', $rows, '2026.09');

        self::assertSame(0, $result['accepted']);
        self::assertSame(2, $result['quarantined']);
        self::assertSame([], $authority->readForTenant('ref_kode', 'B'));
        self::assertCount(2, $authority->quarantines('ref_kode'));
    }
}
