<?php

namespace App\Services;

use App\Models\SpjPackage;
use App\Support\ActiveSpjContext;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Atomic effective-context bulk FINAL authority.
 *
 * Selection and ordering are deterministic. Every candidate is checked before
 * the transaction invokes the single-package V2 FINAL authority. A blocked
 * member aborts the outer transaction, so no package or batch audit remains.
 */
final class SpjV2BulkFinalizationService
{
    public function __construct(
        private readonly ActiveSpjContext $context,
        private readonly SpjReadPathSelector $readPaths,
        private readonly SpjV2FinalizationService $finalization,
        private readonly SpjNumberingOrderService $ordering,
    ) {}

    /** @return array{status:string,authorized:bool,reason:string,error_code:?string,quarter:int,count:int,package_ids:list<int>,audit_id:?int,idempotent:bool} */
    public function finalize(int $quarter): array
    {
        try {
            return DB::connection('school')->transaction(
                fn (): array => $this->finalizeCore($quarter),
            );
        } catch (SpjV2BulkFinalizationBlocked $blocked) {
            return $this->result('BLOCKED', false, $blocked->getMessage(), $blocked->errorCode, $quarter);
        } catch (Throwable $failure) {
            return $this->result('BLOCKED', false, 'effective bulk FINAL failed atomically; no package was finalized: '.$failure->getMessage(), 'BULK_FINALIZATION_FAILED', $quarter);
        }
    }

    /** @return array{status:string,authorized:bool,reason:string,error_code:?string,quarter:int,count:int,package_ids:list<int>,audit_id:?int,idempotent:bool} */
    private function finalizeCore(int $quarter): array
    {
        $db = DB::connection('school');
        $fail = fn (string $code, string $reason): never => throw new SpjV2BulkFinalizationBlocked($code, $reason);
        if ($quarter < 1 || $quarter > 4) {
            $fail('QUARTER_INVALID', 'quarter must be between 1 and 4');
        }
        $fundSourceId = $this->context->fundSourceId();
        if ($fundSourceId === null) {
            $fail('FUND_CONTEXT_MISSING', 'active fund-source context is required');
        }
        $selection = $this->readPaths->select($db, $this->context->fiscalYearId(), $fundSourceId);
        if ($selection['requested'] !== SpjReadPathSelector::V2 || $selection['path'] !== SpjReadPathSelector::V2 || $selection['source_id'] === null) {
            $fail('V2_SELECTOR_UNAVAILABLE', 'effective bulk FINAL requires an explicit V2 selector');
        }

        $batchKey = $this->batchKey($quarter, $this->context->fiscalYearId(), $fundSourceId, (int) $selection['source_id']);
        $existingAudit = $db->table('operational_audit_logs')
            ->where('entity_type', 'SPJ_QUARTER')
            ->where('entity_id', $batchKey)
            ->where('action', 'FINALISASI_BATCH_V2')
            ->first();
        if ($existingAudit !== null) {
            return $this->result('FINALIZED', true, 'effective bulk FINAL already committed; retry is idempotent', null, $quarter, [], (int) $existingAudit->id, true);
        }

        $candidates = SpjPackage::query()
            ->with(['transaction'])
            ->where('status', 'NUMBERED')
            ->whereHas('transaction', function ($query) use ($fundSourceId, $quarter): void {
                $query->where('fund_source_id', $fundSourceId)
                    ->whereMonth('transaction_date', '>=', (($quarter - 1) * 3) + 1)
                    ->whereMonth('transaction_date', '<=', $quarter * 3);
            })
            ->get();
        $ordered = $this->ordering->orderedPackagesForDocumentType($candidates, 'SPJ')->values();
        foreach ($ordered as $package) {
            $this->preflight($db, $package, $quarter, $fundSourceId, (int) $selection['source_id'], $fail);
        }
        if ($ordered->isEmpty()) {
            return $this->result('NOOP', true, 'no NUMBERED package is eligible for this effective quarter', null, $quarter);
        }

        $packageIds = $ordered->map(fn (SpjPackage $package): int => (int) $package->id)->all();
        $locked = SpjPackage::query()->whereIn('id', $packageIds)->lockForUpdate()->get()->keyBy('id');
        if ($locked->count() !== count($packageIds)) {
            $fail('CANDIDATE_DISAPPEARED', 'bulk candidate set changed before mutation');
        }
        foreach ($packageIds as $packageId) {
            $result = $this->finalization->finalize($locked->get($packageId));
            if ($result['status'] !== 'FINALIZED') {
                $fail((string) ($result['error_code'] ?? 'FINALIZATION_BLOCKED'), 'package '.$packageId.' blocked: '.$result['reason']);
            }
        }

        if (! $db->getSchemaBuilder()->hasTable('operational_audit_logs')) {
            $fail('AUDIT_UNAVAILABLE', 'bulk FINAL audit storage is unavailable');
        }
        $db->table('operational_audit_logs')->insert([
            'fiscal_year_id' => $this->context->fiscalYearId(),
            'entity_type' => 'SPJ_QUARTER',
            'entity_id' => $batchKey,
            'action' => 'FINALISASI_BATCH_V2',
            'description' => 'Bulk FINAL V2 untuk '.count($packageIds).' paket pada konteks '.$batchKey.'.',
            'user_id' => auth()->id(),
            'created_at' => now(),
        ]);
        $audit = $db->table('operational_audit_logs')->where('entity_type', 'SPJ_QUARTER')->where('entity_id', $batchKey)->where('action', 'FINALISASI_BATCH_V2')->first();
        if ($audit === null || $db->table('spj_packages')->whereIn('id', $packageIds)->where('status', '!=', 'FINAL')->exists()) {
            $fail('BULK_FINAL_POSTCONDITION_INVALID', 'not all package and audit post-conditions are verified');
        }

        return $this->result('FINALIZED', true, 'effective bulk FINAL committed atomically', null, $quarter, $packageIds, (int) $audit->id);
    }

    /** @param callable(string,string):never $fail */
    private function preflight(Connection $db, SpjPackage $package, int $quarter, int $fundSourceId, int $sourceId, callable $fail): void
    {
        if ($package->status !== 'NUMBERED' || ! $package->transaction) {
            $fail('PACKAGE_NOT_NUMBERED', 'every bulk candidate must be NUMBERED and have one transaction');
        }
        if ((int) $package->transaction->fund_source_id !== $fundSourceId || (int) $package->transaction->fiscal_year_id < 1) {
            $fail('FUND_SOURCE_MISMATCH', 'bulk candidate does not match active fund context');
        }
        $month = (int) $package->transaction->transaction_date?->month;
        if ($month < (($quarter - 1) * 3) + 1 || $month > $quarter * 3) {
            $fail('QUARTER_CONTEXT_MISMATCH', 'bulk candidate is outside the requested quarter');
        }
        if ((bool) $package->transaction->requires_reconciliation || strtoupper((string) ($package->transaction->source_status ?: 'ACTIVE')) === 'SOURCE_MISSING') {
            $fail('RECONCILIATION_BLOCKED', 'bulk candidate has unresolved reconciliation or missing source');
        }
        if ($db->table('spj_v2_settlements')->where('spj_package_id', (int) $package->id)->exists()) {
            $fail('SETTLEMENT_STATE_INVALID', 'NUMBERED candidate already has settlement state');
        }
        if ($db->table('spj_packages')->where('id', (int) $package->id)->count() !== 1) {
            $fail('DUPLICATE_CANDIDATE', 'bulk candidate identity is not unique');
        }
        // The single-package V2 authority repeats exact membership, provenance,
        // parity, document, period, and audit gates immediately before write.
        if ($sourceId < 1) {
            $fail('EFFECTIVE_CONTEXT_UNRESOLVED', 'effective source identity is unavailable');
        }
    }

    private function batchKey(int $quarter, int $yearId, int $fundSourceId, int $sourceId): string
    {
        return 'V2|'.$yearId.'|'.$fundSourceId.'|'.$sourceId.'|Q'.$quarter;
    }

    /** @return array{status:string,authorized:bool,reason:string,error_code:?string,quarter:int,count:int,package_ids:list<int>,audit_id:?int,idempotent:bool} */
    private function result(string $status, bool $authorized, string $reason, ?string $errorCode, int $quarter, array $packageIds = [], ?int $auditId = null, bool $idempotent = false): array
    {
        return ['status' => $status, 'authorized' => $authorized, 'reason' => $reason, 'error_code' => $errorCode, 'quarter' => $quarter, 'count' => count($packageIds), 'package_ids' => $packageIds, 'audit_id' => $auditId, 'idempotent' => $idempotent];
    }
}

final class SpjV2BulkFinalizationBlocked extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }
}
