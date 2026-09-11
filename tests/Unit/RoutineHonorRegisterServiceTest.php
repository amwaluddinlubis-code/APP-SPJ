<?php

namespace Tests\Unit;

use App\Models\SpjHonor;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Services\RoutineHonorRegisterService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class RoutineHonorRegisterServiceTest extends TestCase
{
    public function test_same_recipient_type_and_value_are_merged_across_packages(): void
    {
        $honors = collect([
            $this->honor('Siti Aminah', 'Honor Operator', 500000, 500000, 25000, 475000, 'BKU-001', '001/SPJ', '2026-01-31'),
            $this->honor('Siti Aminah', 'Honor Operator', 500000, 500000, 25000, 475000, 'BKU-002', '002/SPJ', '2026-02-28'),
        ]);

        $result = (new RoutineHonorRegisterService)->aggregate($honors);

        $this->assertCount(1, $result['rows']);
        $row = $result['rows']->first();
        $this->assertTrue($row['is_routine']);
        $this->assertSame(2, $row['occurrences']);
        $this->assertSame(1000000.0, $row['gross']);
        $this->assertSame(50000.0, $row['tax']);
        $this->assertSame(950000.0, $row['net']);
        $this->assertStringContainsString('BKU-001 / 001/SPJ', $row['package_references']);
        $this->assertStringContainsString('BKU-002 / 002/SPJ', $row['package_references']);
        $this->assertSame(1000000.0, $result['summary']['gross']);
    }

    public function test_different_honor_value_is_not_merged(): void
    {
        $honors = collect([
            $this->honor('Siti Aminah', 'Honor Operator', 500000, 500000, 25000, 475000, 'BKU-001', '001/SPJ', '2026-01-31'),
            $this->honor('Siti Aminah', 'Honor Operator', 600000, 600000, 30000, 570000, 'BKU-002', '002/SPJ', '2026-02-28'),
        ]);

        $result = (new RoutineHonorRegisterService)->aggregate($honors);

        $this->assertCount(2, $result['rows']);
        $this->assertFalse($result['rows']->first()['is_routine']);
        $this->assertSame(1100000.0, $result['summary']['gross']);
    }

    private function honor(
        string $name,
        string $position,
        float $rate,
        float $gross,
        float $tax,
        float $net,
        string $noBukti,
        string $documentNumber,
        string $date,
    ): SpjHonor {
        $package = new SpjPackage(['document_number' => $documentNumber]);
        $transaction = new Transaction([
            'no_bukti' => $noBukti,
            'transaction_date' => Carbon::parse($date),
        ]);
        $transaction->setRelation('spjPackage', $package);

        $item = new TransactionItem;
        $item->setRelation('transaction', $transaction);

        $honor = new SpjHonor([
            'name' => $name,
            'position' => $position,
            'honor_months' => 1,
            'rate_per_unit' => $rate,
            'gross_amount' => $gross,
            'tax_amount' => $tax,
            'net_amount' => $net,
        ]);
        $honor->setRelation('item', $item);

        return $honor;
    }
}
