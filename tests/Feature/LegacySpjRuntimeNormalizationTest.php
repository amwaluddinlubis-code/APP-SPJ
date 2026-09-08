<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\Transaction;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LegacySpjRuntimeNormalizationTest extends TestCase
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

        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        FiscalYear::query()->create(['id' => 1, 'year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    public function test_legacy_printed_package_is_normalized_only_when_number_identity_is_complete(): void
    {
        $transaction = $this->transaction('BPU01', 'BARANG');
        $packageId = DB::connection('school')->table('spj_packages')->insertGetId([
            'transaction_id' => $transaction->id,
            'document_number' => '001/SPJ/2026',
            'status' => 'DICETAK',
            'numbered_at' => '2026-03-06 08:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $incompleteTransaction = $this->transaction('BPU02', 'BARANG');
        $incompletePackageId = DB::connection('school')->table('spj_packages')->insertGetId([
            'transaction_id' => $incompleteTransaction->id,
            'status' => 'DICETAK',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->runNormalizationMigration();

        $this->assertSame('NUMBERED', DB::connection('school')->table('spj_packages')->where('id', $packageId)->value('status'));
        $this->assertSame('DICETAK', DB::connection('school')->table('spj_packages')->where('id', $incompletePackageId)->value('status'));
    }

    public function test_legacy_service_recipient_tax_and_net_are_backfilled_from_source_transaction(): void
    {
        $transaction = $this->transaction('BPU22', 'JASA_LAINNYA', 1000, 100, 900);

        DB::connection('school')->table('spj_service_recipients')->insert([
            [
                'transaction_id' => $transaction->id,
                'name' => 'Penerima A',
                'service_type' => 'Jasa sewa harian',
                'quantity' => 1,
                'unit' => 'unit',
                'rental_days' => 1,
                'daily_rate' => 400,
                'amount' => 400,
                'tax_amount' => 0,
                'net_amount' => 0,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'transaction_id' => $transaction->id,
                'name' => 'Penerima B',
                'service_type' => 'Jasa sewa harian',
                'quantity' => 1,
                'unit' => 'unit',
                'rental_days' => 1,
                'daily_rate' => 600,
                'amount' => 600,
                'tax_amount' => 0,
                'net_amount' => 0,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->runNormalizationMigration();

        $rows = DB::connection('school')->table('spj_service_recipients')
            ->where('transaction_id', $transaction->id)
            ->orderBy('sort_order')
            ->get();

        $this->assertSame(100.0, round((float) $rows->sum('tax_amount'), 2));
        $this->assertSame(900.0, round((float) $rows->sum('net_amount'), 2));
        $this->assertSame(40.0, (float) $rows[0]->tax_amount);
        $this->assertSame(360.0, (float) $rows[0]->net_amount);
        $this->assertSame(60.0, (float) $rows[1]->tax_amount);
        $this->assertSame(540.0, (float) $rows[1]->net_amount);
    }

    public function test_pdf_service_cannot_reintroduce_legacy_printed_status(): void
    {
        $source = file_get_contents(app_path('Services/SpjPdfService.php'));

        $this->assertIsString($source);
        $this->assertStringNotContainsString("'status' => 'DICETAK'", $source);
        $this->assertStringNotContainsString("'generated_at' => now()", $source);
    }

    private function runNormalizationMigration(): void
    {
        $migration = require database_path('migrations/school/2026_09_08_090000_normalize_legacy_spj_runtime_state.php');
        $migration->up();
    }

    private function transaction(string $noBukti, string $category, float $gross = 1000, float $tax = 0, float $net = 1000): Transaction
    {
        return Transaction::query()->create([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'no_bukti' => $noBukti,
            'transaction_date' => '2026-03-06',
            'spj_category' => $category,
            'gross_amount' => $gross,
            'tax_total' => $tax,
            'net_amount' => $net,
            'status' => 'DRAFT',
        ]);
    }
}
