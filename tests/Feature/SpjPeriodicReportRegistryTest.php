<?php

namespace Tests\Feature;

use App\Services\SpjPeriodicReportRegistry;
use Tests\TestCase;

class SpjPeriodicReportRegistryTest extends TestCase
{
    public function test_registry_contains_requested_report_packages(): void
    {
        $registry = app(SpjPeriodicReportRegistry::class);
        $packages = $registry->packages();

        $this->assertSame(9, count($packages['bulan']));
        $this->assertSame(10, count($packages['triwulan']));
        $this->assertSame(11, count($packages['semester']));
        $this->assertSame(9, count($packages['tahunan']));

        $this->assertSame([
            'SPTJM',
            'Buku Kas Umum',
            'Buku Pembantu Kas',
            'Buku Pembantu Bank',
            'Buku Pembantu Pajak',
            'Register Penutupan Kas (K7B)',
            'Berita Acara Pemeriksaan Kas (K7C)',
            'BOS K7A',
            'Rekap Belanja Modal dan Belanja Barang / Jasa',
        ], array_column($packages['bulan'], 'label'));

        $this->assertSame([
            'SPTJM',
            'BOS K7A',
            'Format K7',
            'Buku Kas Umum (BKU)',
            'SPB',
            'SP2B',
            'Lampiran SP2B',
            'SP2T',
            'Berita Acara Rekonsiliasi',
            'Lampiran Berita Acara Rekonsiliasi',
        ], array_column($packages['triwulan'], 'label'));

        $this->assertSame([
            'SPTJM',
            'BOS K7A',
            'Format K7',
            'Buku Kas Umum (BKU)',
            'SPB',
            'SP2B',
            'Lampiran SP2B',
            'SP2T',
            'Berita Acara Rekonsiliasi',
            'Lampiran Berita Acara Rekonsiliasi',
            'Rekap Belanja Barang Milik Daerah (Aset)',
        ], array_column($packages['semester'], 'label'));

        $this->assertSame([
            'SPTJM',
            'BOS K7A',
            'Format K7',
            'Buku Kas Umum (BKU)',
            'Rekap Barang Milik Daerah (Aset)',
            'BOS K8',
            'Rekap Belanja Dana BOS',
            'Form 1C',
            'Rekapitulasi Pengeluaran Dana BOS',
        ], array_column($packages['tahunan'], 'label'));
    }

    public function test_period_contract_is_explicit_for_each_scope(): void
    {
        $registry = app(SpjPeriodicReportRegistry::class);

        $this->assertTrue($registry->isValidPeriod('bulan', 12));
        $this->assertFalse($registry->isValidPeriod('bulan', 13));
        $this->assertTrue($registry->isValidPeriod('triwulan', 4));
        $this->assertFalse($registry->isValidPeriod('triwulan', 5));
        $this->assertTrue($registry->isValidPeriod('semester', 2));
        $this->assertFalse($registry->isValidPeriod('semester', 3));
        $this->assertTrue($registry->isValidPeriod('tahunan', null));
        $this->assertFalse($registry->isValidPeriod('tahunan', 1));
    }
}
