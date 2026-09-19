<?php

namespace Tests\Feature;

use App\Services\SpjV2CanonicalReadService;
use App\Services\SpjV2LegacyMigrationService;
use App\Services\TaxFilterService;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class V2DTaxReadContextCutoverTest extends TestCase
{
    protected function tearDown(): void
    {
        DB::purge('school');

        parent::tearDown();
    }

    public function test_tax_list_uses_deterministic_effective_context_representatives(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-tax-read-context.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $context = $this->taxContext($db);
            $this->activateContext($context);
            config()->set('spj.v2_read_path', 'v2');

            $before = $this->protectedHash($db);
            $data = app(TaxFilterService::class)->taxData('', null, null, null, 10000);
            $expected = $this->representativeTaxRows($db, $context);

            $this->assertSame('v2', $data['read_path']);
            $this->assertSame(
                $expected->pluck('id')->map(fn ($id): int => (int) $id)->sort()->values()->all(),
                collect($data['transactions']->items())->pluck('id')->map(fn ($id): int => (int) $id)->sort()->values()->all(),
            );
            $this->assertSame($expected->count(), (int) $data['summary']->count);
            $this->assertTrue(collect($data['transactions']->items())->every(
                fn ($transaction): bool => $transaction->getAttribute('read_context_path') === 'v2_compat'
                    && filled($transaction->source_key),
            ));

            $sample = $expected->first();
            $this->assertNotNull($sample);
            $month = Carbon::parse((string) $sample->transaction_date)->month;
            $monthData = app(TaxFilterService::class)->taxData('', $month, null, null, 10000);
            $expectedMonth = $expected->filter(
                fn (object $row): bool => Carbon::parse((string) $row->transaction_date)->month === $month,
            );
            $this->assertSame('v2', $monthData['read_path']);
            $this->assertSame($expectedMonth->count(), (int) $monthData['filteredSummary']->count);

            $searchData = app(TaxFilterService::class)->taxData((string) $sample->no_bukti, null, null, null, 10000);
            $this->assertSame('v2', $searchData['read_path']);
            $this->assertContains((int) $sample->id, collect($searchData['transactions']->items())->pluck('id')->map(fn ($id): int => (int) $id)->all());

            $this->assertSame($before, $this->protectedHash($db));
        } finally {
            File::delete($target);
        }
    }

    public function test_tax_raw_drift_fails_the_consumer_closed_to_legacy(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-tax-read-drift.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $context = $this->taxContext($db);
            $this->activateContext($context);

            config()->set('spj.v2_read_path', 'legacy');
            $legacy = app(TaxFilterService::class)->taxData('', null, null, null, 10000);
            $this->assertSame('legacy', $legacy['read_path']);

            config()->set('spj.v2_read_path', 'v2');
            $canonical = app(TaxFilterService::class)->taxData('', null, null, null, 10000);
            $this->assertSame('v2', $canonical['read_path']);

            $raw = $this->canonicalTaxRawRow($db, $context);
            $this->assertNotNull($raw);
            $payload = json_decode((string) $raw->payload, true, 512, JSON_THROW_ON_ERROR);
            $amountKey = $this->amountKey($payload);
            $this->assertNotNull($amountKey);
            $payload[$amountKey] = (float) $payload[$amountKey] + 12345.0;
            $db->table('arkas_raw_mirror_rows')->where('id', $raw->id)->update([
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ]);

            $guarded = app(TaxFilterService::class)->taxData('', null, null, null, 10000);
            $this->assertSame('legacy', $guarded['read_path']);
            $this->assertSame((int) $legacy['summary']->count, (int) $guarded['summary']->count);
            $this->assertEquals((float) $legacy['summary']->total, (float) $guarded['summary']->total);

            config()->set('spj.v2_read_path', 'legacy');
            $rolledBack = app(TaxFilterService::class)->taxData('', null, null, null, 10000);
            $this->assertSame('legacy', $rolledBack['read_path']);
        } finally {
            File::delete($target);
        }
    }

    public function test_tax_view_routes_effective_rows_by_source_key_as_read_only(): void
    {
        $source = (string) file_get_contents(resource_path('views/livewire/tax-filter.blade.php'));

        $this->assertStringContainsString("read_context_path') === 'v2_compat' ? \$transaction->source_key", $source);
        $this->assertStringContainsString('Baca saja', $source);
        $this->assertStringNotContainsString("route('transactions.spj-descriptions.update'", $source);
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
            storage_path('app/v2-c-rehearsal/reports/test-v2d-tax-read-context.json'),
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

    private function taxContext(Connection $db): object
    {
        $contexts = $db->table('legacy_transaction_v2_map as provenance')
            ->join('transactions as legacy', 'legacy.id', '=', 'provenance.legacy_transaction_id')
            ->join('spj_transactions as v2', 'v2.id', '=', 'provenance.spj_transaction_id')
            ->where('v2.canonical_context_status', 'ACTIVE_CANONICAL')
            ->where('legacy.tax_total', '>', 0)
            ->whereColumn('legacy.fiscal_year_id', '!=', 'v2.fiscal_year_id')
            ->select([
                'v2.fiscal_year_id',
                'v2.fund_source_id',
                'v2.source_id',
            ])
            ->distinct()
            ->orderBy('v2.fiscal_year_id')
            ->orderBy('v2.fund_source_id')
            ->first();

        $this->assertNotNull($contexts, 'Expected a stale effective context containing at least one taxed legacy transaction.');

        return $contexts;
    }

    private function representativeTaxRows(Connection $db, object $context)
    {
        return $db->table('legacy_transaction_v2_map as provenance')
            ->join('transactions as legacy', 'legacy.id', '=', 'provenance.legacy_transaction_id')
            ->join('spj_transactions as v2', 'v2.id', '=', 'provenance.spj_transaction_id')
            ->where('provenance.canonical_context_status', 'ACTIVE_CANONICAL')
            ->where('v2.canonical_context_status', 'ACTIVE_CANONICAL')
            ->where('v2.fiscal_year_id', $context->fiscal_year_id)
            ->where('v2.fund_source_id', $context->fund_source_id)
            ->where('v2.source_id', $context->source_id)
            ->orderBy('provenance.id')
            ->select([
                'provenance.spj_transaction_id',
                'legacy.id',
                'legacy.source_key',
                'legacy.no_bukti',
                'legacy.transaction_date',
                'legacy.tax_total',
            ])
            ->get()
            ->keyBy('spj_transaction_id')
            ->filter(fn (object $row): bool => (float) $row->tax_total > 0)
            ->values();
    }

    private function canonicalTaxRawRow(Connection $db, object $context): ?object
    {
        $canonical = app(SpjV2CanonicalReadService::class)
            ->forContext(
                $db,
                (int) $context->fiscal_year_id,
                (int) $context->fund_source_id,
                (int) $context->source_id,
            )
            ->first(fn (array $row): bool => (float) ($row['tax_total'] ?? 0) > 0);

        $this->assertIsArray($canonical);
        $parentIds = collect($canonical['items'] ?? [])
            ->map(fn (array $item): string => trim((string) (($item['payload']['id_kas_umum'] ?? null) ?: ($item['source_key'] ?? ''))))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $this->assertNotEmpty($parentIds);

        return $db->table('arkas_raw_mirror_rows as raw')
            ->join('arkas_raw_mirror_tables as mirror_table', 'mirror_table.id', '=', 'raw.mirror_table_id')
            ->where('mirror_table.source_id', $context->source_id)
            ->where('mirror_table.source_table', 'kas_umum')
            ->where('mirror_table.status', 'ACTIVE')
            ->whereRaw("CAST(COALESCE(json_extract(raw.payload, '$.id_ref_bku'), 0) AS INTEGER) IN (10, 30)")
            ->whereRaw("COALESCE(json_extract(raw.payload, '$.soft_delete'), '0') != '1'")
            ->whereIn(DB::raw("json_extract(raw.payload, '$.parent_id_kas_umum')"), $parentIds)
            ->select(['raw.id', 'raw.payload'])
            ->orderBy('raw.id')
            ->first();
    }

    /** @param array<string, mixed> $payload */
    private function amountKey(array $payload): ?string
    {
        foreach (['saldo', 'jumlah', 'nilai', 'nominal'] as $key) {
            if (array_key_exists($key, $payload)) {
                return $key;
            }
        }

        return null;
    }

    private function activateContext(object $context): void
    {
        session([
            'active_school_id' => 1,
            'active_fiscal_year_id' => (int) $context->fiscal_year_id,
            'active_fund_source_id' => (int) $context->fund_source_id,
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
