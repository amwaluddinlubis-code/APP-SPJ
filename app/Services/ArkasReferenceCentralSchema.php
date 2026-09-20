<?php

namespace App\Services;

use InvalidArgumentException;

final class ArkasReferenceCentralSchema
{
    /** @var array<string, array<string, mixed>> */
    private const DEFINITIONS = [
        'mst_wilayah' => ['target_table' => 'central_ref_mst_wilayah', 'natural_key' => ['kode_wilayah'], 'version_dimensions' => ['arkas_release'], 'semantic_columns' => ['kode_wilayah', 'mst_kode_wilayah', 'negara_id', 'id_level_wilayah', 'nama', 'asal_wilayah', 'kode_bps', 'kode_dagri', 'kode_keu']],
        'ref_level_wilayah' => ['target_table' => 'central_ref_level_wilayah', 'natural_key' => ['id_level_wilayah'], 'version_dimensions' => ['arkas_release'], 'semantic_columns' => ['id_level_wilayah', 'level_wilayah']],
        'ref_negara' => ['target_table' => 'central_ref_negara', 'natural_key' => ['negara_id'], 'version_dimensions' => ['arkas_release'], 'semantic_columns' => ['negara_id', 'nama', 'luar_negeri']],
        'ref_jabatan' => ['target_table' => 'central_ref_jabatan', 'natural_key' => ['jabatan_id'], 'version_dimensions' => ['arkas_release'], 'semantic_columns' => ['jabatan_id', 'ref_jabatan_id', 'nama', 'level_jabatan']],
        'ref_jenis_instansi' => ['target_table' => 'central_ref_jenis_instansi', 'natural_key' => ['jenis_instansi_id'], 'version_dimensions' => ['arkas_release'], 'semantic_columns' => ['jenis_instansi_id', 'nama', 'sort']],
        'ref_satuan' => ['target_table' => 'central_ref_satuan', 'natural_key' => ['ref_satuan_id'], 'version_dimensions' => ['arkas_release'], 'semantic_columns' => ['ref_satuan_id', 'satuan', 'unit']],
        'ref_periode' => ['target_table' => 'central_ref_periode', 'natural_key' => ['id_periode'], 'version_dimensions' => ['arkas_release'], 'semantic_columns' => ['id_periode', 'periode']],
        'ref_level_kode' => ['target_table' => 'central_ref_level_kode', 'natural_key' => ['id_level_kode'], 'version_dimensions' => ['arkas_release'], 'semantic_columns' => ['id_level_kode', 'nama']],
        'ref_indikator' => ['target_table' => 'central_ref_indikator', 'natural_key' => ['id_ref_indikator'], 'version_dimensions' => ['arkas_release'], 'semantic_columns' => ['id_ref_indikator', 'nama_indikator']],
        'ref_rekening' => ['target_table' => 'central_ref_rekening', 'natural_key' => ['kode_rekening'], 'version_dimensions' => ['tahun', 'arkas_release'], 'semantic_columns' => ['kode_rekening', 'rekening', 'neraca', 'blokid', 'batas_atas', 'batas_bawah', 'validasi_type', 'is_ppn', 'is_pph21', 'is_pph22', 'is_pph4', 'is_sspd', 'bhp', 'is_custom_pajak_1', 'is_honor', 'is_buku', 'is_custom_satuan', 'tahun']],
        'ref_acuan_barang' => ['target_table' => 'central_ref_acuan_barang', 'natural_key' => ['id_barang'], 'version_dimensions' => ['tahun', 'arkas_release'], 'semantic_columns' => ['id_barang', 'kode_rekening', 'nama_barang', 'satuan', 'blok_id', 'kode_belanja', 'harga_barang', 'batas_bawah', 'batas_atas', 'kategori_id', 'hs_code', 'kbki', 'tahun']],
        'ref_bku' => ['target_table' => 'central_ref_bku', 'natural_key' => ['id_ref_bku'], 'version_dimensions' => ['arkas_release'], 'semantic_columns' => ['id_ref_bku', 'bku', 'kode_bku']],
        'ref_sumber_dana' => ['target_table' => 'central_ref_sumber_dana', 'natural_key' => ['kode'], 'version_dimensions' => ['arkas_release'], 'semantic_columns' => ['kode', 'nama_sumber_dana', 'expired_date', 'is_hidden', 'alias', 'jumlah_tahap_pelaporan', 'role_pengesahan', 'tanggal_akhir_belanja', 'tanggal_akhir_laporan', 'tanggal_awal_belanja', 'tanggal_awal_laporan', 'tenggat_penatausahaan', 'tenggat_penganggaran', 'validasi_anggaran', 'is_filtered_by_instansi_id', 'is_sumber_dana_tambahan', 'mekanisme_pengesahan', 'is_afirmasi']],
        'ref_kode' => ['target_table' => 'central_ref_kode', 'natural_key' => ['id_kode'], 'version_dimensions' => ['arkas_release'], 'semantic_columns' => ['id_kode', 'parent_kode', 'uraian_kode', 'id_level_kode', 'tipe'], 'extension_columns' => ['id_ref_kode', 'tahun', 'sumber_dana_id', 'bentuk_pendidikan_id', 'is_bos_pusat', 'is_bos_prop', 'is_bos_kab', 'is_komite', 'is_lainnnya', 'expired_date']],
    ];

    /** @return array<string, array<string, mixed>> */
    public static function definitions(): array
    {
        return self::DEFINITIONS;
    }

    /** @return array<string, mixed> */
    public static function for(string $table): array
    {
        if (! isset(self::DEFINITIONS[$table])) {
            throw new InvalidArgumentException('Tabel tidak memiliki target central reference schema: '.$table);
        }

        return self::DEFINITIONS[$table];
    }
}
