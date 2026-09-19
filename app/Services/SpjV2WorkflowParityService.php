<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use RuntimeException;

final class SpjV2WorkflowParityService
{
    public function __construct(
        private readonly SpjV2CanonicalReadService $canonicalReads,
    ) {}

    /** @return array<string, mixed> */
    public function compare(Connection $db): array
    {
        $this->assertSchema($db);

        $contexts = $db->table('spj_transactions')
            ->where('canonical_context_status', 'ACTIVE_CANONICAL')
            ->select('fiscal_year_id', 'fund_source_id', 'source_id')
            ->distinct()
            ->orderBy('fiscal_year_id')
            ->orderBy('fund_source_id')
            ->orderBy('source_id')
            ->get();

        $packagesByV2 = $db->table('spj_packages')->whereNotNull('spj_transaction_id')->get()->keyBy('spj_transaction_id');
        $packagesByLegacy = $db->table('spj_packages')->get()->keyBy('transaction_id');
        $documentsByPackage = $db->table('spj_documents')->get()->groupBy('spj_package_id');
        $itemCounts = $db->table('transaction_items')
            ->selectRaw('transaction_id, COUNT(*) as aggregate_count')
            ->groupBy('transaction_id')
            ->pluck('aggregate_count', 'transaction_id');

        $transactionMismatches = [];
        $reportMismatches = [];
        $taxMismatches = [];
        $periodMismatches = [];
        $periodRepresentationDeltas = [];
        $activityMismatches = [];
        $accountMismatches = [];
        $canonicalCount = 0;
        $legacyCount = 0;

        foreach ($contexts as $context) {
            $contextKey = implode(':', [
                (int) $context->fiscal_year_id,
                (int) $context->fund_source_id,
                (int) $context->source_id,
            ]);

            $canonical = $this->canonicalReads->forContext(
                $db,
                (int) $context->fiscal_year_id,
                (int) $context->fund_source_id,
                (int) $context->source_id,
            )->keyBy('id');

            $legacy = $db->table('legacy_transaction_v2_map as provenance')
                ->join('transactions as legacy', 'legacy.id', '=', 'provenance.legacy_transaction_id')
                ->join('spj_transactions as v2', 'v2.id', '=', 'provenance.spj_transaction_id')
                ->where('provenance.canonical_context_status', 'ACTIVE_CANONICAL')
                ->where('v2.canonical_context_status', 'ACTIVE_CANONICAL')
                ->where('v2.fiscal_year_id', $context->fiscal_year_id)
                ->where('v2.fund_source_id', $context->fund_source_id)
                ->where('v2.source_id', $context->source_id)
                ->select([
                    'provenance.spj_transaction_id',
                    'legacy.*',
                ])
                ->get()
                ->keyBy('spj_transaction_id');

            $canonicalCount += $canonical->count();
            $legacyCount += $legacy->count();

            $canonicalIds = $canonical->keys()->map(fn ($id): int => (int) $id)->sort()->values()->all();
            $legacyIds = $legacy->keys()->map(fn ($id): int => (int) $id)->sort()->values()->all();
            if ($canonicalIds !== $legacyIds) {
                $transactionMismatches[] = [
                    'context' => $contextKey,
                    'field' => 'identity_set',
                    'canonical_only' => array_values(array_diff($canonicalIds, $legacyIds)),
                    'legacy_only' => array_values(array_diff($legacyIds, $canonicalIds)),
                ];
            }

            foreach (array_values(array_intersect($canonicalIds, $legacyIds)) as $v2Id) {
                $canonicalRow = $canonical->get($v2Id);
                $legacyRow = $legacy->get($v2Id);
                foreach ($this->transactionDifferences($canonicalRow, $legacyRow) as $field => $values) {
                    $transactionMismatches[] = [
                        'context' => $contextKey,
                        'spj_transaction_id' => $v2Id,
                        'legacy_transaction_id' => (int) $legacyRow->id,
                        'field' => $field,
                        ...$values,
                    ];
                }
            }

            foreach ($this->periodWindows() as $periodKey => $window) {
                $canonicalRows = $canonical->filter(fn (array $row): bool => $this->inWindow($row['transaction_date'] ?? null, $window));
                $legacyRows = $legacy->filter(fn (object $row): bool => $this->inWindow($row->transaction_date ?? null, $window));

                $canonicalReport = $this->reportSummary(
                    $canonicalRows,
                    fn (array $row): ?object => $packagesByV2->get($row['id']),
                    $documentsByPackage,
                    fn (array $row): int => count($row['items'] ?? []),
                );
                $legacyReport = $this->reportSummary(
                    $legacyRows,
                    fn (object $row): ?object => $packagesByLegacy->get($row->id),
                    $documentsByPackage,
                    fn (object $row): int => (int) ($itemCounts[$row->id] ?? 0),
                );
                if ($canonicalReport !== $legacyReport) {
                    $reportMismatches[] = [
                        'context' => $contextKey,
                        'period' => $periodKey,
                        'canonical' => $canonicalReport,
                        'legacy' => $legacyReport,
                    ];
                }

                $canonicalTax = $this->taxSummary($canonicalRows);
                $legacyTax = $this->taxSummary($legacyRows);
                if ($canonicalTax !== $legacyTax) {
                    $taxMismatches[] = [
                        'context' => $contextKey,
                        'period' => $periodKey,
                        'canonical' => $canonicalTax,
                        'legacy' => $legacyTax,
                    ];
                }
            }

            foreach ([1, 2, 3, 4] as $quarter) {
                $window = ['type' => 'quarter', 'value' => $quarter];
                $canonicalRows = $canonical->filter(fn (array $row): bool => $this->inWindow($row['transaction_date'] ?? null, $window));
                $legacyRows = $legacy->filter(fn (object $row): bool => $this->inWindow($row->transaction_date ?? null, $window));

                $canonicalPeriod = $this->periodSummary(
                    $canonicalRows,
                    fn (array $row): ?object => $packagesByV2->get($row['id']),
                    fn (array $row): int => count($row['items'] ?? []),
                    fn (array $row): bool => (bool) ($row['requires_reconciliation'] ?? false),
                    fn (array $row): string => (string) ($row['source_status'] ?? ''),
                );
                $legacyPeriod = $this->periodSummary(
                    $legacyRows,
                    fn (object $row): ?object => $packagesByLegacy->get($row->id),
                    fn (object $row): int => (int) ($itemCounts[$row->id] ?? 0),
                    fn (object $row): bool => (bool) ($row->requires_reconciliation ?? false),
                    fn (object $row): string => (string) ($row->source_status ?? ''),
                );
                if ($canonicalPeriod !== $legacyPeriod) {
                    $delta = [
                        'context' => $contextKey,
                        'quarter' => $quarter,
                        'canonical' => $canonicalPeriod,
                        'legacy' => $legacyPeriod,
                    ];

                    if ($this->periodDecision($canonicalPeriod) !== $this->periodDecision($legacyPeriod)) {
                        $periodMismatches[] = $delta;
                    } else {
                        $periodRepresentationDeltas[] = $delta;
                    }
                }
            }

            $canonicalActivities = $this->realizationByCode($canonical, 'activity_code');
            $legacyActivities = $this->realizationByCode($legacy, 'activity_code');
            if ($canonicalActivities !== $legacyActivities) {
                $activityMismatches[] = [
                    'context' => $contextKey,
                    'canonical' => $canonicalActivities,
                    'legacy' => $legacyActivities,
                ];
            }

            $canonicalAccounts = $this->realizationByCode($canonical, 'account_code');
            $legacyAccounts = $this->realizationByCode($legacy, 'account_code');
            if ($canonicalAccounts !== $legacyAccounts) {
                $accountMismatches[] = [
                    'context' => $contextKey,
                    'canonical' => $canonicalAccounts,
                    'legacy' => $legacyAccounts,
                ];
            }
        }

        $status = $transactionMismatches === []
            && $reportMismatches === []
            && $taxMismatches === []
            && $periodMismatches === []
            && $activityMismatches === []
            && $accountMismatches === []
            ? 'PASS'
            : 'FAIL';

        return [
            'status' => $status,
            'counts' => [
                'contexts' => $contexts->count(),
                'canonical_transactions' => $canonicalCount,
                'legacy_active_provenance_transactions' => $legacyCount,
            ],
            'transactions' => [
                'mismatch_count' => count($transactionMismatches),
                'mismatches' => $transactionMismatches,
            ],
            'reports' => [
                'mismatch_count' => count($reportMismatches),
                'mismatches' => $reportMismatches,
                'period_windows_checked_per_context' => count($this->periodWindows()),
            ],
            'taxes' => [
                'mismatch_count' => count($taxMismatches),
                'mismatches' => $taxMismatches,
            ],
            'period_workflow' => [
                'mismatch_count' => count($periodMismatches),
                'mismatches' => $periodMismatches,
                'representation_delta_count' => count($periodRepresentationDeltas),
                'representation_deltas' => $periodRepresentationDeltas,
            ],
            'activity_realization' => [
                'mismatch_count' => count($activityMismatches),
                'mismatches' => $activityMismatches,
            ],
            'account_realization' => [
                'mismatch_count' => count($accountMismatches),
                'mismatches' => $accountMismatches,
            ],
        ];
    }

    /** @return array<string, array{canonical: mixed, legacy: mixed}> */
    private function transactionDifferences(array $canonical, object $legacy): array
    {
        $differences = [];
        foreach ([
            'no_bukti' => [$canonical['no_bukti'] ?? null, $legacy->no_bukti ?? null],
            'transaction_date' => [$this->date($canonical['transaction_date'] ?? null), $this->date($legacy->transaction_date ?? null)],
            'description' => [$canonical['description'] ?? null, $legacy->description ?? null],
            'activity_code' => [$canonical['activity_code'] ?? null, $legacy->activity_code ?? null],
            'account_code' => [$canonical['account_code'] ?? null, $legacy->account_code ?? null],
            'recipient_name' => [$canonical['recipient_name'] ?? null, $legacy->recipient_name ?? null],
            'source_status' => [$canonical['source_status'] ?? null, $legacy->source_status ?? null],
            'requires_reconciliation' => [(bool) ($canonical['requires_reconciliation'] ?? false), (bool) ($legacy->requires_reconciliation ?? false)],
        ] as $field => [$canonicalValue, $legacyValue]) {
            $canonicalValue = is_bool($canonicalValue) ? $canonicalValue : $this->normalize($canonicalValue);
            $legacyValue = is_bool($legacyValue) ? $legacyValue : $this->normalize($legacyValue);
            if ($canonicalValue !== $legacyValue) {
                $differences[$field] = ['canonical' => $canonicalValue, 'legacy' => $legacyValue];
            }
        }

        foreach (['gross_amount', 'ppn', 'pph21', 'pph22', 'pph23', 'pph4', 'sspd', 'tax_total', 'net_amount'] as $field) {
            $canonicalValue = round((float) ($canonical[$field] ?? 0), 2);
            $legacyValue = round((float) ($legacy->{$field} ?? 0), 2);
            if (abs($canonicalValue - $legacyValue) > 0.01) {
                $differences[$field] = ['canonical' => $canonicalValue, 'legacy' => $legacyValue];
            }
        }

        return $differences;
    }

    /**
     * @param Collection<int|string, mixed> $rows
     * @param callable(mixed): ?object $packageResolver
     * @param callable(mixed): int $itemCount
     * @return array<string, float|int>
     */
    private function reportSummary(Collection $rows, callable $packageResolver, Collection $documentsByPackage, callable $itemCount): array
    {
        $summary = [
            'successful_count' => 0,
            'cancelled_count' => 0,
            'pending_count' => 0,
            'gross' => 0.0,
            'tax' => 0.0,
            'net' => 0.0,
            'ppn' => 0.0,
            'pph21' => 0.0,
            'pph22' => 0.0,
            'pph23' => 0.0,
            'pph4' => 0.0,
            'sspd' => 0.0,
        ];

        foreach ($rows as $row) {
            $package = $packageResolver($row);
            $documentNumber = $package?->document_number;
            if ($documentNumber !== null && trim((string) $documentNumber) !== '') {
                $summary['successful_count']++;
                foreach (['gross_amount' => 'gross', 'tax_total' => 'tax', 'net_amount' => 'net', 'ppn' => 'ppn', 'pph21' => 'pph21', 'pph22' => 'pph22', 'pph23' => 'pph23', 'pph4' => 'pph4', 'sspd' => 'sspd'] as $field => $target) {
                    $summary[$target] += (float) $this->value($row, $field);
                }

                continue;
            }

            $cancelled = false;
            if ($package !== null) {
                foreach ($documentsByPackage->get($package->id, collect()) as $document) {
                    if ((string) $document->document_type === 'SPJ'
                        && (string) $document->scope_key === 'MAIN'
                        && (string) $document->status === 'CANCELLED') {
                        $cancelled = true;
                        break;
                    }
                }
            }
            if ($cancelled) {
                $summary['cancelled_count']++;
            }
            if ($itemCount($row) > 0) {
                $summary['pending_count']++;
            }
        }

        return $this->roundSummary($summary);
    }

    /** @param Collection<int|string, mixed> $rows @return array<string, float|int> */
    private function taxSummary(Collection $rows): array
    {
        $summary = ['count' => 0, 'ppn' => 0.0, 'pph21' => 0.0, 'pph22' => 0.0, 'pph23' => 0.0, 'pph4' => 0.0, 'sspd' => 0.0, 'total' => 0.0];
        foreach ($rows as $row) {
            $total = (float) $this->value($row, 'tax_total');
            if ($total <= 0) {
                continue;
            }

            $summary['count']++;
            foreach (['ppn', 'pph21', 'pph22', 'pph23', 'pph4', 'sspd'] as $field) {
                $summary[$field] += (float) $this->value($row, $field);
            }
            $summary['total'] += $total;
        }

        return $this->roundSummary($summary);
    }

    /**
     * @param Collection<int|string, mixed> $rows
     * @param callable(mixed): ?object $packageResolver
     * @param callable(mixed): int $itemCount
     * @param callable(mixed): bool $requiresReconciliation
     * @param callable(mixed): string $sourceStatus
     * @return array<string, int>
     */
    private function periodSummary(
        Collection $rows,
        callable $packageResolver,
        callable $itemCount,
        callable $requiresReconciliation,
        callable $sourceStatus,
    ): array {
        $summary = ['transactions' => $rows->count(), 'reconciliation_blockers' => 0, 'without_package' => 0, 'not_final' => 0];

        foreach ($rows as $row) {
            if ($requiresReconciliation($row) || $sourceStatus($row) === 'SOURCE_MISSING') {
                $summary['reconciliation_blockers']++;
            }

            $package = $packageResolver($row);
            if ($itemCount($row) > 0 && $package === null) {
                $summary['without_package']++;
            }
            if ($package !== null && (string) $package->status !== 'FINAL') {
                $summary['not_final']++;
            }
        }

        $summary['unfinished'] = $summary['without_package'] + $summary['not_final'];

        return $summary;
    }

    /** @param array<string, int> $summary @return array{transactions:int,reconciliation_blockers:int,unfinished:int} */
    private function periodDecision(array $summary): array
    {
        return [
            'transactions' => (int) $summary['transactions'],
            'reconciliation_blockers' => (int) $summary['reconciliation_blockers'],
            'unfinished' => (int) $summary['unfinished'],
        ];
    }

    /** @param Collection<int|string, mixed> $rows @return array<string, float> */
    private function realizationByCode(Collection $rows, string $field): array
    {
        $result = [];
        foreach ($rows as $row) {
            $code = $this->normalize($this->value($row, $field)) ?? '-';
            $result[$code] = ($result[$code] ?? 0.0) + (float) $this->value($row, 'gross_amount');
        }
        foreach ($result as &$amount) {
            $amount = round($amount, 2);
        }
        unset($amount);
        ksort($result);

        return $result;
    }

    /** @return array<string, array{type: string, value?: int}> */
    private function periodWindows(): array
    {
        $windows = ['all' => ['type' => 'all']];
        foreach (range(1, 12) as $month) {
            $windows['month:'.$month] = ['type' => 'month', 'value' => $month];
        }
        foreach (range(1, 4) as $quarter) {
            $windows['quarter:'.$quarter] = ['type' => 'quarter', 'value' => $quarter];
        }
        foreach (range(1, 2) as $semester) {
            $windows['semester:'.$semester] = ['type' => 'semester', 'value' => $semester];
        }

        return $windows;
    }

    /** @param array{type: string, value?: int} $window */
    private function inWindow(mixed $date, array $window): bool
    {
        if ($window['type'] === 'all') {
            return true;
        }

        $normalized = $this->date($date);
        if ($normalized === null) {
            return false;
        }

        $month = (int) substr($normalized, 5, 2);

        return match ($window['type']) {
            'month' => $month === (int) $window['value'],
            'quarter' => (int) ceil($month / 3) === (int) $window['value'],
            'semester' => (int) ceil($month / 6) === (int) $window['value'],
            default => false,
        };
    }

    private function value(mixed $row, string $field): mixed
    {
        return is_array($row) ? ($row[$field] ?? null) : ($row->{$field} ?? null);
    }

    /** @param array<string, float|int> $summary @return array<string, float|int> */
    private function roundSummary(array $summary): array
    {
        foreach ($summary as $key => $value) {
            if (is_float($value)) {
                $summary[$key] = round($value, 2);
            }
        }

        return $summary;
    }

    private function date(mixed $value): ?string
    {
        $value = $this->normalize($value);
        if ($value === null) {
            return null;
        }

        return substr($value, 0, 10);
    }

    private function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function assertSchema(Connection $db): void
    {
        foreach ([
            'transactions',
            'transaction_items',
            'spj_packages',
            'spj_documents',
            'spj_transactions',
            'legacy_transaction_v2_map',
        ] as $table) {
            if (! $db->getSchemaBuilder()->hasTable($table)) {
                throw new RuntimeException('V2-D workflow parity requires table: '.$table);
            }
        }

        if (! $db->getSchemaBuilder()->hasColumn('spj_packages', 'spj_transaction_id')) {
            throw new RuntimeException('V2-D workflow parity requires spj_packages.spj_transaction_id.');
        }
    }
}
