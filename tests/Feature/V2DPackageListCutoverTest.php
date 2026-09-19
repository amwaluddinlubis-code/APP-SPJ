<?php

namespace Tests\Feature;

use App\Services\SpjV2LegacyMigrationService;
use App\UseCases\Spj\SpjWorkspaceUseCase;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class V2DPackageListCutoverTest extends TestCase
{
    protected function tearDown(): void
    {
        DB::purge('school');

        parent::tearDown();
    }

    public function test_v2_package_list_and_package_metrics_follow_effective_context_membership(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-package-list-cutover.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $row = $this->staleNumberedPackage($db);
            $this->activateContext($row);
            config()->set('spj.v2_read_path', 'v2');

            $before = $this->protectedHash($db);
            $useCase = app(SpjWorkspaceUseCase::class);
            $list = $useCase->packageListData(100);
            $metrics = $useCase->overviewMetrics();
            $after = $this->protectedHash($db);

            $expectedPackageIds = $db->table('spj_packages as package')
                ->join('spj_transactions as transaction', 'transaction.id', '=', 'package.spj_transaction_id')
                ->where('transaction.fiscal_year_id', $row->effective_fiscal_year_id)
                ->where('transaction.fund_source_id', $row->fund_source_id)
                ->where('transaction.source_id', $row->source_id)
                ->where('transaction.canonical_context_status', 'ACTIVE_CANONICAL')
                ->orderBy('package.id')
                ->pluck('package.id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $actualPackageIds = collect($list->items())
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->sort()
                ->values()
                ->all();
            $expectedSorted = collect($expectedPackageIds)->sort()->values()->all();

            $this->assertNotEmpty($expectedPackageIds);
            $this->assertSame($expectedSorted, $actualPackageIds);
            $this->assertSame(count($expectedPackageIds), $list->total());
            $this->assertSame(count($expectedPackageIds), $metrics['totalPackages']);

            $expectedNumbered = (int) $db->table('spj_packages')
                ->whereIn('id', $expectedPackageIds)
                ->whereNotNull('document_number')
                ->count();
            $this->assertSame($expectedNumbered, $metrics['numberedPackages']);

            foreach ($list->items() as $package) {
                $this->assertSame('v2_compat', $package->getAttribute('read_context_path'));
                $this->assertSame((int) $row->fund_source_id, (int) $package->transaction->fund_source_id);
                $this->assertContains((int) $package->id, $expectedPackageIds);
            }

            $this->assertSame($before, $after, 'V2 Paket list/metrics must be read-only.');
        } finally {
            File::delete($target);
        }
    }

    public function test_legacy_config_rolls_package_list_back_immediately(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-package-list-rollback.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $row = $this->staleNumberedPackage($db);
            $this->activateContext($row);
            $useCase = app(SpjWorkspaceUseCase::class);

            config()->set('spj.v2_read_path', 'v2');
            $v2Ids = collect($useCase->packageListData(100)->items())
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $this->assertContains((int) $row->package_id, $v2Ids);

            config()->set('spj.v2_read_path', 'legacy');
            $legacyIds = collect($useCase->packageListData(100)->items())
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $this->assertNotContains((int) $row->package_id, $legacyIds);

            config()->set('spj.v2_read_path', 'v2');
            $restoredIds = collect($useCase->packageListData(100)->items())
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $this->assertContains((int) $row->package_id, $restoredIds);
        } finally {
            File::delete($target);
        }
    }

    public function test_wrong_package_bridge_falls_back_to_legacy_list_without_exposing_stale_package(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-package-list-drift.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $row = $this->staleNumberedPackage($db);
            $this->activateContext($row);

            $replacementId = $db->table('spj_transactions')
                ->where('fiscal_year_id', $row->effective_fiscal_year_id)
                ->where('fund_source_id', $row->fund_source_id)
                ->where('source_id', $row->source_id)
                ->where('canonical_context_status', 'ACTIVE_CANONICAL')
                ->where('id', '!=', $row->spj_transaction_id)
                ->orderBy('id')
                ->value('id');
            $this->assertNotNull($replacementId);

            $db->table('spj_packages')->where('id', $row->package_id)->update([
                'spj_transaction_id' => $replacementId,
            ]);

            $before = $this->protectedHash($db);
            config()->set('spj.v2_read_path', 'v2');

            $useCase = app(SpjWorkspaceUseCase::class);
            $ids = collect($useCase->packageListData(100)->items())
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $metrics = $useCase->overviewMetrics();

            $this->assertNotContains((int) $row->package_id, $ids);
            $this->assertSame(count($ids), $metrics['totalPackages']);
            $this->assertSame($before, $this->protectedHash($db));
        } finally {
            File::delete($target);
        }
    }

    public function test_package_list_view_marks_v2_rows_as_read_only_without_adding_mutation_actions(): void
    {
        $source = (string) file_get_contents(resource_path('views/livewire/spj-package-list.blade.php'));

        $this->assertStringContainsString("read_context_path') === 'v2_compat'", $source);
        $this->assertStringContainsString('Baca saja', $source);
        $this->assertStringContainsString('Buka baca →', $source);
        $this->assertStringNotContainsString("route('spj.update'", $source);
        $this->assertStringNotContainsString("route('spj.ready'", $source);
        $this->assertStringNotContainsString("route('spj.assign-number'", $source);
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
            storage_path('app/v2-c-rehearsal/reports/test-v2d-package-list-cutover.json'),
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

    private function staleNumberedPackage(Connection $db): object
    {
        $row = $db->table('spj_packages as package')
            ->join('transactions as legacy', 'legacy.id', '=', 'package.transaction_id')
            ->join('spj_transactions as v2', 'v2.id', '=', 'package.spj_transaction_id')
            ->where('package.status', 'NUMBERED')
            ->where('v2.canonical_context_status', 'ACTIVE_CANONICAL')
            ->whereColumn('legacy.fiscal_year_id', '!=', 'v2.fiscal_year_id')
            ->select([
                'package.id as package_id',
                'package.transaction_id as legacy_transaction_id',
                'package.spj_transaction_id',
                'v2.fiscal_year_id as effective_fiscal_year_id',
                'v2.fund_source_id',
                'v2.source_id',
            ])
            ->orderBy('package.id')
            ->first();

        $this->assertNotNull($row);

        return $row;
    }

    private function activateContext(object $row): void
    {
        session([
            'active_school_id' => 1,
            'active_fiscal_year_id' => (int) $row->effective_fiscal_year_id,
            'active_fund_source_id' => (int) $row->fund_source_id,
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
