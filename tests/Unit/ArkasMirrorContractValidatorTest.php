<?php

namespace Tests\Unit;

use App\Services\ArkasMirrorContractValidator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ArkasMirrorContractValidatorTest extends TestCase
{
    public function test_composite_tax_identity_is_stable(): void
    {
        $keyColumns = (new ArkasMirrorContractValidator)->validate(
            $this->taxEntry(),
            [
                ['name' => 'id_kas_nota', 'type' => 'VARCHAR', 'primary_order' => '0', 'primary' => '—'],
                ['name' => 'ntpn', 'type' => 'VARCHAR', 'primary_order' => '0', 'primary' => '—'],
            ],
            [
                ['id_kas_nota' => 'NOTA-1', 'ntpn' => 'NTPN-1'],
                ['id_kas_nota' => 'NOTA-1', 'ntpn' => 'NTPN-2'],
            ],
        );

        self::assertSame(['id_kas_nota', 'ntpn'], $keyColumns);
    }

    public function test_duplicate_composite_identity_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Duplikat identitas ARKAS');

        (new ArkasMirrorContractValidator)->validate(
            $this->taxEntry(),
            [
                ['name' => 'id_kas_nota', 'type' => 'VARCHAR', 'primary_order' => '0', 'primary' => '—'],
                ['name' => 'ntpn', 'type' => 'VARCHAR', 'primary_order' => '0', 'primary' => '—'],
            ],
            [
                ['id_kas_nota' => 'NOTA-1', 'ntpn' => 'NTPN-1'],
                ['id_kas_nota' => 'NOTA-1', 'ntpn' => 'NTPN-1'],
            ],
        );
    }

    public function test_missing_composite_key_value_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Identitas key ARKAS kosong');

        (new ArkasMirrorContractValidator)->validate(
            $this->taxEntry(),
            [
                ['name' => 'id_kas_nota', 'type' => 'VARCHAR', 'primary_order' => '0', 'primary' => '—'],
                ['name' => 'ntpn', 'type' => 'VARCHAR', 'primary_order' => '0', 'primary' => '—'],
            ],
            [['id_kas_nota' => 'NOTA-1', 'ntpn' => null]],
        );
    }

    public function test_required_column_drift_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('kolom wajib ntpn tidak ada');

        (new ArkasMirrorContractValidator)->validate(
            $this->taxEntry(),
            [['name' => 'id_kas_nota', 'type' => 'VARCHAR', 'primary_order' => '0', 'primary' => '—']],
            [],
        );
    }

    /** @return array<string, mixed> */
    private function taxEntry(): array
    {
        return [
            'source_table' => 'kas_umum_nota_pajak',
            'key_strategy' => 'COMPOSITE',
            'key_columns' => ['id_kas_nota', 'ntpn'],
            'required_columns' => ['id_kas_nota', 'ntpn'],
        ];
    }
}
