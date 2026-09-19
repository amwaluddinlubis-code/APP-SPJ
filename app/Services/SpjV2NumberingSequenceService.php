<?php

namespace App\Services;

use App\Models\DocumentNumberFormat;
use App\Models\SpjPackage;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Reserve effective-context sequence slots without issuing a document number.
 *
 * The existing sequence scope is fiscal year + fund source + document format
 * + format reset period. Quarter is carried as proven context metadata and is
 * included in the key when a format explicitly resets quarterly; it is not
 * silently made a new business scope for the existing YEAR policy.
 */
final class SpjV2NumberingSequenceService
{
    public function __construct(
        private readonly SpjV2NumberingAuthorizationService $authorization,
        private readonly SpjNumberingPolicyService $numberingPolicy,
    ) {}

    /**
     * @return array{status:string,authorized:bool,reason:string,error_code:?string,reservation_id:?int,sequence_number:?int,context_key:?string,package_id:int,document_type:?string,scope_key:string}
     */
    public function reserve(SpjPackage $package, string $documentType, string $scopeKey = 'MAIN', ?string $intentKey = null): array
    {
        $documentType = $this->numberingPolicy->canonicalAutomaticDocumentType($documentType) ?? '';
        $intentKey = $intentKey === null ? '' : trim($intentKey);
        $blocked = fn (string $code, string $reason, ?array $context = null): array => $this->result(
            'BLOCKED',
            false,
            $reason,
            $code,
            null,
            null,
            $context['context_key'] ?? null,
            (int) $package->id,
            $documentType !== '' ? $documentType : null,
            $scopeKey,
        );

        if ($documentType === '') {
            return $blocked('DOCUMENT_TYPE_INVALID', 'document type is not registered for automatic numbering');
        }
        if ($intentKey === '' || mb_strlen($intentKey) > 180) {
            return $blocked('INTENT_INVALID', 'stable reservation intent identity is required');
        }

        $context = $this->context($package, $documentType, $scopeKey);
        if (! $context['authorized']) {
            return $blocked($context['error_code'], $context['reason']);
        }

        $db = DB::connection('school');
        try {
            return $db->transaction(function () use ($db, $package, $documentType, $scopeKey, $intentKey, $context): array {
                $existing = $db->table('spj_v2_numbering_reservations')
                    ->where('intent_key', $intentKey)
                    ->lockForUpdate()
                    ->first();
                if ($existing !== null) {
                    if (! $this->sameIdentity($existing, $package, $documentType, $scopeKey, $context)) {
                        return $this->result('BLOCKED', false, 'reservation intent belongs to a different package/context', 'RESERVATION_OWNER_MISMATCH', null, null, $context['context_key'], (int) $package->id, $documentType, $scopeKey);
                    }

                    return $this->rowResult($existing, $context, (int) $package->id, $documentType, $scopeKey);
                }

                $identityExists = $db->table('spj_v2_numbering_reservations')
                    ->where('spj_package_id', (int) $package->id)
                    ->where('document_type', $documentType)
                    ->where('scope_key', $scopeKey)
                    ->lockForUpdate()
                    ->first();
                if ($identityExists !== null) {
                    return $this->result('BLOCKED', false, 'package/document identity already has another reservation', 'RESERVATION_IDENTITY_EXISTS', null, null, $context['context_key'], (int) $package->id, $documentType, $scopeKey);
                }

                $sequence = $this->nextSequence($db, $context, $documentType);
                $id = $db->table('spj_v2_numbering_reservations')->insertGetId([
                    'spj_package_id' => (int) $package->id,
                    'effective_fiscal_year_id' => $context['effective_fiscal_year_id'],
                    'fund_source_id' => $context['effective_fund_source_id'],
                    'effective_quarter' => $context['quarter'],
                    'document_type' => $documentType,
                    'scope_key' => $scopeKey,
                    'period_key' => $context['period_key'],
                    'context_key' => $context['context_key'],
                    'intent_key' => $intentKey,
                    'sequence_number' => $sequence,
                    'status' => 'RESERVED',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return $this->result('RESERVED', true, 'sequence slot reserved; document issuance remains blocked', null, (int) $id, $sequence, $context['context_key'], (int) $package->id, $documentType, $scopeKey);
            });
        } catch (QueryException) {
            return $blocked('SEQUENCE_COLLISION', 'sequence reservation collided with another owner; no reservation was accepted');
        } catch (\RuntimeException) {
            return $blocked('SEQUENCE_COLLISION', 'sequence candidate is already occupied; no reservation was accepted');
        } catch (\Throwable) {
            return $blocked('RESERVATION_FAILED', 'sequence reservation failed atomically');
        }
    }

    /** @return array{status:string,authorized:bool,reason:string,error_code:?string,reservation_id:?int,sequence_number:?int,context_key:?string,package_id:int,document_type:?string,scope_key:string} */
    public function complete(SpjPackage $package, string $documentType, string $scopeKey, string $intentKey, int $sequenceNumber): array
    {
        $documentType = $this->numberingPolicy->canonicalAutomaticDocumentType($documentType) ?? '';
        $context = $this->context($package, $documentType, $scopeKey);
        if (! $context['authorized']) {
            return $this->result('BLOCKED', false, $context['reason'], $context['error_code'], null, null, null, (int) $package->id, $documentType !== '' ? $documentType : null, $scopeKey);
        }

        $db = DB::connection('school');

        return $db->transaction(function () use ($db, $package, $documentType, $scopeKey, $intentKey, $sequenceNumber, $context): array {
            $reservation = $db->table('spj_v2_numbering_reservations')->where('intent_key', trim($intentKey))->lockForUpdate()->first();
            if ($reservation === null) {
                return $this->result('BLOCKED', false, 'reservation was not found', 'RESERVATION_MISSING', null, null, $context['context_key'], (int) $package->id, $documentType, $scopeKey);
            }
            if (! $this->sameIdentity($reservation, $package, $documentType, $scopeKey, $context) || (int) $reservation->sequence_number !== $sequenceNumber) {
                return $this->result('BLOCKED', false, 'completion does not match reservation owner, context, or sequence', 'COMPLETION_MISMATCH', (int) $reservation->id, (int) $reservation->sequence_number, (string) $reservation->context_key, (int) $package->id, $documentType, $scopeKey);
            }
            if ($reservation->status === 'COMPLETED') {
                return $this->rowResult($reservation, $context, (int) $package->id, $documentType, $scopeKey);
            }
            if ($reservation->status !== 'RESERVED') {
                return $this->result('BLOCKED', false, 'cancelled reservation cannot be completed', 'RESERVATION_NOT_ACTIVE', (int) $reservation->id, (int) $reservation->sequence_number, (string) $reservation->context_key, (int) $package->id, $documentType, $scopeKey);
            }

            $db->table('spj_v2_numbering_reservations')->where('id', $reservation->id)->update([
                'status' => 'COMPLETED',
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->result('COMPLETED', true, 'sequence reservation completed without document issuance', null, (int) $reservation->id, $sequenceNumber, (string) $reservation->context_key, (int) $package->id, $documentType, $scopeKey);
        });
    }

    /** @return array{status:string,authorized:bool,reason:string,error_code:?string,reservation_id:?int,sequence_number:?int,context_key:?string,package_id:int,document_type:?string,scope_key:string} */
    public function cancel(SpjPackage $package, string $documentType, string $scopeKey, string $intentKey): array
    {
        $documentType = $this->numberingPolicy->canonicalAutomaticDocumentType($documentType) ?? '';
        $db = DB::connection('school');

        return $db->transaction(function () use ($db, $package, $documentType, $scopeKey, $intentKey): array {
            $reservation = $db->table('spj_v2_numbering_reservations')->where('intent_key', trim($intentKey))->lockForUpdate()->first();
            if ($reservation === null || ! $this->sameBasicIdentity($reservation, $package, $documentType, $scopeKey)) {
                return $this->result('BLOCKED', false, 'reservation owner or identity was not found', 'RESERVATION_OWNER_MISMATCH', null, null, null, (int) $package->id, $documentType, $scopeKey);
            }
            if ($reservation->status === 'COMPLETED') {
                return $this->result('BLOCKED', false, 'completed reservation cannot be cancelled by preflight subsystem', 'RESERVATION_COMPLETED', (int) $reservation->id, (int) $reservation->sequence_number, (string) $reservation->context_key, (int) $package->id, $documentType, $scopeKey);
            }
            if ($reservation->status === 'CANCELLED') {
                return $this->rowResult($reservation, [], (int) $package->id, $documentType, $scopeKey);
            }

            $db->table('spj_v2_numbering_reservations')->where('id', $reservation->id)->update([
                'status' => 'CANCELLED',
                'cancelled_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->result('CANCELLED', true, 'reservation cancelled; sequence remains consumed under existing gap policy', null, (int) $reservation->id, (int) $reservation->sequence_number, (string) $reservation->context_key, (int) $package->id, $documentType, $scopeKey);
        });
    }

    /** @return array<string,mixed> */
    private function context(SpjPackage $package, string $documentType, string $scopeKey): array
    {
        if ($documentType === '') {
            return ['authorized' => false, 'error_code' => 'DOCUMENT_TYPE_INVALID', 'reason' => 'document type is invalid'];
        }
        $authorization = $this->authorization->authorize($package, [$documentType]);
        if (! $authorization['authorized']) {
            return ['authorized' => false, 'error_code' => 'AUTHORIZATION_BLOCKED', 'reason' => $authorization['reason']];
        }
        if (($authorization['path'] ?? null) !== 'v2_authorized_preflight') {
            return ['authorized' => false, 'error_code' => 'LEGACY_RESERVATION_DISABLED', 'reason' => 'effective reservation is disabled on the legacy selector path'];
        }
        $definition = $this->numberingPolicy->numberingDefinition($documentType);
        if ($definition === null || ! $this->validScope($definition['scope_rule'], $scopeKey)) {
            return ['authorized' => false, 'error_code' => 'SCOPE_INVALID', 'reason' => 'document scope is invalid for the registry definition'];
        }
        $format = DocumentNumberFormat::query()->where('fiscal_year_id', $authorization['effective_fiscal_year_id'])->where('document_type', $documentType)->where('is_active', true)->first();
        if ($format === null) {
            return ['authorized' => false, 'error_code' => 'FORMAT_MISSING', 'reason' => 'active canonical document number format is not available'];
        }
        $periodKey = $this->periodKey(
            (string) $format->reset_period,
            (int) ($authorization['effective_fiscal_year'] ?? 0),
            (int) $authorization['quarter'],
            (string) ($authorization['date_basis'] ?? ''),
        );
        if ($periodKey === null) {
            return ['authorized' => false, 'error_code' => 'PERIOD_KEY_UNPROVEN', 'reason' => 'numbering format period key cannot be derived from proven effective facts'];
        }

        return [
            'authorized' => true,
            'effective_fiscal_year_id' => (int) $authorization['effective_fiscal_year_id'],
            'effective_fund_source_id' => (int) $authorization['effective_fund_source_id'],
            'quarter' => (int) $authorization['quarter'],
            'period_key' => $periodKey,
            'context_key' => implode('|', [(int) $authorization['effective_fiscal_year_id'], (int) $authorization['effective_fund_source_id'], $documentType, $periodKey]),
        ];
    }

    private function nextSequence(object $db, array $context, string $documentType): int
    {
        $key = [
            'fiscal_year_id' => $context['effective_fiscal_year_id'],
            'fund_source_id' => $context['effective_fund_source_id'],
            'format_name' => $documentType,
            'period_key' => $context['period_key'],
        ];
        $sequence = $db->table('document_number_sequences')->where($key)->lockForUpdate()->first();
        if ($sequence === null) {
            $db->table('document_number_sequences')->insert($key + ['last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $sequence = $db->table('document_number_sequences')->where($key)->lockForUpdate()->first();
        }
        $candidate = max(
            (int) $sequence->last_number,
            $this->maxDocumentSequence($db, $context, $documentType),
        ) + 1;
        $reservationKey = [
            'effective_fiscal_year_id' => $context['effective_fiscal_year_id'],
            'fund_source_id' => $context['effective_fund_source_id'],
            'document_type' => $documentType,
            'period_key' => $context['period_key'],
            'sequence_number' => $candidate,
        ];
        $occupied = $db->table('spj_v2_numbering_reservations')->where($reservationKey)->exists();
        if ($occupied || $this->documentSequenceOccupied($db, $context, $documentType, $candidate)) {
            throw new \RuntimeException('sequence candidate is occupied');
        }
        $db->table('document_number_sequences')->where('id', $sequence->id)->update(['last_number' => $candidate, 'updated_at' => now()]);

        return $candidate;
    }

    private function validScope(string $rule, string $scopeKey): bool
    {
        return $rule === 'MAIN' ? $scopeKey === 'MAIN' : preg_match('/^TRAVEL-[0-9]+$/', $scopeKey) === 1;
    }

    private function periodKey(string $resetPeriod, int $year, int $quarter, string $dateBasis): ?string
    {
        if ($year < 1 || $dateBasis === '') {
            return null;
        }

        return match (strtoupper($resetPeriod)) {
            'QUARTER' => $year.'-Q'.$quarter,
            'MONTH' => $year.'-'.substr($dateBasis, 5, 2),
            'NONE' => 'ALL',
            default => (string) $year,
        };
    }

    private function documentSequenceOccupied(object $db, array $context, string $documentType, int $candidate): bool
    {
        if (! $db->getSchemaBuilder()->hasTable('spj_transactions')) {
            throw new \LogicException('canonical V2 transaction schema is unavailable');
        }

        $rows = $db->table('spj_documents as documents')
            ->join('spj_packages as packages', 'packages.id', '=', 'documents.spj_package_id')
            ->join('spj_transactions as canonical', 'canonical.id', '=', 'packages.spj_transaction_id')
            ->where('canonical.fiscal_year_id', $context['effective_fiscal_year_id'])
            ->where('canonical.fund_source_id', $context['effective_fund_source_id'])
            ->where('documents.document_type', $documentType)
            ->where('documents.status', '!=', 'CANCELLED')
            ->where('documents.sequence_number', $candidate)
            ->get(['documents.document_date']);

        foreach ($rows as $row) {
            if ($this->periodKeyForDate($context['period_key'], $row->document_date) === $context['period_key']) {
                return true;
            }
        }

        return false;
    }

    private function maxDocumentSequence(object $db, array $context, string $documentType): int
    {
        if (! $db->getSchemaBuilder()->hasTable('spj_transactions')) {
            throw new \LogicException('canonical V2 transaction schema is unavailable');
        }

        $rows = $db->table('spj_documents as documents')
            ->join('spj_packages as packages', 'packages.id', '=', 'documents.spj_package_id')
            ->join('spj_transactions as canonical', 'canonical.id', '=', 'packages.spj_transaction_id')
            ->where('canonical.fiscal_year_id', $context['effective_fiscal_year_id'])
            ->where('canonical.fund_source_id', $context['effective_fund_source_id'])
            ->where('documents.document_type', $documentType)
            ->where('documents.status', '!=', 'CANCELLED')
            ->whereNotNull('documents.sequence_number')
            ->get(['documents.sequence_number', 'documents.document_date']);

        return (int) $rows
            ->filter(fn (object $row): bool => $this->periodKeyForDate($context['period_key'], $row->document_date) === $context['period_key'])
            ->max(fn (object $row): int => (int) $row->sequence_number);
    }

    private function periodKeyForDate(string $contextPeriodKey, mixed $date): ?string
    {
        if ($date === null || trim((string) $date) === '') {
            return null;
        }
        $year = substr($contextPeriodKey, 0, 4);
        if (preg_match('/^\d{4}$/', $contextPeriodKey) === 1) {
            return substr((string) $date, 0, 4);
        }
        if (preg_match('/^\d{4}-Q[1-4]$/', $contextPeriodKey) === 1) {
            $month = (int) substr((string) $date, 5, 2);

            return $year.'-Q'.(int) ceil($month / 3);
        }
        if (preg_match('/^\d{4}-\d{2}$/', $contextPeriodKey) === 1) {
            return substr((string) $date, 0, 7);
        }

        return $contextPeriodKey === 'ALL' ? 'ALL' : null;
    }

    private function sameIdentity(object $row, SpjPackage $package, string $documentType, string $scopeKey, array $context): bool
    {
        return $this->sameBasicIdentity($row, $package, $documentType, $scopeKey)
            && (string) $row->context_key === (string) $context['context_key'];
    }

    private function sameBasicIdentity(object $row, SpjPackage $package, string $documentType, string $scopeKey): bool
    {
        return (int) $row->spj_package_id === (int) $package->id
            && (string) $row->document_type === $documentType
            && (string) $row->scope_key === $scopeKey;
    }

    /** @return array{status:string,authorized:bool,reason:string,error_code:?string,reservation_id:?int,sequence_number:?int,context_key:?string,package_id:int,document_type:?string,scope_key:string} */
    private function rowResult(object $row, array $context, int $packageId, string $documentType, string $scopeKey): array
    {
        return $this->result((string) $row->status, in_array((string) $row->status, ['RESERVED', 'COMPLETED'], true), 'existing reservation is idempotent', null, (int) $row->id, (int) $row->sequence_number, (string) $row->context_key, $packageId, $documentType, $scopeKey);
    }

    /** @return array<string,mixed> */
    private function result(string $status, bool $authorized, string $reason, ?string $errorCode, ?int $reservationId, ?int $sequenceNumber, ?string $contextKey, int $packageId, ?string $documentType, string $scopeKey): array
    {
        return [
            'status' => $status,
            'authorized' => $authorized,
            'reason' => $reason,
            'error_code' => $errorCode,
            'reservation_id' => $reservationId,
            'sequence_number' => $sequenceNumber,
            'context_key' => $contextKey,
            'package_id' => $packageId,
            'document_type' => $documentType,
            'scope_key' => $scopeKey,
        ];
    }
}
