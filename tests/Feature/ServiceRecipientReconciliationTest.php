<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\Transaction;
use App\Services\SpjTransactionDetailsService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ServiceRecipientReconciliationTest extends TestCase
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

    public function test_tax_and_net_are_allocated_across_service_recipients_without_rounding_drift(): void
    {
        $transaction = Transaction::query()->create([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'no_bukti' => 'BPU-JASA-001',
            'transaction_date' => '2026-04-10',
            'spj_category' => 'JASA_LAINNYA',
            'gross_amount' => 1000000,
            'tax_total' => 25000,
            'net_amount' => 975000,
            'source_status' => 'ACTIVE',
            'requires_reconciliation' => false,
        ]);

        app(SpjTransactionDetailsService::class)->synchronize($transaction, [
            'service_recipients' => [
                ['name' => 'Penyedia A', 'quantity' => 2, 'rental_days' => 2, 'daily_rate' => 100000],
                ['name' => 'Penyedia B', 'quantity' => 3, 'rental_days' => 2, 'daily_rate' => 100000],
            ],
        ]);

        $recipients = $transaction->serviceRecipients()->orderBy('sort_order')->get();

        $this->assertCount(2, $recipients);
        $this->assertSame(1000000.0, (float) $recipients->sum('amount'));
        $this->assertSame(25000.0, (float) $recipients->sum('tax_amount'));
        $this->assertSame(975000.0, (float) $recipients->sum('net_amount'));
        $this->assertSame(10000.0, (float) $recipients[0]->tax_amount);
        $this->assertSame(15000.0, (float) $recipients[1]->tax_amount);
    }
}
