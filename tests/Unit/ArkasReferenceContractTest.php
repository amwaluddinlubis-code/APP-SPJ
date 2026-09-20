<?php

namespace Tests\Unit;

use App\Services\ArkasReferencePromotionService;
use Tests\TestCase;

class ArkasReferenceContractTest extends TestCase
{
    public function test_invalid_acuan_identity_is_quarantined_without_silent_drop(): void
    {
        $promotion = new ArkasReferencePromotionService;
        $rows = [
            ['id_barang' => '', 'tahun' => 2026, 'nama_barang' => 'empty'],
            ['id_barang' => '   ', 'tahun' => 2026, 'nama_barang' => 'whitespace'],
            ['id_barang' => "bad\nkey", 'tahun' => 2026, 'nama_barang' => 'malformed'],
            ['id_barang' => 'GOOD-1', 'tahun' => 2026, 'nama_barang' => 'valid'],
        ];

        $report = $promotion->promoteReport('ref_acuan_barang', $rows, '2026.09');

        self::assertSame(1, $report['accepted']);
        self::assertSame(3, $report['diagnostics']['quarantined']);
        self::assertCount(3, $report['quarantined']);
        self::assertCount(1, $promotion->readCentral('ref_acuan_barang'));
    }

    public function test_acuan_quarantine_is_deterministic_and_idempotent(): void
    {
        $promotion = new ArkasReferencePromotionService;
        $invalid = [['id_barang' => null, 'tahun' => 2026]];

        $first = $promotion->promoteReport('ref_acuan_barang', $invalid, '2026.09');
        $second = $promotion->promoteReport('ref_acuan_barang', $invalid, '2026.09');

        self::assertSame($first, $second);
        self::assertSame([], $promotion->readCentral('ref_acuan_barang'));
    }

    public function test_code_same_semantic_deduplicates_and_distinct_applicability_allows_variant(): void
    {
        $promotion = new ArkasReferencePromotionService;
        $base = [
            'id_kode' => '02.', 'parent_kode' => null, 'uraian_kode' => 'Standar Isi',
            'id_level_kode' => '1', 'tipe' => 'PROGRAM', 'tahun' => 2026,
            'sumber_dana_id' => '1', 'bentuk_pendidikan_id' => 'SD',
        ];

        $promotion->promoteCodeVariants('A', [$base, $base], '2026.09');
        self::assertCount(1, $promotion->readCentral('ref_kode'));
        self::assertCount(1, $promotion->readTenantExtension('ref_kode', 'A'));

        $variant = $base;
        $variant['uraian_kode'] = 'Pengembangan Standar Isi';
        $variant['tahun'] = 2027;
        $promotion->promoteCodeVariants('A', [$variant], '2026.09');
        self::assertCount(2, $promotion->readCentral('ref_kode'));
        self::assertCount(2, $promotion->readTenantExtension('ref_kode', 'A'));
    }

    public function test_code_conflict_same_context_fails_closed(): void
    {
        $promotion = new ArkasReferencePromotionService;
        $base = [
            'id_kode' => '02.', 'parent_kode' => null, 'uraian_kode' => 'Standar Isi',
            'id_level_kode' => '1', 'tipe' => 'PROGRAM', 'tahun' => 2026,
            'sumber_dana_id' => '1', 'bentuk_pendidikan_id' => 'SD',
        ];
        $promotion->promoteCodeVariants('A', [$base], '2026.09');

        $this->expectExceptionMessage('contradictory semantic definition');
        $conflict = $base;
        $conflict['uraian_kode'] = 'Different';
        $promotion->promoteCodeVariants('A', [$conflict], '2026.09');
    }

    public function test_orphan_code_applicability_fails_closed(): void
    {
        $promotion = new ArkasReferencePromotionService;
        $base = [
            'id_kode' => '02.', 'tahun' => 2026, 'sumber_dana_id' => '1',
            'bentuk_pendidikan_id' => 'SD',
        ];
        $this->expectExceptionMessage('Orphan ref_kode applicability');
        $promotion->attachCodeApplicability('A', $base, '2026.09', 'missing-variant');
    }
}
