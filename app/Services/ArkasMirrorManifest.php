<?php

namespace App\Services;

use InvalidArgumentException;

final class ArkasMirrorManifest
{
    public const VERSION = '2026-09-19.v1';

    /** @var array<string, array{source_table:string, category:string, bridge:class-string<ArkasMirrorBridge>, enabled:bool, key_strategy:string, contract_note:string}> */
    private const ENTRIES = [
        'anggaran' => ['source_table' => 'anggaran', 'category' => 'TENANT', 'bridge' => ArkasTenantDataBridge::class, 'enabled' => true, 'key_strategy' => 'PRIMARY_KEY', 'contract_note' => 'Anggaran dan konteks tahun/sumber dana milik sekolah sumber.'],
        'kas_umum' => ['source_table' => 'kas_umum', 'category' => 'TENANT', 'bridge' => ArkasTenantDataBridge::class, 'enabled' => true, 'key_strategy' => 'PRIMARY_KEY', 'contract_note' => 'BKU adalah fakta operasional tenant dan sumber identity transaksi.'],
        'kas_umum_nota' => ['source_table' => 'kas_umum_nota', 'category' => 'TENANT', 'bridge' => ArkasTenantDataBridge::class, 'enabled' => true, 'key_strategy' => 'PRIMARY_KEY', 'contract_note' => 'Nota transaksi tetap berada pada tenant yang memiliki transaksi induk.'],
        'kas_umum_nota_pajak' => ['source_table' => 'kas_umum_nota_pajak', 'category' => 'TENANT', 'bridge' => ArkasTenantDataBridge::class, 'enabled' => true, 'key_strategy' => 'PRIMARY_KEY', 'contract_note' => 'Rincian pajak adalah fakta transaksi tenant.'],
        'pegawai' => ['source_table' => 'pegawai', 'category' => 'TENANT', 'bridge' => ArkasTenantDataBridge::class, 'enabled' => true, 'key_strategy' => 'PRIMARY_KEY', 'contract_note' => 'Master pegawai berasal dari database sekolah dan dapat memiliki overlay lokal.'],
        'ptk' => ['source_table' => 'ptk', 'category' => 'TENANT', 'bridge' => ArkasTenantDataBridge::class, 'enabled' => true, 'key_strategy' => 'PRIMARY_KEY', 'contract_note' => 'PTK sekolah dipakai dalam konteks tenant.'],
        'rapbs' => ['source_table' => 'rapbs', 'category' => 'TENANT', 'bridge' => ArkasTenantDataBridge::class, 'enabled' => true, 'key_strategy' => 'PRIMARY_KEY', 'contract_note' => 'RKAS/anggaran adalah data perencanaan sekolah.'],
        'rapbs_periode' => ['source_table' => 'rapbs_periode', 'category' => 'TENANT', 'bridge' => ArkasTenantDataBridge::class, 'enabled' => true, 'key_strategy' => 'PRIMARY_KEY', 'contract_note' => 'Periode RKAS mengikuti konteks anggaran tenant.'],
        'sekolah_penjab' => ['source_table' => 'sekolah_penjab', 'category' => 'TENANT', 'bridge' => ArkasTenantDataBridge::class, 'enabled' => true, 'key_strategy' => 'PRIMARY_KEY', 'contract_note' => 'Penanggung jawab sekolah bukan referensi global.'],
        'mst_sekolah' => ['source_table' => 'mst_sekolah', 'category' => 'TENANT', 'bridge' => ArkasTenantDataBridge::class, 'enabled' => true, 'key_strategy' => 'PRIMARY_KEY', 'contract_note' => 'Identitas sekolah menjadi provenance sumber tenant.'],
        'ref_kode' => ['source_table' => 'ref_kode', 'category' => 'UNKNOWN', 'bridge' => ArkasCentralReferenceBridge::class, 'enabled' => false, 'key_strategy' => 'PRIMARY_KEY', 'contract_note' => 'Belum dipromosikan ke central sebelum parity lintas sekolah terbukti.'],
        'ref_level_kode' => ['source_table' => 'ref_level_kode', 'category' => 'UNKNOWN', 'bridge' => ArkasCentralReferenceBridge::class, 'enabled' => false, 'key_strategy' => 'PRIMARY_KEY', 'contract_note' => 'Belum dipromosikan ke central sebelum parity lintas sekolah terbukti.'],
        'ref_periode' => ['source_table' => 'ref_periode', 'category' => 'UNKNOWN', 'bridge' => ArkasCentralReferenceBridge::class, 'enabled' => false, 'key_strategy' => 'PRIMARY_KEY', 'contract_note' => 'Belum dipromosikan ke central sebelum parity lintas sekolah terbukti.'],
        'ref_rekening' => ['source_table' => 'ref_rekening', 'category' => 'UNKNOWN', 'bridge' => ArkasCentralReferenceBridge::class, 'enabled' => false, 'key_strategy' => 'PRIMARY_KEY', 'contract_note' => 'Belum dipromosikan ke central sebelum parity lintas sekolah terbukti.'],
        'ref_sumber_dana' => ['source_table' => 'ref_sumber_dana', 'category' => 'UNKNOWN', 'bridge' => ArkasCentralReferenceBridge::class, 'enabled' => false, 'key_strategy' => 'PRIMARY_KEY', 'contract_note' => 'Belum dipromosikan ke central sebelum parity lintas sekolah terbukti.'],
    ];

    /** @return array<string, array{source_table:string, category:string, bridge:class-string<ArkasMirrorBridge>, enabled:bool, key_strategy:string, contract_note:string}> */
    public function entries(): array
    {
        return self::ENTRIES;
    }

    /** @return array<int, string> */
    public function enabledSourceTables(): array
    {
        return array_keys(array_filter(self::ENTRIES, static fn (array $entry): bool => $entry['enabled']));
    }

    /** @return array<int, string> */
    public function importableTables(array $availableTables): array
    {
        return array_values(array_intersect($this->enabledSourceTables(), $availableTables));
    }

    /** @return array{source_table:string, category:string, bridge:class-string<ArkasMirrorBridge>, enabled:bool, key_strategy:string, contract_note:string} */
    public function entry(string $sourceTable): array
    {
        if (! isset(self::ENTRIES[$sourceTable])) {
            throw new InvalidArgumentException('Tabel ARKAS tidak ada di manifest mirror: '.$sourceTable);
        }

        return self::ENTRIES[$sourceTable];
    }
}
