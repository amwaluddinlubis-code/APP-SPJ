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
}
