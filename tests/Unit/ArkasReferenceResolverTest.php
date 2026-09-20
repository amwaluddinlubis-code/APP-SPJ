<?php

namespace Tests\Unit;

use App\Services\ArkasReferenceResolver;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ArkasReferenceResolverTest extends TestCase
{
    public function test_legacy_raw_mode_returns_only_legacy_rows(): void
    {
        $resolver = new ArkasReferenceResolver;
        $legacy = [['id' => 1]];

        self::assertSame($legacy, $resolver->read(ArkasReferenceResolver::LEGACY_RAW, $legacy, [['id' => 2]]));
    }

    public function test_central_compat_requires_shadow_parity_and_returns_central_rows(): void
    {
        $resolver = new ArkasReferenceResolver;
        $legacy = [['id' => 1, 'last_update' => 'A']];
        $central = [['id' => 1, 'last_update' => 'B']];

        self::assertSame($central, $resolver->read(ArkasReferenceResolver::CENTRAL_COMPAT, $legacy, $central));
    }

    public function test_central_compat_fails_closed_without_silent_fallback(): void
    {
        $this->expectException(RuntimeException::class);

        (new ArkasReferenceResolver)->read(ArkasReferenceResolver::CENTRAL_COMPAT, [['id' => 1]], [['id' => 2]]);
    }

    public function test_central_only_never_reads_legacy_rows(): void
    {
        $central = [['id' => 2]];

        self::assertSame($central, (new ArkasReferenceResolver)->read(ArkasReferenceResolver::CENTRAL_ONLY, [['id' => 1]], $central));
    }
}
