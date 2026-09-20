<?php

namespace Tests\Feature;

use App\Livewire\RkasBudgetFilter;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\User;
use App\Services\ArkasPersistentReferenceAuthority;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class RkasBudgetFilterTest extends TestCase
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

        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        FiscalYear::query()->create([
            'id' => 1,
            'year' => 2026,
            'fund_source' => 'BOSP',
            'fund_source_id' => 1,
        ]);

        $this->actingAs(User::factory()->create());
        $this->withoutMiddleware()->withSession([
            'active_school_id' => 1,
            'active_fiscal_year_id' => 1,
            'active_fund_source_id' => 1,
        ]);

        (new ArkasPersistentReferenceAuthority)->promoteCodeVariants('1', [
            ['id_kode' => '03', 'parent_kode' => null, 'uraian_kode' => 'Program Uji', 'id_level_kode' => '1', 'tipe' => 'P', 'id_ref_kode' => 'REF-03', 'tahun' => '2026', 'sumber_dana_id' => '1', 'bentuk_pendidikan_id' => '6'],
            ['id_kode' => '03.03', 'parent_kode' => '03', 'uraian_kode' => 'Sub Program Uji', 'id_level_kode' => '2', 'tipe' => 'S', 'id_ref_kode' => 'REF-033', 'tahun' => '2026', 'sumber_dana_id' => '1', 'bentuk_pendidikan_id' => '6'],
            ['id_kode' => '03.03.07', 'parent_kode' => '03.03', 'uraian_kode' => 'Kegiatan Uji', 'id_level_kode' => '3', 'tipe' => 'K', 'id_ref_kode' => 'REF-03307', 'tahun' => '2026', 'sumber_dana_id' => '1', 'bentuk_pendidikan_id' => '6'],
            ['id_kode' => '05', 'parent_kode' => null, 'uraian_kode' => 'Program Buku', 'id_level_kode' => '1', 'tipe' => 'P', 'id_ref_kode' => 'REF-05', 'tahun' => '2026', 'sumber_dana_id' => '1', 'bentuk_pendidikan_id' => '6'],
            ['id_kode' => '05.02', 'parent_kode' => '05', 'uraian_kode' => 'Sub Program Buku', 'id_level_kode' => '2', 'tipe' => 'S', 'id_ref_kode' => 'REF-052', 'tahun' => '2026', 'sumber_dana_id' => '1', 'bentuk_pendidikan_id' => '6'],
            ['id_kode' => '05.02.03', 'parent_kode' => '05.02', 'uraian_kode' => 'Kegiatan Buku', 'id_level_kode' => '3', 'tipe' => 'K', 'id_ref_kode' => 'REF-05203', 'tahun' => '2026', 'sumber_dana_id' => '1', 'bentuk_pendidikan_id' => '6'],
            ['id_kode' => '09.01.01', 'parent_kode' => '09.01', 'uraian_kode' => 'Kegiatan Referensi Lain', 'id_level_kode' => '3', 'tipe' => 'K', 'id_ref_kode' => 'REF-090101', 'tahun' => '2026', 'sumber_dana_id' => '1', 'bentuk_pendidikan_id' => '6'],
        ], '2026.09');

        DB::connection('school')->table('arkas_rkas_items')->insert([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'source_rapbs_id' => 'RAPBS-1',
            'activity_code' => '03.03.07',
            'activity_name' => 'Kegiatan Uji',
            'account_code' => '5.1.02.01',
            'description' => 'Belanja uji',
            'amount' => 100_000_000,
            'payload' => json_encode([
                'TW_1' => 25_000_000,
                'TW_2' => 25_000_000,
                'TW_3' => 25_000_000,
                'TW_4' => 25_000_000,
                'VOLUME_TOTAL' => 4,
                'SATUAN' => 'paket',
                'HARGA_SATUAN' => 25_000_000,
            ]),
        ]);
        DB::connection('school')->table('arkas_rkas_items')->insert([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'source_rapbs_id' => 'RAPBS-2',
            'activity_code' => '05.02.03',
            'activity_name' => 'Kegiatan Buku',
            'account_code' => '5.1.02.02',
            'description' => 'Belanja buku',
            'amount' => 0,
            'payload' => json_encode([]),
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    public function test_mode_alias_maps_to_legacy_quarter_scope(): void
    {
        $this->get(route('rkas-budget.index', [
            'mode' => 'triwulan',
            'periode' => 1,
        ]))->assertOk()
            ->assertViewHas('budget', fn ($value): bool => abs((float) $value - 25_000_000) < 0.01);
    }

    public function test_short_hierarchy_aliases_filter_like_legacy_names(): void
    {
        $this->get(route('rkas-budget.index', [
            'program' => '03',
            'sub' => '03.03',
            'kegiatan' => '03.03.07',
        ]))->assertOk()
            ->assertViewHas('budget', fn ($value): bool => abs((float) $value - 100_000_000) < 0.01);

        $this->get(route('rkas-budget.index', [
            'program' => '99',
        ]))->assertOk()
            ->assertViewHas('budget', fn ($value): bool => abs((float) $value - 100_000_000) < 0.01);
    }

    public function test_filter_component_renders_options_and_navigates_on_change(): void
    {
        $this->withSession([
            'active_fiscal_year_id' => 1,
            'active_fund_source_id' => 1,
        ]);

        Livewire::test(RkasBudgetFilter::class)
            ->assertSee('Filter Penganggaran')
            ->assertSee('Semua Program')
            ->assertSee('03 - Program')
            ->set('mode', 'bulan')
            ->assertRedirect(route('rkas-budget.index', [
                'mode' => 'bulan',
            ]));
    }

    public function test_filter_component_resets_child_state_on_parent_change(): void
    {
        $this->withSession([
            'active_fiscal_year_id' => 1,
            'active_fund_source_id' => 1,
        ]);

        Livewire::test(RkasBudgetFilter::class)
            ->assertSee('03 - Program')
            ->assertSee('05 - Program')
            ->set('kegiatan', '03.03.07')
            ->assertSet('program', '03')
            ->assertSet('sub', '03.03')
            ->set('program', '')
            ->assertSet('sub', '')
            ->assertSet('kegiatan', '');
    }

    public function test_filter_reads_full_hierarchy_from_central_reference_when_raw_mirror_is_absent(): void
    {
        $now = now();
        $anggaranId = DB::connection('school')->table('arkas_raw_mirror_tables')->insertGetId([
            'source_id' => 1,
            'source_table' => 'anggaran',
            'schema' => json_encode([]),
            'schema_hash' => hash('sha256', 'anggaran'),
            'row_count' => 1,
            'status' => 'ACTIVE',
            'last_synced_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $rapbsId = DB::connection('school')->table('arkas_raw_mirror_tables')->insertGetId([
            'source_id' => 1,
            'source_table' => 'rapbs',
            'schema' => json_encode([]),
            'schema_hash' => hash('sha256', 'rapbs'),
            'row_count' => 1,
            'status' => 'ACTIVE',
            'last_synced_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $insertRow = function (int $tableId, string $key, array $payload) use ($now): void {
            DB::connection('school')->table('arkas_raw_mirror_rows')->insert([
                'mirror_table_id' => $tableId,
                'source_key' => $key,
                'ordinal' => 0,
                'payload' => json_encode($payload),
                'payload_hash' => hash('sha256', json_encode($payload)),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        };
        $insertRow($anggaranId, 'ANG-1', [
            'ID_ANGGARAN' => 'ANG-1',
            'TAHUN_ANGGARAN' => '2026',
            'ID_REF_SUMBER_DANA' => '1',
            'IS_APPROVE' => '1',
            'IS_AKTIF' => '1',
            'IS_REVISI' => '0',
            'LAST_UPDATE' => '2026-01-01',
            'SOFT_DELETE' => '0',
        ]);
        $insertRow($rapbsId, 'RAPBS-1', [
            'ID_ANGGARAN' => 'ANG-1',
            'ID_REF_KODE' => 'REF-1',
            'KODE_PROGRAM' => '03',
            'NAMA_PROGRAM' => 'Program Uji',
            'KODE_SUB_PROGRAM' => '03.03',
            'NAMA_SUB_PROGRAM' => 'Sub Program Uji',
            'KODE_KEGIATAN' => '03.03.07',
            'NAMA_KEGIATAN' => 'Kegiatan Uji',
        ]);

        $component = Livewire::test(RkasBudgetFilter::class)
            ->assertSee('03 - Program Uji')
            ->set('program', '03');
        $subOptions = $component->instance()->render()->getData()['subOptions']->all();
        self::assertSame(['kode' => '03.03', 'nama' => 'Sub Program Uji'], $subOptions[0]);

        $component->set('sub', '03.03');
        $activityOptions = $component->instance()->render()->getData()['kegiatanOptions']->all();
        self::assertSame(['kode' => '03.03.07', 'nama' => 'Kegiatan Uji'], $activityOptions[0]);
    }

    public function test_filter_page_cascades_options_within_selected_parent(): void
    {
        $this->get(route('rkas-budget.index', ['program' => '05']))
            ->assertOk()
            ->assertSee('05.02')
            ->assertDontSee('03.03.07');

        $this->get(route('rkas-budget.index', ['program' => '05', 'sub' => '05.02']))
            ->assertOk()
            ->assertSee('05.02.03')
            ->assertDontSee('03.03.07');
    }

    public function test_budget_table_keeps_subtotals_in_header_and_labels_activity_as_name(): void
    {
        $this->get(route('rkas-budget.index', ['program' => '03']))
            ->assertOk()
            ->assertSee('Nama Kegiatan')
            ->assertDontSee('Subtotal tersaring');
    }
}
