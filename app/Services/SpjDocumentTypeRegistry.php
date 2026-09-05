<?php

namespace App\Services;

final class SpjDocumentTypeRegistry
{
    public const EMPTY_SCALAR_VALUE = '-';

    public const SPJ_COVER = 'SPJ_COVER';
    public const SPJ_CHECKLIST = 'SPJ_CHECKLIST';
    public const KUITANSI_A2 = 'KUITANSI_A2';
    public const RINCIAN_BELANJA = 'RINCIAN_BELANJA';
    public const REKAP_PAJAK = 'REKAP_PAJAK';
    public const SURAT_PESANAN = 'SURAT_PESANAN';
    public const BAP = 'BAP';
    public const BAST = 'BAST';
    public const INVOICE = 'INVOICE';
    public const RAB_PEMELIHARAAN = 'RAB_PEMELIHARAAN';
    public const SPK_PEMELIHARAAN = 'SPK_PEMELIHARAAN';

    public const SCOPE_PACKAGE = 'PACKAGE';
    public const SCOPE_TRANSACTION = 'TRANSACTION';

    public const SOURCE_PACKAGE_GENERATED = 'PACKAGE_GENERATED';
    public const SOURCE_GENERATED = 'GENERATED';
    public const SOURCE_EXTERNAL = 'EXTERNAL';

    public const USAGE_ADMIN_REPRINT = 'ADMIN_REPRINT';

    /** @return array<int,string> */
    public static function categories(): array
    {
        return [
            'BARANG',
            'KONSUMSI',
            'PEMELIHARAAN',
            'SPPD',
            'HONOR_PEGAWAI',
            'JASA_LAINNYA',
        ];
    }

    /** @return array<int,array{0:string,1:string}> */
    public static function pairedPlaceholders(): array
    {
        return [
            ['NAMA_KEPALA_SEKOLAH', 'NIP_KEPALA_SEKOLAH'],
            ['NAMA_BENDAHARA_BOSP', 'NIP_BENDAHARA_BOSP'],
        ];
    }

    /** @return array<int,string> */
    public static function technicalSheets(): array
    {
        return ['PLACEHOLDER_MAP'];
    }

    /**
     * Registry canonical tipe dokumen SPJ.
     *
     * required/optional berisi placeholder scalar.
     * repeat_required/repeat_optional berisi placeholder baris dinamis.
     * image berisi placeholder yang dirender khusus sebagai gambar.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function all(): array
    {
        $allCategories = self::categories();

        return [
            self::SPJ_COVER => [
                'label' => 'Cover SPJ',
                'sheet' => 'TPL_COVER_SPJ',
                'scope' => self::SCOPE_PACKAGE,
                'source' => self::SOURCE_PACKAGE_GENERATED,
                'usage' => null,
                'applicable_categories' => $allCategories,
                'required' => [
                    'NOMOR_DOKUMEN', 'NOMOR_BUKTI', 'TANGGAL_DOKUMEN', 'SUMBER_DANA_PERIODE',
                    'JENIS_SPJ', 'KODE_KEGIATAN', 'NAMA_KEGIATAN', 'KODE_REKENING',
                    'NAMA_REKENING', 'URAIAN_TRANSAKSI', 'NILAI_BRUTO', 'NILAI_DIBAYARKAN',
                    'NAMA_KEPALA_SEKOLAH', 'NIP_KEPALA_SEKOLAH',
                    'NAMA_BENDAHARA_BOSP', 'NIP_BENDAHARA_BOSP',
                ],
                'optional' => [
                    'TAHUN_ANGGARAN', 'SUMBER_DANA', 'NAMA_PENERIMA', 'POTONGAN_PAJAK',
                    'TRIWULAN', 'SEMESTER',
                ],
                'repeat_required' => [],
                'repeat_optional' => [],
                'image' => ['KOP_SURAT'],
            ],

            self::SPJ_CHECKLIST => [
                'label' => 'Checklist Kelengkapan SPJ',
                'sheet' => 'TPL_CHECKLIST_SPJ',
                'scope' => self::SCOPE_PACKAGE,
                'source' => self::SOURCE_PACKAGE_GENERATED,
                'usage' => null,
                'applicable_categories' => $allCategories,
                'required' => ['NOMOR_DOKUMEN', 'NOMOR_BUKTI', 'TANGGAL_DOKUMEN', 'SUMBER_DANA_PERIODE'],
                'optional' => [
                    'JENIS_SPJ', 'NAMA_SEKOLAH', 'NPSN', 'TRIWULAN', 'SEMESTER',
                    'NAMA_KEPALA_SEKOLAH', 'NIP_KEPALA_SEKOLAH',
                    'NAMA_BENDAHARA_BOSP', 'NIP_BENDAHARA_BOSP',
                ],
                'repeat_required' => [],
                'repeat_optional' => [],
                'image' => [],
            ],

            self::KUITANSI_A2 => [
                'label' => 'Kuitansi / Bukti Kas Pengeluaran (A2)',
                'sheet' => 'TPL_KUITANSI',
                'scope' => self::SCOPE_TRANSACTION,
                'source' => self::SOURCE_GENERATED,
                'usage' => null,
                'applicable_categories' => $allCategories,
                'required' => [
                    'NOMOR_DOKUMEN', 'NOMOR_BUKTI', 'TANGGAL_DOKUMEN', 'SUDAH_TERIMA_DARI',
                    'NAMA_PENERIMA_KUITANSI', 'UNTUK_PEMBAYARAN', 'CARA_BAYAR_REFERENSI', 'NILAI_BRUTO',
                    'TOTAL_PAJAK', 'NILAI_DIBAYARKAN', 'TERBILANG_NETO',
                    'NAMA_KEPALA_SEKOLAH', 'NIP_KEPALA_SEKOLAH',
                    'NAMA_BENDAHARA_BOSP', 'NIP_BENDAHARA_BOSP',
                ],
                'optional' => [
                    'PENERIMA_PENYEDIA', 'CARA_BAYAR', 'REFERENSI_BAYAR',
                    'PPN', 'PPH21', 'PPH22', 'PPH23', 'PPH4', 'SSPD',
                    'SUMBER_DANA', 'KODE_KEGIATAN', 'KODE_REKENING',
                ],
                'repeat_required' => [],
                'repeat_optional' => [],
                'image' => [],
            ],

            self::RINCIAN_BELANJA => [
                'label' => 'Rincian Belanja',
                'sheet' => 'TPL_RINCIAN',
                'scope' => self::SCOPE_TRANSACTION,
                'source' => self::SOURCE_GENERATED,
                'usage' => null,
                'applicable_categories' => $allCategories,
                'required' => ['NOMOR_DOKUMEN', 'NOMOR_BUKTI', 'NILAI_BRUTO'],
                'optional' => ['KODE_KEGIATAN', 'NAMA_KEGIATAN', 'KODE_REKENING', 'NAMA_REKENING'],
                'repeat_required' => ['ITEM_NO', 'ITEM_URAIAN', 'ITEM_VOLUME', 'ITEM_SATUAN', 'ITEM_HARGA_SATUAN', 'ITEM_JUMLAH'],
                'repeat_optional' => ['ITEM_KODE_REKENING', 'ITEM_NAMA_REKENING'],
                'image' => [],
            ],

            self::REKAP_PAJAK => [
                'label' => 'Rekap Pajak',
                'sheet' => 'TPL_REKAP_PAJAK',
                'scope' => self::SCOPE_TRANSACTION,
                'source' => self::SOURCE_GENERATED,
                'usage' => null,
                'applicable_categories' => $allCategories,
                'required' => ['NOMOR_DOKUMEN', 'NOMOR_BUKTI', 'TANGGAL_DOKUMEN', 'NILAI_BRUTO', 'TOTAL_PAJAK'],
                'optional' => [
                    'NILAI_DIBAYARKAN', 'PPN', 'PPH21', 'PPH22', 'PPH23', 'PPH4', 'SSPD',
                    'KODE_REKENING', 'NAMA_REKENING', 'URAIAN_TRANSAKSI', 'NAMA_PENYEDIA', 'NPWP_PENYEDIA',
                ],
                'repeat_required' => [],
                'repeat_optional' => [],
                'image' => [],
            ],

            self::SURAT_PESANAN => [
                'label' => 'Surat Pesanan Internal',
                'sheet' => 'TPL_SURAT_PESANAN',
                'scope' => self::SCOPE_TRANSACTION,
                'source' => self::SOURCE_GENERATED,
                'usage' => null,
                'applicable_categories' => ['BARANG', 'KONSUMSI', 'JASA_LAINNYA'],
                'required' => [
                    'NOMOR_PESANAN', 'TANGGAL_PESANAN', 'NAMA_PENYEDIA', 'ALAMAT_PENYEDIA',
                    'KODE_KEGIATAN', 'NAMA_KEGIATAN', 'NILAI_BRUTO', 'TEMPAT_PENYERAHAN',
                    'TANGGAL_PENYERAHAN', 'NAMA_KEPALA_SEKOLAH', 'NIP_KEPALA_SEKOLAH',
                ],
                'optional' => [
                    'KODE_REKENING', 'NAMA_REKENING', 'NPWP_PENYEDIA', 'TELEPON_PENYEDIA',
                    'CARA_BAYAR', 'REFERENSI_BAYAR', 'CARA_BAYAR_REFERENSI',
                ],
                'repeat_required' => ['ITEM_NO', 'ITEM_URAIAN', 'ITEM_VOLUME', 'ITEM_SATUAN', 'ITEM_HARGA_SATUAN', 'ITEM_JUMLAH'],
                'repeat_optional' => [],
                'image' => ['KOP_SURAT'],
            ],

            self::BAP => [
                'label' => 'Berita Acara Pemeriksaan/Penerimaan',
                'sheet' => 'TPL_BA_PEMERIKSAAN',
                'scope' => self::SCOPE_TRANSACTION,
                'source' => self::SOURCE_GENERATED,
                'usage' => null,
                'applicable_categories' => ['BARANG', 'KONSUMSI'],
                'required' => [
                    'NOMOR_DOKUMEN', 'TANGGAL_DOKUMEN', 'NOMOR_PESANAN', 'TANGGAL_PESANAN',
                    'NAMA_PENYEDIA', 'TANGGAL_PENYERAHAN', 'TEMPAT_PENYERAHAN',
                    'NAMA_KEPALA_SEKOLAH', 'NIP_KEPALA_SEKOLAH',
                    'NAMA_BENDAHARA_BOSP', 'NIP_BENDAHARA_BOSP',
                ],
                'optional' => [
                    'KODE_KEGIATAN', 'NAMA_KEGIATAN', 'KODE_REKENING', 'NAMA_REKENING', 'NPWP_PENYEDIA',
                ],
                'repeat_required' => ['ITEM_NO', 'ITEM_URAIAN', 'ITEM_VOLUME', 'ITEM_SATUAN'],
                'repeat_optional' => ['ITEM_HARGA_SATUAN', 'ITEM_JUMLAH'],
                'image' => ['KOP_SURAT'],
            ],

            self::BAST => [
                'label' => 'Berita Acara Serah Terima',
                'sheet' => 'TPL_BA_SERAH_TERIMA',
                'scope' => self::SCOPE_TRANSACTION,
                'source' => self::SOURCE_GENERATED,
                'usage' => null,
                'applicable_categories' => ['BARANG', 'KONSUMSI'],
                'required' => [
                    'NOMOR_DOKUMEN', 'TANGGAL_DOKUMEN', 'NAMA_PENYEDIA', 'UNTUK_PEMBAYARAN',
                    'TANGGAL_PENYERAHAN', 'TEMPAT_PENYERAHAN', 'NAMA_KEPALA_SEKOLAH',
                    'NIP_KEPALA_SEKOLAH', 'NAMA_BENDAHARA_BOSP', 'NIP_BENDAHARA_BOSP',
                ],
                'optional' => [
                    'NOMOR_PESANAN', 'TANGGAL_PESANAN', 'ALAMAT_PENYEDIA', 'NPWP_PENYEDIA',
                    'TELEPON_PENYEDIA', 'NILAI_BRUTO',
                ],
                'repeat_required' => ['ITEM_NO', 'ITEM_URAIAN', 'ITEM_VOLUME'],
                'repeat_optional' => ['ITEM_SATUAN', 'ITEM_HARGA_SATUAN', 'ITEM_JUMLAH'],
                'image' => ['KOP_SURAT'],
            ],

            self::INVOICE => [
                'label' => 'Invoice / Faktur',
                'sheet' => 'TPL_INVOICE',
                'scope' => self::SCOPE_TRANSACTION,
                'source' => self::SOURCE_EXTERNAL,
                'usage' => self::USAGE_ADMIN_REPRINT,
                'applicable_categories' => ['BARANG', 'KONSUMSI', 'JASA_LAINNYA'],
                'required' => ['NOMOR_INVOICE', 'TANGGAL_INVOICE', 'NAMA_PENYEDIA', 'NILAI_BRUTO', 'TOTAL_PAJAK', 'NILAI_DIBAYARKAN'],
                'optional' => [
                    'STATUS_INVOICE', 'NPWP_PENYEDIA', 'ALAMAT_PENYEDIA', 'TELEPON_PENYEDIA',
                    'NOMOR_PESANAN', 'TANGGAL_PESANAN', 'PPN', 'PPH21', 'PPH22', 'PPH23',
                    'PPH4', 'SSPD', 'TERBILANG_NETO', 'CARA_BAYAR_REFERENSI',
                    'SIPLAH_NOMOR_PESANAN', 'SIPLAH_REFERENSI_BAYAR',
                ],
                'repeat_required' => ['ITEM_NO', 'ITEM_URAIAN', 'ITEM_VOLUME', 'ITEM_SATUAN', 'ITEM_HARGA_SATUAN', 'ITEM_JUMLAH'],
                'repeat_optional' => [],
                'image' => [],
            ],

            self::RAB_PEMELIHARAAN => [
                'label' => 'RAB Pekerjaan/Pemeliharaan',
                'sheet' => 'TPL_RAB_PEMELIHARAAN',
                'scope' => self::SCOPE_TRANSACTION,
                'source' => self::SOURCE_GENERATED,
                'usage' => null,
                'applicable_categories' => ['PEMELIHARAAN'],
                'required' => [
                    'TANGGAL_RAB', 'URAIAN_PEKERJAAN', 'NILAI_PEKERJAAN',
                    'NAMA_KEPALA_SEKOLAH', 'NIP_KEPALA_SEKOLAH',
                ],
                'optional' => [
                    'NOMOR_RAB', 'LOKASI_PEKERJAAN', 'NILAI_PEKERJAAN_TERBILANG',
                    'NAMA_BENDAHARA_BOSP', 'NIP_BENDAHARA_BOSP', 'KODE_KEGIATAN', 'NAMA_KEGIATAN',
                ],
                'repeat_required' => [
                    'ITEM_NO', 'ITEM_URAIAN', 'ITEM_VOLUME', 'ITEM_SATUAN', 'ITEM_HARGA_SATUAN', 'ITEM_JUMLAH',
                    'UPAH_NO', 'UPAH_NAMA', 'UPAH_PEKERJAAN', 'UPAH_HARI', 'UPAH_TARIF_HARI', 'UPAH_JUMLAH',
                ],
                'repeat_optional' => ['UPAH_PENERIMA_KUITANSI'],
                'image' => ['KOP_SURAT'],
            ],

            self::SPK_PEMELIHARAAN => [
                'label' => 'Surat Perintah Kerja',
                'sheet' => 'TPL_SPK_PEMELIHARAAN',
                'scope' => self::SCOPE_TRANSACTION,
                'source' => self::SOURCE_GENERATED,
                'usage' => null,
                'applicable_categories' => ['PEMELIHARAAN'],
                'required' => [
                    'NOMOR_SPK', 'TANGGAL_SPK', 'NAMA_PENERIMA', 'URAIAN_PEKERJAAN', 'LOKASI_PEKERJAAN',
                    'TANGGAL_MULAI', 'TANGGAL_SELESAI', 'NILAI_PEKERJAAN', 'NILAI_PEKERJAAN_TERBILANG',
                    'NAMA_KEPALA_SEKOLAH', 'NIP_KEPALA_SEKOLAH',
                ],
                'optional' => [
                    'NOMOR_RAB', 'TANGGAL_RAB', 'CARA_BAYAR', 'REFERENSI_BAYAR',
                    'NAMA_BENDAHARA_BOSP', 'NIP_BENDAHARA_BOSP',
                ],
                'repeat_required' => [],
                'repeat_optional' => [],
                'image' => ['KOP_SURAT'],
            ],
        ];
    }

    /** @return array<int,string> */
    public static function codes(): array
    {
        return array_keys(self::all());
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        $options = [];
        foreach (self::all() as $code => $definition) {
            $options[$code] = $definition['label'];
        }

        return $options;
    }

    /** @return array<string,mixed>|null */
    public static function definition(string $code): ?array
    {
        return self::all()[strtoupper(trim($code))] ?? null;
    }

    public static function canonical(string $code): ?string
    {
        $normalized = strtoupper(trim($code));
        if (isset(self::all()[$normalized])) {
            return $normalized;
        }

        return [
            'KUITANSI' => self::KUITANSI_A2,
            'CHECKLIST' => self::SPJ_CHECKLIST,
            'INVOICE_PESANAN' => self::INVOICE,
            'SPK' => self::SPK_PEMELIHARAAN,
        ][$normalized] ?? null;
    }

    /** @return array<int,string> */
    public static function placeholdersFor(string $code): array
    {
        $canonical = self::canonical($code);
        $definition = $canonical ? self::definition($canonical) : null;
        if (! $definition) {
            return [];
        }

        return array_values(array_unique(array_merge(
            $definition['required'],
            $definition['optional'],
            $definition['repeat_required'],
            $definition['repeat_optional'],
            $definition['image'],
        )));
    }
}
