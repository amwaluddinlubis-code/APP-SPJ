<?php

namespace Tests\Unit;

use App\Models\DocumentNumberFormat;
use App\Services\SpjDocumentNumberService;
use App\Services\SpjNumberingPolicyService;
use App\Services\SpjProcurementPolicyService;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class QuarterNumberingPlaceholderTest extends TestCase
{
    public function test_tw_placeholder_uses_document_quarter_not_month(): void
    {
        $format = new DocumentNumberFormat([
            'format_pattern' => '{SEQ}/OP/{SCHOOL}/{TW}/{YEAR}',
            'padding' => 4,
        ]);
        $service = $this->numberingService();

        $this->assertSame('0001/OP/SDN.318/I/2026', $service->renderConfiguredNumber($format, 'PESANAN', 1, Carbon::parse('2026-01-08'), 'SDN.318'));
        $this->assertSame('0002/OP/SDN.318/I/2026', $service->renderConfiguredNumber($format, 'PESANAN', 2, Carbon::parse('2026-02-03'), 'SDN.318'));
        $this->assertSame('0003/OP/SDN.318/I/2026', $service->renderConfiguredNumber($format, 'PESANAN', 3, Carbon::parse('2026-03-31'), 'SDN.318'));
        $this->assertSame('0004/OP/SDN.318/II/2026', $service->renderConfiguredNumber($format, 'PESANAN', 4, Carbon::parse('2026-04-01'), 'SDN.318'));
        $this->assertSame('0005/OP/SDN.318/III/2026', $service->renderConfiguredNumber($format, 'PESANAN', 5, Carbon::parse('2026-07-01'), 'SDN.318'));
        $this->assertSame('0006/OP/SDN.318/IV/2026', $service->renderConfiguredNumber($format, 'PESANAN', 6, Carbon::parse('2026-10-01'), 'SDN.318'));
    }

    public function test_legacy_roman_month_placeholder_remains_backward_compatible(): void
    {
        $format = new DocumentNumberFormat([
            'format_pattern' => '{SEQ}/OP/{SCHOOL}/{ROMAN_MONTH}/{YEAR}',
            'padding' => 4,
        ]);
        $service = $this->numberingService();

        $this->assertSame('0001/OP/SDN.318/II/2026', $service->renderConfiguredNumber($format, 'PESANAN', 1, Carbon::parse('2026-02-03'), 'SDN.318'));
    }

    private function numberingService(): SpjDocumentNumberService
    {
        return new SpjDocumentNumberService(
            new SpjNumberingPolicyService(new SpjProcurementPolicyService),
        );
    }
}
