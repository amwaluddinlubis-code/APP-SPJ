<?php

namespace Tests\Feature;

use App\Services\SpjV2LegacyMigrationService;
use App\UseCases\Spj\ExtendedSpjReportUseCase;
use Illuminate\Database\Connection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Tests\TestCase;

final class V2DExtendedReportContextCutoverTest extends TestCase
{
    protected function tearDown(): void
    {
        DB::purge('school');

        parent::tearDown();
    }

    public function test_honor_and_service_selectors_use_effective_legacy_transaction_membership(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-extended-report-context.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            [$context, $honorTransactionId, $serviceTransactionId] = $this->seedSyntheticOperatorOverlays($db);
            $this->activateContext($context);
            $before = $this->protectedHash($db);

            $useCase = app(ExtendedSpjReportUseCase::class);

            config()->set('spj.v2_read_path', 'legacy');
            $legacyHonorIds = $useCase->selectionTransactions('HONOR_PEGAWAI')->modelKeys();
            $legacyServiceIds = $useCase->selectionTransactions('JASA_LAINNYA')->modelKeys();
            $this->assertNotContains($honorTransactionId, $legacyHonorIds);
            $this->assertNotContains($serviceTransactionId, $legacyServiceIds);

            config()->set('spj.v2_read_path', 'v2');
            $v2HonorIds = $useCase->selectionTransactions('HONOR_PEGAWAI')->modelKeys();
            $v2ServiceIds = $useCase->selectionTransactions('JASA_LAINNYA')->modelKeys();
            $this->assertContains($honorTransactionId, $v2HonorIds);
            $this->assertContains($serviceTransactionId, $v2ServiceIds);

            $honorView = $useCase->composeHonorPayments(Request::create('/spj/laporan/honor/susun', 'POST', [
                'transaction_ids' => [$honorTransactionId],
            ]));
            $this->assertInstanceOf(View::class, $honorView);
            $this->assertSame('spj-reports.honor-compose', $honorView->name());
            $this->assertSame([$honorTransactionId], $honorView->getData()['transactions']->modelKeys());

            $serviceView = $useCase->composeServiceRecipients(Request::create('/spj/laporan/jasa/susun', 'POST', [
                'transaction_ids' => [$serviceTransactionId],
            ]));
            $this->assertInstanceOf(View::class, $serviceView);
            $this->assertSame('spj-reports.service-recipient-compose', $serviceView->name());
            $this->assertSame([$serviceTransactionId], $serviceView->getData()['transactions']->modelKeys());

            $this->assertSame($before, $this->protectedHash($db), 'Honor/Jasa read-context cutover must not mutate protected transaction/Paket/document state.');
        } finally {
            File::delete($target);
        }
    }

    public function test_effective_context_does_not_authorize_transaction_outside_membership(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-extended-report-isolation.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            [$context, $honorTransactionId] = $this->seedSyntheticOperatorOverlays($db);
            $this->activateContext($context);
            config()->set('spj.v2_read_path', 'v2');

            $membershipIds = app(\App\Services\SpjV2PackageReadMembershipService::class)
                ->forContext(
                    $db,
                    (int) $context->fiscal_year_id,
                    (int) $context->fund_source_id,
                )['legacy_transaction_ids'] ?? [];

            $outsideId = $db->table('transactions')
                ->whereNotIn('id', $membershipIds)
                ->orderBy('id')
                ->value('id');

            if ($outsideId !== null) {
                $outsideId = (int) $outsideId;
                $db->table('transactions')->where('id', $outsideId)->update([
                    'spj_category' => 'JASA_LAINNYA',
                ]);
                $db->table('spj_service_recipients')->insert([
                    'transaction_id' => $outsideId,
                    'name' => 'Outside Context Recipient',
                    'service_type' => 'Regression',
                    'service_description' => 'Outside effective context',
                    'quantity' => 1,
                    'unit' => 'kegiatan',
                    'rental_days' => 1,
                    'daily_rate' => 1000,
                    'amount' => 1000,
                    'tax_amount' => 0,
                    'net_amount' => 1000,
                    'is_receipt_recipient' => 1,
                    'sort_order' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $outsideId = PHP_INT_MAX;
            }

            $useCase = app(ExtendedSpjReportUseCase::class);
            $serviceIds = $useCase->selectionTransactions('JASA_LAINNYA')->modelKeys();

            $this->assertContains($honorTransactionId, $useCase->selectionTransactions('HONOR_PEGAWAI')->modelKeys());
            $this->assertNotContains($outsideId, $serviceIds);

            $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
            $useCase->composeServiceRecipients(Request::create('/spj/laporan/jasa/susun', 'POST', [
                'transaction_ids' => [$outsideId],
            ]));
        } finally {
            File::delete($target);
        }
    }

    public function test_config_rollback_restores_legacy_selector_immediately(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-extended-report-rollback.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            [$context, $honorTransactionId, $serviceTransactionId] = $this->seedSyntheticOperatorOverlays($db);
            $this->activateContext($context);

            $useCase = app(ExtendedSpjReportUseCase::class);

            config()->set('spj.v2_read_path', 'v2');
            $this->assertContains($honorTransactionId, $useCase->selectionTransactions('HONOR_PEGAWAI')->modelKeys());
            $this->assertContains($serviceTransactionId, $useCase->selectionTransactions('JASA_LAINNYA')->modelKeys());

            config()->set('spj.v2_read_path', 'legacy');
            $this->assertNotContains($honorTransactionId, $useCase->selectionTransactions('HONOR_PEGAWAI')->modelKeys());
            $this->assertNotContains($serviceTransactionId, $useCase->selectionTransactions('JASA_LAINNYA')->modelKeys());
        } finally {
            File::delete($target);
        }
    }

    private function prepareClone(string $target): string
    {
        $sourceClone = storage_path('app/school-databases/10260786/spj.sqlite');
        $source = $this->sourcePath();
        $this->assertFileExists($sourceClone);
        $this->assertNotSame('', $source, 'No readable ARKAS evidence source was found for the V2-D rehearsal.');
        File::ensureDirectoryExists(dirname($target));
        File::copy($sourceClone, $target);

        return $source;
    }

    private function migrateAndProject(): void
    {
        Artisan::call('migrate', [
            '--database' => 'school',
            '--path' => 'database/migrations/v2-rehearsal',
            '--force' => true,
            '--no-interaction' => true,
        ]);

        $db = DB::connection('school');
        $migration = app(SpjV2LegacyMigrationService::class)->migrate(
            $db,
            1,
            true,
            storage_path('app/v2-c-rehearsal/reports/test-v2d-extended-report-context.json'),
            10260756,
        );

        $this->assertSame([], $migration['errors']);
        $this->assertSame('PASS', app(SpjV2LegacyMigrationService::class)->verify($db)['status']);
    }

    private function connect(string $target, string $source): void
    {
        config()->set('database.connections.school.database', $target);
        config()->set('spj.v2_b_isolated_manifest', [
            'target_path' => $target,
            'npsn' => 10260756,
            'source_id' => 1,
            'source_identity_npsn' => 10260756,
            'source_path' => $source,
            'source_read_only' => true,
            'query_only' => true,
            'source_unavailable' => false,
            'mode' => null,
        ]);
        DB::purge('school');
    }

    private function sourcePath(): string
    {
        $configured = getenv('SPJ_V2_C_SOURCE_PATH') ?: config('spj.v2_c_source_path');
        $candidates = array_filter([
            is_string($configured) ? $configured : null,
            base_path('../../backupdata/datasmp.db'),
            storage_path('app/datasmp.db'),
        ]);

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    /** @return array{0:object,1:int,2:int} */
    private function seedSyntheticOperatorOverlays(Connection $db): array
    {
        $context = $db->table('spj_packages as package')
            ->join('transactions as legacy', 'legacy.id', '=', 'package.transaction_id')
            ->join('spj_transactions as v2', 'v2.id', '=', 'package.spj_transaction_id')
            ->where('v2.canonical_context_status', 'ACTIVE_CANONICAL')
            ->whereColumn('legacy.fiscal_year_id', '!=', 'v2.fiscal_year_id')
            ->select([
                'v2.fiscal_year_id',
                'v2.fund_source_id',
                'v2.source_id',
            ])
            ->orderBy('v2.fiscal_year_id')
            ->orderBy('v2.fund_source_id')
            ->first();
        $this->assertNotNull($context);

        $candidateIds = $db->table('legacy_transaction_v2_map as map')
            ->join('transactions as legacy', 'legacy.id', '=', 'map.legacy_transaction_id')
            ->join('spj_transactions as v2', 'v2.id', '=', 'map.spj_transaction_id')
            ->join('transaction_items as item', 'item.transaction_id', '=', 'map.legacy_transaction_id')
            ->where('v2.fiscal_year_id', $context->fiscal_year_id)
            ->where('v2.fund_source_id', $context->fund_source_id)
            ->where('v2.source_id', $context->source_id)
            ->where('v2.canonical_context_status', 'ACTIVE_CANONICAL')
            ->whereColumn('legacy.fiscal_year_id', '!=', 'v2.fiscal_year_id')
            ->orderBy('map.legacy_transaction_id')
            ->pluck('map.legacy_transaction_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->take(2)
            ->all();
        $this->assertCount(2, $candidateIds);

        [$honorTransactionId, $serviceTransactionId] = $candidateIds;
        $db->table('transactions')->where('id', $honorTransactionId)->update(['spj_category' => 'HONOR_PEGAWAI']);
        $db->table('transactions')->where('id', $serviceTransactionId)->update(['spj_category' => 'JASA_LAINNYA']);

        $itemId = (int) $db->table('transaction_items')
            ->where('transaction_id', $honorTransactionId)
            ->orderBy('id')
            ->value('id');
        $this->assertGreaterThan(0, $itemId);

        $db->table('spj_honors')->insert([
            'transaction_item_id' => $itemId,
            'name' => 'Effective Honor Recipient',
            'position' => 'Regression',
            'honor_months' => 1,
            'rate_per_unit' => 1000,
            'gross_amount' => 1000,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'net_amount' => 1000,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $db->table('spj_service_recipients')->insert([
            'transaction_id' => $serviceTransactionId,
            'name' => 'Effective Service Recipient',
            'service_type' => 'Regression',
            'service_description' => 'Effective context',
            'quantity' => 1,
            'unit' => 'kegiatan',
            'rental_days' => 1,
            'daily_rate' => 1000,
            'amount' => 1000,
            'tax_amount' => 0,
            'net_amount' => 1000,
            'is_receipt_recipient' => 1,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$context, $honorTransactionId, $serviceTransactionId];
    }

    private function activateContext(object $context): void
    {
        session([
            'active_school_id' => 1,
            'active_fiscal_year_id' => (int) $context->fiscal_year_id,
            'active_fund_source_id' => (int) $context->fund_source_id,
        ]);
    }

    private function protectedHash(Connection $db): string
    {
        return hash('sha256', json_encode([
            'transactions' => $db->table('transactions')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
            'packages' => $db->table('spj_packages')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
            'documents' => $db->table('spj_documents')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
