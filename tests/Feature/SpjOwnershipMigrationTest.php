<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\SpjPackage;
use App\Models\Transaction;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SpjOwnershipMigrationTest extends TestCase
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

    public function test_transaction_without_items_cannot_prepare_package(): void
    {
        $transaction = $this->createTransaction(false);
        $this->get(route('transactions.prepare-spj', $transaction->id))
            ->assertRedirect(route('transactions.show', $transaction->id))
            ->assertSessionHas('error');
        $this->assertSame(0, SpjPackage::query()->count());
    }

    public function test_empty_item_descriptions_block_package_creation(): void
    {
        $transaction = $this->createTransaction();
        $transaction->items()->update(['item_description' => null]);
        $this->get(route('transactions.prepare-spj', $transaction->id))
            ->assertRedirect(route('transactions.show', $transaction->id).'#rincian-transaksi')
            ->assertSessionHas('error');
        $this->assertSame(0, SpjPackage::query()->count());
    }

    public function test_existing_package_with_empty_descriptions_blocks_access(): void
    {
        $transaction = $this->createTransaction();
        $transaction->items()->update(['item_description' => null]);
        $package = $transaction->spjPackage()->create(['status' => 'DRAFT', 'quarter_code' => 'TW-1', 'semester_code' => 'SEM-I']);
        $this->get(route('transactions.prepare-spj', $transaction->id))->assertSessionHas('error');
        $this->get(route('spj.index', ['tab' => 'paket', 'package_id' => $package->id]))
            ->assertRedirect(route('transactions.show', $transaction->id).'#rincian-transaksi');
    }

    public function test_saved_descriptions_enable_idempotent_draft_gateway(): void
    {
        $transaction = $this->createTransaction();
        $item = $transaction->items()->firstOrFail();
        $item->update(['item_description' => null]);
        $this->put(route('transactions.spj-descriptions.update', $transaction->id), [
            'items' => [['id' => $item->id, 'item_description' => 'Uraian tersimpan']],
        ])->assertSessionHasNoErrors();
        $this->assertSame('Uraian tersimpan', $item->fresh()->item_description);

        $this->get(route('transactions.prepare-spj', $transaction->id))->assertRedirect()->assertSessionMissing('error');
        $package = $transaction->spjPackage()->firstOrFail();
        $this->assertSame(1, SpjPackage::query()->count());

        $this->get(route('transactions.prepare-spj', $transaction->id))
            ->assertRedirect(route('spj.index', ['tab' => 'paket', 'package_id' => $package->id]));
        $this->assertSame(1, SpjPackage::query()->count());
    }

    public function test_package_update_cannot_change_tax_values(): void
    {
        $transaction = $this->createTransaction();
        $package = $this->openDraft($transaction);
        $protected = ['gross_amount', 'ppn', 'pph21', 'pph22', 'pph23', 'pph4', 'sspd', 'tax_total', 'net_amount'];
        $source = $transaction->fresh()->only($protected);

        $this->put(route('spj.update', $package->id), [
            ...array_fill_keys($protected, 999999),
            'spj_category' => 'BARANG', 'payment_description' => 'Test',
            'payment_method' => 'tunai',
        ])->assertSessionHasNoErrors();

        $this->assertSame($source, $transaction->fresh()->only($protected));
    }

    public function test_package_update_cannot_change_item_descriptions(): void
    {
        $transaction = $this->createTransaction();
        $package = $this->openDraft($transaction);
        $item = $transaction->items()->firstOrFail();
        $originalDescription = $item->item_description;

        $this->put(route('spj.update', $package->id), [
            'spj_category' => 'BARANG', 'payment_description' => 'Test',
            'payment_method' => 'tunai',
            'items' => [['id' => $item->id, 'item_description' => 'Hacked', 'quantity' => 99, 'unit' => 'x', 'unit_price' => 1, 'amount' => 1]],
        ])->assertSessionHasNoErrors();

        $this->assertSame($originalDescription, $item->fresh()->item_description);
    }

    public function test_category_switch_is_ajax_persists_and_returns_json(): void
    {
        $transaction = $this->createTransaction();
        $package = $this->openDraft($transaction);

        $response = $this->putJson(route('spj.update', $package->id), [
            'category_switch' => 1, 'spj_category' => 'SPPD',
        ]);

        $response->assertOk()->assertJsonPath('spj_category', 'SPPD');
        $this->assertSame('SPPD', $transaction->fresh()->spj_category);
    }

    public function test_legacy_spj_prepare_route_does_not_exist(): void
    {
        $this->post('/spj/1/siapkan', ['payment_description' => 'Legacy'])->assertNotFound();
    }

    public function test_legacy_manual_description_route_does_not_exist(): void
    {
        $this->put('/transaksi/1/uraian-manual', ['payment_description' => 'Legacy'])->assertNotFound();
    }

    public function test_transaction_controller_only_writes_item_description(): void
    {
        $transaction = $this->createTransaction();
        $item = $transaction->items()->firstOrFail();

        $this->put(route('transactions.spj-descriptions.update', $transaction->id), [
            'items' => [
                ['id' => $item->id, 'item_description' => 'Updated description'],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame('Updated description', $item->fresh()->item_description);
        $this->assertSame('BARANG', $transaction->fresh()->spj_category);
        $this->assertNull($transaction->fresh()->payment_description);
        $this->assertNull($transaction->fresh()->vendor_name);
    }

    #[DataProvider('canonicalCategories')]
    public function test_each_category_can_be_selected_and_saved_in_draft(string $category): void
    {
        $transaction = $this->createTransaction();
        $package = $this->openDraft($transaction);

        $this->putJson(route('spj.update', $package->id), [
            'category_switch' => 1, 'spj_category' => $category,
        ])->assertOk()->assertJsonPath('spj_category', $category);

        $this->assertSame($category, $transaction->fresh()->spj_category);
        $this->assertSame('DRAFT', $package->fresh()->status);
    }

    public function test_tax_reference_partial_renders_readonly_display(): void
    {
        $transaction = $this->createTransaction();
        $viewData = [
            'transaction' => $transaction,
            'rupiah' => fn ($value): string => 'Rp '.number_format((float) $value, 0, ',', '.'),
        ];
        $html = view('spj.partials.package.tax-reference', $viewData)->render();

        $this->assertStringContainsString('PPh 4(2)', $html);
        $this->assertStringContainsString('Rp 805', $html);
        $this->assertStringNotContainsString('<input', $html);
        $this->assertStringNotContainsString('name="ppn_rate"', $html);
        $this->assertStringNotContainsString('name="pph21_rate"', $html);
    }

    public function test_category_partials_use_data_spj_section_attribute(): void
    {
        $transaction = $this->createTransaction();
        $viewData = [
            'transaction' => $transaction, 'package' => $transaction->spjPackage()->first() ?? new SpjPackage,
            'selectedSpjType' => 'BARANG', 'transactionDateLimit' => '2026-01-15',
            'orderDate' => '2026-01-10', 'bapDate' => '2026-01-11', 'bastDate' => '2026-01-12',
            'purchaseDetails' => null, 'workDetails' => null,
            'workerRows' => [], 'participantRows' => [], 'participantRoster' => collect(),
            'rupiah' => fn ($value): string => 'Rp '.number_format((float) $value, 0, ',', '.'),
        ];

        foreach (['barang', 'konsumsi', 'pemeliharaan', 'sppd', 'honor-pegawai', 'jasa-lainnya'] as $partial) {
            $html = view('spj.partials.package.categories.'.$partial, $viewData)->render();
            $this->assertStringContainsString('data-spj-section=', $html, "Partial {$partial} must have data-spj-section attribute");
        }
    }

    public function test_numbered_package_blocks_description_update(): void
    {
        $transaction = $this->createTransaction();
        $package = $this->openDraft($transaction);
        $package->update(['status' => 'NUMBERED']);
        $item = $transaction->items()->firstOrFail();

        $this->put(route('transactions.spj-descriptions.update', $transaction->id), [
            'items' => [['id' => $item->id, 'item_description' => 'Should fail']],
        ])->assertSessionHas('error');
    }

    public function test_wrong_fund_context_rejects_all_operations(): void
    {
        $transaction = $this->createTransaction();
        $package = $this->openDraft($transaction);
        $this->withSession(['active_fund_source_id' => 2]);
        $this->get(route('transactions.prepare-spj', $transaction->id))->assertSessionHas('error');
        $this->put(route('spj.update', $package->id), ['vendor_name' => 'Asing'])->assertSessionHas('error');
        $this->putJson(route('spj.update', $package->id), ['category_switch' => 1, 'spj_category' => 'SPPD'])->assertNotFound();
    }

    /** @return array<string, array{string}> */
    public static function canonicalCategories(): array
    {
        return array_combine(
            ['BARANG', 'KONSUMSI', 'PEMELIHARAAN', 'JASA_LAINNYA', 'SPPD', 'HONOR_PEGAWAI'],
            array_map(fn (string $c): array => [$c], ['BARANG', 'KONSUMSI', 'PEMELIHARAAN', 'JASA_LAINNYA', 'SPPD', 'HONOR_PEGAWAI']),
        );
    }

    private function createTransaction(bool $withItem = true): Transaction
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
