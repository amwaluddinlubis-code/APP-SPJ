<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\School;
use App\Models\Transaction;
use App\Services\SpjTemplateService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TransactionSignatoryCleanupTest extends TestCase
{
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

        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        FiscalYear::query()->create(['id' => 1, 'year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
        DB::connection('school')->table('school_profiles')->insert([
            'fiscal_year_id' => 1,
            'principal_name' => 'Kepala Sekolah Uji',
            'principal_nip' => '198001012000011001',
            'treasurer_name' => 'Bendahara Uji',
            'treasurer_nip' => '198202022002022002',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    public function test_transaction_signatory_columns_are_removed_and_receipt_recipient_no_longer_depends_on_them(): void
    {
        $this->assertFalse(Schema::connection('school')->hasColumn('transactions', 'signatory_name'));
        $this->assertFalse(Schema::connection('school')->hasColumn('transactions', 'signatory_role'));

        $transaction = Transaction::query()->create([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'no_bukti' => 'BKU-001',
            'transaction_date' => '2026-09-05',
            'recipient_name' => 'Penyedia ARKAS',
            'receipt_recipient_name' => 'Penerima Kuitansi',
            'gross_amount' => 1000,
            'tax_total' => 0,
            'net_amount' => 1000,
        ]);

        $this->assertSame('Penerima Kuitansi', $transaction->effective_receipt_recipient_name);
        $this->assertSame('Kepala Sekolah Uji', $transaction->signatory_name);
        $this->assertSame('Kepala Sekolah', $transaction->signatory_role);
    }

    public function test_legacy_signatory_template_placeholders_resolve_to_school_principal_profile(): void
    {
        $transaction = Transaction::query()->create([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'no_bukti' => 'BKU-002',
            'transaction_date' => '2026-09-05',
            'recipient_name' => 'Penyedia',
            'receipt_recipient_name' => 'Penyedia',
            'payment_method' => 'tunai',
            'gross_amount' => 1000,
            'tax_total' => 0,
            'net_amount' => 1000,
        ]);
        $package = $transaction->spjPackage()->create([
            'quarter_code' => 'TW-3',
            'semester_code' => 'SEM-II',
            'status' => 'DRAFT',
        ]);

        $values = app(SpjTemplateService::class)->placeholders(
            $package->load('transaction'),
            new School(['name' => 'SD Negeri Uji', 'npsn' => '12345678'])
        );

        $this->assertSame('Kepala Sekolah Uji', $values['NAMA_PENANDATANGAN']);
        $this->assertSame('Kepala Sekolah', $values['JABATAN_PENANDATANGAN']);
        $this->assertSame('Kepala Sekolah Uji', $values['NAMA_KEPALA_SEKOLAH']);
        $this->assertSame('Bendahara Uji', $values['NAMA_BENDAHARA_BOSP']);
    }
}
