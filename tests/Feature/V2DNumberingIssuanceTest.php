<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\SpjPackage;
use App\Services\SpjNumberingOrderService;
use App\Services\SpjPackageValidationService;
use App\Services\SpjV2LegacyMigrationService;
use App\Services\SpjV2NumberingAuthorizationService;
use App\Services\SpjV2NumberingBatchService;
use App\Services\SpjV2NumberingIssuanceService;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class V2DNumberingIssuanceTest extends TestCase
{
    /** @var array<int,array{int,int}> */
    private array $openContexts = [];

    protected function tearDown(): void
    {
        DB::purge('school');

        parent::tearDown();
    }

    public function test_single_valid_issuance_uses_effective_year_and_completes_atomically(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-issuance-single.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $package = $this->firstIssuablePackage($db);
            $this->assertNotNull($package, 'Expected one issuable stale Paket.');
            $legacyFiscalYearId = (int) $package->transaction->fiscal_year_id;

            $result = app(SpjV2NumberingIssuanceService::class)->issue($package, 'SPJ', 'MAIN', 'v2-issue-intent-single-1');

            $this->assertSame('ISSUED', $result['status'], json_encode($result, JSON_THROW_ON_ERROR));
            $this->assertFalse($result['idempotent']);
            $sequence = (int) $result['sequence_number'];
            $this->assertGreaterThanOrEqual(1, $sequence);

            $effectiveFiscalYearId = $this->openContexts[(int) $package->id][0];
            $effectiveYear = (int) $db->table('fiscal_years')->where('id', $effectiveFiscalYearId)->value('year');
            $this->assertStringContainsString((string) $effectiveYear, (string) $result['document_number']);
            $padded = str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $this->assertMatchesRegularExpression('#^'.$padded.'/SPJ/.+/(I|II|III|IV)/'.$effectiveYear.'$#', (string) $result['document_number']);

            $document = $db->table('spj_documents')->where('id', $result['document_id'])->first();
            $this->assertNotNull($document);
            $this->assertSame('NUMBERED', strtoupper((string) $document->status));
            $this->assertSame($sequence, (int) $document->sequence_number);
            $this->assertSame('NUMBERED', (string) $db->table('spj_packages')->where('id', $package->id)->value('status'));
            $this->assertSame((string) $result['document_number'], (string) $db->table('spj_packages')->where('id', $package->id)->value('document_number'));

            $this->assertSame('COMPLETED', (string) $db->table('spj_v2_numbering_reservations')->where('id', $result['reservation_id'])->value('status'));
            $this->assertSame(1, $db->table('operational_audit_logs')
                ->where('entity_type', 'SPJ_PACKAGE')
                ->where('entity_id', (string) $package->id)
                ->where('action', 'TETAPKAN_NOMOR_V2')
                ->count());
            $audit = $db->table('operational_audit_logs')->where('id', $result['audit_id'])->first();
            $this->assertSame($effectiveFiscalYearId, (int) $audit->fiscal_year_id);

            // Stale legacy fiscal year is authority for nothing and stays put.
            $this->assertSame($legacyFiscalYearId, (int) $db->table('transactions')->where('id', $package->transaction_id)->value('fiscal_year_id'));
            $this->assertNotSame($legacyFiscalYearId, $effectiveFiscalYearId);
        } finally {
            File::delete($target);
        }
    }

    public function test_single_retry_is_idempotent_without_new_sequence_or_audit(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-issuance-retry.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $package = $this->firstIssuablePackage($db);
            $this->assertNotNull($package);
            $service = app(SpjV2NumberingIssuanceService::class);

            $first = $service->issue($package, 'SPJ', 'MAIN', 'v2-issue-intent-retry-1');
            $this->assertSame('ISSUED', $first['status'], json_encode($first, JSON_THROW_ON_ERROR));

            $package = SpjPackage::query()->with(['transaction.items', 'documents'])->findOrFail($package->id);
            $second = $service->issue($package, 'SPJ', 'MAIN', 'v2-issue-intent-retry-1');
            $this->assertSame('ISSUED', $second['status'], json_encode($second, JSON_THROW_ON_ERROR));
            $this->assertTrue($second['idempotent']);
            $this->assertSame($first['sequence_number'], $second['sequence_number']);
            $this->assertSame($first['document_number'], $second['document_number']);
            $this->assertSame(1, $db->table('spj_v2_numbering_reservations')->count());
            $this->assertSame(1, $db->table('operational_audit_logs')->where('action', 'TETAPKAN_NOMOR_V2')->count());
            $this->assertSame((int) $first['sequence_number'], (int) $db->table('document_number_sequences')->where('format_name', 'SPJ')->value('last_number'));
        } finally {
            File::delete($target);
        }
    }

    public function test_single_negative_matrix_fails_closed_without_writes(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-issuance-negative.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');

            // Case 1: legacy selector never issues through the V2 path.
            $package = $this->firstIssuablePackage($db);
            $this->assertNotNull($package);
            config()->set('spj.v2_read_path', 'legacy');
            $before = $this->writeFingerprint($db, (int) $package->id);
            $result = app(SpjV2NumberingIssuanceService::class)->issue($package, 'SPJ', 'MAIN', 'v2-issue-intent-neg-legacy');
            $this->assertSame('BLOCKED', $result['status']);
            $this->assertSame($before, $this->writeFingerprint($db, (int) $package->id));
            config()->set('spj.v2_read_path', 'v2');

            // Case 2: DRAFT lifecycle is not eligible (reuse the case-1 package).
            $db->table('spj_packages')->where('id', $package->id)->update(['status' => 'DRAFT']);
            $draft = SpjPackage::query()->with(['transaction.items', 'documents'])->findOrFail($package->id);
            $before = $this->writeFingerprint($db, (int) $package->id);
            $result = app(SpjV2NumberingIssuanceService::class)->issue($draft, 'SPJ', 'MAIN', 'v2-issue-intent-neg-draft');
            $this->assertSame('BLOCKED', $result['status']);
            $this->assertSame($before, $this->writeFingerprint($db, (int) $package->id));
            $db->table('spj_packages')->where('id', $package->id)->update(['status' => 'READY']);

            // Case 3: reconciliation flag blocks with no residue.
            $package = $this->firstIssuablePackage($db);
            $this->assertNotNull($package);
            $db->table('transactions')->where('id', $package->transaction_id)->update(['requires_reconciliation' => true]);
            $package = SpjPackage::query()->with(['transaction.items', 'documents'])->findOrFail($package->id);
            $before = $this->writeFingerprint($db, (int) $package->id);
            $result = app(SpjV2NumberingIssuanceService::class)->issue($package, 'SPJ', 'MAIN', 'v2-issue-intent-neg-recon');
            $this->assertSame('BLOCKED', $result['status']);
            $this->assertSame($before, $this->writeFingerprint($db, (int) $package->id));
            $db->table('transactions')->where('id', $package->transaction_id)->update(['requires_reconciliation' => false]);

            // Case 4: closed effective period blocks with no residue.
            $package = $this->firstIssuablePackage($db);
            $this->assertNotNull($package);
            $effectiveFiscalYearId = $this->openContexts[(int) $package->id][0];
            $quarter = (int) ceil((int) date('n', strtotime((string) $package->transaction->transaction_date)) / 3);
            $db->table('fiscal_period_closures')->updateOrInsert(
                ['fiscal_year_id' => $effectiveFiscalYearId, 'quarter' => $quarter],
                ['status' => 'CLOSED', 'updated_at' => now(), 'created_at' => now()],
            );
            $package = SpjPackage::query()->with(['transaction.items', 'documents'])->findOrFail($package->id);
            $before = $this->writeFingerprint($db, (int) $package->id);
            $result = app(SpjV2NumberingIssuanceService::class)->issue($package, 'SPJ', 'MAIN', 'v2-issue-intent-neg-closed');
            $this->assertSame('BLOCKED', $result['status']);
            $this->assertSame($before, $this->writeFingerprint($db, (int) $package->id));
        } finally {
            File::delete($target);
        }
    }

    public function test_batch_all_or_nothing_rolls_back_on_member_failure(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-batch-atomic.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $members = $this->batchMembers($db, 2);
            $this->assertCount(2, $members);

            // Poison the second member: preflight must refuse before any write.
            $db->table('transactions')->where('id', $members[1]->transaction_id)->update(['requires_reconciliation' => true]);
            $fresh = collect($members)->map(fn (SpjPackage $package): SpjPackage => SpjPackage::query()->with(['transaction.items', 'documents'])->findOrFail($package->id))->all();
            $result = app(SpjV2NumberingBatchService::class)->issueBatch($fresh, 'SPJ');
            $this->assertSame('BLOCKED', $result['status'], json_encode($result, JSON_THROW_ON_ERROR));

            $this->assertSame(0, $db->table('spj_documents')->whereIn('spj_package_id', [$members[0]->id, $members[1]->id])->count());
            $this->assertSame(0, $db->table('operational_audit_logs')->where('action', 'TETAPKAN_NOMOR_V2')->count());
            $this->assertSame(0, $db->table('operational_audit_logs')->where('action', 'PENOMORAN_BATCH_V2')->count());
            $this->assertSame(0, $db->table('spj_v2_numbering_reservations')->count());
            $this->assertSame('READY', (string) $db->table('spj_packages')->where('id', $members[0]->id)->value('status'));

            // Heal and prove the same batch issues cleanly.
            $db->table('transactions')->where('id', $members[1]->transaction_id)->update(['requires_reconciliation' => false]);
            $fresh = collect($members)->map(fn (SpjPackage $package): SpjPackage => SpjPackage::query()->with(['transaction.items', 'documents'])->findOrFail($package->id))->all();
            $result = app(SpjV2NumberingBatchService::class)->issueBatch($fresh, 'SPJ');
            $this->assertSame('ISSUED', $result['status'], json_encode($result, JSON_THROW_ON_ERROR));
            $this->assertSame(2, $result['issued']);
        } finally {
            File::delete($target);
        }
    }

    public function test_batch_valid_issues_in_deterministic_order_with_unique_sequences(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-batch-order.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $members = $this->batchMembers($db, 3);
            $this->assertCount(3, $members);

            $result = app(SpjV2NumberingBatchService::class)->issueBatch($members, 'SPJ');
            $this->assertSame('ISSUED', $result['status'], json_encode($result, JSON_THROW_ON_ERROR));

            $sequences = collect($result['items'])->pluck('sequence_number')->map(fn ($value): int => (int) $value)->all();
            $this->assertGreaterThanOrEqual(1, $sequences[0]);
            $this->assertSame([$sequences[0], $sequences[0] + 1, $sequences[0] + 2], $sequences);

            $expectedOrder = app(SpjNumberingOrderService::class)
                ->orderedPackagesForDocumentType(collect($members), 'SPJ')
                ->map(fn (SpjPackage $package): int => (int) $package->id)
                ->all();
            $this->assertSame($expectedOrder, collect($result['items'])->pluck('package_id')->all());

            foreach ($members as $member) {
                $this->assertSame('NUMBERED', (string) $db->table('spj_packages')->where('id', $member->id)->value('status'));
            }
            $this->assertSame(3, $db->table('operational_audit_logs')->where('action', 'TETAPKAN_NOMOR_V2')->count());
            $this->assertSame(1, $db->table('operational_audit_logs')->where('action', 'PENOMORAN_BATCH_V2')->count());
            $this->assertSame(3, $db->table('spj_v2_numbering_reservations')->where('status', 'COMPLETED')->count());

            // Retry is fully idempotent: no new sequences, audits, or batch rows.
            $fresh = collect($members)->map(fn (SpjPackage $package): SpjPackage => SpjPackage::query()->with(['transaction.items', 'documents'])->findOrFail($package->id))->all();
            $retry = app(SpjV2NumberingBatchService::class)->issueBatch($fresh, 'SPJ');
            $this->assertSame('ISSUED', $retry['status'], json_encode($retry, JSON_THROW_ON_ERROR));
            $this->assertSame($sequences, collect($retry['items'])->pluck('sequence_number')->map(fn ($value): int => (int) $value)->all());
            $this->assertTrue(collect($retry['items'])->every(fn (array $item): bool => $item['idempotent'] === true));
            $this->assertSame($sequences[2], (int) $db->table('document_number_sequences')->where('format_name', 'SPJ')->value('last_number'));
            $this->assertSame(3, $db->table('operational_audit_logs')->where('action', 'TETAPKAN_NOMOR_V2')->count());
            $this->assertSame(1, $db->table('operational_audit_logs')->where('action', 'PENOMORAN_BATCH_V2')->count());
        } finally {
            File::delete($target);
        }
    }

    /**
     * First package (BKU order) passing the full read-only preflight:
     * V2 authorization, gate, validation, and queue order.
     */
    private function firstIssuablePackage(Connection $db): ?SpjPackage
    {
        $rows = $db->table('spj_packages as package')
            ->join('transactions as legacy', 'legacy.id', '=', 'package.transaction_id')
            ->join('spj_transactions as v2', 'v2.id', '=', 'package.spj_transaction_id')
            ->whereIn('package.status', ['DRAFT', 'READY'])
            ->where('v2.canonical_context_status', 'ACTIVE_CANONICAL')
            ->whereColumn('legacy.fiscal_year_id', '!=', 'v2.fiscal_year_id')
            ->orderBy('package.id')
            ->limit(25)
            ->get(['package.id as package_id', 'v2.fiscal_year_id as effective_fiscal_year_id', 'v2.fund_source_id', 'v2.source_id']);

        $candidates = collect();
        foreach ($rows as $row) {
            $transactionId = (int) $db->table('spj_packages')->where('id', $row->package_id)->value('transaction_id');
            $db->table('transactions')->where('id', $transactionId)->update(['spj_category' => 'BARANG']);
            $db->table('spj_packages')->where('id', $row->package_id)->update(['status' => 'READY']);
            $package = SpjPackage::query()->with(['transaction.items', 'documents'])->findOrFail($row->package_id);
            $this->prepareOperatorData($db, $package);
            $this->openContext($db, $package, (int) $row->effective_fiscal_year_id, (int) $row->fund_source_id);
            $candidates->push($package);
        }

        $ordered = app(SpjNumberingOrderService::class)->orderedPackagesForDocumentType($candidates, 'SPJ');
        foreach ($ordered as $package) {
            $authorization = app(SpjV2NumberingAuthorizationService::class)->authorize($package, ['SPJ']);
            if (! $authorization['authorized'] || ($authorization['path'] ?? null) !== 'v2_authorized_preflight') {
                continue;
            }
            $issues = app(SpjPackageValidationService::class)->validateForNumbering($package);
            if ($issues !== []) {
                continue;
            }
            if (app(SpjNumberingOrderService::class)->singleNumberingBlocker($package, ['SPJ']) !== null) {
                continue;
            }
            [$fiscalYearId, $fundSourceId] = $this->openContexts[(int) $package->id];
            $this->activateIds($fiscalYearId, $fundSourceId);

            return $package;
        }

        return null;
    }

    /**
     * @return list<SpjPackage>
     */
    private function batchMembers(Connection $db, int $count): array
    {
        // The isolated fixture ships nearly all packages as NUMBERED. Revert
        // a few to READY (clearing their active documents) on the clone so
        // batch mechanics have candidates; the revert itself is test setup,
        // and every issuance assertion below still proves atomic behavior.
        $reverted = $db->table('spj_packages as package')
            ->join('spj_transactions as v2', 'v2.id', '=', 'package.spj_transaction_id')
            ->where('package.status', 'NUMBERED')
            ->where('v2.canonical_context_status', 'ACTIVE_CANONICAL')
            ->orderBy('package.id')
            ->limit($count)
            ->pluck('package.id');
        foreach ($reverted as $packageId) {
            $db->table('spj_documents')
                ->where('spj_package_id', $packageId)
                ->where('status', '!=', 'CANCELLED')
                ->delete();
            $db->table('spj_packages')->where('id', $packageId)->update([
                'status' => 'READY',
                'document_number' => null,
                'numbered_at' => null,
                'cancelled_at' => null,
                'cancelled_by' => null,
                'cancellation_reason' => null,
                'updated_at' => now(),
            ]);
        }

        $rows = $db->table('spj_packages as package')
            ->join('transactions as legacy', 'legacy.id', '=', 'package.transaction_id')
            ->join('spj_transactions as v2', 'v2.id', '=', 'package.spj_transaction_id')
            ->whereIn('package.status', ['DRAFT', 'READY'])
            ->where('v2.canonical_context_status', 'ACTIVE_CANONICAL')
            ->orderBy('package.id')
            ->limit(40)
            ->get(['package.id as package_id', 'v2.fiscal_year_id as effective_fiscal_year_id', 'v2.fund_source_id']);

        $byContext = [];
        foreach ($rows as $row) {
            $transactionId = (int) $db->table('spj_packages')->where('id', $row->package_id)->value('transaction_id');
            $db->table('transactions')->where('id', $transactionId)->update(['spj_category' => 'BARANG']);
            $db->table('spj_packages')->where('id', $row->package_id)->update(['status' => 'READY']);
            $package = SpjPackage::query()->with(['transaction.items', 'documents'])->findOrFail($row->package_id);
            $this->prepareOperatorData($db, $package);
            $this->openContext($db, $package, (int) $row->effective_fiscal_year_id, (int) $row->fund_source_id);
            $authorization = app(SpjV2NumberingAuthorizationService::class)->authorize($package, ['SPJ']);
            if (! $authorization['authorized'] || ($authorization['path'] ?? null) !== 'v2_authorized_preflight') {
                continue;
            }
            $issues = app(SpjPackageValidationService::class)->validateForNumbering($package);
            if ($issues !== []) {
                continue;
            }
            $key = $authorization['effective_fiscal_year_id'].'|'.$authorization['effective_fund_source_id'].'|'.$authorization['quarter'];
            $byContext[$key][] = $package;
            if (count($byContext[$key]) === $count) {
                [$fiscalYearId, $fundSourceId] = $this->openContexts[(int) $byContext[$key][0]->id];
                $this->activateIds($fiscalYearId, $fundSourceId);

                return $byContext[$key];
            }
        }

        return [];
    }

    /**
     * Minimal operator completion mirroring Isian Manual + penerimaan so the
     * canonical pre-numbering validation can pass on the isolated clone.
     * Only operator-owned columns/rows are touched; ARKAS source facts stay.
     */
    private function prepareOperatorData(Connection $db, SpjPackage $package): void
    {
        $transaction = $db->table('transactions')->where('id', $package->transaction_id)->first();
        $updates = [];
        if (blank($transaction->payment_description)) {
            $updates['payment_description'] = 'Uraian V2-D issuance gate';
        }
        if (blank($transaction->receipt_recipient_name)) {
            $updates['receipt_recipient_name'] = 'Penerima V2-D';
        }
        if (blank($transaction->payment_method)) {
            $updates['payment_method'] = 'tunai';
        }
        if (blank($transaction->vendor_name)) {
            $updates['vendor_name'] = 'Vendor V2-D';
        }
        if ($updates !== []) {
            $updates['updated_at'] = now();
            $db->table('transactions')->where('id', $package->transaction_id)->update($updates);
        }

        $firstItemId = $db->table('transaction_items')->where('transaction_id', $package->transaction_id)->orderBy('id')->value('id');
        // Order month must not precede the RKAS month; anchor on rkas_date.
        $orderDate = blank($transaction->rkas_date) ? substr((string) $transaction->transaction_date, 0, 10) : substr((string) $transaction->rkas_date, 0, 10);
        if ($firstItemId && ! $db->table('spj_goods')->where('transaction_item_id', $firstItemId)->exists()) {
            $db->table('spj_goods')->insert([
                'transaction_item_id' => $firstItemId,
                'order_date' => $orderDate,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        if (! $db->table('goods_receipts')->where('transaction_id', $package->transaction_id)->exists()) {
            $db->table('goods_receipts')->insert([
                'transaction_id' => $package->transaction_id,
                'scope_key' => 'MAIN',
                'receipt_sequence' => 1,
                'receipt_date' => substr((string) $transaction->transaction_date, 0, 10),
                'status' => 'DRAFT',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $package->load(['transaction.items', 'transaction.goods', 'transaction.goodsReceipts', 'documents']);
    }

    private function openContext(Connection $db, SpjPackage $package, int $effectiveFiscalYearId, int $fundSourceId): void
    {
        $this->activateIds($effectiveFiscalYearId, $fundSourceId);
        $this->openContexts[(int) $package->id] = [$effectiveFiscalYearId, $fundSourceId];
        $quarter = (int) ceil((int) date('n', strtotime((string) $package->transaction->transaction_date)) / 3);
        $db->table('fiscal_period_closures')->updateOrInsert(
            ['fiscal_year_id' => $effectiveFiscalYearId, 'quarter' => $quarter],
            ['status' => 'OPEN', 'updated_at' => now(), 'created_at' => now()],
        );
        $db->table('document_number_formats')->updateOrInsert(
            ['fiscal_year_id' => $effectiveFiscalYearId, 'document_type' => 'SPJ'],
            ['format_pattern' => '{SEQ}/SPJ/{SCHOOL}/{TW}/{YEAR}', 'reset_period' => 'YEAR', 'padding' => 4, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()],
        );
    }

    private function activateIds(int $effectiveFiscalYearId, int $fundSourceId): void
    {
        session([
            'active_school_id' => 1,
            'active_fiscal_year_id' => $effectiveFiscalYearId,
            'active_fund_source_id' => $fundSourceId,
        ]);
        config()->set('spj.v2_read_path', 'v2');
    }

    /** @return array<string,int|string> */
    private function writeFingerprint(Connection $db, int $packageId): array
    {
        return [
            'documents' => $db->table('spj_documents')->where('spj_package_id', $packageId)->count(),
            'audits' => $db->table('operational_audit_logs')->where('entity_id', (string) $packageId)->whereIn('action', ['TETAPKAN_NOMOR_V2', 'TETAPKAN_NOMOR'])->count(),
            'reservations' => $db->table('spj_v2_numbering_reservations')->where('spj_package_id', $packageId)->count(),
            'status' => (string) $db->table('spj_packages')->where('id', $packageId)->value('status'),
            'legacy_fy' => (int) $db->table('transactions')->where('id', (int) $db->table('spj_packages')->where('id', $packageId)->value('transaction_id'))->value('fiscal_year_id'),
        ];
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
            storage_path('app/v2-c-rehearsal/reports/test-v2d-issuance.json'),
            10260756,
        );

        $this->assertSame([], $migration['errors']);
        $this->assertSame('PASS', app(SpjV2LegacyMigrationService::class)->verify($db)['status']);
    }

    private function connect(string $target, string $source): void
    {
        School::updateOrCreate(
            ['id' => 1],
            ['npsn' => '10260756', 'school_code' => 'SMPN2RB', 'name' => 'SMP Negeri 2 Ranto Baek'],
        );
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
}
