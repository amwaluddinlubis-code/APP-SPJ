<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\School;
use App\Models\SchoolDatabase;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SpjIsolatedNumberingCommandTest extends TestCase
{
    use RefreshDatabase;

    private ?string $baselinePath = null;

    private ?string $copyPath = null;

    /** @var array<string, mixed>|null */
    private ?array $originalSchoolConfig = null;

    protected function tearDown(): void
    {
        DB::purge('school');
        if ($this->originalSchoolConfig !== null) {
            config()->set('database.connections.school', $this->originalSchoolConfig);
        }
        foreach ([$this->baselinePath, $this->copyPath] as $path) {
            if ($path && File::exists($path)) {
                File::delete($path);
            }
        }

        parent::tearDown();
    }

    public function test_isolated_smoke_numbers_exactly_one_package_and_preserves_baseline_bytes(): void
    {
        $school = $this->buildFixture();
        $baselineHashBefore = hash_file('sha256', $this->baselinePath);

        $this->artisan('spj:test-numbering-copy', [
            'npsn' => $school->npsn,
            '--database' => $this->copyPath,
            '--year' => 2026,
            '--quarter' => 2,
            '--fund-source' => 1,
        ])
            ->expectsOutputToContain('ISOLATED TEST RESULT: PASS')
            ->expectsOutputToContain('Baseline hash  : UNCHANGED')
            ->expectsOutputToContain('Sequence SPJ      : 1')
            ->assertSuccessful();

        clearstatcache(true, $this->baselinePath);
        $this->assertSame($baselineHashBefore, hash_file('sha256', $this->baselinePath));

        $this->connectTenant($this->baselinePath);
        $this->assertSame(0, DB::connection('school')->table('spj_documents')->count());
        $this->assertSame(0, DB::connection('school')->table('document_number_sequences')->count());
        $this->assertSame(0, DB::connection('school')->table('spj_packages')->where('status', 'NUMBERED')->count());

        $this->connectTenant($this->copyPath);
        $this->assertSame(1, DB::connection('school')->table('spj_documents')->where('document_type', 'SPJ')->where('status', 'NUMBERED')->count());
        $this->assertSame(1, DB::connection('school')->table('spj_packages')->where('status', 'NUMBERED')->count());
        $this->assertSame(1, (int) DB::connection('school')->table('document_number_sequences')->where('format_name', 'SPJ')->value('last_number'));
        $numberedBukti = DB::connection('school')->table('spj_packages as p')
            ->join('transactions as t', 't.id', '=', 'p.transaction_id')
            ->where('p.status', 'NUMBERED')
            ->value('t.no_bukti');
        $this->assertSame('BPU02', $numberedBukti);
    }

    public function test_isolated_smoke_rejects_the_baseline_path(): void
    {
        $school = $this->buildFixture();
        $hashBefore = hash_file('sha256', $this->baselinePath);

        $this->artisan('spj:test-numbering-copy', [
            'npsn' => $school->npsn,
            '--database' => $this->baselinePath,
            '--year' => 2026,
            '--quarter' => 2,
            '--fund-source' => 1,
        ])
            ->expectsOutputToContain('DITOLAK')
            ->assertExitCode(1);

        clearstatcache(true, $this->baselinePath);
        $this->assertSame($hashBefore, hash_file('sha256', $this->baselinePath));
    }

    private function buildFixture(): School
    {
        $school = School::create([
            'npsn' => '10208183',
            'school_code' => 'SDN.318',
            'name' => 'SDN Isolated Numbering',
        ]);

        $directory = storage_path('framework/testing');
        File::ensureDirectoryExists($directory);
        $this->baselinePath = $directory.'/spj-isolated-baseline-'.uniqid().'.sqlite';
        $this->copyPath = $directory.'/spj-isolated-copy-'.uniqid().'.sqlite';
        File::put($this->baselinePath, '');

        $this->originalSchoolConfig = config('database.connections.school');
        $this->connectTenant($this->baselinePath);
        Artisan::call('migrate', [
            '--database' => 'school',
            '--path' => 'database/migrations/school',
            '--force' => true,
        ]);

        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        $fiscalYear = FiscalYear::query()->create([
            'year' => 2026,
            'fund_source' => 'BOSP',
            'fund_source_id' => 1,
            'is_active' => true,
        ]);

        $this->createReadyPackage($fiscalYear->id, 'BPU10', '10');
        $this->createReadyPackage($fiscalYear->id, 'BPU02', '2');

        DB::purge('school');
        File::copy($this->baselinePath, $this->copyPath);
        config()->set('database.connections.school', $this->originalSchoolConfig);

        SchoolDatabase::create([
            'school_id' => $school->id,
            'database_path' => $this->baselinePath,
            'status' => 'READY',
        ]);

        return $school;
    }

    private function createReadyPackage(int $fiscalYearId, string $noBukti, string $sourceItemId): void
    {
        $transaction = Transaction::query()->create([
            'fiscal_year_id' => $fiscalYearId,
            'fund_source_id' => 1,
            'id_kas_umum' => $sourceItemId,
            'no_bukti' => $noBukti,
            'transaction_date' => '2026-04-07',
            'rkas_date' => '2026-04-01',
            'source_created_at' => '2026-04-07 08:00:00',
            'source_last_updated_at' => '2026-04-07 08:05:00',
            'description' => 'Fixture isolated numbering',
            'payment_description' => 'Fixture isolated numbering',
            'payment_method' => 'tunai',
            'recipient_name' => 'Toko Fixture',
            'receipt_recipient_name' => 'Toko Fixture',
            'vendor_name' => 'Toko Fixture',
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
        $item = $transaction->items()->create([
            'source_item_id' => $sourceItemId,
            'description' => 'Barang fixture',
            'item_description' => 'Barang fixture',
            'quantity' => 1,
            'unit' => 'buah',
            'unit_price' => 1000,
            'amount' => 1000,
            'source_status' => 'ACTIVE',
        ]);
        $item->goods()->create([
            'order_date' => '2026-04-01',
            'bap_date' => '2026-04-07',
            'bast_date' => '2026-04-07',
        ]);
        $transaction->spjPackage()->create(['status' => 'READY']);
    }

    private function connectTenant(string $path): void
    {
        config()->set('database.connections.school.database', $path);
        config()->set('database.connections.school.journal_mode', null);
        config()->set('database.connections.school.synchronous', null);
        DB::purge('school');
    }
}
