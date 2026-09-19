<?php

namespace Tests\Feature;

use App\Models\SpjPackage;
use App\Services\SpjV2LegacyMigrationService;
use App\Services\SpjV2MutationContextService;
use App\UseCases\Spj\SpjPackageCategoryUseCase;
use App\UseCases\Spj\SpjWorkspaceUseCase;
use App\UseCases\Spj\UpdateSpjPackageDetailsUseCase;
use Illuminate\Database\Connection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Tests\TestCase;

final class V2DPackageOverlayWriteCutoverTest extends TestCase
{
    protected function tearDown(): void
    {
        DB::purge('school');

        parent::tearDown();
    }

    public function test_stale_draft_can_save_operator_overlay_without_rewriting_legacy_context_or_source_facts(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-overlay-write.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $row = $this->stalePackage($db, 'DRAFT');
            $this->activateEffectiveContext($row);
            config()->set('spj.v2_read_path', 'v2');

            // Pin the synthetic operator overlay to a simple category while
            // preserving every source-owned fact for parity authorization.
            $db->table('transactions')->where('id', $row->legacy_transaction_id)->update([
                'spj_category' => 'BARANG',
            ]);

            $package = SpjPackage::query()->with('transaction')->findOrFail($row->package_id);
            $legacyFiscalYearId = (int) $package->transaction->fiscal_year_id;
            $sourceFactsBefore = $this->sourceFactsHash($db, (int) $package->transaction_id);
            $documentsBefore = $this->documentsHash($db, (int) $package->id);

            $mutationContext = app(SpjV2MutationContextService::class);
            $this->assertTrue($mutationContext->authorizePackageWrite($package));
            $this->assertSame('v2_compat', $mutationContext->packageContext($package)['path'] ?? null);
            $this->assertSame($legacyFiscalYearId, (int) $package->transaction->fiscal_year_id);
            $this->assertFalse($package->transaction->isDirty('fiscal_year_id'));

            app(UpdateSpjPackageDetailsUseCase::class)->handle(
                (string) $package->id,
                $this->manualRequest($package->transaction),
            );

            $transaction = $db->table('transactions')->where('id', $package->transaction_id)->first();
            $this->assertNotNull($transaction);
            $this->assertSame($legacyFiscalYearId, (int) $transaction->fiscal_year_id);
            $this->assertSame('STEP 11B - Uraian overlay', (string) $transaction->payment_description);
            $this->assertSame('tunai', (string) $transaction->payment_method);
            $this->assertSame('Penerima Step 11B', (string) $transaction->receipt_recipient_name);
            $this->assertSame('Vendor Step 11B', (string) $transaction->vendor_name);
            $this->assertSame($sourceFactsBefore, $this->sourceFactsHash($db, (int) $package->transaction_id));
            $this->assertSame('DRAFT', (string) $db->table('spj_packages')->where('id', $package->id)->value('status'));
            $this->assertSame($documentsBefore, $this->documentsHash($db, (int) $package->id));

            $audit = $db->table('operational_audit_logs')
                ->where('entity_type', 'SPJ_PACKAGE')
                ->where('entity_id', (string) $package->id)
                ->where('action', 'PERBARUI_ISIAN')
                ->orderByDesc('id')
                ->first();
            $this->assertNotNull($audit);
            $this->assertSame((int) $row->effective_fiscal_year_id, (int) $audit->fiscal_year_id);
        } finally {
            File::delete($target);
        }
    }

    public function test_stale_ready_category_change_demotes_to_draft_without_context_rewrite(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-overlay-category.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $row = $this->stalePackage($db, 'DRAFT');
            $this->activateEffectiveContext($row);
            config()->set('spj.v2_read_path', 'v2');

            $db->table('transactions')->where('id', $row->legacy_transaction_id)->update([
                'spj_category' => 'BARANG',
            ]);
            $db->table('spj_packages')->where('id', $row->package_id)->update([
                'status' => 'READY',
            ]);

            $legacyFiscalYearId = (int) $db->table('transactions')
                ->where('id', $row->legacy_transaction_id)
                ->value('fiscal_year_id');

            $request = Request::create('/spj/category', 'PUT', [
                'spj_category' => 'JASA_LAINNYA',
            ]);
            $request->headers->set('Accept', 'application/json');

            $response = app(SpjPackageCategoryUseCase::class)->switchCategory(
                (string) $row->package_id,
                $request,
            );

            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame(
                'JASA_LAINNYA',
                (string) $db->table('transactions')->where('id', $row->legacy_transaction_id)->value('spj_category'),
            );
            $this->assertSame(
                $legacyFiscalYearId,
                (int) $db->table('transactions')->where('id', $row->legacy_transaction_id)->value('fiscal_year_id'),
            );
            $this->assertSame('DRAFT', (string) $db->table('spj_packages')->where('id', $row->package_id)->value('status'));

            $audit = $db->table('operational_audit_logs')
                ->where('entity_type', 'SPJ_PACKAGE')
                ->where('entity_id', (string) $row->package_id)
                ->where('action', 'UBAH_KATEGORI')
                ->orderByDesc('id')
                ->first();
            $this->assertNotNull($audit);
            $this->assertSame((int) $row->effective_fiscal_year_id, (int) $audit->fiscal_year_id);
            $this->assertStringContainsString('DRAFT', (string) $audit->description);
        } finally {
            File::delete($target);
        }
    }

    public function test_legacy_config_blocks_stale_overlay_write(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-overlay-write-legacy.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $row = $this->stalePackage($db, 'DRAFT');
            $this->activateEffectiveContext($row);
            config()->set('spj.v2_read_path', 'legacy');

            $before = $db->table('transactions')->where('id', $row->legacy_transaction_id)->first();
            $this->assertNotNull($before);

            app(UpdateSpjPackageDetailsUseCase::class)->handle(
                (string) $row->package_id,
                Request::create('/spj/update', 'PUT', [
                    'payment_description' => 'FORGED LEGACY CONFIG WRITE',
                ]),
            );

            $after = $db->table('transactions')->where('id', $row->legacy_transaction_id)->first();
            $this->assertSame($before->payment_description, $after?->payment_description);
            $this->assertSame((int) $before->fiscal_year_id, (int) $after?->fiscal_year_id);
            $this->assertSame(
                0,
                $db->table('operational_audit_logs')
                    ->where('entity_type', 'SPJ_PACKAGE')
                    ->where('entity_id', (string) $row->package_id)
                    ->where('action', 'PERBARUI_ISIAN')
                    ->count(),
            );
        } finally {
            File::delete($target);
        }
    }

    public function test_numbered_effective_context_package_remains_locked_for_overlay_write(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-overlay-numbered-lock.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $row = $this->stalePackage($db, 'NUMBERED');
            $this->activateEffectiveContext($row);
            config()->set('spj.v2_read_path', 'v2');

            $package = SpjPackage::query()->with('transaction')->findOrFail($row->package_id);
            $mutationContext = app(SpjV2MutationContextService::class);
            $this->assertTrue($mutationContext->authorizePackageWrite($package));
            $before = $package->transaction->payment_description;
            $legacyFiscalYearId = (int) $package->transaction->fiscal_year_id;

            app(UpdateSpjPackageDetailsUseCase::class)->handle(
                (string) $package->id,
                Request::create('/spj/update', 'PUT', [
                    'payment_description' => 'FORGED NUMBERED V2 WRITE',
                ]),
            );

            $this->assertSame($before, $package->transaction->fresh()->payment_description);
            $this->assertSame($legacyFiscalYearId, (int) $package->transaction->fresh()->fiscal_year_id);
            $this->assertSame('NUMBERED', (string) $package->fresh()->status);
        } finally {
            File::delete($target);
        }
    }

    public function test_workspace_exposes_dedicated_overlay_editor_without_normalizing_fiscal_year(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-overlay-editor.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $row = $this->stalePackage($db, 'DRAFT');
            $this->activateEffectiveContext($row);
            config()->set('spj.v2_read_path', 'v2');

            $response = app(SpjWorkspaceUseCase::class)->handle(Request::create('/spj', 'GET', [
                'tab' => 'paket',
                'package_id' => $row->package_id,
                'edit' => 1,
            ]));

            $this->assertInstanceOf(View::class, $response);
            $this->assertSame('spj.package-compat-edit', $response->name());
            $this->assertSame(
                (int) $row->legacy_fiscal_year_id,
                (int) $response->getData()['transaction']->fiscal_year_id,
            );

            $editor = (string) file_get_contents(resource_path('views/spj/package-compat-edit.blade.php'));
            $readOnly = (string) file_get_contents(resource_path('views/spj/package-readonly.blade.php'));
            $maintenance = (string) file_get_contents(resource_path('views/spj/partials/package/categories/pemeliharaan.blade.php'));
            $categoryJs = (string) file_get_contents(resource_path('js/spj-package-manual-category.js'));

            $summary = (string) file_get_contents(resource_path('views/spj/partials/package/transaction-summary.blade.php'));

            $this->assertStringContainsString('data-compatibility-editor="1"', $editor);
            $this->assertStringContainsString('$transactionDetailIdentifier = $transaction->source_key', $editor);
            $this->assertStringContainsString('$activeSpjDocument = $package->documents', $editor);
            $this->assertStringContainsString('$transactionDetailIdentifier ?? $transaction->id', $summary);
            $this->assertStringContainsString("route('spj.update', \$package->id)", $editor);
            $this->assertStringNotContainsString("route('spj.quarter-numbering'", $editor);
            $this->assertStringNotContainsString("route('spj.assign-number'", $editor);
            $this->assertStringNotContainsString("route('spj.bulk-finalize'", $editor);
            $this->assertStringContainsString("'edit' => 1", $readOnly);
            $this->assertStringContainsString('@unless($compatibilityOverlayEdit ?? false)', $maintenance);
            $this->assertStringContainsString("form.dataset.compatibilityEditor === '1'", $categoryJs);
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
            storage_path('app/v2-c-rehearsal/reports/test-v2d-overlay-write.json'),
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

    private function stalePackage(Connection $db, string $status): object
    {
        $row = $db->table('spj_packages as package')
            ->join('transactions as legacy', 'legacy.id', '=', 'package.transaction_id')
            ->join('spj_transactions as v2', 'v2.id', '=', 'package.spj_transaction_id')
            ->where('package.status', $status)
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

        $this->assertNotNull($row, "Expected the isolated fixture to contain a stale {$status} Paket.");

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

    private function manualRequest(object $transaction): Request
    {
        return Request::create('/spj/update', 'PUT', [
            'spj_category' => 'BARANG',
            'payment_description' => 'STEP 11B - Uraian overlay',
            'payment_reference' => 'STEP11B-REF',
            'payment_method' => 'tunai',
            'receipt_recipient_name' => 'Penerima Step 11B',
            'vendor_name' => 'Vendor Step 11B',
            'vendor_owner' => 'Pemilik Step 11B',
            'vendor_npwp' => '00.000.000.0-000.000',
            'invoice_number' => 'INV-STEP11B',
            'invoice_date' => $transaction->transaction_date?->format('Y-m-d'),
            'invoice_status' => 'Lunas',
            'siplah_order_number' => 'ORDER-STEP11B',
        ]);
    }

    private function sourceFactsHash(Connection $db, int $transactionId): string
    {
        $row = $db->table('transactions')->where('id', $transactionId)->first([
            'fiscal_year_id',
            'fund_source_id',
            'id_kas_umum',
            'no_bukti',
            'transaction_date',
            'rkas_date',
            'description',
            'activity_code',
            'activity_name',
            'account_code',
            'account_name',
            'recipient_name',
            'gross_amount',
            'ppn',
            'pph21',
            'pph22',
            'pph23',
            'pph4',
            'sspd',
            'tax_total',
            'net_amount',
            'source_key',
            'source_status',
            'requires_reconciliation',
        ]);

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
