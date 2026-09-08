<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\SpjScenarioFactory;

class SpjScenarioFactoryTest extends TestCase
{
    /** @return array<string, array{0:string}> */
    public static function categories(): array
    {
        $rows = [];
        foreach (SpjScenarioFactory::CATEGORIES as $category) {
            $rows[$category] = [$category];
        }

        return $rows;
    }

    #[DataProvider('categories')]
    public function test_each_canonical_category_has_a_reusable_operator_payload(string $category): void
    {
        $payload = SpjScenarioFactory::payload($category, 1000000);

        $this->assertSame($category, $payload['spj_category']);
        $this->assertNotEmpty($payload['payment_description']);
        $this->assertSame('tunai', $payload['payment_method']);
    }

    public function test_money_driven_scenarios_reconcile_to_the_requested_gross_amount(): void
    {
        $gross = 1250000.0;

        $maintenance = SpjScenarioFactory::payload('PEMELIHARAAN', $gross);
        $this->assertSame($gross, (float) $maintenance['workers'][0]['work_days'] * (float) $maintenance['workers'][0]['daily_rate']);

        $service = SpjScenarioFactory::payload('JASA_LAINNYA', $gross);
        $serviceRow = $service['service_recipients'][0];
        $this->assertSame($gross, (float) $serviceRow['quantity'] * (float) $serviceRow['rental_days'] * (float) $serviceRow['daily_rate']);

        $travel = SpjScenarioFactory::payload('SPPD', $gross);
        $this->assertSame($gross, (float) $travel['travels'][0]['amount']);

        $honor = SpjScenarioFactory::payload('HONOR_PEGAWAI', $gross);
        $this->assertSame($gross, (float) $honor['workers'][0]['work_days'] * (float) $honor['workers'][0]['daily_rate']);
    }

    public function test_consumption_scenario_keeps_participant_count_equal_to_portions(): void
    {
        $payload = SpjScenarioFactory::payload('KONSUMSI');
        $portions = array_sum(array_map(fn (array $row): int => (int) $row['portions'], $payload['participants']));

        $this->assertSame($portions, $payload['participant_count']);
    }
}
