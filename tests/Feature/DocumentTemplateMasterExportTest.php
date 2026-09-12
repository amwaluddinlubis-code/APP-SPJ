<?php

namespace Tests\Feature;

use App\Models\DocumentTemplate;
use App\Services\DocumentTemplateMasterExportService;
use App\Services\SpjDocumentTypeRegistry;
use App\Services\SpjTemplatePackageImporter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Tests\TestCase;

class DocumentTemplateMasterExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');
        Storage::fake('local');

        Artisan::call('migrate', [
            '--database' => 'school',
            '--path' => 'database/migrations/school/2026_09_01_000000_create_complete_spj_tenant_tables.php',
            '--force' => true,
        ]);

        DB::connection('school')->table('fund_sources')->insert([
            'id' => 1,
            'code' => 'BOSP',
            'name' => 'BOSP',
            'is_hidden' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $yearId = DB::connection('school')->table('fiscal_years')->insertGetId([
            'year' => 2026,
            'fund_source' => 'BOSP',
            'fund_source_id' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        session(['active_fiscal_year_id' => (int) $yearId]);
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    public function test_master_export_uses_latest_active_xlsx_for_each_canonical_document(): void
    {
        $updatedType = SpjDocumentTypeRegistry::RINCIAN_BELANJA;
        $this->storeCanonicalTemplateSet($updatedType);

        $artifact = app(DocumentTemplateMasterExportService::class)->generate();

        try {
            $this->assertFileExists($artifact['path']);
            $this->assertSame('MASTER-TEMPLATE-SPJ-TERBARU.xlsx', $artifact['download_name']);

            $workbook = IOFactory::load($artifact['path']);
            try {
                $expectedSheets = collect(SpjDocumentTypeRegistry::all())
                    ->pluck('sheet')
                    ->values()
                    ->all();

                $this->assertSame($expectedSheets, $workbook->getSheetNames());
                $this->assertSame(
                    'VERSI-BARU-RINCIAN',
                    $workbook->getSheetByName('TPL_RINCIAN')?->getCell('Z30')->getValue(),
                );
                $this->assertSame(
                    'VERSI-AKTIF-KUITANSI_A2',
                    $workbook->getSheetByName('TPL_KUITANSI')?->getCell('Z30')->getValue(),
                );
            } finally {
                $workbook->disconnectWorksheets();
            }

            $validation = app(SpjTemplatePackageImporter::class)->validatePackage($artifact['path']);
            $this->assertTrue($validation['valid'], json_encode($validation['errors'], JSON_PRETTY_PRINT));
        } finally {
            if (is_file($artifact['path'])) {
                @unlink($artifact['path']);
            }
        }
    }

    public function test_master_export_rejects_partial_active_xlsx_set(): void
    {
        $missingType = SpjDocumentTypeRegistry::BAP;
        $this->storeCanonicalTemplateSet(null, $missingType);

        try {
            app(DocumentTemplateMasterExportService::class)->generate();
            $this->fail('Master parsial seharusnya tidak boleh diunduh sebagai paket canonical.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($missingType, $exception->getMessage());
            $this->assertStringContainsString('belum tersedia', $exception->getMessage());
        }
    }

    private function storeCanonicalTemplateSet(?string $updatedType = null, ?string $skipType = null): void
    {
        $yearId = (int) session('active_fiscal_year_id');

        foreach (SpjDocumentTypeRegistry::all() as $documentType => $definition) {
            if ($documentType === $skipType) {
                continue;
            }

            $sheetName = $documentType === $updatedType
                ? 'Template Update Operator'
                : (string) $definition['sheet'];
            $sentinel = $documentType === $updatedType
                ? 'VERSI-BARU-RINCIAN'
                : 'VERSI-AKTIF-'.$documentType;
            $temporaryPath = $this->makeCanonicalWorkbook($documentType, $sheetName, $sentinel);
            $relativePath = 'document-templates/'.$yearId.'/master-export/'.$documentType.'.xlsx';

            Storage::disk('local')->put($relativePath, file_get_contents($temporaryPath));
            @unlink($temporaryPath);

            DocumentTemplate::query()->create([
                'fiscal_year_id' => $yearId,
                'document_type' => $documentType,
                'name' => (string) $definition['label'],
                'format' => 'xlsx',
                'file_path' => $relativePath,
                'applicable_categories' => $definition['applicable_categories'] ?? [],
                'is_active' => true,
            ]);
        }
    }

    private function makeCanonicalWorkbook(string $documentType, string $sheetName, string $sentinel): string
    {
        $definition = SpjDocumentTypeRegistry::definition($documentType);
        $this->assertIsArray($definition);

        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle($sheetName);
        $scalarMarkers = array_values(array_unique(array_merge(
            $definition['required'],
            $definition['optional'],
            $definition['image'],
        )));
        $repeatMarkers = array_values(array_unique(array_merge(
            $definition['repeat_required'],
            $definition['repeat_optional'],
        )));

        $this->writeMarkers($sheet, $scalarMarkers, 1);
        $this->writeMarkers($sheet, $repeatMarkers, 20);
        $sheet->setCellValue('Z30', $sentinel);

        $path = tempnam(sys_get_temp_dir(), 'spj-master-export-').'.xlsx';
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return $path;
    }

    /** @param array<int,string> $markers */
    private function writeMarkers(Worksheet $sheet, array $markers, int $row): void
    {
        foreach ($markers as $index => $marker) {
            $sheet->setCellValue([$index + 1, $row], '{{'.$marker.'}}');
        }
    }
}
