<?php

namespace App\Services;

use App\Models\SpjDocument;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Support\ActiveSpjContext;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Atomic effective-context NUMBERED -> FINAL authority.
 *
 * The legacy lifecycle remains available for the legacy selector. V2 never
 * falls back to it: every effective identity, document, period, and audit
 * fact must pass before any FINAL mutation is committed.
 */
final class SpjV2FinalizationService
{
    public function __construct(
        private readonly ActiveSpjContext $context,
        private readonly SpjReadPathSelector $readPaths,
        private readonly SpjV2EffectiveContextCompatibilityService $effectiveContexts,
        private readonly SpjV2CanonicalReadService $canonicalReads,
        private readonly SpjV2EffectiveNumberingPeriodResolver $periodResolver,
        private readonly SpjNumberingDocumentRegistry $registry,
        private readonly SpjNumberingPolicyService $numberingPolicy,
    ) {}

    /** @return array{status:string,authorized:bool,reason:string,error_code:?string,package_id:int,audit_id:?int,idempotent:bool} */
    public function finalize(SpjPackage $package): array
    {
        try {
            return DB::connection('school')->transaction(
                fn (): array => $this->finalizeCore((int) $package->id),
            );
        } catch (SpjV2FinalizationBlocked $blocked) {
            return $this->result('BLOCKED', false, $blocked->getMessage(), $blocked->errorCode, (int) $package->id);
        } catch (Throwable $failure) {
            return $this->result('BLOCKED', false, 'effective finalization failed atomically; package remains NUMBERED: '.$failure->getMessage(), 'FINALIZATION_FAILED', (int) $package->id);
        }
    }

    /** @return array{status:string,authorized:bool,reason:string,error_code:?string,package_id:int,audit_id:?int,idempotent:bool} */
    private function finalizeCore(int $packageId): array
    {
        $db = DB::connection('school');
        $fail = fn (string $code, string $reason): never => throw new SpjV2FinalizationBlocked($code, $reason);

        $package = SpjPackage::query()
            ->with([
                'documents.template',
                'transaction.items',
                'transaction.goods',
                'transaction.goodsReceipts',
                'transaction.participants',
                'transaction.travels',
                'transaction.honors',
                'transaction.payments',
                'transaction.serviceRecipients',
                'transaction.workOrder.workers',
            ])
            ->lockForUpdate()
            ->find($packageId);
        if (! $package instanceof SpjPackage) {
            $fail('PACKAGE_MISSING', 'package does not exist in the active tenant database');
        }

        if ($package->status === 'FINAL') {
            $auditId = $this->existingFinalAudit($db, $packageId);
            if ($auditId === null) {
                $fail('FINAL_AUDIT_MISSING', 'package is FINAL but its effective FINAL audit is missing');
            }

            return $this->result('FINALIZED', true, 'effective FINAL already committed; retry is idempotent', null, $packageId, $auditId, true);
        }
        if ($package->status !== 'NUMBERED') {
            $fail('PACKAGE_NOT_NUMBERED', 'only a NUMBERED package may transition to FINAL');
        }

        $fundSourceId = $this->context->fundSourceId();
        if ($fundSourceId === null) {
            $fail('FUND_CONTEXT_MISSING', 'active fund-source context is required');
        }
        $selection = $this->readPaths->select($db, $this->context->fiscalYearId(), $fundSourceId);
        if ($selection['requested'] !== SpjReadPathSelector::V2 || $selection['path'] !== SpjReadPathSelector::V2 || $selection['source_id'] === null) {
            $fail('V2_SELECTOR_UNAVAILABLE', 'effective FINAL requires an explicit, uniquely resolved V2 selector');
        }

        $transaction = $package->transaction;
        if (! $transaction instanceof Transaction) {
            $fail('PACKAGE_TRANSACTION_MISSING', 'effective FINAL requires one package transaction');
        }
        if ((int) $transaction->fund_source_id !== $fundSourceId) {
            $fail('FUND_SOURCE_MISMATCH', 'legacy package fund source does not match active effective fund source');
        }
        if ((bool) $transaction->requires_reconciliation || strtoupper((string) ($transaction->source_status ?: 'ACTIVE')) === 'SOURCE_MISSING') {
            $fail('RECONCILIATION_BLOCKED', 'reconciliation or source status is not clear');
        }

        $resolved = $this->effectiveContexts->resolve($db, $this->context->fiscalYearId(), $fundSourceId, (int) $selection['source_id']);
        if ($resolved['status'] !== 'RESOLVED') {
            $fail('EFFECTIVE_CONTEXT_UNRESOLVED', 'effective membership/provenance context is not uniquely resolved');
        }
        $canonicalId = (int) ($package->getAttribute('spj_transaction_id') ?? 0);
        if ($canonicalId < 1
            || ! in_array($packageId, array_map('intval', $resolved['package_ids'] ?? []), true)
            || ! in_array((int) $package->transaction_id, array_map('intval', $resolved['legacy_transaction_ids'] ?? []), true)
            || ! in_array($canonicalId, array_map('intval', $resolved['canonical_transaction_ids'] ?? []), true)) {
            $fail('EFFECTIVE_MEMBERSHIP_INVALID', 'package is not a member of the active effective context');
        }

        $bridges = $db->table('legacy_transaction_v2_map')->where('legacy_transaction_id', (int) $package->transaction_id)->get();
        $exact = $bridges->filter(fn (object $row): bool => (int) $row->spj_transaction_id === $canonicalId);
        if ($bridges->count() !== 1 || $exact->count() !== 1 || ! in_array((string) $exact->first()->mapping_status, ['EXACT', 'DETERMINISTIC'], true)) {
            $fail('PROVENANCE_BRIDGE_INVALID', 'package/V2 provenance bridge is missing, duplicate, or ambiguous');
        }

        $canonicalLock = $db->table('spj_transactions')->where('id', $canonicalId)->lockForUpdate()->first();
        if ($canonicalLock === null) {
            $fail('CANONICAL_SOURCE_INVALID', 'canonical transaction disappeared before FINAL mutation');
        }

        $canonical = $this->canonicalReads->forContext($db, $this->context->fiscalYearId(), $fundSourceId, (int) $selection['source_id'])
            ->first(fn (array $row): bool => (int) $row['id'] === $canonicalId);
        if (! is_array($canonical) || strtoupper((string) ($canonical['canonical_context_status'] ?? '')) !== 'ACTIVE_CANONICAL' || strtoupper((string) ($canonical['source_status'] ?? '')) !== 'ACTIVE') {
            $fail('CANONICAL_SOURCE_INVALID', 'canonical source identity is missing or inactive');
        }
        if (! $this->sourceFactsMatch($canonical, $transaction)) {
            $fail('SOURCE_PARITY_DRIFT', 'canonical transaction/source facts are not in parity');
        }
        if (! $this->itemFactsMatch($canonical, $transaction)) {
            $fail('ITEM_PARITY_DRIFT', 'canonical item facts are not in parity');
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
            $fail('EFFECTIVE_PERIOD_CLOSED', 'effective quarter closed before FINAL mutation');
        }
        $this->assertNumberingPostCondition($package, $fail);

        $capturedAt = now();
        $packageSnapshot = $package->toArray();
        foreach ($package->documents->where('status', '!=', 'CANCELLED') as $document) {
            if ($document->status === 'FINAL') {
                continue;
            }
            $template = $document->template;
            $templatePath = $template ? storage_path('app/'.$template->file_path) : null;
            $document->forceFill([
                'status' => 'FINAL',
                'snapshot' => [
                    'document' => $document->only(['document_type', 'document_number', 'document_date', 'event_date', 'scope_key']),
                    'package' => $packageSnapshot,
                    'effective_context' => $period,
                    'captured_at' => $capturedAt->toIso8601String(),
                ],
                'template_snapshot' => $template?->only(['id', 'document_type', 'name', 'format', 'file_path', 'applicable_categories', 'updated_at']),
                'template_hash' => $templatePath && is_file($templatePath) ? hash_file('sha256', $templatePath) : null,
                'finalized_at' => $capturedAt,
                'finalized_by' => $this->context->actorId(),
            ])->save();
        }

        $package->forceFill([
            'status' => 'FINAL',
            'snapshot' => [
                'package' => $packageSnapshot,
                'effective_context' => $period,
                'captured_at' => $capturedAt->toIso8601String(),
            ],
            'finalized_at' => $capturedAt,
            'finalized_by' => $this->context->actorId(),
        ])->save();

        $auditId = $this->recordFinalAudit($db, $package, $period, $fail);
        $this->verifyPostCondition($db, $packageId, $transaction, $period, $auditId, $fail);

        return $this->result('FINALIZED', true, 'effective NUMBERED -> FINAL committed atomically', null, $packageId, $auditId);
    }

    /** @param callable(string,string):never $fail */
    private function assertNumberingPostCondition(SpjPackage $package, callable $fail): void
    {
        if (blank($package->document_number) || $package->numbered_at === null) {
            $fail('NUMBERING_POSTCONDITION_INVALID', 'package numbering post-condition is not valid');
        }
        $active = $package->documents->where('status', '!=', 'CANCELLED');
        if ($active->isEmpty()) {
            $fail('DOCUMENTS_MISSING', 'package has no active numbered documents');
        }
        $seen = [];
        foreach ($active as $document) {
            $type = $this->registry->canonical((string) $document->document_type);
            $scope = trim((string) $document->scope_key);
            if ($type === null || blank($document->document_number) || ! in_array($document->status, ['NUMBERED', 'FINAL'], true)) {
                $fail('NUMBERING_POSTCONDITION_INVALID', 'an active document is missing a valid number/status');
            }
            if ($scope !== 'MAIN' && ! preg_match('/^TRAVEL(?:[:-])[0-9]+$/', $scope)) {
                $fail('DOCUMENT_SCOPE_INVALID', 'an active document has an invalid scope');
            }
            $identity = $type.'|'.$scope;
            if (isset($seen[$identity])) {
                $fail('DOCUMENT_IDENTITY_DUPLICATE', 'duplicate active document identity prevents FINAL');
            }
            $seen[$identity] = true;
        }

        foreach ($this->requiredDocumentIdentities($package) as $identity) {
            $present = $active->contains(fn (SpjDocument $document): bool => $this->registry->canonical((string) $document->document_type) === $identity['document_type']
                && (string) $document->scope_key === $identity['scope_key']
                && filled($document->document_number)
                && in_array($document->status, ['NUMBERED', 'FINAL'], true));
            if (! $present) {
                $fail('DOCUMENT_COMPLETENESS_INVALID', 'required document '.$identity['document_type'].'/'.$identity['scope_key'].' is missing');
            }
        }
    }

    /** @return list<array{document_type:string,scope_key:string}> */
    private function requiredDocumentIdentities(SpjPackage $package): array
    {
        $requirements = [];
        foreach ($this->numberingPolicy->automaticDocumentTypes() as $documentType) {
            if (! $this->numberingPolicy->isAutomaticDocumentEligible($package->transaction, $documentType)) {
                continue;
            }
            $definition = $this->numberingPolicy->numberingDefinition($documentType);
            if ($definition === null) {
                continue;
            }
            if ($definition['scope_rule'] === 'TRAVEL') {
                foreach ($package->transaction->travels as $travel) {
                    $scopeKey = 'TRAVEL-'.$travel->id;
                    if (filled($this->numberingPolicy->documentEventDateValue($package->transaction, $documentType, $scopeKey))) {
                        $requirements[] = ['document_type' => $documentType, 'scope_key' => $scopeKey];
                    }
                }

                continue;
            }
            if (filled($this->numberingPolicy->documentEventDateValue($package->transaction, $documentType))) {
                $requirements[] = ['document_type' => $documentType, 'scope_key' => 'MAIN'];
            }
        }

        return $requirements;
    }

    /** @param callable(string,string):never $fail */
    private function recordFinalAudit(Connection $db, SpjPackage $package, array $period, callable $fail): int
    {
        if (! $db->getSchemaBuilder()->hasTable('operational_audit_logs')) {
            $fail('AUDIT_UNAVAILABLE', 'FINAL audit table is unavailable');
        }
        $description = 'Paket difinalkan pada konteks efektif '.$period['effective_fiscal_year'].' TW'.$period['effective_quarter'].' [V2].';
        $query = $db->table('operational_audit_logs')->where([
            'fiscal_year_id' => (int) $period['effective_fiscal_year_id'],
            'entity_type' => 'SPJ_PACKAGE',
            'entity_id' => (string) $package->id,
            'action' => 'FINALISASI_PAKET_V2',
            'description' => $description,
        ]);
        $existing = (int) $query->value('id');
        if ($existing > 0) {
            return $existing;
        }
        $db->table('operational_audit_logs')->insert([
            'fiscal_year_id' => (int) $period['effective_fiscal_year_id'],
            'entity_type' => 'SPJ_PACKAGE',
            'entity_id' => (string) $package->id,
            'action' => 'FINALISASI_PAKET_V2',
            'description' => $description,
            'user_id' => $this->context->actorId(),
            'created_at' => now(),
        ]);
        $auditId = (int) $query->orderByDesc('id')->value('id');
        if ($auditId < 1) {
            $fail('AUDIT_WRITE_FAILED', 'FINAL audit could not be verified');
        }

        return $auditId;
    }

    private function existingFinalAudit(Connection $db, int $packageId): ?int
    {
        if (! $db->getSchemaBuilder()->hasTable('operational_audit_logs')) {
            return null;
        }

        $id = $db->table('operational_audit_logs')
            ->where('entity_type', 'SPJ_PACKAGE')
            ->where('entity_id', (string) $packageId)
            ->where('action', 'FINALISASI_PAKET_V2')
            ->orderByDesc('id')
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    /** @param array<string,mixed> $period @param callable(string,string):never $fail */
    private function verifyPostCondition(Connection $db, int $packageId, Transaction $transaction, array $period, int $auditId, callable $fail): void
    {
        $row = $db->table('spj_packages')->where('id', $packageId)->first();
        if ($row === null || strtoupper((string) $row->status) !== 'FINAL' || $row->finalized_at === null) {
            $fail('FINAL_POSTCONDITION_FAILED', 'package FINAL post-condition failed');
        }
        $activeDocuments = $db->table('spj_documents')->where('spj_package_id', $packageId)->where('status', '!=', 'CANCELLED')->get();
        if ($activeDocuments->isEmpty() || $activeDocuments->contains(fn (object $document): bool => strtoupper((string) $document->status) !== 'FINAL' || blank($document->document_number))) {
            $fail('FINAL_POSTCONDITION_FAILED', 'active documents FINAL post-condition failed');
        }
        if ((int) $db->table('transactions')->where('id', $transaction->id)->value('fiscal_year_id') !== (int) $transaction->fiscal_year_id) {
            $fail('LEGACY_FISCAL_YEAR_REWRITTEN', 'legacy fiscal year changed during FINAL');
        }
        if ((int) $db->table('operational_audit_logs')->where('id', $auditId)->where('action', 'FINALISASI_PAKET_V2')->count() !== 1) {
            $fail('FINAL_POSTCONDITION_FAILED', 'exactly one FINAL audit could not be verified');
        }
        if ((int) $period['effective_fiscal_year_id'] !== (int) $this->context->fiscalYearId()
            || (int) $period['effective_fund_source_id'] !== (int) $this->context->fundSourceId()) {
            $fail('FINAL_POSTCONDITION_FAILED', 'effective context changed during FINAL');
        }
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
        $legacyItems = $legacy->items;
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
        } catch (Throwable) {
            return null;
        }
    }

    private function normalize(mixed $value): ?string
    {
        $value = $value === null ? null : trim((string) $value);

        return $value === null || $value === '' ? null : $value;
    }

    /** @return array{status:string,authorized:bool,reason:string,error_code:?string,package_id:int,audit_id:?int,idempotent:bool} */
    private function result(string $status, bool $authorized, string $reason, ?string $errorCode, int $packageId, ?int $auditId = null, bool $idempotent = false): array
    {
        return [
            'status' => $status,
            'authorized' => $authorized,
            'reason' => $reason,
            'error_code' => $errorCode,
            'package_id' => $packageId,
            'audit_id' => $auditId,
            'idempotent' => $idempotent,
        ];
    }
}

final class SpjV2FinalizationBlocked extends \RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }
}
