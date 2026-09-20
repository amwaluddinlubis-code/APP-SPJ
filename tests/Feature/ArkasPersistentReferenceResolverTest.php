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
}
