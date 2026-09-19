<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;

final class SpjV2TaxReadContextService
{
    public function __construct(
        private readonly SpjReadPathSelector $readPaths,
        private readonly SpjV2EffectiveContextCompatibilityService $effectiveContexts,
        private readonly SpjV2CanonicalReadService $canonicalReads,
    ) {}

    /**
     * Resolve one deterministic legacy representative for every canonical
     * transaction in the active effective context, but only when the fields
     * consumed by the Pajak UI still match canonical raw facts exactly.
     *
     * Null means the caller must stay on the legacy ActiveSpjContext path.
     *
     * @return array{legacy_transaction_ids:list<int>,canonical_transaction_ids:list<int>,source_id:int,compatibility_mode:string}|null
     */
    public function forContext(
        Connection $db,
        int $fiscalYearId,
        int $fundSourceId,
    ): ?array {
        $selection = $this->readPaths->select($db, $fiscalYearId, $fundSourceId);
        if ($selection['path'] !== SpjReadPathSelector::V2 || $selection['source_id'] === null) {
            return null;
        }

        $resolved = $this->effectiveContexts->resolve(
            $db,
            $fiscalYearId,
            $fundSourceId,
            $selection['source_id'],
        );
        if ($resolved['status'] !== 'RESOLVED') {
            return null;
        }

        $canonical = $this->canonicalReads
            ->forContext($db, $fiscalYearId, $fundSourceId, $selection['source_id'])
            ->keyBy('id');

        $legacy = $db->table('legacy_transaction_v2_map as provenance')
            ->join('transactions as legacy', 'legacy.id', '=', 'provenance.legacy_transaction_id')
            ->join('spj_transactions as v2', 'v2.id', '=', 'provenance.spj_transaction_id')
            ->where('provenance.canonical_context_status', 'ACTIVE_CANONICAL')
            ->where('v2.canonical_context_status', 'ACTIVE_CANONICAL')
            ->where('v2.fiscal_year_id', $fiscalYearId)
            ->where('v2.fund_source_id', $fundSourceId)
            ->where('v2.source_id', $selection['source_id'])
            ->orderBy('provenance.id')
            ->select([
                'provenance.spj_transaction_id',
                'legacy.id',
                'legacy.no_bukti',
                'legacy.transaction_date',
                'legacy.description',
                'legacy.recipient_name',
                'legacy.ppn',
                'legacy.pph21',
                'legacy.pph22',
                'legacy.pph23',
                'legacy.pph4',
                'legacy.sspd',
                'legacy.tax_total',
            ])
            ->get()
            ->keyBy('spj_transaction_id');

        $canonicalIds = $canonical->keys()->map(fn ($id): int => (int) $id)->sort()->values()->all();
        $legacyCanonicalIds = $legacy->keys()->map(fn ($id): int => (int) $id)->sort()->values()->all();
        if ($canonicalIds !== $legacyCanonicalIds) {
            return null;
        }

        foreach ($canonicalIds as $canonicalId) {
            $canonicalRow = $canonical->get($canonicalId);
            $legacyRow = $legacy->get($canonicalId);
            if (! is_array($canonicalRow) || $legacyRow === null || ! $this->matches($canonicalRow, $legacyRow)) {
                return null;
            }
        }

        return [
            'legacy_transaction_ids' => $legacy
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all(),
            'canonical_transaction_ids' => $canonicalIds,
            'source_id' => $selection['source_id'],
            'compatibility_mode' => (string) $resolved['mode'],
        ];
    }

    /** @param array<string, mixed> $canonical */
    private function matches(array $canonical, object $legacy): bool
    {
        foreach (['no_bukti', 'description', 'recipient_name'] as $field) {
            if ($this->normalize($canonical[$field] ?? null) !== $this->normalize($legacy->{$field} ?? null)) {
                return false;
            }
        }

        if ($this->date($canonical['transaction_date'] ?? null) !== $this->date($legacy->transaction_date ?? null)) {
            return false;
        }

        foreach (['ppn', 'pph21', 'pph22', 'pph23', 'pph4', 'sspd', 'tax_total'] as $field) {
            if (abs(round((float) ($canonical[$field] ?? 0), 2) - round((float) ($legacy->{$field} ?? 0), 2)) > 0.01) {
                return false;
            }
        }

        return true;
    }

    private function date(mixed $value): ?string
    {
        $value = $this->normalize($value);
        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
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
