<?php

namespace Tests\Feature;

use App\Models\SpjPackage;
use App\Services\SpjPackageValidationService;
use App\Services\SpjV2LegacyMigrationService;
use App\Services\SpjV2MutationContextService;
use App\UseCases\Spj\SpjPackageLifecycleUseCase;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class V2DMutationContextReadyTest extends TestCase
{
    protected function tearDown(): void
    {
        DB::purge('school');

        parent::tearDown();
    }

    public function test_stale_draft_package_can_transition_ready_only_through_resolved_v2_context(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-mutation-ready.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $row = $this->staleDraftPackage($db);
            $this->activateEffectiveContext($row);
            config()->set('spj.v2_read_path', 'v2');

            $package = SpjPackage::query()->with('transaction')->findOrFail($row->package_id);
            $legacyFiscalYearId = (int) $package->transaction->fiscal_year_id;
            $transactionHashBefore = $this->transactionHash($db, (int) $package->transaction_id);
            $documentsHashBefore = $this->documentsHash($db, (int) $package->id);

            $mutationContext = app(SpjV2MutationContextService::class);
            $this->assertTrue($mutationContext->preparePackage($package));
            $this->assertSame('v2_compat', $mutationContext->packageContext($package)['path'] ?? null);
            $this->assertSame('v2_compat', $mutationContext->transactionContext($package->transaction)['path'] ?? null);
            $this->assertArrayNotHasKey('mutation_context_path', $package->getAttributes());
            $this->assertArrayNotHasKey('mutation_context_source_id', $package->getAttributes());
            $this->assertArrayNotHasKey('mutation_context_mode', $package->getAttributes());
            $this->assertArrayNotHasKey('mutation_context_path', $package->transaction->getAttributes());
            $this->assertArrayNotHasKey('mutation_context_source_id', $package->transaction->getAttributes());
            $this->assertFalse($package->isDirty('mutation_context_path'));
            $this->assertFalse($package->isDirty('mutation_context_source_id'));
            $this->assertFalse($package->isDirty('mutation_context_mode'));
            $this->assertSame((int) $row->effective_fiscal_year_id, (int) $package->transaction->fiscal_year_id);
            $this->assertSame(
                $legacyFiscalYearId,
                (int) $db->table('transactions')->where('id', $package->transaction_id)->value('fiscal_year_id'),
            );

            $this->mockValidationPasses();
            $result = app(SpjPackageLifecycleUseCase::class)->markReadyResult((string) $package->id);

            $this->assertTrue($result['success'], $result['message']);
            $this->assertSame('READY', (string) $db->table('spj_packages')->where('id', $package->id)->value('status'));
            $this->assertSame(
                $legacyFiscalYearId,
                (int) $db->table('transactions')->where('id', $package->transaction_id)->value('fiscal_year_id'),
                'READY compatibility must never persist the in-memory effective fiscal year to the legacy transaction.',
            );
            $this->assertSame($transactionHashBefore, $this->transactionHash($db, (int) $package->transaction_id));
            $this->assertSame($documentsHashBefore, $this->documentsHash($db, (int) $package->id));

            $audit = $db->table('operational_audit_logs')
                ->where('entity_type', 'SPJ_PACKAGE')
                ->where('entity_id', (string) $package->id)
                ->where('action', 'PAKET_READY')
                ->orderByDesc('id')
                ->first();
            $this->assertNotNull($audit);
            $this->assertSame((int) $row->effective_fiscal_year_id, (int) $audit->fiscal_year_id);
        } finally {
            File::delete($target);
        }
    }

    public function test_legacy_config_keeps_stale_ready_mutation_blocked(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-mutation-ready-legacy.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $row = $this->staleDraftPackage($db);
            $this->activateEffectiveContext($row);
            config()->set('spj.v2_read_path', 'legacy');

            $package = SpjPackage::query()->with('transaction')->findOrFail($row->package_id);
            $legacyFiscalYearId = (int) $package->transaction->fiscal_year_id;
            $this->mockValidationMustNotRun();

            $result = app(SpjPackageLifecycleUseCase::class)->markReadyResult((string) $package->id);

            $this->assertFalse($result['success']);
            $this->assertSame('DRAFT', (string) $db->table('spj_packages')->where('id', $package->id)->value('status'));
            $this->assertSame(
                $legacyFiscalYearId,
                (int) $db->table('transactions')->where('id', $package->transaction_id)->value('fiscal_year_id'),
            );
            $this->assertSame(
                0,
                $db->table('operational_audit_logs')
                    ->where('entity_type', 'SPJ_PACKAGE')
                    ->where('entity_id', (string) $package->id)
                    ->where('action', 'PAKET_READY')
                    ->count(),
            );
        } finally {
            File::delete($target);
        }
    }

    public function test_wrong_package_bridge_blocks_ready_without_repairing_legacy_context(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-mutation-ready-wrong-bridge.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $row = $this->staleDraftPackage($db);
            $this->activateEffectiveContext($row);
            config()->set('spj.v2_read_path', 'v2');

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
            $this->mockValidationMustNotRun();

            $result = app(SpjPackageLifecycleUseCase::class)->markReadyResult((string) $package->id);

            $this->assertFalse($result['success']);
            $this->assertSame('DRAFT', (string) $db->table('spj_packages')->where('id', $package->id)->value('status'));
            $this->assertSame(
                $legacyFiscalYearId,
                (int) $db->table('transactions')->where('id', $package->transaction_id)->value('fiscal_year_id'),
            );
        } finally {
            File::delete($target);
        }
    }

    public function test_effective_context_invoice_duplicate_validation_does_not_fall_back_to_stale_legacy_year(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-mutation-ready-invoice-duplicate.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $row = $this->staleDraftPackage($db);
            $this->activateEffectiveContext($row);
            config()->set('spj.v2_read_path', 'v2');

            $otherLegacyId = $db->table('legacy_transaction_v2_map as provenance')
                ->join('spj_transactions as v2', 'v2.id', '=', 'provenance.spj_transaction_id')
                ->where('v2.fiscal_year_id', $row->effective_fiscal_year_id)
                ->where('v2.fund_source_id', $row->fund_source_id)
                ->where('v2.source_id', $row->source_id)
                ->where('v2.canonical_context_status', 'ACTIVE_CANONICAL')
                ->where('provenance.legacy_transaction_id', '!=', $row->legacy_transaction_id)
                ->orderBy('provenance.legacy_transaction_id')
                ->value('provenance.legacy_transaction_id');
            $this->assertNotNull($otherLegacyId);

            $db->table('transactions')->whereIn('id', [$row->legacy_transaction_id, $otherLegacyId])->update([
                'spj_category' => 'BARANG',
                'invoice_number' => 'INV-V2-DUPLICATE',
                'vendor_name' => 'Vendor Effective Context',
            ]);

            $package = SpjPackage::query()->with([
                'transaction.items',
                'transaction.goods',
                'transaction.honors',
                'transaction.serviceRecipients',
            ])->findOrFail($row->package_id);
            $this->assertTrue(app(SpjV2MutationContextService::class)->preparePackage($package));

            $checks = collect(app(SpjPackageValidationService::class)->checklist($package))->keyBy('key');

            $this->assertArrayHasKey('invoice_duplicate', $checks->all());
            $this->assertFalse($checks['invoice_duplicate']['passed']);
        } finally {
            File::delete($target);
        }
    }

    public function test_unresolved_reconciliation_blocks_ready_before_validation(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-mutation-ready-reconciliation.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $row = $this->staleDraftPackage($db);
            $this->activateEffectiveContext($row);
            config()->set('spj.v2_read_path', 'v2');

            $db->table('transactions')->where('id', $row->legacy_transaction_id)->update([
                'requires_reconciliation' => 1,
            ]);
            $this->mockValidationMustNotRun();

            $result = app(SpjPackageLifecycleUseCase::class)->markReadyResult((string) $row->package_id);

            $this->assertFalse($result['success']);
            $this->assertSame('DRAFT', (string) $db->table('spj_packages')->where('id', $row->package_id)->value('status'));
        } finally {
            File::delete($target);
        }
    }

    public function test_raw_source_drift_blocks_ready_before_validation(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-mutation-ready-source-drift.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $row = $this->staleDraftPackage($db);
            $this->activateEffectiveContext($row);
            config()->set('spj.v2_read_path', 'v2');

            $rawRows = $db->table('spj_transaction_sources as source_link')
                ->join('arkas_source_identity_registry as identity', 'identity.id', '=', 'source_link.arkas_source_identity_id')
                ->join('arkas_raw_mirror_rows as raw', 'raw.id', '=', 'identity.current_raw_mirror_row_id')
                ->where('source_link.spj_transaction_id', $row->spj_transaction_id)
                ->orderBy('source_link.sort_order')
                ->get(['raw.id', 'raw.payload']);

            $drifted = false;
            foreach ($rawRows as $rawRow) {
                $payload = json_decode((string) $rawRow->payload, true, 512, JSON_THROW_ON_ERROR);
                if (! in_array((int) ($payload['id_ref_bku'] ?? 0), [4, 15, 24, 35], true)) {
                    continue;
                }

                foreach (['saldo', 'jumlah', 'nilai', 'nominal'] as $amountKey) {
                    if (! array_key_exists($amountKey, $payload)) {
                        continue;
                    }

                    $payload[$amountKey] = (float) $payload[$amountKey] + 12345.0;
                    $db->table('arkas_raw_mirror_rows')->where('id', $rawRow->id)->update([
                        'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    ]);
                    $drifted = true;
                    break 2;
                }
            }
            $this->assertTrue($drifted, 'Expected a canonical gross source row that can be drifted.');

            $package = SpjPackage::query()->with('transaction')->findOrFail($row->package_id);
            $legacyFiscalYearId = (int) $package->transaction->fiscal_year_id;
            $this->mockValidationMustNotRun();

            $result = app(SpjPackageLifecycleUseCase::class)->markReadyResult((string) $package->id);

            $this->assertFalse($result['success']);
            $this->assertSame('DRAFT', (string) $db->table('spj_packages')->where('id', $package->id)->value('status'));
            $this->assertSame(
                $legacyFiscalYearId,
                (int) $db->table('transactions')->where('id', $package->transaction_id)->value('fiscal_year_id'),
            );
        } finally {
            File::delete($target);
        }
    }

    public function test_legacy_aligned_context_keeps_existing_ready_behavior_without_v2_cutover(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-mutation-ready-aligned.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $row = $this->staleDraftPackage($db);
            $legacyFiscalYearId = (int) $db->table('transactions')
                ->where('id', $row->legacy_transaction_id)
                ->value('fiscal_year_id');

            session([
                'active_school_id' => 1,
                'active_fiscal_year_id' => $legacyFiscalYearId,
                'active_fund_source_id' => (int) $row->fund_source_id,
            ]);
            config()->set('spj.v2_read_path', 'legacy');
            $this->mockValidationPasses();

            $result = app(SpjPackageLifecycleUseCase::class)->markReadyResult((string) $row->package_id);

            $this->assertTrue($result['success'], $result['message']);
            $this->assertSame('READY', (string) $db->table('spj_packages')->where('id', $row->package_id)->value('status'));
            $auditYear = $db->table('operational_audit_logs')
                ->where('entity_type', 'SPJ_PACKAGE')
                ->where('entity_id', (string) $row->package_id)
                ->where('action', 'PAKET_READY')
                ->orderByDesc('id')
                ->value('fiscal_year_id');
            $this->assertSame($legacyFiscalYearId, (int) $auditYear);
        } finally {
            File::delete($target);
        }
    }

    public function test_compatibility_surfaces_expose_only_guarded_ready_entry_point(): void
    {
        $checklist = (string) file_get_contents(resource_path('views/spj/checklist.blade.php'));
        $readOnlyPackage = (string) file_get_contents(resource_path('views/spj/package-readonly.blade.php'));
        $validation = (string) file_get_contents(app_path('Services/SpjPackageValidationService.php'));

        $this->assertStringContainsString("route('spj.ready', \$package->id)", $checklist);
        $this->assertStringContainsString('@if($canMarkReady)', $checklist);
        $this->assertStringContainsString('Effective context', $checklist);
        $this->assertStringContainsString("route('spj.checklist', \$package->id)", $readOnlyPackage);
        $this->assertStringNotContainsString("route('spj.ready'", $readOnlyPackage);
        $this->assertStringContainsString("relationLoaded('v2MutationContext')", $validation);
        $this->assertStringContainsString('$transaction->source_key', $validation);
        $this->assertStringNotContainsString("setAttribute('mutation_context_", (string) file_get_contents(app_path('Services/SpjV2MutationContextService.php')));
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
            storage_path('app/v2-c-rehearsal/reports/test-v2d-mutation-ready.json'),
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

    private function staleDraftPackage(Connection $db): object
    {
        $row = $db->table('spj_packages as package')
            ->join('transactions as legacy', 'legacy.id', '=', 'package.transaction_id')
            ->join('spj_transactions as v2', 'v2.id', '=', 'package.spj_transaction_id')
            ->where('package.status', 'DRAFT')
            ->where('v2.canonical_context_status', 'ACTIVE_CANONICAL')
            ->whereColumn('legacy.fiscal_year_id', '!=', 'v2.fiscal_year_id')
            ->select([
                'package.id as package_id',
                'package.transaction_id as legacy_transaction_id',
                'package.spj_transaction_id',
                'legacy.fiscal_year_id as legacy_fiscal_year_id',
                'v2.fiscal_year_id as effective_fiscal_year_id',
                'v2.fund_source_id',
                'v2.source_id',
            ])
            ->orderBy('package.id')
            ->first();

        $this->assertNotNull($row, 'Expected the isolated fixture to contain a stale DRAFT Paket.');

        return $row;
    }

    private function activateEffectiveContext(object $row): void
    {
        session([
            'active_school_id' => 1,
            'active_fiscal_year_id' => (int) $row->effective_fiscal_year_id,
            'active_fund_source_id' => (int) $row->fund_source_id,
        ]);
    }

    private function mockValidationPasses(): void
    {
        $validator = $this->createMock(SpjPackageValidationService::class);
        $validator->expects($this->once())->method('validate')->willReturn([]);
        $this->app->instance(SpjPackageValidationService::class, $validator);
    }

    private function mockValidationMustNotRun(): void
    {
        $validator = $this->createMock(SpjPackageValidationService::class);
        $validator->expects($this->never())->method('validate');
        $this->app->instance(SpjPackageValidationService::class, $validator);
    }

    private function transactionHash(Connection $db, int $transactionId): string
    {
        $row = $db->table('transactions')->where('id', $transactionId)->first();

        return hash('sha256', json_encode((array) $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function documentsHash(Connection $db, int $packageId): string
    {
        $rows = $db->table('spj_documents')
            ->where('spj_package_id', $packageId)
            ->orderBy('id')
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();

        return hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
