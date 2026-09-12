<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\School;
use App\Models\SchoolDatabase;
use App\Models\SpjPackage;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SpjPreflightNumberingCommandTest extends TestCase
{
    use RefreshDatabase;

    private ?string $tenantPath = null;

    /** @var array<string, mixed>|null */
    private ?array $originalSchoolConfig = null;

    protected function tearDown(): void
    {
        DB::purge('school');
        if ($this->originalSchoolConfig !== null) {
            config()->set('database.connections.school', $this->originalSchoolConfig);
        }
        if ($this->tenantPath && File::exists($this->tenantPath)) {
            File::delete($this->tenantPath);
        }

        parent::tearDown();
    }

    public function test_preflight_is_query_only_and_does_not_change_database_bytes(): void
    {
        [$school, $database] = $this->buildTenantFixture();
        $recordUpdatedAt = $database->updated_at?->toISOString();
        $hashBefore = hash_file('sha256', $this->tenantPath);

        $this->artisan('spj:preflight-numbering', [
            'npsn' => $school->npsn,
            '--year' => 2026,
            '--quarter' => 2,
            '--fund-source' => 1,
            '--limit' => 10,
        ])
            ->expectsOutputToContain('SPJ NUMBERING PREFLIGHT — READ ONLY')
            ->expectsOutputToContain('query_only=ON')
            ->expectsOutputToContain('Hash unchanged : YES')
            ->expectsOutputToContain('PREFLIGHT RESULT: BLOCKED')
            ->assertExitCode(1);

        clearstatcache(true, $this->tenantPath);
        $this->assertSame($hashBefore, hash_file('sha256', $this->tenantPath));
        $this->assertSame($recordUpdatedAt, $database->fresh()->updated_at?->toISOString());
    }

    public function test_preflight_json_exposes_source_aware_canonical_order_without_numbering(): void
    {
        [$school] = $this->buildTenantFixture();
        $hashBefore = hash_file('sha256', $this->tenantPath);

        $exitCode = Artisan::call('spj:preflight-numbering', [
            'npsn' => $school->npsn,
            '--year' => 2026,
            '--quarter' => 2,
            '--fund-source' => 1,
            '--document-type' => 'SPJ',
            '--json' => true,
        ]);

        $report = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $exitCode, 'Fixture sengaja belum lengkap sehingga preflight harus BLOCKED.');
        $this->assertTrue($report['query_only']);
        $this->assertTrue($report['database_hash_unchanged']);
        $this->assertSame(2, $report['summary']['transactions_with_items']);
        $this->assertSame(2, $report['summary']['packages_ready_or_numbered']);
        $this->assertSame(0, $report['summary']['scope_mismatch']);
        $this->assertSame(2, $report['summary']['ordered_packages']);
        $this->assertSame(['BPU02', 'BPU10'], array_column($report['ordering'], 'no_bukti'));

        $this->reconnectTenant();
        $this->assertSame(0, DB::connection('school')->table('spj_documents')->count());
        $this->assertSame(0, DB::connection('school')->table('document_number_sequences')->count());
        $this->assertSame(0, DB::connection('school')->table('quarter_numbering_runs')->count());
        DB::purge('school');

        clearstatcache(true, $this->tenantPath);
        $this->assertSame($hashBefore, hash_file('sha256', $this->tenantPath));
    }

    public function test_preflight_never_creates_a_missing_tenant_database(): void
    {
        $school = School::create(['npsn' => '87654321', 'name' => 'SD Tanpa Database']);
        $missingPath = storage_path('framework/testing/missing-spj-preflight-'.uniqid().'.sqlite');
        SchoolDatabase::create([
            'school_id' => $school->id,
            'database_path' => $missingPath,
            'status' => 'READY',
        ]);

        $this->artisan('spj:preflight-numbering', [
            'npsn' => $school->npsn,
            '--year' => 2026,
            '--quarter' => 2,
            '--fund-source' => 1,
        ])
            ->expectsOutputToContain('tidak ditemukan')
            ->assertExitCode(1);

        $this->assertFileDoesNotExist($missingPath);
    }

    /** @return array{0:School,1:SchoolDatabase} */
    private function buildTenantFixture(): array
    {
        $school = School::create(['npsn' => '10208183', 'name' => 'SDN Preflight']);
        $this->tenantPath = storage_path('framework/testing/spj-preflight-'.uniqid().'.sqlite');
        File::ensureDirectoryExists(dirname($this->tenantPath));
        File::put($this->tenantPath, '');

        $this->originalSchoolConfig = config('database.connections.school');
        $this->reconnectTenant();
        Artisan::call('migrate', [
            '--database' => 'school',
            '--path' => 'database/migrations/school',
            '--force' => true,
        ]);

        FundSource::query()->create([
            'id' => 1,
            'code' => 'BOSP',
            'name' => 'BOSP',
        ]);
        $year = FiscalYear::query()->create([
            'year' => 2026,
            'fund_source' => 'BOSP',
            'fund_source_id' => 1,
            'is_active' => true,
        ]);

        $this->createPackage($year->id, 'BPU10', '10', '2026-04-07 08:00:00', '2026-04-07 08:05:00');
        $this->createPackage($year->id, 'BPU02', '2', '2026-04-07 08:00:00', '2026-04-07 08:05:00');

        DB::purge('school');
        config()->set('database.connections.school', $this->originalSchoolConfig);

        $database = SchoolDatabase::create([
            'school_id' => $school->id,
            'database_path' => $this->tenantPath,
            'status' => 'READY',
        ]);

        return [$school, $database];
    }

    private function createPackage(int $fiscalYearId, string $noBukti, string $sourceItemId, string $sourceCreatedAt, string $sourceUpdatedAt): SpjPackage
    {
        $transaction = Transaction::query()->create([
            'fiscal_year_id' => $fiscalYearId,
            'fund_source_id' => 1,
            'id_kas_umum' => $sourceItemId,
            'no_bukti' => $noBukti,
            'transaction_date' => '2026-04-07',
            'rkas_date' => '2026-04-01',
            'source_created_at' => $sourceCreatedAt,
            'source_last_updated_at' => $sourceUpdatedAt,
            'description' => 'Fixture preflight',
            'payment_description' => 'Fixture preflight',
            'payment_method' => 'tunai',
            'recipient_name' => 'Penerima Fixture',
            'receipt_recipient_name' => 'Penerima Fixture',
            'gross_amount' => 1000,
            'tax_total' => 0,
            'net_amount' => 1000,
            'spj_category' => 'BARANG',
            'activity_code' => '05.02.01',
            'activity_name' => 'Kegiatan fixture',
            'account_code' => '5.1.02.01',
            'account_name' => 'Belanja barang fixture',
            'source_status' => 'ACTIVE',
            'requires_reconciliation' => false,
        ]);
        $transaction->items()->create([
            'source_item_id' => $sourceItemId,
            'description' => 'Barang fixture',
            'item_description' => 'Barang fixture',
            'quantity' => 1,
            'unit' => 'buah',
            'unit_price' => 1000,
            'amount' => 1000,
            'source_status' => 'ACTIVE',
        ]);

        return $transaction->spjPackage()->create(['status' => 'READY']);
    }

    private function reconnectTenant(): void
    {
        config()->set('database.connections.school.database', $this->tenantPath);
        config()->set('database.connections.school.journal_mode', null);
        config()->set('database.connections.school.synchronous', null);
        DB::purge('school');
    }
}
