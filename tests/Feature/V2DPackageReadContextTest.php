<?php

namespace Tests\Feature;

use App\Models\DocumentTemplate;
use App\Models\SpjPackage;
use App\Services\SpjPackageTemplateSelector;
use App\Services\SpjV2LegacyMigrationService;
use App\Services\SpjV2PackageReadContextService;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class V2DPackageReadContextTest extends TestCase
{
    protected function tearDown(): void
    {
        DB::purge('school');

        parent::tearDown();
    }

    public function test_v2_read_context_normalizes_only_in_memory_and_uses_effective_year_templates(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-package-read-context.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $row = $this->staleNumberedPackage($db);
            $this->activateContext($row);
            config()->set('spj.v2_read_path', 'v2');

            $package = SpjPackage::query()->with('transaction')->findOrFail($row->package_id);
            $legacyFiscalYearId = (int) $package->transaction->fiscal_year_id;
            $before = $this->protectedHash($db);

            $this->assertNotSame((int) $row->effective_fiscal_year_id, $legacyFiscalYearId);
            $this->assertTrue(app(SpjV2PackageReadContextService::class)->prepare($package));
            $this->assertSame('v2_compat', $package->getAttribute('read_context_path'));
            $this->assertSame((int) $row->effective_fiscal_year_id, (int) $package->transaction->fiscal_year_id);
            $this->assertSame((int) $row->fund_source_id, (int) $package->transaction->fund_source_id);

            $persistedFiscalYearId = (int) $db->table('transactions')
                ->where('id', $package->transaction_id)
                ->value('fiscal_year_id');
            $this->assertSame($legacyFiscalYearId, $persistedFiscalYearId);

            $staleTemplate = DocumentTemplate::query()->create([
                'fiscal_year_id' => $legacyFiscalYearId,
                'document_type' => 'V2_CONTEXT_TEST_STALE',
                'name' => 'V2 Context Stale Template',
                'format' => 'xlsx',
                'file_path' => 'document-templates/v2-context-stale.xlsx',
                'applicable_categories' => ['SEMUA'],
                'is_active' => true,
            ]);
            $effectiveTemplate = DocumentTemplate::query()->create([
                'fiscal_year_id' => (int) $row->effective_fiscal_year_id,
                'document_type' => 'V2_CONTEXT_TEST_EFFECTIVE',
                'name' => 'V2 Context Effective Template',
                'format' => 'xlsx',
                'file_path' => 'document-templates/v2-context-effective.xlsx',
                'applicable_categories' => ['SEMUA'],
                'is_active' => true,
            ]);

            $selectedIds = app(SpjPackageTemplateSelector::class)
                ->forPackage($package)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $this->assertContains((int) $effectiveTemplate->id, $selectedIds);
            $this->assertNotContains((int) $staleTemplate->id, $selectedIds);
            $this->assertSame($before, $this->protectedHash($db), 'Read compatibility must not persist transaction/Paket/document changes.');
        } finally {
            File::delete($target);
        }
    }

    public function test_legacy_config_keeps_stale_package_read_context_blocked(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-package-read-context-legacy.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $row = $this->staleNumberedPackage($db);
            $this->activateContext($row);
            config()->set('spj.v2_read_path', 'legacy');

            $package = SpjPackage::query()->with('transaction')->findOrFail($row->package_id);
            $legacyFiscalYearId = (int) $package->transaction->fiscal_year_id;
            $before = $this->protectedHash($db);

            $this->assertFalse(app(SpjV2PackageReadContextService::class)->prepare($package));
            $this->assertSame($legacyFiscalYearId, (int) $package->transaction->fiscal_year_id);
            $this->assertNull($package->getAttribute('read_context_path'));
            $this->assertSame($before, $this->protectedHash($db));
        } finally {
            File::delete($target);
        }
    }

    public function test_wrong_package_bridge_blocks_read_context_without_repair(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-package-read-context-drift.sqlite');
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

            $package = SpjPackage::query()->with('transaction')->findOrFail($row->package_id);
            $legacyFiscalYearId = (int) $package->transaction->fiscal_year_id;
            $before = $this->protectedHash($db);
            config()->set('spj.v2_read_path', 'v2');

            $this->assertFalse(app(SpjV2PackageReadContextService::class)->prepare($package));
            $this->assertSame($legacyFiscalYearId, (int) $package->transaction->fiscal_year_id);
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
            storage_path('app/v2-c-rehearsal/reports/test-v2d-package-read-context.json'),
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
