<?php

namespace Tests\Feature;

use App\Models\DocumentTemplate;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\School;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Services\PreviewAlignedSpjTemplateService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SpjGeneratedDocumentRegressionTest extends TestCase
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
        Storage::fake('local');

        Artisan::call('migrate', [
            '--database' => 'school',
            '--path' => 'database/migrations/school',
            '--force' => true,
        ]);

        $fund = FundSource::query()->create([
            'code' => 'BOSP',
            'name' => 'BOSP',
        ]);
        $this->year = FiscalYear::query()->create([
            'year' => 2026,
            'fund_source' => 'BOSP',
            'fund_source_id' => $fund->id,
            'is_active' => true,
        ]);
        DB::connection('school')->table('school_profiles')->insert([
            'fiscal_year_id' => $this->year->id,
            'principal_name' => 'Kepala Sekolah Regression',
            'principal_nip' => '198001012000011001',
            'treasurer_name' => 'Bendahara Regression',
            'treasurer_nip' => '198202022002022002',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->school = School::query()->create([
            'npsn' => '10208183',
            'school_code' => 'SCH-REG',
            'name' => 'SD Negeri Regression',
            'address' => 'Jl. Regression No. 1',
        ]);
    }

    protected function tearDown(): void
    {
        Model::preventLazyLoading(false);
        DB::purge('school');
        parent::tearDown();
    }

    public function test_consumption_xlsx_expands_one_participant_per_row(): void
    {
        $transaction = $this->createTransaction('KONSUMSI');
        $item = $transaction->items()->create([
            'source_item_id' => 'ITEM-KONSUMSI-1',
            'description' => 'Konsumsi rapat',
            'item_description' => 'Konsumsi rapat',
            'quantity' => 3,
            'unit' => 'porsi',
            'unit_price' => 12500,
            'amount' => 37500,
        ]);
        $item->participants()->create([
            'name' => 'Peserta Satu',
            'position' => 'Guru',
            'nip' => '198001012000011001',
            'portions' => 2,
            'sort_order' => 1,
        ]);
        $item->participants()->create([
            'name' => 'Peserta Dua',
            'position' => 'Pelatih',
            'nuptk' => '1234567890123456',
            'portions' => 1,
            'sort_order' => 2,
        ]);

        $package = $transaction->spjPackage()->create([
            'document_number' => 'SPJ-KONSUMSI-001',
            'quarter_code' => 'TW1',
            'semester_code' => 'S1',
            'status' => 'READY',
        ]);
        $package->setRelation('transaction', $transaction);

        $template = $this->createConsumptionTemplate();
        $service = new class extends PreviewAlignedSpjTemplateService
        {
            public function renderCanonical(DocumentTemplate $template, SpjPackage $package, School $school): Spreadsheet
            {
                return $this->canonicalSpreadsheet($template, $package, $school);
            }
        };

        $rendered = $service->renderCanonical($template, $package, $this->school);

        try {
            $sheet = $rendered->getSheet(0);

            $this->assertSame('1', (string) $sheet->getCell('A10')->getValue());
            $this->assertSame('Peserta Satu', $sheet->getCell('B10')->getValue());
            $this->assertSame('Guru / NIP 198001012000011001', $sheet->getCell('C10')->getValue());
            $this->assertSame('2', (string) $sheet->getCell('D10')->getValue());
            $this->assertSame('12500', (string) $sheet->getCell('E10')->getValue());
            $this->assertSame('25000', (string) $sheet->getCell('F10')->getValue());

            $this->assertSame('2', (string) $sheet->getCell('A11')->getValue());
            $this->assertSame('Peserta Dua', $sheet->getCell('B11')->getValue());
            $this->assertSame('Pelatih / NUPTK 1234567890123456', $sheet->getCell('C11')->getValue());
            $this->assertSame('1', (string) $sheet->getCell('D11')->getValue());
            $this->assertSame('12500', (string) $sheet->getCell('E11')->getValue());
            $this->assertSame('12500', (string) $sheet->getCell('F11')->getValue());

            $this->assertStringNotContainsString("\n", (string) $sheet->getCell('B10')->getValue());
            $this->assertStringNotContainsString("\n", (string) $sheet->getCell('B11')->getValue());
            $this->assertSame('37500', (string) $sheet->getCell('A16')->getValue());
        } finally {
            $rendered->disconnectWorksheets();
        }
    }

    public function test_service_recipient_eager_load_hydrates_transaction_inverse(): void
    {
        $transaction = $this->createTransaction('JASA_LAINNYA');
        $transaction->serviceRecipients()->create([
            'name' => 'Penerima Jasa Regression',
            'service_type' => 'Pelatihan',
            'service_description' => 'Jasa pelatihan',
            'quantity' => 1,
            'unit' => 'kegiatan',
            'rental_days' => 1,
            'daily_rate' => 100000,
            'amount' => 100000,
            'tax_amount' => 5000,
            'net_amount' => 95000,
            'sort_order' => 1,
        ]);

        Model::preventLazyLoading();

        $loaded = Transaction::query()
            ->with('serviceRecipients')
            ->findOrFail($transaction->id);
        $recipient = $loaded->serviceRecipients->firstOrFail();

        $this->assertTrue($recipient->relationLoaded('transaction'));
        $this->assertSame($loaded->id, $recipient->transaction->id);
        $this->assertSame($loaded->no_bukti, $recipient->transaction->no_bukti);
    }

    private function createTransaction(string $category): Transaction
    {
        return Transaction::query()->create([
            'fiscal_year_id' => $this->year->id,
            'fund_source_id' => $this->year->fund_source_id,
            'id_kas_umum' => hash('crc32b', $category),
            'no_bukti' => 'BPU-REG-'.$category,
            'transaction_date' => '2026-01-15',
            'rkas_date' => '2026-01-01',
            'description' => 'Regression '.$category,
            'activity_code' => '02.01',
            'activity_name' => 'Kegiatan Regression',
            'account_code' => '5.1.02.01',
            'account_name' => 'Belanja Regression',
            'recipient_name' => 'Penerima BKU Regression',
            'gross_amount' => $category === 'KONSUMSI' ? 37500 : 100000,
            'tax_total' => $category === 'KONSUMSI' ? 0 : 5000,
            'net_amount' => $category === 'KONSUMSI' ? 37500 : 95000,
            'spj_category' => $category,
            'payment_method' => 'tunai',
            'source_status' => 'ACTIVE',
            'requires_reconciliation' => false,
            'source_key' => hash('sha256', 'generated-document-regression-'.$category),
            'event_name' => $category === 'KONSUMSI' ? 'Rapat Regression' : null,
            'event_location' => $category === 'KONSUMSI' ? 'Ruang Guru' : null,
            'event_date' => $category === 'KONSUMSI' ? '2026-01-15' : null,
        ]);
    }

    private function createConsumptionTemplate(): DocumentTemplate
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('KONSUMSI_TEST');
        $sheet->setCellValue('A10', '{{KONSUMSI_NO}}');
        $sheet->setCellValue('B10', '{{KONSUMSI_NAMA}}');
        $sheet->setCellValue('C10', '{{KONSUMSI_IDENTITAS}}');
        $sheet->setCellValue('D10', '{{KONSUMSI_PORSI}}');
        $sheet->setCellValue('E10', '{{KONSUMSI_HARGA_PORSI}}');
        $sheet->setCellValue('F10', '{{KONSUMSI_JUMLAH}}');
        $sheet->setCellValue('A15', '{{TOTAL_KONSUMSI}}');

        Storage::disk('local')->makeDirectory('document-templates');
        $relativePath = 'document-templates/consumption-repeating-row-regression.xlsx';
        (new Xlsx($book))->save(Storage::disk('local')->path($relativePath));
        $book->disconnectWorksheets();

        return (new DocumentTemplate)->forceFill([
            'document_type' => 'RINCIAN_BELANJA',
            'format' => 'xlsx',
            'file_path' => $relativePath,
        ]);
    }
}
