<?php

namespace App\Services;

use App\Models\DocumentNumberFormat;
use App\Models\SpjDocument;
use App\Models\SpjPackage;
use App\Support\ActiveSpjContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Atomic effective-context single numbering issuance.
 *
 * Chain: authorize (V2 preflight) → gate → validation → order → reserve →
 * render (effective format) → persist document → package lifecycle →
 * effective-year audit → complete reservation → post-condition verify,
 * all inside one `school` transaction. Any failure throws
 * {@see SpjV2IssuanceBlocked} so nothing is left half-written.
 *
 * Idempotency: the stable intent (`v2-issue-{package}-{type}-{scope}` by
 * default) replays a prior success when the COMPLETED reservation, the
 * NUMBERED document, and the single audit row still match. Mismatches fail
 * closed instead of being silently repaired.
 *
 * This service never touches FINAL, settlement, bulk-final, or fiscal-period
 * mutations, and never rewrites the stale legacy fiscal year.
 */
final class SpjV2NumberingIssuanceService
{
    public function __construct(
        private readonly SpjV2NumberingAuthorizationService $authorization,
        private readonly SpjV2NumberingSequenceService $sequences,
        private readonly SpjNumberingPolicyService $numberingPolicy,
        private readonly SpjNumberingOrderService $order,
        private readonly SpjPackageValidationService $validator,
        private readonly SpjDocumentNumberService $numbers,
        private readonly OperationalAuditService $audit,
        private readonly ActiveSpjContext $context,
    ) {}

    /**
     * @return array{status:string,authorized:bool,reason:string,error_code:?string,package_id:int,document_type:?string,scope_key:string,sequence_number:?int,document_number:?string,document_id:?int,reservation_id:?int,audit_id:?int,idempotent:bool}
     */
    public function issue(SpjPackage $package, string $documentType, string $scopeKey = 'MAIN', ?string $intentKey = null, bool $enforceOrder = true): array
    {
        $db = DB::connection('school');

        try {
            return $db->transaction(fn (): array => $this->issueCore($package, $documentType, $scopeKey, $intentKey, $enforceOrder));
        } catch (SpjV2IssuanceBlocked $blocked) {
            return $this->result('BLOCKED', false, $blocked->getMessage(), $blocked->errorCode, (int) $package->id, null, $scopeKey, null, null, null, null, null, false);
        } catch (QueryException) {
            return $this->result('BLOCKED', false, 'effective issuance collided on a unique numbering constraint; nothing was persisted', 'ISSUANCE_COLLISION', (int) $package->id, null, $scopeKey, null, null, null, null, null, false);
        } catch (Throwable) {
            return $this->result('BLOCKED', false, 'effective issuance failed atomically; nothing was persisted', 'ISSUANCE_FAILED', (int) $package->id, null, $scopeKey, null, null, null, null, null, false);
        }
    }

    /**
     * Throwing issuance core. Safe to call inside an outer `school`
     * transaction (batch mode joins via savepoint); any failure rolls back
     * everything the issuance wrote.
     *
     * @return array{status:string,authorized:bool,reason:string,error_code:?string,package_id:int,document_type:?string,scope_key:string,sequence_number:?int,document_number:?string,document_id:?int,reservation_id:?int,audit_id:?int,idempotent:bool}
     */
    public function issueCore(SpjPackage $package, string $documentType, string $scopeKey = 'MAIN', ?string $intentKey = null, bool $enforceOrder = true): array
    {
        $documentType = $this->numberingPolicy->canonicalAutomaticDocumentType($documentType) ?? '';
        $scopeKey = trim($scopeKey) === '' ? 'MAIN' : trim($scopeKey);
        $intentKey = $intentKey === null || trim($intentKey) === ''
            ? 'v2-issue-'.(int) $package->id.'-'.($documentType !== '' ? $documentType : 'UNKNOWN').'-'.$scopeKey
            : trim($intentKey);
        $fail = fn (string $code, string $reason): never => throw new SpjV2IssuanceBlocked($code, $reason);

        if ($documentType === '') {
            $fail('DOCUMENT_TYPE_INVALID', 'document type is not registered for automatic numbering');
        }
        $definition = $this->numberingPolicy->numberingDefinition($documentType);
        if ($definition === null || ! $this->validScope($definition['scope_rule'], $scopeKey)) {
            $fail('SCOPE_INVALID', 'document scope is invalid for the registry definition');
        }

        $db = DB::connection('school');
        $package->loadMissing(['transaction.items', 'documents']);
        $transaction = $package->transaction;
        if ($transaction === null) {
            $fail('PACKAGE_TRANSACTION_MISSING', 'issuance requires one package transaction');
        }
        $legacyFiscalYearId = (int) $transaction->fiscal_year_id;

        // Resume path: a COMPLETED reservation is the source of truth for an
        // intent that was authorized before. Full READY-only authorization
        // cannot re-run once the package is NUMBERED, so completion is
        // resumed from stored reservation facts with fail-closed drift
        // checks instead of silent repair. Mismatches never repair.
        $existing = $db->table('spj_v2_numbering_reservations')->where('intent_key', $intentKey)->lockForUpdate()->first();
        if ($existing !== null && (string) $existing->status === 'COMPLETED'
            && (int) $existing->spj_package_id === (int) $package->id
            && (string) $existing->document_type === $documentType
            && (string) $existing->scope_key === $scopeKey) {
            return $this->resumeCompleted($package, $existing, $definition, $legacyFiscalYearId, $fail);
        }

        $authorization = $this->authorization->authorize($package, [$documentType]);
        if (! $authorization['authorized'] || ($authorization['path'] ?? null) !== 'v2_authorized_preflight') {
            $fail('AUTHORIZATION_BLOCKED', 'effective issuance is not authorized: '.($authorization['reason'] ?? 'unknown'));
        }
        // The legacy gate is intentionally not reused here: its context match
        // is bound to the stale legacy fiscal year, while every equivalent
        // check (fund source, READY lifecycle, policy eligibility, registry
        // relation, period state) is already proven on effective facts by the
        // V2 authorization boundary above.
        if ($issues = $this->validator->validateForNumbering($package)) {
            $fail('VALIDATION_BLOCKED', 'Penomoran ditolak. '.collect($issues)->pluck('message')->implode(' '));
        }
        if ($enforceOrder && ($blocker = $this->order->singleNumberingBlocker($package, [$documentType]))) {
            $fail('ORDER_BLOCKED', $blocker);
        }

        $reserved = $this->sequences->reserve($package, $documentType, $scopeKey, $intentKey);
        if ($reserved['status'] === 'BLOCKED') {
            $fail($reserved['error_code'] ?? 'RESERVATION_FAILED', 'sequence reservation refused: '.($reserved['reason'] ?? 'unknown'));
        }
        if ($reserved['status'] === 'COMPLETED') {
            // A reservation completed by an earlier attempt that crashed
            // before writing anything: resume from stored facts.
            $row = $db->table('spj_v2_numbering_reservations')->where('id', (int) $reserved['reservation_id'])->lockForUpdate()->first();

            return $this->resumeCompleted($package, $row, $definition, $legacyFiscalYearId, $fail);
        }
        $sequenceNumber = (int) $reserved['sequence_number'];
        $reservationId = (int) $reserved['reservation_id'];

        $effectiveFiscalYearId = (int) $authorization['effective_fiscal_year_id'];
        $effectiveYear = (int) ($authorization['effective_fiscal_year'] ?? 0);
        $quarter = (int) ($authorization['quarter'] ?? 0);

        $eventDate = $this->eventDate($package, $definition, $documentType, $scopeKey, $authorization['date_basis'] ?? null, $fail);
        $documentNumber = $this->renderNumber($effectiveFiscalYearId, $documentType, $sequenceNumber, $eventDate, $fail);

        $appliesToPackage = ($definition['number_target']['relation'] ?? null) === 'package' && $scopeKey === 'MAIN';

        // Complete while the package is still READY: the reservation boundary
        // re-authorizes, and post-issuance the lifecycle is NUMBERED. Crash
        // recovery after this point resumes from the COMPLETED reservation.
        $completed = $this->sequences->complete($package, $documentType, $scopeKey, $intentKey, $sequenceNumber);
        if ($completed['status'] !== 'COMPLETED') {
            $fail($completed['error_code'] ?? 'RESERVATION_COMPLETION_FAILED', 'reservation completion refused: '.($completed['reason'] ?? 'unknown'));
        }

        $document = $this->persistDocument($package, $documentType, $scopeKey, $documentNumber, $sequenceNumber, $eventDate, $fail);
        $this->applyTarget($package, $definition, $documentType, $scopeKey, $documentNumber, $document, $appliesToPackage, $fail);

        $auditDescription = $this->auditDescription($documentType, $documentNumber, $effectiveYear, $quarter, $intentKey);
        $auditId = $this->recordAuditOnce($effectiveFiscalYearId, $appliesToPackage ? 'SPJ_PACKAGE' : 'SPJ_DOCUMENT', $appliesToPackage ? $package->id : $document->id, $auditDescription, $fail);

        $postCondition = $this->verifySinglePostCondition(
            (int) $package->id,
            $documentType,
            $scopeKey,
            $sequenceNumber,
            $documentNumber,
            (int) $document->id,
            $reservationId,
            $auditId,
            $auditDescription,
            $effectiveFiscalYearId,
            $legacyFiscalYearId,
            $appliesToPackage,
        );
        if (! $postCondition['passed']) {
            $fail('POSTCONDITION_FAILED', 'post-condition failed: '.implode('; ', $postCondition['failures']));
        }

        return $this->result('ISSUED', true, 'effective number issued atomically; issuance remains V2-gated', null, (int) $package->id, $documentType, $scopeKey, $sequenceNumber, $documentNumber, (int) $document->id, $reservationId, $auditId, false);
    }

    /**
     * Explicit post-condition verifier for one effective issuance.
     *
     * @return array{passed:bool,failures:list<string>}
     */
    public function verifySinglePostCondition(
        int $packageId,
        string $documentType,
        string $scopeKey,
        int $sequenceNumber,
        string $documentNumber,
        int $documentId,
        int $reservationId,
        int $auditId,
        string $auditDescription,
        int $effectiveFiscalYearId,
        int $legacyFiscalYearId,
        bool $appliesToPackage,
    ): array {
        $failures = [];
        $db = DB::connection('school');

        $document = $db->table('spj_documents')->where('id', $documentId)->first();
        if ($document === null || (int) $document->spj_package_id !== $packageId
            || (string) $document->document_type !== $documentType
            || (string) $document->scope_key !== $scopeKey
            || strtoupper((string) $document->status) !== 'NUMBERED'
            || (string) $document->document_number !== $documentNumber
            || (int) $document->sequence_number !== $sequenceNumber) {
            $failures[] = 'issued document row does not carry the reserved sequence and rendered number';
        }
        $identityCount = $db->table('spj_documents')
            ->where('spj_package_id', $packageId)
            ->where('document_type', $documentType)
            ->where('scope_key', $scopeKey)
            ->where('status', '!=', 'CANCELLED')
            ->count();
        if ($identityCount !== 1) {
            $failures[] = 'duplicate numbering document identity for package/type/scope';
        }

        $package = $db->table('spj_packages')->where('id', $packageId)->first();
        if ($package === null) {
            $failures[] = 'package row is missing after issuance';
        } elseif ($appliesToPackage) {
            if (strtoupper((string) $package->status) !== 'NUMBERED'
                || (string) ($package->document_number ?? '') !== $documentNumber
                || $package->numbered_at === null) {
                $failures[] = 'package lifecycle did not reach NUMBERED with the issued number';
            }
        } elseif (strtoupper((string) $package->status) === 'FINAL') {
            $failures[] = 'package must never reach FINAL through numbering issuance';
        }

        $auditCount = $db->table('operational_audit_logs')->where('id', $auditId)->where('description', $auditDescription)->count();
        if ($auditCount !== 1) {
            $failures[] = 'numbering audit row is missing or duplicated';
        }

        $reservation = $db->table('spj_v2_numbering_reservations')->where('id', $reservationId)->first();
        if ($reservation === null || (string) $reservation->status !== 'COMPLETED'
            || (int) $reservation->sequence_number !== $sequenceNumber
            || (int) $reservation->spj_package_id !== $packageId) {
            $failures[] = 'sequence reservation is not COMPLETED for the issued number';
        }

        $legacyYear = (int) $db->table('transactions')->where('id', (int) ($package->transaction_id ?? 0))->value('fiscal_year_id');
        if ($legacyYear !== $legacyFiscalYearId) {
            $failures[] = 'stale legacy fiscal year was rewritten during issuance';
        }

        return ['passed' => $failures === [], 'failures' => $failures];
    }

    /**
     * Resume a COMPLETED reservation whose later writes may be missing after
     * a crash. Every fact is re-derived from the stored reservation; anything
     * already written must match exactly, anything missing is written once.
     * Drift (reconciliation, source status, fund mismatch) fails closed.
     *
     * @param  array<string,mixed>  $definition
     * @param  callable(string,string):never  $fail
     * @return array{status:string,authorized:bool,reason:string,error_code:?string,package_id:int,document_type:?string,scope_key:string,sequence_number:?int,document_number:?string,document_id:?int,reservation_id:?int,audit_id:?int,idempotent:bool}
     */
    private function resumeCompleted(SpjPackage $package, object $reservation, array $definition, int $legacyFiscalYearId, callable $fail): array
    {
        $db = DB::connection('school');
        $documentType = (string) $reservation->document_type;
        $scopeKey = (string) $reservation->scope_key;
        $sequenceNumber = (int) $reservation->sequence_number;
        $effectiveFiscalYearId = (int) $reservation->effective_fiscal_year_id;

        $package->loadMissing(['transaction.items', 'documents']);
        $transaction = $package->transaction;
        if ($transaction === null) {
            $fail('PACKAGE_TRANSACTION_MISSING', 'resume requires one package transaction');
        }
        if ((bool) $transaction->requires_reconciliation || strtoupper((string) ($transaction->source_status ?: 'ACTIVE')) === 'SOURCE_MISSING') {
            $fail('RESUME_DRIFT', 'transaction reconciliation or source status changed after reservation; refusing resume');
        }
        if ((int) $transaction->fund_source_id !== (int) $reservation->fund_source_id) {
            $fail('RESUME_DRIFT', 'fund source changed after reservation; refusing resume');
        }

        $effectiveYear = (int) $db->table('fiscal_years')->where('id', $effectiveFiscalYearId)->value('year');
        $eventDate = $this->eventDate($package, $definition, $documentType, $scopeKey, $effectiveYear.'-'.str_pad((string) ((int) $reservation->effective_quarter * 3), 2, '0', STR_PAD_LEFT).'-01', $fail);
        $documentNumber = $this->renderNumber($effectiveFiscalYearId, $documentType, $sequenceNumber, $eventDate, $fail);

        $appliesToPackage = ($definition['number_target']['relation'] ?? null) === 'package' && $scopeKey === 'MAIN';
        $document = $this->persistDocument($package, $documentType, $scopeKey, $documentNumber, $sequenceNumber, $eventDate, $fail);
        $this->applyTarget($package, $definition, $documentType, $scopeKey, $documentNumber, $document, $appliesToPackage, $fail);

        $auditDescription = $this->auditDescription($documentType, $documentNumber, $effectiveYear, (int) $reservation->effective_quarter, (string) $reservation->intent_key);
        $auditId = $this->recordAuditOnce($effectiveFiscalYearId, $appliesToPackage ? 'SPJ_PACKAGE' : 'SPJ_DOCUMENT', $appliesToPackage ? $package->id : $document->id, $auditDescription, $fail);

        $postCondition = $this->verifySinglePostCondition(
            (int) $package->id,
            $documentType,
            $scopeKey,
            $sequenceNumber,
            $documentNumber,
            (int) $document->id,
            (int) $reservation->id,
            $auditId,
            $auditDescription,
            $effectiveFiscalYearId,
            $legacyFiscalYearId,
            $appliesToPackage,
        );
        if (! $postCondition['passed']) {
            $fail('POSTCONDITION_FAILED', 'resume post-condition failed: '.implode('; ', $postCondition['failures']));
        }

        return $this->result('ISSUED', true, 'effective issuance resumed from the completed reservation without consuming a new sequence', null, (int) $package->id, $documentType, $scopeKey, $sequenceNumber, $documentNumber, (int) $document->id, (int) $reservation->id, $auditId, true);
    }

    /**
     * @param  callable(string,string):never  $fail
     */
    private function renderNumber(int $effectiveFiscalYearId, string $documentType, int $sequenceNumber, Carbon $eventDate, callable $fail): string
    {
        $format = DocumentNumberFormat::query()
            ->where('fiscal_year_id', $effectiveFiscalYearId)
            ->where('document_type', $documentType)
            ->where('is_active', true)
            ->first();
        if ($format === null) {
            $fail('FORMAT_MISSING', 'active canonical document number format is not available for the effective fiscal year');
        }

        $school = $this->context->school();

        return $this->numbers->renderConfiguredNumber(
            $format,
            $documentType,
            $sequenceNumber,
            $eventDate,
            $school->school_code ?: $school->npsn,
            $school->npsn,
        );
    }

    private function auditDescription(string $documentType, string $documentNumber, int $effectiveYear, int $quarter, string $intentKey): string
    {
        return 'Nomor '.$documentType.' '.$documentNumber.' ditetapkan pada konteks efektif '.$effectiveYear.' TW'.$quarter.' [efektif V2 '.$intentKey.'].';
    }

    /**
     * Exactly-once audit insert keyed by the deterministic description.
     *
     * @param  callable(string,string):never  $fail
     */
    private function recordAuditOnce(int $effectiveFiscalYearId, string $entityType, string|int $entityId, string $description, callable $fail): int
    {
        $db = DB::connection('school');
        $existing = (int) $db->table('operational_audit_logs')->where([
            'fiscal_year_id' => $effectiveFiscalYearId,
            'entity_type' => $entityType,
            'entity_id' => (string) $entityId,
            'action' => 'TETAPKAN_NOMOR_V2',
            'description' => $description,
        ])->orderByDesc('id')->value('id');
        if ($existing > 0) {
            return $existing;
        }
        $this->audit->record($effectiveFiscalYearId, $entityType, $entityId, 'TETAPKAN_NOMOR_V2', $description);
        $auditId = (int) $db->table('operational_audit_logs')->where([
            'fiscal_year_id' => $effectiveFiscalYearId,
            'entity_type' => $entityType,
            'entity_id' => (string) $entityId,
            'action' => 'TETAPKAN_NOMOR_V2',
            'description' => $description,
        ])->orderByDesc('id')->value('id');
        if ($auditId < 1) {
            $fail('AUDIT_MISSING', 'numbering audit row was not persisted');
        }

        return $auditId;
    }

    /**
     * @param  array<string,mixed>  $definition
     * @param  callable(string,string):never  $fail
     */
    private function eventDate(SpjPackage $package, array $definition, string $documentType, string $scopeKey, mixed $dateBasis, callable $fail): Carbon
    {
        if (str_starts_with($scopeKey, 'TRAVEL-')) {
            $travel = $package->transaction->travels->firstWhere('id', (int) substr($scopeKey, strlen('TRAVEL-')));
            if ($travel === null) {
                $fail('TRAVEL_SCOPE_INVALID', 'travel scope does not resolve to a package travel row');
            }
            $rule = $definition['event_date_rule'];
            $value = ($travel->{$rule['field']} ?? null) ?: ($rule['fallback_field'] ? ($travel->{$rule['fallback_field']} ?? null) : null);
            if ($value === null || trim((string) $value) === '') {
                $fail('EVENT_DATE_MISSING', 'travel event date is unavailable under the registry rule');
            }
            try {
                return Carbon::parse($value);
            } catch (Throwable) {
                $fail('EVENT_DATE_INVALID', 'travel event date cannot be parsed');
            }
        }

        $value = $this->order->documentEventDateValue($package, $documentType) ?? $dateBasis;
        if ($value === null || trim((string) $value) === '') {
            $fail('EVENT_DATE_MISSING', 'document event date is unavailable under the registry rule');
        }
        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            $fail('EVENT_DATE_INVALID', 'document event date cannot be parsed');
        }
    }

    /**
     * @param  callable(string,string):never  $fail
     */
    private function persistDocument(SpjPackage $package, string $documentType, string $scopeKey, string $documentNumber, int $sequenceNumber, Carbon $eventDate, callable $fail): SpjDocument
    {
        $existing = SpjDocument::query()
            ->where('spj_package_id', $package->id)
            ->where('document_type', $documentType)
            ->where('scope_key', $scopeKey)
            ->where('status', '!=', 'CANCELLED')
            ->latest('id')
            ->first();
        if ($existing instanceof SpjDocument && filled($existing->document_number)) {
            if ((string) $existing->document_number !== $documentNumber || (int) $existing->sequence_number !== $sequenceNumber) {
                $fail('DOCUMENT_MISMATCH', 'an active numbered document already exists with a different number; refusing overwrite');
            }

            return $existing;
        }

        $document = $existing ?? new SpjDocument([
            'spj_package_id' => $package->id,
            'document_type' => $documentType,
            'scope_key' => $scopeKey,
        ]);
        $document->fill([
            'document_number' => $documentNumber,
            'sequence_number' => $sequenceNumber,
            'document_date' => $eventDate,
            'event_date' => $eventDate,
            'status' => 'NUMBERED',
            'is_late_entry' => (bool) $package->is_late_entry,
            'numbered_at' => now(),
            'replaces_document_id' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'cancellation_reason' => null,
        ])->save();

        return $document;
    }

    /**
     * @param  array<string,mixed>  $definition
     * @param  callable(string,string):never  $fail
     */
    private function applyTarget(SpjPackage $package, array $definition, string $documentType, string $scopeKey, string $documentNumber, SpjDocument $document, bool $appliesToPackage, callable $fail): void
    {
        if ($appliesToPackage) {
            if ($package->status !== 'READY' && ! ($package->status === 'NUMBERED' && (string) ($package->document_number ?? '') === $documentNumber)) {
                $fail('PACKAGE_STATE_INVALID', 'package lifecycle state cannot accept the issued number');
            }
            if ($package->status === 'NUMBERED') {
                return;
            }
            $field = $definition['number_target']['field'] ?? null;
            $updates = [
                'status' => 'NUMBERED',
                'numbered_at' => now(),
                'cancelled_at' => null,
                'cancelled_by' => null,
                'cancellation_reason' => null,
            ];
            if ($field) {
                $updates[$field] = $documentNumber;
            }
            $package->forceFill($updates)->save();

            return;
        }

        $target = $definition['number_target']['relation'] ?? null;
        $targetField = $definition['number_target']['field'] ?? null;
        $transaction = $package->transaction;

        if ($target === 'goods' && $targetField) {
            $existing = $transaction->goods->pluck($targetField)->filter()->first();
            if ($existing && (string) $existing !== $documentNumber) {
                $fail('TARGET_MISMATCH', 'goods target already carries a different number');
            }
            $transaction->goods()->whereNull($targetField)->update([$targetField => $documentNumber]);
        } elseif ($target === 'workOrder' && $targetField) {
            $workOrder = $transaction->workOrder;
            if ($workOrder === null) {
                $fail('TARGET_MISSING', 'work order target is missing for the issued document');
            }
            if (filled($workOrder->{$targetField}) && (string) $workOrder->{$targetField} !== $documentNumber) {
                $fail('TARGET_MISMATCH', 'work order target already carries a different number');
            }
            $workOrder->forceFill([$targetField => $documentNumber])->save();
        } elseif ($target === 'travels' && $targetField) {
            $travel = $package->transaction->travels->firstWhere('id', (int) substr($scopeKey, strlen('TRAVEL-')));
            if ($travel === null) {
                $fail('TRAVEL_SCOPE_INVALID', 'travel scope does not resolve to a package travel row');
            }
            if (filled($travel->{$targetField}) && (string) $travel->{$targetField} !== $documentNumber) {
                $fail('TARGET_MISMATCH', 'travel target already carries a different number');
            }
            $travel->forceFill([$targetField => $documentNumber])->save();
        } elseif ($target !== null) {
            $fail('TARGET_UNSUPPORTED', 'document target relation is not supported by effective issuance');
        }
    }

    private function validScope(string $rule, string $scopeKey): bool
    {
        return $rule === 'MAIN' ? $scopeKey === 'MAIN' : preg_match('/^TRAVEL-[0-9]+$/', $scopeKey) === 1;
    }

    /** @return array<string,mixed> */
    private function result(string $status, bool $authorized, string $reason, ?string $errorCode, int $packageId, ?string $documentType, string $scopeKey, ?int $sequenceNumber, ?string $documentNumber, ?int $documentId, ?int $reservationId, ?int $auditId, bool $idempotent): array
    {
        return [
            'status' => $status,
            'authorized' => $authorized,
            'reason' => $reason,
            'error_code' => $errorCode,
            'package_id' => $packageId,
            'document_type' => $documentType,
            'scope_key' => $scopeKey,
            'sequence_number' => $sequenceNumber,
            'document_number' => $documentNumber,
            'document_id' => $documentId,
            'reservation_id' => $reservationId,
            'audit_id' => $auditId,
            'idempotent' => $idempotent,
        ];
    }
}
