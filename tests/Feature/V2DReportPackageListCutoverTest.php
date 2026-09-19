<?php

namespace Tests\Feature;

use App\Services\SpjV2LegacyMigrationService;
use App\UseCases\Spj\SpjReportUseCase;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class V2DReportPackageListCutoverTest extends TestCase
{
    protected function tearDown(): void
    {
        DB::purge('school');

        parent::tearDown();
    }

    public function test_report_table_and_summary_switch_atomically_to_effective_package_context(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-report-package-list.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $context = $this->numberedContext($db);
            $this->activateContext($context);
            config()->set('spj.v2_read_path', 'v2');

            $before = $this->protectedHash($db);
            [$packages, $summary] = app(SpjReportUseCase::class)->reportData('semua', null, 10000, 10000);

            $expectedIds = $this->expectedReportPackageIds($db, $context);
            $actualIds = $packages->getCollection()
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->sort()
                ->values()
                ->all();

            $this->assertSame('v2', $summary['read_path']);
            $this->assertSame($expectedIds, $actualIds);
            $this->assertSame(count($expectedIds), $packages->total());
            $this->assertSame(
                (int) $db->table('spj_packages')
                    ->whereIn('id', $expectedIds)
                    ->whereNotNull('document_number')
                    ->count(),
                (int) $summary['count'],
            );
            $this->assertTrue($packages->getCollection()->every(
                fn ($package): bool => $package->getAttribute('read_context_path') === 'v2_compat',
            ));
            $this->assertSame($before, $this->protectedHash($db));
        } finally {
            File::delete($target);
        }
    }

    public function test_report_effective_membership_respects_month_quarter_and_semester_filters(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-report-package-periods.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $context = $this->numberedContext($db);
            $this->activateContext($context);
            config()->set('spj.v2_read_path', 'v2');

            $sampleDate = $db->table('spj_packages as package')
                ->join('transactions as legacy', 'legacy.id', '=', 'package.transaction_id')
                ->join('spj_transactions as v2', 'v2.id', '=', 'package.spj_transaction_id')
                ->where('v2.fiscal_year_id', $context->fiscal_year_id)
                ->where('v2.fund_source_id', $context->fund_source_id)
                ->where('v2.source_id', $context->source_id)
                ->where('v2.canonical_context_status', 'ACTIVE_CANONICAL')
                ->whereNotNull('package.document_number')
                ->orderBy('legacy.transaction_date')
                ->value('legacy.transaction_date');
            $this->assertNotNull($sampleDate);

            $date = Carbon::parse((string) $sampleDate);
            $filters = [
                ['bulan', $date->month],
                ['triwulan', (int) ceil($date->month / 3)],
                ['semester', (int) ceil($date->month / 6)],
            ];

            foreach ($filters as [$mode, $periode]) {
                [$packages, $summary] = app(SpjReportUseCase::class)->reportData($mode, $periode, 10000, 10000);
                $expectedIds = $this->expectedReportPackageIds($db, $context, $mode, $periode);

                $actualIds = $packages->getCollection()
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->sort()
                    ->values()
                    ->all();

                $this->assertSame('v2', $summary['read_path'], 'Expected V2 report path for '.$mode.' '.$periode);
                $this->assertSame($expectedIds, $actualIds, 'Period membership mismatch for '.$mode.' '.$periode);
                $this->assertSame(
                    (int) $db->table('spj_packages')
                        ->whereIn('id', $expectedIds)
                        ->whereNotNull('document_number')
                        ->count(),
                    (int) $summary['count'],
                    'Period count mismatch for '.$mode.' '.$periode,
                );
            }
        } finally {
            File::delete($target);
        }
    }

    public function test_report_table_rolls_back_with_config_and_raw_drift_fails_the_whole_consumer_closed(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-report-package-rollback.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $context = $this->numberedContext($db);
            $this->activateContext($context);

            config()->set('spj.v2_read_path', 'v2');
            [$v2Packages, $v2Summary] = app(SpjReportUseCase::class)->reportData('semua', null, 10000, 10000);
            $this->assertSame('v2', $v2Summary['read_path']);
            $this->assertNotEmpty($v2Packages->items());

            config()->set('spj.v2_read_path', 'legacy');
            [$legacyPackages, $legacySummary] = app(SpjReportUseCase::class)->reportData('semua', null, 10000, 10000);
            $this->assertSame('legacy', $legacySummary['read_path']);
            $this->assertNotSame($v2Packages->total(), $legacyPackages->total());

            config()->set('spj.v2_read_path', 'v2');
            $raw = $this->numberedGrossSourceRow($db, $context);
            $this->assertNotNull($raw);
            $payload = json_decode((string) $raw->payload, true, 512, JSON_THROW_ON_ERROR);
            $amountKey = $this->amountKey($payload);
            $this->assertNotNull($amountKey);
            $payload[$amountKey] = (float) $payload[$amountKey] + 12345.0;
            $db->table('arkas_raw_mirror_rows')->where('id', $raw->id)->update([
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ]);

            [$driftPackages, $driftSummary] = app(SpjReportUseCase::class)->reportData('semua', null, 10000, 10000);
            $this->assertSame('legacy', $driftSummary['read_path']);
            $this->assertSame($legacyPackages->total(), $driftPackages->total());
            $this->assertSame(
                collect($legacyPackages->items())->pluck('id')->all(),
                collect($driftPackages->items())->pluck('id')->all(),
            );
        } finally {
            File::delete($target);
        }
    }

    public function test_report_view_labels_effective_context_rows_as_read_only(): void
    {
        $source = (string) file_get_contents(resource_path('views/livewire/spj-report-filter.blade.php'));

        $this->assertStringContainsString("read_context_path') === 'v2_compat'", $source);
        $this->assertStringContainsString('Baca saja', $source);
        $this->assertStringContainsString('Buka Paket (baca saja)', $source);
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
            storage_path('app/v2-c-rehearsal/reports/test-v2d-report-package-list.json'),
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
            ->first();

        $this->assertNotNull($context);

        return $context;
    }

    private function activateContext(object $context): void
    {
        session([
            'active_school_id' => 1,
            'active_fiscal_year_id' => (int) $context->fiscal_year_id,
            'active_fund_source_id' => (int) $context->fund_source_id,
        ]);
    }

    /** @return list<int> */
    private function expectedReportPackageIds(
        Connection $db,
        object $context,
        string $mode = 'semua',
        ?int $periode = null,
    ): array {
        $query = $db->table('spj_packages as package')
            ->join('transactions as legacy', 'legacy.id', '=', 'package.transaction_id')
            ->join('spj_transactions as v2', 'v2.id', '=', 'package.spj_transaction_id')
            ->where('v2.fiscal_year_id', $context->fiscal_year_id)
            ->where('v2.fund_source_id', $context->fund_source_id)
            ->where('v2.source_id', $context->source_id)
            ->where('v2.canonical_context_status', 'ACTIVE_CANONICAL')
            ->where(function ($query): void {
                $query->whereNotNull('package.document_number')
                    ->orWhereExists(function ($document): void {
                        $document->selectRaw('1')
                            ->from('spj_documents')
                            ->whereColumn('spj_documents.spj_package_id', 'package.id')
                            ->where('spj_documents.document_type', 'SPJ')
                            ->where('spj_documents.scope_key', 'MAIN')
                            ->where('spj_documents.status', 'CANCELLED');
                    });
            });

        if ($mode === 'bulan' && $periode !== null) {
            $query->whereMonth('legacy.transaction_date', $periode);
        } elseif ($mode === 'triwulan' && $periode !== null) {
            $query->whereMonth('legacy.transaction_date', '>=', (($periode - 1) * 3) + 1)
                ->whereMonth('legacy.transaction_date', '<=', $periode * 3);
        } elseif ($mode === 'semester' && $periode !== null) {
            $query->whereMonth('legacy.transaction_date', '>=', $periode === 1 ? 1 : 7)
                ->whereMonth('legacy.transaction_date', '<=', $periode === 1 ? 6 : 12);
        }

        return $query->orderBy('package.id')
            ->pluck('package.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function numberedGrossSourceRow(Connection $db, object $context): ?object
    {
        return $db->table('spj_packages as package')
            ->join('spj_transactions as transaction', 'transaction.id', '=', 'package.spj_transaction_id')
            ->join('spj_transaction_sources as source_link', 'source_link.spj_transaction_id', '=', 'transaction.id')
            ->join('arkas_source_identity_registry as identity', 'identity.id', '=', 'source_link.arkas_source_identity_id')
            ->join('arkas_raw_mirror_rows as raw', 'raw.id', '=', 'identity.current_raw_mirror_row_id')
            ->whereNotNull('package.document_number')
            ->where('transaction.fiscal_year_id', $context->fiscal_year_id)
            ->where('transaction.fund_source_id', $context->fund_source_id)
            ->where('transaction.source_id', $context->source_id)
            ->where('transaction.canonical_context_status', 'ACTIVE_CANONICAL')
            ->whereRaw("CAST(COALESCE(json_extract(raw.payload, '$.id_ref_bku'), 0) AS INTEGER) IN (4, 15, 24, 35)")
            ->whereRaw("COALESCE(json_extract(raw.payload, '$.soft_delete'), '0') != '1'")
            ->select(['raw.id', 'raw.payload'])
            ->orderBy('transaction.id')
            ->orderBy('source_link.sort_order')
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

    private function protectedHash(Connection $db): string
    {
        return hash('sha256', json_encode([
            'transactions' => $db->table('transactions')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
            'packages' => $db->table('spj_packages')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
            'documents' => $db->table('spj_documents')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
