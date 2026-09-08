<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SpjAuditDiffCommandTest extends TestCase
{
    /** @var array<int, string> */
    private array $paths = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            File::delete($path);
        }

        parent::tearDown();
    }

    public function test_diff_reports_no_regression_when_release_safety_metrics_improve(): void
    {
        $before = $this->snapshot('before', warnings: 3, critical: 1, blankDescriptions: 2);
        $after = $this->snapshot('after', warnings: 1, critical: 0, blankDescriptions: 0);

        $this->artisan('spj:audit-diff', [
            'before' => $before,
            'after' => $after,
            '--fail-on-regression' => true,
        ])
            ->expectsOutputToContain('NO REGRESSION')
            ->assertExitCode(0);
    }

    public function test_diff_can_fail_a_verification_gate_when_blockers_increase(): void
    {
        $before = $this->snapshot('before-regression', warnings: 0, critical: 0, blankDescriptions: 0);
        $after = $this->snapshot('after-regression', warnings: 1, critical: 0, blankDescriptions: 1);

        $this->artisan('spj:audit-diff', [
            'before' => $before,
            'after' => $after,
            '--fail-on-regression' => true,
        ])->assertExitCode(1);
    }

    public function test_diff_rejects_snapshots_from_different_contexts(): void
    {
        $before = $this->snapshot('before-context', warnings: 0, critical: 0, blankDescriptions: 0, quarter: 1);
        $after = $this->snapshot('after-context', warnings: 0, critical: 0, blankDescriptions: 0, quarter: 2);

        $this->artisan('spj:audit-diff', [
            'before' => $before,
            'after' => $after,
        ])
            ->expectsOutputToContain('konteks berbeda')
            ->assertExitCode(1);
    }

    private function snapshot(
        string $name,
        int $warnings,
        int $critical,
        int $blankDescriptions,
        int $quarter = 1,
    ): string {
        $path = storage_path('framework/testing/'.$name.'-'.uniqid().'.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'read_only' => true,
            'query_only' => true,
            'school' => ['npsn' => '10208183', 'name' => 'SDN Audit'],
            'context' => ['year' => 2026, 'quarter' => $quarter, 'fund_source_id' => 1],
            'summary' => [
                'transactions' => 10,
                'packages' => 6,
                'unassigned_category' => 0,
                'blank_item_descriptions' => $blankDescriptions,
                'financial_mismatches' => 0,
                'critical' => $critical,
                'warnings' => $warnings,
                'info' => 0,
            ],
            'categories' => [
                [
                    'category' => 'BARANG',
                    'transactions' => 2,
                    'packages' => 1,
                    'none' => 1,
                    'draft' => 1,
                    'ready' => 0,
                    'numbered' => 0,
                    'final' => 0,
                    'cancelled' => 0,
                    'candidate_count' => 1,
                ],
            ],
            'candidates' => [
                'BARANG' => ['transaction_id' => 10, 'issues' => []],
            ],
            'anomalies' => array_merge(
                array_fill(0, $critical, ['severity' => 'CRITICAL', 'code' => 'CRITICAL_SAMPLE']),
                array_fill(0, $warnings, ['severity' => 'WARNING', 'code' => 'WARNING_SAMPLE']),
            ),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->paths[] = $path;

        return $path;
    }
}
