<?php

namespace Tests\Feature;

use App\Http\Controllers\TransactionController;
use App\Models\DocumentNumberFormat;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\SpjDocument;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Services\OperationalAuditService;
use App\Services\SpjDocumentLifecycleService;
use App\Services\SpjDocumentNumberService;
use App\Support\ActiveSpjContext;
use App\UseCases\Spj\SpjNumberingRollbackUseCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SpjNumberingRollbackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');
        Artisan::call('migrate', ['--database' => 'school', '--path' => 'database/migrations/school', '--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    public function test_individual_cancel_keeps_number_reserved_and_next_document_uses_next_sequence(): void
    {
        [$year, $fund] = $this->contextData();
        $first = $this->package($year, $fund->id, '2026-01-10');
        $second = $this->package($year, $fund->id, '2026-01-11');
        $numbers = app(SpjDocumentNumberService::class);

        $firstDocument = $numbers->assign($first, 'SPJ', $first->transaction->transaction_date, '10200001');
        app(SpjDocumentLifecycleService::class)->cancel($firstDocument, 99, 'Transaksi dibatalkan');
        $secondDocument = $numbers->assign($second, 'SPJ', $second->transaction->transaction_date, '10200001');

        $this->assertSame('CANCELLED', $firstDocument->fresh()->status);
        $this->assertSame(1, (int) $firstDocument->fresh()->sequence_number);
        $this->assertSame(2, (int) $secondDocument->sequence_number);
    }

    public function test_tail_rollback_deletes_active_numbering_and_resets_sequence(): void
    {
        [$year, $fund] = $this->contextData();
        $packages = collect([
            $this->package($year, $fund->id, '2026-01-10'),
            $this->package($year, $fund->id, '2026-01-11'),
            $this->package($year, $fund->id, '2026-01-12'),
        ]);
        $numbers = app(SpjDocumentNumberService::class);
        foreach ($packages as $package) {
            $numbers->assign($package, 'SPJ', $package->transaction->transaction_date, '10200001');
        }

        $useCase = $this->rollbackUseCase($year->id, $fund->id);
        $useCase->rollbackFromSequence($this->request([
            'sequence_number' => 2,
            'reason' => 'Urutan transaksi ARKAS berubah',
        ]));

        $this->assertDatabaseHas('spj_documents', ['spj_package_id' => $packages[0]->id, 'sequence_number' => 1], 'school');
        $this->assertDatabaseMissing('spj_documents', ['spj_package_id' => $packages[1]->id, 'status' => 'NUMBERED'], 'school');
        $this->assertDatabaseMissing('spj_documents', ['spj_package_id' => $packages[2]->id, 'status' => 'NUMBERED'], 'school');
        $this->assertSame('DRAFT', $packages[1]->fresh()->status);
        $this->assertSame('DRAFT', $packages[2]->fresh()->status);
        $this->assertSame(1, (int) DB::connection('school')->table('document_number_sequences')->where([
            'fiscal_year_id' => $year->id,
            'fund_source_id' => $fund->id,
            'format_name' => 'SPJ',
            'period_key' => '2026',
        ])->value('last_number'));

        $packages[1]->forceFill(['status' => 'READY'])->save();
        $renumbered = $numbers->assign($packages[1]->fresh('transaction'), 'SPJ', $packages[1]->transaction->transaction_date, '10200001');
        $this->assertSame(2, (int) $renumbered->sequence_number);
    }

    public function test_quarter_rollback_is_blocked_by_later_numbered_quarter_in_same_context(): void
    {
        [$year, $fund] = $this->contextData();
        $q1 = $this->package($year, $fund->id, '2026-02-10');
        $q2 = $this->package($year, $fund->id, '2026-04-10');
        $numbers = app(SpjDocumentNumberService::class);
        $numbers->assign($q1, 'SPJ', $q1->transaction->transaction_date, '10200001');
        $numbers->assign($q2, 'SPJ', $q2->transaction->transaction_date, '10200001');

        $response = $this->rollbackUseCase($year->id, $fund->id)->cancelQuarter($this->request([
            'quarter' => 1,
            'reason' => 'Koreksi TW1',
        ]));

        $this->assertStringContainsString('Triwulan 2', (string) $response->getSession()->get('error'));
        $this->assertSame('NUMBERED', $q1->fresh()->status);
    }

    public function test_later_quarter_from_other_fund_source_does_not_block_active_context(): void
    {
        [$year, $fund] = $this->contextData();
        $otherFund = FundSource::query()->create(['id' => 2, 'code' => 'LAIN', 'name' => 'Sumber Lain']);
        $q1 = $this->package($year, $fund->id, '2026-02-10');
        $otherQ2 = $this->package($year, $otherFund->id, '2026-04-10');

        $this->manualNumber($q1, 1, '0001/SPJ/2026');
        $this->manualNumber($otherQ2, 9, '0009/SPJ-LAIN/2026');
        DB::connection('school')->table('document_number_sequences')->insert([
            'fiscal_year_id' => $year->id,
            'fund_source_id' => $fund->id,
            'format_name' => 'SPJ',
            'period_key' => '2026',
            'last_number' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('school')->table('document_number_sequences')->insert([
            'fiscal_year_id' => $year->id,
            'fund_source_id' => $otherFund->id,
            'format_name' => 'SPJ',
            'period_key' => '2026',
            'last_number' => 9,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->rollbackUseCase($year->id, $fund->id)->cancelQuarter($this->request([
            'quarter' => 1,
            'reason' => 'Koreksi TW1 sumber aktif',
        ]));

        $this->assertSame('DRAFT', $q1->fresh()->status);
        $this->assertSame('NUMBERED', $otherQ2->fresh()->status);
        $this->assertSame(9, (int) DB::connection('school')->table('document_number_sequences')->where('fund_source_id', $otherFund->id)->value('last_number'));
    }

    public function test_item_description_can_change_when_numbered_but_not_when_final(): void
    {
        [$year, $fund] = $this->contextData();
        $package = $this->package($year, $fund->id, '2026-01-10');
        $package->forceFill(['status' => 'NUMBERED', 'document_number' => '0001/SPJ/2026'])->save();
        $item = $package->transaction->items()->firstOrFail();
        session([
            'active_fiscal_year_id' => $year->id,
            'active_fund_source_id' => $fund->id,
        ]);

        app(TransactionController::class)->updateSpjDescriptions($this->request([
            'items' => [['id' => $item->id, 'item_description' => 'Nama baru setelah numbering']],
        ]), (string) $package->transaction_id);
        $this->assertSame('Nama baru setelah numbering', $item->fresh()->item_description);

        $package->forceFill(['status' => 'FINAL'])->save();
        app(TransactionController::class)->updateSpjDescriptions($this->request([
            'items' => [['id' => $item->id, 'item_description' => 'Tidak boleh berubah']],
        ]), (string) $package->transaction_id);
        $this->assertSame('Nama baru setelah numbering', $item->fresh()->item_description);
    }

    /** @return array{FiscalYear, FundSource} */
    private function contextData(): array
    {
        $fund = FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        $year = FiscalYear::query()->create([
            'year' => 2026,
            'fund_source' => 'BOSP',
            'fund_source_id' => $fund->id,
        ]);
        DocumentNumberFormat::query()->create([
            'fiscal_year_id' => $year->id,
            'document_type' => 'SPJ',
            'format_pattern' => '{SEQ}/SPJ/{YEAR}',
            'reset_period' => 'YEAR',
            'padding' => 4,
            'is_active' => true,
        ]);

        return [$year, $fund];
    }

    private function package(FiscalYear $year, int $fundSourceId, string $date): SpjPackage
    {
        $transaction = Transaction::query()->create([
            'fiscal_year_id' => $year->id,
            'fund_source_id' => $fundSourceId,
            'id_kas_umum' => uniqid('KAS-', true),
            'no_bukti' => uniqid('BPU-', true),
            'transaction_date' => $date,
            'gross_amount' => 1000,
            'tax_total' => 0,
            'net_amount' => 1000,
            'spj_category' => 'BARANG',
        ]);
        $transaction->items()->create([
            'description' => 'Barang',
            'item_description' => 'Barang',
            'quantity' => 1,
            'unit_price' => 1000,
            'amount' => 1000,
        ]);

        return $transaction->spjPackage()->create(['status' => 'READY'])->load('transaction');
    }

    private function manualNumber(SpjPackage $package, int $sequence, string $number): SpjDocument
    {
        $document = $package->documents()->create([
            'document_type' => 'SPJ',
            'scope_key' => 'MAIN',
            'document_number' => $number,
            'sequence_number' => $sequence,
            'document_date' => $package->transaction->transaction_date,
            'event_date' => $package->transaction->transaction_date,
            'status' => 'NUMBERED',
            'numbered_at' => now(),
        ]);
        $package->forceFill(['status' => 'NUMBERED', 'document_number' => $number, 'numbered_at' => now()])->save();

        return $document;
    }

    private function rollbackUseCase(int $yearId, int $fundSourceId): SpjNumberingRollbackUseCase
    {
        return new SpjNumberingRollbackUseCase(
            app(OperationalAuditService::class),
            new ActiveSpjContext(1, $yearId, $fundSourceId, 99, true),
        );
    }

    private function request(array $data): Request
    {
        $request = Request::create('/test', 'POST', $data);
        $request->setLaravelSession(app('session')->driver());
        app()->instance('request', $request);

        return $request;
    }
}
