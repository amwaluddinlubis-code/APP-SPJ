<?php

namespace Tests\Feature;

use App\Services\TaxFilterService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class TaxFilterFreshProjectionTest extends TestCase
{
    protected function tearDown(): void
    {
        config()->set('spj.v2_read_path', 'legacy');
        DB::purge('school');

        parent::tearDown();
    }

    public function test_tax_page_reads_fresh_projection_when_legacy_transactions_are_empty(): void
    {
        $this->prepareSchoolConnection();
        config()->set('spj.v2_read_path', 'legacy');
        session([
            'active_fiscal_year_id' => 1,
            'active_fund_source_id' => 1,
        ]);

        $data = app(TaxFilterService::class)->taxData('', null, null, null, 15);

        $this->assertSame('fresh', $data['read_path']);
        $this->assertSame(1, (int) $data['summary']->count);
        $this->assertSame(1250.0, (float) $data['summary']->ppn);
        $this->assertSame(1250.0, (float) $data['summary']->total);
        $this->assertSame('BKU-FRESH-001', $data['transactions']->first()->no_bukti);
        $this->assertSame('fresh', $data['transactions']->first()->getAttribute('read_context_path'));
        $this->assertTrue((bool) $data['transactions']->first()->is_siplah);

        $filtered = app(TaxFilterService::class)->taxData('', 2, null, null, 15);

        $this->assertSame(0, (int) $filtered['filteredSummary']->count);

        $siplah = app(TaxFilterService::class)->taxData('', null, null, null, 15, null, 'siplah');
        $this->assertSame(1, (int) $siplah['filteredSummary']->count);

        $nonSiplah = app(TaxFilterService::class)->taxData('', null, null, null, 15, null, 'non_siplah');
        $this->assertSame(0, (int) $nonSiplah['filteredSummary']->count);
    }

    private function prepareSchoolConnection(): void
    {
        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');
        DB::reconnect('school');

        Schema::connection('school')->create('fiscal_years', function ($table): void {
            $table->id();
            $table->integer('year');
            $table->unsignedBigInteger('fund_source_id')->nullable();
            $table->timestamps();
        });

        Schema::connection('school')->create('transactions', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('fiscal_year_id');
            $table->unsignedBigInteger('fund_source_id')->nullable();
            $table->decimal('tax_total', 18, 2)->default(0);
        });

        Schema::connection('school')->create('arkas_raw_mirror_tables', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('source_id');
            $table->string('source_table');
            $table->string('status')->default('ACTIVE');
        });

        Schema::connection('school')->create('arkas_raw_mirror_rows', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('mirror_table_id');
            $table->string('source_key');
            $table->json('payload');
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
        });

        Schema::connection('school')->create('spj_fresh_transaction_items', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('spj_fresh_transaction_id');
            $table->string('source_table');
            $table->string('source_key');
            $table->unsignedBigInteger('raw_mirror_row_id')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
        });

        DB::connection('school')->table('fiscal_years')->insert([
            'id' => 1,
            'year' => 2026,
            'fund_source_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mirrorTableId = DB::connection('school')->table('arkas_raw_mirror_tables')->insertGetId([
            'source_id' => 1,
            'source_table' => 'kas_umum',
            'status' => 'ACTIVE',
        ]);
        $itemRowId = DB::connection('school')->table('arkas_raw_mirror_rows')->insertGetId([
            'mirror_table_id' => $mirrorTableId,
            'source_key' => 'ITEM-001',
            'payload' => json_encode([
                'id_kas_umum' => 'ITEM-001',
                'id_kas_nota' => 'NOTA-001',
                'no_bukti' => 'BKU-FRESH-001',
                'tanggal_transaksi' => '2026-01-15',
                'uraian' => 'Belanja dari proyeksi baru',
                'nama_penerima' => 'Penerima Fresh',
                'id_ref_bku' => '15',
                'saldo' => '100000',
            ], JSON_THROW_ON_ERROR),
        ]);
        DB::connection('school')->table('arkas_raw_mirror_rows')->insert([
            'mirror_table_id' => $mirrorTableId,
            'source_key' => 'TAX-001',
            'payload' => json_encode([
                'id_kas_umum' => 'TAX-001',
                'parent_id_kas_umum' => 'ITEM-001',
                'id_ref_bku' => '30',
                'is_ppn' => '1',
                'saldo' => '1250',
                'soft_delete' => '0',
            ], JSON_THROW_ON_ERROR),
        ]);
        $notaTableId = DB::connection('school')->table('arkas_raw_mirror_tables')->insertGetId([
            'source_id' => 1,
            'source_table' => 'kas_umum_nota',
            'status' => 'ACTIVE',
        ]);
        DB::connection('school')->table('arkas_raw_mirror_rows')->insert([
            'mirror_table_id' => $notaTableId,
            'source_key' => 'NOTA-001',
            'payload' => json_encode([
                'id_kas_nota' => 'NOTA-001',
                'is_beli_di_siplah' => '1',
            ], JSON_THROW_ON_ERROR),
        ]);

        $freshId = DB::connection('school')->table('spj_fresh_transactions')->insertGetId([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'source_id' => 1,
            'source_table' => 'kas_umum',
            'source_key' => 'fresh-source-key',
            'raw_mirror_row_id' => $itemRowId,
            'source_status' => 'ACTIVE',
        ]);
        DB::connection('school')->table('spj_fresh_transaction_items')->insert([
            'spj_fresh_transaction_id' => $freshId,
            'source_table' => 'kas_umum',
            'source_key' => 'ITEM-001',
            'raw_mirror_row_id' => $itemRowId,
            'sort_order' => 0,
        ]);
    }
}
