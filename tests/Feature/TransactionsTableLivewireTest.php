<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureActiveFiscalYear;
use App\Http\Middleware\EnsureActiveSchool;
use App\Livewire\TransactionsTable;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\Transaction;
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

    public function test_transaction_spj_data_can_be_edited_from_livewire_modal(): void
    {
        $this->prepareSchoolConnection();

        $fundSource = FundSource::on('school')->create(['code' => 'BOS', 'name' => 'BOS Reguler']);
        $year = FiscalYear::on('school')->create([
            'year' => 2026,
            'fund_source' => 'BOS Reguler',
            'fund_source_id' => $fundSource->id,
            'is_active' => true,
        ]);
        $transaction = Transaction::query()->create([
            'fiscal_year_id' => $year->id,
            'fund_source_id' => $fundSource->id,
            'no_bukti' => 'BKU-001',
            'transaction_date' => '2026-01-10',
            'description' => 'Uraian dari ARKAS',
            'gross_amount' => 100000,
            'tax_total' => 0,
            'net_amount' => 100000,
            'is_siplah' => false,
            'status' => 'DITETAPKAN',
        ]);

        $this->actingAs(User::factory()->create(['role' => 'ADMIN']))
            ->withSession([
                'active_school_id' => 1,
                'active_fiscal_year_id' => $year->id,
                'active_fund_source_id' => $fundSource->id,
            ]);

        Livewire::test(TransactionsTable::class)
            ->assertSee('BKU-001')
            ->call('edit', $transaction->id)
            ->assertSet('showEditor', true)
            ->set('form.spj_category', 'BARANG')
            ->set('form.payment_description', 'Pembayaran alat tulis kantor')
            ->set('form.payment_method', 'transfer_bank')
            ->call('save')
            ->assertSet('showEditor', false);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'spj_category' => 'BARANG',
            'payment_description' => 'Pembayaran alat tulis kantor',
            'payment_method' => 'transfer_bank',
        ], 'school');
    }

    public function test_second_page_from_url_displays_transaction_rows(): void
    {
        $this->prepareSchoolConnection();

        $fundSource = FundSource::on('school')->create(['code' => 'BOS', 'name' => 'BOS Reguler']);
        $year = FiscalYear::on('school')->create([
            'year' => 2026,
            'fund_source' => 'BOS Reguler',
            'fund_source_id' => $fundSource->id,
            'is_active' => true,
        ]);
        foreach (range(1, 31) as $number) {
            Transaction::query()->create([
                'fiscal_year_id' => $year->id,
                'fund_source_id' => $fundSource->id,
                'no_bukti' => sprintf('BKU-%03d', $number),
                'transaction_date' => '2026-01-10',
                'description' => 'Transaksi '.$number,
                'gross_amount' => 100000,
                'tax_total' => 0,
                'net_amount' => 100000,
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
            ->assertSee('BKU-016')
            ->assertSee('BKU-030')
            ->assertDontSee('BKU-001');

        Livewire::withQueryParams(['perPage' => 100])
            ->test(TransactionsTable::class)
            ->assertSee('BKU-001')
            ->assertSee('BKU-031');
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
    }
}
