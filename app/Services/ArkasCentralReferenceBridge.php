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
            'ref_kode',
            'ref_level_kode',
            'ref_periode',
            'ref_rekening',
            'ref_sumber_dana',
        ];
    }
}
