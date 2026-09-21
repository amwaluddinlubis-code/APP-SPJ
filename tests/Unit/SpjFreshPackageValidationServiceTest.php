<?php

namespace Tests\Unit;

use App\Models\ArkasRawMirrorRow;
use App\Models\SpjFreshPackage;
use App\Models\SpjFreshTransaction;
use App\Models\SpjFreshTransactionItem;
use App\Services\SpjFreshPackageValidationService;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

final class SpjFreshPackageValidationServiceTest extends TestCase
{
    public function test_fresh_package_reports_missing_operator_fields(): void
    {
        $transaction = new SpjFreshTransaction;
        $transaction->setRawAttributes(['description' => null, 'payment_description' => null, 'payment_method' => null]);
        $transaction->setRelation('items', new Collection);

        $package = new SpjFreshPackage;
        $package->setRelation('transaction', $transaction);

        $issues = new SpjFreshPackageValidationService()->validate($package);

        self::assertContains('spj_category', array_column($issues, 'key'));
        self::assertContains('recipient', array_column($issues, 'key'));
        self::assertContains('items', array_column($issues, 'key'));
    }

    public function test_fresh_package_with_required_data_has_no_basic_validation_issues(): void
    {
        $transaction = new SpjFreshTransaction;
        $transaction->setRawAttributes([
            'spj_category' => 'BARANG',
            'description' => 'Belanja ATK',
            'payment_description' => 'Belanja ATK',
            'payment_method' => 'tunai',
            'receipt_recipient_name' => 'Toko ABC',
        ]);
        $item = new SpjFreshTransactionItem;
        $item->setRawAttributes([
            'item_description' => 'Kertas A4',
        ]);

        $rawMirrorRow = new ArkasRawMirrorRow;
        $rawMirrorRow->setRawAttributes(['payload' => json_encode(['saldo' => 100000], JSON_THROW_ON_ERROR)]);
        $item->setRelation('rawMirrorRow', $rawMirrorRow);
        $transaction->setRelation('items', new Collection([$item]));

        $package = new SpjFreshPackage;
        $package->setRelation('transaction', $transaction);

        self::assertSame([], new SpjFreshPackageValidationService()->validate($package));
    }
}
