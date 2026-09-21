<?php

namespace Tests\Unit;

use App\Services\SpjOverlayMigrationService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class SpjOverlayDeterministicEvidenceTest extends TestCase
{
    public function test_transaction_evidence_resolves_only_one_candidate(): void
    {
        $result = $this->audit(
            [
                $this->candidate(1, '2024-03-23', '1000000', 'Exact recipient'),
                $this->candidate(2, '2024-03-23', '900000', 'Other recipient'),
            ],
        );
        self::assertSame('AUTO_RESOLVED_DETERMINISTIC', $result['classification']);
        self::assertSame(['old_transaction_id' => 1], $result['winner']);
    }

    public function test_item_count_source_ids_and_descriptions_are_exact_evidence(): void
    {
        $result = $this->audit(
            [
                $this->candidate(1, '2024-03-23', '1000000', 'Exact recipient'),
                $this->candidate(2, '2025-01-02', '2', 'Other'),
            ],
            [
                (object) ['source_key' => 'ITEM-1', 'item_description' => '  Exact item  '],
            ],
            [
                1 => [['source_item_id' => 'ITEM-1', 'item_description' => 'Exact item']],
                2 => [['source_item_id' => 'ITEM-2', 'item_description' => 'Exact item']],
            ],
        );
        self::assertSame('AUTO_RESOLVED_DETERMINISTIC', $result['classification']);
        self::assertContains('exact_item_count_source_ids_descriptions', $result['candidate_evidence'][0]['matches']);
    }

    public function test_collision_with_two_exact_candidates_fails_closed(): void
    {
        $result = $this->audit([
            $this->candidate(1, '2024-03-23', '1000000', 'Same'),
            $this->candidate(2, '2024-03-23', '1000000', 'Same'),
        ]);

        self::assertSame('STILL_AMBIGUOUS', $result['classification']);
        self::assertNull($result['winner']);
    }

    public function test_no_bukti_fallback_cannot_cross_fiscal_year_or_fund_source(): void
    {
        $method = new ReflectionMethod(SpjOverlayMigrationService::class, 'resolveTransaction');
        $method->setAccessible(true);
        $result = $method->invoke(
            new SpjOverlayMigrationService,
            (object) ['fiscal_year_id' => 3, 'fund_source_id' => 1, 'source_key' => 'missing-source-key'],
            ['no_bukti' => 'BPU-1', 'tanggal_transaksi' => '2026-01-10'],
            [
                'proof-date:BPU-1:2026-01-10' => [
                    ['id' => 1, 'fiscal_year_id' => 2, 'fund_source_id' => 1],
                    ['id' => 2, 'fiscal_year_id' => 3, 'fund_source_id' => 1],
                ],
            ],
        );

        self::assertSame('matched', $result['status']);
        self::assertSame(2, $result['transaction']['id']);
    }

    /** @param array<int, array<string,mixed>> $candidates @param array<int, object> $freshItems @param array<int, array<int, array<string,mixed>>> $oldItems */
    private function audit(array $candidates, array $freshItems = [], array $oldItems = []): array
    {
        $fresh = (object) ['id' => 10, 'source_key' => 'fresh-key'];
        $payload = [
            'no_bukti' => 'BPU-1',
            'tanggal_transaksi' => '2024-03-23',
            'saldo' => '1000000',
            'uraian' => 'Exact recipient',
        ];
        $method = new ReflectionMethod(SpjOverlayMigrationService::class, 'auditAmbiguousCandidates');
        $method->setAccessible(true);

        return $method->invoke(
            new SpjOverlayMigrationService,
            $fresh,
            $payload,
            ['candidate_old_transactions' => $candidates],
            $freshItems,
            $oldItems,
            [],
        );
    }

    /** @return array<string,mixed> */
    private function candidate(int $id, string $date, string $amount, string $description): array
    {
        return [
            'id' => $id,
            'transaction_date' => $date,
            'gross_amount' => $amount,
            'payment_description' => $description,
        ];
    }
}
