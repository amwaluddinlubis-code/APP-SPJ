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
            'aktivasi_bku',
            'anggaran',
            'kas_umum',
            'kas_umum_nota',
            'kas_umum_nota_pajak',
            'pegawai',
            'ptk',
            'rapbs',
            'rapbs_periode',
            'rapbs_ptk',
            'salur',
            'sekolah_history',
            'sekolah_penjab',
            'mst_sekolah',
        ];
    }
}
