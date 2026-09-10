<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\UseCases\Spj\UpdateSpjPackageDetailsUseCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use ReflectionMethod;
use Tests\TestCase;

class SpjNonGoodsInputValidationTest extends TestCase
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

    public function test_non_goods_input_helper_is_bootstrapped_and_guards_live_totals(): void
    {
        $bootstrap = file_get_contents(resource_path('js/bootstrap.js'));
        $script = file_get_contents(resource_path('js/spj-detail-input-validation.js'));

        $this->assertStringContainsString("import './spj-detail-input-validation';", $bootstrap);
        foreach (['HONOR_PEGAWAI', 'PEMELIHARAAN', 'SPPD', 'JASA_LAINNYA'] as $category) {
            $this->assertStringContainsString($category, $script);
        }
        foreach (['participant_count', 'portions', 'work_days', 'quantity', 'rental_days'] as $field) {
            $this->assertStringContainsString($field, $script);
        }
        $this->assertStringContainsString('Validasi total biaya', $script);
        $this->assertStringContainsString('Total rincian', $script);
        $this->assertStringContainsString('Bruto transaksi', $script);
        $this->assertStringContainsString('event.preventDefault()', $script);
        $this->assertStringContainsString('service_recipients', $script);
        $this->assertStringContainsString('daily_rate', $script);
    }

    public function test_non_goods_count_and_rupiah_fields_reject_decimals_on_server(): void
    {
        $jasa = $this->validator([
            'spj_category' => 'JASA_LAINNYA',
            'service_recipients' => [[
                'name' => 'Penerima Jasa',
                'quantity' => '1.5',
                'rental_days' => 1,
                'daily_rate' => '1000.5',
            ]],
        ]);
        $this->assertTrue($jasa->fails());
        $this->assertArrayHasKey('service_recipients.0.quantity', $jasa->errors()->toArray());
        $this->assertArrayHasKey('service_recipients.0.daily_rate', $jasa->errors()->toArray());

        $honor = $this->validator([
            'spj_category' => 'HONOR_PEGAWAI',
            'workers' => [[
                'name' => 'Penerima Honor',
                'work_days' => '1.5',
                'daily_rate' => '1000.5',
            ]],
        ]);
        $this->assertTrue($honor->fails());
        $this->assertArrayHasKey('workers.0.work_days', $honor->errors()->toArray());
        $this->assertArrayHasKey('workers.0.daily_rate', $honor->errors()->toArray());

        $sppd = $this->validator([
            'spj_category' => 'SPPD',
            'travels' => [[
                'traveler_name' => 'Pelaksana',
                'amount' => '1000.5',
            ]],
        ]);
        $this->assertTrue($sppd->fails());
        $this->assertArrayHasKey('travels.0.amount', $sppd->errors()->toArray());
    }

    public function test_each_non_goods_cost_detail_must_equal_transaction_gross(): void
    {
        $cases = [
            [
                'payload' => [
                    'spj_category' => 'HONOR_PEGAWAI',
                    'workers' => [['name' => 'Penerima Honor', 'work_days' => 1, 'daily_rate' => 900]],
                ],
                'error' => 'workers',
            ],
            [
                'payload' => [
                    'spj_category' => 'PEMELIHARAAN',
                    'workers' => [['name' => 'Pekerja', 'work_days' => 1, 'daily_rate' => 900]],
                ],
                'error' => 'workers',
            ],
            [
                'payload' => [
                    'spj_category' => 'SPPD',
                    'travels' => [['traveler_name' => 'Pelaksana', 'amount' => 900]],
                ],
                'error' => 'travels',
            ],
            [
                'payload' => [
                    'spj_category' => 'JASA_LAINNYA',
                    'service_recipients' => [['name' => 'Penerima Jasa', 'quantity' => 1, 'rental_days' => 1, 'daily_rate' => 900]],
                ],
                'error' => 'service_recipients',
            ],
        ];

        foreach ($cases as $case) {
            $validator = $this->validator($case['payload']);
            $this->assertTrue($validator->fails());
            $this->assertArrayHasKey($case['error'], $validator->errors()->toArray());
        }
    }

    public function test_each_non_goods_cost_detail_accepts_an_exact_gross_total(): void
    {
        $cases = [
            [
                'payload' => [
                    'spj_category' => 'HONOR_PEGAWAI',
                    'workers' => [['name' => 'Penerima Honor', 'work_days' => 1, 'daily_rate' => 1000]],
                ],
                'error' => 'workers',
            ],
            [
                'payload' => [
                    'spj_category' => 'PEMELIHARAAN',
                    'workers' => [['name' => 'Pekerja', 'work_days' => 1, 'daily_rate' => 1000]],
                ],
                'error' => 'workers',
            ],
            [
                'payload' => [
                    'spj_category' => 'SPPD',
                    'travels' => [['traveler_name' => 'Pelaksana', 'amount' => 1000]],
                ],
                'error' => 'travels',
            ],
            [
                'payload' => [
                    'spj_category' => 'JASA_LAINNYA',
                    'service_recipients' => [['name' => 'Penerima Jasa', 'quantity' => 1, 'rental_days' => 1, 'daily_rate' => 1000]],
                ],
                'error' => 'service_recipients',
            ],
        ];

        foreach ($cases as $case) {
            $validator = $this->validator($case['payload']);
            $validator->passes();
            $this->assertArrayNotHasKey($case['error'], $validator->errors()->toArray());
        }
    }

    private function validator(array $payload)
    {
        $package = $this->package($payload['spj_category']);
        $request = Request::create('/spj/test', 'PUT', $payload);
        $method = new ReflectionMethod(UpdateSpjPackageDetailsUseCase::class, 'rules');
        $method->setAccessible(true);
        $rules = $method->invoke(app(UpdateSpjPackageDetailsUseCase::class), $request, $package);

        return Validator::make($request->all(), $rules);
    }

    private function package(string $category): SpjPackage
    {
        FundSource::query()->firstOrCreate(['id' => 1], ['code' => 'BOSP', 'name' => 'BOSP']);
        $year = FiscalYear::query()->firstOrCreate([
            'year' => 2026,
            'fund_source_id' => 1,
        ], [
            'fund_source' => 'BOSP',
        ]);
        $transaction = Transaction::query()->create([
            'fiscal_year_id' => $year->id,
            'fund_source_id' => 1,
            'id_kas_umum' => 'INPUT-'.uniqid(),
            'no_bukti' => 'BPU-INPUT-'.uniqid(),
            'transaction_date' => '2026-04-10',
            'rkas_date' => '2026-03-01',
            'activity_code' => '05.02.01',
            'activity_name' => 'Kegiatan sekolah',
            'account_code' => '5.1.02.01',
            'account_name' => 'Belanja',
            'gross_amount' => 1000,
            'tax_total' => 0,
            'net_amount' => 1000,
            'spj_category' => $category,
        ]);

        return $transaction->spjPackage()->create(['status' => 'DRAFT']);
    }
}
