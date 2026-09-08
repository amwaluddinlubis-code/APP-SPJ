<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class DiffSpjAudit extends Command
{
    protected $signature = 'spj:audit-diff
        {before : Snapshot JSON audit sebelum perubahan}
        {after : Snapshot JSON audit sesudah perubahan}
        {--json : Tampilkan hasil diff sebagai JSON}
        {--fail-on-regression : Return exit code 1 bila critical/warning atau data-quality blocker bertambah}';

    protected $description = 'Bandingkan dua snapshot spj:audit-quarter tanpa membaca atau mengubah database tenant.';

    public function handle(): int
    {
        try {
            $before = $this->readSnapshot((string) $this->argument('before'));
            $after = $this->readSnapshot((string) $this->argument('after'));
            $this->assertComparable($before, $after);
        } catch (\Throwable $exception) {
            $this->error('Audit diff gagal: '.$exception->getMessage());

            return self::FAILURE;
        }

        $summaryFields = [
            'transactions',
            'packages',
            'unassigned_category',
            'blank_item_descriptions',
            'financial_mismatches',
            'critical',
            'warnings',
            'info',
        ];
        $summary = [];
        foreach ($summaryFields as $field) {
            $summary[$field] = $this->delta(
                (int) ($before['summary'][$field] ?? 0),
                (int) ($after['summary'][$field] ?? 0),
            );
        }

        $beforeCodes = $this->anomalyCounts($before['anomalies'] ?? []);
        $afterCodes = $this->anomalyCounts($after['anomalies'] ?? []);
        $allCodes = array_values(array_unique([...array_keys($beforeCodes), ...array_keys($afterCodes)]));
        sort($allCodes);
        $anomalyDiff = [];
        foreach ($allCodes as $code) {
            $anomalyDiff[$code] = $this->delta($beforeCodes[$code] ?? 0, $afterCodes[$code] ?? 0);
        }

        $categoryDiff = [];
        $beforeCategories = $this->indexCategories($before['categories'] ?? []);
        $afterCategories = $this->indexCategories($after['categories'] ?? []);
        foreach (array_values(array_unique([...array_keys($beforeCategories), ...array_keys($afterCategories)])) as $category) {
            $left = $beforeCategories[$category] ?? [];
            $right = $afterCategories[$category] ?? [];
            $categoryDiff[$category] = [];
            foreach (['transactions', 'packages', 'none', 'draft', 'ready', 'numbered', 'final', 'cancelled', 'candidate_count'] as $field) {
                $categoryDiff[$category][$field] = $this->delta((int) ($left[$field] ?? 0), (int) ($right[$field] ?? 0));
            }
        }
        ksort($categoryDiff);

        $candidateChanges = [];
        foreach (array_values(array_unique([...array_keys($before['candidates'] ?? []), ...array_keys($after['candidates'] ?? [])])) as $category) {
            $old = $before['candidates'][$category] ?? null;
            $new = $after['candidates'][$category] ?? null;
            $oldId = $old['transaction_id'] ?? null;
            $newId = $new['transaction_id'] ?? null;
            if ($oldId !== $newId || ($old['issues'] ?? []) !== ($new['issues'] ?? [])) {
                $candidateChanges[$category] = [
                    'before_transaction_id' => $oldId,
                    'after_transaction_id' => $newId,
                    'before_issues' => $old['issues'] ?? [],
                    'after_issues' => $new['issues'] ?? [],
                ];
            }
        }

        $regression = $this->hasRegression($summary);
        $result = [
            'context' => $after['context'],
            'school' => $after['school'] ?? null,
            'summary' => $summary,
            'anomaly_codes' => $anomalyDiff,
            'categories' => $categoryDiff,
            'candidate_changes' => $candidateChanges,
            'regression' => $regression,
        ];

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->renderHuman($result);
        }

        if ((bool) $this->option('fail-on-regression') && $regression) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function readSnapshot(string $path): array
    {
        $path = $this->absolutePath($path);
        if (! File::isFile($path)) {
            throw new \RuntimeException('Snapshot tidak ditemukan: '.$path);
        }

        $decoded = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($decoded) || ! isset($decoded['summary'], $decoded['context'], $decoded['anomalies'])) {
            throw new \RuntimeException('File bukan snapshot spj:audit-quarter yang valid: '.$path);
        }

        return $decoded;
    }

    /** @param array<string, mixed> $before @param array<string, mixed> $after */
    private function assertComparable(array $before, array $after): void
    {
        $keys = ['year', 'quarter', 'fund_source_id'];
        foreach ($keys as $key) {
            if (($before['context'][$key] ?? null) !== ($after['context'][$key] ?? null)) {
                throw new \RuntimeException('Snapshot memakai konteks berbeda pada '.$key.'.');
            }
        }

        $beforeNpsn = $before['school']['npsn'] ?? null;
        $afterNpsn = $after['school']['npsn'] ?? null;
        if ($beforeNpsn !== null && $afterNpsn !== null && (string) $beforeNpsn !== (string) $afterNpsn) {
            throw new \RuntimeException('Snapshot berasal dari sekolah/NPSN yang berbeda.');
        }
    }

    /** @return array{before:int,after:int,delta:int} */
    private function delta(int $before, int $after): array
    {
        return ['before' => $before, 'after' => $after, 'delta' => $after - $before];
    }

    /** @param array<int, array<string, mixed>> $anomalies @return array<string, int> */
    private function anomalyCounts(array $anomalies): array
    {
        $counts = [];
        foreach ($anomalies as $anomaly) {
            $code = strtoupper(trim((string) ($anomaly['code'] ?? 'UNKNOWN')));
            $counts[$code] = ($counts[$code] ?? 0) + 1;
        }
        ksort($counts);

        return $counts;
    }

    /** @param array<int, array<string, mixed>> $rows @return array<string, array<string, mixed>> */
    private function indexCategories(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $category = strtoupper(trim((string) ($row['category'] ?? '')));
            if ($category !== '') {
                $indexed[$category] = $row;
            }
        }

        return $indexed;
    }

    /** @param array<string, array{before:int,after:int,delta:int}> $summary */
    private function hasRegression(array $summary): bool
    {
        foreach (['critical', 'warnings', 'blank_item_descriptions', 'financial_mismatches', 'unassigned_category'] as $field) {
            if (($summary[$field]['delta'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $result */
    private function renderHuman(array $result): void
    {
        $this->newLine();
        $this->info('SPJ AUDIT SNAPSHOT DIFF');
        $this->line('Tahun/Triwulan : '.$result['context']['year'].' / TW'.$result['context']['quarter']);
        $this->line('Sumber Dana    : '.($result['context']['fund_source_id'] ?? 'SEMUA'));
        $this->newLine();

        $this->table(
            ['Metric', 'Before', 'After', 'Delta'],
            array_map(
                fn (string $field, array $values): array => [$field, $values['before'], $values['after'], $this->formatDelta($values['delta'])],
                array_keys($result['summary']),
                array_values($result['summary']),
            ),
        );

        $changedCodes = array_filter($result['anomaly_codes'], fn (array $values): bool => $values['delta'] !== 0);
        if ($changedCodes !== []) {
            $this->info('Perubahan anomaly');
            $this->table(
                ['Code', 'Before', 'After', 'Delta'],
                array_map(
                    fn (string $code, array $values): array => [$code, $values['before'], $values['after'], $this->formatDelta($values['delta'])],
                    array_keys($changedCodes),
                    array_values($changedCodes),
                ),
            );
        }

        if ($result['candidate_changes'] !== []) {
            $this->info('Perubahan kandidat E2E');
            $this->table(
                ['Kategori', 'Before Tx', 'After Tx', 'Before Issues', 'After Issues'],
                array_map(
                    fn (string $category, array $change): array => [
                        $category,
                        $change['before_transaction_id'] ?? '-',
                        $change['after_transaction_id'] ?? '-',
                        implode(', ', $change['before_issues']) ?: '-',
                        implode(', ', $change['after_issues']) ?: '-',
                    ],
                    array_keys($result['candidate_changes']),
                    array_values($result['candidate_changes']),
                ),
            );
        }

        $result['regression']
            ? $this->warn('DIFF RESULT: REGRESSION — blocker/anomaly utama bertambah.')
            : $this->info('DIFF RESULT: NO REGRESSION pada metric release-safety yang dibandingkan.');
    }

    private function formatDelta(int $delta): string
    {
        return $delta > 0 ? '+'.$delta : (string) $delta;
    }

    private function absolutePath(string $path): string
    {
        if (str_starts_with($path, '/') || preg_match('~^[A-Za-z]:[\\\\/]~', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }
}
