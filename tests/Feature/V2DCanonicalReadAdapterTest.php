<?php

namespace Tests\Feature;

use App\Services\ArkasReferenceResolver;
use App\Services\SpjV2CanonicalReadService;
use App\Services\SpjV2LegacyMigrationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class V2DCanonicalReadAdapterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // This isolated V2-D fixture predates the persistent central authority.
        // Keep its source-read contract explicit while the cutover suite covers CENTRAL_COMPAT.
        config()->set('arkas.reference_read_mode', ArkasReferenceResolver::LEGACY_RAW);
    }

    public function test_canonical_adapter_reads_all_active_contexts_without_identity_or_financial_drift(): void
    {
        $sourceClone = storage_path('app/school-databases/10260786/spj.sqlite');
        $source = $this->sourcePath();
        $this->assertFileExists($sourceClone);
        $this->assertNotSame('', $source, 'No readable ARKAS evidence source was found for the V2-D rehearsal.');

        $target = storage_path('app/v2-c-rehearsal/test-v2d-canonical-read.sqlite');
        File::copy($sourceClone, $target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $adapter = app(SpjV2CanonicalReadService::class);
            $contexts = $db->table('spj_transactions')
                ->where('canonical_context_status', 'ACTIVE_CANONICAL')
                ->select('fiscal_year_id', 'fund_source_id', 'source_id')
                ->distinct()
                ->orderBy('fiscal_year_id')
                ->orderBy('fund_source_id')
                ->get();

            $rows = collect();
            foreach ($contexts as $context) {
                $contextRows = $adapter->forContext(
                    $db,
                    (int) $context->fiscal_year_id,
                    (int) $context->fund_source_id,
                    (int) $context->source_id,
                );

                $expected = (int) $db->table('spj_transactions')
                    ->where('fiscal_year_id', $context->fiscal_year_id)
                    ->where('fund_source_id', $context->fund_source_id)
                    ->where('source_id', $context->source_id)
                    ->where('canonical_context_status', 'ACTIVE_CANONICAL')
                    ->count();

                $this->assertCount($expected, $contextRows);
                $rows = $rows->concat($contextRows);
            }

            $this->assertCount(187, $rows);
            $this->assertTrue($rows->every(fn (array $row): bool => $row['canonical_context_status'] === 'ACTIVE_CANONICAL'));
            $this->assertSame(429605000.0, (float) $rows->sum('gross_amount'));
            $this->assertSame(20497310.0, (float) $rows->sum('tax_total'));
            $this->assertSame(409107690.0, (float) $rows->sum('net_amount'));

            foreach ($rows as $row) {
                $this->assertCount(
                    (int) $db->table('spj_transaction_sources')->where('spj_transaction_id', $row['id'])->count(),
                    $row['items'],
                );
            }
        } finally {
            File::delete($target);
        }
    }

    public function test_legacy_deterministic_source_key_resolves_to_the_same_canonical_transaction(): void
    {
        $sourceClone = storage_path('app/school-databases/10260786/spj.sqlite');
        $source = $this->sourcePath();
        $this->assertFileExists($sourceClone);
        $this->assertNotSame('', $source, 'No readable ARKAS evidence source was found for the V2-D rehearsal.');

        $target = storage_path('app/v2-c-rehearsal/test-v2d-canonical-identifier.sqlite');
        File::copy($sourceClone, $target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $provenance = $db->table('legacy_transaction_v2_map as provenance')
                ->join('spj_transactions as transactions', 'transactions.id', '=', 'provenance.spj_transaction_id')
                ->where('provenance.mapping_status', 'DETERMINISTIC')
                ->whereColumn('provenance.legacy_source_key', '!=', 'provenance.current_membership_hash')
                ->where('transactions.canonical_context_status', 'ACTIVE_CANONICAL')
                ->select([
                    'provenance.legacy_source_key',
                    'provenance.current_membership_hash',
                    'transactions.id',
                    'transactions.fiscal_year_id',
                    'transactions.fund_source_id',
                    'transactions.source_id',
                ])
                ->first();

            $this->assertNotNull($provenance);

            $adapter = app(SpjV2CanonicalReadService::class);
            $fromLegacy = $adapter->findBySourceIdentifier(
                $db,
                (int) $provenance->fiscal_year_id,
                (int) $provenance->fund_source_id,
                (int) $provenance->source_id,
                (string) $provenance->legacy_source_key,
            );
            $fromCanonical = $adapter->findBySourceIdentifier(
                $db,
                (int) $provenance->fiscal_year_id,
                (int) $provenance->fund_source_id,
                (int) $provenance->source_id,
                (string) $provenance->current_membership_hash,
            );

            $this->assertNotNull($fromLegacy);
            $this->assertNotNull($fromCanonical);
            $this->assertSame((int) $provenance->id, $fromLegacy['id']);
            $this->assertSame($fromLegacy['id'], $fromCanonical['id']);
            $this->assertSame((string) $provenance->current_membership_hash, $fromLegacy['source_membership_hash']);
            $this->assertContains((string) $provenance->legacy_source_key, $fromLegacy['legacy_source_keys']);
        } finally {
            File::delete($target);
        }
    }

    public function test_adapter_reads_operator_owned_values_only_from_v2_overlays(): void
    {
        $sourceClone = storage_path('app/school-databases/10260786/spj.sqlite');
        $source = $this->sourcePath();
        $this->assertFileExists($sourceClone);
        $this->assertNotSame('', $source, 'No readable ARKAS evidence source was found for the V2-D rehearsal.');

        $target = storage_path('app/v2-c-rehearsal/test-v2d-canonical-overlay.sqlite');
        File::copy($sourceClone, $target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $transaction = $db->table('spj_transactions')
                ->where('canonical_context_status', 'ACTIVE_CANONICAL')
                ->orderBy('id')
                ->first();
            $this->assertNotNull($transaction);

            $overlay = $db->table('spj_transaction_overlays')
                ->where('spj_transaction_id', $transaction->id)
                ->first();
            $this->assertNotNull($overlay);

            $row = app(SpjV2CanonicalReadService::class)->findBySourceIdentifier(
                $db,
                (int) $transaction->fiscal_year_id,
                (int) $transaction->fund_source_id,
                (int) $transaction->source_id,
                (string) $transaction->source_membership_hash,
            );

            $this->assertNotNull($row);
            $this->assertSame($this->normalize($overlay->spj_category), $row['overlay']['spj_category']);
            $this->assertSame($this->normalize($overlay->payment_description), $row['overlay']['payment_description']);
            $this->assertSame($this->normalize($overlay->payment_method), $row['overlay']['payment_method']);
            $this->assertSame($this->normalize($overlay->payment_reference), $row['overlay']['payment_reference']);
            $this->assertSame($this->normalize($overlay->receipt_recipient_name), $row['overlay']['receipt_recipient_name']);

            foreach ($row['items'] as $item) {
                $itemOverlay = $db->table('spj_item_overlays')
                    ->where('spj_transaction_source_id', $item['source_link_id'])
                    ->first();

                $this->assertSame(
                    $this->normalize($itemOverlay?->item_description),
                    $item['item_description'],
                );
            }
        } finally {
            File::delete($target);
        }
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
            storage_path('app/v2-c-rehearsal/reports/test-v2d-canonical-read.json'),
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

    private function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
