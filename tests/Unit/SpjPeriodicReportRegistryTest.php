<?php

namespace Tests\Unit;

use App\Services\SpjPeriodicReportRegistry;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpjPeriodicReportRegistryTest extends TestCase
{
    #[Test]
    public function it_exposes_the_four_requested_reporting_scopes(): void
    {
        $registry = app(SpjPeriodicReportRegistry::class);

        $this->assertSame([
            SpjPeriodicReportRegistry::SCOPE_MONTHLY,
            SpjPeriodicReportRegistry::SCOPE_QUARTERLY,
            SpjPeriodicReportRegistry::SCOPE_SEMESTER,
            SpjPeriodicReportRegistry::SCOPE_ANNUAL,
        ], $registry->scopes());

        $this->assertSame([
            'bulan' => 'Laporan Bulanan',
            'triwulan' => 'Laporan Tahap & Triwulan',
            'semester' => 'Laporan Semester',
            'tahunan' => 'Laporan Tahunan',
        ], $registry->scopeLabels());
    }

    #[Test]
    public function it_keeps_the_requested_document_packages_exact(): void
    {
        $registry = app(SpjPeriodicReportRegistry::class);

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
        ], array_column($registry->forScope(SpjPeriodicReportRegistry::SCOPE_MONTHLY), 'label'));

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
        ], array_column($registry->forScope(SpjPeriodicReportRegistry::SCOPE_QUARTERLY), 'label'));

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
        ], array_column($registry->forScope(SpjPeriodicReportRegistry::SCOPE_SEMESTER), 'label'));

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
        ], array_column($registry->forScope(SpjPeriodicReportRegistry::SCOPE_ANNUAL), 'label'));

        $this->assertSame(39, array_sum(array_map('count', $registry->packages())));
    }

    #[Test]
    public function it_defines_valid_period_boundaries_without_binding_templates(): void
    {
        $registry = app(SpjPeriodicReportRegistry::class);

        $this->assertSame(range(1, 12), $registry->validPeriods(SpjPeriodicReportRegistry::SCOPE_MONTHLY));
        $this->assertSame(range(1, 4), $registry->validPeriods(SpjPeriodicReportRegistry::SCOPE_QUARTERLY));
        $this->assertSame(range(1, 2), $registry->validPeriods(SpjPeriodicReportRegistry::SCOPE_SEMESTER));
        $this->assertSame([], $registry->validPeriods(SpjPeriodicReportRegistry::SCOPE_ANNUAL));

        $this->assertTrue($registry->isValidPeriod(SpjPeriodicReportRegistry::SCOPE_MONTHLY, 12));
        $this->assertFalse($registry->isValidPeriod(SpjPeriodicReportRegistry::SCOPE_MONTHLY, 13));
        $this->assertTrue($registry->isValidPeriod(SpjPeriodicReportRegistry::SCOPE_QUARTERLY, 4));
        $this->assertFalse($registry->isValidPeriod(SpjPeriodicReportRegistry::SCOPE_QUARTERLY, 5));
        $this->assertTrue($registry->isValidPeriod(SpjPeriodicReportRegistry::SCOPE_SEMESTER, 2));
        $this->assertFalse($registry->isValidPeriod(SpjPeriodicReportRegistry::SCOPE_SEMESTER, 3));
        $this->assertTrue($registry->isValidPeriod(SpjPeriodicReportRegistry::SCOPE_ANNUAL, null));
        $this->assertFalse($registry->isValidPeriod(SpjPeriodicReportRegistry::SCOPE_ANNUAL, 1));
    }
}
