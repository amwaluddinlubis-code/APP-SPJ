<?php

namespace Tests\Feature;

use App\Http\Controllers\DocumentTemplateController;
use App\Models\DocumentTemplate;
use App\Services\SpjTemplateValidator;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class DocumentTemplateUploadValidationTest extends TestCase
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

    public function test_invalid_upload_does_not_replace_existing_template(): void
    {
        Storage::put('document-templates/1/existing.xlsx', 'existing-template');
        $existing = DocumentTemplate::query()->create([
            'fiscal_year_id' => (int) session('active_fiscal_year_id'),
            'document_type' => 'RINCIAN_BELANJA',
            'name' => 'Template Lama',
            'format' => 'xlsx',
            'file_path' => 'document-templates/1/existing.xlsx',
            'applicable_categories' => [],
            'is_active' => true,
        ]);

        $path = $this->makeWorkbook('TPL_RINCIAN', ['{{NOMOR_DOKUMEN}}']);
        $request = Request::create('/pengaturan/template-dokumen', 'POST', [
            'document_type' => 'RINCIAN_BELANJA',
            'name' => 'Template Rusak',
        ], [], [
            'template' => new UploadedFile($path, 'template.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ]);
        $request->setLaravelSession(app('session')->driver());

        try {
            app(DocumentTemplateController::class)->store($request, app(SpjTemplateValidator::class));
            $this->fail('Upload tanpa placeholder wajib seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('template', $exception->errors());
        }

        $existing->refresh();
        $this->assertSame('Template Lama', $existing->name);
        $this->assertSame('document-templates/1/existing.xlsx', $existing->file_path);
        Storage::assertExists('document-templates/1/existing.xlsx');
        $this->assertSame(1, DocumentTemplate::query()->count());
    }

    public function test_valid_upload_is_saved_even_when_sheet_name_only_produces_warning(): void
    {
        $path = $this->makeWorkbook('Rincian Belanja', [
            '{{NOMOR_DOKUMEN}}',
            '{{NOMOR_BUKTI}}',
            '{{NILAI_BRUTO}}',
            '{{ITEM_NO}}',
            '{{ITEM_URAIAN}}',
            '{{ITEM_VOLUME}}',
            '{{ITEM_SATUAN}}',
            '{{ITEM_HARGA_SATUAN}}',
            '{{ITEM_JUMLAH}}',
        ], repeatRow: 6);
        $request = Request::create('/pengaturan/template-dokumen', 'POST', [
            'document_type' => 'RINCIAN_BELANJA',
            'name' => 'Template Valid',
            'applicable_categories' => ['BARANG'],
        ], [], [
            'template' => new UploadedFile($path, 'template.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ]);
        $request->setLaravelSession(app('session')->driver());

        $response = app(DocumentTemplateController::class)->store($request, app(SpjTemplateValidator::class));

        $this->assertTrue($response->getSession()->has('template_validation_warnings'));
        $template = DocumentTemplate::query()->sole();
        $this->assertSame('RINCIAN_BELANJA', $template->document_type);
        $this->assertSame(['BARANG'], $template->applicable_categories);
        Storage::assertExists($template->file_path);
    }

    /**
     * @param  array<int,string>  $markers
     */
    private function makeWorkbook(string $sheetName, array $markers, ?int $repeatRow = null): string
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle($sheetName);
        $column = 1;
        $row = 1;

        foreach ($markers as $marker) {
            if ($repeatRow !== null && str_starts_with($marker, '{{ITEM_')) {
                $row = $repeatRow;
            } elseif ($repeatRow !== null && $row === $repeatRow) {
                $row = 1;
                $column = 1;
            }

            $sheet->setCellValue([$column, $row], $marker);
            $column++;
            if ($column > 10) {
                $column = 1;
                $row++;
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'spj-template-').'.xlsx';
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return $path;
    }
}
