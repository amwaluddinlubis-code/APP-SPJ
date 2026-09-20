<?php

namespace Tests\Unit;

use App\Services\ArkasCentralReferenceBridge;
use App\Services\ArkasMirrorManifest;
use App\Services\ArkasTenantDataBridge;
use PHPUnit\Framework\TestCase;

class ArkasMirrorManifestTest extends TestCase
{
    public function test_manifest_is_explicit_and_unknown_tables_are_not_importable(): void
    {
        $manifest = new ArkasMirrorManifest;

        self::assertSame([
            'anggaran',
            'kas_umum',
            'kas_umum_nota',
            'kas_umum_nota_pajak',
            'pegawai',
            'ptk',
            'rapbs',
            'rapbs_periode',
            'sekolah_penjab',
            'mst_sekolah',
        ], $manifest->importableTables([
            'unknown_table',
            'rapbs',
            'kas_umum',
            'mst_sekolah',
            'ref_kode',
            'anggaran',
            'kas_umum_nota',
            'kas_umum_nota_pajak',
            'pegawai',
            'ptk',
            'rapbs_periode',
            'sekolah_penjab',
        ]));
        self::assertSame(ArkasTenantDataBridge::class, $manifest->entry('kas_umum')['bridge']);
        self::assertSame('TENANT', $manifest->entry('kas_umum')['category']);
        self::assertSame('HYBRID', $manifest->entry('ref_kode')['category']);
        self::assertFalse($manifest->entry('ref_kode')['enabled']);
        self::assertSame(ArkasCentralReferenceBridge::class, $manifest->entry('ref_kode')['bridge']);
        self::assertSame('COMPOSITE', $manifest->entry('kas_umum_nota_pajak')['key_strategy']);
        self::assertSame(['id_kas_nota', 'ntpn'], $manifest->entry('kas_umum_nota_pajak')['key_columns']);
        self::assertSame('OPTIONAL', $manifest->entry('pegawai')['availability']);
        self::assertSame(['id_kas_umum'], $manifest->entry('kas_umum')['key_columns']);
    }

    public function test_manifest_rejects_tables_without_an_explicit_contract(): void
    {
        $manifest = new ArkasMirrorManifest;

        self::assertSame([], $manifest->importableTables(['custom_table', 'another_table']));
        $this->expectException(\InvalidArgumentException::class);
        $manifest->entry('custom_table');
    }

    public function test_drifted_reference_tables_have_explicit_ownership_and_version_contracts(): void
    {
        $manifest = new ArkasMirrorManifest;

        self::assertSame('HYBRID_CENTRAL_BASE_TENANT_EXTENSION', $manifest->entry('ref_sumber_dana')['classification']);
        self::assertSame(['kode'], $manifest->entry('ref_sumber_dana')['canonical_key']);
        self::assertSame(['arkas_release', 'fiscal_year'], $manifest->entry('ref_sumber_dana')['version_dimensions']);
        self::assertSame('CENTRAL_BASE_PLUS_TENANT_EXTENSION', $manifest->entry('ref_sumber_dana')['target_scope']);

        self::assertSame('VERSIONED_GLOBAL_REFERENCE', $manifest->entry('ref_rekening')['classification']);
        self::assertSame(['kode_rekening', 'tahun'], $manifest->entry('ref_rekening')['key_columns']);
        self::assertSame(['tahun', 'arkas_release'], $manifest->entry('ref_rekening')['version_dimensions']);

        self::assertSame('VERSIONED_GLOBAL_REFERENCE', $manifest->entry('ref_acuan_barang')['classification']);
        self::assertSame(['id_barang', 'tahun'], $manifest->entry('ref_acuan_barang')['key_columns']);

        self::assertSame('HYBRID_CENTRAL_BASE_TENANT_EXTENSION', $manifest->entry('ref_kode')['classification']);
        self::assertSame(['id_kode'], $manifest->entry('ref_kode')['canonical_key']);
        self::assertSame(['arkas_release', 'tahun', 'sumber_dana_id', 'bentuk_pendidikan_id'], $manifest->entry('ref_kode')['version_dimensions']);

        self::assertSame('VERSIONED_GLOBAL_REFERENCE', $manifest->entry('ref_bku')['classification']);
        self::assertSame(['id_ref_bku'], $manifest->entry('ref_bku')['canonical_key']);

        self::assertSame('OPTIONAL_TENANT_REFERENCE', $manifest->entry('ref_sumber_dana_sekolah')['classification']);
        self::assertSame(ArkasTenantDataBridge::class, $manifest->entry('ref_sumber_dana_sekolah')['bridge']);
        self::assertTrue($manifest->entry('ref_sumber_dana_sekolah')['enabled']);
        self::assertSame('OPTIONAL', $manifest->entry('ref_sumber_dana_sekolah')['availability']);
        self::assertSame(['id_ref_sumber_dana', 'tahun'], $manifest->entry('ref_sumber_dana_sekolah')['key_columns']);
    }

    public function test_unresolved_reference_tables_are_not_silently_enabled_for_central_cutover(): void
    {
        $manifest = new ArkasMirrorManifest;

        foreach (['ref_sumber_dana', 'ref_rekening', 'ref_acuan_barang', 'ref_kode', 'ref_bku'] as $table) {
            self::assertFalse($manifest->entry($table)['enabled'], $table);
        }
    }
}
