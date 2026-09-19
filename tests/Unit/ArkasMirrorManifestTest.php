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
        self::assertSame('UNKNOWN', $manifest->entry('ref_kode')['category']);
        self::assertFalse($manifest->entry('ref_kode')['enabled']);
        self::assertSame(ArkasCentralReferenceBridge::class, $manifest->entry('ref_kode')['bridge']);
    }

    public function test_manifest_rejects_tables_without_an_explicit_contract(): void
    {
        $manifest = new ArkasMirrorManifest;

        self::assertSame([], $manifest->importableTables(['custom_table', 'another_table']));
        $this->expectException(\InvalidArgumentException::class);
        $manifest->entry('custom_table');
    }
}
