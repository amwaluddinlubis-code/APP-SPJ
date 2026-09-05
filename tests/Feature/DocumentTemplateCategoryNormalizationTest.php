<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DocumentTemplateCategoryNormalizationTest extends TestCase
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

    public function test_known_legacy_categories_are_normalized_and_deduplicated(): void
    {
        $templateId = $this->insertTemplate([
            'BELANJA_MODAL',
            'BARANG',
            'PERJALANAN_DINAS',
            'JASA_HONORARIUM',
            'UPAH',
            'JASA',
            'LAINNYA',
        ]);

        $this->runNormalizationMigration();

        $categories = $this->categoriesFor($templateId);

        $this->assertSame([
            'BARANG',
            'SPPD',
            'HONOR_PEGAWAI',
            'PEMELIHARAAN',
            'JASA_LAINNYA',
        ], $categories);
    }

    public function test_semua_is_normalized_to_empty_mapping_for_all_categories(): void
    {
        $templateId = $this->insertTemplate(['SEMUA', 'BARANG']);

        $this->runNormalizationMigration();

        $this->assertSame([], $this->categoriesFor($templateId));
    }

    public function test_unknown_categories_are_preserved_for_manual_review(): void
    {
        $templateId = $this->insertTemplate(['BARANG', 'KATEGORI_LAMA_TIDAK_DIKENAL']);

        $this->runNormalizationMigration();

        $this->assertSame(
            ['BARANG', 'KATEGORI_LAMA_TIDAK_DIKENAL'],
            $this->categoriesFor($templateId)
        );
    }

    private function insertTemplate(array $categories): int
    {
        DB::connection('school')->table('fund_sources')->insert([
            'id' => 1,
            'code' => 'BOSP',
            'name' => 'BOSP',
            'is_hidden' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $yearId = (int) DB::connection('school')->table('fiscal_years')->insertGetId([
            'year' => 2026,
            'fund_source' => 'BOSP',
            'fund_source_id' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::connection('school')->table('document_templates')->insertGetId([
            'fiscal_year_id' => $yearId,
            'document_type' => 'KUITANSI_A2',
            'name' => 'Template Test',
            'format' => 'xlsx',
            'file_path' => 'template-test.xlsx',
            'is_active' => true,
            'applicable_categories' => json_encode($categories, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function categoriesFor(int $templateId): array
    {
        $value = DB::connection('school')->table('document_templates')
            ->where('id', $templateId)
            ->value('applicable_categories');

        return json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
    }

    private function runNormalizationMigration(): void
    {
        $migration = require base_path('database/migrations/school/2026_09_05_200000_normalize_document_template_categories.php');
        $migration->up();
    }
}
