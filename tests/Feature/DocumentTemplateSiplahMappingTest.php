<?php

namespace Tests\Feature;

use App\Http\Controllers\DocumentTemplateController;
use App\Livewire\DocumentTemplateList;
use App\Models\DocumentTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class DocumentTemplateSiplahMappingTest extends TestCase
{
    use RefreshDatabase;

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
        $template = $this->createTemplate();

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
        $this->assertStringContainsString('wire:click="reloadList"', $component);
        $this->assertStringContainsString('template sesuai filter daftar saat ini', $component);
        $this->assertStringContainsString('Semua channel', $source);
        $this->assertStringContainsString('SiPlah saja', $source);
        $this->assertStringContainsString('Non-SiPlah saja', $source);
        $this->assertStringContainsString('field <span class="font-mono">is_siplah</span>', $source);
    }

    public function test_reactive_mapping_requires_administrator(): void
    {
        $template = $this->createTemplate();

        foreach ([User::ROLE_OPERATOR, User::ROLE_VIEWER] as $role) {
            $actor = User::factory()->create(['role' => $role]);

            Livewire::actingAs($actor)
                ->test(DocumentTemplateList::class)
                ->call('saveMapping', (string) $template->id)
                ->assertStatus(403);
        }

        $template->refresh();
        $this->assertNull($template->is_siplah);
        $this->assertSame(['BARANG'], $template->applicable_categories);
        $this->assertTrue($template->is_active);
    }

    public function test_administrator_can_save_reactive_mapping(): void
    {
        $template = $this->createTemplate();
        $administrator = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $templateId = (string) $template->id;

        Livewire::actingAs($administrator)
            ->test(DocumentTemplateList::class)
            ->set('mappingCategories.'.$templateId, ['SPPD'])
            ->set('mappingScopes.'.$templateId, 'siplah')
            ->set('mappingActive.'.$templateId, false)
            ->call('saveMapping', $templateId)
            ->assertHasNoErrors();

        $template->refresh();
        $this->assertSame(['SPPD'], $template->applicable_categories);
        $this->assertTrue($template->is_siplah);
        $this->assertFalse($template->is_active);
    }

    public function test_reload_list_rehydrates_mapping_state_from_database(): void
    {
        $template = $this->createTemplate();
        $administrator = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $templateId = (string) $template->id;

        $component = Livewire::actingAs($administrator)
            ->test(DocumentTemplateList::class)
            ->assertSet('mappingCategories.'.$templateId, ['BARANG'])
            ->assertSet('mappingScopes.'.$templateId, 'all')
            ->assertSet('mappingActive.'.$templateId, true);

        $template->update([
            'applicable_categories' => ['SPPD'],
            'is_siplah' => false,
            'is_active' => false,
        ]);

        $component
            ->call('reloadList')
            ->assertSet('mappingCategories.'.$templateId, ['SPPD'])
            ->assertSet('mappingScopes.'.$templateId, 'non_siplah')
            ->assertSet('mappingActive.'.$templateId, false);
    }

    public function test_reactive_filter_updates_visible_templates(): void
    {
        $this->createTemplate(['name' => 'Template Barang']);
        $this->createTemplate([
            'document_type' => 'SPJ_SPPD',
            'name' => 'Template SPPD',
            'applicable_categories' => ['SPPD'],
        ]);
        $administrator = User::factory()->create(['role' => User::ROLE_ADMIN]);

        Livewire::actingAs($administrator)
            ->test(DocumentTemplateList::class)
            ->assertSee('Template Barang')
            ->assertSee('Template SPPD')
            ->set('category', 'BARANG')
            ->assertSee('Template Barang')
            ->assertDontSee('Template SPPD');
    }

    /** @param array<string,mixed> $overrides */
    private function createTemplate(array $overrides = []): DocumentTemplate
    {
        return DocumentTemplate::query()->create(array_merge([
            'fiscal_year_id' => (int) session('active_fiscal_year_id'),
            'document_type' => 'KUITANSI_A2',
            'name' => 'Kuitansi A2',
            'format' => 'xlsx',
            'file_path' => 'document-templates/kuitansi.xlsx',
            'applicable_categories' => ['BARANG'],
            'is_siplah' => null,
            'is_active' => true,
        ], $overrides));
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
