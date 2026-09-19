<?php

namespace Tests\Feature;

use App\Services\SpjV2EffectiveContextCompatibilityService;
use App\Services\SpjV2LegacyMigrationService;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class V2DEffectiveContextCompatibilityTest extends TestCase
{
    protected function tearDown(): void
    {
        DB::purge('school');

        parent::tearDown();
    }

    public function test_real_fixture_audits_stale_effective_context_without_mutating_legacy_or_package_state(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-effective-context.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $before = $this->protectedHash($db);
            $service = app(SpjV2EffectiveContextCompatibilityService::class);
            $audit = $service->audit($db);
            $after = $this->protectedHash($db);

            $this->writeAuditReport($audit);

            $this->assertSame('COMPATIBLE_STALE_CONTEXT', $audit['status'], $this->diagnostic($audit));
            $this->assertSame(67, $audit['packages']['total']);
            $this->assertSame(66, $audit['packages']['numbered']);
            $this->assertSame(0, $audit['packages']['unsafe'], $this->diagnostic($audit));
            $this->assertGreaterThan(0, $audit['packages']['stale_legacy_fiscal_year']);
            $this->assertGreaterThan(0, $audit['packages']['duplicate_provenance']);
            $this->assertSame(67, array_sum($audit['packages']['classification']));
            $this->assertGreaterThan(0, $audit['provenance']['stale_legacy_fiscal_year']);
            $this->assertSame(0, $audit['provenance']['fund_source_mismatch']);
            $this->assertSame(0, $audit['counts']['blocked_contexts']);

            $context = $this->numberedContext($db);
            $resolved = $service->resolve(
                $db,
                (int) $context->fiscal_year_id,
                (int) $context->fund_source_id,
                (int) $context->source_id,
            );

            $this->assertSame('RESOLVED', $resolved['status'], $this->diagnostic($resolved));
            $this->assertContains($resolved['mode'], ['ALIGNED', 'STALE_COMPATIBLE']);
            $this->assertNotEmpty($resolved['canonical_transaction_ids']);
            $this->assertNotEmpty($resolved['package_ids']);

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

            $this->assertSame($expectedPackageIds, $resolved['package_ids']);
            $this->assertSame([], $resolved['unsafe']);
            $this->assertSame($before, $after, 'Effective-context audit must be fully read-only.');
        } finally {
            File::delete($target);
        }
    }

    public function test_wrong_package_bridge_blocks_effective_context_resolver(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-effective-context-drift.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $context = $this->numberedContext($db);
            $package = $db->table('spj_packages as package')
                ->join('spj_transactions as transaction', 'transaction.id', '=', 'package.spj_transaction_id')
                ->where('transaction.fiscal_year_id', $context->fiscal_year_id)
                ->where('transaction.fund_source_id', $context->fund_source_id)
                ->where('transaction.source_id', $context->source_id)
                ->where('transaction.canonical_context_status', 'ACTIVE_CANONICAL')
                ->where('package.status', 'NUMBERED')
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
            $resolved = app(SpjV2EffectiveContextCompatibilityService::class)->resolve(
                $db,
                (int) $context->fiscal_year_id,
                (int) $context->fund_source_id,
                (int) $context->source_id,
            );
            $after = $this->protectedHash($db);

            $this->assertSame('BLOCKED', $resolved['status']);
            $this->assertSame('UNSAFE', $resolved['mode']);
            $this->assertTrue(
                collect($resolved['unsafe'])->contains(
                    fn (array $issue): bool => in_array(
                        $issue['type'] ?? '',
                        ['PACKAGE_PROVENANCE_LINK_MISMATCH', 'MULTIPLE_PACKAGES_PER_CANONICAL_TRANSACTION'],
                        true,
                    ),
                ),
                $this->diagnostic($resolved),
            );
            $this->assertSame($before, $after, 'Resolver must not repair or rewrite an unsafe Paket bridge.');
        } finally {
            File::delete($target);
        }
    }

    public function test_missing_v2_schema_is_unavailable_and_never_guessed(): void
    {
        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');

        $resolved = app(SpjV2EffectiveContextCompatibilityService::class)->resolve(
            DB::connection('school'),
            1,
            1,
            1,
        );

        $this->assertSame('UNAVAILABLE', $resolved['status']);
        $this->assertSame('UNAVAILABLE', $resolved['mode']);
        $this->assertSame([], $resolved['canonical_transaction_ids']);
        $this->assertSame([], $resolved['package_ids']);
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
            storage_path('app/v2-c-rehearsal/reports/test-v2d-effective-context-migration.json'),
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

    private function numberedContext(Connection $db): object
    {
        $context = $db->table('spj_packages as package')
            ->join('spj_transactions as transaction', 'transaction.id', '=', 'package.spj_transaction_id')
            ->whereNotNull('package.document_number')
            ->where('transaction.canonical_context_status', 'ACTIVE_CANONICAL')
            ->select([
                'transaction.fiscal_year_id',
                'transaction.fund_source_id',
                'transaction.source_id',
            ])
            ->orderBy('transaction.fiscal_year_id')
            ->orderBy('transaction.fund_source_id')
            ->orderBy('transaction.source_id')
            ->first();

        $this->assertNotNull($context);

        return $context;
    }

    /** @param array<string, mixed> $result */
    private function writeAuditReport(array $result): void
    {
        $path = storage_path('app/v2-c-rehearsal/reports/test-v2d-effective-context-compatibility.json');
        File::ensureDirectoryExists(dirname($path));
        File::put(
            $path,
            json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );
    }

    /** @param array<string, mixed> $result */
    private function diagnostic(array $result): string
    {
        return json_encode(
            $result,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
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
