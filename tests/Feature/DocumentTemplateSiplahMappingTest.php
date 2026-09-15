<?php

namespace Tests\Feature;

use App\Http\Controllers\DocumentTemplateController;
use App\Models\DocumentTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DocumentTemplateSiplahMappingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');

        Artisan::call('migrate', [
            '--database' => 'school',
            '--path' => 'database/migrations/school',
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

        $fiscalYearId = DB::connection('school')->table('fiscal_years')->insertGetId([
            'year' => 2026,
            'fund_source' => 'BOSP',
            'fund_source_id' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        session(['active_fiscal_year_id' => $fiscalYearId]);
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    public function test_mapping_form_persists_all_three_siplah_scope_states(): void
    {
        $template = DocumentTemplate::query()->create([
            'fiscal_year_id' => (int) session('active_fiscal_year_id'),
            'document_type' => 'KUITANSI_A2',
            'name' => 'Kuitansi A2',
            'format' => 'xlsx',
            'file_path' => 'document-templates/kuitansi.xlsx',
            'applicable_categories' => ['BARANG'],
            'is_siplah' => null,
            'is_active' => true,
        ]);

        $this->updateMapping($template, 'siplah');
        $template->refresh();
        $this->assertTrue($template->is_siplah);

        $this->updateMapping($template, 'non_siplah');
        $template->refresh();
        $this->assertFalse($template->is_siplah);

        $this->updateMapping($template, 'all');
        $template->refresh();
        $this->assertNull($template->is_siplah);
        $this->assertSame(['BARANG'], $template->applicable_categories);
        $this->assertTrue($template->is_active);
    }

    public function test_template_settings_view_exposes_siplah_scope_controls(): void
    {
        $source = file_get_contents(resource_path('views/document-templates/index.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('<livewire:document-template-list', $source);
        $component = file_get_contents(resource_path('views/livewire/document-template-list.blade.php'));
        $this->assertIsString($component);
        $this->assertStringContainsString('wire:model="mappingScopes.', $component);
        $this->assertStringContainsString('wire:click="saveMapping(', $component);
        $this->assertStringContainsString('Semua channel', $source);
        $this->assertStringContainsString('SiPlah saja', $source);
        $this->assertStringContainsString('Non-SiPlah saja', $source);
        $this->assertStringContainsString('field <span class="font-mono">is_siplah</span>', $source);
    }

    private function updateMapping(DocumentTemplate $template, string $scope): void
    {
        $request = Request::create(
            '/pengaturan/template-dokumen/'.$template->id.'/mapping',
            'PUT',
            [
                'is_active' => '1',
                'applicable_categories' => ['BARANG'],
                'siplah_scope' => $scope,
            ],
        );
        $request->setLaravelSession(app('session')->driver());

        app()->call([app(DocumentTemplateController::class), 'updateMapping'], [
            'request' => $request,
            'templateId' => (string) $template->id,
        ]);
    }
}
