<?php

namespace Tests\Unit;

use App\Services\SpjDocumentTypeRegistry;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpjDocumentTypeRegistryTest extends TestCase
{
    #[Test]
    public function it_exposes_the_canonical_document_types_from_the_workbook(): void
    {
        $this->assertSame([
            'SPJ_COVER',
            'SPJ_CHECKLIST',
            'KUITANSI_A2',
            'RINCIAN_BELANJA',
            'REKAP_PAJAK',
            'SURAT_PESANAN',
            'BAP',
            'BAST',
            'INVOICE',
            'RAB_PEMELIHARAAN',
            'SPK_PEMELIHARAAN',
        ], SpjDocumentTypeRegistry::codes());

        $this->assertNotContains('PLACEHOLDER_MAP', SpjDocumentTypeRegistry::codes());
        $this->assertContains('PLACEHOLDER_MAP', SpjDocumentTypeRegistry::technicalSheets());
    }

    #[Test]
    public function it_keeps_invoice_as_external_administrative_reprint(): void
    {
        $invoice = SpjDocumentTypeRegistry::definition(SpjDocumentTypeRegistry::INVOICE);

        $this->assertNotNull($invoice);
        $this->assertSame('TPL_INVOICE', $invoice['sheet']);
        $this->assertSame(SpjDocumentTypeRegistry::SOURCE_EXTERNAL, $invoice['source']);
        $this->assertSame(SpjDocumentTypeRegistry::USAGE_ADMIN_REPRINT, $invoice['usage']);
    }

    #[Test]
    public function it_defines_official_signer_name_and_nip_as_pairs(): void
    {
        $this->assertSame([
            ['NAMA_KEPALA_SEKOLAH', 'NIP_KEPALA_SEKOLAH'],
            ['NAMA_BENDAHARA_BOSP', 'NIP_BENDAHARA_BOSP'],
        ], SpjDocumentTypeRegistry::pairedPlaceholders());
    }

    #[Test]
    public function cover_requires_the_composed_funding_period_without_requiring_duplicate_components(): void
    {
        $cover = SpjDocumentTypeRegistry::definition(SpjDocumentTypeRegistry::SPJ_COVER);

        $this->assertNotNull($cover);
        $this->assertContains('SUMBER_DANA_PERIODE', $cover['required']);
        $this->assertNotContains('TAHUN_ANGGARAN', $cover['required']);
        $this->assertNotContains('SUMBER_DANA', $cover['required']);
        $this->assertContains('TAHUN_ANGGARAN', $cover['optional']);
        $this->assertContains('SUMBER_DANA', $cover['optional']);
    }

    #[Test]
    public function bast_does_not_require_item_unit_when_the_workbook_has_no_unit_column(): void
    {
        $bast = SpjDocumentTypeRegistry::definition(SpjDocumentTypeRegistry::BAST);

        $this->assertNotNull($bast);
        $this->assertContains('ITEM_NO', $bast['repeat_required']);
        $this->assertContains('ITEM_URAIAN', $bast['repeat_required']);
        $this->assertContains('ITEM_VOLUME', $bast['repeat_required']);
        $this->assertNotContains('ITEM_SATUAN', $bast['repeat_required']);
        $this->assertContains('ITEM_SATUAN', $bast['repeat_optional']);
    }

    #[Test]
    public function every_registered_placeholder_has_one_contract_role_per_document(): void
    {
        foreach (SpjDocumentTypeRegistry::all() as $code => $definition) {
            $buckets = [
                $definition['required'],
                $definition['optional'],
                $definition['repeat_required'],
                $definition['repeat_optional'],
                $definition['image'],
            ];

            $flattened = array_merge(...$buckets);
            $this->assertSame(
                count($flattened),
                count(array_unique($flattened)),
                "Placeholder contract contains duplicates for {$code}."
            );
        }
    }

    #[Test]
    public function it_keeps_legacy_aliases_explicit_without_registering_them_as_canonical_types(): void
    {
        $this->assertSame(SpjDocumentTypeRegistry::KUITANSI_A2, SpjDocumentTypeRegistry::canonical('KUITANSI'));
        $this->assertSame(SpjDocumentTypeRegistry::SPJ_CHECKLIST, SpjDocumentTypeRegistry::canonical('CHECKLIST'));
        $this->assertSame(SpjDocumentTypeRegistry::INVOICE, SpjDocumentTypeRegistry::canonical('INVOICE_PESANAN'));
        $this->assertSame(SpjDocumentTypeRegistry::SPK_PEMELIHARAAN, SpjDocumentTypeRegistry::canonical('SPK'));
        $this->assertNull(SpjDocumentTypeRegistry::canonical('UNKNOWN_DOCUMENT'));
    }

    #[Test]
    public function empty_scalar_rendering_contract_uses_a_dash(): void
    {
        $this->assertSame('-', SpjDocumentTypeRegistry::EMPTY_SCALAR_VALUE);
    }
}
