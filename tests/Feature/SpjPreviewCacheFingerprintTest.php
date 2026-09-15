<?php

namespace Tests\Feature;

use App\Models\DocumentTemplate;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\School;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Services\SpjTemplateService;
use App\UseCases\Spj\SpjDocumentUseCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
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
        config()->set('cache.default', 'array');
        DB::purge('school');
        Cache::flush();

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
        Cache::flush();
        DB::purge('school');
        parent::tearDown();
    }

    public function test_fingerprint_is_stable_for_same_render_state_and_changes_with_service_recipient(): void
    {
        $transaction = $this->transaction();
        $recipient = $this->serviceRecipient($transaction);
        $package = $this->package($transaction, 'SPJ-CACHE-001');
        $templates = collect([$this->template(99)]);

        $first = $this->fingerprint($package->fresh('transaction'), $templates);
        $same = $this->fingerprint($package->fresh('transaction'), $templates);

        $this->assertSame($first, $same);

        $recipient->update(['name' => 'Penerima Cache B']);

        $changed = $this->fingerprint($package->fresh('transaction'), $templates);

        $this->assertNotSame($first, $changed);
    }

    public function test_preview_package_cache_hits_then_invalidates_when_recipient_changes(): void
    {
        $transaction = $this->transaction();
        $recipient = $this->serviceRecipient($transaction);
        $package = $this->package($transaction, 'SPJ-CACHE-RENDER');
        $this->persistedTemplate();

        $this->mock(SpjTemplateService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('packagePreviewPdfBytes')
                ->twice()
                ->andReturn('%PDF-1.4 cache regression');
        });

        $useCase = app(SpjDocumentUseCase::class);

        $first = $useCase->previewPackagePdf((string) $package->id);
        $second = $useCase->previewPackagePdf((string) $package->id);

        $this->assertSame(200, $first->getStatusCode());
        $this->assertSame(200, $second->getStatusCode());
        $this->assertSame($first->getContent(), $second->getContent());

        $recipient->update(['name' => 'Penerima Cache Sesudah Perubahan']);

        $third = $useCase->previewPackagePdf((string) $package->id);

        $this->assertSame(200, $third->getStatusCode());
    }

    public function test_fingerprint_changes_when_school_profile_changes(): void
    {
        $transaction = $this->transaction();
        $package = $this->package($transaction, 'SPJ-CACHE-002');
        $templates = collect([$this->template(100)]);

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

    private function package(Transaction $transaction, string $number): SpjPackage
    {
        return $transaction->spjPackage()->create([
            'document_number' => $number,
            'quarter_code' => 'TW1',
            'semester_code' => 'S1',
            'status' => 'READY',
        ]);
    }

    private function template(int $id): DocumentTemplate
    {
        return (new DocumentTemplate)->forceFill([
            'id' => $id,
            'fiscal_year_id' => $this->year->id,
            'document_type' => 'KUITANSI',
            'name' => 'Template Cache',
            'format' => 'xlsx',
            'file_path' => 'document-templates/cache.xlsx',
            'is_active' => true,
        ]);
    }

    private function persistedTemplate(): DocumentTemplate
    {
        return DocumentTemplate::query()->create([
            'fiscal_year_id' => $this->year->id,
            'document_type' => 'KUITANSI',
            'name' => 'Template Cache Aktif',
            'format' => 'xlsx',
            'file_path' => 'document-templates/cache-active.xlsx',
            'applicable_categories' => ['JASA_LAINNYA'],
            'is_active' => true,
        ]);
    }

    private function serviceRecipient(Transaction $transaction)
    {
        return $transaction->serviceRecipients()->create([
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
