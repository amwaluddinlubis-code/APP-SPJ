<?php

namespace Tests\Feature;

use App\Livewire\SpjReportFilter;
use App\Models\User;
use App\Services\SpjV2LegacyMigrationService;
use App\UseCases\Spj\SpjReportUseCase;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

final class V2DReportSummaryCutoverTest extends TestCase
{
    public function test_report_financial_summary_uses_v2_only_when_selected_and_rolls_back_without_legacy_mutation(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-report-summary-cutover.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $context = $this->numberedContext($db);
            session([
                'active_school_id' => 1,
                'active_fiscal_year_id' => $context->fiscal_year_id,
                'active_fund_source_id' => $context->fund_source_id,
            ]);
            $before = $this->protectedHash($db);

            config()->set('spj.v2_read_path', 'legacy');
            [, $legacy] = app(SpjReportUseCase::class)->reportData('semua', null, 10000, 10000);
            $this->assertSame('legacy', $legacy['read_path']);

            config()->set('spj.v2_read_path', 'v2');
            [, $canonical] = app(SpjReportUseCase::class)->reportData('semua', null, 10000, 10000);
            $this->assertSame('v2', $canonical['read_path']);
            foreach ($this->financialFields() as $field) {
                $this->assertEquals($legacy[$field], $canonical[$field], 'Initial V2 summary mismatch for '.$field);
            }

            $raw = $this->numberedGrossSourceRow($db, $context);
            $this->assertNotNull($raw);
            $payload = json_decode((string) $raw->payload, true, 512, JSON_THROW_ON_ERROR);
            $amountKey = $this->amountKey($payload);
            $this->assertNotNull($amountKey);
            $delta = 12345.0;
            $payload[$amountKey] = (float) $payload[$amountKey] + $delta;
            $db->table('arkas_raw_mirror_rows')->where('id', $raw->id)->update([
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ]);

            [, $driftedV2] = app(SpjReportUseCase::class)->reportData('semua', null, 10000, 10000);
            $this->assertSame('v2', $driftedV2['read_path']);
            $this->assertEquals((float) $legacy['gross'] + $delta, (float) $driftedV2['gross']);
            $this->assertEquals((float) $legacy['net'] + $delta, (float) $driftedV2['net']);

            config()->set('spj.v2_read_path', 'legacy');
            [, $rolledBack] = app(SpjReportUseCase::class)->reportData('semua', null, 10000, 10000);
            $this->assertSame('legacy', $rolledBack['read_path']);
            foreach ($this->financialFields() as $field) {
                $this->assertEquals($legacy[$field], $rolledBack[$field], 'Legacy rollback mismatch for '.$field);
            }

            $this->assertSame($before, $this->protectedHash($db), 'Read-path cutover must not mutate legacy transaction/package/document state.');
        } finally {
            File::delete($target);
        }
    }

    public function test_viewer_can_read_v2_report_summary_inside_active_context(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-report-summary-viewer.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $context = $this->numberedContext($db);
            $expectedCount = (int) $db->table('spj_packages as package')
                ->join('spj_transactions as transaction', 'transaction.id', '=', 'package.spj_transaction_id')
                ->where('transaction.fiscal_year_id', $context->fiscal_year_id)
                ->where('transaction.fund_source_id', $context->fund_source_id)
                ->where('transaction.canonical_context_status', 'ACTIVE_CANONICAL')
                ->whereNotNull('package.document_number')
                ->count();

            config()->set('spj.v2_read_path', 'v2');
            $viewer = User::factory()->create(['role' => User::ROLE_VIEWER]);
            $this->actingAs($viewer);
            session([
                'active_school_id' => 1,
                'active_fiscal_year_id' => $context->fiscal_year_id,
                'active_fund_source_id' => $context->fund_source_id,
            ]);

            Livewire::test(SpjReportFilter::class)
                ->assertViewHas('summary', fn (array $summary): bool => $summary['read_path'] === 'v2'
                    && (int) $summary['count'] === $expectedCount);
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
            storage_path('app/v2-c-rehearsal/reports/test-v2d-report-summary-cutover.json'),
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

    /** @return list<string> */
    private function financialFields(): array
    {
        return ['count', 'cancelled_count', 'gross', 'tax', 'net', 'ppn', 'pph21', 'pph22', 'pph23', 'pph4', 'sspd'];
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
