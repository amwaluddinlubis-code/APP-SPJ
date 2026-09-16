<?php

namespace Tests\Unit;

use App\Models\Transaction;
use App\Services\SpjDescriptionService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SpjDescriptionServiceTest extends TestCase
{
    #[DataProvider('siplahDescriptionCases')]
    public function test_siplah_payment_description_uses_safe_canonical_fallbacks(array $metadata, string $expected): void
    {
        $transaction = new Transaction(['siplah_metadata' => ['siplahResponse' => $metadata]]);

        $this->assertSame(
            $expected,
            app(SpjDescriptionService::class)->siplahPaymentDescription($transaction),
        );
    }

    public static function siplahDescriptionCases(): array
    {
        return [
            'merchant marketplace invoice' => [[
                'merchant' => 'Toko Nusantara',
                'marketplace_displayname' => 'Mitra SiPLah',
                'invoice_number' => 'INV-001',
            ], 'Pembelian barang di Merchant Toko Nusantara melalui Mitra SiPLah berdasarkan invoice INV-001'],
            'merchant marketplace without invoice' => [[
                'merchant' => 'Toko Nusantara',
                'marketplace_displayname' => 'Mitra SiPLah',
            ], 'Pembelian barang di Merchant Toko Nusantara melalui Mitra SiPLah'],
            'marketplace without merchant' => [[
                'marketplace_displayname' => 'Mitra SiPLah',
                'invoice_number' => 'INV-002',
            ], 'Pembelian barang melalui Mitra SiPLah berdasarkan invoice INV-002'],
            'items without marketplace' => [[
                'items' => [
                    ['siplah_item_name' => 'Kertas A4'],
                    ['siplah_item_name' => 'Tinta Printer'],
                ],
            ], 'Pembelian barang: Kertas A4, Tinta Printer'],
            'empty metadata' => [[], 'Pembelian barang melalui SiPLah'],
        ];
    }
}
