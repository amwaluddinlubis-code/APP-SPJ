<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\UseCases\Spj\SpjPackageUseCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SpjWorkspaceMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');
        Artisan::call('migrate', ['--database' => 'school', '--path' => 'database/migrations/school', '--force' => true]);
        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        FiscalYear::query()->create(['id' => 1, 'year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
        $this->withoutMiddleware()->withSession(['active_fiscal_year_id' => 1, 'active_fund_source_id' => 1]);
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    public function test_gateway_rejects_transactions_without_items(): void
    {
        $transaction = $this->transaction(false);
        $this->get(route('transactions.prepare-spj', $transaction->id))
            ->assertRedirect(route('transactions.show', $transaction->id))->assertSessionHas('error');
        $this->assertSame(0, SpjPackage::query()->count());
    }

    public function test_gateway_rejects_unsaved_descriptions_even_when_package_exists(): void
    {
        $transaction = $this->transaction();
        $transaction->items()->update(['item_description' => null]);
        $this->get(route('transactions.prepare-spj', $transaction->id))
            ->assertRedirect(route('transactions.show', $transaction->id).'#rincian-transaksi')
            ->assertSessionHas('error');
        $this->assertSame(0, SpjPackage::query()->count());

        $package = $transaction->spjPackage()->create(['status' => 'DRAFT', 'quarter_code' => 'TW-1', 'semester_code' => 'SEM-I']);
        $this->get(route('transactions.prepare-spj', $transaction->id))->assertSessionHas('error');
        $this->get(route('spj.index', ['tab' => 'paket', 'package_id' => $package->id]))
            ->assertRedirect(route('transactions.show', $transaction->id).'#rincian-transaksi');
        $this->assertSame(1, SpjPackage::query()->count());
    }

    public function test_saved_item_descriptions_allow_an_idempotent_draft_gateway(): void
    {
        $transaction = $this->transaction();
        $item = $transaction->items()->firstOrFail();
        $item->update(['item_description' => null]);
        $this->put(route('transactions.spj-descriptions.update', $transaction->id), [
            'items' => [['id' => $item->id, 'item_description' => 'Uraian tersimpan']],
            'payment_description' => 'Tidak boleh ditulis dari transaksi',
            'spj_category' => 'SPPD',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Uraian tersimpan', $item->fresh()->item_description);
        $this->assertSame('BARANG', $transaction->fresh()->spj_category);
        $this->assertNull($transaction->fresh()->payment_description);
        $package = $this->openDraft($transaction);
        $this->get(route('transactions.prepare-spj', $transaction->id))
            ->assertRedirect(route('spj.index', ['tab' => 'paket', 'package_id' => $package->id]));
        $this->assertSame(1, SpjPackage::query()->count());
    }

    public function test_description_update_rejects_foreign_items_before_writing_any_item(): void
    {
        $transaction = $this->transaction();
        $item = $transaction->items()->firstOrFail();
        $other = $this->transaction()->items()->firstOrFail();
        $this->put(route('transactions.spj-descriptions.update', $transaction->id), [
            'items' => [
                ['id' => $item->id, 'item_description' => 'Jangan tersimpan sebagian'],
                ['id' => $other->id, 'item_description' => 'Item asing'],
            ],
        ])->assertStatus(422);
        $this->assertSame('Kertas untuk SPJ', $item->fresh()->item_description);
    }

    public function test_package_update_ignores_source_values_and_item_descriptions_in_manual_requests(): void
    {
        $transaction = $this->transaction();
        $package = $this->openDraft($transaction);
        $item = $transaction->items()->firstOrFail();
        $protected = ['gross_amount', 'ppn', 'pph21', 'pph22', 'pph23', 'pph4', 'sspd', 'tax_total', 'net_amount',
            'ppn_rate', 'pph21_rate', 'pph22_rate', 'pph23_rate', 'pph4_rate', 'sspd_rate'];
        $source = $transaction->fresh()->only($protected);
        $itemSource = $item->getAttributes();
        $this->put(route('spj.update', $package->id), [
            ...array_fill_keys($protected, 999999),
            'spj_category' => 'BARANG', 'payment_description' => 'Pembelian dokumen',
            'payment_method' => 'tunai', 'vendor_name' => 'Toko Uji',
            'items' => [['id' => $item->id, 'item_description' => 'Tidak boleh berubah', 'quantity' => 99, 'unit' => 'paket', 'unit_price' => 1, 'amount' => 1]],
            'item_description' => 'Tidak boleh berubah',
        ])->assertSessionHasNoErrors()->assertSessionMissing('error');
        $this->assertSame($source, $transaction->fresh()->only($protected));
        $this->assertSame($itemSource, $item->fresh()->getAttributes());
        $this->assertSame('Toko Uji', $transaction->fresh()->vendor_name);
    }

    public function test_wrong_fund_context_cannot_open_update_or_switch_package(): void
    {
        $transaction = $this->transaction();
        $package = $this->openDraft($transaction);
        $this->withSession(['active_fund_source_id' => 2]);
        $this->get(route('transactions.prepare-spj', $transaction->id))->assertSessionHas('error');
        $this->put(route('spj.update', $package->id), ['vendor_name' => 'Asing'])->assertSessionHas('error');
        $this->putJson(route('spj.update', $package->id), ['category_switch' => 1, 'spj_category' => 'SPPD'])->assertNotFound();
        $this->assertNull($transaction->fresh()->vendor_name);
        $this->assertSame('BARANG', $transaction->fresh()->spj_category);
    }

    public function test_numbered_and_final_packages_keep_all_normal_edit_paths_locked(): void
    {
        $transaction = $this->transaction();
        $package = $this->openDraft($transaction);
        foreach (['NUMBERED', 'FINAL'] as $status) {
            $package->update(['status' => $status]);
            $this->put(route('spj.update', $package->id), ['vendor_name' => 'Ditolak'])->assertSessionHas('error');
            $this->putJson(route('spj.update', $package->id), ['category_switch' => 1, 'spj_category' => 'SPPD'])->assertUnprocessable();
            $this->put(route('transactions.spj-descriptions.update', $transaction->id), [
                'items' => [['id' => $transaction->items()->first()->id, 'item_description' => 'Ditolak']],
            ])->assertSessionHas('error');
        }
        $this->assertNull($transaction->fresh()->vendor_name);
        $this->assertSame('Kertas untuk SPJ', $transaction->items()->first()->item_description);
    }

    public function test_legacy_routes_and_use_case_are_retired(): void
    {
        $this->assertFalse(Route::has('spj.prepare'));
        $this->assertFalse(Route::has('transactions.manual-description.update'));
        $this->post('/spj/1/siapkan', ['payment_description' => 'Legacy'])->assertNotFound();
        $this->put('/transaksi/1/uraian-manual', ['payment_description' => 'Legacy'])->assertNotFound();
        $this->assertFalse(class_exists(SpjPackageUseCase::class));
    }

    #[DataProvider('categories')]
    public function test_each_category_can_be_selected_saved_and_rendered_in_the_package(string $category): void
    {
        $transaction = $this->transaction();
        $package = $this->openDraft($transaction);
        $this->putJson(route('spj.update', $package->id), [
            'category_switch' => 1, 'spj_category' => $category,
        ])->assertOk()->assertJsonPath('spj_category', $category)->assertJsonPath('package_id', $package->id);

        $payload = [
            'spj_category' => $category, 'payment_description' => 'Dokumen '.$category,
            'payment_method' => 'tunai', 'payment_reference' => 'REF-001',
            'receipt_recipient_name' => 'Penerima Uji', 'vendor_name' => 'Penyedia Uji',
            ...match ($category) {
                'BARANG' => ['order_date' => '2026-01-10', 'bap_date' => '2026-01-11', 'bast_date' => '2026-01-12'],
                'KONSUMSI' => ['event_name' => 'Rapat', 'event_location' => 'Sekolah', 'event_date' => '2026-01-14', 'participant_count' => 1,
                    'participants' => [['name' => 'Peserta', 'position' => 'Guru', 'portions' => 1]]],
                'PEMELIHARAAN' => ['work_description' => 'Perbaikan', 'work_location' => 'Sekolah',
                    'workers' => [['name' => 'Pekerja', 'work_days' => 1, 'daily_rate' => 1000]]],
                'SPPD' => ['travels' => [
                    ['traveler_name' => 'Pelaksana A', 'destination' => 'Dinas', 'departure_date' => '2026-01-10', 'return_date' => '2026-01-11', 'amount' => 400],
                    ['traveler_name' => 'Pelaksana B', 'destination' => 'Dinas', 'departure_date' => '2026-01-10', 'return_date' => '2026-01-11', 'amount' => 600],
                ]],
                'HONOR_PEGAWAI' => ['workers' => [
                    ['name' => 'Guru A', 'work_days' => 1, 'daily_rate' => 400],
                    ['name' => 'Guru B', 'work_days' => 1, 'daily_rate' => 600],
                ]],
                'JASA_LAINNYA' => ['service_recipients' => [
                    ['name' => 'Jasa A', 'quantity' => 1, 'rental_days' => 1, 'daily_rate' => 400],
                    ['name' => 'Jasa B', 'quantity' => 1, 'rental_days' => 1, 'daily_rate' => 600],
                ]],
            },
        ];
        $this->put(route('spj.update', $package->id), $payload)->assertSessionHasNoErrors()->assertSessionMissing('error');
        $transaction = $transaction->fresh();
        $this->assertSame($category, $transaction->spj_category);
        $this->assertSame('Dokumen '.$category, $transaction->payment_description);
        $this->assertSame('DRAFT', $package->fresh()->status);
        $this->assertSame(0, $package->documents()->count());
        match ($category) {
            'BARANG' => $this->assertSame('2026-01-10', $transaction->goods->first()->order_date->format('Y-m-d')),
            'KONSUMSI' => $this->assertCount(1, $transaction->participants),
            'PEMELIHARAAN' => $this->assertCount(1, $transaction->workers),
            'SPPD' => $this->assertCount(2, $transaction->travels),
            'HONOR_PEGAWAI' => $this->assertCount(2, $transaction->honors),
            'JASA_LAINNYA' => $this->assertCount(2, $transaction->serviceRecipients),
        };

        $viewData = [
            'transaction' => $transaction, 'package' => $package->fresh(), 'selectedSpjType' => $category,
            'transactionDateLimit' => '2026-01-15', 'orderDate' => '2026-01-10', 'bapDate' => '2026-01-11', 'bastDate' => '2026-01-12',
            'purchaseDetails' => $transaction->goods->first(), 'workDetails' => $transaction->workOrder,
            'workerRows' => [], 'participantRows' => [], 'participantRoster' => collect(),
            'rupiah' => fn ($value): string => 'Rp '.number_format((float) $value, 0, ',', '.'),
        ];
        foreach (['barang', 'konsumsi', 'pemeliharaan', 'sppd', 'honor-pegawai', 'jasa-lainnya'] as $partial) {
            $html = view('spj.partials.package.categories.'.$partial, $viewData)->render();
            $this->assertStringContainsString('data-spj-section=', $html);
            $this->assertStringNotContainsString('name="ppn_rate"', $html);
            $this->assertStringNotContainsString('name="item_description"', $html);
        }
        $taxHtml = view('spj.partials.package.tax-reference', $viewData)->render();
        $this->assertStringContainsString('PPh 4(2)', $taxHtml);
        $this->assertStringContainsString('Rp 805', $taxHtml);
        $this->assertStringNotContainsString('<input', $taxHtml);
    }

    /** @return array<string, array{string}> */
    public static function categories(): array
    {
        return array_combine(
            ['BARANG', 'KONSUMSI', 'PEMELIHARAAN', 'JASA_LAINNYA', 'SPPD', 'HONOR_PEGAWAI'],
            array_map(fn (string $category): array => [$category], ['BARANG', 'KONSUMSI', 'PEMELIHARAAN', 'JASA_LAINNYA', 'SPPD', 'HONOR_PEGAWAI']),
        );
    }

    private function transaction(bool $withItem = true): Transaction
    {
        $transaction = Transaction::query()->create([
            'fiscal_year_id' => 1, 'fund_source_id' => 1, 'no_bukti' => 'BPU-'.(Transaction::query()->count() + 1),
            'transaction_date' => '2026-01-15', 'gross_amount' => 1000, 'ppn' => 100, 'pph21' => 50,
            'pph22' => 0, 'pph23' => 0, 'pph4' => 25, 'sspd' => 20, 'tax_total' => 195, 'net_amount' => 805,
            'spj_category' => 'BARANG', 'payment_method' => 'tunai', 'source_status' => 'ACTIVE',
            'requires_reconciliation' => false, 'recipient_name' => 'Penerima BKU',
        ]);
        if ($withItem) {
            $transaction->items()->create([
                'description' => 'Kertas BKU', 'item_description' => 'Kertas untuk SPJ',
                'quantity' => 1, 'unit' => 'rim', 'unit_price' => 1000, 'amount' => 1000,
            ]);
        }

        return $transaction;
    }

    private function openDraft(Transaction $transaction): SpjPackage
    {
        $this->get(route('transactions.prepare-spj', $transaction->id))->assertRedirect()->assertSessionMissing('error');

        return $transaction->spjPackage()->firstOrFail();
    }
}
