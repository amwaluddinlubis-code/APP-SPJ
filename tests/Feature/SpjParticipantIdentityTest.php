<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Services\SpjTransactionDetailsService;
use App\UseCases\Spj\SpjWorkspaceUseCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

class SpjParticipantIdentityTest extends TestCase
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

    public function test_participants_persist_nip_and_nuptk(): void
    {
        [$transaction] = $this->seedTransaction();

        $this->synchronize($transaction, [
            'participants' => [
                ['name' => 'Rista Dewi', 'position' => 'Guru', 'nip' => '19800101', 'nuptk' => '654321', 'portions' => 2],
                ['name' => 'Tamu Manual', 'position' => null, 'nip' => '', 'nuptk' => null, 'portions' => 1],
            ],
        ]);

        $rows = $transaction->participants()->orderBy('sort_order')->get();
        $this->assertCount(2, $rows);
        $this->assertSame('19800101', $rows[0]->nip);
        $this->assertSame('654321', $rows[0]->nuptk);
        $this->assertSame('Guru', $rows[0]->position);
        $this->assertNull($rows[1]->nip);
        $this->assertNull($rows[1]->nuptk);
    }

    public function test_roster_serves_all_active_sources_with_identifiers(): void
    {
        Employee::query()->create([
            'source_type' => 'PTK', 'source_key' => 'PTK:1', 'name' => 'Arkas Saja',
            'normalized_name' => 'arkas saja', 'nip' => '19700101', 'nuptk' => '111111',
        ]);
        Employee::query()->create([
            'source_type' => 'MANUAL', 'source_key' => 'MANUAL:1', 'name' => 'Honorer Manual',
            'normalized_name' => 'honorer manual', 'operator_locked' => true,
        ]);
        Employee::query()->create([
            'source_type' => 'DAPODIK', 'source_key' => 'DAPODIK:1', 'name' => 'Nonaktif',
            'normalized_name' => 'nonaktif', 'is_active' => false,
        ]);

        $roster = app(SpjWorkspaceUseCase::class)->participantRoster();
        $names = $roster->pluck('name')->all();

        $this->assertContains('Arkas Saja', $names);
        $this->assertContains('Honorer Manual', $names);
        $this->assertNotContains('Nonaktif', $names);

        $arkasOnly = $roster->firstWhere('name', 'Arkas Saja');
        $this->assertSame('19700101', $arkasOnly->nip);
        $this->assertSame('111111', $arkasOnly->nuptk);
    }

    /**
     * @return array{0: Transaction}
     */
    private function seedTransaction(): array
    {
        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        $year = FiscalYear::query()->create(['year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
        $transaction = Transaction::query()->create([
            'fiscal_year_id' => $year->id, 'fund_source_id' => 1,
            'no_bukti' => 'BUKTI-1', 'transaction_date' => '2026-01-15',
        ]);
        TransactionItem::query()->create(['transaction_id' => $transaction->id, 'description' => 'Konsumsi rapat']);

        return [$transaction->fresh()];
    }

    /** @param array<string, mixed> $details */
    private function synchronize(Transaction $transaction, array $details): void
    {
        $method = new ReflectionMethod(SpjTransactionDetailsService::class, 'synchronizeParticipants');
        $method->invoke(app(SpjTransactionDetailsService::class), $transaction->fresh(), $details);
    }
}
