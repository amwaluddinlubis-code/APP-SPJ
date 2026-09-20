<?php

namespace App\Services;

final class ArkasCentralReferenceBridge implements ArkasMirrorBridge
{
    public function scope(): string
    {
        return 'CENTRAL';
    }

    /** @return array<int, string> */
    public function sourceTables(): array
    {
        return [
            'ref_acuan_barang',
            'ref_bku',
            'ref_kode',
            'ref_level_kode',
            'ref_periode',
            'ref_rekening',
            'ref_sumber_dana',
            'mst_wilayah',
            'ref_level_wilayah',
            'ref_negara',
            'ref_jabatan',
            'ref_jenis_instansi',
            'ref_satuan',
            'ref_indikator',
        ];
    }
}
