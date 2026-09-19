<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

final class SpjV2ReportFinancialSummaryService
{
    public function __construct(
        private readonly SpjReadPathSelector $readPaths,
        private readonly SpjV2CanonicalReadService $canonicalReads,
    ) {}

    /**
     * Return a V2 financial summary only when the selector and required bridge
     * relations are fully eligible. Null means the consumer must use legacy.
     *
     * @param array{count:int,cancelled_count:int,gross:float,tax:float,net:float,ppn:float,pph21:float,pph22:float,pph23:float,pph4:float,sspd:float} $consumerSummary
     * @return array{count:int,cancelled_count:int,gross:float,tax:float,net:float,ppn:float,pph21:float,pph22:float,pph23:float,pph4:float,sspd:float,source_id:int}|null
     */
    public function forContext(
        Connection $db,
        int $fiscalYearId,
        int $fundSourceId,
        int $year,
        string $mode,
        ?int $periode,
        array $consumerSummary,
    ): ?array {
        $selection = $this->readPaths->select($db, $fiscalYearId, $fundSourceId);
        if ($selection['path'] !== SpjReadPathSelector::V2 || $selection['source_id'] === null) {
            return null;
        }

        $schema = $db->getSchemaBuilder();
        if (! $schema->hasTable('spj_packages')
            || ! $schema->hasColumn('spj_packages', 'spj_transaction_id')
            || ! $schema->hasTable('spj_documents')) {
            return null;
        }

        $rows = $this->canonicalReads
            ->forContext($db, $fiscalYearId, $fundSourceId, $selection['source_id'])
            ->filter(fn (array $row): bool => $this->inPeriod($row['transaction_date'] ?? null, $year, $mode, $periode))
            ->values();

        $transactionIds = $rows->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $packages = $transactionIds === []
            ? collect()
            : $db->table('spj_packages')
                ->whereIn('spj_transaction_id', $transactionIds)
                ->get()
                ->keyBy('spj_transaction_id');

        $successful = $rows->filter(function (array $row) use ($packages): bool {
            $package = $packages->get($row['id']);

            return $package !== null && filled($package->document_number);
        });

        $cancelledPackageIds = $packages
            ->filter(fn (object $package): bool => blank($package->document_number))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $cancelledCount = $cancelledPackageIds === []
            ? 0
            : (int) $db->table('spj_documents')
                ->whereIn('spj_package_id', $cancelledPackageIds)
                ->where('document_type', 'SPJ')
                ->where('scope_key', 'MAIN')
                ->where('status', 'CANCELLED')
                ->distinct()
                ->count('spj_package_id');

        $summary = [
            'count' => $successful->count(),
            'cancelled_count' => $cancelledCount,
            'gross' => $this->sum($successful, 'gross_amount'),
            'tax' => $this->sum($successful, 'tax_total'),
            'net' => $this->sum($successful, 'net_amount'),
            'ppn' => $this->sum($successful, 'ppn'),
            'pph21' => $this->sum($successful, 'pph21'),
            'pph22' => $this->sum($successful, 'pph22'),
            'pph23' => $this->sum($successful, 'pph23'),
            'pph4' => $this->sum($successful, 'pph4'),
            'sspd' => $this->sum($successful, 'sspd'),
            'source_id' => $selection['source_id'],
        ];

        if (! $this->matchesConsumerSummary($summary, $consumerSummary)) {
            return null;
        }

        return $summary;
    }

    /**
     * @param array<string, mixed> $canonical
     * @param array<string, mixed> $consumer
     */
    private function matchesConsumerSummary(array $canonical, array $consumer): bool
    {
        foreach (['count', 'cancelled_count'] as $field) {
            if ((int) ($canonical[$field] ?? 0) !== (int) ($consumer[$field] ?? 0)) {
                return false;
            }
        }

        foreach (['gross', 'tax', 'net', 'ppn', 'pph21', 'pph22', 'pph23', 'pph4', 'sspd'] as $field) {
            if (abs(round((float) ($canonical[$field] ?? 0), 2) - round((float) ($consumer[$field] ?? 0), 2)) > 0.01) {
                return false;
            }
        }

        return true;
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    private function sum(Collection $rows, string $field): float
    {
        return round((float) $rows->sum(fn (array $row): float => (float) ($row[$field] ?? 0)), 2);
    }

    private function inPeriod(mixed $value, int $year, string $mode, ?int $periode): bool
    {
        if (blank($value)) {
            return $mode === 'semua';
        }

        try {
            $date = Carbon::parse((string) $value);
        } catch (\Throwable) {
            return false;
        }

        if ($date->year !== $year) {
            return false;
        }

        return match ($mode) {
            'bulan' => $periode !== null && $periode >= 1 && $periode <= 12 && $date->month === $periode,
            'triwulan' => $periode !== null && $periode >= 1 && $periode <= 4 && (int) ceil($date->month / 3) === $periode,
            'semester' => $periode !== null && $periode >= 1 && $periode <= 2 && (int) ceil($date->month / 6) === $periode,
            default => true,
        };
    }
}
