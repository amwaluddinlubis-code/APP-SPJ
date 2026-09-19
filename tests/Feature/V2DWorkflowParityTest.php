<?php

namespace Tests\Feature;

use App\Services\SpjV2LegacyMigrationService;
use App\Services\SpjV2WorkflowParityService;
use Carbon\Carbon;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class V2DWorkflowParityTest extends TestCase
{
    public function test_report_tax_and_period_workflows_match_canonical_v2_reads(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-workflow-parity.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $before = $this->protectedHash($db);
            $result = app(SpjV2WorkflowParityService::class)->compare($db);
            $after = $this->protectedHash($db);

            $this->assertSame('PASS', $result['status'], $this->diagnostic($result));
            $this->assertSame(187, $result['counts']['canonical_transactions']);
            $this->assertSame(187, $result['counts']['legacy_active_provenance_transactions']);
            $this->assertSame(0, $result['transactions']['mismatch_count'], $this->diagnostic($result));
            $this->assertSame(0, $result['reports']['mismatch_count'], $this->diagnostic($result));
            $this->assertSame(0, $result['taxes']['mismatch_count'], $this->diagnostic($result));
            $this->assertSame(0, $result['period_workflow']['mismatch_count'], $this->diagnostic($result));
            $this->assertSame(0, $result['activity_realization']['mismatch_count'], $this->diagnostic($result));
            $this->assertSame(0, $result['account_realization']['mismatch_count'], $this->diagnostic($result));
            $this->assertSame(19, $result['reports']['period_windows_checked_per_context']);
            $this->assertSame($before, $after, 'Workflow parity service must remain read-only.');
        } finally {
            File::delete($target);
        }
    }

    public function test_tax_component_drift_fails_closed_even_when_total_tax_is_unchanged(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-workflow-tax-drift.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $taxRow = $db->table('arkas_raw_mirror_rows as raw')
                ->join('arkas_raw_mirror_tables as mirror_table', 'mirror_table.id', '=', 'raw.mirror_table_id')
                ->where('mirror_table.source_id', 1)
                ->where('mirror_table.source_table', 'kas_umum')
                ->where('mirror_table.status', 'ACTIVE')
                ->whereRaw("CAST(COALESCE(json_extract(raw.payload, '$.id_ref_bku'), 0) AS INTEGER) IN (10, 30)")
                ->whereRaw("CAST(COALESCE(json_extract(raw.payload, '$.is_ppn'), 0) AS INTEGER) = 1")
                ->select(['raw.id', 'raw.payload'])
                ->first();
            $this->assertNotNull($taxRow, 'Expected at least one PPN tax row in the rehearsal raw mirror.');

            $payload = json_decode((string) $taxRow->payload, true, 512, JSON_THROW_ON_ERROR);
            $payload['is_ppn'] = 0;
            $payload['is_pph_22'] = 1;
            $db->table('arkas_raw_mirror_rows')->where('id', $taxRow->id)->update([
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ]);

            $result = app(SpjV2WorkflowParityService::class)->compare($db);

            $this->assertSame('FAIL', $result['status']);
            $this->assertGreaterThan(0, $result['transactions']['mismatch_count']);
            $this->assertGreaterThan(0, $result['taxes']['mismatch_count']);
        } finally {
            File::delete($target);
        }
    }

    public function test_transaction_date_drift_fails_period_membership_closed(): void
    {
        $target = storage_path('app/v2-c-rehearsal/test-v2d-workflow-period-drift.sqlite');
        $source = $this->prepareClone($target);

        try {
            $this->connect($target, $source);
            $this->migrateAndProject();

            $db = DB::connection('school');
            $sourceRow = $db->table('spj_transaction_sources as source_link')
                ->join('spj_transactions as transaction', 'transaction.id', '=', 'source_link.spj_transaction_id')
                ->join('arkas_source_identity_registry as identity', 'identity.id', '=', 'source_link.arkas_source_identity_id')
                ->join('arkas_raw_mirror_rows as raw', 'raw.id', '=', 'identity.current_raw_mirror_row_id')
                ->where('transaction.canonical_context_status', 'ACTIVE_CANONICAL')
                ->where('source_link.sort_order', 0)
                ->whereRaw("CAST(strftime('%m', COALESCE(json_extract(raw.payload, '$.tanggal_transaksi'), json_extract(raw.payload, '$.tanggal'))) AS INTEGER) BETWEEN 1 AND 9")
                ->orderBy('transaction.id')
                ->select(['raw.id', 'raw.payload'])
                ->first();
            $this->assertNotNull($sourceRow, 'Expected a canonical source row whose date can move to the next quarter.');

            $payload = json_decode((string) $sourceRow->payload, true, 512, JSON_THROW_ON_ERROR);
            $dateKey = array_key_exists('tanggal_transaksi', $payload) ? 'tanggal_transaksi' : 'tanggal';
            $original = Carbon::parse((string) $payload[$dateKey]);
            $payload[$dateKey] = $original->copy()->addMonthsNoOverflow(3)->toDateString();
            $db->table('arkas_raw_mirror_rows')->where('id', $sourceRow->id)->update([
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ]);

            $result = app(SpjV2WorkflowParityService::class)->compare($db);

            $this->assertSame('FAIL', $result['status']);
            $this->assertGreaterThan(0, $result['transactions']['mismatch_count']);
            $this->assertGreaterThan(0, $result['reports']['mismatch_count']);
            $this->assertGreaterThan(0, $result['period_workflow']['mismatch_count']);
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
            storage_path('app/v2-c-rehearsal/reports/test-v2d-workflow-parity.json'),
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

    private function protectedHash(Connection $db): string
    {
        return hash('sha256', json_encode([
            'transactions' => $db->table('transactions')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
            'packages' => $db->table('spj_packages')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
            'documents' => $db->table('spj_documents')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
            'periods' => $db->table('fiscal_period_closures')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $result */
    private function diagnostic(array $result): string
    {
        return json_encode([
            'status' => $result['status'] ?? null,
            'counts' => $result['counts'] ?? null,
            'transactions' => $result['transactions'] ?? null,
            'reports' => $result['reports'] ?? null,
            'taxes' => $result['taxes'] ?? null,
            'period_workflow' => $result['period_workflow'] ?? null,
            'activity_realization' => $result['activity_realization'] ?? null,
            'account_realization' => $result['account_realization'] ?? null,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
