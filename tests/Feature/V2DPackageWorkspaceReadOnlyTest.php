<?php

namespace Tests\Feature;

use App\Services\SpjV2LegacyMigrationService;
use App\UseCases\Spj\SpjWorkspaceUseCase;
use Illuminate\Database\Connection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Tests\TestCase;

final class V2DPackageWorkspaceReadOnlyTest extends TestCase
{
    protected function tearDown(): void
    {
        DB::purge('school');

        parent::tearDown();
    }

    public function test_stale_package_opens_dedicated_read_only_workspace_under_resolved_v2_context(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-package-workspace-readonly.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $row = $this->staleNumberedPackage($db);
            $this->activateContext($row);
            config()->set('spj.v2_read_path', 'v2');

            $legacyFiscalYearId = (int) $db->table('transactions')
                ->where('id', $row->legacy_transaction_id)
                ->value('fiscal_year_id');
            $before = $this->protectedHash($db);

            $response = app(SpjWorkspaceUseCase::class)->handle(Request::create('/spj', 'GET', [
                'tab' => 'paket',
                'package_id' => $row->package_id,
            ]));

            $this->assertInstanceOf(View::class, $response);
            $this->assertSame('spj.package-readonly', $response->name());

            $data = $response->getData();
            $package = $data['package'];
            $this->assertSame('v2_compat', $package->getAttribute('read_context_path'));
            $this->assertSame((int) $row->effective_fiscal_year_id, (int) $package->transaction->fiscal_year_id);
            $this->assertSame((int) $row->fund_source_id, (int) $package->transaction->fund_source_id);
            $this->assertSame((int) $row->package_id, (int) $package->id);
            $this->assertSame((int) $row->spj_transaction_id, (int) $package->spj_transaction_id);
            $this->assertTrue(collect($data['templates'])->every(
                fn ($template): bool => (int) $template->fiscal_year_id === (int) $row->effective_fiscal_year_id,
            ));

            $persistedFiscalYearId = (int) $db->table('transactions')
                ->where('id', $row->legacy_transaction_id)
                ->value('fiscal_year_id');
            $this->assertSame($legacyFiscalYearId, $persistedFiscalYearId);
            $this->assertSame($before, $this->protectedHash($db));

            $viewSource = (string) file_get_contents(resource_path('views/spj/package-readonly.blade.php'));
            foreach ([
                "route('spj.update'",
                "route('spj.ready'",
                "route('spj.assign-number'",
                "route('spj.documents.assign-number'",
                "route('spj.documents.finalize'",
                "route('spj.documents.cancel'",
                "route('spj.documents.replace'",
                "route('spj.quarter-numbering'",
            ] as $mutationRoute) {
                $this->assertStringNotContainsString($mutationRoute, $viewSource);
            }
            foreach ([
                "route('spj.preview-package'",
                "route('spj.download-package-excel'",
                "route('spj.download'",
                "route('spj.preview-template'",
                "route('spj.download-template'",
                "route('spj.download-template-pdf'",
            ] as $readRoute) {
                $this->assertStringContainsString($readRoute, $viewSource);
            }
        } finally {
            File::delete($target);
        }
    }

    public function test_legacy_config_keeps_stale_package_out_of_workspace(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-package-workspace-legacy.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $row = $this->staleNumberedPackage($db);
            $this->activateContext($row);
            config()->set('spj.v2_read_path', 'legacy');

            $response = app(SpjWorkspaceUseCase::class)->handle(Request::create('/spj', 'GET', [
                'tab' => 'paket',
                'package_id' => $row->package_id,
            ]));

            $this->assertInstanceOf(RedirectResponse::class, $response);
            $this->assertStringContainsString('tab=persiapan', $response->getTargetUrl());
        } finally {
            File::delete($target);
        }
    }

    public function test_wrong_package_bridge_keeps_stale_package_out_of_read_only_workspace(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-package-workspace-drift.sqlite');
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

            $response = app(SpjWorkspaceUseCase::class)->handle(Request::create('/spj', 'GET', [
                'tab' => 'paket',
                'package_id' => $row->package_id,
            ]));

            $this->assertInstanceOf(RedirectResponse::class, $response);
            $this->assertStringContainsString('tab=persiapan', $response->getTargetUrl());
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
            storage_path('app/v2-c-rehearsal/reports/test-v2d-package-workspace-readonly.json'),
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
