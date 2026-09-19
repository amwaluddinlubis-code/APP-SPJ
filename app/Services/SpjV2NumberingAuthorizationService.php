<?php

namespace App\Services;

use App\Models\FiscalPeriodClosure;
use App\Models\FiscalYear;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Support\ActiveSpjContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Read-only, fail-closed authorization boundary for future V2 numbering.
 *
 * This service deliberately does not allocate a sequence, write a document,
 * change package state, write an audit row, or normalize legacy fiscal year.
 */
final class SpjV2NumberingAuthorizationService
{
    public function __construct(
        private readonly ActiveSpjContext $context,
        private readonly SpjReadPathSelector $readPaths,
        private readonly SpjV2EffectiveContextCompatibilityService $effectiveContexts,
        private readonly SpjV2CanonicalReadService $canonicalReads,
        private readonly SpjNumberingDocumentRegistry $registry,
        private readonly SpjNumberingPolicyService $numberingPolicy,
    ) {}

    /**
     * @param  list<string>|null  $documentTypes
     * @return array{authorized:bool,path:string,reason:string,package_id:int,effective_fiscal_year_id:?int,effective_fund_source_id:?int,source_id:?int,quarter:?int}
     */
    public function authorize(SpjPackage $package, ?array $documentTypes = null): array
    {
        $blocked = fn (string $reason, string $path = 'blocked', ?int $sourceId = null, ?int $quarter = null): array => [
            'authorized' => false,
            'path' => $path,
            'reason' => $reason,
            'package_id' => (int) $package->id,
            'effective_fiscal_year_id' => $this->context->fiscalYearId(),
            'effective_fund_source_id' => $this->context->fundSourceId(),
            'source_id' => $sourceId,
            'quarter' => $quarter,
        ];

        $transaction = $package->relationLoaded('transaction') ? $package->transaction : $package->transaction()->first();
        if (! $transaction instanceof Transaction) {
            return $blocked('numbering authorization requires a package transaction');
        }

        if ($package->status !== 'READY') {
            return $blocked('package lifecycle status is not eligible for numbering');
        }

        $db = DB::connection('school');
        $fundSourceId = $this->context->fundSourceId();
        if ($fundSourceId === null || (int) $transaction->fund_source_id !== $fundSourceId) {
            return $blocked('package fund source does not match the active effective fund source');
        }

        $selection = $this->readPaths->select($db, $this->context->fiscalYearId(), $fundSourceId);
        if ($selection['path'] === SpjReadPathSelector::LEGACY) {
            if (! $this->context->matchesTransaction($transaction)) {
                return $blocked('legacy selector is active; effective-context numbering is not authorized');
            }

            $period = $this->period($transaction, $this->context->fiscalYearId(), $blocked);
            if ($period !== null && ! $period['authorized']) {
                return $period;
            }

            $documentBlocker = $this->documentRelationBlocker($package, $documentTypes);
            if ($documentBlocker !== null) {
                return $blocked($documentBlocker, 'legacy');
            }
            if ($documentBlocker = $this->eligibilityBlocker($package, $documentTypes)) {
                return $blocked($documentBlocker, 'legacy');
            }

            return $this->allowed((int) $package->id, 'legacy', null, $period['quarter'] ?? null);
        }

        $resolved = $this->effectiveContexts->resolve(
            $db,
            $this->context->fiscalYearId(),
            $fundSourceId,
            (int) $selection['source_id'],
        );
        if ($resolved['status'] !== 'RESOLVED') {
            return $blocked('effective context is not uniquely resolved', 'v2', (int) $selection['source_id']);
        }

        $membership = $this->membership($resolved);
        $canonicalId = (int) ($package->getAttribute('spj_transaction_id') ?? 0);
        if (! in_array((int) $package->id, $membership['package_ids'], true)
            || ! in_array((int) $package->transaction_id, $membership['legacy_transaction_ids'], true)
            || $canonicalId < 1
            || ! in_array($canonicalId, $membership['canonical_transaction_ids'], true)) {
            return $blocked('package is not an effective fiscal-year/fund-source member', 'v2', (int) $selection['source_id']);
        }

        $bridges = $db->table('legacy_transaction_v2_map')
            ->where('legacy_transaction_id', (int) $package->transaction_id)
            ->get();
        $exact = $bridges->filter(fn (object $row): bool => (int) $row->spj_transaction_id === $canonicalId);
        if ($bridges->count() !== 1 || $exact->count() !== 1 || ! in_array((string) $exact->first()->mapping_status, ['EXACT', 'DETERMINISTIC'], true)) {
            return $blocked('package/V2 provenance bridge is missing, duplicate, or ambiguous', 'v2', (int) $selection['source_id']);
        }

        if ((bool) $transaction->requires_reconciliation || strtoupper((string) ($transaction->source_status ?: 'ACTIVE')) === 'SOURCE_MISSING') {
            return $blocked('transaction reconciliation or source status is not clear', 'v2', (int) $selection['source_id']);
        }

        $canonical = $this->canonicalReads->forContext(
            $db,
            $this->context->fiscalYearId(),
            $fundSourceId,
            (int) $selection['source_id'],
        )->first(fn (array $row): bool => (int) $row['id'] === $canonicalId);
        if (! is_array($canonical) || strtoupper((string) ($canonical['source_status'] ?? '')) !== 'ACTIVE') {
            return $blocked('canonical source identity is missing or not active', 'v2', (int) $selection['source_id']);
        }
        if (! $this->sourceFactsMatch($canonical, $transaction)) {
            return $blocked('canonical transaction/source facts are not in parity', 'v2', (int) $selection['source_id']);
        }
        if (! $this->itemFactsMatch($canonical, $transaction)) {
            return $blocked('canonical item facts are not in parity', 'v2', (int) $selection['source_id']);
        }

        $period = $this->periodFromCanonical($canonical, $blocked);
        if ($period !== null && ! $period['authorized']) {
            return $period;
        }

        $documentBlocker = $this->documentRelationBlocker($package, $documentTypes);
        if ($documentBlocker !== null) {
            return $blocked($documentBlocker, 'v2', (int) $selection['source_id'], $period['quarter'] ?? null);
        }
        if ($documentBlocker = $this->eligibilityBlocker($package, $documentTypes)) {
            return $blocked($documentBlocker, 'v2', (int) $selection['source_id'], $period['quarter'] ?? null);
        }

        return $this->allowed((int) $package->id, 'v2_authorized_preflight', (int) $selection['source_id'], $period['quarter'] ?? null);
    }

    /** @return array{authorized:bool,path:string,reason:string,package_id:int,effective_fiscal_year_id:?int,effective_fund_source_id:?int,source_id:?int,quarter:?int} */
    private function allowed(int $packageId, string $path, ?int $sourceId, ?int $quarter): array
    {
        return [
            'authorized' => true,
            'path' => $path,
            'reason' => 'all read-only effective numbering authorization checks passed; issuance remains separately gated',
            'package_id' => $packageId,
            'effective_fiscal_year_id' => $this->context->fiscalYearId(),
            'effective_fund_source_id' => $this->context->fundSourceId(),
            'source_id' => $sourceId,
            'quarter' => $quarter,
        ];
    }

    /** @param array<string,mixed> $resolved */
    private function membership(array $resolved): array
    {
        return [
            'package_ids' => array_map('intval', $resolved['package_ids'] ?? []),
            'legacy_transaction_ids' => array_map('intval', $resolved['legacy_transaction_ids'] ?? []),
            'canonical_transaction_ids' => array_map('intval', $resolved['canonical_transaction_ids'] ?? []),
        ];
    }

    private function documentRelationBlocker(SpjPackage $package, ?array $documentTypes): ?string
    {
        $allowed = $documentTypes === null ? $this->registry->numberedCodes() : array_values(array_filter(array_map(fn (string $type): ?string => $this->registry->canonical($type), $documentTypes)));
        $documents = $package->relationLoaded('documents') ? $package->documents : $package->documents()->get();
        $active = $documents->filter(fn ($document): bool => strtoupper((string) $document->status) !== 'CANCELLED');
        $seen = [];
        foreach ($active as $document) {
            $type = $this->registry->canonical((string) $document->document_type);
            if ($type === null || ! in_array($type, $allowed, true)) {
                return 'package has an orphan or unknown numbering document relation';
            }
            $scope = trim((string) $document->scope_key);
            if ($scope !== 'MAIN' && ! preg_match('/^TRAVEL(?:[:-])[0-9]+$/', $scope)) {
                return 'package has an ambiguous numbering document scope';
            }
            $identity = $type.'|'.$scope;
            if (isset($seen[$identity])) {
                return 'package has duplicate numbering document identity';
            }
            $seen[$identity] = true;
        }

        return null;
    }

    private function eligibilityBlocker(SpjPackage $package, ?array $documentTypes): ?string
    {
        $types = $documentTypes ?? $this->registry->numberedCodes();
        foreach ($types as $type) {
            $canonical = $this->numberingPolicy->canonicalAutomaticDocumentType($type);
            if ($canonical === null || ! $this->numberingPolicy->isAutomaticDocumentEligible($package->transaction, $canonical)) {
                return 'requested document type is not eligible under the canonical numbering policy';
            }
        }

        return null;
    }

    /** @param callable(string,string,?int,?int):array $blocked */
    private function period(Transaction $transaction, int $fiscalYearId, callable $blocked): ?array
    {
        if (! $transaction->transaction_date) {
            return $blocked('effective transaction period cannot be proven');
        }
        try {
            $date = Carbon::parse($transaction->transaction_date);
        } catch (\Throwable) {
            return $blocked('effective transaction period cannot be parsed');
        }
        $year = FiscalYear::query()->find($fiscalYearId);
        if ($year === null || (int) $year->year !== (int) $date->year) {
            return $blocked('effective fiscal year does not match the canonical transaction date');
        }
        $quarter = (int) ceil($date->month / 3);
        if (FiscalPeriodClosure::query()->where('fiscal_year_id', $fiscalYearId)->where('quarter', $quarter)->value('status') === 'CLOSED') {
            return $blocked('effective numbering period is closed', 'blocked', null, $quarter);
        }

        return ['authorized' => true, 'quarter' => $quarter];
    }

    /** @param array<string,mixed> $canonical @param callable(string,string,?int,?int):array $blocked */
    private function periodFromCanonical(array $canonical, callable $blocked): ?array
    {
        $date = $canonical['transaction_date'] ?? null;
        if ($date === null || trim((string) $date) === '') {
            return $blocked('effective canonical period cannot be proven', 'v2');
        }
        $transaction = new Transaction(['transaction_date' => $date]);

        return $this->period($transaction, $this->context->fiscalYearId(), $blocked);
    }

    /** @param array<string,mixed> $canonical */
    private function sourceFactsMatch(array $canonical, Transaction $legacy): bool
    {
        foreach (['no_bukti', 'description', 'activity_code', 'account_code', 'recipient_name'] as $field) {
            if ($this->normalize($canonical[$field] ?? null) !== $this->normalize($legacy->{$field} ?? null)) {
                return false;
            }
        }
        if ($this->date($canonical['transaction_date'] ?? null) !== $this->date($legacy->transaction_date ?? null)) {
            return false;
        }
        foreach (['gross_amount', 'tax_total', 'net_amount', 'ppn', 'pph21', 'pph22', 'pph23', 'pph4', 'sspd'] as $field) {
            if (abs(round((float) ($canonical[$field] ?? 0), 2) - round((float) ($legacy->{$field} ?? 0), 2)) > 0.01) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string,mixed> $canonical */
    private function itemFactsMatch(array $canonical, Transaction $legacy): bool
    {
        $legacyItems = $legacy->relationLoaded('items') ? $legacy->items : $legacy->items()->get();
        $bySource = $legacyItems->keyBy(fn ($item): string => trim((string) $item->source_item_id));
        $canonicalItems = collect($canonical['items'] ?? []);
        if ($canonicalItems->count() !== $legacyItems->count()) {
            return false;
        }
        foreach ($canonicalItems as $item) {
            $key = trim((string) ($item['source_key'] ?? ''));
            $legacyItem = $bySource->get($key);
            $payload = $item['payload'] ?? [];
            if ($key === '' || $legacyItem === null || ! is_array($payload)) {
                return false;
            }
            foreach ([['description', ['uraian', 'description']], ['unit', ['satuan', 'unit']]] as [$field, $keys]) {
                foreach ($keys as $keyName) {
                    if (array_key_exists($keyName, $payload) && trim((string) $payload[$keyName]) !== '' && $this->normalize($payload[$keyName]) !== $this->normalize($legacyItem->{$field})) {
                        return false;
                    }
                }
            }
            $quantity = $payload['volume'] ?? $payload['quantity'] ?? null;
            if ($quantity !== null && abs((float) $quantity - (float) $legacyItem->quantity) > 0.01) {
                return false;
            }
            $amount = (float) ($payload['saldo'] ?? $payload['jumlah'] ?? $payload['nilai'] ?? $payload['nominal'] ?? 0);
            if (abs($amount - (float) $legacyItem->amount) > 0.01) {
                return false;
            }
        }

        return true;
    }

    private function date(mixed $value): ?string
    {
        try {
            return $value === null || trim((string) $value) === '' ? null : Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalize(mixed $value): ?string
    {
        $value = $value === null ? null : trim((string) $value);

        return $value === null || $value === '' ? null : $value;
    }
}
