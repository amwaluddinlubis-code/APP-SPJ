<?php

namespace Tests\Unit;

use App\Services\SpjDocumentTypeRegistry;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpjChecklistContractTest extends TestCase
{
    #[Test]
    public function checklist_does_not_require_spj_category_marker(): void
    {
        $definition = SpjDocumentTypeRegistry::definition(SpjDocumentTypeRegistry::SPJ_CHECKLIST);

        $this->assertNotNull($definition);
        $this->assertNotContains('JENIS_SPJ', $definition['required']);
        $this->assertContains('JENIS_SPJ', $definition['optional']);
        $this->assertContains('SUMBER_DANA_PERIODE', $definition['required']);
    }
}
