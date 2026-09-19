<?php

namespace Tests\Feature;

use App\Livewire\TransactionDetailWorkspace;
use App\Models\FiscalYear;
use App\Models\SpjPackage;
use App\Models\User;
use App\Services\SpjV2EffectiveNumberingPeriodResolver;
use App\Services\SpjV2LegacyMigrationService;
use App\Services\SpjV2MutationContextService;
use App\Services\SpjV2NumberingAuthorizationService;
use App\UseCases\Spj\SpjPackageCategoryUseCase;
use App\UseCases\Spj\SpjSingleNumberingUseCase;
use App\UseCases\Spj\SpjWorkspaceUseCase;
use App\UseCases\Spj\UpdateSpjPackageDetailsUseCase;
use Illuminate\Database\Connection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Livewire\Livewire;
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

    public function test_stale_numbered_package_allows_only_narrative_correction_without_opening_lifecycle_mutations(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-numbered-description.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $row = $this->stalePackage($db, 'NUMBERED');
            $this->activateEffectiveContext($row);
            config()->set('spj.v2_read_path', 'v2');

            $package = SpjPackage::query()->with('transaction')->findOrFail($row->package_id);
            $before = $db->table('spj_packages')->where('id', $package->id)->first();
            $transactionBefore = $db->table('transactions')->where('id', $package->transaction_id)->first();
            $documentsBefore = $this->documentsHash($db, (int) $package->id);

            app(UpdateSpjPackageDetailsUseCase::class)->handle(
                (string) $package->id,
                Request::create('/spj/update', 'PUT', [
                    'payment_description' => 'STEP 11C - Koreksi narasi NUMBERED',
                    'vendor_name' => 'FORBIDDEN NUMBERED VENDOR',
                    'payment_method' => 'transfer_bank',
                    'spj_category' => 'JASA_LAINNYA',
                ]),
            );

            $after = $db->table('spj_packages')->where('id', $package->id)->first();
            $transaction = $db->table('transactions')->where('id', $package->transaction_id)->first();

            $this->assertSame('NUMBERED', (string) $after->status);
            $this->assertSame((string) $before->document_number, (string) $after->document_number);
            $this->assertSame('STEP 11C - Koreksi narasi NUMBERED', (string) $transaction->payment_description);
            $this->assertNotSame('FORBIDDEN NUMBERED VENDOR', (string) $transaction->vendor_name);
            $this->assertSame((string) $transactionBefore->payment_method, (string) $transaction->payment_method);
            $this->assertSame($documentsBefore, $this->documentsHash($db, (int) $package->id));
            $this->assertSame(
                (int) $row->effective_fiscal_year_id,
                (int) $db->table('operational_audit_logs')
                    ->where('entity_type', 'SPJ_PACKAGE')
                    ->where('entity_id', (string) $package->id)
                    ->where('action', 'KOREKSI_URAIAN_NUMBERED')
                    ->latest('id')
                    ->value('fiscal_year_id'),
            );
        } finally {
            File::delete($target);
        }
    }

    public function test_stale_numbered_transaction_detail_resolves_only_with_exact_item_source_parity(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-transaction-detail-description.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $row = $this->stalePackage($db, 'NUMBERED');
            $this->activateEffectiveContext($row);
            config()->set('spj.v2_read_path', 'v2');

            $package = SpjPackage::query()->with(['transaction.items', 'transaction.spjPackage'])->findOrFail($row->package_id);
            $transaction = $package->transaction;
            $resolver = app(SpjV2MutationContextService::class);

            $resolved = $resolver->resolveTransactionForDescription((string) $transaction->source_key);
            $this->assertNotNull($resolved);
            $this->assertSame((int) $transaction->id, (int) $resolved->id);
            $this->assertTrue($resolver->authorizeTransactionDescription($resolved));
            $this->assertSame('v2_compat', $resolver->transactionContext($resolved)['path'] ?? null);

            $actor = User::factory()->create(['role' => User::ROLE_OPERATOR]);
            $item = $transaction->items->first();
            Livewire::actingAs($actor)
                ->test(TransactionDetailWorkspace::class, ['transactionId' => $transaction->source_key])
                ->set('paymentDescription', 'STEP 11C - Detail Transaksi')
                ->set('itemDescriptions', [$item->id => 'STEP 11C - Item'])
                ->call('saveDescriptions');

            $this->assertSame('STEP 11C - Detail Transaksi', (string) $db->table('transactions')->where('id', $transaction->id)->value('payment_description'));
            $this->assertSame('STEP 11C - Item', (string) $db->table('transaction_items')->where('id', $item->id)->value('item_description'));
            $this->assertSame('NUMBERED', (string) $db->table('spj_packages')->where('id', $package->id)->value('status'));
            $this->assertSame((string) $package->document_number, (string) $db->table('spj_packages')->where('id', $package->id)->value('document_number'));

            $item->forceFill(['amount' => (float) $item->amount + 1])->save();

            $this->assertNull($resolver->resolveTransactionForDescription((string) $transaction->source_key));
        } finally {
            File::delete($target);
        }
    }

    public function test_stale_effective_context_remains_closed_for_single_numbering(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-numbering-closed.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $row = $this->stalePackage($db, 'DRAFT');
            $this->activateEffectiveContext($row);
            config()->set('spj.v2_read_path', 'v2');
            $db->table('spj_packages')->where('id', $row->package_id)->update(['status' => 'READY']);

            $response = app(SpjSingleNumberingUseCase::class)->assignNumber((string) $row->package_id);

            $this->assertSame(302, $response->getStatusCode());
            $this->assertSame('READY', (string) $db->table('spj_packages')->where('id', $row->package_id)->value('status'));
            $this->assertNull($db->table('spj_packages')->where('id', $row->package_id)->value('document_number'));
            $this->assertSame(0, (int) $db->table('spj_documents')->where('spj_package_id', $row->package_id)->whereNotNull('document_number')->count());
        } finally {
            File::delete($target);
        }
    }

    public function test_effective_numbering_authorization_is_explicit_and_fail_closed_without_side_effects(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-numbering-authorization.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $row = $this->stalePackage($db, 'DRAFT');
            $this->activateEffectiveContext($row);
            config()->set('spj.v2_read_path', 'v2');
            $db->table('transactions')->where('id', $row->legacy_transaction_id)->update(['spj_category' => 'BARANG']);
            $db->table('spj_packages')->where('id', $row->package_id)->update(['status' => 'READY']);
            foreach (range(1, 4) as $quarter) {
                $db->table('fiscal_period_closures')->updateOrInsert(
                    ['fiscal_year_id' => $row->effective_fiscal_year_id, 'quarter' => $quarter],
                    ['status' => 'OPEN', 'updated_at' => now(), 'created_at' => now()],
                );
            }

            $packageId = (int) $row->package_id;
            $authorize = function () use ($packageId): array {
                $package = SpjPackage::query()->with(['transaction.items', 'documents'])->findOrFail($packageId);

                return app(SpjV2NumberingAuthorizationService::class)->authorize($package, ['SPJ']);
            };
            $before = [
                'package' => $db->table('spj_packages')->where('id', $row->package_id)->first(),
                'transaction' => $db->table('transactions')->where('id', $row->legacy_transaction_id)->first(),
                'documents' => $this->documentsHash($db, (int) $row->package_id),
                'audit' => (int) $db->table('operational_audit_logs')->count(),
            ];
            $positive = $authorize();
            $this->assertTrue($positive['authorized'], $positive['reason']);
            $this->assertSame('v2_authorized_preflight', $positive['path']);
            $this->assertSame((int) $row->effective_fiscal_year_id, $positive['effective_fiscal_year_id']);
            $this->assertNotSame((int) $row->legacy_fiscal_year_id, $positive['effective_fiscal_year_id']);
            $this->assertContains($positive['quarter'], [1, 2, 3, 4]);

            $scenarios = [
                'legacy selector' => function (): void {
                    config()->set('spj.v2_read_path', 'legacy');
                },
                'package membership' => function () use ($db, $row): void {
                    $db->table('spj_packages')->where('id', $row->package_id)->update(['spj_transaction_id' => null]);
                },
                'wrong bridge' => function () use ($db, $row): void {
                    $other = $db->table('spj_transactions')->where('id', '!=', $row->spj_transaction_id)->where('fiscal_year_id', $row->effective_fiscal_year_id)->where('fund_source_id', $row->fund_source_id)->first();
                    $this->assertNotNull($other);
                    $db->table('spj_packages')->where('id', $row->package_id)->update(['spj_transaction_id' => $other->id]);
                },
                'missing provenance' => function () use ($db, $row): void {
                    $db->table('legacy_transaction_v2_map')->where('legacy_transaction_id', $row->legacy_transaction_id)->delete();
                },
                'ambiguous provenance' => function () use ($db, $row): void {
                    $db->table('legacy_transaction_v2_map')->where('legacy_transaction_id', $row->legacy_transaction_id)->update(['mapping_status' => 'AMBIGUOUS']);
                },
                'provenance status' => function () use ($db, $row): void {
                    $db->table('legacy_transaction_v2_map')->where('legacy_transaction_id', $row->legacy_transaction_id)->update(['mapping_status' => 'REQUIRES_REVIEW']);
                },
                'fund mismatch' => function () use ($db, $row): void {
                    $db->table('transactions')->where('id', $row->legacy_transaction_id)->update(['fund_source_id' => null]);
                },
                'reconciliation' => function () use ($db, $row): void {
                    $db->table('transactions')->where('id', $row->legacy_transaction_id)->update(['requires_reconciliation' => true]);
                },
                'source missing' => function () use ($db, $row): void {
                    $db->table('transactions')->where('id', $row->legacy_transaction_id)->update(['source_status' => 'SOURCE_MISSING']);
                },
                'source facts drift' => function () use ($db, $row): void {
                    $db->table('transactions')->where('id', $row->legacy_transaction_id)->update(['description' => 'DRIFTED SOURCE FACT']);
                },
                'canonical financial drift' => function () use ($db, $row): void {
                    $this->driftCanonicalGrossSource($db, (int) $row->spj_transaction_id);
                },
                'item drift' => function () use ($db, $row): void {
                    $db->table('transaction_items')->where('transaction_id', $row->legacy_transaction_id)->limit(1)->update(['amount' => DB::raw('amount + 1')]);
                },
                'extra item' => function () use ($db, $row): void {
                    $db->table('transaction_items')->insert(['transaction_id' => $row->legacy_transaction_id, 'source_item_id' => 'AUTH-EXTRA', 'description' => 'Unexpected item', 'quantity' => 1, 'amount' => 1, 'source_status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]);
                },
                'document relation' => function () use ($db, $row): void {
                    $db->table('spj_documents')->insert(['spj_package_id' => $row->package_id, 'document_type' => 'UNKNOWN_AUTH_DOCUMENT', 'scope_key' => 'MAIN', 'status' => 'DRAFT', 'created_at' => now(), 'updated_at' => now()]);
                },
                'lifecycle' => function () use ($db, $row): void {
                    $db->table('spj_packages')->where('id', $row->package_id)->update(['status' => 'FINAL']);
                },
                'period proof' => function () use ($db, $row): void {
                    $db->table('transactions')->where('id', $row->legacy_transaction_id)->update(['transaction_date' => '1900-01-01']);
                },
            ];

            foreach ($scenarios as $label => $mutate) {
                $db->beginTransaction();
                try {
                    config()->set('spj.v2_read_path', 'v2');
                    $mutate();
                    $result = $authorize();
                    $this->assertFalse($result['authorized'], $label.' unexpectedly authorized');
                } finally {
                    $db->rollBack();
                }
            }

            config()->set('spj.v2_read_path', 'v2');
            $after = [
                'package' => $db->table('spj_packages')->where('id', $row->package_id)->first(),
                'transaction' => $db->table('transactions')->where('id', $row->legacy_transaction_id)->first(),
                'documents' => $this->documentsHash($db, (int) $row->package_id),
                'audit' => (int) $db->table('operational_audit_logs')->count(),
            ];
            $this->assertSame((array) $before['package'], (array) $after['package']);
            $this->assertSame((array) $before['transaction'], (array) $after['transaction']);
            $this->assertSame($before['documents'], $after['documents']);
            $this->assertSame($before['audit'], $after['audit']);
            $this->assertSame((int) $row->legacy_fiscal_year_id, (int) $after['transaction']->fiscal_year_id);
            $this->assertNull($after['package']->document_number);
        } finally {
            File::delete($target);
        }
    }

    public function test_effective_numbering_period_resolver_proves_year_and_all_quarter_boundaries_without_legacy_rewrite(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-numbering-period-resolver.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $row = $this->stalePackage($db, 'DRAFT');
            $this->activateEffectiveContext($row);
            $package = SpjPackage::query()->with('transaction')->findOrFail($row->package_id);
            $year = (int) FiscalYear::query()->findOrFail($row->effective_fiscal_year_id)->year;
            foreach (range(1, 4) as $quarter) {
                $db->table('fiscal_period_closures')->updateOrInsert(
                    ['fiscal_year_id' => $row->effective_fiscal_year_id, 'quarter' => $quarter],
                    ['status' => 'OPEN', 'updated_at' => now(), 'created_at' => now()],
                );
            }

            $legacyFiscalYearId = (int) $package->transaction->fiscal_year_id;
            $resolver = app(SpjV2EffectiveNumberingPeriodResolver::class);
            $canonical = [
                'fiscal_year_id' => (int) $row->effective_fiscal_year_id,
                'fund_source_id' => (int) $row->fund_source_id,
                'source_id' => (int) $row->source_id,
                'canonical_context_status' => 'ACTIVE_CANONICAL',
                'source_status' => 'ACTIVE',
            ];

            foreach ([
                [1, $year.'-03-31'],
                [2, $year.'-04-01'],
                [2, $year.'-06-30'],
                [3, $year.'-07-01'],
                [3, $year.'-09-30'],
                [4, $year.'-10-01'],
            ] as [$quarter, $date]) {
                $package->transaction->setAttribute('transaction_date', $date);
                $canonical['transaction_date'] = $date;
                $result = $resolver->resolve($package, $canonical);
                $this->assertTrue($result['authorized'], $result['reason']);
                $this->assertSame($quarter, $result['effective_quarter']);
                $this->assertSame((int) $row->effective_fiscal_year_id, $result['effective_fiscal_year_id']);
                $this->assertSame($legacyFiscalYearId, (int) $package->transaction->fiscal_year_id);
            }

            $canonical['transaction_date'] = $year.'-03-31';
            $package->transaction->setAttribute('transaction_date', $canonical['transaction_date']);
            foreach ([
                'missing date' => static function (array &$facts): void {
                    unset($facts['transaction_date']);
                },
                'invalid date' => static function (array &$facts): void {
                    $facts['transaction_date'] = 'not-a-date';
                },
                'year mismatch' => static function (array &$facts) use ($year): void {
                    $facts['transaction_date'] = ($year + 1).'-01-01';
                },
                'context mismatch' => static function (array &$facts): void {
                    $facts['fund_source_id'] = 999999;
                },
            ] as $label => $mutate) {
                $facts = $canonical;
                $mutate($facts);
                $result = $resolver->resolve($package, $facts);
                $this->assertFalse($result['authorized'], $label.' unexpectedly authorized');
            }

            $db->table('fiscal_period_closures')
                ->where(['fiscal_year_id' => $row->effective_fiscal_year_id, 'quarter' => 1])
                ->update(['status' => 'CLOSED']);
            $closed = $resolver->resolve($package, $canonical);
            $this->assertFalse($closed['authorized']);
            $this->assertSame('EFFECTIVE_PERIOD_CLOSED', $closed['error_code']);
            $this->assertSame($legacyFiscalYearId, (int) $db->table('transactions')->where('id', $row->legacy_transaction_id)->value('fiscal_year_id'));
            $this->assertSame('DRAFT', (string) $db->table('spj_packages')->where('id', $row->package_id)->value('status'));
            $this->assertSame(0, (int) $db->table('operational_audit_logs')->count());
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

    public function test_wrong_package_bridge_blocks_overlay_write_without_audit(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-overlay-write-wrong-bridge.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $row = $this->stalePackage($db, 'DRAFT');
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

            $before = $db->table('transactions')->where('id', $row->legacy_transaction_id)->first();
            $this->assertNotNull($before);

            app(UpdateSpjPackageDetailsUseCase::class)->handle(
                (string) $row->package_id,
                Request::create('/spj/update', 'PUT', [
                    'payment_description' => 'FORGED WRONG BRIDGE WRITE',
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

    public function test_reconciliation_and_source_missing_block_overlay_write_without_audit(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-overlay-write-source-state.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $row = $this->stalePackage($db, 'DRAFT');
            $this->activateEffectiveContext($row);
            config()->set('spj.v2_read_path', 'v2');

            $before = $db->table('transactions')->where('id', $row->legacy_transaction_id)->first();
            $this->assertNotNull($before);

            $db->table('transactions')->where('id', $row->legacy_transaction_id)->update([
                'requires_reconciliation' => 1,
            ]);

            app(UpdateSpjPackageDetailsUseCase::class)->handle(
                (string) $row->package_id,
                Request::create('/spj/update', 'PUT', [
                    'payment_description' => 'FORGED RECONCILIATION WRITE',
                ]),
            );

            $this->assertSame(
                $before->payment_description,
                $db->table('transactions')->where('id', $row->legacy_transaction_id)->value('payment_description'),
            );

            $db->table('transactions')->where('id', $row->legacy_transaction_id)->update([
                'requires_reconciliation' => 0,
                'source_status' => 'SOURCE_MISSING',
            ]);

            app(UpdateSpjPackageDetailsUseCase::class)->handle(
                (string) $row->package_id,
                Request::create('/spj/update', 'PUT', [
                    'payment_description' => 'FORGED SOURCE MISSING WRITE',
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

    public function test_raw_source_drift_blocks_overlay_write_without_audit(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-overlay-write-source-drift.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $row = $this->stalePackage($db, 'DRAFT');
            $this->activateEffectiveContext($row);
            config()->set('spj.v2_read_path', 'v2');

            $before = $db->table('transactions')->where('id', $row->legacy_transaction_id)->first();
            $this->assertNotNull($before);

            $this->driftCanonicalGrossSource($db, (int) $row->spj_transaction_id);

            app(UpdateSpjPackageDetailsUseCase::class)->handle(
                (string) $row->package_id,
                Request::create('/spj/update', 'PUT', [
                    'payment_description' => 'FORGED RAW DRIFT WRITE',
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

    public function test_numbered_effective_context_package_allows_narrative_correction_without_lifecycle_write(): void
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

            $this->assertNotSame($before, $package->transaction->fresh()->payment_description);
            $this->assertSame('FORGED NUMBERED V2 WRITE', $package->transaction->fresh()->payment_description);
            $this->assertSame($legacyFiscalYearId, (int) $package->transaction->fresh()->fiscal_year_id);
            $this->assertSame('NUMBERED', (string) $package->fresh()->status);
        } finally {
            File::delete($target);
        }
    }

    public function test_numbered_effective_context_package_remains_locked_for_category_change(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-overlay-numbered-category-lock.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $row = $this->stalePackage($db, 'NUMBERED');
            $this->activateEffectiveContext($row);
            config()->set('spj.v2_read_path', 'v2');

            $beforeCategory = (string) $db->table('transactions')
                ->where('id', $row->legacy_transaction_id)
                ->value('spj_category');
            $legacyFiscalYearId = (int) $db->table('transactions')
                ->where('id', $row->legacy_transaction_id)
                ->value('fiscal_year_id');

            $request = Request::create('/spj/category', 'PUT', [
                'spj_category' => $beforeCategory === 'BARANG' ? 'JASA_LAINNYA' : 'BARANG',
            ]);
            $request->headers->set('Accept', 'application/json');

            $response = app(SpjPackageCategoryUseCase::class)->switchCategory(
                (string) $row->package_id,
                $request,
            );

            $this->assertSame(422, $response->getStatusCode());
            $this->assertSame(
                $beforeCategory,
                (string) $db->table('transactions')->where('id', $row->legacy_transaction_id)->value('spj_category'),
            );
            $this->assertSame(
                $legacyFiscalYearId,
                (int) $db->table('transactions')->where('id', $row->legacy_transaction_id)->value('fiscal_year_id'),
            );
            $this->assertSame('NUMBERED', (string) $db->table('spj_packages')->where('id', $row->package_id)->value('status'));
            $this->assertSame(
                0,
                $db->table('operational_audit_logs')
                    ->where('entity_type', 'SPJ_PACKAGE')
                    ->where('entity_id', (string) $row->package_id)
                    ->where('action', 'UBAH_KATEGORI')
                    ->count(),
            );
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

    private function driftCanonicalGrossSource(Connection $db, int $canonicalTransactionId): void
    {
        $rawRows = $db->table('spj_transaction_sources as source_link')
            ->join('arkas_source_identity_registry as identity', 'identity.id', '=', 'source_link.arkas_source_identity_id')
            ->join('arkas_raw_mirror_rows as raw', 'raw.id', '=', 'identity.current_raw_mirror_row_id')
            ->where('source_link.spj_transaction_id', $canonicalTransactionId)
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
