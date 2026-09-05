<?php

namespace Tests\Unit;

use App\Services\SpjDocumentTypeRegistry;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpjMaintenanceRabContractTest extends TestCase
{
    #[Test]
    public function maintenance_rab_keeps_identity_extras_optional_without_weakening_core_values(): void
    {
        $definition = SpjDocumentTypeRegistry::definition(SpjDocumentTypeRegistry::RAB_PEMELIHARAAN);

        $this->assertNotNull($definition);

        foreach (['TANGGAL_RAB', 'URAIAN_PEKERJAAN', 'NILAI_PEKERJAAN', 'NAMA_KEPALA_SEKOLAH', 'NIP_KEPALA_SEKOLAH'] as $marker) {
            $this->assertContains($marker, $definition['required']);
        }

        foreach (['NOMOR_RAB', 'LOKASI_PEKERJAAN', 'NILAI_PEKERJAAN_TERBILANG'] as $marker) {
            $this->assertNotContains($marker, $definition['required']);
            $this->assertContains($marker, $definition['optional']);
        }

        $this->assertContains('ITEM_NO', $definition['repeat_required']);
        $this->assertContains('UPAH_NO', $definition['repeat_required']);
    }
}
