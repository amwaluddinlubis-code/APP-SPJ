<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureActiveFiscalYear;
use App\Http\Middleware\EnsureActiveSchool;
use App\Livewire\TransactionsTable;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\SpjFreshTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;
use Tests\TestCase;

class TransactionsTableLivewireTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        DB::purge('school');

        parent::tearDown();
    }

    public function test_school_context_middleware_is_persistent_for_livewire_updates(): void
    {
        $middleware = app(PersistentMiddleware::class)->getPersistentMiddleware();

        $this->assertContains(EnsureActiveSchool::class, $middleware);
        $this->assertContains(EnsureActiveFiscalYear::class, $middleware);
    }

    public function test_transaction_actions_are_navigation_only_and_livewire_has_no_spj_editor_write_path(): void
    {
        $this->prepareSchoolConnection();

        $fundSource = FundSource::on('school')->create(['id' => 1, 'code' => 'BOS', 'name' => 'BOS Reguler']);
        $year = FiscalYear::on('school')->create([
            'year' => 2026,
            'fund_source' => 'BOS Reguler',
            'fund_source_id' => $fundSource->id,
            'is_active' => true,
        ]);
        $mirrorTableId = DB::connection('school')->table('arkas_raw_mirror_tables')->insertGetId([
            'source_id' => 1, 'source_table' => 'kas_umum', 'schema' => '{}', 'schema_hash' => str_repeat('a', 64),
            'row_count' => 1, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::connection('school')->table('arkas_raw_mirror_rows')->insert([
            'mirror_table_id' => $mirrorTableId, 'source_key' => 'BPU-001', 'ordinal' => 0,
            'payload' => json_encode(['no_bukti' => 'BPU-001', 'tanggal_transaksi' => '2026-01-10', 'uraian' => 'Uraian dari ARKAS', 'jumlah' => 100000]),
            'payload_hash' => str_repeat('b', 64), 'created_at' => now(), 'updated_at' => now(),
        ]);
        SpjFreshTransaction::query()->create([
            'fiscal_year_id' => $year->id,
            'fund_source_id' => $fundSource->id,
            'source_id' => 1,
            'source_table' => 'kas_umum',
            'source_key' => 'BPU-001',
            'raw_mirror_row_id' => 1,
            'status' => 'DITETAPKAN',
        ]);

        $this->actingAs(User::factory()->create(['role' => 'ADMIN']))
            ->withSession([
                'active_school_id' => 1,
                'active_fiscal_year_id' => $year->id,
                'active_fund_source_id' => $fundSource->id,
            ]);

        Livewire::test(TransactionsTable::class)
            ->assertSee('BPU-001')
            ->assertSee('Paket SPJ')
            ->assertSee('Detail');

        $this->assertFalse(method_exists(TransactionsTable::class, 'edit'));
        $this->assertFalse(method_exists(TransactionsTable::class, 'save'));
        $this->assertFalse(method_exists(TransactionsTable::class, 'closeEditor'));
    }

    public function test_second_page_from_url_displays_transaction_rows(): void
    {
        $this->prepareSchoolConnection();

        $fundSource = FundSource::on('school')->create(['id' => 1, 'code' => 'BOS', 'name' => 'BOS Reguler']);
        $year = FiscalYear::on('school')->create([
            'year' => 2026,
            'fund_source' => 'BOS Reguler',
            'fund_source_id' => $fundSource->id,
            'is_active' => true,
        ]);
        $mirrorTableId = DB::connection('school')->table('arkas_raw_mirror_tables')->insertGetId([
            'source_id' => 1, 'source_table' => 'kas_umum', 'schema' => '{}', 'schema_hash' => str_repeat('a', 64),
            'row_count' => 31, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (range(1, 31) as $number) {
            $rawRowId = DB::connection('school')->table('arkas_raw_mirror_rows')->insertGetId([
                'mirror_table_id' => $mirrorTableId, 'source_key' => sprintf('BPU-%03d', $number), 'ordinal' => $number,
            'payload' => json_encode(['no_bukti' => sprintf('BPU-%03d', $number), 'tanggal_transaksi' => '2026-01-10', 'uraian' => 'Transaksi '.$number, 'jumlah' => 100000]),
                'payload_hash' => str_repeat((string) (($number % 8) + 1), 64), 'created_at' => now(), 'updated_at' => now(),
            ]);
            SpjFreshTransaction::query()->create([
                'fiscal_year_id' => $year->id,
                'fund_source_id' => $fundSource->id,
                'source_id' => 1,
                'source_table' => 'kas_umum',
                'source_key' => sprintf('BPU-%03d', $number),
                'raw_mirror_row_id' => $rawRowId,
                'status' => 'DITETAPKAN',
            ]);
        }

        $this->actingAs(User::factory()->create(['role' => 'ADMIN']))
            ->withSession([
                'active_school_id' => 1,
                'active_fiscal_year_id' => $year->id,
                'active_fund_source_id' => $fundSource->id,
            ]);

        Livewire::withQueryParams(['page' => 2])
            ->test(TransactionsTable::class)
            ->assertSee('BPU-016')
            ->assertSee('BPU-030')
            ->assertDontSee('BPU-001');

        Livewire::withQueryParams(['perPage' => 100])
            ->test(TransactionsTable::class)
            ->assertSee('BPU-001')
            ->assertSee('BPU-031');
    }

    private function prepareSchoolConnection(): void
    {
        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');

        Schema::connection('school')->create('fund_sources', function ($table): void {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection('school')->create('fiscal_years', function ($table): void {
            $table->id();
            $table->integer('year');
            $table->string('fund_source')->nullable();
            $table->foreignId('fund_source_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection('school')->create('transactions', function ($table): void {
            $table->id();
            $table->foreignId('fiscal_year_id');
            $table->foreignId('fund_source_id')->nullable();
            $table->string('no_bukti')->nullable();
            $table->date('transaction_date')->nullable();
            $table->text('description')->nullable();
            $table->text('payment_description')->nullable();
            $table->string('payment_method', 40)->nullable();
            $table->string('payment_reference', 160)->nullable();
            $table->string('activity_code')->nullable();
            $table->string('account_code')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('receipt_recipient_name')->nullable();
            $table->decimal('gross_amount', 18, 2)->default(0);
            $table->decimal('tax_total', 18, 2)->default(0);
            $table->decimal('net_amount', 18, 2)->default(0);
            $table->boolean('is_siplah')->default(false);
            $table->string('source_status', 30)->default('ACTIVE');
            $table->boolean('requires_reconciliation')->default(false);
            $table->string('status')->nullable();
            $table->string('spj_category')->nullable();
            $table->timestamps();
        });

        Schema::connection('school')->create('transaction_items', function ($table): void {
            $table->id();
            $table->foreignId('transaction_id');
            $table->text('description')->nullable();
            $table->text('item_description')->nullable();
            $table->decimal('amount', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::connection('school')->create('spj_packages', function ($table): void {
            $table->id();
            $table->foreignId('transaction_id');
            $table->string('status')->default('DRAFT');
            $table->string('document_number')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
        });

        Schema::connection('school')->create('spj_goods', function ($table): void {
            $table->id();
            $table->foreignId('transaction_item_id');
            $table->string('order_number')->nullable();
            $table->date('order_date')->nullable();
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
            $table->foreignId('fiscal_year_id');
            $table->unsignedBigInteger('fund_source_id');
            $table->unsignedBigInteger('source_id');
            $table->string('source_table');
            $table->string('source_key');
            $table->unsignedBigInteger('raw_mirror_row_id')->nullable();
            $table->string('spj_category')->nullable();
            $table->text('payment_description')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('receipt_recipient_name')->nullable();
            $table->string('source_status')->default('ACTIVE');
            $table->boolean('requires_reconciliation')->default(false);
            $table->timestamp('source_missing_since')->nullable();
            $table->timestamps();
        });
        Schema::connection('school')->create('spj_fresh_transaction_items', function ($table): void {
            $table->id();
            $table->foreignId('spj_fresh_transaction_id');
            $table->string('source_table');
            $table->string('source_key');
            $table->unsignedBigInteger('raw_mirror_row_id')->nullable();
            $table->text('item_description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::connection('school')->create('spj_fresh_packages', function ($table): void {
            $table->id();
            $table->foreignId('spj_fresh_transaction_id');
            $table->string('status')->default('DRAFT');
            $table->string('document_number')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
        });
    }
}
