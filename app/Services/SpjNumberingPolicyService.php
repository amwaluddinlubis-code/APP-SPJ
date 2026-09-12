<?php

namespace App\Services;

use App\Models\DocumentNumberFormat;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Runtime policy adapter for the canonical numbering registry.
 *
 * Document identity, labels, categories, event-date rules and target number
 * fields live in SpjNumberingDocumentRegistry. This service only applies
 * runtime conditions such as the SiPlah channel and persists number formats.
 */
class SpjNumberingPolicyService
{
    public function __construct(
        private readonly SpjProcurementPolicyService $procurementPolicy,
        private readonly SpjNumberingDocumentRegistry $registry,
    ) {}

    /** @return list<string> */
    public function automaticDocumentTypes(): array
    {
        return $this->registry->numberedCodes();
    }

    /** @return array<string,string> */
    public function automaticDocumentLabels(): array
    {
        return $this->registry->numberedLabels();
    }

    /** @return array<string,array<string,mixed>> */
    public function numberingDefinitions(): array
    {
        return $this->registry->all();
    }

    /** @return array<string,mixed>|null */
    public function numberingDefinition(string $documentType): ?array
    {
        return $this->registry->get($documentType);
    }

    public function canonicalAutomaticDocumentType(string $documentType): ?string
    {
        $canonical = $this->registry->canonical($documentType);
        $definition = $canonical ? $this->registry->get($canonical) : null;

        return $definition && $definition['numbered'] === true ? $canonical : null;
    }

    public function isAutomaticDocumentType(string $documentType): bool
    {
        return $this->canonicalAutomaticDocumentType($documentType) !== null;
    }

    public function isAutomaticDocumentEligible(Transaction $transaction, string $documentType): bool
    {
        $definition = $this->numberingDefinition($documentType);
        if (! $definition || $definition['numbered'] !== true) {
            return false;
        }

        $category = $this->canonicalCategory((string) $transaction->spj_category);
        if (! in_array($category, $definition['applicable_categories'], true)) {
            return false;
        }

        return match ($definition['channel']) {
            SpjNumberingDocumentRegistry::CHANNEL_NON_SIPLAH => ! $this->procurementPolicy->isSiplah($transaction),
            default => true,
        };
    }

    public function documentEventDateValue(Transaction $transaction, string $documentType, string $scopeKey = 'MAIN'): mixed
    {
        $definition = $this->numberingDefinition($documentType);
        if (! $definition) {
            return null;
        }

        $rule = $definition['event_date_rule'];
        $field = $rule['field'];
        $fallbackField = $rule['fallback_field'];

        return match ($rule['relation']) {
            'transaction' => $transaction->{$field},
            'goods' => $transaction->goods->pluck($field)->filter()->sort()->first()
                ?: ($fallbackField ? $transaction->goods->pluck($fallbackField)->filter()->sort()->first() : null),
            'workOrder' => $transaction->workOrder?->{$field}
                ?: ($fallbackField ? $transaction->workOrder?->{$fallbackField} : null),
            'travels' => $this->travelEventDateValue($transaction, $scopeKey, $field, $fallbackField),
            default => null,
        };
    }

    /**
     * Persist canonical defaults for numbered document types without
     * overwriting any format already customized by the school/operator.
     *
     * @return Collection<int, DocumentNumberFormat>
     */
    public function ensureAutomaticFormats(int $fiscalYearId): Collection
    {
        return collect($this->automaticDocumentTypes())
            ->map(fn (string $documentType): DocumentNumberFormat => $this->formatFor($fiscalYearId, $documentType));
    }

    public function formatFor(int $fiscalYearId, string $documentType): DocumentNumberFormat
    {
        $documentType = $this->canonicalAutomaticDocumentType($documentType);
        if ($documentType === null) {
            throw new InvalidArgumentException('Jenis dokumen tidak termasuk domain penomoran canonical aplikasi.');
        }

        return DocumentNumberFormat::query()->firstOrCreate(
            [
                'fiscal_year_id' => $fiscalYearId,
                'document_type' => $documentType,
            ],
            $this->defaultFormat($documentType),
        );
    }

    /** @return array{format_pattern:string,reset_period:string,padding:int,is_active:bool} */
    public function defaultFormat(string $documentType): array
    {
        $canonical = $this->canonicalAutomaticDocumentType($documentType);
        if ($canonical === null) {
            throw new InvalidArgumentException('Jenis dokumen tidak termasuk domain penomoran canonical aplikasi.');
        }

        return [
            'format_pattern' => '{SEQ}/'.$canonical.'/{SCHOOL}/{TW}/{YEAR}',
            'reset_period' => 'YEAR',
            'padding' => 4,
            'is_active' => true,
        ];
    }

    public function canonicalCategory(string $category): string
    {
        $category = strtoupper(trim($category));

        return match ($category) {
            'BELANJA_MODAL' => 'BARANG',
            'PERJALANAN_DINAS' => 'SPPD',
            'JASA_HONORARIUM' => 'HONOR_PEGAWAI',
            'UPAH' => 'PEMELIHARAAN',
            'LAINNYA' => 'JASA_LAINNYA',
            default => $category,
        };
    }

    private function travelEventDateValue(Transaction $transaction, string $scopeKey, string $field, ?string $fallbackField): mixed
    {
        if (str_starts_with($scopeKey, 'TRAVEL-')) {
            $travelId = (int) substr($scopeKey, strlen('TRAVEL-'));
            $travel = $transaction->travels->firstWhere('id', $travelId);

            return $travel?->{$field} ?: ($fallbackField ? $travel?->{$fallbackField} : null);
        }

        return $transaction->travels->pluck($field)->filter()->sort()->first()
            ?: ($fallbackField ? $transaction->travels->pluck($fallbackField)->filter()->sort()->first() : null);
    }
}
