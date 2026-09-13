<?php

namespace App\Services;

use App\Models\ArkasImportProfile;
use App\Models\ArkasImportRun;
use App\Models\ArkasSource;
use App\Models\FiscalYear;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Fetches Bridge payloads into tenant staging before a domain adapter consumes them. */
class ArkasStagingService
{
    private readonly ArkasSourceKeyResolver $sourceKeys;

    private readonly ArkasImportRowSynchronizer $rows;

    public function __construct(
        private readonly ArkasBridgeClient $bridge,
        ?ArkasSourceKeyResolver $sourceKeys = null,
        ?ArkasImportRowSynchronizer $rows = null,
    ) {
        $this->sourceKeys = $sourceKeys ?? new ArkasSourceKeyResolver;
        $this->rows = $rows ?? new ArkasImportRowSynchronizer($this->sourceKeys);
    }

    /** @return array<int, array<string, mixed>> */
    public function stage(string $sourceTable, string $command, FiscalYear $year, ArkasSource $source, string $parser = 'decode', ?int $bridgeYear = null, ?int $fundSource = null): array
    {
        $profile = ArkasImportProfile::query()->firstOrCreate(
            ['source_table' => $sourceTable],
            [...ArkasDomainAdapter::presetFor($sourceTable), 'label' => 'Auto '.$sourceTable],
        );
        $preset = ArkasDomainAdapter::presetFor($sourceTable);
        $lock = Cache::lock(
            ArkasTenantLockKey::staging($source, $sourceTable, (int) $year->id),
            900,
        );
        if (! $lock->get()) {
            throw new \RuntimeException('Staging ARKAS sedang berjalan untuk tabel, sekolah, dan tahun anggaran ini.');
        }

        $run = ArkasImportRun::query()->create([
            'profile_id' => $profile->id,
            'fiscal_year_id' => $year->id,
            'status' => 'RUNNING',
            'started_at' => now(),
        ]);

        try {
            $bridgeYear ??= in_array($command, ['bku', 'rkas', 'fund-sources'], true) ? $year->year : null;
            $fundSource ??= in_array($command, ['bku', 'rkas'], true) ? $year->fund_source_id : null;
            $records = $this->fetch($profile, $year, $source, $command, $parser, $bridgeYear, $fundSource);
            $sourceKeyColumn = $profile->source_key_column ?: $preset['source_key_column'];
            $metrics = DB::connection('school')->transaction(
                fn (): array => $this->rows->synchronize($profile, $year, $records, $sourceKeyColumn),
            );
            $run->update([
                'status' => 'SUCCESS',
                'records_read' => count($records),
                'records_written' => $metrics['written'],
                'records_new' => $metrics['new'],
                'records_changed' => $metrics['changed'],
                'records_unchanged' => $metrics['unchanged'],
                'records_removed' => $metrics['removed'],
                'message' => 'Payload Bridge tersimpan di staging.',
                'finished_at' => now(),
            ]);
            $profile->update(['last_synced_at' => now()]);

            return $records;
        } catch (\Throwable $exception) {
            $run->update(['status' => 'FAILED', 'message' => $exception->getMessage(), 'finished_at' => now()]);
            throw $exception;
        } finally {
            $lock->release();
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function fetch(ArkasImportProfile $profile, FiscalYear $year, ArkasSource $source, string $command, string $parser = 'decode', ?int $bridgeYear = null, ?int $fundSource = null): array
    {
        $preset = ArkasDomainAdapter::presetFor($profile->source_table);
        $bridgeYear ??= in_array($command, ['bku', 'rkas', 'fund-sources'], true) ? $year->year : null;
        $fundSource ??= in_array($command, ['bku', 'rkas'], true) ? $year->fund_source_id : null;
        $table = $command === 'rows' ? $profile->source_table : null;
        $output = $this->bridge->execute($source, $command, $bridgeYear, $table, $fundSource, 100000);
        $records = match ($parser) {
            'values' => [ArkasPipePayload::values($output, $command)],
            'pairs' => array_map(static fn (array $pair): array => ['id' => $pair['id'], 'name' => $pair['name']], ArkasPipePayload::pairs($output, $command)),
            'lines' => array_map(static fn (string $line): array => ['value' => $line], ArkasPipePayload::lines($output, $command)),
            default => ArkasPipePayload::decode($output, $command.':'.$profile->source_table),
        };
        if (in_array($command, ['bku', 'rkas'], true)) {
            $records = array_values(array_filter($records, fn (array $record): bool => (int) ($record['ID_REF_SUMBER_DANA'] ?? $record['id_ref_sumber_dana'] ?? 0) === (int) $year->fund_source_id));
        }
        $records = $this->filterToFiscalContext($records, $profile, $year);
        if ($profile->source_table === 'rapbs_periode') {
            $records = $this->filterToActiveBudget($records, $source, $year);
        }

        return $this->deduplicate($records, $profile, $preset);
    }

    /**
     * Reference tables are fetched through the generic rows command, so the
     * bridge cannot apply the active fiscal context for them. Apply the
     * configured source columns before staging and domain synchronization.
     *
     * @param  array<int, array<string, mixed>>  $records
     * @return array<int, array<string, mixed>>
     */
    private function filterToFiscalContext(array $records, ArkasImportProfile $profile, FiscalYear $year): array
    {
        if (blank($profile->year_column) && blank($profile->fund_source_column)) {
            return $records;
        }

        return array_values(array_filter($records, function (array $record) use ($profile, $year): bool {
            if (filled($profile->year_column)) {
                $sourceYear = $this->value($record, (string) $profile->year_column);
                if ($sourceYear !== null && (int) $sourceYear !== (int) $year->year) {
                    return false;
                }
            }

            if (filled($profile->fund_source_column)) {
                $sourceFund = $this->value($record, (string) $profile->fund_source_column);
                if ($sourceFund !== null && (int) $sourceFund !== (int) $year->fund_source_id) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * rapbs_periode does not carry the budget version. Resolve the latest
     * approved and active anggaran first, then keep only its rapbs details.
     * Without this, every approved revision is imported into the same year.
     *
     * @param  array<int, array<string, mixed>>  $records
     * @return array<int, array<string, mixed>>
     */
    private function filterToActiveBudget(array $records, ArkasSource $source, FiscalYear $year): array
    {
        $anggaran = ArkasPipePayload::decode(
            $this->bridge->execute($source, 'rows', null, 'anggaran', null, 100000),
            'rows:anggaran',
        );
        $eligible = array_values(array_filter($anggaran, function (array $row) use ($year): bool {
            return (int) ($this->value($row, 'tahun_anggaran') ?? 0) === (int) $year->year
                && (int) ($this->value($row, 'id_ref_sumber_dana') ?? 0) === (int) $year->fund_source_id
                && (int) ($this->value($row, 'is_approve') ?? 0) === 1
                && (int) ($this->value($row, 'is_aktif') ?? 0) === 1
                && (int) ($this->value($row, 'soft_delete') ?? 0) === 0;
        }));
        usort($eligible, function (array $left, array $right): int {
            $revision = (int) ($this->value($right, 'is_revisi') ?? 0) <=> (int) ($this->value($left, 'is_revisi') ?? 0);
            if ($revision !== 0) {
                return $revision;
            }

            return strcmp((string) ($this->value($right, 'tanggal_pengesahan') ?? ''), (string) ($this->value($left, 'tanggal_pengesahan') ?? ''));
        });
        $activeBudget = $eligible[0] ?? null;
        if ($activeBudget === null) {
            return $records;
        }

        $activeBudgetId = (string) $this->value($activeBudget, 'id_anggaran');
        $rapbs = ArkasPipePayload::decode(
            $this->bridge->execute($source, 'rows', null, 'rapbs', null, 100000),
            'rows:rapbs',
        );
        $activeRapbs = [];
        foreach ($rapbs as $row) {
            if ((string) $this->value($row, 'id_anggaran') === $activeBudgetId) {
                $key = $this->value($row, 'id_rapbs');
                if ($key !== null) {
                    $activeRapbs[(string) $key] = true;
                }
            }
        }
        if ($activeRapbs === []) {
            return [];
        }

        return array_values(array_filter($records, fn (array $row): bool => isset($activeRapbs[(string) ($this->value($row, 'id_rapbs') ?? '')])));
    }

    /** @param array<string, mixed> $row */
    private function value(array $row, string $column): mixed
    {
        foreach ($row as $key => $value) {
            if (strcasecmp((string) $key, $column) === 0) {
                return $value;
            }
        }

        return null;
    }

    /** @param array<int, array<string, mixed>> $records @param array<string, mixed> $preset @return array<int, array<string, mixed>> */
    private function deduplicate(array $records, ArkasImportProfile $profile, array $preset): array
    {
        $indexed = [];
        $sourceKeyColumn = $profile->source_key_column ?: $preset['source_key_column'];
        foreach ($records as $record) {
            $indexed[$this->sourceKeys->resolve($record, $sourceKeyColumn)] = $record;
        }

        return array_values($indexed);
    }
}
