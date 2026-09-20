<?php

namespace App\Services;

use InvalidArgumentException;

final class ArkasMirrorManifest
{
    public const VERSION = '2026-09-20.v3';

    /** @var array<string, array<string, mixed>> */
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
        'ref_kode' => [
            'source_table' => 'ref_kode', 'category' => 'HYBRID', 'classification' => 'HYBRID_CENTRAL_BASE_TENANT_EXTENSION',
            'bridge' => ArkasCentralReferenceBridge::class, 'enabled' => false, 'availability' => 'OPTIONAL',
            'key_strategy' => 'COMPOSITE', 'key_columns' => ['id_ref_kode', 'tahun', 'sumber_dana_id', 'bentuk_pendidikan_id'],
            'canonical_key' => ['id_kode'], 'version_dimensions' => ['arkas_release', 'tahun', 'sumber_dana_id', 'bentuk_pendidikan_id'],
            'required_columns' => ['id_ref_kode', 'id_kode', 'tahun', 'sumber_dana_id', 'bentuk_pendidikan_id'],
            'target_scope' => 'CENTRAL_BASE_PLUS_TENANT_EXTENSION', 'school_scope' => 'APPLICABILITY',
            'school_scope_rule' => 'Definisi kode dapat dibagi hanya setelah semantic identity sama; applicability tetap scoped oleh tenant/year/fund/education.',
            'drift_policy' => 'CONFLICT_FAIL_CLOSED; membership drift menjadi tenant extension; source-specific semantic definitions tidak dipromosikan.',
            'contract_note' => 'ID_REF_KODE tidak portable lintas dump; id_kode adalah kandidat base identity, sedangkan tahun/sumber dana/bentuk pendidikan adalah applicability dimensions.',
        ],
        'ref_level_kode' => ['source_table' => 'ref_level_kode', 'category' => 'CENTRAL_CONFIRMED', 'classification' => 'GLOBAL_REFERENCE_CONFIRMED', 'bridge' => ArkasCentralReferenceBridge::class, 'enabled' => false, 'availability' => 'OPTIONAL', 'key_strategy' => 'PRIMARY_KEY', 'key_columns' => ['id_level_kode'], 'canonical_key' => ['id_level_kode'], 'version_dimensions' => ['arkas_release'], 'required_columns' => ['id_level_kode'], 'target_scope' => 'CENTRAL_PENDING', 'school_scope' => 'NONE', 'school_scope_rule' => 'No school membership; parity confirmed across three dumps.', 'drift_policy' => 'CONFLICT_FAIL_CLOSED', 'contract_note' => 'GLOBAL_REFERENCE_CONFIRMED by three-dump parity audit.'],
        'ref_periode' => ['source_table' => 'ref_periode', 'category' => 'CENTRAL_CONFIRMED', 'classification' => 'GLOBAL_REFERENCE_CONFIRMED', 'bridge' => ArkasCentralReferenceBridge::class, 'enabled' => false, 'availability' => 'OPTIONAL', 'key_strategy' => 'PRIMARY_KEY', 'key_columns' => ['id_periode'], 'canonical_key' => ['id_periode'], 'version_dimensions' => ['arkas_release'], 'required_columns' => ['id_periode'], 'target_scope' => 'CENTRAL_PENDING', 'school_scope' => 'NONE', 'school_scope_rule' => 'No school membership; parity confirmed across three dumps.', 'drift_policy' => 'CONFLICT_FAIL_CLOSED', 'contract_note' => 'GLOBAL_REFERENCE_CONFIRMED by three-dump parity audit.'],
        'ref_rekening' => [
            'source_table' => 'ref_rekening', 'category' => 'CENTRAL_VERSIONED', 'classification' => 'VERSIONED_GLOBAL_REFERENCE',
            'bridge' => ArkasCentralReferenceBridge::class, 'enabled' => false, 'availability' => 'OPTIONAL',
            'key_strategy' => 'COMPOSITE', 'key_columns' => ['kode_rekening', 'tahun'],
            'canonical_key' => ['kode_rekening'], 'version_dimensions' => ['tahun', 'arkas_release'],
            'required_columns' => ['kode_rekening', 'tahun'], 'target_scope' => 'CENTRAL_VERSIONED', 'school_scope' => 'NONE',
            'school_scope_rule' => 'No school membership is present; applicability is resolved by fiscal year and source release.',
            'drift_policy' => 'IGNORE_VOLATILE_PROVENANCE; semantic conflict for the same key/version fails closed.',
            'contract_note' => 'Three-dump audit found 9 row differences only in create/last-update provenance; account semantics were equal.',
        ],
        'ref_sumber_dana' => [
            'source_table' => 'ref_sumber_dana', 'category' => 'HYBRID', 'classification' => 'HYBRID_CENTRAL_BASE_TENANT_EXTENSION',
            'bridge' => ArkasCentralReferenceBridge::class, 'enabled' => false, 'availability' => 'OPTIONAL',
            'key_strategy' => 'PRIMARY_KEY', 'key_columns' => ['id_ref_sumber_dana'],
            'canonical_key' => ['kode'], 'version_dimensions' => ['arkas_release', 'fiscal_year'],
            'required_columns' => ['id_ref_sumber_dana', 'kode', 'nama_sumber_dana'],
            'target_scope' => 'CENTRAL_BASE_PLUS_TENANT_EXTENSION', 'school_scope' => 'MEMBERSHIP_AND_LOCAL_SOURCE',
            'school_scope_rule' => 'Common semantic definitions may be central base; tenant-only/custom sources and availability remain tenant-owned.',
            'drift_policy' => 'COMMON_BY_NATURAL_KEY; tenant-only rows and semantic conflicts never overwrite central base.',
            'contract_note' => 'Ten common rows are shared; source C adds code 4.3.1.61 and ref_sumber_dana_sekolah links it for 2026.',
        ],
        'ref_acuan_barang' => [
            'source_table' => 'ref_acuan_barang', 'category' => 'CENTRAL_VERSIONED', 'classification' => 'VERSIONED_GLOBAL_REFERENCE',
            'bridge' => ArkasCentralReferenceBridge::class, 'enabled' => false, 'availability' => 'OPTIONAL',
            'key_strategy' => 'COMPOSITE', 'key_columns' => ['id_barang', 'tahun'],
            'canonical_key' => ['id_barang'], 'version_dimensions' => ['tahun', 'arkas_release'],
            'required_columns' => ['id_barang', 'tahun', 'nama_barang'], 'target_scope' => 'CENTRAL_VERSIONED', 'school_scope' => 'NONE',
            'school_scope_rule' => 'Catalog rows have no school column; availability is derived from version/year, not tenant membership.',
            'drift_policy' => 'IGNORE_TIMESTAMP_FORMAT_DRIFT; same key/version semantic conflict fails closed; source-only rows remain version-scoped.',
            'contract_note' => 'Three-dump audit found 68,761 common rows, 2 source-B-only rows, and timestamp-only common-row drift.',
        ],
        'ref_bku' => [
            'source_table' => 'ref_bku', 'category' => 'CENTRAL_VERSIONED', 'classification' => 'VERSIONED_GLOBAL_REFERENCE',
            'bridge' => ArkasCentralReferenceBridge::class, 'enabled' => false, 'availability' => 'OPTIONAL',
            'key_strategy' => 'PRIMARY_KEY', 'key_columns' => ['id_ref_bku'],
            'canonical_key' => ['id_ref_bku'], 'version_dimensions' => ['arkas_release'],
            'required_columns' => ['id_ref_bku', 'bku', 'kode_bku'], 'target_scope' => 'CENTRAL_VERSIONED', 'school_scope' => 'NONE',
            'school_scope_rule' => 'Lookup semantics are global; tenant bookkeeping facts reference the stable lookup id.',
            'drift_policy' => 'IGNORE_VOLATILE_PROVENANCE; conflicting bku/kode_bku semantics fail closed.',
            'contract_note' => 'All 28 rows are common across three dumps; only last_update differs by capture time.',
        ],
        'mst_wilayah' => ['source_table' => 'mst_wilayah', 'category' => 'CENTRAL_CONFIRMED', 'classification' => 'GLOBAL_REFERENCE_CONFIRMED', 'bridge' => ArkasCentralReferenceBridge::class, 'enabled' => false, 'availability' => 'OPTIONAL', 'key_strategy' => 'PRIMARY_KEY', 'key_columns' => ['kode_wilayah'], 'canonical_key' => ['kode_wilayah'], 'version_dimensions' => ['arkas_release'], 'required_columns' => ['kode_wilayah'], 'target_scope' => 'CENTRAL_PENDING', 'school_scope' => 'NONE', 'school_scope_rule' => 'No school membership; parity confirmed across three dumps.', 'drift_policy' => 'CONFLICT_FAIL_CLOSED', 'contract_note' => 'GLOBAL_REFERENCE_CONFIRMED by three-dump parity audit.'],
        'ref_level_wilayah' => ['source_table' => 'ref_level_wilayah', 'category' => 'CENTRAL_CONFIRMED', 'classification' => 'GLOBAL_REFERENCE_CONFIRMED', 'bridge' => ArkasCentralReferenceBridge::class, 'enabled' => false, 'availability' => 'OPTIONAL', 'key_strategy' => 'PRIMARY_KEY', 'key_columns' => ['id_level_wilayah'], 'canonical_key' => ['id_level_wilayah'], 'version_dimensions' => ['arkas_release'], 'required_columns' => ['id_level_wilayah'], 'target_scope' => 'CENTRAL_PENDING', 'school_scope' => 'NONE', 'school_scope_rule' => 'No school membership; parity confirmed across three dumps.', 'drift_policy' => 'CONFLICT_FAIL_CLOSED', 'contract_note' => 'GLOBAL_REFERENCE_CONFIRMED by three-dump parity audit.'],
        'ref_negara' => ['source_table' => 'ref_negara', 'category' => 'CENTRAL_CONFIRMED', 'classification' => 'GLOBAL_REFERENCE_CONFIRMED', 'bridge' => ArkasCentralReferenceBridge::class, 'enabled' => false, 'availability' => 'OPTIONAL', 'key_strategy' => 'PRIMARY_KEY', 'key_columns' => ['negara_id'], 'canonical_key' => ['negara_id'], 'version_dimensions' => ['arkas_release'], 'required_columns' => ['negara_id'], 'target_scope' => 'CENTRAL_PENDING', 'school_scope' => 'NONE', 'school_scope_rule' => 'No school membership; parity confirmed across three dumps.', 'drift_policy' => 'CONFLICT_FAIL_CLOSED', 'contract_note' => 'GLOBAL_REFERENCE_CONFIRMED by three-dump parity audit.'],
        'ref_jabatan' => ['source_table' => 'ref_jabatan', 'category' => 'CENTRAL_CONFIRMED', 'classification' => 'GLOBAL_REFERENCE_CONFIRMED', 'bridge' => ArkasCentralReferenceBridge::class, 'enabled' => false, 'availability' => 'OPTIONAL', 'key_strategy' => 'PRIMARY_KEY', 'key_columns' => ['jabatan_id'], 'canonical_key' => ['jabatan_id'], 'version_dimensions' => ['arkas_release'], 'required_columns' => ['jabatan_id'], 'target_scope' => 'CENTRAL_PENDING', 'school_scope' => 'NONE', 'school_scope_rule' => 'No school membership; parity confirmed across three dumps.', 'drift_policy' => 'CONFLICT_FAIL_CLOSED', 'contract_note' => 'GLOBAL_REFERENCE_CONFIRMED by three-dump parity audit.'],
        'ref_jenis_instansi' => ['source_table' => 'ref_jenis_instansi', 'category' => 'CENTRAL_CONFIRMED', 'classification' => 'GLOBAL_REFERENCE_CONFIRMED', 'bridge' => ArkasCentralReferenceBridge::class, 'enabled' => false, 'availability' => 'OPTIONAL', 'key_strategy' => 'PRIMARY_KEY', 'key_columns' => ['jenis_instansi_id'], 'canonical_key' => ['jenis_instansi_id'], 'version_dimensions' => ['arkas_release'], 'required_columns' => ['jenis_instansi_id'], 'target_scope' => 'CENTRAL_PENDING', 'school_scope' => 'NONE', 'school_scope_rule' => 'No school membership; parity confirmed across three dumps.', 'drift_policy' => 'CONFLICT_FAIL_CLOSED', 'contract_note' => 'GLOBAL_REFERENCE_CONFIRMED by three-dump parity audit.'],
        'ref_satuan' => ['source_table' => 'ref_satuan', 'category' => 'CENTRAL_CONFIRMED', 'classification' => 'GLOBAL_REFERENCE_CONFIRMED', 'bridge' => ArkasCentralReferenceBridge::class, 'enabled' => false, 'availability' => 'OPTIONAL', 'key_strategy' => 'PRIMARY_KEY', 'key_columns' => ['ref_satuan_id'], 'canonical_key' => ['ref_satuan_id'], 'version_dimensions' => ['arkas_release'], 'required_columns' => ['ref_satuan_id'], 'target_scope' => 'CENTRAL_PENDING', 'school_scope' => 'NONE', 'school_scope_rule' => 'No school membership; parity confirmed across three dumps.', 'drift_policy' => 'CONFLICT_FAIL_CLOSED', 'contract_note' => 'GLOBAL_REFERENCE_CONFIRMED by three-dump parity audit.'],
        'ref_indikator' => ['source_table' => 'ref_indikator', 'category' => 'CENTRAL_CONFIRMED', 'classification' => 'GLOBAL_REFERENCE_CONFIRMED', 'bridge' => ArkasCentralReferenceBridge::class, 'enabled' => false, 'availability' => 'OPTIONAL', 'key_strategy' => 'PRIMARY_KEY', 'key_columns' => ['id_ref_indikator'], 'canonical_key' => ['id_ref_indikator'], 'version_dimensions' => ['arkas_release'], 'required_columns' => ['id_ref_indikator'], 'target_scope' => 'CENTRAL_PENDING', 'school_scope' => 'NONE', 'school_scope_rule' => 'No school membership; parity confirmed across three dumps.', 'drift_policy' => 'CONFLICT_FAIL_CLOSED', 'contract_note' => 'GLOBAL_REFERENCE_CONFIRMED by three-dump parity audit.'],
        'ref_sumber_dana_sekolah' => [
            'source_table' => 'ref_sumber_dana_sekolah', 'category' => 'TENANT', 'classification' => 'OPTIONAL_TENANT_REFERENCE',
            'bridge' => ArkasTenantDataBridge::class, 'enabled' => true, 'availability' => 'OPTIONAL',
            'key_strategy' => 'COMPOSITE', 'key_columns' => ['id_ref_sumber_dana', 'tahun'],
            'canonical_key' => ['id_ref_sumber_dana'], 'version_dimensions' => ['tahun'],
            'required_columns' => ['id_ref_sumber_dana', 'tahun'], 'target_scope' => 'TENANT', 'school_scope' => 'DIRECT_TENANT_DB',
            'school_scope_rule' => 'The school is supplied by the owning tenant database; row presence and delete_at are tenant membership state.',
            'drift_policy' => 'OPTIONAL_EMPTY_ALLOWED; never promote membership to central and never synthesize absent rows.',
            'contract_note' => 'A/B are empty and C contains (61, 2026); composite key is stable and table is an optional tenant extension.',
        ],
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
