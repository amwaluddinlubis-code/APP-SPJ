<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Support\ActiveSpjContext;
use Tests\TestCase;

class ActiveSpjContextTest extends TestCase
{
    public function test_context_exposes_explicit_values_without_reading_request_globals(): void
    {
        $context = new ActiveSpjContext(12, 2026, 7, 99, true);

        $this->assertSame(12, $context->schoolId());
        $this->assertSame(2026, $context->fiscalYearId());
        $this->assertSame(7, $context->fundSourceId());
        $this->assertSame(99, $context->actorId());
        $this->assertTrue($context->isAdministrator());
    }

    public function test_transaction_scope_accepts_context_explicitly(): void
    {
        $context = new ActiveSpjContext(12, 2026, 7, 99, false);
        $query = Transaction::query()->forSpjContext($context);

        $this->assertStringContainsString('fiscal_year_id', $query->toSql());
        $this->assertStringContainsString('fund_source_id', $query->toSql());
        $this->assertSame([2026, 7], $query->getBindings());
    }

    public function test_core_spj_orchestration_does_not_read_session_or_auth_directly(): void
    {
        $paths = [
            'Models/Transaction.php',
            'Services/SpjNumberingOrderService.php',
            'UseCases/Spj/SpjSingleNumberingUseCase.php',
            'UseCases/Spj/SpjQuarterNumberingUseCase.php',
            'UseCases/Spj/SpjPackageLifecycleUseCase.php',
            'UseCases/Spj/SpjDocumentLifecycleUseCase.php',
            'UseCases/Spj/SpjFiscalPeriodUseCase.php',
            'UseCases/Spj/SpjSettlementUseCase.php',
        ];

        foreach ($paths as $path) {
            $source = file_get_contents(app_path($path));

            $this->assertIsString($source, $path);
            $this->assertStringNotContainsString("session('", $source, $path);
            $this->assertStringNotContainsString('auth()->', $source, $path);
        }
    }
}
