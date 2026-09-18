<?php

namespace Tests\Feature;

use App\Models\ArkasSource;
use App\Models\FiscalYear;
use App\Services\SpjFreshProjectionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SpjFreshProjectionContractTest extends TestCase
{
    protected function tearDown(): void
    {
        DB::purge('school');

        parent::tearDown();
    }

    public function test_projection_groups_spending_rows_and_keeps_fund_source_boundary(): void
    {
        $this->prepareSchoolConnection();

        $source = new ArkasSource;
        $source->id = 7;

        $year = new FiscalYear;
        $year->id = 11;
        $year->year = 2026;

        $anggaranTableId = $this->insertMirrorTable($source->id, 'anggaran');
        $this->insertRawRow($anggaranTableId, 'ANG-REG', [
            'id_anggaran' => 'ANG-REG',
            'tahun_anggaran' => '2026',
            'id_ref_sumber_dana' => 1,
            'is_approve' => 1,
            'is_aktif' => 1,
            'soft_delete' => 0,
            'is_revisi' => 0,
            'last_update' => '2026-01-01 08:00:00',
        ]);
        $this->insertRawRow($anggaranTableId, 'ANG-OTHER', [
            'id_anggaran' => 'ANG-OTHER',
            'tahun_anggaran' => '2026',
            'id_ref_sumber_dana' => 2,
            'is_approve' => 1,
            'is_aktif' => 1,
            'soft_delete' => 0,
            'is_revisi' => 0,
            'last_update' => '2026-01-01 08:00:00',
        ]);

        $kasTableId = $this->insertMirrorTable($source->id, 'kas_umum');
        $this->insertRawRow($kasTableId, 'KAS-002', [
            'id_kas_umum' => 'KAS-002',
            'id_kas_nota' => 'NOTA-SHARED',
            'id_ref_bku' => 4,
            'id_anggaran' => 'ANG-REG',
            'tanggal_transaksi' => '2026-02-03',
            'no_bukti' => 'BPU-001',
            'saldo' => 60000,
            'soft_delete' => 0,
        ]);
        $this->insertRawRow($kasTableId, 'KAS-001', [
            'id_kas_umum' => 'KAS-001',
            'id_kas_nota' => 'NOTA-SHARED',
            'id_ref_bku' => 4,
            'id_anggaran' => 'ANG-REG',
            'tanggal_transaksi' => '2026-02-03',
            'no_bukti' => 'BPU-001',
            'saldo' => 40000,
            'soft_delete' => 0,
        ]);
        $this->insertRawRow($kasTableId, 'PAJAK-001', [
            'id_kas_umum' => 'PAJAK-001',
            'parent_id_kas_umum' => 'KAS-001',
            'id_ref_bku' => 10,
            'id_anggaran' => 'ANG-REG',
            'tanggal_transaksi' => '2026-02-03',
            'saldo' => 11000,
            'soft_delete' => 0,
        ]);
        $this->insertRawRow($kasTableId, 'KAS-OTHER', [
            'id_kas_umum' => 'KAS-OTHER',
            'id_ref_bku' => 4,
            'id_anggaran' => 'ANG-OTHER',
            'tanggal_transaksi' => '2026-02-03',
            'no_bukti' => 'BPU-999',
            'saldo' => 999999,
            'soft_delete' => 0,
        ]);

        $result = app(SpjFreshProjectionService::class)->project($year, 1, $source);

        $expectedSourceKey = hash('sha256', 'KAS-001|KAS-002');

        $this->assertSame(1, $result['transactions']);
        $this->assertSame(2, $result['items']);

        $transaction = DB::connection('school')->table('spj_fresh_transactions')->sole();
        $this->assertSame($expectedSourceKey, $transaction->source_key);
        $this->assertSame(11, $transaction->fiscal_year_id);
        $this->assertSame(1, $transaction->fund_source_id);
        $this->assertSame('ACTIVE', $transaction->source_status);

        $itemKeys = DB::connection('school')
            ->table('spj_fresh_transaction_items')
            ->orderBy('source_key')
            ->pluck('source_key')
            ->all();

        $this->assertSame(['KAS-001', 'KAS-002'], $itemKeys);
        $this->assertDatabaseMissing('spj_fresh_transactions', [
            'fund_source_id' => 1,
            'source_key' => hash('sha256', 'KAS-OTHER'),
        ], 'school');
    }

    public function test_transaction_source_key_is_stable_when_raw_row_order_changes(): void
    {
        $ids = ['KAS-003', 'KAS-001', 'KAS-002'];
        $sorted = $ids;
        sort($sorted, SORT_STRING);

        $this->assertSame(
            hash('sha256', implode('|', $sorted)),
            hash('sha256', 'KAS-001|KAS-002|KAS-003'),
        );
    }

    public function test_missing_approved_budget_marks_existing_projection_missing_and_return_reactivates_it(): void
    {
        $this->prepareSchoolConnection();
        $source = new ArkasSource;
        $source->id = 7;
        $year = new FiscalYear;
        $year->id = 11;
        $year->year = 2026;

        $budgetTableId = $this->insertMirrorTable($source->id, 'anggaran');
        $this->insertRawRow($budgetTableId, 'ANG-001', [
            'id_anggaran' => 'ANG-001',
            'tahun_anggaran' => '2026',
            'id_ref_sumber_dana' => 1,
            'is_approve' => 1,
            'is_aktif' => 1,
            'soft_delete' => 0,
            'is_revisi' => 0,
            'last_update' => '2026-01-01 08:00:00',
        ]);
        $kasTableId = $this->insertMirrorTable($source->id, 'kas_umum');
        $this->insertRawRow($kasTableId, 'KAS-001', [
            'id_kas_umum' => 'KAS-001',
            'id_ref_bku' => 4,
            'id_anggaran' => 'ANG-001',
            'tanggal_transaksi' => '2026-02-03',
            'no_bukti' => 'BPU-001',
            'saldo' => 100000,
            'soft_delete' => 0,
        ]);

        app(SpjFreshProjectionService::class)->project($year, 1, $source);
        $transaction = DB::connection('school')->table('spj_fresh_transactions')->sole();

        DB::connection('school')->table('arkas_raw_mirror_rows')
            ->where('mirror_table_id', $budgetTableId)
            ->update(['payload' => json_encode([
                'id_anggaran' => 'ANG-001',
                'tahun_anggaran' => '2026',
                'id_ref_sumber_dana' => 1,
                'is_approve' => 0,
                'is_aktif' => 0,
                'soft_delete' => 0,
                'is_revisi' => 0,
                'last_update' => '2026-01-01 08:00:00',
            ], JSON_THROW_ON_ERROR)]);

        app(SpjFreshProjectionService::class)->project($year, 1, $source);
        $transaction = DB::connection('school')->table('spj_fresh_transactions')->find($transaction->id);
        $this->assertSame('SOURCE_MISSING', $transaction->source_status);
        $this->assertNotNull($transaction->source_missing_since);
        $firstMissingSince = $transaction->source_missing_since;
        $this->assertSame(1, DB::connection('school')->table('spj_fresh_transactions')->count());

        app(SpjFreshProjectionService::class)->project($year, 1, $source);
        $this->assertSame($firstMissingSince, DB::connection('school')->table('spj_fresh_transactions')->find($transaction->id)->source_missing_since);

        DB::connection('school')->table('arkas_raw_mirror_rows')
            ->where('mirror_table_id', $budgetTableId)
            ->update(['payload' => json_encode([
                'id_anggaran' => 'ANG-001',
                'tahun_anggaran' => '2026',
                'id_ref_sumber_dana' => 1,
                'is_approve' => 1,
                'is_aktif' => 1,
                'soft_delete' => 0,
                'is_revisi' => 0,
                'last_update' => '2026-01-01 08:00:00',
            ], JSON_THROW_ON_ERROR)]);

        app(SpjFreshProjectionService::class)->project($year, 1, $source);
        $returned = DB::connection('school')->table('spj_fresh_transactions')->find($transaction->id);
        $this->assertSame('ACTIVE', $returned->source_status);
        $this->assertNull($returned->source_missing_since);
        $this->assertSame(1, DB::connection('school')->table('spj_fresh_transactions')->count());
    }

    public function test_raw_mirror_catch_up_projects_all_years_and_fund_sources_idempotently(): void
    {
        $this->prepareSchoolConnection();
        $source = new ArkasSource;
        $source->id = 7;

        DB::connection('school')->table('fund_sources')->insert([
            ['id' => 1, 'code' => 'BOS-REG', 'name' => 'BOS Reguler'],
            ['id' => 2, 'code' => 'BOS-KIN', 'name' => 'BOS Kinerja'],
        ]);
        DB::connection('school')->table('fiscal_years')->insert([
            ['id' => 11, 'year' => 2025, 'fund_source_id' => 1],
            ['id' => 12, 'year' => 2026, 'fund_source_id' => 1],
            ['id' => 13, 'year' => 2026, 'fund_source_id' => 2],
        ]);

        $anggaranTableId = $this->insertMirrorTable($source->id, 'anggaran');
        foreach ([
            ['id_anggaran' => 'ANG-2025-REG', 'tahun_anggaran' => '2025', 'id_ref_sumber_dana' => 1],
            ['id_anggaran' => 'ANG-2026-REG', 'tahun_anggaran' => '2026', 'id_ref_sumber_dana' => 1],
            ['id_anggaran' => 'ANG-2026-KIN', 'tahun_anggaran' => '2026', 'id_ref_sumber_dana' => 2],
        ] as $index => $budget) {
            $this->insertRawRow($anggaranTableId, $budget['id_anggaran'], $budget + [
                'is_approve' => 1,
                'is_aktif' => 1,
                'soft_delete' => 0,
                'is_revisi' => 0,
                'last_update' => '2026-01-01 08:00:0'.$index,
            ]);
        }

        $kasTableId = $this->insertMirrorTable($source->id, 'kas_umum');
        foreach ([
            ['id' => 'KAS-2025', 'budget' => 'ANG-2025-REG', 'date' => '2025-02-03', 'proof' => 'BPU-2025'],
            ['id' => 'KAS-2026-REG', 'budget' => 'ANG-2026-REG', 'date' => '2026-02-03', 'proof' => 'BPU-2026-REG'],
            ['id' => 'KAS-2026-KIN', 'budget' => 'ANG-2026-KIN', 'date' => '2026-03-03', 'proof' => 'BPU-2026-KIN'],
        ] as $item) {
            $this->insertRawRow($kasTableId, $item['id'], [
                'id_kas_umum' => $item['id'],
                'id_ref_bku' => 4,
                'id_anggaran' => $item['budget'],
                'tanggal_transaksi' => $item['date'],
                'no_bukti' => $item['proof'],
                'saldo' => 1000,
                'soft_delete' => 0,
            ]);
        }

        $first = app(SpjFreshProjectionService::class)->projectAllValidContexts($source);
        $second = app(SpjFreshProjectionService::class)->projectAllValidContexts($source);

        $this->assertSame(3, $first['contexts']);
        $this->assertSame(3, $first['transactions']);
        $this->assertSame(3, $first['items']);
        $this->assertSame(0, $second['transactions']);
        $this->assertSame(0, $second['items']);
        $this->assertSame([
            ['fiscal_year_id' => 11, 'fund_source_id' => 1, 'count' => 1],
            ['fiscal_year_id' => 12, 'fund_source_id' => 1, 'count' => 1],
            ['fiscal_year_id' => 13, 'fund_source_id' => 2, 'count' => 1],
        ], DB::connection('school')->table('spj_fresh_transactions')
            ->selectRaw('fiscal_year_id, fund_source_id, COUNT(*) as count')
            ->groupBy('fiscal_year_id', 'fund_source_id')
            ->orderBy('fiscal_year_id')
            ->orderBy('fund_source_id')
            ->get()
            ->map(fn (object $row): array => [
                'fiscal_year_id' => (int) $row->fiscal_year_id,
                'fund_source_id' => (int) $row->fund_source_id,
                'count' => (int) $row->count,
            ])->all());
    }

    private function prepareSchoolConnection(): void
    {
        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');
        DB::reconnect('school');

        Schema::connection('school')->create('fund_sources', function ($table): void {
            $table->unsignedInteger('id')->primary();
            $table->string('code');
            $table->string('name');
        });

        Schema::connection('school')->create('fiscal_years', function ($table): void {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('fund_source_id')->nullable();
        });

        Schema::connection('school')->create('arkas_raw_mirror_tables', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('source_id');
            $table->string('source_table');
            $table->json('schema');
            $table->string('schema_hash');
            $table->unsignedBigInteger('row_count')->default(0);
            $table->string('status')->default('ACTIVE');
            $table->timestamps();
        });

        Schema::connection('school')->create('arkas_raw_mirror_rows', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('mirror_table_id');
            $table->string('source_key');
            $table->unsignedInteger('ordinal')->default(0);
            $table->json('payload');
            $table->string('payload_hash');
            $table->timestamps();
        });

        Schema::connection('school')->create('spj_fresh_transactions', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('fiscal_year_id');
            $table->unsignedBigInteger('fund_source_id');
            $table->unsignedBigInteger('source_id');
            $table->string('source_table');
            $table->string('source_key');
            $table->unsignedBigInteger('raw_mirror_row_id')->nullable();
            $table->string('source_status')->default('ACTIVE');
            $table->boolean('requires_reconciliation')->default(false);
            $table->timestamp('source_missing_since')->nullable();
            $table->timestamps();
        });

        Schema::connection('school')->create('spj_fresh_transaction_items', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('spj_fresh_transaction_id');
            $table->string('source_table');
            $table->string('source_key');
            $table->unsignedBigInteger('raw_mirror_row_id')->nullable();
            $table->text('item_description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    private function insertMirrorTable(int $sourceId, string $table): int
    {
        return DB::connection('school')->table('arkas_raw_mirror_tables')->insertGetId([
            'source_id' => $sourceId,
            'source_table' => $table,
            'schema' => '[]',
            'schema_hash' => hash('sha256', '[]'),
            'row_count' => 0,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function insertRawRow(int $mirrorTableId, string $sourceKey, array $payload): int
    {
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return DB::connection('school')->table('arkas_raw_mirror_rows')->insertGetId([
            'mirror_table_id' => $mirrorTableId,
            'source_key' => $sourceKey,
            'ordinal' => 0,
            'payload' => $encoded,
            'payload_hash' => hash('sha256', $encoded),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
