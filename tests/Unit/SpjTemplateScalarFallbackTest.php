<?php

namespace Tests\Unit;

use App\Services\ArkasActivityHierarchyResolver;
use App\Services\SpjDocumentTypeRegistry;
use App\Services\SpjTemplateService;
use ReflectionMethod;
use Tests\TestCase;

class SpjTemplateScalarFallbackTest extends TestCase
{
    public function test_activity_hierarchy_placeholders_are_exposed_in_runtime_catalog(): void
    {
        $groups = SpjTemplateService::placeholderGroups();

        $this->assertContains('KODE_PROGRAM', $groups['Transaksi & pembayaran']);
        $this->assertContains('NAMA_PROGRAM', $groups['Transaksi & pembayaran']);
        $this->assertContains('KODE_SUB_PROGRAM', $groups['Transaksi & pembayaran']);
        $this->assertContains('NAMA_SUB_PROGRAM', $groups['Transaksi & pembayaran']);
        $this->assertContains('KODE_KEGIATAN', $groups['Transaksi & pembayaran']);
        $this->assertContains('NAMA_KEGIATAN', $groups['Transaksi & pembayaran']);
    }

    public function test_empty_scalar_values_render_as_dash_while_image_marker_stays_empty(): void
    {
        $service = new SpjTemplateService(new ArkasActivityHierarchyResolver);
        $method = new ReflectionMethod($service, 'normalizeScalarPlaceholders');

        $result = $method->invoke($service, [
            'ALAMAT_PENYEDIA' => '',
            'TELEPON_PENYEDIA' => '   ',
            'NIP_KEPALA_SEKOLAH' => null,
            'REFERENSI_BAYAR' => '',
            'NAMA_SEKOLAH' => 'SD Negeri Contoh',
            'KOP_SURAT' => '',
        ]);

        $this->assertSame(SpjDocumentTypeRegistry::EMPTY_SCALAR_VALUE, $result['ALAMAT_PENYEDIA']);
        $this->assertSame(SpjDocumentTypeRegistry::EMPTY_SCALAR_VALUE, $result['TELEPON_PENYEDIA']);
        $this->assertSame(SpjDocumentTypeRegistry::EMPTY_SCALAR_VALUE, $result['NIP_KEPALA_SEKOLAH']);
        $this->assertSame(SpjDocumentTypeRegistry::EMPTY_SCALAR_VALUE, $result['REFERENSI_BAYAR']);
        $this->assertSame('SD Negeri Contoh', $result['NAMA_SEKOLAH']);
        $this->assertSame('', $result['KOP_SURAT']);
    }
}
