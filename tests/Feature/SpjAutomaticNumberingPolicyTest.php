<?php

namespace Tests\Feature;

use App\Models\DocumentNumberFormat;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\Transaction;
use App\Services\SpjDocumentNumberService;
use App\Services\SpjNumberingPolicyService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SpjAutomaticNumberingPolicyTest extends TestCase
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

    public function test_automatic_document_types_are_scoped_by_category_and_channel(): void
    {
        $policy = app(SpjNumberingPolicyService::class);

        $barang = new Transaction(['spj_category' => 'BARANG', 'is_siplah' => false]);
        $konsumsi = new Transaction(['spj_category' => 'KONSUMSI', 'is_siplah' => false]);
        $maintenance = new Transaction(['spj_category' => 'PEMELIHARAAN', 'is_siplah' => false]);
        $jasa = new Transaction(['spj_category' => 'JASA_LAINNYA', 'is_siplah' => false]);
        $sppd = new Transaction(['spj_category' => 'SPPD', 'is_siplah' => false]);
        $honor = new Transaction(['spj_category' => 'HONOR_PEGAWAI', 'is_siplah' => false]);
        $siplah = new Transaction(['spj_category' => 'BARANG', 'is_siplah' => true]);

        foreach ([$barang, $konsumsi] as $transaction) {
            $this->assertTrue($policy->isAutomaticDocumentEligible($transaction, 'SPJ'));
            $this->assertTrue($policy->isAutomaticDocumentEligible($transaction, 'PESANAN'));
            $this->assertTrue($policy->isAutomaticDocumentEligible($transaction, 'BAP'));
            $this->assertTrue($policy->isAutomaticDocumentEligible($transaction, 'BAST'));
            $this->assertFalse($policy->isAutomaticDocumentEligible($transaction, 'SPK'));
            $this->assertFalse($policy->isAutomaticDocumentEligible($transaction, 'RAB'));
            $this->assertFalse($policy->isAutomaticDocumentEligible($transaction, 'SURAT_TUGAS_PERJALANAN_DINAS'));
        }

        $this->assertTrue($policy->isAutomaticDocumentEligible($maintenance, 'SPK'));
        $this->assertTrue($policy->isAutomaticDocumentEligible($maintenance, 'RAB'));
        $this->assertFalse($policy->isAutomaticDocumentEligible($maintenance, 'PESANAN'));

        $this->assertTrue($policy->isAutomaticDocumentEligible($sppd, 'SURAT_TUGAS_PERJALANAN_DINAS'));
        $this->assertFalse($policy->isAutomaticDocumentEligible($sppd, 'PESANAN'));
        $this->assertFalse($policy->isAutomaticDocumentEligible($jasa, 'PESANAN'));
        $this->assertFalse($policy->isAutomaticDocumentEligible($jasa, 'SPK'));
        $this->assertFalse($policy->isAutomaticDocumentEligible($honor, 'PESANAN'));
        $this->assertFalse($policy->isAutomaticDocumentEligible($honor, 'SPK'));

        $this->assertTrue($policy->isAutomaticDocumentEligible($siplah, 'SPJ'));
        $this->assertFalse($policy->isAutomaticDocumentEligible($siplah, 'PESANAN'));
        $this->assertFalse($policy->isAutomaticDocumentEligible($siplah, 'BAP'));
        $this->assertFalse($policy->isAutomaticDocumentEligible($siplah, 'BAST'));
    }

    public function test_stale_goods_dates_on_jasa_do_not_create_internal_procurement_numbers(): void
    {
        $year = $this->year();
        $transaction = $this->transaction($year, 'JASA_LAINNYA', '2026-04-10');
        $item = $transaction->items()->create([
            'description' => 'Jasa lama',
            'item_description' => 'Jasa lama',
            'quantity' => 1,
            'unit_price' => 1000,
            'amount' => 1000,
        ]);
        $goods = $item->goods()->create([
            'order_date' => '2026-03-01',
            'bap_date' => '2026-03-02',
            'bast_date' => '2026-03-03',
        ]);
        $package = $transaction->spjPackage()->create(['status' => 'READY']);

        $result = app(SpjDocumentNumberService::class)->assignAutomaticNumbers($package, '10200001');

        $this->assertSame(1, $result['created']);
        $this->assertSame(['SPJ'], $package->documents()->orderBy('id')->pluck('document_type')->all());
        $goods->refresh();
        $this->assertNull($goods->order_number);
        $this->assertNull($goods->bap_number);
        $this->assertNull($goods->bast_number);
    }

    public function test_siplah_barang_does_not_create_internal_order_bap_or_bast_numbers(): void
    {
        $year = $this->year();
        $transaction = $this->transaction($year, 'BARANG', '2026-04-10', true);
        $item = $transaction->items()->create([
            'description' => 'Barang SIPLah',
            'item_description' => 'Barang SIPLah',
            'quantity' => 1,
            'unit_price' => 1000,
            'amount' => 1000,
        ]);
        $goods = $item->goods()->create([
            'order_date' => '2026-03-01',
            'bap_date' => '2026-03-02',
            'bast_date' => '2026-03-03',
        ]);
        $package = $transaction->spjPackage()->create(['status' => 'READY']);

        $result = app(SpjDocumentNumberService::class)->assignAutomaticNumbers($package, '10200001');

        $this->assertSame(1, $result['created']);
        $this->assertSame(['SPJ'], $package->documents()->orderBy('id')->pluck('document_type')->all());
        $goods->refresh();
        $this->assertNull($goods->order_number);
        $this->assertNull($goods->bap_number);
        $this->assertNull($goods->bast_number);
    }

    public function test_cross_quarter_barang_uses_bku_date_for_spj_and_event_dates_for_supporting_documents(): void
    {
        $year = $this->year();
        $transaction = $this->transaction($year, 'BARANG', '2026-04-10');
        $item = $transaction->items()->create([
            'description' => 'Barang',
            'item_description' => 'Barang',
            'quantity' => 1,
            'unit_price' => 1000,
            'amount' => 1000,
        ]);
        $goods = $item->goods()->create([
            'order_date' => '2026-02-15',
            'bap_date' => '2026-03-20',
            'bast_date' => '2026-03-20',
        ]);
        $package = $transaction->spjPackage()->create(['status' => 'READY']);

        $result = app(SpjDocumentNumberService::class)->assignAutomaticNumbers($package, '10200001');

        $this->assertSame(4, $result['created']);
        $this->assertStringContainsString('/II/2026', $package->fresh()->document_number);
        $goods->refresh();
        $this->assertStringContainsString('/I/2026', $goods->order_number);
        $this->assertStringContainsString('/I/2026', $goods->bap_number);
        $this->assertStringContainsString('/I/2026', $goods->bast_number);
    }

    public function test_canonical_automatic_formats_are_persisted_without_overwriting_custom_format(): void
    {
        $year = $this->year();
        DocumentNumberFormat::query()->create([
            'fiscal_year_id' => $year->id,
            'document_type' => 'SPJ',
            'format_pattern' => 'CUSTOM/{SEQ}/{YEAR}',
            'reset_period' => 'MONTH',
            'padding' => 3,
            'is_active' => true,
        ]);

        $formats = app(SpjNumberingPolicyService::class)->ensureAutomaticFormats($year->id);

        $this->assertCount(7, $formats);
        $this->assertSame(7, DocumentNumberFormat::query()->where('fiscal_year_id', $year->id)->count());

        $spj = DocumentNumberFormat::query()->where([
            'fiscal_year_id' => $year->id,
            'document_type' => 'SPJ',
        ])->firstOrFail();
        $this->assertSame('CUSTOM/{SEQ}/{YEAR}', $spj->format_pattern);
        $this->assertSame('MONTH', $spj->reset_period);
        $this->assertSame(3, $spj->padding);

        foreach (['PESANAN', 'BAP', 'BAST', 'SPK', 'RAB', 'SURAT_TUGAS_PERJALANAN_DINAS'] as $type) {
            $format = DocumentNumberFormat::query()->where([
                'fiscal_year_id' => $year->id,
                'document_type' => $type,
            ])->firstOrFail();
            $this->assertSame('{SEQ}/'.$type.'/{SCHOOL}/{TW}/{YEAR}', $format->format_pattern);
            $this->assertSame('YEAR', $format->reset_period);
            $this->assertSame(4, $format->padding);
            $this->assertTrue($format->is_active);
        }
    }

    public function test_upgrade_migration_seeds_existing_year_and_preserves_custom_format(): void
    {
        $year = $this->year();
        DocumentNumberFormat::query()->create([
            'fiscal_year_id' => $year->id,
            'document_type' => 'SPJ',
            'format_pattern' => 'CUSTOM/{SEQ}/{YEAR}',
            'reset_period' => 'YEAR',
            'padding' => 3,
            'is_active' => true,
        ]);

        $migration = require database_path('migrations/school/2026_09_10_134500_seed_canonical_document_number_formats.php');
        $migration->up();

        $this->assertSame(7, DocumentNumberFormat::query()->where('fiscal_year_id', $year->id)->count());
        $this->assertSame(
            'CUSTOM/{SEQ}/{YEAR}',
            DocumentNumberFormat::query()->where('fiscal_year_id', $year->id)->where('document_type', 'SPJ')->value('format_pattern'),
        );
        $this->assertSame(
            '{SEQ}/PESANAN/{SCHOOL}/{TW}/{YEAR}',
            DocumentNumberFormat::query()->where('fiscal_year_id', $year->id)->where('document_type', 'PESANAN')->value('format_pattern'),
        );
    }

    private function year(): FiscalYear
    {
        FundSource::query()->firstOrCreate(['id' => 1], ['code' => 'BOSP', 'name' => 'BOSP']);

        return FiscalYear::query()->create([
            'year' => 2026,
            'fund_source' => 'BOSP',
            'fund_source_id' => 1,
        ]);
    }

    private function transaction(FiscalYear $year, string $category, string $date, bool $isSiplah = false): Transaction
    {
        return Transaction::query()->create([
            'fiscal_year_id' => $year->id,
            'fund_source_id' => 1,
            'id_kas_umum' => uniqid('KAS-', true),
            'no_bukti' => uniqid('BPU-', true),
            'transaction_date' => $date,
            'gross_amount' => 1000,
            'tax_total' => 0,
            'net_amount' => 1000,
            'spj_category' => $category,
            'is_siplah' => $isSiplah,
        ]);
    }
}
