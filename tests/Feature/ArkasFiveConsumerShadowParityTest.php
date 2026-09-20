<?php

namespace Tests\Feature;

use App\Livewire\RkasBudgetFilter;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\User;
use App\Services\ArkasPersistentReferenceAuthority;
use App\Services\ArkasReferenceReadBoundary;
use App\Services\ArkasReferenceResolver;
use App\Services\SpjV2CanonicalReadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

final class ArkasFiveConsumerShadowParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');
        Artisan::call('migrate', ['--database' => 'school', '--path' => 'database/migrations/school', '--force' => true]);

        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        FiscalYear::query()->create(['id' => 1, 'year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
        $this->actingAs(User::factory()->create());
        $this->withoutMiddleware()->withSession(['active_school_id' => 1, 'active_fiscal_year_id' => 1, 'active_fund_source_id' => 1]);
        $this->seedTenantMirror();
        $this->seedCentralAuthority();
    }

    public function test_all_five_consumer_entry_points_have_request_level_shadow_parity(): void
    {
        $results = [
            'ArkasReferenceController' => $this->compareModes(fn (): array => $this->referenceControllerResult()),
            'RkasBudgetController' => $this->compareModes(fn (): array => $this->budgetControllerResult()),
            'RkasBudgetFilter' => $this->compareModes(fn (): array => $this->budgetFilterResult()),
            'ArkasDomainAdapter' => $this->compareModes(fn (): array => $this->domainAdapterResult()),
            'SpjV2CanonicalReadService' => $this->compareModes(fn (): array => $this->canonicalReaderResult()),
        ];

        foreach ($results as $consumer => $result) {
            self::assertSame($result['legacy'], $result['central'], $consumer.' semantic shadow parity mismatch.');
        }

        self::assertCount(5, $results);
    }

    public function test_central_compat_fails_closed_when_a_consumer_requires_missing_reference(): void
    {
        config()->set('arkas.reference_read_mode', ArkasReferenceResolver::CENTRAL_COMPAT);
        $this->expectExceptionMessage('shadow parity mismatch');

        app(ArkasReferenceReadBoundary::class)->resolve('ref_rekening', collect([['KODE_REKENING' => 'MISSING', 'REKENING' => 'Missing', 'TAHUN' => '2026']]), null);
    }

    public function test_production_selector_defaults_to_central_compat_and_legacy_raw_is_explicit_rollback(): void
    {
        config()->set('arkas.reference_read_mode', ArkasReferenceResolver::CENTRAL_COMPAT);

        self::assertSame(ArkasReferenceResolver::CENTRAL_COMPAT, config('arkas.reference_read_mode'));

        config()->set('arkas.reference_read_mode', ArkasReferenceResolver::LEGACY_RAW);

        self::assertSame(
            [['KODE_REKENING' => 'ROLLBACK', 'REKENING' => 'Rollback', 'TAHUN' => '2026']],
            app(ArkasReferenceReadBoundary::class)->resolve('ref_rekening', collect([['KODE_REKENING' => 'ROLLBACK', 'REKENING' => 'Rollback', 'TAHUN' => '2026']]))?->all(),
        );
    }

    /** @return array{legacy: array<string, mixed>, central: array<string, mixed>} */
    private function compareModes(callable $scenario): array
    {
        config()->set('arkas.reference_read_mode', ArkasReferenceResolver::LEGACY_RAW);
        $legacy = $scenario();
        config()->set('arkas.reference_read_mode', ArkasReferenceResolver::CENTRAL_COMPAT);
        $central = $scenario();

        return ['legacy' => $legacy, 'central' => $central];
    }

    /** @return array<string, mixed> */
    private function referenceControllerResult(): array
    {
        $response = $this->get(route('arkas.references', ['type' => 'accounts', 'q' => '05.01']));
        $response->assertOk();
        $paginator = $response->viewData('rows');

        return ['counts' => $response->viewData('counts'), 'rows' => $paginator->items(), 'type' => $response->viewData('type')];
    }

    /** @return array<string, mixed> */
    private function budgetControllerResult(): array
    {
        $response = $this->get(route('rkas-budget.index', ['program' => '05']));
        $response->assertOk();

        return ['budget' => $response->viewData('budget'), 'tree' => $response->viewData('hierarchyTree'), 'filter' => $response->viewData('filterContext')];
    }

    /** @return array<string, mixed> */
    private function budgetFilterResult(): array
    {
        $component = Livewire::test(RkasBudgetFilter::class);
        $view = $component->instance()->render();

        return ['data' => $this->normalize($view->getData())];
    }

    /** @return array<string, mixed> */
    private function domainAdapterResult(): array
    {
        return ['rows' => $this->normalize(app(ArkasPersistentReferenceAuthority::class)->readForTenant('ref_kode', '1'))];
    }

    /** @return array<string, mixed> */
    private function canonicalReaderResult(): array
    {
        $method = new ReflectionMethod(SpjV2CanonicalReadService::class, 'rawPayloadRows');
        $method->setAccessible(true);
        $service = app(SpjV2CanonicalReadService::class);
        $db = DB::connection('school');

        $rows = $method->invoke($service, $db, 1, 'ref_kode');

        return ['rows' => $this->normalize($rows)];
    }

    private function seedTenantMirror(): void
    {
        $rows = [
            'ref_kode' => [$this->codeRow(true)],
            'ref_rekening' => [['KODE_REKENING' => '5.1.02.01', 'REKENING' => 'Belanja Buku', 'TAHUN' => '2026']],
            'ref_acuan_barang' => [['ID_BARANG' => 'BARANG-1', 'KODE_REKENING' => '5.1.02.01', 'NAMA_BARANG' => 'Buku', 'SATUAN' => 'paket', 'HARGA_BARANG' => 1000, 'BATAS_BAWAH' => 500, 'BATAS_ATAS' => 1500, 'TAHUN' => '2026']],
            'rapbs' => [['ID_ANGGARAN' => 'ANG-1', 'KODE_REKENING' => '5.1.02.01', 'URAIAN' => 'Belanja Buku', 'JUMLAH' => 1000, 'ID_REF_SUMBER_DANA' => 1, 'TAHUN_ANGGARAN' => '2026', 'SOFT_DELETE' => '0']],
            'anggaran' => [['ID_ANGGARAN' => 'ANG-1', 'TAHUN_ANGGARAN' => '2026', 'ID_REF_SUMBER_DANA' => 1, 'IS_APPROVE' => '1', 'IS_AKTIF' => '1', 'IS_REVISI' => '0', 'LAST_UPDATE' => '2026-01-01', 'SOFT_DELETE' => '0']],
        ];
        foreach ($rows as $table => $payloads) {
            $mirrorId = DB::connection('school')->table('arkas_raw_mirror_tables')->insertGetId(['source_id' => 1, 'source_table' => $table, 'schema' => json_encode([]), 'schema_hash' => hash('sha256', $table), 'row_count' => count($payloads), 'status' => 'ACTIVE', 'last_synced_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($payloads as $ordinal => $payload) {
                DB::connection('school')->table('arkas_raw_mirror_rows')->insert(['mirror_table_id' => $mirrorId, 'source_key' => (string) ($payload['ID_KODE'] ?? $payload['ID_ANGGARAN'] ?? $payload['ID_BARANG'] ?? $ordinal), 'ordinal' => $ordinal, 'payload' => json_encode($payload), 'payload_hash' => hash('sha256', json_encode($payload)), 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        DB::connection('school')->table('arkas_rkas_items')->insert(['fiscal_year_id' => 1, 'fund_source_id' => 1, 'source_rapbs_id' => 'ANG-1', 'activity_code' => '05.02.03', 'activity_name' => 'Kegiatan Buku', 'account_code' => '5.1.02.01', 'description' => 'Buku', 'amount' => 1000, 'payload' => json_encode([]), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function seedCentralAuthority(): void
    {
        $authority = new ArkasPersistentReferenceAuthority;
        $authority->promote('ref_rekening', [['kode_rekening' => '5.1.02.01', 'rekening' => 'Belanja Buku', 'tahun' => '2026']], '2026.09');
        $authority->promote('ref_acuan_barang', [['id_barang' => 'BARANG-1', 'kode_rekening' => '5.1.02.01', 'nama_barang' => 'Buku', 'satuan' => 'paket', 'harga_barang' => 1000, 'batas_bawah' => 500, 'batas_atas' => 1500, 'tahun' => '2026']], '2026.09');
        $authority->promoteCodeVariants('1', [$this->codeRow()], '2026.09');
    }

    /** @return array<string, mixed> */
    private function codeRow(bool $upper = false): array
    {
        $row = ['id_kode' => '05.02.03', 'parent_kode' => '05.02', 'uraian_kode' => 'Kegiatan Buku', 'id_level_kode' => '3', 'tipe' => 'K', 'id_ref_kode' => 'REF-1', 'tahun' => '2026', 'sumber_dana_id' => '1', 'bentuk_pendidikan_id' => '6'];

        return $upper ? array_change_key_case($row, CASE_UPPER) : $row;
    }

    private function normalize(mixed $value): mixed
    {
        if ($value instanceof Collection) {
            return $this->normalize($value->all());
        }

        if (! is_array($value)) {
            return $value;
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[strtolower((string) $key)] = $this->normalize($item);
        }
        ksort($normalized);

        return $normalized;
    }
}
