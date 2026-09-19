<?php

namespace App\Services;

final class ArkasTenantDataBridge implements ArkasMirrorBridge
{
    public function scope(): string
    {
        return 'TENANT';
    }

    /** @return array<int, string> */
    public function sourceTables(): array
    {
        return [
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
        ];
    }
}
