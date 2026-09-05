<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DocumentTemplateTypeNormalizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');

        Artisan::call('migrate', [
            '--database' => 'school',
            '--path' => 'database/migrations/school/2026_09_01_000000_create_complete_spj_tenant_tables.php',
            '--force' => true,
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    public function test_unambiguous_legacy_aliases_are_normalized_to_canonical_codes(): void
    {
        $yearId = $this->createFiscalYear();
        $this->insertTemplate($yearId, 'KUITANSI', 'xlsx', 'legacy-kuitansi.xlsx');
        $this->insertTemplate($yearId, 'CHECKLIST', 'docx', 'legacy-checklist.docx');
        $this->insertTemplate($yearId, 'INVOICE_PESANAN', 'xlsx', 'legacy-invoice.xlsx');
        $this->insertTemplate($yearId, 'SPK', 'xlsx', 'legacy-spk.xlsx');

        $this->runNormalizationMigration();

        $this->assertSame(1, DB::connection('school')->table('document_templates')->where('document_type', 'KUITANSI_A2')->count());
        $this->assertSame(1, DB::connection('school')->table('document_templates')->where('document_type', 'SPJ_CHECKLIST')->count());
        $this->assertSame(1, DB::connection('school')->table('document_templates')->where('document_type', 'INVOICE')->count());
        $this->assertSame(1, DB::connection('school')->table('document_templates')->where('document_type', 'SPK_PEMELIHARAAN')->count());
    }

    public function test_collision_keeps_canonical_template_and_deactivates_legacy_row(): void
    {
        $yearId = $this->createFiscalYear();
        $canonicalId = $this->insertTemplate($yearId, 'KUITANSI_A2', 'xlsx', 'canonical.xlsx');
        $legacyId = $this->insertTemplate($yearId, 'KUITANSI', 'xlsx', 'legacy.xlsx');

        $this->runNormalizationMigration();

        $canonical = DB::connection('school')->table('document_templates')->where('id', $canonicalId)->first();
        $legacy = DB::connection('school')->table('document_templates')->where('id', $legacyId)->first();

        $this->assertSame('KUITANSI_A2', $canonical->document_type);
        $this->assertTrue((bool) $canonical->is_active);
        $this->assertSame('KUITANSI', $legacy->document_type);
        $this->assertFalse((bool) $legacy->is_active);
        $this->assertSame('legacy.xlsx', $legacy->file_path);
    }

    public function test_unsupported_legacy_types_are_preserved_for_manual_review(): void
    {
        $yearId = $this->createFiscalYear();
        foreach (['DAFTAR_PEMBAYARAN', 'DAFTAR_HADIR', 'SURAT_TUGAS', 'SPPD'] as $type) {
            $this->insertTemplate($yearId, $type, 'xlsx', strtolower($type).'.xlsx');
        }

        $this->runNormalizationMigration();

        $types = DB::connection('school')->table('document_templates')->orderBy('id')->pluck('document_type')->all();

        $this->assertSame(['DAFTAR_PEMBAYARAN', 'DAFTAR_HADIR', 'SURAT_TUGAS', 'SPPD'], $types);
    }

    private function createFiscalYear(): int
    {
        DB::connection('school')->table('fund_sources')->insert([
            'id' => 1,
            'code' => 'BOSP',
            'name' => 'BOSP',
            'is_hidden' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::connection('school')->table('fiscal_years')->insertGetId([
            'year' => 2026,
            'fund_source' => 'BOSP',
            'fund_source_id' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertTemplate(int $yearId, string $type, string $format, string $path): int
    {
        return (int) DB::connection('school')->table('document_templates')->insertGetId([
            'fiscal_year_id' => $yearId,
            'document_type' => $type,
            'name' => $type,
            'format' => $format,
            'file_path' => $path,
            'is_active' => true,
            'applicable_categories' => '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function runNormalizationMigration(): void
    {
        $migration = require base_path('database/migrations/school/2026_09_05_190000_normalize_document_template_types.php');
        $migration->up();
    }
}
