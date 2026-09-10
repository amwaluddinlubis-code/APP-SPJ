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

    public function test_session_backed_context_preserves_null_fund_source_scope_semantics(): void
    {
        session()->put('active_fiscal_year_id', 2026);
        session()->forget('active_fund_source_id');

        $context = app(ActiveSpjContext::class);
        $query = Transaction::query()->forSpjContext($context);

        $this->assertNull($context->fundSourceId());
        $this->assertStringContainsString('fund_source_id" is null', strtolower($query->toSql()));
        $this->assertSame([2026], $query->getBindings());
    }

    public function test_context_keeps_fiscal_year_only_and_full_scope_guards_distinct(): void
    {
        $context = new ActiveSpjContext(12, 2026, 7, 99, false);
        $transaction = new Transaction([
            'fiscal_year_id' => 2026,
            'fund_source_id' => 8,
        ]);

        $this->assertTrue($context->matchesFiscalYear($transaction));
        $this->assertFalse($context->matchesTransaction($transaction));
    }

    public function test_spj_use_cases_do_not_read_session_or_auth_directly(): void
    {
        $paths = glob(app_path('UseCases/Spj/*.php')) ?: [];

        $this->assertNotEmpty($paths);
        foreach ($paths as $path) {
            $source = file_get_contents($path);

            $this->assertIsString($source, $path);
            $this->assertStringNotContainsString("session('", $source, $path);
            $this->assertStringNotContainsString('auth()->', $source, $path);
        }
    }

    public function test_context_aware_model_and_numbering_service_do_not_read_request_globals(): void
    {
        foreach ([
            app_path('Models/Transaction.php'),
            app_path('Services/SpjNumberingOrderService.php'),
        ] as $path) {
            $source = file_get_contents($path);

            $this->assertIsString($source, $path);
            $this->assertStringNotContainsString("session('", $source, $path);
            $this->assertStringNotContainsString('auth()->', $source, $path);
        }
    }
}
