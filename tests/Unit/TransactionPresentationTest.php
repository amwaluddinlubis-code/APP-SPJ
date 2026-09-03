<?php

namespace Tests\Unit;

use App\Http\Controllers\TransactionController;
use App\Models\Transaction;
use ReflectionMethod;
use Tests\TestCase;

class TransactionPresentationTest extends TestCase
{
    public function test_honor_transaction_uses_the_honor_banner_without_an_unrelated_image(): void
    {
        $visual = $this->invokeControllerMethod('headerVisual', new Transaction([
            'spj_category' => 'HONOR_PEGAWAI',
            'description' => 'Honorarium guru',
            'account_code' => '5.1.02.02.01.0013',
        ]));

        $this->assertSame('Honor Pegawai', $visual['label']);
        $this->assertNull($visual['image']);
    }

    public function test_legacy_payment_method_is_normalized_for_display_and_form_selection(): void
    {
        $transaction = new Transaction(['payment_method' => 'Tunai', 'no_bukti' => 'BPU01']);

        $this->assertSame('tunai', $this->invokeControllerMethod('normalizePaymentMethod', 'Tunai', $transaction));
    }

    private function invokeControllerMethod(string $method, mixed ...$arguments): mixed
    {
        $reflection = new ReflectionMethod(TransactionController::class, $method);

        return $reflection->invoke(new TransactionController, ...$arguments);
    }
}
