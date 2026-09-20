<?php

namespace App\Services;

use App\Models\FiscalPeriodClosure;
use App\Support\ActiveSpjContext;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Atomic effective-context authority for fiscal-period close/reopen.
 *
 * The legacy workflow remains available under the legacy selector. V2 never
 * falls back to it when an effective-context precondition is not proven.
 */
final class SpjV2PeriodLifecycleService
{
    public function __construct(
        private readonly ActiveSpjContext $context,
        private readonly SpjReadPathSelector $readPaths,
        private readonly SpjV2EffectiveContextCompatibilityService $effectiveContexts,
        private readonly SpjV2CanonicalReadService $canonicalReads,
    ) {}

    /** @return array{status:string,authorized:bool,reason:string,error_code:?string,period_id:?int,audit_id:?int,idempotent:bool} */
    public function close(int $quarter): array
    {
        try {
            return DB::connection('school')->transaction(
                fn (): array => $this->closeCore($quarter),
            );
        } catch (SpjV2PeriodLifecycleBlocked $blocked) {
            return $this->result('BLOCKED', false, $blocked->getMessage(), $blocked->errorCode);
        } catch (Throwable $failure) {
            return $this->result('BLOCKED', false, 'effective period close failed atomically: '.$failure->getMessage(), 'PERIOD_CLOSE_FAILED');
        }
    }

    /** @return array{status:string,authorized:bool,reason:string,error_code:?string,period_id:?int,audit_id:?int,idempotent:bool} */
    public function reopen(int $periodId, string $reason): array
    {
        try {
            return DB::connection('school')->transaction(
                fn (): array => $this->reopenCore($periodId, $reason),
            );
        } catch (SpjV2PeriodLifecycleBlocked $blocked) {
            return $this->result('BLOCKED', false, $blocked->getMessage(), $blocked->errorCode, $periodId);
        } catch (Throwable $failure) {
            return $this->result('BLOCKED', false, 'effective period reopen failed atomically: '.$failure->getMessage(), 'PERIOD_REOPEN_FAILED', $periodId);
        }
    }

    /** @return array{status:string,authorized:bool,reason:string,error_code:?string,period_id:?int,audit_id:?int,idempotent:bool} */
    private function closeCore(int $quarter): array
    {
        $db = DB::connection('school');
        $fail = fn (string $code, string $reason): never => throw new SpjV2PeriodLifecycleBlocked($code, $reason);
        $this->assertAuthority($db, $quarter, $fail);

        $period = FiscalPeriodClosure::query()
            ->where('fiscal_year_id', $this->context->fiscalYearId())
            ->where('quarter', $quarter)
            ->lockForUpdate()
            ->first();
        if (! $period instanceof FiscalPeriodClosure) {
            $fail('PERIOD_MISSING', 'effective quarter period state is unavailable');
        }
        if ($period->status === 'CLOSED') {
            $auditId = $this->findAudit($db, (int) $period->id, 'PERIOD_CLOSE_V2');
            if ($auditId === null) {
                $fail('PERIOD_AUDIT_MISSING', 'period is CLOSED but its effective close audit is missing');
            }

            return $this->result('CLOSED', true, 'effective period is already CLOSED; retry is idempotent', null, (int) $period->id, $auditId, true);
        }
        if ($period->status !== 'NUMBERED') {
            $fail('PERIOD_NOT_NUMBERED', 'effective period can close only after numbering is complete');
        }

        $context = $this->assertPeriodFacts($db, $quarter, $fail);
        $period->forceFill(['status' => 'CLOSED', 'closed_at' => now(), 'closed_by' => $this->context->actorId()])->save();
        $auditId = $this->recordAudit($db, $period, 'PERIOD_CLOSE_V2', $context, $fail);
        $this->verifyPostCondition($db, $period, 'CLOSED', $auditId, $context, $fail);

        return $this->result('CLOSED', true, 'effective period CLOSED atomically', null, (int) $period->id, $auditId);
    }

    /** @return array{status:string,authorized:bool,reason:string,error_code:?string,period_id:?int,audit_id:?int,idempotent:bool} */
    private function reopenCore(int $periodId, string $reason): array
    {
        $db = DB::connection('school');
        $fail = fn (string $code, string $message): never => throw new SpjV2PeriodLifecycleBlocked($code, $message);
        $this->assertAuthority($db, null, $fail);
        if (trim($reason) === '') {
            $fail('REOPEN_REASON_REQUIRED', 'effective period reopen requires a reason');
        }

        $period = FiscalPeriodClosure::query()
            ->where('id', $periodId)
            ->where('fiscal_year_id', $this->context->fiscalYearId())
            ->lockForUpdate()
            ->first();
        if (! $period instanceof FiscalPeriodClosure) {
            $fail('PERIOD_NOT_FOUND', 'period does not belong to the active effective fiscal year');
        }
        if ($period->status !== 'CLOSED') {
            $fail('PERIOD_NOT_CLOSED', 'only a CLOSED effective period may be reopened');
        }
        $context = $this->contextPayload((int) $period->fiscal_year_id, $this->context->fundSourceId(), (int) $period->quarter);
        $period->forceFill([
            'status' => 'NUMBERED',
            'reopened_at' => now(),
            'reopened_by' => $this->context->actorId(),
            'reopen_reason' => trim($reason),
        ])->save();
        $auditId = $this->recordAudit($db, $period, 'PERIOD_REOPEN_V2', $context, $fail, trim($reason));
        $this->verifyPostCondition($db, $period, 'NUMBERED', $auditId, $context, $fail);

        return $this->result('OPEN', true, 'effective period reopened atomically without lifecycle rollback', null, (int) $period->id, $auditId);
    }

    /** @param callable(string,string):never $fail */
    private function assertAuthority(Connection $db, ?int $quarter, callable $fail): void
    {
        if (! $this->context->isAdministrator()) {
            $fail('UNAUTHORIZED', 'only an authorized administrator may mutate an effective period');
        }
        $fundSourceId = $this->context->fundSourceId();
        if ($fundSourceId === null || $this->context->fiscalYearId() < 1) {
            $fail('EFFECTIVE_CONTEXT_MISSING', 'effective fiscal year and fund source are required');
        }
        if ($quarter !== null && ($quarter < 1 || $quarter > 4)) {
            $fail('INVALID_QUARTER', 'quarter must be between 1 and 4');
        }
        $selection = $this->readPaths->select($db, $this->context->fiscalYearId(), $fundSourceId);
        if ($selection['requested'] !== SpjReadPathSelector::V2 || $selection['path'] !== SpjReadPathSelector::V2 || $selection['source_id'] === null) {
            $fail('V2_SELECTOR_UNAVAILABLE', 'effective period mutation requires an explicit V2 selector');
        }
        $resolved = $this->effectiveContexts->resolve($db, $this->context->fiscalYearId(), $fundSourceId, (int) $selection['source_id']);
        if ($resolved['status'] !== 'RESOLVED') {
            $fail('EFFECTIVE_CONTEXT_UNRESOLVED', 'effective period membership/provenance is not uniquely resolved');
        }
    }

    /** @param callable(string,string):never $fail @return array<string,mixed> */
    private function assertPeriodFacts(Connection $db, int $quarter, callable $fail): array
    {
        $fundSourceId = (int) $this->context->fundSourceId();
        $selection = $this->readPaths->select($db, $this->context->fiscalYearId(), $fundSourceId);
        $canonical = $this->canonicalReads->forContext($db, $this->context->fiscalYearId(), $fundSourceId, (int) $selection['source_id']);
        $canonicalIds = $canonical
            ->filter(function (array $row) use ($quarter): bool {
                $month = (int) date('n', strtotime((string) ($row['transaction_date'] ?? '')));

                return $month >= (($quarter - 1) * 3 + 1) && $month <= ($quarter * 3);
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values();
        $mappings = $db->table('legacy_transaction_v2_map')
            ->whereIn('spj_transaction_id', $canonicalIds->all())
            ->get();
        $canonicalByLegacy = $mappings->pluck('spj_transaction_id', 'legacy_transaction_id');
        $transactions = $db->table('transactions')
            ->whereIn('id', $canonicalByLegacy->keys()->map(fn ($id): int => (int) $id)->all())
            ->where('fund_source_id', $fundSourceId)
            ->orderBy('id')
            ->get();
        $packageIds = [];
        foreach ($transactions as $transaction) {
            if ((bool) $transaction->requires_reconciliation || strtoupper((string) ($transaction->source_status ?: 'ACTIVE')) === 'SOURCE_MISSING') {
                $fail('RECONCILIATION_BLOCKED', 'period contains unresolved reconciliation or SOURCE_MISSING facts');
            }
            $canonicalId = (int) ($canonicalByLegacy[(int) $transaction->id] ?? 0);
            $canonicalRow = $canonical->first(fn (array $row): bool => (int) $row['id'] === $canonicalId);
            if ($canonicalId < 1 || ! is_array($canonicalRow) || strtoupper((string) ($canonicalRow['source_status'] ?? '')) !== 'ACTIVE') {
                $fail('SOURCE_PARITY_INVALID', 'period contains a transaction without active canonical source parity');
            }
            $package = $db->table('spj_packages')->where('transaction_id', $transaction->id)->lockForUpdate()->first();
            if ($package === null && $db->table('transaction_items')->where('transaction_id', $transaction->id)->exists()) {
                $fail('PACKAGE_PREREQUISITE_INVALID', 'period contains a transaction without an SPJ package');
            }
            if ($package !== null) {
                if (strtoupper((string) $package->status) !== 'FINAL') {
                    $fail('PACKAGE_PREREQUISITE_INVALID', 'period contains a package that is not FINAL');
                }
                $packageIds[] = (int) $package->id;
            }
        }
        if ($packageIds !== [] && ! $db->getSchemaBuilder()->hasTable('spj_v2_settlements')) {
            $fail('SETTLEMENT_SCHEMA_UNAVAILABLE', 'effective period close requires persistent settlement state');
        }
        if ($packageIds !== []) {
            $settled = $db->table('spj_v2_settlements')->whereIn('spj_package_id', $packageIds)->where('status', 'SETTLED')->pluck('spj_package_id')->all();
            if (count(array_unique(array_map('intval', $settled))) !== count(array_unique($packageIds))) {
                $fail('SETTLEMENT_PREREQUISITE_INVALID', 'every FINAL package in the effective period must be SETTLED');
            }
        }

        return $this->contextPayload($this->context->fiscalYearId(), $fundSourceId, $quarter, (int) $selection['source_id'], count($packageIds));
    }

    /** @return array<string,mixed> */
    private function contextPayload(int $yearId, ?int $fundSourceId, int $quarter, ?int $sourceId = null, int $packageCount = 0): array
    {
        return ['effective_fiscal_year_id' => $yearId, 'effective_fund_source_id' => $fundSourceId, 'effective_quarter' => $quarter, 'source_id' => $sourceId, 'package_count' => $packageCount];
    }

    /** @param array<string,mixed> $context @param callable(string,string):never $fail */
    private function recordAudit(Connection $db, FiscalPeriodClosure $period, string $action, array $context, callable $fail, ?string $reason = null): int
    {
        if (! $db->getSchemaBuilder()->hasTable('operational_audit_logs')) {
            $fail('AUDIT_UNAVAILABLE', 'effective period audit storage is unavailable');
        }
        $description = $action.' effective FY '.$context['effective_fiscal_year_id'].' TW'.$context['effective_quarter'].' fund '.$context['effective_fund_source_id'].' source '.$context['source_id'].($reason === null ? '' : ' reason: '.$reason);
        $db->table('operational_audit_logs')->insert(['fiscal_year_id' => $context['effective_fiscal_year_id'], 'entity_type' => 'FISCAL_PERIOD', 'entity_id' => (string) $period->id, 'action' => $action, 'description' => $description, 'user_id' => $this->context->actorId(), 'created_at' => now()]);
        $auditId = (int) $db->table('operational_audit_logs')->where('entity_type', 'FISCAL_PERIOD')->where('entity_id', (string) $period->id)->where('action', $action)->orderByDesc('id')->value('id');
        if ($auditId < 1) {
            $fail('AUDIT_WRITE_FAILED', 'effective period audit could not be verified');
        }

        return $auditId;
    }

    /** @param array<string,mixed> $context @param callable(string,string):never $fail */
    private function verifyPostCondition(Connection $db, FiscalPeriodClosure $period, string $status, int $auditId, array $context, callable $fail): void
    {
        $fresh = $period->fresh();
        if ($fresh === null || strtoupper((string) $fresh->status) !== $status || (int) $fresh->fiscal_year_id !== (int) $context['effective_fiscal_year_id']) {
            $fail('PERIOD_POSTCONDITION_FAILED', 'effective period state post-condition failed');
        }
        if ((int) $db->table('operational_audit_logs')->where('id', $auditId)->where('action', $status === 'CLOSED' ? 'PERIOD_CLOSE_V2' : 'PERIOD_REOPEN_V2')->count() !== 1) {
            $fail('PERIOD_POSTCONDITION_FAILED', 'effective period audit post-condition failed');
        }
    }

    private function findAudit(Connection $db, int $periodId, string $action): ?int
    {
        if (! $db->getSchemaBuilder()->hasTable('operational_audit_logs')) {
            return null;
        }
        $id = $db->table('operational_audit_logs')->where('entity_type', 'FISCAL_PERIOD')->where('entity_id', (string) $periodId)->where('action', $action)->orderByDesc('id')->value('id');

        return $id === null ? null : (int) $id;
    }

    /** @return array{status:string,authorized:bool,reason:string,error_code:?string,period_id:?int,audit_id:?int,idempotent:bool} */
    private function result(string $status, bool $authorized, string $reason, ?string $errorCode, ?int $periodId = null, ?int $auditId = null, bool $idempotent = false): array
    {
        return ['status' => $status, 'authorized' => $authorized, 'reason' => $reason, 'error_code' => $errorCode, 'period_id' => $periodId, 'audit_id' => $auditId, 'idempotent' => $idempotent];
    }
}

final class SpjV2PeriodLifecycleBlocked extends \RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }
}
