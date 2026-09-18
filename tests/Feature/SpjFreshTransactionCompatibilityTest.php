<?php

namespace Tests\Feature;

use App\Models\SpjFreshTransaction;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SpjFreshTransactionCompatibilityTest extends TestCase
{
    protected function tearDown(): void
    {
        DB::purge('school');

        parent::tearDown();
    }

    public function test_grouped_source_key_resolves_legacy_overlay_and_package(): void
    {
        $this->prepareSchoolConnection();

        $sourceKey = hash('sha256', 'KAS-001|KAS-002');
        $transactionId = DB::connection('school')->table('transactions')->insertGetId([
            'fiscal_year_id' => 11,
            'fund_source_id' => 1,
            'id_kas_umum' => 'KAS-001',
            'source_key' => $sourceKey,
            'no_bukti' => 'BPU-001',
            'payment_description' => 'Uraian operator lama',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('school')->table('transaction_items')->insert([
            'transaction_id' => $transactionId,
            'source_item_id' => 'KAS-002',
            'item_description' => 'Uraian item operator lama',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('school')->table('spj_packages')->insert([
            'transaction_id' => $transactionId,
            'status' => 'NUMBERED',
            'document_number' => '001/SPJ/2026',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $transaction = Transaction::query()
            ->with(['items', 'spjPackage'])
            ->forSourceIdentifier($sourceKey)
            ->sole();

        $this->assertSame($transactionId, $transaction->id);
        $this->assertSame('Uraian operator lama', $transaction->payment_description);
        $this->assertSame('Uraian item operator lama', $transaction->items->sole()->item_description);
        $this->assertSame('NUMBERED', $transaction->spjPackage?->status);
        $this->assertSame('001/SPJ/2026', $transaction->spjPackage?->document_number);
    }

    public function test_grouped_fresh_totals_sum_all_items_and_only_tax_receipts_from_same_source(): void
    {
        $this->prepareSchoolConnection();

        $mirrorTableId = $this->insertMirrorTable(7);
        $otherSourceTableId = $this->insertMirrorTable(8);

        $itemOneId = $this->insertRawRow($mirrorTableId, 'KAS-001', [
            'id_kas_umum' => 'KAS-001',
            'id_ref_bku' => 4,
            'saldo' => 40000,
            'soft_delete' => 0,
        ]);
        $itemTwoId = $this->insertRawRow($mirrorTableId, 'KAS-002', [
            'id_kas_umum' => 'KAS-002',
            'id_ref_bku' => 15,
            'saldo' => 60000,
            'soft_delete' => 0,
        ]);

        $this->insertRawRow($mirrorTableId, 'PBT-001', [
            'id_kas_umum' => 'PBT-001',
            'parent_id_kas_umum' => 'KAS-001',
            'id_ref_bku' => 10,
            'saldo' => 11000,
            'is_ppn' => 1,
            'soft_delete' => 0,
        ]);
        $this->insertRawRow($mirrorTableId, 'PBS-001', [
            'id_kas_umum' => 'PBS-001',
            'parent_id_kas_umum' => 'KAS-001',
            'id_ref_bku' => 11,
            'saldo' => 11000,
            'is_ppn' => 1,
            'soft_delete' => 0,
        ]);
        $this->insertRawRow($mirrorTableId, 'PBT-002', [
            'id_kas_umum' => 'PBT-002',
            'parent_id_kas_umum' => 'KAS-002',
            'id_ref_bku' => 10,
            'saldo' => 5000,
            'is_pph_22' => 1,
            'soft_delete' => 0,
        ]);
        $this->insertRawRow($mirrorTableId, 'PBS-002', [
            'id_kas_umum' => 'PBS-002',
            'parent_id_kas_umum' => 'KAS-002',
            'id_ref_bku' => 11,
            'saldo' => 5000,
            'is_pph_22' => 1,
            'soft_delete' => 0,
        ]);
        $this->insertRawRow($otherSourceTableId, 'PBT-OTHER', [
            'id_kas_umum' => 'PBT-OTHER',
            'parent_id_kas_umum' => 'KAS-001',
            'id_ref_bku' => 10,
            'saldo' => 99999,
            'is_ppn' => 1,
            'soft_delete' => 0,
        ]);

        $sourceKey = hash('sha256', 'KAS-001|KAS-002');
        $freshTransactionId = DB::connection('school')->table('spj_fresh_transactions')->insertGetId([
            'fiscal_year_id' => 11,
            'fund_source_id' => 1,
            'source_id' => 7,
            'source_table' => 'kas_umum',
            'source_key' => $sourceKey,
            'raw_mirror_row_id' => $itemOneId,
            'source_status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('school')->table('spj_fresh_transaction_items')->insert([
            [
                'spj_fresh_transaction_id' => $freshTransactionId,
                'source_table' => 'kas_umum',
                'source_key' => 'KAS-001',
                'raw_mirror_row_id' => $itemOneId,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'spj_fresh_transaction_id' => $freshTransactionId,
                'source_table' => 'kas_umum',
                'source_key' => 'KAS-002',
                'raw_mirror_row_id' => $itemTwoId,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $transaction = SpjFreshTransaction::query()
            ->with(['items.rawMirrorRow'])
            ->findOrFail($freshTransactionId);

        $this->assertSame(100000.0, $transaction->gross_amount);
        $this->assertSame(16000.0, $transaction->tax_total);
        $this->assertSame(84000.0, $transaction->net_amount);
    }

    private function prepareSchoolConnection(): void
    {
        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');
        DB::reconnect('school');

        Schema::connection('school')->create('transactions', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('fiscal_year_id');
            $table->unsignedBigInteger('fund_source_id')->nullable();
            $table->string('id_kas_umum')->nullable();
            $table->string('source_key')->nullable();
            $table->string('no_bukti')->nullable();
            $table->text('payment_description')->nullable();
            $table->timestamps();
        });

        Schema::connection('school')->create('transaction_items', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('transaction_id');
            $table->string('source_item_id')->nullable();
            $table->text('item_description')->nullable();
            $table->timestamps();
        });

        Schema::connection('school')->create('spj_packages', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('transaction_id');
            $table->string('status')->default('DRAFT');
            $table->string('document_number')->nullable();
            $table->timestamps();
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

    private function insertMirrorTable(int $sourceId): int
    {
        return DB::connection('school')->table('arkas_raw_mirror_tables')->insertGetId([
            'source_id' => $sourceId,
            'source_table' => 'kas_umum',
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
