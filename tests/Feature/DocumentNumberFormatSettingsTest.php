<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureOperatorOrAdministrator;
use App\Models\DocumentNumberFormat;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\SpjMaintenance;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SpjDocumentNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DocumentNumberFormatSettingsTest extends TestCase
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

    public function test_operator_can_access_number_format_settings_but_viewer_cannot(): void
    {
        $middleware = new EnsureOperatorOrAdministrator;
        $request = Request::create('/pengaturan/format-penomoran');
        $request->setUserResolver(fn () => new User(['role' => User::ROLE_OPERATOR]));

        $response = $middleware->handle($request, fn () => new Response('OK'));

        $this->assertSame(200, $response->getStatusCode());

        $request->setUserResolver(fn () => new User(['role' => User::ROLE_VIEWER]));
        $this->expectException(HttpException::class);
        $middleware->handle($request, fn () => new Response('OK'));
    }

    public function test_configured_spj_format_is_used_for_new_numbers(): void
    {
        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        $year = FiscalYear::query()->create(['year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
        $transaction = Transaction::query()->create([
            'fiscal_year_id' => $year->id,
            'fund_source_id' => 1,
            'no_bukti' => 'BPU-001',
            'transaction_date' => '2026-03-10',
        ]);
        $package = $transaction->spjPackage()->create(['status' => 'READY']);
        DocumentNumberFormat::query()->create([
            'fiscal_year_id' => $year->id,
            'document_type' => 'SPJ',
            'format_pattern' => 'SPJ-{YEAR}-{MONTH}-{SEQ}-{SCHOOL}-{NPSN}',
            'reset_period' => 'YEAR',
            'padding' => 3,
            'is_active' => true,
        ]);

        $document = app(SpjDocumentNumberService::class)->assign(
            $package,
            'SPJ',
            Carbon::parse('2026-03-10'),
            'SDN-01',
            npsn: '10260756',
        );

        $this->assertSame('SPJ-2026-03-001-SDN-01-10260756', $document->document_number);
    }

    public function test_all_related_numbers_use_their_own_event_dates_and_do_not_overwrite_existing_numbers(): void
    {
        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        $year = FiscalYear::query()->create(['year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
        $transaction = Transaction::query()->create([
            'fiscal_year_id' => $year->id,
            'fund_source_id' => 1,
            'no_bukti' => 'BPU-002',
            'transaction_date' => '2026-01-10',
        ]);
        $item = $transaction->items()->create(['description' => 'Barang', 'amount' => 1000]);
        $goods = $item->goods()->create([
            'order_date' => '2026-02-05',
            'bap_date' => '2026-03-06',
            'bast_date' => '2026-04-07',
        ]);
        $maintenance = SpjMaintenance::query()->create([
            'fiscal_year_id' => $year->id,
            'name' => 'Pemeliharaan ruang kelas',
        ]);
        $workOrder = $transaction->workOrder()->create([
            'maintenance_id' => $maintenance->id,
            'expense_type' => 'UPAH',
            'work_description' => 'Perbaikan ruang kelas',
            'spk_date' => '2026-05-08',
            'rab_date' => '2026-06-09',
        ]);
        $travel = $transaction->travels()->create([
            'traveler_name' => 'Operator Sekolah',
            'assignment_letter_date' => '2026-07-10',
            'departure_date' => '2026-07-11',
        ]);
        $package = $transaction->spjPackage()->create(['status' => 'READY']);

        $result = app(SpjDocumentNumberService::class)->assignAutomaticNumbers($package, '10260756');

        $this->assertSame(7, $result['created']);
        $this->assertStringContainsString('/II/2026', $goods->fresh()->order_number);
        $this->assertStringContainsString('/III/2026', $goods->fresh()->bap_number);
        $this->assertStringContainsString('/IV/2026', $goods->fresh()->bast_number);
        $this->assertStringContainsString('/V/2026', $workOrder->fresh()->spk_number);
        $this->assertStringContainsString('/VI/2026', $workOrder->fresh()->rab_number);
        $this->assertStringContainsString('/VII/2026', $travel->fresh()->assignment_letter_number);

        $existingOrderNumber = $goods->fresh()->order_number;
        app(SpjDocumentNumberService::class)->assignAutomaticNumbers($package->fresh(), '10260756');
        $this->assertSame($existingOrderNumber, $goods->fresh()->order_number);
    }
}
