<?php

namespace Tests\Unit;

use App\Services\ArkasSourceKeyResolver;
use PHPUnit\Framework\TestCase;

class ArkasSourceKeyResolverTest extends TestCase
{
    public function test_configured_column_wins_case_insensitively(): void
    {
        $resolver = new ArkasSourceKeyResolver;

        $key = $resolver->resolve([
            'external_id' => 'CUSTOM-001',
            'ID_RAPBS' => 'RKAS-001',
        ], 'EXTERNAL_ID');

        $this->assertSame('CUSTOM-001', $key);
    }

    public function test_blank_configured_value_falls_back_to_known_source_identity(): void
    {
        $resolver = new ArkasSourceKeyResolver;

        $key = $resolver->resolve([
            'external_id' => '',
            'id_kas_nota_pajak' => 'PAJAK-77',
            'ID' => 'GENERIC-1',
        ], 'external_id');

        $this->assertSame('PAJAK-77', $key);
    }

    public function test_known_fallbacks_are_case_insensitive(): void
    {
        $resolver = new ArkasSourceKeyResolver;

        $this->assertSame('RAPBS-9', $resolver->resolve(['id_rapbs' => 'RAPBS-9']));
        $this->assertSame('NOTA-8', $resolver->resolve(['Id_Kas_Nota' => 'NOTA-8']));
        $this->assertSame('KODE-7', $resolver->resolve(['id_ref_kode' => 'KODE-7']));
    }

    public function test_payload_hash_is_deterministic_when_no_stable_identity_exists(): void
    {
        $resolver = new ArkasSourceKeyResolver;
        $record = ['uraian' => 'Belanja tanpa ID', 'jumlah' => 125000];
        $expected = hash('sha256', json_encode($record, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));

        $this->assertSame($expected, $resolver->resolve($record));
        $this->assertSame($expected, $resolver->resolve($record));
    }

    public function test_primary_key_columns_override_unsafe_global_fallbacks(): void
    {
        $resolver = new ArkasSourceKeyResolver;

        $key = $resolver->resolveFromColumns([
            'ID_RAPBS' => 'RAPBS-SHARED',
            'ID_KAS_NOTA' => 'NOTA-SHARED',
            'ID_KAS_UMUM' => 'KAS-UNIQUE-001',
        ], ['ID_KAS_UMUM']);

        $this->assertSame('KAS-UNIQUE-001', $key);
    }

    public function test_composite_primary_key_is_deterministic_and_uses_all_components(): void
    {
        $resolver = new ArkasSourceKeyResolver;

        $first = $resolver->resolveFromColumns([
            'ID_KODE' => 'KODE-01',
            'ID_LEVEL_KODE' => 'LEVEL-02',
        ], ['ID_LEVEL_KODE', 'ID_KODE']);
        $same = $resolver->resolveFromColumns([
            'id_level_kode' => 'LEVEL-02',
            'id_kode' => 'KODE-01',
        ], ['ID_LEVEL_KODE', 'ID_KODE']);
        $different = $resolver->resolveFromColumns([
            'ID_LEVEL_KODE' => 'LEVEL-02',
            'ID_KODE' => 'KODE-99',
        ], ['ID_LEVEL_KODE', 'ID_KODE']);

        $this->assertSame($first, $same);
        $this->assertNotSame($first, $different);
        $this->assertSame(64, strlen($first));
    }
}
