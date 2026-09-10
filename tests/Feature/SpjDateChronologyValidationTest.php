<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\Transaction;
use App\UseCases\Spj\CreateSpjDraftUseCase;
use App\UseCases\Spj\UpdateSpjPackageDetailsUseCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SpjDateChronologyValidationTest extends TestCase
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

        session([
            'active_fiscal_year_id' => 1,
            'active_fund_source_id' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    public function test_service_completion_date_cannot_precede_service_start_date(): void
    {
        $transaction = $this->transaction('JASA-DATE-001', 'JASA_LAINNYA');
        $request = Request::create('/spj/'.$transaction->id, 'PUT', [
            'spj_category' => 'JASA_LAINNYA',
            'payment_description' => 'Jasa pengujian',
            'payment_method' => 'tunai',
            'service_recipients' => [[
                'name' => 'Penyedia Uji',
                'quantity' => 1,
                'rental_days' => 1,
                'daily_rate' => 100000,
                'usage_started_at' => '2026-03-09',
                'usage_completed_at' => '2026-03-08',
            ]],
        ]);

        $errors = $this->validationErrors($transaction, $request);

        $this->assertArrayHasKey('service_recipients.0.usage_completed_at', $errors);
        $this->assertSame(
            'Tanggal selesai jasa tidak boleh sebelum tanggal mulai.',
            $errors['service_recipients.0.usage_completed_at'][0],
        );
    }

    public function test_sppd_return_date_cannot_precede_departure_date(): void
    {
        $transaction = $this->transaction('SPPD-DATE-001', 'SPPD');
        $request = Request::create('/spj/'.$transaction->id, 'PUT', [
            'spj_category' => 'SPPD',
            'payment_description' => 'Perjalanan dinas pengujian',
            'payment_method' => 'tunai',
            'travels' => [[
                'traveler_name' => 'Pelaksana Uji',
                'destination' => 'Lokasi Uji',
                'departure_date' => '2026-03-09',
                'return_date' => '2026-03-08',
                'amount' => 100000,
            ]],
        ]);

        $errors = $this->validationErrors($transaction, $request);

        $this->assertArrayHasKey('travels.0.return_date', $errors);
        $this->assertSame(
            'Tanggal kembali tidak boleh sebelum tanggal berangkat.',
            $errors['travels.0.return_date'][0],
        );
    }

    private function transaction(string $number, string $category): Transaction
    {
        $transaction = Transaction::query()->create([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'no_bukti' => $number,
            'transaction_date' => '2026-03-10',
            'spj_category' => $category,
            'gross_amount' => 100000,
            'net_amount' => 100000,
            'tax_total' => 0,
            'source_status' => 'ACTIVE',
            'requires_reconciliation' => false,
        ]);

        $transaction->items()->create([
            'description' => 'Item uji tanggal',
            'item_description' => 'Item uji tanggal',
            'quantity' => 1,
            'unit' => 'kegiatan',
            'unit_price' => 100000,
            'amount' => 100000,
        ]);

        app(CreateSpjDraftUseCase::class)->handle((string) $transaction->id);

        return $transaction->fresh();
    }

    /** @return array<string, array<int, string>> */
    private function validationErrors(Transaction $transaction, Request $request): array
    {
        try {
            app(UpdateSpjPackageDetailsUseCase::class)->handle((string) $transaction->spjPackage->id, $request);
            $this->fail('Expected SPJ date chronology validation to fail.');
        } catch (ValidationException $exception) {
            return $exception->errors();
        }
    }
}
