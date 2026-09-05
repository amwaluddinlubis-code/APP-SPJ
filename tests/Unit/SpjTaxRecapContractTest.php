<?php

namespace Tests\Unit;

use App\Services\SpjDocumentTypeRegistry;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpjTaxRecapContractTest extends TestCase
{
    #[Test]
    public function net_paid_value_is_optional_for_tax_recap(): void
    {
        $definition = SpjDocumentTypeRegistry::definition(SpjDocumentTypeRegistry::REKAP_PAJAK);

        $this->assertNotNull($definition);
        $this->assertContains('NILAI_BRUTO', $definition['required']);
        $this->assertContains('TOTAL_PAJAK', $definition['required']);
        $this->assertNotContains('NILAI_DIBAYARKAN', $definition['required']);
        $this->assertContains('NILAI_DIBAYARKAN', $definition['optional']);
    }
}
