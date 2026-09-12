<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\SpjDocument;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Services\DocumentTemplatePlaceholderInspectorService;
use App\Services\SpjDocumentTypeRegistry;
use App\Services\SpjTemplateService;
use App\Support\ActiveSpjContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DocumentTemplatePlaceholderInspectorTest extends TestCase
{
    private int $activeYearId;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');

        Artisan::call('migrate', [
            '--database' => 'school',
            '--path' => 'database/migrations/school/2026_09_01_000000_create_complete_spj_tenant_tables.php',
            '--force' => true,
        ]);

        DB::connection('school')->table('fund_sources')->insert([
            [
                'id' => 1,
                'code' => 'BOSP',
                'name' => 'BOSP Reguler',
                'is_hidden' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'code' => 'KINERJA',
                'name' => 'BOSP Kinerja',
                'is_hidden' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->activeYearId = DB::connection('school')->table('fiscal_years')->insertGetId([
            'year' => 2026,
            'fund_source' => 'BOSP Reguler',
            'fund_source_id' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('school')->table('fiscal_years')->insert([
            'year' => 2026,
            'fund_source' => 'BOSP Kinerja',
            'fund_source_id' => 2,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('school')->table('school_profiles')->insert([
            'fiscal_year_id' => $this->activeYearId,
            'principal_name' => 'Kepala Sekolah Test',
            'principal_nip' => '197001011999031001',
            'treasurer_name' => 'Bendahara Test',
            'treasurer_nip' => '198001012000032001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app->instance(ActiveSpjContext::class, new ActiveSpjContext(
            schoolIdOverride: 1,
            fiscalYearIdOverride: $this->activeYearId,
            fundSourceIdOverride: 1,
            actorIdOverride: 1,
            administratorOverride: true,
        ));
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    public function test_inspector_returns_all_placeholder_groups_with_actual_scalar_and_item_values(): void
    {
        $package = $this->makePackage(
            fundSourceId: 1,
            proofNumber: 'BPU-001',
            documentNumber: '001/SPJ/2026',
        );
        TransactionItem::query()->create([
            'transaction_id' => $package->transaction_id,
            'description' => 'ATK source',
            'item_description' => 'Kertas A4 80 gsm',
            'quantity' => 2,
            'unit' => 'rim',
            'unit_price' => 75000,
            'amount' => 150000,
            'source_status' => 'ACTIVE',
        ]);

        $result = app(DocumentTemplatePlaceholderInspectorService::class)->inspect(
            '001/SPJ/2026',
            $this->school(),
        );

        $this->assertNotNull($result);
        $this->assertSame('001/SPJ/2026', $result['package']['document_number']);
        $this->assertSame('BPU-001', $result['package']['no_bukti']);
        $this->assertSame('BARANG', $result['package']['category']);
        $this->assertSame(
            collect(SpjTemplateService::placeholderGroups())->flatten()->count(),
            $result['total'],
        );

        $values = $this->placeholderValues($result['groups']);
        $this->assertSame('001/SPJ/2026', $values['NOMOR_SPJ']);
        $this->assertSame('BPU-001', $values['NO_BUKTI']);
        $this->assertSame('SMP Negeri Test', $values['NAMA_SEKOLAH']);
        $this->assertSame('Kepala Sekolah Test', $values['NAMA_KEPALA_SEKOLAH']);
        $this->assertSame('Kertas A4 80 gsm', $values['ITEM_URAIAN']);
        $this->assertSame('2.00', $values['ITEM_VOLUME']);
        $this->assertSame('Rp 75.000', $values['ITEM_HARGA_SATUAN']);
        $this->assertSame('Rp 150.000', $values['ITEM_JUMLAH']);
        $this->assertSame(SpjDocumentTypeRegistry::EMPTY_SCALAR_VALUE, $values['UPAH_NAMA']);
    }

    public function test_inspector_can_find_package_from_child_document_number_and_proof_number(): void
    {
        $package = $this->makePackage(
            fundSourceId: 1,
            proofNumber: 'BPU-LOOKUP',
            documentNumber: '010/SPJ/2026',
        );
        SpjDocument::query()->create([
            'spj_package_id' => $package->id,
            'document_type' => 'SPK',
            'scope_key' => 'MAIN',
            'status' => 'NUMBERED',
            'document_number' => '010/SPK/2026',
            'sequence_number' => 10,
            'document_date' => '2026-06-10',
            'numbered_at' => now(),
        ]);

        $byChildDocument = app(DocumentTemplatePlaceholderInspectorService::class)->inspect(
            '010/SPK/2026',
            $this->school(),
        );
        $byProofNumber = app(DocumentTemplatePlaceholderInspectorService::class)->inspect(
            'BPU-LOOKUP',
            $this->school(),
        );

        $this->assertNotNull($byChildDocument);
        $this->assertNotNull($byProofNumber);
        $this->assertSame((string) $package->id, $byChildDocument['package']['id']);
        $this->assertSame((string) $package->id, $byProofNumber['package']['id']);
    }

    public function test_inspector_does_not_return_package_from_other_fund_source(): void
    {
        $this->makePackage(
            fundSourceId: 2,
            proofNumber: 'BPU-OTHER-FUND',
            documentNumber: '999/SPJ/2026',
        );

        $result = app(DocumentTemplatePlaceholderInspectorService::class)->inspect(
            '999/SPJ/2026',
            $this->school(),
        );

        $this->assertNull($result);
    }

    public function test_template_page_contains_ajax_placeholder_checker_contract(): void
    {
        $page = file_get_contents(resource_path('views/document-templates/index.blade.php'));
        $modal = file_get_contents(resource_path('views/document-templates/partials/placeholder-checker.blade.php'));

        $this->assertIsString($page);
        $this->assertIsString($modal);
        $this->assertStringContainsString('Cek Placeholder', $page);
        $this->assertStringContainsString('$dispatch(\'open-placeholder-checker\')', $page);
        $this->assertStringContainsString('document-templates.partials.placeholder-checker', $page);
        $this->assertStringContainsString("url.searchParams.set('placeholder_reference', reference)", $modal);
        $this->assertStringContainsString("'Accept': 'application/json'", $modal);
        $this->assertStringContainsString('group.placeholders', $modal);
    }

    private function makePackage(int $fundSourceId, string $proofNumber, string $documentNumber): SpjPackage
    {
        $yearId = $this->activeYearId;
        if ($fundSourceId !== 1) {
            $yearId = (int) DB::connection('school')->table('fiscal_years')
                ->where('fund_source_id', $fundSourceId)
                ->value('id');
        }

        $transaction = Transaction::query()->create([
            'fiscal_year_id' => $yearId,
            'fund_source_id' => $fundSourceId,
            'no_bukti' => $proofNumber,
            'transaction_date' => '2026-06-10',
            'description' => 'Belanja alat tulis kantor',
            'payment_description' => 'Pembayaran alat tulis kantor',
            'payment_method' => 'transfer_bank',
            'payment_reference' => 'REF-001',
            'activity_code' => '02.03.04',
            'activity_name' => 'Pelaksanaan administrasi sekolah',
            'account_code' => '5.1.02.01',
            'account_name' => 'Belanja Barang',
            'recipient_name' => 'Toko Sumber',
            'receipt_recipient_name' => 'Toko Sumber',
            'vendor_name' => 'Toko Sumber',
            'gross_amount' => 150000,
            'ppn' => 0,
            'tax_total' => 0,
            'net_amount' => 150000,
            'spj_category' => 'BARANG',
            'source_key' => 'TEST-'.$fundSourceId.'-'.$proofNumber,
            'source_status' => 'ACTIVE',
        ]);

        return SpjPackage::query()->create([
            'transaction_id' => $transaction->id,
            'document_number' => $documentNumber,
            'quarter_code' => 'TW2',
            'semester_code' => '1',
            'status' => 'NUMBERED',
        ]);
    }

    private function school(): School
    {
        return new School([
            'npsn' => '12345678',
            'school_code' => 'SMP-TEST',
            'name' => 'SMP Negeri Test',
            'address' => 'Jl. Pendidikan No. 1',
            'desa' => 'Sekupang',
            'district' => 'Sekupang',
            'regency' => 'Batam',
            'province' => 'Kepulauan Riau',
        ]);
    }

    /**
     * @param  array<int,array{name:string,placeholders:array<int,array{key:string,marker:string,value:string,kind:string}>}>  $groups
     * @return array<string,string>
     */
    private function placeholderValues(array $groups): array
    {
        $values = [];

        foreach ($groups as $group) {
            foreach ($group['placeholders'] as $placeholder) {
                $values[$placeholder['key']] = $placeholder['value'];
            }
        }

        return $values;
    }
}
