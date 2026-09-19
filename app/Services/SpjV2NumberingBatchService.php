<?php

namespace App\Services;

use App\Models\SpjPackage;
use App\Support\ActiveSpjContext;
use App\UseCases\Spj\SpjQuarterNumberingUseCase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Atomic effective-context batch (quarter) numbering issuance.
 *
 * Unlike the legacy resumable quarter flow
 * ({@see SpjQuarterNumberingUseCase}), which commits each
 * document separately, this V2 batch is all-or-nothing: preflight validates
 * every candidate before any write, then one `school` transaction reserves,
 * assigns, persists, audits, and completes every item. The first failing
 * item rolls back the whole batch — no partial numbers, audits, lifecycle
 * transitions, or completed reservations leak.
 *
 * Ordering reuses the canonical BKU-aligned order
 * ({@see SpjNumberingOrderService}); per-item queue blockers are intentionally
 * replaced by that explicit ordering. Batch members must share one effective
 * fiscal year, fund source, and quarter.
 *
 * Never touches FINAL, settlement, bulk-final, or fiscal-period mutations.
 */
final class SpjV2NumberingBatchService
{
    public function __construct(
        private readonly SpjV2NumberingIssuanceService $issuance,
        private readonly SpjV2NumberingAuthorizationService $authorization,
        private readonly SpjNumberingOrderService $order,
        private readonly SpjPackageValidationService $validator,
        private readonly SpjNumberingPolicyService $numberingPolicy,
        private readonly OperationalAuditService $audit,
        private readonly ActiveSpjContext $context,
    ) {}

    /**
     * @param  Collection<int,SpjPackage>|list<SpjPackage>  $packages
     * @return array{status:string,authorized:bool,reason:string,error_code:?string,issued:int,items:list<array<string,mixed>>,batch_audit_id:?int}
     */
    public function issueBatch(Collection|array $packages, string $documentType, string $scopeKey = 'MAIN'): array
    {
        $documentType = $this->numberingPolicy->canonicalAutomaticDocumentType($documentType) ?? '';
        $blocked = fn (string $code, string $reason, int $issued = 0): array => [
            'status' => 'BLOCKED',
            'authorized' => false,
            'reason' => $reason,
            'error_code' => $code,
            'issued' => $issued,
            'items' => [],
            'batch_audit_id' => null,
        ];
        if ($documentType === '') {
            return $blocked('DOCUMENT_TYPE_INVALID', 'document type is not registered for automatic numbering');
        }

        $candidates = collect($packages)->values();
        if ($candidates->isEmpty()) {
            return $blocked('BATCH_EMPTY', 'batch has no candidate packages');
        }
        /** @var Collection<int,SpjPackage> $candidates */
        $candidates = $candidates->map(fn (SpjPackage $package): SpjPackage => $package->loadMissing(['transaction.items', 'documents']));
        $identityCount = $candidates->map(fn (SpjPackage $package): int => (int) $package->id)->unique()->count();
        if ($identityCount !== $candidates->count()) {
            return $blocked('BATCH_DUPLICATE_PACKAGE', 'batch contains the same package twice');
        }

        // Read-only preflight for every candidate before any write.
        $preflight = [];
        foreach ($candidates as $package) {
            $check = $this->preflightOne($package, $documentType);
            if ($check !== null) {
                return $blocked('BATCH_PREFLIGHT_FAILED', 'batch refused before any write: package '.$package->id.' — '.$check);
            }
            $preflight[(int) $package->id] = $this->authorization->authorize($package, [$documentType]);
        }

        $contextKey = null;
        foreach ($preflight as $packageId => $authorization) {
            // Idempotent replays of already-issued members are allowed; they
            // must still belong to the same effective context when provable.
            if (($authorization['path'] ?? null) !== 'v2_authorized_preflight' && ! $this->isReplayable($candidates->firstWhere('id', $packageId), $documentType, $scopeKey)) {
                return $blocked('BATCH_PREFLIGHT_FAILED', 'batch refused before any write: package '.$packageId.' — '.$authorization['reason']);
            }
            if (($authorization['path'] ?? null) === 'v2_authorized_preflight') {
                $key = $authorization['effective_fiscal_year_id'].'|'.$authorization['effective_fund_source_id'].'|'.$authorization['quarter'];
                $contextKey = $contextKey ?? $key;
                if ($key !== $contextKey) {
                    return $blocked('BATCH_CONTEXT_SPLIT', 'batch members span more than one effective fiscal year, fund source, or quarter');
                }
            }
        }

        $ordered = $this->order->orderedPackagesForDocumentType($candidates, $documentType);
        $db = DB::connection('school');

        try {
            return $db->transaction(function () use ($db, $ordered, $documentType, $scopeKey): array {
                $items = [];
                foreach ($ordered as $package) {
                    // Queue blockers are replaced by the explicit batch order;
                    // every other fail-closed boundary still applies per item.
                    $items[] = $this->issuance->issueCore($package, $documentType, $scopeKey, null, false);
                }

                $sequences = collect($items)->pluck('sequence_number')->all();
                if (count(array_unique($sequences)) !== count($sequences)) {
                    throw new SpjV2IssuanceBlocked('BATCH_SEQUENCE_COLLISION', 'batch produced duplicate sequences');
                }

                // Batch audit identity derives from stored reservation facts so
                // retries reuse the same row instead of duplicating it.
                $anchor = $db->table('spj_v2_numbering_reservations')
                    ->where('id', (int) ($items[0]['reservation_id'] ?? 0))
                    ->first();
                if ($anchor === null) {
                    throw new SpjV2IssuanceBlocked('BATCH_AUDIT_MISSING', 'batch anchor reservation is missing');
                }
                $effectiveYearId = (int) $anchor->effective_fiscal_year_id;
                $effectiveYear = (int) $db->table('fiscal_years')->where('id', $effectiveYearId)->value('year');
                $batchKey = $documentType.'|'.$scopeKey.'|'.$effectiveYear.'Q'.(int) $anchor->effective_quarter;
                $description = 'Penomoran batch '.$documentType.' efektif '.count($items).' nomor diterbitkan ['.$batchKey.'].';
                $batchAuditId = (int) $db->table('operational_audit_logs')->where([
                    'fiscal_year_id' => $effectiveYearId,
                    'entity_type' => 'SPJ_BATCH',
                    'entity_id' => $batchKey,
                    'action' => 'PENOMORAN_BATCH_V2',
                    'description' => $description,
                ])->orderByDesc('id')->value('id');
                if ($batchAuditId < 1) {
                    $this->audit->record($effectiveYearId, 'SPJ_BATCH', $batchKey, 'PENOMORAN_BATCH_V2', $description);
                    $batchAuditId = (int) $db->table('operational_audit_logs')->where([
                        'fiscal_year_id' => $effectiveYearId,
                        'entity_type' => 'SPJ_BATCH',
                        'entity_id' => $batchKey,
                        'action' => 'PENOMORAN_BATCH_V2',
                        'description' => $description,
                    ])->orderByDesc('id')->value('id');
                }
                if ($batchAuditId < 1) {
                    throw new SpjV2IssuanceBlocked('BATCH_AUDIT_MISSING', 'batch audit row was not persisted');
                }

                return [
                    'status' => 'ISSUED',
                    'authorized' => true,
                    'reason' => 'effective batch issued atomically with no partial residue',
                    'error_code' => null,
                    'issued' => count($items),
                    'items' => $items,
                    'batch_audit_id' => $batchAuditId,
                ];
            });
        } catch (SpjV2IssuanceBlocked $blocked) {
            return $blocked('BATCH_ROLLED_BACK', 'batch failed and was fully rolled back: ['.$blocked->errorCode.'] '.$blocked->getMessage());
        } catch (QueryException) {
            return $blocked('BATCH_COLLISION', 'batch collided on a unique numbering constraint; nothing was persisted');
        } catch (Throwable) {
            return $blocked('BATCH_FAILED', 'batch failed atomically; nothing was persisted');
        }
    }

    /**
     * Read-only preflight for one candidate. Returns null when the package
     * may proceed (fresh issuance or idempotent replay), otherwise a reason.
     */
    private function preflightOne(SpjPackage $package, string $documentType): ?string
    {
        if ($this->isReplayable($package, $documentType, 'MAIN')) {
            return null;
        }
        $authorization = $this->authorization->authorize($package, [$documentType]);
        if (! $authorization['authorized'] || ($authorization['path'] ?? null) !== 'v2_authorized_preflight') {
            return $authorization['reason'] ?? 'not authorized';
        }
        if ($issues = $this->validator->validateForNumbering($package)) {
            return collect($issues)->pluck('message')->implode(' ');
        }

        return null;
    }

    private function isReplayable(SpjPackage $package, string $documentType, string $scopeKey): bool
    {
        $db = DB::connection('school');
        $intent = 'v2-issue-'.(int) $package->id.'-'.$documentType.'-'.$scopeKey;
        $reservation = $db->table('spj_v2_numbering_reservations')->where('intent_key', $intent)->first();
        if ($reservation === null || (string) $reservation->status !== 'COMPLETED') {
            return false;
        }

        return (bool) $db->table('spj_documents')
            ->where('spj_package_id', (int) $package->id)
            ->where('document_type', $documentType)
            ->where('scope_key', $scopeKey)
            ->where('status', '!=', 'CANCELLED')
            ->whereNotNull('document_number')
            ->where('sequence_number', (int) $reservation->sequence_number)
            ->exists();
    }
}
