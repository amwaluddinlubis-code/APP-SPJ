<?php

namespace Tests\Feature;

use App\Services\SpjV2LegacyMigrationService;
use App\Services\SpjV2PackageReadMembershipService;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class V2DPackageReadMembershipTest extends TestCase
{
    protected function tearDown(): void
    {
        DB::purge('school');

        parent::tearDown();
    }

    public function test_v2_membership_resolves_all_package_ids_by_effective_context_without_legacy_fiscal_year_rewrite(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-package-read-membership.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $before = $this->protectedHash($db);
            $contexts = $this->canonicalContexts($db);
            $this->assertNotEmpty($contexts);

            config()->set('spj.v2_read_path', 'v2');
            $service = app(SpjV2PackageReadMembershipService::class);
            $resolvedPackageIds = [];
            $resolvedNumberedPackageIds = [];

            foreach ($contexts as $context) {
                $membership = $service->forContext(
                    $db,
                    (int) $context->fiscal_year_id,
                    (int) $context->fund_source_id,
                );

                $this->assertNotNull($membership, 'Every canonical context in the fixture must resolve through the compatibility gate.');
                $this->assertSame((int) $context->source_id, $membership['source_id']);
                $this->assertContains($membership['compatibility_mode'], ['ALIGNED', 'STALE_COMPATIBLE']);

                $expectedPackageIds = $db->table('spj_packages as package')
                    ->join('spj_transactions as transaction', 'transaction.id', '=', 'package.spj_transaction_id')
                    ->where('transaction.fiscal_year_id', $context->fiscal_year_id)
                    ->where('transaction.fund_source_id', $context->fund_source_id)
                    ->where('transaction.source_id', $context->source_id)
                    ->where('transaction.canonical_context_status', 'ACTIVE_CANONICAL')
                    ->orderBy('package.id')
                    ->pluck('package.id')
                    ->map(fn ($id): int => (int) $id)
                    ->all();

                $this->assertSame($expectedPackageIds, $membership['package_ids']);

                $resolvedPackageIds = [...$resolvedPackageIds, ...$membership['package_ids']];
                $resolvedNumberedPackageIds = [
                    ...$resolvedNumberedPackageIds,
                    ...$db->table('spj_packages')
                        ->whereIn('id', $membership['package_ids'])
                        ->where('status', 'NUMBERED')
                        ->pluck('id')
                        ->map(fn ($id): int => (int) $id)
                        ->all(),
                ];

                $legacyFundSources = $db->table('transactions')
                    ->whereIn('id', $membership['legacy_transaction_ids'])
                    ->pluck('fund_source_id')
                    ->map(fn ($id): int => (int) $id)
                    ->unique()
                    ->values()
                    ->all();

                $this->assertSame([(int) $context->fund_source_id], $legacyFundSources);
            }

            sort($resolvedPackageIds);
            sort($resolvedNumberedPackageIds);

            $expectedAllPackageIds = $db->table('spj_packages')
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $this->assertCount(67, $expectedAllPackageIds);
            $this->assertSame($expectedAllPackageIds, $resolvedPackageIds);
            $this->assertCount(66, $resolvedNumberedPackageIds);
            $this->assertCount(67, array_unique($resolvedPackageIds));
            $this->assertSame($before, $this->protectedHash($db), 'Membership resolution must not mutate legacy/Paket/document state.');
        } finally {
            File::delete($target);
        }
    }

    public function test_legacy_configuration_keeps_membership_on_legacy_path_and_rollback_is_immediate(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-package-read-membership-rollback.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $context = $this->canonicalContexts($db)->first();
            $this->assertNotNull($context);
            $service = app(SpjV2PackageReadMembershipService::class);

            config()->set('spj.v2_read_path', 'legacy');
            $this->assertNull($service->forContext(
                $db,
                (int) $context->fiscal_year_id,
                (int) $context->fund_source_id,
            ));

            config()->set('spj.v2_read_path', 'v2');
            $this->assertNotNull($service->forContext(
                $db,
                (int) $context->fiscal_year_id,
                (int) $context->fund_source_id,
            ));

            config()->set('spj.v2_read_path', 'legacy');
            $this->assertNull($service->forContext(
                $db,
                (int) $context->fiscal_year_id,
                (int) $context->fund_source_id,
            ));
        } finally {
            File::delete($target);
        }
    }

    public function test_wrong_package_bridge_fails_closed_to_legacy_membership(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-package-read-membership-drift.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $context = $this->canonicalContexts($db)->first(
                fn (object $row): bool => (int) $db->table('spj_packages as package')
                    ->join('spj_transactions as transaction', 'transaction.id', '=', 'package.spj_transaction_id')
                    ->where('transaction.fiscal_year_id', $row->fiscal_year_id)
                    ->where('transaction.fund_source_id', $row->fund_source_id)
                    ->where('transaction.source_id', $row->source_id)
                    ->count() > 0,
            );
            $this->assertNotNull($context);

            $package = $db->table('spj_packages as package')
                ->join('spj_transactions as transaction', 'transaction.id', '=', 'package.spj_transaction_id')
                ->where('transaction.fiscal_year_id', $context->fiscal_year_id)
                ->where('transaction.fund_source_id', $context->fund_source_id)
                ->where('transaction.source_id', $context->source_id)
                ->select(['package.id', 'package.spj_transaction_id'])
                ->orderBy('package.id')
                ->first();
            $this->assertNotNull($package);

            $replacementId = $db->table('spj_transactions')
                ->where('fiscal_year_id', $context->fiscal_year_id)
                ->where('fund_source_id', $context->fund_source_id)
                ->where('source_id', $context->source_id)
                ->where('canonical_context_status', 'ACTIVE_CANONICAL')
                ->where('id', '!=', $package->spj_transaction_id)
                ->orderBy('id')
                ->value('id');
            $this->assertNotNull($replacementId);

            $db->table('spj_packages')->where('id', $package->id)->update([
                'spj_transaction_id' => $replacementId,
            ]);

            $before = $this->protectedHash($db);
            config()->set('spj.v2_read_path', 'v2');

            $this->assertNull(
                app(SpjV2PackageReadMembershipService::class)->forContext(
                    $db,
                    (int) $context->fiscal_year_id,
                    (int) $context->fund_source_id,
                ),
                'Unsafe package bridge must fail closed so the caller stays on legacy membership.',
            );
            $this->assertSame($before, $this->protectedHash($db));
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
            storage_path('app/v2-c-rehearsal/reports/test-v2d-package-read-membership.json'),
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

    /** @return Collection<int, object> */
    private function canonicalContexts(Connection $db): Collection
    {
        return $db->table('spj_transactions')
            ->where('canonical_context_status', 'ACTIVE_CANONICAL')
            ->select('fiscal_year_id', 'fund_source_id', 'source_id')
            ->distinct()
            ->orderBy('fiscal_year_id')
            ->orderBy('fund_source_id')
            ->orderBy('source_id')
            ->get();
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
