<?php

namespace Tests\Feature;

use App\Livewire\SpjMonitoringList;
use App\Livewire\SpjPackageList;
use App\Livewire\SpjPreparationFilter;
use App\Livewire\SpjReportFilter;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class SpjTabFiltersLivewireTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        DB::purge('school');

        parent::tearDown();
    }

    public function test_preparation_filter_updates_rows_without_reload(): void
    {
        $this->seedWorkspace();

        Livewire::test(SpjPreparationFilter::class)
            ->assertSee('BKU-001')
            ->assertSee('BKU-002')
            ->assertSee('BKU-003')
            ->set('month', 2)
            ->assertSee('BKU-002')
            ->assertDontSee('BKU-001')
            ->assertDontSee('BKU-003')
            ->set('month', null)
            ->set('spj_category', 'JASA_LAINNYA')
            ->assertSee('BKU-002')
            ->assertDontSee('BKU-001');
    }

    public function test_preparation_queue_state_filters_without_reload(): void
    {
        $this->seedWorkspace();

        Livewire::test(SpjPreparationFilter::class)
            ->call('setQueueState', 'draft')
            ->assertSee('BKU-002')
            ->assertDontSee('BKU-001')
            ->assertDontSee('BKU-003')
            ->call('setQueueState', 'numbered')
            ->assertSee('BKU-003')
            ->assertDontSee('BKU-002')
            ->call('resetFilters')
            ->assertSee('BKU-001')
            ->assertSee('BKU-002')
            ->assertSee('BKU-003');
    }

    public function test_report_filter_and_summary_update_without_reload(): void
    {
        $this->seedWorkspace();

        Livewire::test(SpjReportFilter::class)
            ->assertSee('0001/SPJ/2026')
            ->assertSee('Rp 250.000')
            ->call('setMode', 'bulan')
            ->set('periode', 3)
            ->assertSee('0001/SPJ/2026')
            ->set('periode', 1)
            ->assertDontSee('0001/SPJ/2026')
            ->assertSee('Belum ada riwayat paket SPJ untuk filter ini.');
    }

    public function test_report_mount_supports_legacy_bookmark_params(): void
    {
        $this->seedWorkspace();

        Livewire::withQueryParams(['month' => 3])
            ->test(SpjReportFilter::class)
            ->assertSet('mode', 'bulan')
            ->assertSet('periode', 3)
            ->assertSee('0001/SPJ/2026');
    }

    public function test_package_list_paginates_without_reload(): void
    {
        $this->seedWorkspace(extraPackages: 12);

        Livewire::withQueryParams(['package_perPage' => 10])
            ->test(SpjPackageList::class)
            ->assertSee('BKU-003')
            ->call('gotoPage', 2, 'package_page')
            ->assertDontSee('BKU-003');
    }

    public function test_package_list_filters_by_status_category_and_search_without_reload(): void
    {
        $this->seedWorkspace();

        Livewire::test(SpjPackageList::class)
            ->set('status', 'NUMBERED')
            ->assertSee('BKU-003')
            ->assertDontSee('BKU-002')
            ->set('status', '')
            ->set('category', 'JASA_LAINNYA')
            ->assertSee('BKU-002')
            ->assertDontSee('BKU-003')
            ->set('category', '')
            ->set('search', 'BKU-003')
            ->assertSee('BKU-003')
            ->assertDontSee('BKU-002');
    }

    public function test_monitoring_list_shows_pending_without_reload(): void
    {
        $this->seedWorkspace();

        Livewire::test(SpjMonitoringList::class)
            ->assertSee('BKU-001')
            ->assertSee('BKU-002')
            ->assertDontSee('BKU-003')
            ->set('pendingPerPage', 25)
            ->assertSee('BKU-001');
    }

    private function seedWorkspace(int $extraPackages = 0): void
    {
        $this->prepareSchoolConnection();

        $fundSource = FundSource::on('school')->create(['code' => 'BOS', 'name' => 'BOS Reguler']);
        $year = FiscalYear::on('school')->create([
            'year' => 2026,
            'fund_source' => 'BOS Reguler',
            'fund_source_id' => $fundSource->id,
            'is_active' => true,
        ]);

        $january = $this->makeTransaction($year, $fundSource, 'BKU-001', '2026-01-10', 'BARANG', 100000);
        $february = $this->makeTransaction($year, $fundSource, 'BKU-002', '2026-02-10', 'JASA_LAINNYA', 150000);
        $march = $this->makeTransaction($year, $fundSource, 'BKU-003', '2026-03-10', 'BARANG', 250000);

        $february->spjPackage()->create(['status' => 'DRAFT']);
        $march->spjPackage()->create(['status' => 'NUMBERED', 'document_number' => '0001/SPJ/2026']);

        foreach (range(1, $extraPackages) as $number) {
            $transaction = $this->makeTransaction(
                $year,
                $fundSource,
                sprintf('BKU-X%03d', $number),
                '2026-04-10',
                'BARANG',
                10000
            );
            $transaction->spjPackage()->create(['status' => 'DRAFT']);
        }

        $this->actingAs(User::factory()->create(['role' => 'ADMIN']))
            ->withSession([
                'active_school_id' => 1,
                'active_fiscal_year_id' => $year->id,
                'active_fund_source_id' => $fundSource->id,
            ]);
    }

    private function makeTransaction(FiscalYear $year, FundSource $fundSource, string $noBukti, string $date, string $category, int $gross): Transaction
    {
        $transaction = Transaction::query()->create([
            'fiscal_year_id' => $year->id,
            'fund_source_id' => $fundSource->id,
            'no_bukti' => $noBukti,
            'transaction_date' => $date,
            'description' => 'Uraian '.$noBukti,
            'gross_amount' => $gross,
            'tax_total' => 0,
            'net_amount' => $gross,
            'status' => 'DITETAPKAN',
            'spj_category' => $category,
        ]);
        $transaction->items()->create(['description' => 'Rincian '.$noBukti]);

        return $transaction;
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
            $table->string('activity_code')->nullable();
            $table->string('activity_name')->nullable();
            $table->string('account_code')->nullable();
            $table->string('account_name')->nullable();
            $table->decimal('gross_amount', 18, 2)->default(0);
            $table->decimal('tax_total', 18, 2)->default(0);
            $table->decimal('net_amount', 18, 2)->default(0);
            $table->decimal('ppn', 18, 2)->default(0);
            $table->decimal('pph21', 18, 2)->default(0);
            $table->decimal('pph22', 18, 2)->default(0);
            $table->decimal('pph23', 18, 2)->default(0);
            $table->decimal('pph4', 18, 2)->default(0);
            $table->decimal('sspd', 18, 2)->default(0);
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

        Schema::connection('school')->create('spj_documents', function ($table): void {
            $table->id();
            $table->foreignId('spj_package_id');
            $table->string('document_type', 40);
            $table->string('scope_key', 80)->default('MAIN');
            $table->string('document_number')->nullable();
            $table->unsignedInteger('sequence_number')->nullable();
            $table->string('status', 30)->default('DRAFT');
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
        });
    }
}
