<?php

namespace Tests\Unit;

use App\Services\ArkasMirrorManifest;
use App\Services\ArkasReferenceVersionKey;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ArkasReferenceVersionKeyTest extends TestCase
{
    public function test_same_natural_key_can_coexist_across_explicit_versions(): void
    {
        $resolver = new ArkasReferenceVersionKey(['2026.09']);
        $entry = (new ArkasMirrorManifest)->entry('ref_rekening');

        $first = $resolver->resolve($entry, ['kode_rekening' => '5.1', 'tahun' => 2026], '2026.09');
        $second = $resolver->resolve($entry, ['kode_rekening' => '5.1', 'tahun' => 2025], '2026.09');

        self::assertNotSame($first, $second);
    }

    public function test_hybrid_merge_is_order_independent_and_ignores_provenance_drift(): void
    {
        $resolver = new ArkasReferenceVersionKey(['2026.09']);
        $entry = (new ArkasMirrorManifest)->entry('ref_sumber_dana');
        $rows = [
            ['kode' => '4.3.1.01.', 'nama_sumber_dana' => 'BOS Reguler', 'last_update' => 'A'],
            ['kode' => '4.3.1.02.', 'nama_sumber_dana' => 'BOS Kinerja', 'last_update' => 'B'],
        ];

        self::assertSame(
            $resolver->merge($entry, $rows, '2026.09', ['fiscal_year' => 2026]),
            $resolver->merge($entry, array_reverse($rows), '2026.09', ['fiscal_year' => 2026]),
        );

        $rekening = (new ArkasMirrorManifest)->entry('ref_rekening');
        self::assertSame(
            $resolver->merge($rekening, [['kode_rekening' => '5.1', 'tahun' => 2026, 'rekening' => 'A', 'last_update' => 'one']], '2026.09'),
            $resolver->merge($rekening, [['kode_rekening' => '5.1', 'tahun' => 2026, 'rekening' => 'A', 'last_update' => 'two']], '2026.09'),
        );
    }

    public function test_same_identity_with_semantic_conflict_fails_closed(): void
    {
        $this->expectException(RuntimeException::class);

        (new ArkasReferenceVersionKey(['2026.09']))->merge(
            (new ArkasMirrorManifest)->entry('ref_bku'),
            [
                ['id_ref_bku' => 24, 'bku' => 'Kas Keluar Sisa', 'kode_bku' => 'BPU'],
                ['id_ref_bku' => 24, 'bku' => 'Other', 'kode_bku' => 'BPU'],
            ],
            '2026.09',
        );
    }

    public function test_missing_or_unsupported_version_fails_closed(): void
    {
        $resolver = new ArkasReferenceVersionKey(['2026.09']);
        $entry = (new ArkasMirrorManifest)->entry('ref_rekening');

        $this->expectException(RuntimeException::class);
        $resolver->resolve($entry, ['kode_rekening' => '5.1', 'tahun' => 2026], '2025.01');
    }

    public function test_tenant_extension_identity_isolated_by_year(): void
    {
        $resolver = new ArkasReferenceVersionKey;
        $entry = (new ArkasMirrorManifest)->entry('ref_sumber_dana_sekolah');

        self::assertNotSame(
            $resolver->resolve($entry, ['id_ref_sumber_dana' => 61, 'tahun' => 2025]),
            $resolver->resolve($entry, ['id_ref_sumber_dana' => 61, 'tahun' => 2026]),
        );
    }
}
