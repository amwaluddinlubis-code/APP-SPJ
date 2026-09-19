<?php

namespace App\Services;

use InvalidArgumentException;

final class ArkasMirrorManifest
{
    public const VERSION = '2026-09-19.v2';

    /** @var array<string, array{source_table:string, category:string, bridge:class-string<ArkasMirrorBridge>, enabled:bool, availability:string, key_strategy:string, key_columns:array<int, string>, required_columns:array<int, string>, school_scope:string, contract_note:string}> */
    private const ENTRIES = [
        'aktivasi_bku' => ['source_table' => 'aktivasi_bku', 'category' => 'TENANT', 'bridge' => ArkasTenantDataBridge::class, 'enabled' => true, 'availability' => 'REQUIRED', 'key_strategy' => 'COMPOSITE', 'key_columns' => ['id_anggaran', 'id_periode'], 'required_columns' => ['id_anggaran', 'id_periode'], 'school_scope' => 'ANGGARAN', 'contract_note' => 'Aktivasi BKU mengikuti anggaran tenant dan periode sumber.'],
        'anggaran' => ['source_table' => 'anggaran', 'category' => 'TENANT', 'bridge' => ArkasTenantDataBridge::class, 'enabled' => true, 'availability' => 'REQUIRED', 'key_strategy' => 'PRIMARY_KEY', 'key_columns' => ['id_anggaran'], 'required_columns' => ['id_anggaran', 'id_ref_sumber_dana', 'sekolah_id', 'tahun_anggaran'], 'school_scope' => 'DIRECT', 'contract_note' => 'Anggaran dan konteks tahun/sumber dana milik sekolah sumber.'],
        'kas_umum' => ['source_table' => 'kas_umum', 'category' => 'TENANT', 'bridge' => ArkasTenantDataBridge::class, 'enabled' => true, 'availability' => 'REQUIRED', 'key_strategy' => 'PRIMARY_KEY', 'key_columns' => ['id_kas_umum'], 'required_columns' => ['id_kas_umum', 'id_anggaran'], 'school_scope' => 'ANGGARAN', 'contract_note' => 'BKU adalah fakta operasional tenant dan sumber identity transaksi.'],
        'kas_umum_nota' => ['source_table' => 'kas_umum_nota', 'category' => 'TENANT', 'bridge' => ArkasTenantDataBridge::class, 'enabled' => true, 'availability' => 'REQUIRED', 'key_strategy' => 'PRIMARY_KEY', 'key_columns' => ['id_kas_nota'], 'required_columns' => ['id_kas_nota'], 'school_scope' => 'TRANSACTION', 'contract_note' => 'Nota transaksi tetap berada pada tenant yang memiliki transaksi induk.'],
        'kas_umum_nota_pajak' => ['source_table' => 'kas_umum_nota_pajak', 'category' => 'TENANT', 'bridge' => ArkasTenantDataBridge::class, 'enabled' => true, 'availability' => 'REQUIRED', 'key_strategy' => 'COMPOSITE', 'key_columns' => ['id_kas_nota', 'ntpn'], 'required_columns' => ['id_kas_nota', 'ntpn'], 'expected_types' => ['id_kas_nota' => ['CHAR', 'TEXT', 'VARCHAR'], 'ntpn' => ['CHAR', 'TEXT', 'VARCHAR']], 'school_scope' => 'TRANSACTION', 'contract_note' => 'Rincian pajak memakai identitas unik id_kas_nota + ntpn; bukan primary key tunggal.'],
        'pegawai' => ['source_table' => 'pegawai', 'category' => 'TENANT', 'bridge' => ArkasTenantDataBridge::class, 'enabled' => true, 'availability' => 'OPTIONAL', 'key_strategy' => 'PRIMARY_KEY', 'key_columns' => [], 'required_columns' => [], 'school_scope' => 'DIRECT', 'contract_note' => 'Master pegawai opsional; source yang tidak memiliki tabel ini tidak menggagalkan mirror.'],
        'ptk' => ['source_table' => 'ptk', 'category' => 'TENANT', 'bridge' => ArkasTenantDataBridge::class, 'enabled' => true, 'availability' => 'REQUIRED', 'key_strategy' => 'COMPOSITE', 'key_columns' => ['sekolah_id', 'ptk_id', 'tahun_ajaran_id'], 'required_columns' => ['sekolah_id', 'ptk_id', 'tahun_ajaran_id'], 'school_scope' => 'DIRECT', 'contract_note' => 'PTK sekolah dipakai dalam konteks tenant dan tahun ajaran.'],
        'rapbs' => ['source_table' => 'rapbs', 'category' => 'TENANT', 'bridge' => ArkasTenantDataBridge::class, 'enabled' => true, 'availability' => 'REQUIRED', 'key_strategy' => 'PRIMARY_KEY', 'key_columns' => ['id_rapbs'], 'required_columns' => ['id_rapbs', 'sekolah_id', 'id_anggaran', 'id_ref_kode', 'id_ref_tahun_anggaran'], 'school_scope' => 'ANGGARAN', 'contract_note' => 'RKAS adalah data perencanaan sekolah.'],
        'rapbs_periode' => ['source_table' => 'rapbs_periode', 'category' => 'TENANT', 'bridge' => ArkasTenantDataBridge::class, 'enabled' => true, 'availability' => 'REQUIRED', 'key_strategy' => 'PRIMARY_KEY', 'key_columns' => ['id_rapbs_periode'], 'required_columns' => ['id_rapbs_periode', 'id_rapbs', 'id_periode'], 'school_scope' => 'RAPBS', 'contract_note' => 'Periode RKAS mengikuti konteks anggaran tenant.'],
        'rapbs_ptk' => ['source_table' => 'rapbs_ptk', 'category' => 'TENANT', 'bridge' => ArkasTenantDataBridge::class, 'enabled' => true, 'availability' => 'REQUIRED', 'key_strategy' => 'COMPOSITE', 'key_columns' => ['id_rapbs', 'ptk_id'], 'required_columns' => ['id_rapbs', 'ptk_id'], 'school_scope' => 'RAPBS', 'contract_note' => 'Relasi PTK-RKAS mengikuti tenant dan rencana kerja.'],
        'salur' => ['source_table' => 'salur', 'category' => 'TENANT', 'bridge' => ArkasTenantDataBridge::class, 'enabled' => true, 'availability' => 'REQUIRED', 'key_strategy' => 'COMPOSITE', 'key_columns' => ['sekolah_id', 'npsn', 'tahap', 'gelombang', 'jenis', 'id_ref_sumber_dana', 'tahun'], 'required_columns' => ['sekolah_id', 'npsn', 'tahap', 'gelombang', 'jenis', 'id_ref_sumber_dana', 'tahun'], 'school_scope' => 'DIRECT', 'contract_note' => 'Penyaluran dana adalah fakta tenant dan tahun sumber.'],
        'sekolah_history' => ['source_table' => 'sekolah_history', 'category' => 'TENANT', 'bridge' => ArkasTenantDataBridge::class, 'enabled' => true, 'availability' => 'REQUIRED', 'key_strategy' => 'COMPOSITE', 'key_columns' => ['sekolah_id', 'tahun'], 'required_columns' => ['sekolah_id', 'tahun'], 'school_scope' => 'DIRECT', 'contract_note' => 'Riwayat identitas sekolah adalah provenance tenant.'],
        'sekolah_penjab' => ['source_table' => 'sekolah_penjab', 'category' => 'TENANT', 'bridge' => ArkasTenantDataBridge::class, 'enabled' => true, 'availability' => 'REQUIRED', 'key_strategy' => 'COMPOSITE', 'key_columns' => ['id_penjab', 'tahun'], 'required_columns' => ['id_penjab', 'sekolah_id', 'tahun'], 'school_scope' => 'DIRECT', 'contract_note' => 'Penanggung jawab sekolah bukan referensi global.'],
        'mst_sekolah' => ['source_table' => 'mst_sekolah', 'category' => 'TENANT', 'bridge' => ArkasTenantDataBridge::class, 'enabled' => true, 'availability' => 'REQUIRED', 'key_strategy' => 'PRIMARY_KEY', 'key_columns' => ['sekolah_id'], 'required_columns' => ['sekolah_id'], 'school_scope' => 'DIRECT', 'contract_note' => 'Identitas sekolah menjadi provenance sumber tenant.'],
        'ref_kode' => ['source_table' => 'ref_kode', 'category' => 'UNKNOWN', 'bridge' => ArkasCentralReferenceBridge::class, 'enabled' => false, 'availability' => 'OPTIONAL', 'key_strategy' => 'COMPOSITE', 'key_columns' => ['id_ref_kode', 'tahun', 'sumber_dana_id', 'bentuk_pendidikan_id'], 'required_columns' => ['id_ref_kode', 'tahun', 'sumber_dana_id', 'bentuk_pendidikan_id'], 'school_scope' => 'NONE', 'contract_note' => 'Belum dipromosikan ke central sebelum parity lintas sekolah terbukti.'],
        'ref_level_kode' => ['source_table' => 'ref_level_kode', 'category' => 'UNKNOWN', 'bridge' => ArkasCentralReferenceBridge::class, 'enabled' => false, 'availability' => 'OPTIONAL', 'key_strategy' => 'PRIMARY_KEY', 'key_columns' => ['id_level_kode'], 'required_columns' => ['id_level_kode'], 'school_scope' => 'NONE', 'contract_note' => 'Belum dipromosikan ke central sebelum parity lintas sekolah terbukti.'],
        'ref_periode' => ['source_table' => 'ref_periode', 'category' => 'CENTRAL_CANDIDATE', 'bridge' => ArkasCentralReferenceBridge::class, 'enabled' => false, 'availability' => 'OPTIONAL', 'key_strategy' => 'PRIMARY_KEY', 'key_columns' => ['id_periode'], 'required_columns' => ['id_periode'], 'school_scope' => 'NONE', 'contract_note' => 'Kandidat central; parity lintas sekolah belum terbukti.'],
        'ref_rekening' => ['source_table' => 'ref_rekening', 'category' => 'UNKNOWN', 'bridge' => ArkasCentralReferenceBridge::class, 'enabled' => false, 'availability' => 'OPTIONAL', 'key_strategy' => 'COMPOSITE', 'key_columns' => ['kode_rekening', 'tahun'], 'required_columns' => ['kode_rekening', 'tahun'], 'school_scope' => 'NONE', 'contract_note' => 'Berversi tahun; belum dipromosikan ke central.'],
        'ref_sumber_dana' => ['source_table' => 'ref_sumber_dana', 'category' => 'CENTRAL_CANDIDATE', 'bridge' => ArkasCentralReferenceBridge::class, 'enabled' => false, 'availability' => 'OPTIONAL', 'key_strategy' => 'PRIMARY_KEY', 'key_columns' => ['id_ref_sumber_dana'], 'required_columns' => ['id_ref_sumber_dana'], 'school_scope' => 'NONE', 'contract_note' => 'Kandidat central; parity lintas sekolah belum terbukti.'],
    ];

    /** @param array<string, array<string, mixed>>|null $entries */
    public function __construct(private readonly ?array $entries = null) {}

    /** @return array<string, array<string, mixed>> */
    private function definitions(): array
    {
        return $this->entries ?? self::ENTRIES;
    }

    /** @return array<string, array<string, mixed>> */
    public function entries(): array
    {
        return $this->definitions();
    }

    /** @return array<int, string> */
    public function enabledSourceTables(): array
    {
        return array_keys(array_filter($this->definitions(), static fn (array $entry): bool => $entry['enabled']));
    }

    /** @return array<int, string> */
    public function requiredSourceTables(): array
    {
        return array_keys(array_filter($this->definitions(), static fn (array $entry): bool => $entry['enabled'] && $entry['availability'] === 'REQUIRED'));
    }

    /** @return array<int, string> */
    public function optionalSourceTables(): array
    {
        return array_keys(array_filter($this->definitions(), static fn (array $entry): bool => $entry['enabled'] && $entry['availability'] === 'OPTIONAL'));
    }

    /** @return array<int, string> */
    public function importableTables(array $availableTables): array
    {
        return array_values(array_intersect($this->enabledSourceTables(), $availableTables));
    }

    /** @return array<string, mixed> */
    public function entry(string $sourceTable): array
    {
        if (! isset($this->definitions()[$sourceTable])) {
            throw new InvalidArgumentException('Tabel ARKAS tidak ada di manifest mirror: '.$sourceTable);
        }

        return $this->definitions()[$sourceTable];
    }
}
