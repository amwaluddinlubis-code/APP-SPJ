<?php

namespace Tests\Feature;

use App\Models\DocumentTemplate;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\School;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\UseCases\Spj\SpjDocumentUseCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

class SpjPreviewCacheFingerprintTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private FiscalYear $year;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');

        Artisan::call('migrate', [
            '--database' => 'school',
            '--path' => 'database/migrations/school',
            '--force' => true,
        ]);

        $fundSource = FundSource::query()->create([
            'code' => 'BOSP',
            'name' => 'BOSP',
        ]);
        $this->year = FiscalYear::query()->create([
            'year' => 2026,
            'fund_source' => 'BOSP',
            'fund_source_id' => $fundSource->id,
            'is_active' => true,
        ]);
        DB::connection('school')->table('school_profiles')->insert([
            'fiscal_year_id' => $this->year->id,
            'principal_name' => 'Kepala Cache',
            'principal_nip' => '198001012000011001',
            'treasurer_name' => 'Bendahara Cache',
            'treasurer_nip' => '198202022002022002',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->school = School::query()->create([
            'npsn' => '10209999',
            'school_code' => 'SCH-CACHE',
            'name' => 'SD Cache',
            'address' => 'Jl. Cache',
        ]);

        session([
            'active_school_id' => $this->school->id,
            'active_fiscal_year_id' => $this->year->id,
            'active_fund_source_id' => $fundSource->id,
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    public function test_fingerprint_is_stable_for_same_render_state_and_changes_with_service_recipient(): void
    {
        $transaction = $this->transaction();
        $recipient = $transaction->serviceRecipients()->create([
            'name' => 'Penerima Cache A',
            'service_type' => 'Pelatihan',
            'service_description' => 'Jasa cache',
            'quantity' => 1,
            'unit' => 'kegiatan',
            'rental_days' => 1,
            'daily_rate' => 100000,
            'amount' => 100000,
            'tax_amount' => 5000,
            'net_amount' => 95000,
            'sort_order' => 1,
        ]);
        $package = $transaction->spjPackage()->create([
            'document_number' => 'SPJ-CACHE-001',
            'quarter_code' => 'TW1',
            'semester_code' => 'S1',
            'status' => 'READY',
        ]);

        $template = (new DocumentTemplate)->forceFill([
            'id' => 99,
            'fiscal_year_id' => $this->year->id,
            'document_type' => 'KUITANSI',
            'name' => 'Template Cache',
            'format' => 'xlsx',
            'file_path' => 'document-templates/cache.xlsx',
            'is_active' => true,
        ]);
        $templates = collect([$template]);

        $first = $this->fingerprint($package->fresh('transaction'), $templates);
        $same = $this->fingerprint($package->fresh('transaction'), $templates);

        $this->assertSame($first, $same);

        $recipient->update(['name' => 'Penerima Cache B']);

        $changed = $this->fingerprint($package->fresh('transaction'), $templates);

        $this->assertNotSame($first, $changed);
    }

    public function test_fingerprint_changes_when_school_profile_changes(): void
    {
        $transaction = $this->transaction();
        $package = $transaction->spjPackage()->create([
            'document_number' => 'SPJ-CACHE-002',
            'quarter_code' => 'TW1',
            'semester_code' => 'S1',
            'status' => 'READY',
        ]);
        $templates = collect([(new DocumentTemplate)->forceFill([
            'id' => 100,
            'fiscal_year_id' => $this->year->id,
            'document_type' => 'KUITANSI',
            'name' => 'Template Cache',
            'format' => 'xlsx',
            'file_path' => 'document-templates/cache.xlsx',
            'is_active' => true,
        ])]);

        $before = $this->fingerprint($package->fresh('transaction'), $templates);

        DB::connection('school')->table('school_profiles')
            ->where('fiscal_year_id', $this->year->id)
            ->update([
                'principal_name' => 'Kepala Cache Berubah',
                'updated_at' => now()->addSecond(),
            ]);

        $after = $this->fingerprint($package->fresh('transaction'), $templates);

        $this->assertNotSame($before, $after);
    }

    private function fingerprint(SpjPackage $package, $templates): string
    {
        $method = new ReflectionMethod(SpjDocumentUseCase::class, 'packagePreviewCacheKey');

        return $method->invoke(app(SpjDocumentUseCase::class), $package, $templates);
    }

    private function transaction(): Transaction
    {
        $transaction = Transaction::query()->create([
            'fiscal_year_id' => $this->year->id,
            'fund_source_id' => $this->year->fund_source_id,
            'id_kas_umum' => 'CACHE-001',
            'no_bukti' => 'BPU-CACHE-001',
            'transaction_date' => '2026-02-10',
            'rkas_date' => '2026-02-01',
            'description' => 'Cache regression',
            'activity_code' => '02.01',
            'activity_name' => 'Kegiatan Cache',
            'account_code' => '5.1.02.01',
            'account_name' => 'Belanja Cache',
            'recipient_name' => 'Penerima Cache',
            'gross_amount' => 100000,
            'tax_total' => 5000,
            'net_amount' => 95000,
            'spj_category' => 'JASA_LAINNYA',
            'payment_method' => 'tunai',
            'source_status' => 'ACTIVE',
            'requires_reconciliation' => false,
            'source_key' => hash('sha256', 'cache-fingerprint-regression'),
        ]);

        $transaction->items()->create([
            'source_item_id' => 'CACHE-ITEM-1',
            'description' => 'Item cache',
            'item_description' => 'Item cache',
            'quantity' => 1,
            'unit' => 'kegiatan',
            'unit_price' => 100000,
            'amount' => 100000,
        ]);

        return $transaction;
    }
}
