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

class SpjIsolatedTailRollbackCommandTest extends TestCase
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

    public function test_tail_rollback_smoke_releases_tail_and_preserves_baseline(): void
    {
        $school = $this->buildFixture();
        $hashBefore = hash_file('sha256', $this->baselinePath);

        $this->artisan('spj:test-tail-rollback-copy', [
            'npsn' => $school->npsn,
            '--database' => $this->copyPath,
            '--year' => 2026,
            '--quarter' => 2,
            '--fund-source' => 1,
        ])
            ->expectsOutputToContain('ISOLATED TAIL ROLLBACK RESULT: PASS')
            ->expectsOutputToContain('Initial sequences   : 1,2,3')
            ->expectsOutputToContain('Sequence after      : 1')
            ->expectsOutputToContain('Renumbered sequence : 2')
            ->assertSuccessful();

        clearstatcache(true, $this->baselinePath);
        $this->assertSame($hashBefore, hash_file('sha256', $this->baselinePath));

        $this->connectTenant($this->baselinePath);
        $this->assertSame(0, DB::connection('school')->table('spj_documents')->count());
        $this->assertSame(0, DB::connection('school')->table('document_number_sequences')->count());
        $this->assertSame(0, DB::connection('school')->table('spj_packages')->where('status', 'NUMBERED')->count());

        $this->connectTenant($this->copyPath);
        $activeSequences = DB::connection('school')->table('spj_documents')
            ->where('document_type', 'SPJ')
            ->where('scope_key', 'MAIN')
            ->where('status', 'NUMBERED')
            ->orderBy('sequence_number')
            ->pluck('sequence_number')
            ->map(fn ($value): int => (int) $value)
            ->all();
        $this->assertSame([1, 2], $activeSequences);
        $this->assertSame(2, (int) DB::connection('school')->table('document_number_sequences')->where('format_name', 'SPJ')->value('last_number'));
        $this->assertSame(1, DB::connection('school')->table('spj_packages')->where('status', 'DRAFT')->count());
    }

    public function test_tail_rollback_smoke_rejects_baseline_path(): void
    {
        $school = $this->buildFixture();
        $hashBefore = hash_file('sha256', $this->baselinePath);

        $this->artisan('spj:test-tail-rollback-copy', [
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
            'npsn' => '10208184',
            'school_code' => 'SDN.319',
            'name' => 'SDN Tail Rollback',
        ]);

        $directory = storage_path('framework/testing');
        File::ensureDirectoryExists($directory);
        $this->baselinePath = $directory.'/spj-tail-baseline-'.uniqid().'.sqlite';
        $this->copyPath = $directory.'/spj-tail-copy-'.uniqid().'.sqlite';
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

        $this->createReadyPackage($fiscalYear->id, 'BPU03', '3');
        $this->createReadyPackage($fiscalYear->id, 'BPU01', '1');
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
            'description' => 'Fixture tail rollback',
            'payment_description' => 'Fixture tail rollback',
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
