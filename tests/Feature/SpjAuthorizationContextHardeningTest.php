<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureSpjActiveContext;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\SpjDocument;
use App\Models\SpjPackage;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class SpjAuthorizationContextHardeningTest extends TestCase
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

    public function test_spj_mutation_routes_require_operator_or_administrator(): void
    {
        foreach ([
            'transactions.spj-descriptions.update',
            'transactions.maintenance-links.update',
            'transactions.prepare-spj',
            'spj.update',
            'spj.ready',
            'spj.assign-number',
            'spj.documents.assign-number',
            'spj.documents.finalize',
            'spj.documents.cancel',
            'spj.documents.replace',
            'spj.payments.store',
            'spj.receipts.store',
            'arkas.sync',
            'years.synchronize',
        ] as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route, 'Route missing: '.$routeName);
            $this->assertContains('operator-or-administrator', $route->gatherMiddleware(), 'Mutation route is not role protected: '.$routeName);
        }
    }

    public function test_sensitive_administrator_routes_remain_administrator_only(): void
    {
        foreach ([
            'spj.quarter-numbering',
            'spj.quarter-close',
            'spj.quarter-reopen',
            'spj.unlock',
            'database-manager.reset',
            'school-backups.store',
            'school-backups.restore',
            'document-templates.store',
            'document-templates.mapping.update',
            'document-templates.destroy',
        ] as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route, 'Route missing: '.$routeName);
            $this->assertContains('administrator', $route->gatherMiddleware(), 'Sensitive route is not administrator protected: '.$routeName);
        }
    }

    public function test_spj_resource_routes_are_guarded_by_active_context_middleware(): void
    {
        foreach ([
            'transactions.show',
            'transactions.prepare-spj',
            'spj.checklist',
            'spj.update',
            'spj.assign-number',
            'spj.preview-package',
            'spj.documents.finalize',
        ] as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route, 'Route missing: '.$routeName);
            $this->assertContains('spj-active-context', $route->gatherMiddleware(), 'Route can cross active context: '.$routeName);
        }
    }

    public function test_active_context_middleware_rejects_cross_year_transaction(): void
    {
        [$activeYear, $activeFund, $otherYear] = $this->contextFixtures();
        $transaction = $this->transaction($otherYear, $activeFund, 'OTHER-YEAR');
        $this->activate($activeYear, $activeFund);

        $this->expectException(NotFoundHttpException::class);
        $this->runMiddleware(['transactionId' => $transaction->id]);
    }

    public function test_active_context_middleware_rejects_cross_fund_package_and_document(): void
    {
        [$activeYear, $activeFund] = $this->contextFixtures();
        $otherFund = FundSource::query()->create(['id' => 2, 'code' => 'KINERJA', 'name' => 'BOSP Kinerja']);
        $transaction = $this->transaction($activeYear, $otherFund, 'OTHER-FUND');
        $package = SpjPackage::query()->create(['transaction_id' => $transaction->id, 'status' => 'DRAFT']);
        $document = SpjDocument::query()->create([
            'spj_package_id' => $package->id,
            'document_type' => 'SPJ',
            'scope_key' => 'MAIN',
            'status' => 'NUMBERED',
            'document_number' => '001/SPJ/2026',
            'sequence_number' => 1,
            'document_date' => '2026-01-10',
            'numbered_at' => now(),
        ]);
        $this->activate($activeYear, $activeFund);

        try {
            $this->runMiddleware(['packageId' => $package->id]);
            $this->fail('Cross-fund package was not rejected.');
        } catch (NotFoundHttpException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(NotFoundHttpException::class);
        $this->runMiddleware(['documentId' => $document->id]);
    }

    public function test_active_context_middleware_accepts_matching_transaction_package_and_document(): void
    {
        [$activeYear, $activeFund] = $this->contextFixtures();
        $transaction = $this->transaction($activeYear, $activeFund, 'ACTIVE');
        $package = SpjPackage::query()->create(['transaction_id' => $transaction->id, 'status' => 'DRAFT']);
        $document = SpjDocument::query()->create([
            'spj_package_id' => $package->id,
            'document_type' => 'SPJ',
            'scope_key' => 'MAIN',
            'status' => 'NUMBERED',
            'document_number' => '001/SPJ/2026',
            'sequence_number' => 1,
            'document_date' => '2026-01-10',
            'numbered_at' => now(),
        ]);
        $this->activate($activeYear, $activeFund);

        $this->assertSame(204, $this->runMiddleware(['transactionId' => $transaction->id])->getStatusCode());
        $this->assertSame(204, $this->runMiddleware(['packageId' => $package->id])->getStatusCode());
        $this->assertSame(204, $this->runMiddleware(['documentId' => $document->id])->getStatusCode());
    }

    /** @return array{FiscalYear, FundSource, FiscalYear} */
    private function contextFixtures(): array
    {
        $activeFund = FundSource::query()->create(['id' => 1, 'code' => 'REGULER', 'name' => 'BOSP Reguler']);
        $activeYear = FiscalYear::query()->create(['year' => 2026, 'fund_source' => 'REGULER', 'fund_source_id' => $activeFund->id, 'is_active' => true]);
        $otherYear = FiscalYear::query()->create(['year' => 2025, 'fund_source' => 'REGULER', 'fund_source_id' => $activeFund->id, 'is_active' => false]);

        return [$activeYear, $activeFund, $otherYear];
    }

    private function transaction(FiscalYear $year, FundSource $fund, string $proof): Transaction
    {
        return Transaction::query()->create([
            'fiscal_year_id' => $year->id,
            'fund_source_id' => $fund->id,
            'no_bukti' => $proof,
            'transaction_date' => $year->year.'-01-10',
            'description' => 'Pengujian boundary',
            'gross_amount' => 100000,
            'tax_total' => 0,
            'net_amount' => 100000,
            'source_key' => 'TEST-'.$proof,
            'source_status' => 'ACTIVE',
        ]);
    }

    private function activate(FiscalYear $year, FundSource $fund): void
    {
        session()->put([
            'active_fiscal_year_id' => $year->id,
            'active_fund_source_id' => $fund->id,
            'active_school_id' => 1,
        ]);
    }

    /** @param array<string, int> $parameters */
    private function runMiddleware(array $parameters): Response
    {
        $request = Request::create('/spj-boundary-test', 'GET');
        $route = new class($parameters)
        {
            /** @param array<string, int> $parameters */
            public function __construct(private array $parameters) {}

            public function parameter(string $key, mixed $default = null): mixed
            {
                return $this->parameters[$key] ?? $default;
            }
        };
        $request->setRouteResolver(fn () => $route);

        return app(EnsureSpjActiveContext::class)->handle(
            $request,
            fn () => new Response('', 204)
        );
    }
}
