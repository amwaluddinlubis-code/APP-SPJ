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

    public const DAFTAR_HADIR_KONSUMSI = 'DAFTAR_HADIR_KONSUMSI';

    public const DAFTAR_PENERIMA_KONSUMSI = 'DAFTAR_PENERIMA_KONSUMSI';

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
            ['NAMA_PENGURUS_BARANG', 'NIP_PENGURUS_BARANG'],
        ];
    }

    /** @return array<int,string> */
    public static function technicalSheets(): array
    {
        return [
            'PLACEHOLDER_MAP',
            'TEMPLATE_REGISTER',
            'REGULASI_REFERENSI',
            'BACKEND_TEMPLATE_RULES',
            'TPL_BUKTI_KAS_PENGELUARAN_A2',
            'TPL_SURAT_HASIL_PEMERIKSAAN',
            'TPL_SURAT_BALASAN_PENYEDIA',
            'TPL_DOKUMEN_PERENCANAAN',
            'TPL_DAFTAR_PENERIMAAN_HONOR',
            'TPL_DAFTAR_PENERIMA_JASA',
            'TPL_SURAT_TUGAS_SPPD',
            'TPL_SPPD',
            'TPL_RINCIAN_BIAYA_PERJALANAN',
            'TPL_LAPORAN_PERJALANAN_DINAS',
            'TPL_BA_PEMERIKSAAN_PEKERJAAN',
            'TPL_BA_SERAH_TERIMA_PEKERJAAN',
            'TPL_DAFTAR_PENGELUARAN_RIIL',
        ];
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
                    'URAIAN_TRANSAKSI', 'NILAI_BRUTO', 'NILAI_DIBAYARKAN',
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
                'optional' => ['KODE_KEGIATAN', 'NAMA_KEGIATAN', 'KODE_REKENING'],
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
                    'KODE_REKENING', 'URAIAN_TRANSAKSI', 'NAMA_PENYEDIA', 'NPWP_PENYEDIA',
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
                    'KODE_REKENING', 'NPWP_PENYEDIA', 'TELEPON_PENYEDIA',
                    'CARA_BAYAR', 'REFERENSI_BAYAR', 'CARA_BAYAR_REFERENSI',
                ],
                'repeat_required' => ['ITEM_NO', 'ITEM_URAIAN', 'ITEM_VOLUME', 'ITEM_SATUAN', 'ITEM_HARGA_SATUAN', 'ITEM_JUMLAH'],
                'repeat_optional' => [],
                'image' => ['KOP_SURAT'],
            ],

            self::BAP => [
                'label' => 'Berita Acara Pemeriksaan/Penerimaan',
                'sheet' => 'TPL_BA_PEMERIKSAAN_PENERIMAAN',
                'scope' => self::SCOPE_TRANSACTION,
                'source' => self::SOURCE_GENERATED,
                'usage' => null,
                'applicable_categories' => ['BARANG', 'KONSUMSI'],
                'required' => [
                    'NOMOR_DOKUMEN', 'TANGGAL_DOKUMEN', 'NOMOR_PESANAN', 'TANGGAL_PESANAN',
                    'NAMA_PENYEDIA', 'TANGGAL_PENYERAHAN', 'TEMPAT_PENYERAHAN',
                    'NAMA_KEPALA_SEKOLAH', 'NIP_KEPALA_SEKOLAH',
                    'NAMA_PENGURUS_BARANG', 'NIP_PENGURUS_BARANG',
                ],
                'optional' => [
                    'KODE_KEGIATAN', 'NAMA_KEGIATAN', 'KODE_REKENING', 'NPWP_PENYEDIA',
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
                    'TANGGAL_RAB', 'URAIAN_PEKERJAAN', 'NILAI_PEKERJAAN', 'JENIS_RAB', 'TOTAL_RAB',
                    'NAMA_KEPALA_SEKOLAH', 'NIP_KEPALA_SEKOLAH',
                ],
                'optional' => [
                    'NOMOR_RAB', 'LOKASI_PEKERJAAN', 'NILAI_PEKERJAAN_TERBILANG',
                    'NAMA_BENDAHARA_BOSP', 'NIP_BENDAHARA_BOSP', 'KODE_KEGIATAN', 'NAMA_KEGIATAN',
                ],
                'repeat_required' => [
                    'ITEM_NO', 'ITEM_URAIAN', 'ITEM_VOLUME', 'ITEM_SATUAN', 'ITEM_HARGA_SATUAN', 'ITEM_JUMLAH',
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

            self::DAFTAR_HADIR_KONSUMSI => [
                'label' => 'Daftar Hadir Konsumsi',
                'sheet' => 'TPL_DAFTAR_HADIR_KONSUMSI',
                'scope' => self::SCOPE_TRANSACTION,
                'source' => self::SOURCE_GENERATED,
                'usage' => null,
                'applicable_categories' => ['KONSUMSI'],
                'required' => [
                    'KODE_KEGIATAN', 'NAMA_ACARA', 'TANGGAL_ACARA', 'TEMPAT_ACARA',
                    'KONSUMSI_NO', 'KONSUMSI_NAMA',
                ],
                'optional' => [
                    'KONSUMSI_IDENTITAS', 'NAMA_PENANGGUNG_JAWAB', 'NIP_PENANGGUNG_JAWAB',
                    'NAMA_KEPALA_SEKOLAH', 'NIP_KEPALA_SEKOLAH',
                ],
                'repeat_required' => [],
                'repeat_optional' => [],
                'image' => ['KOP_SURAT'],
            ],

            self::DAFTAR_PENERIMA_KONSUMSI => [
                'label' => 'Daftar Penerima Konsumsi',
                'sheet' => 'TPL_DAFTAR_PENERIMA_KONSUMSI',
                'scope' => self::SCOPE_TRANSACTION,
                'source' => self::SOURCE_GENERATED,
                'usage' => null,
                'applicable_categories' => ['KONSUMSI'],
                'required' => [
                    'TANGGAL_KEGIATAN', 'TEMPAT_KEGIATAN',
                    'KONSUMSI_NO', 'KONSUMSI_NAMA', 'KONSUMSI_PORSI', 'TOTAL_KONSUMSI',
                ],
                'optional' => [
                    'KONSUMSI_IDENTITAS', 'NAMA_PENANGGUNG_JAWAB', 'NIP_PENANGGUNG_JAWAB',
                    'KONSUMSI_HARGA_PORSI', 'KONSUMSI_JUMLAH',
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

    /**
     * Alias resolver <-> registry. Registry memakai satu sisi alias
     * (mis. NOMOR_DOKUMEN) sedangkan resolver menyediakan kedua sisi.
     * Union kategori dihitung lintas alias agar scope tidak salah khusus.
     *
     * @return array<int,string>
     */
    public static function placeholderAliases(string $marker): array
    {
        $marker = strtoupper(trim($marker));

        $groups = [
            ['NOMOR_SPJ', 'NOMOR_DOKUMEN'],
            ['NO_BUKTI', 'NOMOR_BUKTI'],
            ['TANGGAL_TRANSAKSI', 'TANGGAL_DOKUMEN'],
            ['NAMA_SEKOLAH', 'NAMA_SATUAN_PENDIDIKAN'],
            ['TOTAL_PAJAK', 'POTONGAN_PAJAK'],
            ['NILAI_BRUTO', 'NILAI_PEKERJAAN'],
        ];

        foreach ($groups as $group) {
            if (in_array($marker, $group, true)) {
                return $group;
            }
        }

        return [$marker];
    }

    /**
     * Kategori SPJ yang memakai suatu placeholder, dihitung dari union
     * applicable_categories pada definisi dokumen yang memuat marker tersebut.
     * '*' di-expand ke seluruh kategori canonical. Marker yang tidak terdaftar
     * pada registry (mis. KONSUMSI_xx extended) mengembalikan array kosong agar
     * caller dapat memakai fallback group-scope.
     *
     * @return array<int,string>
     */
    public static function placeholderApplicableCategories(string $marker): array
    {
        $variants = self::placeholderAliases($marker);
        if ($variants === []) {
            return [];
        }

        $allCategories = self::categories();
        $union = [];

        foreach (self::all() as $definition) {
            $markers = array_map(
                fn ($value): string => strtoupper(trim((string) $value)),
                array_merge(
                    $definition['required'],
                    $definition['optional'],
                    $definition['repeat_required'],
                    $definition['repeat_optional'],
                    $definition['image'],
                )
            );

            if (array_intersect($variants, $markers) === []) {
                continue;
            }

            $applicable = $definition['applicable_categories'];
            if (in_array('*', $applicable, true)) {
                $applicable = $allCategories;
            }

            foreach ($applicable as $category) {
                $union[$category] = true;
            }
        }

        return array_values(array_intersect($allCategories, array_keys($union)));
    }

    /**
     * Klasifikasi Umum / Transaksional / Khusus per marker.
     *
     * - transaksional: repeating row ITEM_xx / UPAH_xx dan ringkasan RINCIAN_xx.
     * - umum: union kategori mencakup seluruh kategori canonical.
     * - khusus: sisanya (termasuk marker extended yang tidak ada di registry).
     */
    public static function placeholderScope(string $marker): string
    {
        $marker = strtoupper(trim($marker));

        if (str_starts_with($marker, 'ITEM_') || str_starts_with($marker, 'UPAH_')) {
            return 'transaksional';
        }

        if (in_array($marker, ['RINCIAN_BELANJA', 'RINCIAN_UPAH', 'RINCIAN_JASA'], true)) {
            return 'transaksional';
        }

        $applicable = self::placeholderApplicableCategories($marker);

        if ($applicable === []) {
            return 'khusus';
        }

        return count($applicable) >= count(self::categories()) ? 'umum' : 'khusus';
    }
}
