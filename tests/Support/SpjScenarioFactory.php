<?php

namespace Tests\Support;

final class SpjScenarioFactory
{
    /** @var array<int, string> */
    public const CATEGORIES = [
        'BARANG',
        'KONSUMSI',
        'PEMELIHARAAN',
        'JASA_LAINNYA',
        'SPPD',
        'HONOR_PEGAWAI',
    ];

    /**
     * Canonical form payload for one SPJ category.
     *
     * The factory intentionally returns request payloads instead of inserting
     * database records. Tests remain free to create their own transaction and
     * package while sharing the same six-category operator input contract.
     *
     * @return array<string, mixed>
     */
    public static function payload(string $category, float $grossAmount = 1000000): array
    {
        $category = strtoupper(trim($category));
        if (! in_array($category, self::CATEGORIES, true)) {
            throw new \InvalidArgumentException('Kategori SPJ tidak canonical: '.$category);
        }

        $base = [
            'spj_category' => $category,
            'payment_description' => 'Pembayaran skenario verifikasi '.$category,
            'payment_method' => 'tunai',
            'payment_reference' => null,
            'receipt_recipient_name' => 'Penerima Utama Uji',
        ];

        return [...$base, ...match ($category) {
            'BARANG' => self::barang(),
            'KONSUMSI' => self::konsumsi(),
            'PEMELIHARAAN' => self::pemeliharaan($grossAmount),
            'JASA_LAINNYA' => self::jasaLainnya($grossAmount),
            'SPPD' => self::sppd($grossAmount),
            'HONOR_PEGAWAI' => self::honorPegawai($grossAmount),
        }];
    }

    /** @return array<string, mixed> */
    private static function barang(): array
    {
        return [
            'vendor_name' => 'Penyedia Skenario',
            'vendor_owner' => 'Pemilik Penyedia',
            'vendor_npwp' => '00.000.000.0-000.000',
            'order_date' => '2026-01-10',
            'bap_date' => '2026-01-11',
            'bast_date' => '2026-01-12',
        ];
    }

    /** @return array<string, mixed> */
    private static function konsumsi(): array
    {
        return [
            'event_name' => 'Kegiatan Skenario',
            'event_location' => 'Sekolah',
            'event_date' => '2026-01-10',
            'participant_count' => 2,
            'primary_recipient_group' => 'participants',
            'primary_recipient_index' => 0,
            'participants' => [
                ['name' => 'Peserta Utama', 'position' => 'Guru', 'portions' => 1],
                ['name' => 'Peserta Kedua', 'position' => 'Tenaga Kependidikan', 'portions' => 1],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function pemeliharaan(float $grossAmount): array
    {
        return [
            'work_description' => 'Pemeliharaan sarana sekolah',
            'work_location' => 'Sekolah',
            'work_started_at' => '2026-01-10',
            'work_completed_at' => '2026-01-11',
            'spk_date' => '2026-01-10',
            'rab_date' => '2026-01-10',
            'primary_recipient_group' => 'workers',
            'primary_recipient_index' => 0,
            'workers' => [
                [
                    'name' => 'Pekerja Utama',
                    'job_description' => 'Pelaksana pemeliharaan',
                    'work_days' => 1,
                    'daily_rate' => $grossAmount,
                    'is_receipt_recipient' => true,
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function jasaLainnya(float $grossAmount): array
    {
        return [
            'primary_recipient_group' => 'service_recipients',
            'primary_recipient_index' => 0,
            'service_recipients' => [
                [
                    'name' => 'Penerima Jasa Utama',
                    'service_type' => 'Jasa sewa harian',
                    'service_description' => 'Jasa skenario verifikasi',
                    'quantity' => 1,
                    'unit' => 'unit',
                    'rental_days' => 1,
                    'daily_rate' => $grossAmount,
                    'usage_started_at' => '2026-01-10',
                    'usage_completed_at' => '2026-01-10',
                    'is_receipt_recipient' => true,
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function sppd(float $grossAmount): array
    {
        return [
            'primary_recipient_group' => 'travels',
            'primary_recipient_index' => 0,
            'travels' => [
                [
                    'traveler_name' => 'Pelaksana SPPD Utama',
                    'destination' => 'Lokasi Tujuan',
                    'purpose' => 'Kegiatan dinas skenario verifikasi',
                    'assignment_letter_date' => '2026-01-10',
                    'departure_date' => '2026-01-10',
                    'return_date' => '2026-01-10',
                    'transport_mode' => 'Darat',
                    'amount' => $grossAmount,
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function honorPegawai(float $grossAmount): array
    {
        return [
            'primary_recipient_group' => 'workers',
            'primary_recipient_index' => 0,
            'workers' => [
                [
                    'name' => 'Penerima Honor Utama',
                    'job_description' => 'Petugas kegiatan',
                    'work_days' => 1,
                    'daily_rate' => $grossAmount,
                    'is_receipt_recipient' => true,
                ],
            ],
        ];
    }
}
