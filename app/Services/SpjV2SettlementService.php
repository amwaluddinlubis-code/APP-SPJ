<?php

namespace App\Services;

use App\Models\SpjPackage;
use App\Models\SpjV2Settlement;
use App\Models\Transaction;
use App\Support\ActiveSpjContext;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Atomic effective-context settlement authority for FINAL packages.
 *
 * Legacy staged payment and receipt writes remain available for editable
 * packages. V2 settlement never falls back to that path.
 */
final class SpjV2SettlementService
{
    public function __construct(
        private readonly ActiveSpjContext $context,
        private readonly SpjReadPathSelector $readPaths,
        private readonly SpjV2EffectiveContextCompatibilityService $effectiveContexts,
        private readonly SpjV2CanonicalReadService $canonicalReads,
        private readonly SpjV2EffectiveNumberingPeriodResolver $periodResolver,
    ) {}

    /** @return array{status:string,authorized:bool,reason:string,error_code:?string,package_id:int,settlement_id:?int,idempotent:bool} */
    public function settle(SpjPackage $package): array
    {
        try {
            return DB::connection('school')->transaction(
                fn (): array => $this->settleCore((int) $package->id),
            );
        } catch (SpjV2SettlementBlocked $blocked) {
            return $this->result('BLOCKED', false, $blocked->getMessage(), $blocked->errorCode, (int) $package->id);
        } catch (Throwable $failure) {
            return $this->result('BLOCKED', false, 'effective settlement failed atomically; package remains FINAL: '.$failure->getMessage(), 'SETTLEMENT_FAILED', (int) $package->id);
        }
    }

    /** @return array{status:string,authorized:bool,reason:string,error_code:?string,package_id:int,settlement_id:?int,idempotent:bool} */
    private function settleCore(int $packageId): array
    {
        $db = DB::connection('school');
        $fail = fn (string $code, string $reason): never => throw new SpjV2SettlementBlocked($code, $reason);
        if (! $db->getSchemaBuilder()->hasTable('spj_v2_settlements')) {
            $fail('SETTLEMENT_SCHEMA_UNAVAILABLE', 'persistent V2 settlement schema is unavailable');
        }

        $package = SpjPackage::query()
            ->with(['documents', 'transaction.payments', 'transaction.goodsReceipts.items', 'transaction.items'])
            ->lockForUpdate()
            ->find($packageId);
        if (! $package instanceof SpjPackage) {
            $fail('PACKAGE_MISSING', 'package does not exist in the active tenant database');
        }
        if ($package->status !== 'FINAL') {
            $fail('PACKAGE_NOT_FINAL', 'only a FINAL package may be settled');
        }

        $existing = SpjV2Settlement::query()->where('spj_package_id', $packageId)->lockForUpdate()->first();
        if ($existing !== null) {
            if ($existing->status !== 'SETTLED' || (int) $existing->transaction_id !== (int) $package->transaction_id) {
                $fail('SETTLEMENT_STATE_INVALID', 'existing settlement state is inconsistent');
            }

            return $this->result('SETTLED', true, 'effective settlement already committed; retry is idempotent', null, $packageId, (int) $existing->id, true);
        }

        $fundSourceId = $this->context->fundSourceId();
        if ($fundSourceId === null) {
            $fail('FUND_CONTEXT_MISSING', 'active fund-source context is required');
        }
        $selection = $this->readPaths->select($db, $this->context->fiscalYearId(), $fundSourceId);
        if ($selection['requested'] !== SpjReadPathSelector::V2 || $selection['path'] !== SpjReadPathSelector::V2 || $selection['source_id'] === null) {
            $fail('V2_SELECTOR_UNAVAILABLE', 'effective settlement requires an explicit V2 selector');
        }

        $transaction = $package->transaction;
        if (! $transaction instanceof Transaction) {
            $fail('PACKAGE_TRANSACTION_MISSING', 'settlement requires one package transaction');
        }
        if ((int) $transaction->fund_source_id !== $fundSourceId) {
            $fail('FUND_SOURCE_MISMATCH', 'legacy transaction fund source does not match active context');
        }
        if ((bool) $transaction->requires_reconciliation || strtoupper((string) ($transaction->source_status ?: 'ACTIVE')) === 'SOURCE_MISSING') {
            $fail('RECONCILIATION_BLOCKED', 'reconciliation or source status is not clear');
        }
        if ($package->documents->where('status', '!=', 'CANCELLED')->isEmpty()
            || $package->documents->where('status', '!=', 'CANCELLED')->contains(fn ($document): bool => $document->status !== 'FINAL' || blank($document->document_number))) {
            $fail('FINAL_POSTCONDITION_INVALID', 'all active package documents must remain FINAL and numbered');
        }

        $resolved = $this->effectiveContexts->resolve($db, $this->context->fiscalYearId(), $fundSourceId, (int) $selection['source_id']);
        if ($resolved['status'] !== 'RESOLVED') {
            $fail('EFFECTIVE_CONTEXT_UNRESOLVED', 'effective membership/provenance context is not uniquely resolved');
        }
        $canonicalId = (int) ($package->getAttribute('spj_transaction_id') ?? 0);
        if ($canonicalId < 1 || ! in_array($packageId, array_map('intval', $resolved['package_ids'] ?? []), true)) {
            $fail('EFFECTIVE_MEMBERSHIP_INVALID', 'package is not a member of the active effective context');
        }
        $bridges = $db->table('legacy_transaction_v2_map')->where('legacy_transaction_id', (int) $package->transaction_id)->get();
        $exact = $bridges->filter(fn (object $row): bool => (int) $row->spj_transaction_id === $canonicalId);
        if ($bridges->count() !== 1 || $exact->count() !== 1 || ! in_array((string) $exact->first()->mapping_status, ['EXACT', 'DETERMINISTIC'], true)) {
            $fail('PROVENANCE_BRIDGE_INVALID', 'package/V2 provenance bridge is missing, duplicate, or ambiguous');
        }

        $canonical = $this->canonicalReads->forContext($db, $this->context->fiscalYearId(), $fundSourceId, (int) $selection['source_id'])
            ->first(fn (array $row): bool => (int) $row['id'] === $canonicalId);
        if (! is_array($canonical) || strtoupper((string) ($canonical['canonical_context_status'] ?? '')) !== 'ACTIVE_CANONICAL' || strtoupper((string) ($canonical['source_status'] ?? '')) !== 'ACTIVE') {
            $fail('CANONICAL_SOURCE_INVALID', 'canonical source identity is missing or inactive');
        }
        $period = $this->periodResolver->resolve($package, $canonical);
        if (! $period['authorized']) {
            $fail((string) ($period['error_code'] ?? 'EFFECTIVE_PERIOD_INVALID'), $period['reason']);
        }
        $periodLock = $db->table('fiscal_period_closures')
            ->where('fiscal_year_id', (int) $period['effective_fiscal_year_id'])
            ->where('quarter', (int) $period['effective_quarter'])
            ->lockForUpdate()
            ->first();
        if ($periodLock === null || strtoupper((string) $periodLock->status) === 'CLOSED') {
            $fail('EFFECTIVE_PERIOD_CLOSED', 'effective quarter closed before settlement');
        }

        $gross = round((float) $transaction->gross_amount, 2);
        $paid = round((float) $transaction->payments->where('status', '!=', 'CANCELLED')->sum('gross_amount'), 2);
        $tax = round((float) $transaction->payments->where('status', '!=', 'CANCELLED')->sum('tax_amount'), 2);
        if ($gross <= 0 || abs($paid - $gross) > 0.009 || $tax > $paid) {
            $fail('FINANCIAL_TOTAL_MISMATCH', 'active payments must exactly settle transaction gross amount and have valid tax totals');
        }
        if (isset($canonical['gross_amount']) && abs((float) $canonical['gross_amount'] - $gross) > 0.009) {
            $fail('CANONICAL_FINANCIAL_DRIFT', 'canonical and legacy gross amounts are not in parity');
        }

        $settledAt = now();
        $settlement = SpjV2Settlement::query()->create([
            'spj_package_id' => $packageId,
            'transaction_id' => $transaction->id,
            'effective_fiscal_year_id' => $period['effective_fiscal_year_id'],
            'effective_fund_source_id' => $period['effective_fund_source_id'],
            'source_id' => $period['source_id'],
            'effective_quarter' => $period['effective_quarter'],
            'gross_amount' => $gross,
            'paid_amount' => $paid,
            'tax_amount' => $tax,
            'net_amount' => round($paid - $tax, 2),
            'status' => 'SETTLED',
            'snapshot' => ['effective_context' => $period, 'package_status' => $package->status, 'canonical_transaction_id' => $canonicalId, 'payment_ids' => $transaction->payments->where('status', '!=', 'CANCELLED')->pluck('id')->values()->all(), 'settled_at' => $settledAt->toIso8601String()],
            'settled_at' => $settledAt,
            'settled_by' => $this->context->actorId(),
        ]);
        if (! $db->getSchemaBuilder()->hasTable('operational_audit_logs')) {
            $fail('AUDIT_UNAVAILABLE', 'settlement audit storage is unavailable');
        }
        $db->table('operational_audit_logs')->insert([
            'fiscal_year_id' => $period['effective_fiscal_year_id'],
            'entity_type' => 'SPJ_PACKAGE',
            'entity_id' => (string) $packageId,
            'action' => 'SETTLEMENT_V2',
            'description' => 'Paket SPJ settled pada konteks efektif '.$period['context_key'].' dengan nilai '.$gross.'.',
            'user_id' => auth()->id(),
            'created_at' => $settledAt,
        ]);
        $auditCount = $db->table('operational_audit_logs')->where('entity_type', 'SPJ_PACKAGE')->where('entity_id', (string) $packageId)->where('action', 'SETTLEMENT_V2')->count();
        if ($auditCount !== 1 || $settlement->fresh()?->status !== 'SETTLED') {
            $fail('SETTLEMENT_POSTCONDITION_INVALID', 'settlement or its audit post-condition could not be verified');
        }

        return $this->result('SETTLED', true, 'effective FINAL settlement committed atomically', null, $packageId, (int) $settlement->id);
    }

    /** @return array{status:string,authorized:bool,reason:string,error_code:?string,package_id:int,settlement_id:?int,idempotent:bool} */
    private function result(string $status, bool $authorized, string $reason, ?string $errorCode, int $packageId, ?int $settlementId = null, bool $idempotent = false): array
    {
        return [
            'status' => $status,
            'authorized' => $authorized,
            'reason' => $reason,
            'error_code' => $errorCode,
            'package_id' => $packageId,
            'settlement_id' => $settlementId,
            'idempotent' => $idempotent,
        ];
    }
}

final class SpjV2SettlementBlocked extends \RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }
}
