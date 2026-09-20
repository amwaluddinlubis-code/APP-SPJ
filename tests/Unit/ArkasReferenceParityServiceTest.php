<?php

namespace Tests\Unit;

use App\Services\ArkasReferenceParityService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ArkasReferenceParityServiceTest extends TestCase
{
    public function test_identical_reference_is_confirmed_and_order_independent(): void
    {
        $service = new ArkasReferenceParityService;
        $schema = [['name' => 'id', 'type' => 'INTEGER'], ['name' => 'label', 'type' => 'TEXT']];
        $a = ['schema' => $schema, 'rows' => [['id' => 2, 'label' => 'B'], ['id' => 1, 'label' => 'A']]];
        $b = ['schema' => $schema, 'rows' => [['id' => 1, 'label' => 'A'], ['id' => 2, 'label' => 'B']]];
        $c = ['schema' => $schema, 'rows' => [['id' => 2, 'label' => 'B'], ['id' => 1, 'label' => 'A']]];

        $first = $service->compare(['A' => $a, 'B' => $b, 'C' => $c], ['id']);
        $second = $service->compare(['C' => $c, 'A' => $a, 'B' => $b], ['id']);

        self::assertSame('GLOBAL_REFERENCE_CONFIRMED', $first['status']);
        self::assertTrue($first['confirmed_global']);
        self::assertSame($first['rowset_hashes']['A'], $second['rowset_hashes']['A']);
        self::assertSame(2, $first['common_rows']);
        self::assertSame([], $first['only_rows']);
    }

    public function test_content_conflict_and_schema_mismatch_are_not_promoted(): void
    {
        $service = new ArkasReferenceParityService;
        $schema = [['name' => 'id', 'type' => 'INTEGER'], ['name' => 'label', 'type' => 'TEXT']];
        $different = ['schema' => $schema, 'rows' => [['id' => 1, 'label' => 'Different']]];
        $same = ['schema' => $schema, 'rows' => [['id' => 1, 'label' => 'Original']]];
        $result = $service->compare(['A' => $same, 'B' => $different], ['id']);

        self::assertSame('PARITY_CONFLICT', $result['status']);
        self::assertFalse($result['confirmed_global']);
        self::assertSame(1, $result['different_common_rows']);
    }

    public function test_duplicate_key_fails_closed(): void
    {
        $service = new ArkasReferenceParityService;
        $schema = [['name' => 'id', 'type' => 'INTEGER']];

        $result = $service->compare([
            'A' => ['schema' => $schema, 'rows' => [['id' => 1], ['id' => 1]]],
            'B' => ['schema' => $schema, 'rows' => [['id' => 1]]],
        ], ['id']);

        self::assertSame('PARITY_CONFLICT', $result['status']);
        self::assertSame(1, $result['duplicate_keys']['A']);
    }

    public function test_malformed_key_fails_closed(): void
    {
        $service = new ArkasReferenceParityService;
        $schema = [['name' => 'id', 'type' => 'INTEGER']];

        $this->expectException(RuntimeException::class);
        $service->compare([
            'A' => ['schema' => $schema, 'rows' => [['id' => null]]],
            'B' => ['schema' => $schema, 'rows' => [['id' => 1]]],
        ], ['id']);
    }
}
